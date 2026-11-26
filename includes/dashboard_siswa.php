<?php
// Dashboard pribadi untuk akun Siswa dan Guru (dipanggil dari index.php). Semua angka dan daftar HANYA berasal dari checklist milik akun itu sendiri.
// Siswa: dikaitkan lewat NIS (checklist.nis). Guru: dikaitkan lewat id akun (checklist.id_user).
$judul = 'Dashboard';
$nis   = pemilik_nilai();                  // nilai pemilik: NIS (Siswa) atau id akun (Guru)
$kol   = pemilik_kolom();                  // kolom pemilik di tabel checklist: 'nis' atau 'id_user'

if (is_guru()) {
    $sw = ['nama' => user()['nama'], 'kelas' => 'Guru'];
} else {
    $st = $pdo->prepare('SELECT nama, kelas FROM siswa WHERE nis = ?');
    $st->execute([$nis]);
    $sw = $st->fetch() ?: ['nama' => user()['nama'], 'kelas' => ''];
}
$nama  = $sw['nama'];
$kelas = $sw['kelas'];

$hariIni   = date('Y-m-d');
$awalBulan = date('Y-m-01');
$mulai7    = date('Y-m-d', strtotime('-6 days'));
$mulai30   = date('Y-m-d', strtotime('-29 days'));
$mulai60   = date('Y-m-d', strtotime('-59 days'));

// Semua query di bawah memakai 'nis = ?'; untuk Guru kolomnya diganti id_user supaya hanya checklist miliknya yang terbaca.
$q1 = function ($sql, array $p) use ($pdo, $kol) { $s = $pdo->prepare(str_replace('nis = ?', $kol . ' = ?', $sql)); $s->execute($p); return $s; };

// Checklist bulan ini (jumlah dan hari berbeda) dan kepatuhan "Ya" bulan ini
$r = $q1('SELECT COUNT(*) n, COUNT(DISTINCT tanggal) hari FROM checklist WHERE nis = ? AND tanggal >= ?', [$nis, $awalBulan])->fetch();
$jmlChecklist = (int)$r['n']; $jmlHari = (int)$r['hari'];
$r = $q1('SELECT COUNT(*) total, SUM(d.status = \'Ya\') ya FROM detail_checklist d
          JOIN checklist c ON c.id_checklist = d.id_checklist WHERE c.nis = ? AND c.tanggal >= ?', [$nis, $awalBulan])->fetch();
$persen = $r['total'] ? (int)round($r['ya'] / $r['total'] * 100) : null;

// Sudah mengisi hari ini?
$sudahHariIni = (int)$q1('SELECT COUNT(*) FROM checklist WHERE nis = ? AND tanggal = ?', [$nis, $hariIni])->fetchColumn();

// Hari beruntun: berapa hari kalender berturut-turut ada checklist (dihitung mundur dari hari ini;
// bila hari ini belum mengisi, dihitung dari kemarin supaya tidak langsung nol).
$tgl = array_column($q1('SELECT DISTINCT tanggal FROM checklist WHERE nis = ? AND tanggal >= ? ORDER BY tanggal DESC', [$nis, $mulai60])->fetchAll(), 'tanggal');
$punya = array_flip($tgl);
$beruntun = 0;
$t = isset($punya[$hariIni]) ? $hariIni : date('Y-m-d', strtotime('-1 day'));
while (isset($punya[$t])) { $beruntun++; $t = date('Y-m-d', strtotime($t . ' -1 day')); }

// Jumlah item berstatus "Tidak" milik sendiri bulan ini
$jmlTidak = (int)$q1('SELECT COUNT(*) FROM detail_checklist d JOIN checklist c ON c.id_checklist = d.id_checklist
          WHERE c.nis = ? AND c.tanggal >= ? AND d.status = \'Tidak\'', [$nis, $awalBulan])->fetchColumn();

// Grafik: persentase "Ya" milik sendiri per hari, 7 hari terakhir
$per = [];
foreach ($q1('SELECT c.tanggal, ROUND(100 * SUM(d.status = \'Ya\') / COUNT(*)) p FROM checklist c
              JOIN detail_checklist d ON d.id_checklist = c.id_checklist
              WHERE c.nis = ? AND c.tanggal >= ? GROUP BY c.tanggal', [$nis, $mulai7])->fetchAll() as $x) $per[$x['tanggal']] = (int)$x['p'];
$hariNama = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$grafik = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $grafik[] = [$hariNama[(int)date('w', strtotime($d))], $per[$d] ?? null];
}

// Perangkat yang paling sering kamu tandai "Tidak" (30 hari) dan 3 checklist terakhir; semuanya milik sendiri
$perhatian = $q1('SELECT p.nama_perangkat, COUNT(*) n FROM detail_checklist d
        JOIN perangkat p ON p.id_perangkat = d.id_perangkat JOIN checklist c ON c.id_checklist = d.id_checklist
        WHERE c.nis = ? AND d.status = \'Tidak\' AND c.tanggal >= ? GROUP BY p.id_perangkat ORDER BY n DESC LIMIT 3', [$nis, $mulai30])->fetchAll();
$terbaru = $q1('SELECT c.id_checklist, c.tanggal, c.jam, COUNT(d.id_detail) jml, SUM(d.status = \'Ya\') ya,
        GROUP_CONCAT(DISTINCT p.nama_perangkat ORDER BY p.nama_perangkat SEPARATOR \', \') perangkat
        FROM checklist c LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
        LEFT JOIN perangkat p ON p.id_perangkat = d.id_perangkat
        WHERE c.nis = ? GROUP BY c.id_checklist ORDER BY c.tanggal DESC, c.jam DESC LIMIT 3', [$nis])->fetchAll();

$tips = ['Matikan AC dan layar komputer 10 menit sebelum jam pulang.',
         'Pastikan lampu dan kipas mati saat ruangan kosong.',
         'Cabut charger dan perangkat yang tidak dipakai dari stopkontak.',
         'Atur AC di suhu sedang (24-26 derajat), bukan paling dingin.',
         'Gunakan mode hemat daya atau sleep saat komputer ditinggal sebentar.'];
$tip = $tips[(int)date('z') % count($tips)];
$namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$hariPanjang = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

$kartu = function ($ikon, $label, $nilai, $ket = '', $amber = false) {
    return '<div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-ic ' . ($amber ? 'a' : '') . '">' . ikon($ikon) . '</div>'
         . '<div class="stat-k">' . e($label) . '</div><div class="stat-v">' . e($nilai) . '</div>'
         . ($ket !== '' ? '<div class="muted small mt-1">' . e($ket) . '</div>' : '') . '</div></div></div>';
};

require __DIR__ . '/header.php';
?>
<?php if (!$nis): ?>
<div class="alert alert-warning">Akun Anda belum terhubung ke data siswa, jadi belum bisa membuat checklist. Hubungi admin.</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8"><div class="card hero h-100"><div class="card-body p-4 p-lg-5 d-flex flex-column justify-content-center gap-3">
    <div>
    <div class="muted small"><?= $hariPanjang[(int)date('w')] ?>, <?= e(tgl_id($hariIni)) ?><?= $kelas !== '' ? ' &middot; ' . e($kelas) : '' ?></div>
    <div class="judul-besar mt-2">Halo, <em><?= e($nama) ?></em></div>
    <div class="muted mt-1" style="max-width:560px"><?= $sudahHariIni
        ? 'Hari ini kamu sudah mengisi checklist (' . $sudahHariIni . ' kali). Terima kasih sudah ikut menghemat energi.'
        : 'Hari ini kamu belum mengisi checklist. Cek perangkat sebelum, selama, dan sesudah dipakai, lalu catat kondisinya.' ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-amber" href="<?= BASE_URL ?>/checklist/form.php">Buat checklist</a>
    <a class="btn btn-outline-light" href="<?= BASE_URL ?>/checklist/">Lihat riwayat</a>
  </div>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body d-flex flex-column align-items-center justify-content-center text-center gap-2 py-4">
    <h6 class="mb-1">Kepatuhan saya</h6>
    <?= gauge_ring($persen, 'item Ya') ?>
    <div class="muted small">bulan <?= $namaBulan[(int)date('n') - 1] ?></div>
  </div></div></div>
</div>

<div class="row g-3">
  <?= $kartu('list', 'Checklist bulan ini', $jmlChecklist, $jmlHari . ' hari berbeda') ?>
  <?= $kartu('bolt', 'Kepatuhan saya (Ya)', $persen === null ? '-' : $persen . '%', 'bulan ' . $namaBulan[(int)date('n') - 1], true) ?>
  <?= $kartu('list', 'Hari beruntun', $beruntun, 'hari berturut-turut mengisi') ?>
  <?= $kartu('siswa', 'Status Tidak bulan ini', $jmlTidak, $jmlTidak ? 'perlu diperbaiki' : 'semua aman', true) ?>
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-body">
    <h6 class="mb-3">Kepatuhan saya 7 hari terakhir</h6>
    <?= grafik_garis(array_column($grafik, 0), array_column($grafik, 1)) ?>
  </div></div></div>
  <div class="col-lg-5 d-flex flex-column gap-3">
    <div class="card"><div class="card-body"><h6 class="mb-2">Perangkat yang perlu kamu perhatikan</h6>
      <?php foreach ($perhatian as $x): ?>
        <div class="py-2 border-top"><?= e($x['nama_perangkat']) ?><div class="muted small">Kamu menandai Tidak <?= (int)$x['n'] ?> kali dalam 30 hari</div></div>
      <?php endforeach; if (!$perhatian): ?><div class="muted">Belum ada perangkat yang kamu tandai Tidak. Bagus!</div><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body"><h6 class="mb-1">Tips hemat energi</h6><div class="muted"><?= e($tip) ?></div></div></div>
  </div>
</div>

<div class="card"><div class="card-body pb-2"><h6 class="mb-3">Checklist terakhir saya</h6></div>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Tanggal</th><th>Jam</th><th>Perangkat</th><th>Ya/Total</th><th></th></tr></thead><tbody>
    <?php foreach ($terbaru as $x): ?>
      <tr><td><?= e(tgl_id($x['tanggal'])) ?></td><td><?= e(substr($x['jam'], 0, 5)) ?></td>
        <td><?= $x['perangkat'] !== null && $x['perangkat'] !== '' ? e($x['perangkat']) : '<span class="muted">Belum ada detail</span>' ?></td>
        <td><?php if ((int)$x['jml'] > 0): ?><span class="badge <?= (int)$x['ya'] === (int)$x['jml'] ? 'text-bg-success' : 'text-bg-warning' ?>"><?= (int)$x['ya'] ?>/<?= (int)$x['jml'] ?></span><?php else: ?>-<?php endif; ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>/checklist/form.php?id=<?= (int)$x['id_checklist'] ?>">Buka</a></td></tr>
    <?php endforeach; if (!$terbaru): ?>
      <tr><td colspan="5" class="text-center muted py-4">Belum ada checklist. Klik "Buat checklist" untuk memulai.</td></tr>
    <?php endif; ?>
    </tbody></table></div></div>
<?php require __DIR__ . '/footer.php'; ?>
