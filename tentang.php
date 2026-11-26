<?php
require_once __DIR__ . '/includes/functions.php';
login_wajib();
$judul = 'Tentang';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center gap-3"><?= logo(56) ?><div><h3 class="mb-0">Konservasi Energi</h3><div class="muted">Checklist hemat energi di lingkungan sekolah</div></div></div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-body">
    <h6>Tujuan</h6>
    <p class="mb-0">Aplikasi ini membantu siswa dan guru mencatat penggunaan perangkat listrik (komputer, lampu, proyektor, dan lainnya) agar tidak ada energi yang terbuang, serta memantau kepatuhannya dari waktu ke waktu.</p>
  </div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-body">
    <h6>Tahapan checklist</h6>
    <p class="mb-1"><b>SEBELUM</b>: kondisi perangkat sebelum dipakai.</p>
    <p class="mb-1"><b>SELAMA</b>: perangkat saat digunakan.</p>
    <p class="mb-1"><b>SESUDAH</b>: perangkat sudah dimatikan?</p>
    <p class="mb-0"><b>5R</b>: Ringkas, Rapi, Resik, Rawat, Rajin.</p>
  </div></div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
