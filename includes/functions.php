<?php
require_once __DIR__ . '/../config/database.php';   // koneksi database ($pdo); isi DB_HOST, DB_NAME, DB_USER, DB_PASS di file itu
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// Alamat folder aplikasi (BASE_URL) dideteksi otomatis: '' bila ada langsung di htdocs, '/nama_folder' bila di dalam folder.
if (!defined('BASE_URL')) {
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app  = realpath(__DIR__ . '/..') ?: '';
    define('BASE_URL', ($root !== '' && stripos($app, $root) === 0) ? str_replace('\\', '/', substr($app, strlen($root))) : '');
    unset($root, $app);
}

// Pengaturan opsional (ubah nilainya di sini):
//  LOGIN_NIS_OTOMATIS   : true = siswa bisa langsung login dengan NIS; false = akun siswa harus dibuat manual di menu Pengguna.
if (!defined('LOGIN_NIS_OTOMATIS'))   define('LOGIN_NIS_OTOMATIS', true);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/grafik.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($path) { header('Location: ' . BASE_URL . '/' . $path); exit; }

function flash($type = null, $msg = null) {
    if ($type !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Permintaan tidak valid.');
    }
}

function user() { return $_SESSION['user'] ?? null; }
function is_siswa() { return (user()['level'] ?? '') === 'Siswa'; }
function is_admin() { return (user()['level'] ?? '') === 'Admin'; }
function is_guru()  { return (user()['level'] ?? '') === 'Guru'; }
// Mengelola data Siswa dan Perangkat (tambah/ubah/hapus): hanya Admin.
function boleh_kelola_data() { return is_admin(); }
/* Ringkasan hak akses per level (ditegakkan di tiap halaman lewat login_wajib dan pemeriksaan di bawah):
     Admin : semuanya - kelola Pengguna, Siswa, Perangkat; semua checklist (isi, ubah, hapus); laporan/export semua data.
     Guru  : checklist hanya MILIKNYA SENDIRI (dashboard pribadi, isi/ubah, laporan/export). Boleh MELIHAT data Siswa dan Perangkat
             (tanpa tambah/ubah/hapus). Tidak bisa melihat/memantau checklist siswa, tidak ada akses ke Pengguna.
     Siswa : sama seperti Guru, untuk checklist miliknya (dikaitkan lewat NIS). */
// Akun Siswa selalu dikunci ke data miliknya sendiri (NIS akunnya).
function siswa_terbatas() { return is_siswa(); }
// Akun yang hanya boleh melihat/mengelola checklist MILIKNYA SENDIRI: Siswa (lewat NIS) dan Guru (lewat id akun).
function checklist_terbatas() { return is_siswa() || is_guru(); }
// Kolom pemilik di tabel checklist dan nilainya untuk akun yang sedang login: Siswa -> nis, Guru -> id_user.
function pemilik_kolom() { return is_guru() ? 'id_user' : 'nis'; }
function pemilik_nilai() { return is_guru() ? (int)(user()['id'] ?? 0) : (int)(user()['nis'] ?? 0); }
// Ekspresi SQL nama dan kelas pemilik checklist (siswa, atau guru bila checklist dibuat guru). Butuh alias s (siswa) dan u (users).
const SQL_NAMA  = 'COALESCE(s.nama, u.nama)';
const SQL_KELAS = "COALESCE(s.kelas, 'Guru')";

function login_wajib(array $level = []) {
    if (empty($_SESSION['user'])) redirect('login.php');
    if ($level && !in_array($_SESSION['user']['level'], $level, true)) {
        http_response_code(403);
        exit('Anda tidak punya akses ke halaman ini.');
    }
}

// Ambil checklist; siswa hanya boleh membuka miliknya sendiri (username = NIS)
function ambil_checklist(PDO $pdo, $id) {
    $st = $pdo->prepare('SELECT c.*, ' . SQL_NAMA . ' AS nama, ' . SQL_KELAS . ' AS kelas FROM checklist c
        LEFT JOIN siswa s ON s.nis = c.nis LEFT JOIN users u ON u.id_user = c.id_user WHERE c.id_checklist = ?');
    $st->execute([(int)$id]);
    $c = $st->fetch();
    if (!$c) { flash('danger', 'Checklist tidak ditemukan.'); redirect('checklist/'); }
    if (checklist_terbatas()) {                       // Siswa: cocokkan NIS; Guru: cocokkan id akun. Milik orang lain ditolak.
        $milik = is_guru() ? ((string)$c['id_user'] === (string)pemilik_nilai()) : ($c['nis'] !== null && (string)$c['nis'] === (string)pemilik_nilai());
        if (!$milik) {
            http_response_code(403);
            exit('Ini bukan checklist Anda.');
        }
    }
    return $c;
}

// Bagian FROM + WHERE untuk daftar checklist. Dipakai daftar (checklist/index.php) dan export (checklist/export.php)
// supaya isi file export selalu sama dengan yang tampil di layar. Siswa hanya mendapat checklist dengan NIS-nya, Guru hanya yang dibuat akunnya sendiri. Mengembalikan [$dari, $where, $parameter].
// Satu baris hasil = satu baris detail_checklist; LEFT JOIN membuat checklist yang belum punya detail tetap muncul.
function filter_checklist($q) {
    $dari  = ' FROM checklist c
           LEFT JOIN siswa s ON s.nis = c.nis
           LEFT JOIN users u ON u.id_user = c.id_user
           LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
           LEFT JOIN perangkat p ON p.id_perangkat = d.id_perangkat';
    $where = ' WHERE (' . SQL_NAMA . ' LIKE :a OR ' . SQL_KELAS . ' LIKE :b OR c.tanggal LIKE :c OR p.nama_perangkat LIKE :d)';
    $par   = [':a' => "%$q%", ':b' => "%$q%", ':c' => "%$q%", ':d' => "%$q%"];
    if (checklist_terbatas()) {                     // Siswa dan Guru hanya melihat checklist miliknya sendiri (daftar dan export)
        $where .= ' AND c.' . pemilik_kolom() . ' = :pemilik';
        $par[':pemilik'] = pemilik_nilai();
    }
    return [$dari, $where, $par];
}

// ---------- Tampilan ----------

// Ikon SVG sebaris (stroke mengikuti warna teks). Dipakai di sidebar dan kartu.
function ikon($nama) {
    $p = [
        'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'home'   => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
        'siswa'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M17 5a3.5 3.5 0 010 7M22 20c0-2.5-1.5-4-4-5"/>',
        'bolt'   => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'list'   => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
        'moon'   => '<path d="M20 14A8 8 0 019.5 3.5 8 8 0 1020 14z"/>',
        'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
        'out'    => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>',
        'chev'   => '<path d="M6 9l6 6 6-6"/>',
        'set'    => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
        'excel'   => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9.5 12.5l5 5M14.5 12.5l-5 5"/>',
        'printer' => '<path d="M7 9V3h10v6"/><path d="M7 17H5a2 2 0 01-2-2v-4a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/>',
    ][$nama] ?? '';
    return '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

// Mencari file logo di assets/img/ dengan nama konservasi.(png|jpg|jpeg|webp|svg); urutan = prioritas.
function logo_file() {
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $x) {
        if (is_file(__DIR__ . '/../assets/img/konservasi.' . $x)) return 'konservasi.' . $x;
    }
    return null;
}

// Logo aplikasi: pakai file logo jika ada, kalau tidak pakai ikon petir.
function logo($ukuran = 36) {
    $f = logo_file();
    if ($f) {
        return '<img src="' . BASE_URL . '/assets/img/' . $f . '" alt="Logo Konservasi Energi" width="' . $ukuran . '" height="' . $ukuran . '" class="logo-img">';
    }
    return '<span class="logo-ph" style="width:' . $ukuran . 'px;height:' . $ukuran . 'px">' . ikon('bolt') . '</span>';
}

// Halaman aktif ditentukan dari alamat file yang sedang dibuka (untuk menyorot menu).
function halaman_aktif() {
    $s = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (['siswa', 'perangkat', 'checklist', 'users', 'akun'] as $k) {
        if (strpos($s, "/$k/") !== false) return $k;
    }
    return strpos($s, 'tentang') !== false ? 'tentang' : 'dashboard';
}

function menu_item($kunci, $url, $ikon, $label, $aktif) {
    $on = $kunci === $aktif ? ' on' : '';
    return '<a class="menu' . $on . '" href="' . BASE_URL . '/' . $url . '">' . ikon($ikon) . '<span>' . e($label) . '</span></a>';
}

// Tanggal berbahasa Indonesia, contoh: 15 Okt 2026
function tgl_id($tanggal) {
    $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
}

// Panjang teks; memakai mbstring bila tersedia (tidak semua server mengaktifkannya).
function panjang($s) { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); }

// Membuat akun Siswa (tabel users) dari data di tabel siswa. Dipakai saat siswa pertama kali masuk
// dengan username = NIS dan password = NIS. Mengembalikan baris users, atau null jika NIS tidak terdaftar.
function buat_akun_siswa(PDO $pdo, $nis) {
    $st = $pdo->prepare('SELECT nis, nama FROM siswa WHERE nis = ?');
    $st->execute([(int)$nis]);
    $sw = $st->fetch();
    if (!$sw || (string)$sw['nis'] !== (string)$nis) return null;   // tolak mis. "012" (angka nol di depan)
    try {
        $pdo->prepare('INSERT INTO users (username, password, nama, level, nis) VALUES (?,?,?,?,?)')
            ->execute([(string)$nis, password_hash((string)$nis, PASSWORD_DEFAULT), $sw['nama'], 'Siswa', $sw['nis']]);
    } catch (PDOException $e) {
        return null;                                                  // mis. username sudah dipakai
    }
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([(string)$nis]);
    return $st->fetch() ?: null;
}

// Alamat file di assets/ + nomor versi dari waktu ubah file. Saat file diganti, alamatnya berubah,
// sehingga browser tidak memakai CSS/JS lama dari cache.
function aset($path) {
    $file = __DIR__ . '/../assets/' . $path;
    return BASE_URL . '/assets/' . $path . '?v=' . (is_file($file) ? filemtime($file) : 0);
}
