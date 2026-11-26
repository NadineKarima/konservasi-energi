<?php
// Pembuat file PDF dan Excel (.xlsx) tanpa library tambahan. Dipakai oleh checklist/export.php.
//  - PDF  : tabel A4 lanskap, teks otomatis turun baris, kepala tabel diulang di tiap halaman,
//           memakai font bawaan PDF (Helvetica) sehingga tidak perlu menyertakan file font.
//  - XLSX : dibuat dengan penulis ZIP sendiri (zip_buat), jadi TIDAK butuh ekstensi "zip" PHP.
//  - Logo : dari assets/img/konservasi.* (lihat logo_gambar); PDF butuh JPEG, Excel butuh PNG, keduanya dibuat dengan "gd".

// Ubah teks UTF-8 ke Windows-1252 (satu byte per huruf) agar cocok dengan font bawaan PDF.
function ke_latin1($s) {
    $s = (string)$s;
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    $r = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//IGNORE', $s) : false;
    return $r !== false ? $r : preg_replace('/[^\x20-\x7E]/', '?', $s);
}

// Kirim file ke browser sebagai unduhan, lalu hentikan skrip.
function kirim_unduhan($isi, $tipe, $namaFile) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: ' . $tipe);
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Content-Length: ' . strlen($isi));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $isi;
    exit;
}

// Logo untuk file export. Mengembalikan ['jpeg' => ..., 'png' => ..., 'w' => px, 'h' => px] atau null bila tidak ada logo.
// Isi file dibaca apa adanya (tidak percaya ekstensi: file ".jpg" bisa saja sebenarnya PNG). Dengan ekstensi "gd"
// (aktif bawaan di XAMPP) logo dikecilkan dan latar transparan diberi warna putih untuk PDF; tanpa "gd", hanya
// logo berformat JPEG asli yang bisa dipakai di PDF, dan hanya PNG asli di Excel. Gagal apa pun = export tanpa logo.
function logo_gambar($maks = 220) {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    $nama = function_exists('logo_file') ? logo_file() : null;
    if (!$nama) return null;
    $isi = @file_get_contents(__DIR__ . '/../assets/img/' . $nama);
    if ($isi === false || $isi === '') return null;

    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagepng')) {
        $im = @imagecreatefromstring($isi);
        if ($im) {
            $w = imagesx($im); $h = imagesy($im);
            $k = min(1, $maks / max($w, $h));
            $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));

            $png = imagecreatetruecolor($nw, $nh);                      // PNG: transparansi dipertahankan
            imagealphablending($png, false); imagesavealpha($png, true);
            imagefill($png, 0, 0, imagecolorallocatealpha($png, 255, 255, 255, 127));
            imagecopyresampled($png, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $jpg = imagecreatetruecolor($nw, $nh);                      // JPEG: tidak mengenal transparansi -> latar putih
            imagefill($jpg, 0, 0, imagecolorallocate($jpg, 255, 255, 255));
            imagecopyresampled($jpg, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);

            ob_start(); imagepng($png);      $dPng = ob_get_clean();
            ob_start(); imagejpeg($jpg, null, 90); $dJpg = ob_get_clean();
            if ($dPng !== '' && $dJpg !== '') return $cache = ['jpeg' => $dJpg, 'png' => $dPng, 'w' => $nw, 'h' => $nh];
        }
    }
    // Tanpa gd: pakai berkas asli jika formatnya cocok.
    $inf = @getimagesizefromstring($isi);
    if (!$inf) return null;
    $r = ['jpeg' => null, 'png' => null, 'w' => $inf[0], 'h' => $inf[1]];
    if ($inf[2] === IMAGETYPE_JPEG && ($inf['channels'] ?? 3) === 3) $r['jpeg'] = $isi;
    if ($inf[2] === IMAGETYPE_PNG) $r['png'] = $isi;
    return $cache = ($r['jpeg'] || $r['png']) ? $r : null;
}

// Membuat isi file ZIP dari [nama => isi] tanpa ekstensi "zip" PHP (hanya butuh fungsi bawaan; dikompres bila zlib ada).
function zip_buat(array $berkas) {
    $data = ''; $pusat = ''; $n = 0;
    foreach ($berkas as $nama => $isi) {
        $crc = crc32($isi); $asli = strlen($isi);
        $kmp = function_exists('gzdeflate') ? gzdeflate($isi, 6) : false;
        if ($kmp !== false && strlen($kmp) < $asli) { $metode = 8; } else { $metode = 0; $kmp = $isi; }
        $ukuran = strlen($kmp);
        $waktu = 0; $tanggal = (2026 - 1980) << 9 | 1 << 5 | 1;        // cap waktu tetap
        $kepala = pack('vvvvvVVVvv', 20, 0x0800, $metode, $waktu, $tanggal, $crc, $ukuran, $asli, strlen($nama), 0) . $nama;
        $pusat .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, $metode, $waktu, $tanggal, $crc, $ukuran, $asli,
                    strlen($nama), 0, 0, 0, 0, 0, strlen($data)) . $nama;
        $data .= "PK\x03\x04" . $kepala . $kmp;
        $n++;
    }
    return $data . $pusat . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($pusat), strlen($data), 0);
}

/* ====================================================================== PDF */

class PdfTabel {
    const LEBAR  = 841.89;   // A4 lanskap, satuan point (1 pt = 1/72 inci)
    const TINGGI = 595.28;
    const MARGIN = 28;
    const FONT   = 8;        // ukuran huruf isi tabel
    const JARAK  = 10.5;     // tinggi satu baris teks
    const PAD    = 3;        // ruang di dalam sel
    const MAKS_BARIS_SEL = 30;   // sel yang terlalu panjang dipotong agar selalu muat dalam satu halaman

    // Lebar tiap karakter (per 1000 satuan) untuk Helvetica, urut kode Windows-1252 0..255.
    private static $wReg   = [278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584, 350, 556, 350, 222, 556, 333, 1000, 556, 556, 333, 1000, 667, 333, 1000, 350, 611, 350, 350, 222, 222, 333, 333, 350, 556, 1000, 333, 1000, 500, 333, 944, 350, 500, 667, 278, 333, 556, 556, 556, 556, 260, 556, 333, 737, 370, 556, 584, 333, 737, 333, 400, 584, 333, 333, 333, 556, 537, 278, 333, 333, 365, 556, 834, 834, 834, 611, 667, 667, 667, 667, 667, 667, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278, 722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611, 556, 556, 556, 556, 556, 556, 889, 500, 556, 556, 556, 556, 278, 278, 278, 278, 556, 556, 556, 556, 556, 556, 556, 584, 611, 556, 556, 556, 556, 500, 556, 500];
    private static $wTebal = [278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584, 350, 556, 350, 278, 556, 500, 1000, 556, 556, 333, 1000, 667, 333, 1000, 350, 611, 350, 350, 278, 278, 500, 500, 350, 556, 1000, 333, 1000, 556, 333, 944, 350, 500, 667, 278, 333, 556, 556, 556, 556, 280, 556, 333, 737, 370, 556, 584, 333, 737, 333, 400, 584, 333, 333, 333, 611, 556, 278, 333, 333, 365, 556, 834, 834, 834, 611, 722, 722, 722, 722, 722, 722, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278, 722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611, 556, 556, 556, 556, 556, 556, 889, 556, 556, 556, 556, 556, 278, 278, 278, 278, 611, 611, 611, 611, 611, 611, 611, 584, 611, 611, 611, 611, 611, 556, 611, 556];

    private $kolom;          // [[judul, lebar], ...]
    private $hal = [];       // isi tiap halaman yang sudah selesai
    private $isi = '';       // isi halaman yang sedang disusun
    private $y;              // posisi tulis, diukur dari tepi atas halaman
    private $lebar = self::LEBAR, $tinggi = self::TINGGI, $font = self::FONT, $jarak = self::JARAK;
    private $logo = null;    // ['jpeg' => isi file JPEG, 'w' => px, 'h' => px] atau null
    private $kaki = '';      // teks kiri di footer setiap halaman

    // $opsi: 'potret' => true (A4 tegak, default lanskap), 'font' => ukuran huruf isi, 'logo' => hasil logo_gambar(),
    //        'ringkasan' => [[label, nilai, [r,g,b]], ...] kotak angka di atas tabel, 'kaki' => teks footer kiri
    public function __construct(array $kolom, $judul, array $info = [], array $opsi = []) {
        $this->kolom = $kolom;
        if (!empty($opsi['potret'])) { $this->lebar = self::TINGGI; $this->tinggi = self::LEBAR; }
        if (!empty($opsi['font']))   { $this->font = $opsi['font']; $this->jarak = round($opsi['font'] * 1.31, 1); }
        $this->logo = !empty($opsi['logo']['jpeg']) ? $opsi['logo'] : null;
        $this->kaki = (string)($opsi['kaki'] ?? '');
        $this->y = self::MARGIN;

        // Kop: logo di kiri, judul dan keterangan di kanannya.
        $ukLogo = 50;
        $xTeks  = self::MARGIN + ($this->logo ? $ukLogo + 10 : 0);
        $yMulai = $this->y;
        if ($this->logo) {
            $this->isi .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $ukLogo, $ukLogo, self::MARGIN, $this->tinggi - $yMulai - $ukLogo);
        }
        $this->teks($xTeks, $this->y + 13, ke_latin1($judul), 14, true);
        $this->y += 22;
        foreach ($info as $t) {
            $this->teks($xTeks, $this->y + 8, ke_latin1($t), 9, false, [0.35, 0.35, 0.35]);
            $this->y += 12;
        }
        $this->y = max($this->y, $yMulai + ($this->logo ? $ukLogo : 0)) + 8;
        if ($this->logo) {                                   // garis pemisah kop
            $this->isi .= sprintf("0.098 0.529 0.329 RG 1.2 w %.2F %.2F m %.2F %.2F l S\n", self::MARGIN, $this->tinggi - $this->y + 4, $this->lebar - self::MARGIN, $this->tinggi - $this->y + 4);
            $this->y += 6;
        }
        if (!empty($opsi['ringkasan'])) $this->ringkasan($opsi['ringkasan']);
        $this->kepala();
    }

    // Tambah satu baris tabel. $sel = daftar isi sel (urut kolom).
    // $opsi['latar'] = [r,g,b] (0..1) warna latar baris; $opsi['merah'] = nomor kolom yang ditulis merah tebal.
    public function baris(array $sel, array $opsi = []) {
        $batas = $this->tinggi - self::MARGIN;
        $teks = []; $maks = 1;
        foreach ($this->kolom as $i => $k) {
            $teks[$i] = $this->bungkus($sel[$i] ?? '', $k[1] - 2 * self::PAD);
            $maks = max($maks, count($teks[$i]));
        }
        $tinggi = $maks * $this->jarak + 2 * self::PAD;
        if ($this->y + $tinggi > $batas) $this->halamanBaru();

        $latar  = $opsi['latar'] ?? [1, 1, 1];
        $merah  = $opsi['merah'] ?? [];          // kolom yang ditulis merah tebal
        $warna  = $opsi['warna'] ?? [];          // [nomor kolom => [r,g,b]] teks berwarna tebal
        $tengah = $opsi['tengah'] ?? [];         // kolom yang diratakan ke tengah
        $x = self::MARGIN;
        foreach ($this->kolom as $i => $k) {
            $wr = $warna[$i] ?? (in_array($i, $merah, true) ? [0.75, 0.1, 0.1] : null);
            $tebal = $wr !== null;
            $this->kotak($x, $this->y, $k[1], $tinggi, $latar);
            foreach ($teks[$i] as $n => $ln) {
                $tx = $x + self::PAD;
                if (in_array($i, $tengah, true)) $tx = max($tx, $x + ($k[1] - $this->lebarTeks($ln, $tebal)) / 2);
                $this->teks($tx, $this->y + self::PAD + $this->font * 0.94 + $n * $this->jarak, $ln, $this->font, $tebal, $wr ?? [0, 0, 0]);
            }
            $x += $k[1];
        }
        if (!empty($opsi['garis'])) {            // garis hijau tebal di atas baris = awal checklist baru
            $this->isi .= sprintf("0.098 0.529 0.329 RG 1.1 w %.2F %.2F m %.2F %.2F l S\n", self::MARGIN, $this->tinggi - $this->y, $x, $this->tinggi - $this->y);
        }
        $this->y += $tinggi;
    }

    // Susun file PDF lalu kirim ke browser.
    public function kirim($namaFile) {
        $this->hal[] = $this->isi;
        $n = count($this->hal);

        $obj = [];
        $awal = $this->logo ? 6 : 5;                          // nomor objek halaman pertama (5 dipakai gambar logo)
        $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $n; $i++) $kids[] = ($awal + 2 * $i) . ' 0 R';
        $obj[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $obj[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $obj[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $xobj = '';
        if ($this->logo) {
            $obj[5] = sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>',
                    $this->logo['w'], $this->logo['h'], strlen($this->logo['jpeg'])) . "\nstream\n" . $this->logo['jpeg'] . "\nendstream";
            $xobj = ' /XObject << /Im1 5 0 R >>';
        }
        for ($i = 0; $i < $n; $i++) {
            $teksHal = 'Halaman ' . ($i + 1) . ' dari ' . $n;
            $kaki = sprintf("BT /F1 8 Tf 0.4 0.4 0.4 rg %.2F %.2F Td (%s) Tj ET\n",
                $this->lebar - self::MARGIN - $this->lebarTeks($teksHal, false, 8), 14, $teksHal);
            $garisKaki = sprintf("0.8 G 0.5 w %.2F 24 m %.2F 24 l S\n", self::MARGIN, $this->lebar - self::MARGIN);
            $kiri = $this->kaki === '' ? '' : sprintf("BT /F1 8 Tf 0.4 0.4 0.4 rg %.2F 14 Td (%s) Tj ET\n", self::MARGIN, $this->lolos(ke_latin1($this->kaki)));
            $isi = $this->hal[$i] . $garisKaki . $kiri . $kaki;
            $idHal = $awal + 2 * $i;
            $obj[$idHal] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>%s >> /Contents %d 0 R >>',
                $this->lebar, $this->tinggi, $xobj, $idHal + 1);
            $obj[$idHal + 1] = '<< /Length ' . strlen($isi) . " >>\nstream\n" . $isi . "endstream";
        }

        $out = "%PDF-1.4\n";
        $posisi = [];
        $jml = count($obj);
        for ($i = 1; $i <= $jml; $i++) {
            $posisi[$i] = strlen($out);
            $out .= $i . " 0 obj\n" . $obj[$i] . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($jml + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $jml; $i++) $out .= sprintf("%010d 00000 n \n", $posisi[$i]);
        $out .= "trailer\n<< /Size " . ($jml + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

        kirim_unduhan($out, 'application/pdf', $namaFile);
    }

    // ---------- bagian dalam ----------

    private function halamanBaru() {
        $this->hal[] = $this->isi;
        $this->isi = '';
        $this->y = self::MARGIN;
        $this->kepala();
    }

    private function kepala() {
        $tinggi = $this->jarak + 2 * self::PAD;
        $x = self::MARGIN;
        foreach ($this->kolom as $k) {
            $this->kotak($x, $this->y, $k[1], $tinggi, [0.098, 0.529, 0.329]);
            $this->teks($x + self::PAD, $this->y + self::PAD + $this->font * 0.94, ke_latin1($k[0]), $this->font, true, [1, 1, 1]);
            $x += $k[1];
        }
        $this->y += $tinggi;
    }

    // Tulis satu baris teks. $s harus sudah berupa Windows-1252. $yAtas = posisi garis dasar dari tepi atas.
    // Amankan teks untuk dimasukkan ke string PDF: tanda kontrol jadi spasi, \ ( ) diberi escape.
    private function lolos($s) {
        $s = preg_replace('/[\x00-\x1F]/', ' ', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    // Deretan kotak angka ringkasan di atas tabel. $item = [[label, nilai, [r,g,b]], ...]
    private function ringkasan(array $item) {
        $n = count($item); $celah = 8; $h = 36;
        $w = ($this->lebar - 2 * self::MARGIN - $celah * ($n - 1)) / $n;
        $x = self::MARGIN;
        foreach ($item as $it) {
            $wr = $it[2] ?? [0.098, 0.529, 0.329];
            $this->kotak($x, $this->y, $w, $h, [0.95, 0.98, 0.96]);
            $this->isi .= sprintf("%.3F %.3F %.3F rg %.2F %.2F 3.5 %.2F re f\n", $wr[0], $wr[1], $wr[2], $x, $this->tinggi - $this->y - $h, $h);
            $this->teks($x + 12, $this->y + 14, ke_latin1($it[0]), 8, false, [0.4, 0.4, 0.4]);
            $this->teks($x + 12, $this->y + 30, ke_latin1((string)$it[1]), 15, true, $wr);
            $x += $w + $celah;
        }
        $this->y += $h + 12;
    }

    private function teks($x, $yAtas, $s, $ukuran, $tebal = false, array $warna = [0, 0, 0]) {
        $s = $this->lolos($s);
        $this->isi .= sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n",
            $tebal ? 'F2' : 'F1', $ukuran, $warna[0], $warna[1], $warna[2], $x, $this->tinggi - $yAtas, $s);
    }

    private function kotak($x, $yAtas, $w, $h, array $latar) {
        $py = $this->tinggi - $yAtas - $h;
        $this->isi .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $latar[0], $latar[1], $latar[2], $x, $py, $w, $h);
        $this->isi .= sprintf("0.75 G 0.4 w %.2F %.2F %.2F %.2F re S\n", $x, $py, $w, $h);
    }

    private function lebarTeks($s, $tebal = false, $ukuran = null) {
        $w = $tebal ? self::$wTebal : self::$wReg;
        if ($ukuran === null) $ukuran = $this->font;
        $j = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) $j += $w[ord($s[$i])];
        return $j * $ukuran / 1000;
    }

    // Pecah teks menjadi baris-baris yang muat selebar $lebar (hasilnya sudah Windows-1252).
    private function bungkus($teks, $lebar) {
        $hasil = [];
        foreach (preg_split('/\r\n|\r|\n/', ke_latin1($teks)) as $para) {
            $baris = '';
            foreach (explode(' ', $para) as $kata) {
                if ($kata === '') continue;
                $coba = $baris === '' ? $kata : $baris . ' ' . $kata;
                if ($this->lebarTeks($coba) <= $lebar) { $baris = $coba; continue; }
                if ($baris !== '') { $hasil[] = $baris; $baris = ''; }
                while (strlen($kata) > 1 && $this->lebarTeks($kata) > $lebar) {   // satu kata lebih lebar dari sel: potong
                    $n = 1;
                    while ($n < strlen($kata) && $this->lebarTeks(substr($kata, 0, $n + 1)) <= $lebar) $n++;
                    $hasil[] = substr($kata, 0, $n);
                    $kata = substr($kata, $n);
                }
                $baris = $kata;
            }
            $hasil[] = $baris;
        }
        if (count($hasil) > self::MAKS_BARIS_SEL) {
            $hasil = array_slice($hasil, 0, self::MAKS_BARIS_SEL);
            $hasil[self::MAKS_BARIS_SEL - 1] .= '...';
        }
        return $hasil;
    }
}

/* ==================================================================== EXCEL */

function xlsx_xml($s) {
    $b = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string)$s);
    return htmlspecialchars($b === null ? '' : $b, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// 0 -> A, 1 -> B, ... 26 -> AA
function xlsx_huruf($i) {
    $h = '';
    for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) $h = chr(65 + ($n - 1) % 26) . $h;
    return $h;
}

function xlsx_teks($ref, $nilai, $gaya) {
    if ((string)$nilai === '') return '<c r="' . $ref . '" s="' . $gaya . '"/>';
    return '<c r="' . $ref . '" s="' . $gaya . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_xml($nilai) . '</t></is></c>';
}

// $kolom = [[judul, lebar (karakter), jenis], ...] dengan jenis: 't' teks, 'c' teks/angka rata tengah, 'd' tanggal (Y-m-d),
//          's' status (Ya = hijau, Tidak = merah). $pita = [0/1 per baris data]: 1 = baris diberi latar hijau muda (selang-seling per checklist).
// $data  = daftar baris; tiap baris = daftar nilai urut kolom.
// $logo = hasil logo_gambar() (opsional): gambar PNG diletakkan di kiri atas, judul dan info bergeser ke kolom B.
function xlsx_kirim($namaFile, $judul, array $info, array $kolom, array $data, $logo = null, array $pita = []) {
    $logo = (is_array($logo) && !empty($logo['png'])) ? $logo : null;
    $jml    = count($kolom);
    $akhir  = xlsx_huruf($jml - 1);
    $baris  = [];
    $awalT  = $logo ? 'C' : 'A';                // dengan logo (menempati kolom A-B), judul dan info mulai dari kolom C
    $gabung = [$awalT . '1:' . $akhir . '1'];  // sel judul dan info digabung selebar tabel

    $baris[] = '<row r="1" ht="' . ($logo ? 28 : 24) . '" customHeight="1">' . xlsx_teks($awalT . '1', $judul, 1) . '</row>';
    $r = 2;
    foreach ($info as $t) {
        $baris[] = '<row r="' . $r . '"' . ($logo ? ' ht="16" customHeight="1"' : '') . '>' . xlsx_teks($awalT . $r, $t, 2) . '</row>';
        $gabung[] = $awalT . $r . ':' . $akhir . $r;
        $r++;
    }
    $r++;                                       // satu baris kosong sebelum kepala
    $barisH = $r;                               // nomor baris kepala tabel
    $sel = '';
    foreach ($kolom as $i => $k) $sel .= xlsx_teks(xlsx_huruf($i) . $r, $k[0], 3);
    $baris[] = '<row r="' . $r . '" ht="24" customHeight="1">' . $sel . '</row>';
    foreach ($data as $idx => $d) {
        $r++;
        $p = !empty($pita[$idx]) ? 1 : 0;           // 0 = latar putih, 1 = latar hijau muda
        $sel = '';
        foreach ($kolom as $i => $k) {
            $ref = xlsx_huruf($i) . $r;
            $v = $d[$i] ?? '';
            if ($k[2] === 'd' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v)) {
                $sel .= '<c r="' . $ref . '" s="' . (6 + $p) . '"><v>' . ((int)floor(strtotime($v . ' 00:00:00 UTC') / 86400) + 25569) . '</v></c>';
            } elseif (is_int($v)) {                 // angka murni (nomor) disimpan sebagai angka
                $sel .= '<c r="' . $ref . '" s="' . (8 + $p) . '"><v>' . $v . '</v></c>';
            } elseif ($k[2] === 's' && $v === 'Ya') {
                $sel .= xlsx_teks($ref, $v, 10);
            } elseif ($k[2] === 's' && $v === 'Tidak') {
                $sel .= xlsx_teks($ref, $v, 11);
            } else {
                $sel .= xlsx_teks($ref, $v, ($k[2] === 'c' || $k[2] === 's' ? 8 : 4) + $p);
            }
        }
        $baris[] = '<row r="' . $r . '">' . $sel . '</row>';
    }
    $terakhir = $r;

    $lebar = '';
    foreach ($kolom as $i => $k) $lebar .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $k[1] . '" customWidth="1"/>';

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheet = $xml
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="' . $barisH . '" topLeftCell="A' . ($barisH + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>' . $lebar . '</cols>'
        . '<sheetData>' . implode('', $baris) . '</sheetData>'
        . '<autoFilter ref="A' . $barisH . ':' . $akhir . $terakhir . '"/>'
        . '<mergeCells count="' . count($gabung) . '">' . implode('', array_map(function ($g) { return '<mergeCell ref="' . $g . '"/>'; }, $gabung)) . '</mergeCells>'
        . '<printOptions horizontalCentered="1"/>'
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        . '<headerFooter><oddFooter>&amp;L&amp;8Checklist Konservasi Energi&amp;R&amp;8Halaman &amp;P dari &amp;N</oddFooter></headerFooter>'
        . ($logo ? '<drawing r:id="rId1"/>' : '')
        . '</worksheet>';

    $tepi = '<left style="thin"><color rgb="FFBBBBBB"/></left><right style="thin"><color rgb="FFBBBBBB"/></right>'
          . '<top style="thin"><color rgb="FFBBBBBB"/></top><bottom style="thin"><color rgb="FFBBBBBB"/></bottom><diagonal/>';
    $styles = $xml
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd mmm yyyy"/></numFmts>'
        . '<fonts count="6">'
        .   '<font><sz val="11"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="16"/><color rgb="FF146C43"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        .   '<font><i/><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFC00000"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FF146C43"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="6">'
        .   '<fill><patternFill patternType="none"/></fill>'
        .   '<fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF198754"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF7F1"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFFBE4E4"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFE1F3E7"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>' . $tepi . '</border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="12">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
        .   '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .   '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>'
        .   '<xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
        .   '<xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
        .   '<xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $tipeXml = $xml
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Default Extension="png" ContentType="image/png"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . ($logo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
        . '</Types>';
    $relRoot = $xml
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $workbook = $xml
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Checklist" sheetId="1" r:id="rId1"/></sheets>'
        . '<definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">Checklist!$' . $barisH . ':$' . $barisH . '</definedName></definedNames>'
        . '</workbook>';
    $relWb = $xml
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $berkas = [
        '[Content_Types].xml'        => $tipeXml,
        '_rels/.rels'               => $relRoot,
        'xl/workbook.xml'           => $workbook,
        'xl/_rels/workbook.xml.rels' => $relWb,
        'xl/styles.xml'             => $styles,
        'xl/worksheets/sheet1.xml'  => $sheet,
    ];
    if ($logo) {
        $px  = 72;                                                       // sisi logo di Excel (piksel)
        $emu = $px * 9525;
        $berkas['xl/worksheets/_rels/sheet1.xml.rels'] = $xml
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
        $berkas['xl/drawings/drawing1.xml'] = $xml
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>76200</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>38100</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $emu . '" cy="' . $emu . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Logo" descr="Logo Konservasi Energi"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $emu . '" cy="' . $emu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
            . '<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
        $berkas['xl/drawings/_rels/drawing1.xml.rels'] = $xml
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>'
            . '</Relationships>';
        $berkas['xl/media/image1.png'] = $logo['png'];
    }
    $isi = zip_buat($berkas);

    kirim_unduhan($isi, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $namaFile);
}
