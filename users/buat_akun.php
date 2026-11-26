<?php
// Membuat akun Siswa (password awal = NIS) dari data di tabel siswa: satu siswa, atau semua yang belum punya akun.
require_once __DIR__ . '/../includes/functions.php';
login_wajib(['Admin']);
csrf_check();
set_time_limit(300);                     // membuat banyak akun (hash password) bisa memakan waktu

if (($_POST['aksi'] ?? '') === 'semua') {
    $nisList = $pdo->query("SELECT s.nis FROM siswa s
        WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.nis = s.nis)
          AND NOT EXISTS (SELECT 1 FROM users u WHERE u.username = CAST(s.nis AS CHAR) COLLATE utf8mb4_general_ci)")->fetchAll(PDO::FETCH_COLUMN);
    $dibuat = 0; $lewat = 0;
    foreach ($nisList as $n) { if (buat_akun_siswa($pdo, (string)$n)) $dibuat++; else $lewat++; }
    flash($dibuat ? 'success' : 'warning', "$dibuat akun dibuat" . ($lewat ? ", $lewat dilewati" : '') . '. Password awal = NIS masing-masing.');
} else {
    $nis = (int)($_POST['nis'] ?? 0);
    $st = $pdo->prepare('SELECT 1 FROM users WHERE nis = ?');
    $st->execute([$nis]);
    if ($st->fetchColumn()) {
        flash('warning', 'Siswa ini sudah punya akun.');
    } elseif ($row = buat_akun_siswa($pdo, (string)$nis)) {
        flash('success', 'Akun ' . $row['nama'] . ' dibuat. Username dan password awal = NIS ' . $nis . '.');
    } else {
        flash('danger', 'Akun tidak bisa dibuat (NIS tidak terdaftar atau username sudah dipakai).');
    }
}
redirect('users/');
