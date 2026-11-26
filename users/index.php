<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib(['Admin']);
$judul = 'Pengguna';

$q  = trim($_GET['q'] ?? '');
$lv = $_GET['level'] ?? '';
if (!in_array($lv, ['Admin', 'Guru', 'Siswa'], true)) $lv = '';

$gabung = "SELECT u.id_user, u.username, u.nama, u.level, u.nis, s.nama AS nama_siswa, s.kelas, 1 AS punya_akun
             FROM users u LEFT JOIN siswa s ON s.nis = u.nis
           UNION ALL
           SELECT NULL, CAST(s.nis AS CHAR) COLLATE utf8mb4_general_ci, s.nama, 'Siswa', s.nis, s.nama, s.kelas, 0
             FROM siswa s WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.nis = s.nis)";

$sql = "SELECT * FROM ($gabung) t
        WHERE (t.nama LIKE :a OR t.username LIKE :b OR IFNULL(t.kelas, '') LIKE :c OR IFNULL(CAST(t.nis AS CHAR), '') LIKE :d)"
     . ($lv !== '' ? ' AND t.level = :lv' : '')
     . " ORDER BY FIELD(t.level, 'Admin', 'Guru', 'Siswa'), t.kelas, t.nama";
$par = [':a' => "%$q%", ':b' => "%$q%", ':c' => "%$q%", ':d' => "%$q%"];
if ($lv !== '') $par[':lv'] = $lv;
$st = $pdo->prepare($sql);
$st->execute($par);
$data = $st->fetchAll();

$jml = ['Admin' => 0, 'Guru' => 0, 'Siswa' => 0]; $belum = 0;
foreach ($pdo->query("SELECT level, punya_akun, COUNT(*) n FROM ($gabung) t GROUP BY level, punya_akun")->fetchAll() as $x) {
    $jml[$x['level']] += (int)$x['n'];
    if (!$x['punya_akun']) $belum += (int)$x['n'];
}

require '../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div><h3 class="mb-0">Pengguna</h3>
    <div class="muted"><?= array_sum($jml) ?> pengguna: Admin <?= $jml['Admin'] ?>, Guru <?= $jml['Guru'] ?>, Siswa <?= $jml['Siswa'] ?><?= $belum ? " ($belum belum punya akun)" : '' ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($belum): ?>
    <form method="post" action="buat_akun.php" onsubmit="return confirm('Buat akun untuk <?= $belum ?> siswa yang belum punya akun? Password awal = NIS masing-masing.')">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="semua">
      <button class="btn btn-outline-success">Buat akun semua siswa (<?= $belum ?>)</button></form>
    <?php endif; ?>
    <a class="btn btn-success" href="form.php">Tambah pengguna</a>
  </div>
</div>

<form method="get" class="row g-2">
  <div class="col-md-6"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Cari username, nama, kelas, atau NIS"></div>
  <div class="col-6 col-md-3"><select name="level" class="form-select" onchange="this.form.submit()">
    <option value="">Semua level</option>
    <?php foreach (['Admin', 'Guru', 'Siswa'] as $l): ?><option <?= $lv === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-3 d-flex gap-2"><button class="btn btn-outline-success flex-fill">Cari</button>
    <?php if ($q !== '' || $lv !== ''): ?><a class="btn btn-outline-secondary" href="index.php">Reset</a><?php endif; ?></div>
</form>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle tabel-rapat">
  <thead><tr><th>Username</th><th>Nama</th><th>Level</th><th>Kelas</th><th>NIS</th><th>Status akun</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach ($data as $r): ?>
    <tr>
      <td><?= e($r['username']) ?></td>
      <td><?= e($r['nama']) ?><?= $r['punya_akun'] && $r['nama_siswa'] && $r['nama_siswa'] !== $r['nama'] ? '<div class="muted small">siswa: ' . e($r['nama_siswa']) . '</div>' : '' ?></td>
      <td><?= e($r['level']) ?></td>
      <td class="text-nowrap"><?= $r['kelas'] ? e($r['kelas']) : '-' ?></td>
      <td><?= $r['nis'] ? e($r['nis']) : '-' ?></td>
      <td><?php if ($r['punya_akun']): ?><span class="badge text-bg-success">Aktif</span>
          <?php else: ?><span class="badge text-bg-secondary" title="<?= LOGIN_NIS_OTOMATIS ? 'Akun dibuat otomatis saat siswa pertama kali masuk dengan NIS' : 'Klik Buat akun agar siswa bisa masuk' ?>">Belum ada akun</span><?php endif; ?></td>
      <td class="text-end text-nowrap">
      <?php if ($r['punya_akun']): ?>
        <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id_user'] ?>">Edit</a>
        <?php if ((int)$r['id_user'] !== (int)user()['id']): ?>
        <form method="post" action="hapus.php" class="d-inline" onsubmit="return confirm('Hapus akun ini?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_user'] ?>">
          <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
        <?php endif; ?>
      <?php else: ?>
        <form method="post" action="buat_akun.php" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="nis" value="<?= (int)$r['nis'] ?>">
          <button class="btn btn-sm btn-success">Buat akun</button></form>
      <?php endif; ?>
      </td></tr>
  <?php endforeach; if (!$data): ?>
    <tr><td colspan="7" class="text-center muted py-5"><?= ($q !== '' || $lv !== '') ? 'Tidak ada pengguna yang cocok.' : 'Belum ada pengguna.' ?></td></tr>
  <?php endif; ?>
  </tbody></table></div></div>
<p class="muted small mb-0">Siswa tampil di sini sesuai data di menu Siswa. <?= LOGIN_NIS_OTOMATIS ? 'Siswa yang belum punya akun tetap bisa masuk: username dan password awal = NIS (akun dibuat otomatis).' : 'Siswa yang belum punya akun belum bisa masuk sampai akunnya dibuat.' ?> Password awal = NIS mudah ditebak, minta siswa menggantinya.</p>
<?php require '../includes/footer.php'; ?>
