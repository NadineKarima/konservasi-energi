<?php
// Export daftar checklist ke PDF atau Excel (.xlsx).
// Isinya sama dengan tabel di checklist/index.php (termasuk kata kunci pencarian ?q=), tetapi SEMUA baris, bukan satu halaman saja.
// Hak akses mengikuti daftar: Siswa dan Guru hanya mengekspor checklist miliknya; Admin mengekspor semua.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/export.php';
login_wajib();

$format = $_GET['format'] ?? '';
if (!in_array($format, ['pdf', 'xlsx'], true)) { http_response_code(400); exit('Format tidak dikenal.'); }

$q = trim($_GET['q'] ?? '');
[$dari, $where, $par] = filter_checklist($q);

$st = $pdo->prepare('SELECT c.id_checklist, c.tanggal, c.jam, ' . SQL_NAMA . ' AS nama, ' . SQL_KELAS . ' AS kelas,
        d.id_detail, d.tahapan, d.kondisi, d.status, d.keterangan, p.nama_perangkat'
    . $dari . $where . '
    ORDER BY c.tanggal DESC, c.jam DESC, c.id_checklist DESC,
             FIELD(d.tahapan, "SEBELUM", "SELAMA", "SESUDAH", "5R"), p.nama_perangkat
    LIMIT 10000');
$st->execute($par);
$data = $st->fetchAll();
if (!$data) { flash('warning', 'Tidak ada data untuk diexport.'); redirect('checklist/'); }

$judul = 'Laporan Checklist Konservasi Energi';
$cetak = 'Dicetak ' . tgl_id(date('Y-m-d')) . ' pukul ' . date('H:i') . ' oleh ' . (user()['nama'] ?? '') . ' (' . (user()['level'] ?? '') . ')';
$nama  = 'checklist_konservasi_' . date('Ymd_His') . '.' . $format;

// Susun baris: [tanggal (Y-m-d), jam, nama, kelas, perangkat, tahapan, kondisi, status, keterangan]
// plus statistik ringkas untuk kepala laporan.
$baris = []; $idChecklist = [];
$ya = 0; $tidak = 0; $siswa = []; $checklist = [];
foreach ($data as $r) {
    $ada = $r['id_detail'] !== null;
    $baris[] = [
        $r['tanggal'], substr($r['jam'], 0, 5), $r['nama'], $r['kelas'],
        $ada ? $r['nama_perangkat'] : '', $ada ? $r['tahapan'] : '', $ada ? $r['kondisi'] : '',
        $ada ? $r['status'] : '', $ada ? (string)$r['keterangan'] : 'Belum ada detail perangkat',
    ];
    $idChecklist[] = (int)$r['id_checklist'];
    $checklist[(int)$r['id_checklist']] = true;
    $siswa[$r['nama'] . '|' . $r['kelas']] = true;
    if ($ada && $r['status'] === 'Ya') $ya++;
    if ($ada && $r['status'] === 'Tidak') $tidak++;
}
$nChecklist = count($checklist);
$nSiswa     = count($siswa);
$periode    = min(array_column($data, 'tanggal')) === max(array_column($data, 'tanggal'))
    ? tgl_id($data[0]['tanggal'])
    : tgl_id(min(array_column($data, 'tanggal'))) . ' s/d ' . tgl_id(max(array_column($data, 'tanggal')));

$info = [
    'Periode data: ' . $periode . ($q !== '' ? '   |   Pencarian: "' . $q . '"' : ''),
    $cetak,
];

// Pita latar selang-seling per checklist (satu checklist = satu warna), dipakai PDF dan Excel.
$pita = []; $nomor = []; $gelap = false; $prev = null; $no = 0;
foreach ($idChecklist as $i => $id) {
    if ($prev !== $id) { $no++; if ($prev !== null) $gelap = !$gelap; }
    $prev = $id;
    $pita[$i]  = $gelap ? 1 : 0;
    $nomor[$i] = $no;
}

if ($format === 'xlsx') {
    // Excel: semua sel diisi penuh (tanpa sel kosong) supaya mudah difilter / dijadikan pivot.
    $info[] = 'Ringkasan: ' . count($data) . ' data dari ' . $nChecklist . ' checklist, ' . $nSiswa . ' pengisi   |   Status Ya: ' . $ya . '   |   Status Tidak: ' . $tidak;
    $kolom = [['No', 5, 'c'], ['Tanggal', 14, 'd'], ['Jam', 8, 'c'], ['Nama', 26, 't'], ['Kelas', 11, 't'], ['Perangkat', 28, 't'],
              ['Tahapan', 12, 'c'], ['Kondisi', 22, 't'], ['Status', 10, 's'], ['Keterangan', 40, 't']];
    $isiX = [];
    foreach ($baris as $i => $b) $isiX[] = array_merge([$nomor[$i]], $b);
    xlsx_kirim($nama, $judul, $info, $kolom, $isiX, logo_gambar(), $pita);
}

// PDF: lebar kolom dalam point, total 785 (area cetak A4 lanskap)
$kolomPdf = [['No', 24], ['Tanggal', 58], ['Jam', 30], ['Nama', 118], ['Kelas', 52], ['Perangkat', 118],
             ['Tahapan', 55], ['Kondisi', 100], ['Status', 38], ['Keterangan', 192]];
$pdf = new PdfTabel($kolomPdf, $judul, $info, [
    'logo' => logo_gambar(),
    'kaki' => 'Checklist Konservasi Energi',
    'ringkasan' => [
        ['TOTAL DATA',  count($data),  [0.098, 0.529, 0.329]],
        ['CHECKLIST',   $nChecklist,   [0.20, 0.45, 0.80]],
        ['PENGISI',     $nSiswa,       [0.55, 0.40, 0.75]],
        ['STATUS YA',   $ya,           [0.08, 0.50, 0.25]],
        ['STATUS TIDAK', $tidak,       [0.75, 0.10, 0.10]],
    ],
]);
$hijau = [0.08, 0.50, 0.25]; $merah = [0.75, 0.10, 0.10];
$prev = null;
foreach ($baris as $i => $b) {
    $baru = $prev !== $idChecklist[$i];                  // baris pertama sebuah checklist
    $prev = $idChecklist[$i];
    // Identitas (No, tanggal, jam, nama, kelas) hanya ditulis di baris pertama checklist; baris berikutnya dikosongkan.
    $sel = [
        $baru ? (string)$nomor[$i] : '', $baru ? tgl_id($b[0]) : '', $baru ? $b[1] : '', $baru ? $b[2] : '', $baru ? $b[3] : '',
        $b[4], $b[5], $b[6], $b[7], $b[8],
    ];
    $pdf->baris($sel, [
        'latar'  => $pita[$i] ? [0.93, 0.97, 0.94] : [1, 1, 1],
        'tengah' => [0, 2, 6, 8],
        'warna'  => $b[7] === 'Ya' ? [8 => $hijau] : ($b[7] === 'Tidak' ? [8 => $merah] : []),
        'garis'  => $baru && $i > 0,
    ]);
}
$pdf->kirim($nama);
