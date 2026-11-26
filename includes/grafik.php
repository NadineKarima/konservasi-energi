<?php
// Grafik garis berbentuk area (SVG murni, tanpa library dan tanpa internet), dipakai di dashboard.
// $label  = daftar label sumbu X (mis. nama hari); $nilai = persen 0..100 per label, null = belum ada data (garis terputus).
// Animasi (garis tergambar, titik muncul) diatur di assets/css/app.css dan otomatis mati bila perangkat meminta "kurangi gerakan".
function grafik_garis(array $label, array $nilai, $satuan = '%') {
    static $n = 0;
    $n++;
    $jml = count($nilai);
    $ada = 0;
    foreach ($nilai as $v) if ($v !== null) $ada++;
    if ($jml === 0 || $ada === 0) return '<div class="muted text-center py-5">Belum ada data untuk ditampilkan.</div>';

    $W = 640; $H = 240; $kiri = 40; $kanan = 18; $atas = 24; $bawah = 34;
    $lebar = $W - $kiri - $kanan; $tinggi = $H - $atas - $bawah; $dasar = $atas + $tinggi;
    $ruang = 28;                                    // ruang di tepi kiri/kanan agar titik dan nilainya tidak menempel sumbu
    $px = function ($i) use ($jml, $kiri, $lebar, $ruang) { return $kiri + $ruang + ($jml > 1 ? ($lebar - 2 * $ruang) * $i / ($jml - 1) : ($lebar - 2 * $ruang) / 2); };
    $py = function ($v) use ($atas, $tinggi) { return $atas + $tinggi * (1 - max(0, min(100, $v)) / 100); };
    $f = function ($x) { return sprintf('%.1F', $x); };

    $s  = '<svg class="gf" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Grafik garis">';
    $s .= '<defs><linearGradient id="gfg' . $n . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" style="stop-color:var(--pri)" stop-opacity=".35"/><stop offset="1" style="stop-color:var(--pri)" stop-opacity="0"/></linearGradient></defs>';

    // Garis bantu horizontal dan label sumbu Y
    foreach ([0, 25, 50, 75, 100] as $g) {
        $y = $py($g);
        $s .= '<line class="gf-grid" x1="' . $kiri . '" x2="' . ($W - $kanan) . '" y1="' . $f($y) . '" y2="' . $f($y) . '"/>';
        if ($g % 50 === 0) $s .= '<text class="gf-sb" x="' . ($kiri - 8) . '" y="' . $f($y + 4) . '" text-anchor="end">' . $g . $satuan . '</text>';
    }
    // Label sumbu X
    foreach ($label as $i => $lb) {
        $s .= '<text class="gf-sb" x="' . $f($px($i)) . '" y="' . ($H - 10) . '" text-anchor="middle">' . e($lb) . '</text>';
    }

    // Bagi data menjadi ruas-ruas yang berurutan (hari tanpa data memutus garis)
    $ruas = []; $cur = [];
    for ($i = 0; $i < $jml; $i++) {
        if ($nilai[$i] === null) { if ($cur) { $ruas[] = $cur; $cur = []; } }
        else $cur[] = $i;
    }
    if ($cur) $ruas[] = $cur;

    foreach ($ruas as $r) {
        if (count($r) < 2) continue;
        $d = 'M' . $f($px($r[0])) . ' ' . $f($py($nilai[$r[0]]));
        for ($k = 1; $k < count($r); $k++) {            // kurva halus: tangen mendatar, tidak pernah melewati nilai tertinggi/terendah
            $x0 = $px($r[$k - 1]); $y0 = $py($nilai[$r[$k - 1]]); $x1 = $px($r[$k]); $y1 = $py($nilai[$r[$k]]); $xm = ($x0 + $x1) / 2;
            $d .= ' C' . $f($xm) . ' ' . $f($y0) . ' ' . $f($xm) . ' ' . $f($y1) . ' ' . $f($x1) . ' ' . $f($y1);
        }
        $akhir = $r[count($r) - 1];
        $s .= '<path class="gf-area" fill="url(#gfg' . $n . ')" d="' . $d . ' L' . $f($px($akhir)) . ' ' . $f($dasar) . ' L' . $f($px($r[0])) . ' ' . $f($dasar) . ' Z"/>';
        $s .= '<path class="gf-line" pathLength="1" d="' . $d . '"/>';
    }

    // Titik data, nilainya tampil di atas titik dan sebagai tooltip
    foreach ($nilai as $i => $v) {
        $label_i = isset($label[$i]) ? $label[$i] : '';
        if ($v === null) continue;
        $s .= '<g class="gf-t" style="animation-delay:' . $f(0.5 + $i * 0.09) . 's">'
            . '<circle class="gf-dot" cx="' . $f($px($i)) . '" cy="' . $f($py($v)) . '" r="4.5"><title>' . e($label_i) . ': ' . (int)$v . $satuan . '</title></circle>'
            . '<text class="gf-nilai" x="' . $f($px($i)) . '" y="' . $f($py($v) - 11) . '" text-anchor="middle">' . (int)$v . $satuan . '</text></g>';
    }
    return $s . '</svg>';
}

// Pengukur melingkar (SVG murni) untuk persentase 0..100; $persen null = belum ada data. Animasi dan warna diatur di app.css (.gauge).
function gauge_ring($persen, $label = 'Kepatuhan') {
    static $n = 0;
    $n++;
    $r = 54; $keliling = 2 * M_PI * $r;
    $p = $persen === null ? 0 : max(0, min(100, (int)$persen));
    $off = $keliling * (1 - $p / 100);
    $k = sprintf('%.2F', $keliling); $o = sprintf('%.2F', $off);
    return '<div class="gauge"><svg viewBox="0 0 140 140" role="img" aria-label="' . e($label) . ' ' . ($persen === null ? 'belum ada data' : $p . ' persen') . '">'
        . '<defs><linearGradient id="gaugeGrad' . $n . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c4b0ff"/><stop offset="1" stop-color="#8B5CF6"/></linearGradient></defs>'
        . '<circle class="gauge-bg" cx="70" cy="70" r="' . $r . '"/>'
        . '<circle class="gauge-fg" style="stroke:url(#gaugeGrad' . $n . ');--panjang:' . $k . '" cx="70" cy="70" r="' . $r . '" transform="rotate(-90 70 70)" stroke-dasharray="' . $k . '" stroke-dashoffset="' . $o . '"/>'
        . '</svg><div class="gauge-isi"><b>' . ($persen === null ? '-' : $p . '<span>%</span>') . '</b><small>' . e($label) . '</small></div></div>';
}
