<?php
// Form "Isi Checklist": data checklist (siswa, tanggal, jam) + SEMUA baris detail perangkat dalam satu halaman.
//  - tanpa ?id=  : membuat checklist baru
//  - dengan ?id= : menampilkan checklist yang sudah ada dalam bentuk form; semua baris bisa diubah,
//                  ditambah, atau dihapus sekaligus lalu disimpan sekali jalan.
// Setelah disimpan, halaman ini ditampilkan lagi (terisi) supaya hasilnya langsung terlihat.
require_once __DIR__ . '/../includes/functions.php';
login_wajib();

$edit = isset($_GET['id']);
$idCl = $edit ? (int)$_GET['id'] : 0;
$milikGuru = is_guru();                       // checklist milik Guru: dikaitkan ke akun (id_user), bukan ke NIS siswa
$namaPemilik = '';
$row  = ['nis' => '', 'tanggal' => date('Y-m-d'), 'jam' => date('H:i')];
$ada  = [];                                   // id_detail yang sudah tersimpan untuk checklist ini
$rows = [];
$barisKosong = ['id_detail' => '', 'id_perangkat' => '', 'tahapan' => 'SEBELUM', 'kondisi' => '', 'status' => 'Ya', 'keterangan' => ''];

if ($edit) {
    $c   = ambil_checklist($pdo, $idCl);      // juga memeriksa hak akses (Siswa dan Guru hanya miliknya sendiri)
    if ($c['id_user'] !== null) { $milikGuru = true; $namaPemilik = $c['nama']; }   // Admin membuka checklist milik Guru
    $row = ['nis' => $c['nis'], 'tanggal' => $c['tanggal'], 'jam' => substr($c['jam'], 0, 5)];
    $st  = $pdo->prepare('SELECT * FROM detail_checklist WHERE id_checklist = ?
                          ORDER BY FIELD(tahapan, "SEBELUM", "SELAMA", "SESUDAH", "5R"), id_detail');
    $st->execute([$idCl]);
    $rows = $st->fetchAll();
    $ada  = array_map('intval', array_column($rows, 'id_detail'));
}
if (!$rows) $rows = [$barisKosong];

// Siapa yang boleh menghapus baris: hanya Admin. Guru dan Siswa tidak.
$bolehHapus = user()['level'] === 'Admin';
$admin      = user()['level'] === 'Admin';

$nisSaya = (int)(user()['nis'] ?? 0);
if ($milikGuru) { if ($namaPemilik === '') $namaPemilik = user()['nama']; }   // Guru: tanpa pilihan siswa
elseif (siswa_terbatas()) $row['nis'] = $nisSaya;                   // siswa yang dikunci: NIS sendiri, tidak bisa memilih siswa lain

$daftarSiswa     = $pdo->query('SELECT nis, nama, kelas FROM siswa ORDER BY kelas, nama')->fetchAll();
$daftarPerangkat = $pdo->query('SELECT id_perangkat, nama_perangkat, jenis FROM perangkat ORDER BY nama_perangkat')->fetchAll();
$idPerangkatOk   = array_map('intval', array_column($daftarPerangkat, 'id_perangkat'));
$daftarTahapan   = ['SEBELUM', 'SELAMA', 'SESUDAH', '5R'];

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $kirim = $_POST['nis'] ?? '';
    if (!$milikGuru) $row['nis'] = siswa_terbatas() ? $nisSaya : (int)($kirim !== '' ? $kirim : $nisSaya);
    $row['tanggal'] = $_POST['tanggal'] ?? '';
    $row['jam']     = $_POST['jam'] ?? '';

    $cek = $pdo->prepare('SELECT 1 FROM siswa WHERE nis = ?');
    $cek->execute([$row['nis']]);
    if (!$milikGuru && !$cek->fetchColumn()) $err[] = siswa_terbatas() && $nisSaya === 0 ? 'Akun Anda belum terhubung ke data siswa. Hubungi admin.' : 'Pilih siswa.';
    $tgl = DateTime::createFromFormat('Y-m-d', $row['tanggal']);
    if (!$tgl || $tgl->format('Y-m-d') !== $row['tanggal']) $err[] = 'Tanggal tidak valid.';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $row['jam'])) $err[] = 'Jam tidak valid.';

    // Baris detail hanya diproses bila dikirim oleh form ini (penanda semua_baris). Tanpa penanda,
    // detail yang sudah ada tidak disentuh (mencegah terhapus tidak sengaja).
    $sinkron = !$edit || ($_POST['semua_baris'] ?? '') === '1';
    $valid = []; $kirimId = [];
    if ($sinkron) {
        $rows = [];
        foreach (array_slice((array)($_POST['detail'] ?? []), 0, 300) as $d) {
            if (!is_array($d)) continue;
            $r = ['id_detail' => (int)($d['id_detail'] ?? 0) ?: '', 'id_perangkat' => (int)($d['id_perangkat'] ?? 0), 'tahapan' => $d['tahapan'] ?? '',
                  'kondisi' => trim($d['kondisi'] ?? ''), 'status' => $d['status'] ?? '', 'keterangan' => trim($d['keterangan'] ?? '')];
            $rows[] = $r;
            $n = count($rows);
            if ($r['id_detail'] !== '') {
                if (!in_array((int)$r['id_detail'], $ada, true)) { $err[] = "Baris $n: bukan bagian dari checklist ini."; continue; }
                $kirimId[] = (int)$r['id_detail'];
            } elseif (!$r['id_perangkat'] && $r['kondisi'] === '' && $r['keterangan'] === '') {
                continue;                                                           // baris baru yang kosong diabaikan
            }
            if (!in_array($r['id_perangkat'], $idPerangkatOk, true))   { $err[] = "Baris $n: pilih perangkat."; continue; }
            if (!in_array($r['tahapan'], $daftarTahapan, true))        { $err[] = "Baris $n: tahapan tidak valid."; continue; }
            if ($r['kondisi'] === '' || panjang($r['kondisi']) > 100)   { $err[] = "Baris $n: kondisi wajib diisi (maksimal 100 karakter)."; continue; }
            if (!in_array($r['status'], ['Ya', 'Tidak'], true))        { $err[] = "Baris $n: pilih Ya atau Tidak."; continue; }
            $valid[] = $r;
        }
        if (!$rows) $rows = [$barisKosong];
        $hapusId = array_values(array_diff($ada, $kirimId));                       // baris lama yang dihilangkan dari form
        if ($edit && $hapusId && !$bolehHapus) $err[] = 'Anda tidak diizinkan menghapus baris. Hubungi admin.';
    }

    if (!$err) {
        try {
            $pdo->beginTransaction();                                              // semua perubahan = satu kesatuan
            if ($edit) {
                if ($milikGuru) $pdo->prepare('UPDATE checklist SET tanggal=?, jam=? WHERE id_checklist=?')->execute([$row['tanggal'], $row['jam'], $idCl]);   // pemilik (guru) tidak berubah
                else            $pdo->prepare('UPDATE checklist SET nis=?, tanggal=?, jam=? WHERE id_checklist=?')->execute([$row['nis'], $row['tanggal'], $row['jam'], $idCl]);
                $id = $idCl;
            } else {
                if ($milikGuru) $pdo->prepare('INSERT INTO checklist (nis, id_user, tanggal, jam) VALUES (NULL,?,?,?)')->execute([pemilik_nilai(), $row['tanggal'], $row['jam']]);
                else            $pdo->prepare('INSERT INTO checklist (nis, tanggal, jam) VALUES (?,?,?)')->execute([$row['nis'], $row['tanggal'], $row['jam']]);
                $id = (int)$pdo->lastInsertId();
            }
            $upd = $pdo->prepare('UPDATE detail_checklist SET id_perangkat=?, tahapan=?, kondisi=?, status=?, keterangan=? WHERE id_detail=? AND id_checklist=?');
            $ins = $pdo->prepare('INSERT INTO detail_checklist (id_checklist, id_perangkat, tahapan, kondisi, status, keterangan) VALUES (?,?,?,?,?,?)');
            $baru = 0; $ubah = 0;
            foreach ($valid as $r) {
                if ($r['id_detail'] !== '') { $upd->execute([$r['id_perangkat'], $r['tahapan'], $r['kondisi'], $r['status'], $r['keterangan'], $r['id_detail'], $id]); $ubah++; }
                else                        { $ins->execute([$id, $r['id_perangkat'], $r['tahapan'], $r['kondisi'], $r['status'], $r['keterangan']]); $baru++; }
            }
            $dihapus = 0;
            if ($edit && $sinkron && $hapusId) {
                $del = $pdo->prepare('DELETE FROM detail_checklist WHERE id_detail=? AND id_checklist=?');
                foreach ($hapusId as $h) { $del->execute([$h, $id]); $dihapus++; }
            }
            $pdo->commit();

            if (($_POST['aksi'] ?? '') === 'tambah') { flash('success', 'Checklist disimpan. Silakan tambah satu data perangkat.'); redirect('checklist/detail_form.php?id_checklist=' . $id); }
            if ($edit)        flash('success', 'Checklist diperbarui' . ($baru + $dihapus + $ubah ? " ($ubah diubah, $baru ditambah, $dihapus dihapus)." : '.'));
            elseif ($baru)    flash('success', "Checklist disimpan ($baru baris).");
            else              flash('success', 'Checklist dibuat. Tambahkan detail perangkat di bawah.');
            redirect('checklist/form.php?id=' . $id);                              // tampilkan lagi sebagai form terisi
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err[] = 'Data gagal disimpan.';
        }
    }
}

// Satu baris detail (dipakai untuk baris yang sudah ada dan untuk template baris baru)
function baris_detail($i, $r, $perangkat, $tahapan, $bolehHapus) {
    $ada = $r['id_detail'] !== '' && $r['id_detail'] !== null;
    ob_start(); ?>
    <div class="row g-2 align-items-end baris-detail mb-2"<?= $ada ? ' data-ada="1" id="d' . (int)$r['id_detail'] . '"' : '' ?>>
      <?php if ($ada): ?><input type="hidden" name="detail[<?= $i ?>][id_detail]" value="<?= (int)$r['id_detail'] ?>"><?php endif; ?>
      <div class="col-md-3"><label class="form-label small muted d-md-none">Perangkat</label>
        <select name="detail[<?= $i ?>][id_perangkat]" class="form-select">
          <option value="">Pilih perangkat</option>
          <?php foreach ($perangkat as $p): ?>
            <option value="<?= $p['id_perangkat'] ?>" <?= (int)$r['id_perangkat'] === (int)$p['id_perangkat'] ? 'selected' : '' ?>><?= e($p['nama_perangkat'] . ' (' . $p['jenis'] . ')') ?></option>
          <?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label small muted d-md-none">Tahapan</label>
        <select name="detail[<?= $i ?>][tahapan]" class="form-select">
          <?php foreach ($tahapan as $t): ?><option <?= $r['tahapan'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label small muted d-md-none">Kondisi</label>
        <input name="detail[<?= $i ?>][kondisi]" class="form-control" maxlength="100" value="<?= e($r['kondisi']) ?>" placeholder="Contoh: menyala, mati"></div>
      <div class="col-6 col-md-1"><label class="form-label small muted d-md-none">Dilakukan?</label>
        <select name="detail[<?= $i ?>][status]" class="form-select">
          <option <?= $r['status'] === 'Ya' ? 'selected' : '' ?>>Ya</option><option <?= $r['status'] === 'Tidak' ? 'selected' : '' ?>>Tidak</option></select></div>
      <div class="col-6 col-md-3"><label class="form-label small muted d-md-none">Keterangan</label>
        <input name="detail[<?= $i ?>][keterangan]" class="form-control" value="<?= e($r['keterangan']) ?>" placeholder="Catatan (opsional)"></div>
      <div class="col-12 col-md-1 text-md-end">
        <?php if (!$ada || $bolehHapus): ?><button type="button" class="btn btn-danger btn-sm w-100 hapus-baris">Hapus</button><?php endif; ?></div>
    </div>
    <?php return ob_get_clean();
}

$judul = 'Isi Checklist';
require '../includes/header.php';
?>
<div><h3 class="mb-0">Isi Checklist</h3>
  <div class="muted"><?= $edit ? 'Ubah isian di bawah lalu simpan; semua perubahan tersimpan sekaligus.' : 'Catat kondisi perangkat satu per satu. Klik "Tambah baris" bila ada perangkat lain.' ?></div></div>
<?php foreach ($err as $m): ?><div class="alert alert-danger py-2 mb-0"><?= e($m) ?></div><?php endforeach; ?>

<form method="post"><?= csrf_field() ?>
<?php if ($edit): ?><input type="hidden" name="semua_baris" value="1"><?php endif; ?>
<div class="card"><div class="card-body">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= $milikGuru ? 'Guru' : 'Siswa' ?></label>
      <?php if ($milikGuru): ?>
        <input class="form-control" value="<?= e($namaPemilik) ?><?= is_guru() ? ' (akun Anda)' : '' ?>" readonly>
      <?php elseif (siswa_terbatas()): ?>
        <input class="form-control" value="NIS <?= e($row['nis']) ?> (akun Anda)" readonly>
      <?php else: ?>
        <select name="nis" class="form-select" required>
          <option value="">Pilih siswa</option>
          <?php foreach ($daftarSiswa as $s): ?>
            <option value="<?= $s['nis'] ?>" <?= (int)$row['nis'] === (int)$s['nis'] ? 'selected' : '' ?>><?= e($s['nama'] . ' (' . $s['kelas'] . ', NIS ' . $s['nis'] . ')') ?></option>
          <?php endforeach; ?></select>
      <?php endif; ?></div>
    <div class="col-7 col-md-3"><label class="form-label">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" value="<?= e($row['tanggal']) ?>" required></div>
    <div class="col-5 col-md-3"><label class="form-label">Jam</label>
      <input type="time" name="jam" class="form-control" value="<?= e($row['jam']) ?>" required></div>
  </div>

  <h6 class="mt-4 mb-2">Detail perangkat</h6>
  <div class="row g-2 d-none d-md-flex small muted mb-1">
    <div class="col-md-3">Perangkat</div><div class="col-md-2">Tahapan</div><div class="col-md-2">Kondisi</div>
    <div class="col-md-1">Dilakukan?</div><div class="col-md-3">Keterangan</div><div class="col-md-1"></div></div>
  <div id="baris"><?php foreach ($rows as $i => $r) echo baris_detail($i, $r, $daftarPerangkat, $daftarTahapan, $bolehHapus); ?></div>
  <template id="tpl"><?= baris_detail('__I__', $barisKosong, $daftarPerangkat, $daftarTahapan, $bolehHapus) ?></template>
  <button type="button" class="btn btn-outline-success btn-sm mt-1" id="tambah">Tambah baris</button>
</div></div>

<div class="d-flex flex-wrap gap-2 mt-3">
  <button class="btn btn-success">Simpan checklist</button>
  <button class="btn btn-outline-success" name="aksi" value="tambah">Simpan lalu tambah 1 data</button>
  <a class="btn btn-outline-secondary" href="index.php"><?= $edit ? 'Kembali ke daftar' : 'Batal' ?></a>
</div>
</form>

<?php if ($edit && $admin): ?>
<form method="post" action="hapus.php" onsubmit="return confirm('Hapus checklist ini beserta semua barisnya?')">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $idCl ?>">
  <button class="btn btn-outline-danger btn-sm">Hapus checklist ini</button>
</form>
<?php endif; ?>

<script>
  // Tambah / hapus baris detail. Nomor indeks (detail[N]) harus unik agar PHP menerima semua baris.
  (function () {
    var wadah = document.getElementById('baris'), tpl = document.getElementById('tpl').innerHTML, n = wadah.children.length + 1000;
    document.getElementById('tambah').addEventListener('click', function () {
      var d = document.createElement('div'); d.innerHTML = tpl.replace(/__I__/g, n++).trim();
      wadah.appendChild(d.firstElementChild);
    });
    wadah.addEventListener('click', function (e) {
      if (!e.target.classList.contains('hapus-baris')) return;
      var baris = e.target.closest('.baris-detail');
      if (baris.dataset.ada) {                                        // baris yang sudah tersimpan: hilang saat disimpan
        if (!confirm('Hapus baris ini? Perubahan baru berlaku setelah klik "Simpan checklist".')) return;
        baris.remove();
      } else if (wadah.querySelectorAll('.baris-detail').length > 1) {
        baris.remove();
      } else {
        baris.querySelectorAll('input:not([type=hidden])').forEach(function (i) { i.value = ''; });   // baris baru terakhir: kosongkan saja
      }
    });
  })();
</script>
<?php require '../includes/footer.php'; ?>
