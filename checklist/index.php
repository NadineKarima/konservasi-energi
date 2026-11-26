<?php
require_once __DIR__ . '/../includes/functions.php';
login_wajib();
$saya  = checklist_terbatas();             // Siswa dan Guru: hanya melihat dan mengelola checklist miliknya
$judul = $saya ? 'Checklist saya' : 'Checklist';

// Hapus baris: hanya Admin. Guru dan Siswa bisa mengubah (siswa hanya miliknya sendiri).
$bolehHapus = user()['level'] === 'Admin';

$q       = trim($_GET['q'] ?? '');
$perHal  = 25;
$hal     = max(1, (int)($_GET['hal'] ?? 1));

// Satu baris tabel = satu baris detail_checklist (lihat filter_checklist di includes/functions.php).
[$dari, $where, $par] = filter_checklist($q);

$st = $pdo->prepare('SELECT COUNT(*)' . $dari . $where);
$st->execute($par);
$total   = (int)$st->fetchColumn();
$jmlHal  = max(1, (int)ceil($total / $perHal));
$hal     = min($hal, $jmlHal);
$offset  = ($hal - 1) * $perHal;                    // semua angka sudah di-cast ke int, aman dipakai langsung

$st = $pdo->prepare('SELECT c.id_checklist, c.tanggal, c.jam, ' . SQL_NAMA . ' AS nama, ' . SQL_KELAS . ' AS kelas,
        d.id_detail, d.tahapan, d.kondisi, d.status, d.keterangan, p.nama_perangkat'
    . $dari . $where . '
    ORDER BY c.tanggal DESC, c.jam DESC, c.id_checklist DESC,
             FIELD(d.tahapan, "SEBELUM", "SELAMA", "SESUDAH", "5R"), p.nama_perangkat
    LIMIT ' . $perHal . ' OFFSET ' . $offset);
$st->execute($par);
$data = $st->fetchAll();

function url_hal($n, $q) { return '?' . http_build_query(['q' => $q, 'hal' => $n]); }

require '../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div><h3 class="mb-0"><?= $saya ? 'Checklist Saya' : 'Checklist Konservasi Energi' ?></h3>
    <div class="muted"><?= $saya ? e(user()['nama']) . ' &middot; ' : '' ?><?= $total ?> baris<?= $q !== '' ? ' untuk pencarian "' . e($q) . '"' : '' ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <?php $ket = 'Export semua ' . $total . ' baris' . ($q !== '' ? ' hasil pencarian' : '') . ' (bukan hanya halaman ini)'; ?>
    <a class="btn btn-ikon btn-outline-success <?= $total ? '' : 'disabled' ?>" title="<?= e($ket) ?>" href="export.php?<?= e(http_build_query(['format' => 'xlsx', 'q' => $q])) ?>"><?= ikon('excel') ?>Export Excel</a>
    <a class="btn btn-ikon btn-outline-secondary <?= $total ? '' : 'disabled' ?>" title="<?= e($ket) ?>" href="export.php?<?= e(http_build_query(['format' => 'pdf', 'q' => $q])) ?>"><?= ikon('printer') ?>Cetak / PDF</a>
    <a class="btn btn-success" href="form.php">Buat checklist</a>
  </div>
</div>

<?php if ($saya && !pemilik_nilai()): ?>
<div class="alert alert-warning mb-0">Akun Anda belum terhubung ke data siswa, jadi belum bisa membuat checklist. Hubungi admin.</div>
<?php endif; ?>

<form method="get"><div class="input-group">
  <input name="q" value="<?= e($q) ?>" class="form-control" placeholder="<?= $saya ? 'Cari tanggal (2026-10-21) atau perangkat' : 'Cari nama, kelas, tanggal (2026-10-21), atau perangkat' ?>">
  <button class="btn btn-outline-success">Cari</button>
  <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="index.php">Reset</a><?php endif; ?>
</div></form>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle tabel-rapat">
  <thead><tr>
    <th>Tanggal</th><th>Jam</th><?php if (!$saya): ?><th>Nama</th><th>Kelas</th><?php endif; ?><th>Perangkat</th>
    <th>Tahapan</th><th>Kondisi</th><th>Status</th><th>Keterangan</th><th class="text-end">Aksi</th>
  </tr></thead><tbody>
  <?php $prev = null; foreach ($data as $r): $baru = $prev !== null && $prev !== $r['id_checklist']; $prev = $r['id_checklist']; ?>
    <tr class="<?= $baru ? 'grup-baru' : '' ?>">
      <td class="text-nowrap"><a class="text-reset" href="form.php?id=<?= $r['id_checklist'] ?>" title="Buka seluruh checklist ini"><?= e($r['tanggal']) ?></a></td>
      <td><?= e(substr($r['jam'], 0, 5)) ?></td>
      <?php if (!$saya): ?><td><?= e($r['nama']) ?></td>
      <td class="text-nowrap"><?= e($r['kelas']) ?></td><?php endif; ?>
    <?php if ($r['id_detail']): ?>
      <td><?= e($r['nama_perangkat']) ?></td>
      <td><?= e($r['tahapan']) ?></td>
      <td><?= e($r['kondisi']) ?></td>
      <td><span class="badge text-bg-<?= $r['status'] === 'Ya' ? 'success' : 'danger' ?>"><?= e($r['status']) ?></span></td>
      <td><?= e($r['keterangan']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id_checklist'] ?>#d<?= $r['id_detail'] ?>">Ubah</a>
        <?php if ($bolehHapus): ?>
        <form method="post" action="detail_hapus.php" class="d-inline" onsubmit="return confirm('Hapus baris ini?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_detail'] ?>"><input type="hidden" name="kembali" value="daftar">
          <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
        <?php endif; ?>
      </td>
    <?php else: ?>
      <td colspan="5" class="muted">Belum ada detail perangkat.</td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-success" href="form.php?id=<?= $r['id_checklist'] ?>">Isi</a></td>
    <?php endif; ?>
    </tr>
  <?php endforeach; if (!$data): ?>
    <tr><td colspan="<?= $saya ? 8 : 10 ?>" class="text-center muted py-5">
      <?php if ($q !== ''): ?>Tidak ada data yang cocok.
      <?php else: ?>Belum ada checklist.<br><a class="btn btn-success mt-3" href="form.php">Buat checklist</a><?php endif; ?>
    </td></tr>
  <?php endif; ?>
  </tbody></table></div></div>

<?php if ($jmlHal > 1): ?>
<nav aria-label="Halaman"><ul class="pagination justify-content-center mb-0">
  <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= url_hal($hal - 1, $q) ?>">&laquo;</a></li>
  <?php for ($i = 1; $i <= $jmlHal; $i++): ?>
    <li class="page-item <?= $i === $hal ? 'active' : '' ?>"><a class="page-link" href="<?= url_hal($i, $q) ?>"><?= $i ?></a></li>
  <?php endfor; ?>
  <li class="page-item <?= $hal >= $jmlHal ? 'disabled' : '' ?>"><a class="page-link" href="<?= url_hal($hal + 1, $q) ?>">&raquo;</a></li>
</ul></nav>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>
