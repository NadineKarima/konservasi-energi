<?php
// =========================================================
// KONFIGURASI DATABASE
// Sesuaikan jika host/user/password MySQL kamu berbeda
// =========================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'konservasi_energi');
define('DB_USER', 'root');
define('DB_PASS', '');

// Zona waktu: Asia/Jakarta = WIB (+07:00). WITA: Asia/Makassar (+08:00). WIT: Asia/Jayapura (+09:00).
define('TIMEZONE', 'Asia/Jakarta');
define('TIMEZONE_MYSQL', '+07:00');
date_default_timezone_set(TIMEZONE);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->exec("SET time_zone = '" . TIMEZONE_MYSQL . "'");
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
