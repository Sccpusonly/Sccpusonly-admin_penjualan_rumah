<?php
// ============================================================
// Ilustrasi perumahan (SVG) untuk hero beranda.
// Warna diatur lewat variabel CSS di efek.css, jadi otomatis
// berubah jadi suasana malam saat mode gelap aktif.
// Setiap <g class="layer" data-depth="..."> bergerak parallax.
// ============================================================

// Menggambar satu rumah. $model: 'pelana' (atap segitiga) atau 'modern' (atap datar)
function gambar_rumah($x, $lebar, $tinggi, $model, $warnaDinding)
{
    $dasar = 520;                 // garis tanah
    $atas  = $dasar - $tinggi;
    $svg   = '<g class="rumah">';

    // badan rumah
    $svg .= "<rect x='$x' y='$atas' width='$lebar' height='$tinggi' fill='var($warnaDinding)'/>";

    if ($model === 'pelana') {
        $puncak = $atas - $lebar * 0.42;
        $kiri = $x - 12; $kanan = $x + $lebar + 12; $tengah = $x + $lebar / 2;
        $svg .= "<polygon points='$kiri,$atas $tengah,$puncak $kanan,$atas' fill='var(--roof)'/>";
    } else {
        $svg .= "<rect x='" . ($x - 10) . "' y='" . ($atas - 14) . "' width='" . ($lebar + 20) . "' height='16' rx='3' fill='var(--roof)'/>";
        // aksen kayu di sisi kanan
        $svg .= "<rect x='" . ($x + $lebar * 0.62) . "' y='$atas' width='" . ($lebar * 0.38) . "' height='$tinggi' fill='var(--wood)'/>";
    }

    // jendela (class "win" menyala bergantian)
    $jw = $lebar * 0.2; $jh = $tinggi * 0.26;
    $svg .= "<rect class='win' x='" . ($x + $lebar * 0.12) . "' y='" . ($atas + $tinggi * 0.2) . "' width='$jw' height='$jh' rx='2'/>";
    $svg .= "<rect class='win win-b' x='" . ($x + $lebar * 0.4) . "' y='" . ($atas + $tinggi * 0.2) . "' width='$jw' height='$jh' rx='2'/>";

    // pintu
    $svg .= "<rect x='" . ($x + $lebar * 0.14) . "' y='" . ($dasar - $tinggi * 0.42) . "' width='" . ($lebar * 0.16) . "' height='" . ($tinggi * 0.42) . "' fill='var(--door)' rx='2'/>";

    return $svg . '</g>';
}

function gambar_pohon($x, $skala = 1)
{
    $t = 520;
    return "<g class='pohon' style='transform-origin:{$x}px {$t}px'>"
        . "<rect x='" . ($x - 3 * $skala) . "' y='" . ($t - 40 * $skala) . "' width='" . (6 * $skala) . "' height='" . (40 * $skala) . "' fill='var(--trunk)'/>"
        . "<circle cx='$x' cy='" . ($t - 52 * $skala) . "' r='" . (24 * $skala) . "' fill='var(--leaf)'/>"
        . "<circle cx='" . ($x - 14 * $skala) . "' cy='" . ($t - 40 * $skala) . "' r='" . (16 * $skala) . "' fill='var(--leaf-2)'/>"
        . "</g>";
}
?>
<svg class="hero-scene" viewBox="0 0 1600 600" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
    <defs>
        <linearGradient id="gLangit" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" style="stop-color:var(--sky-1)"/>
            <stop offset="0.6" style="stop-color:var(--sky-2)"/>
            <stop offset="1" style="stop-color:var(--sky-3)"/>
        </linearGradient>
        <radialGradient id="gCahaya">
            <stop offset="0" style="stop-color:var(--sun);stop-opacity:.9"/>
            <stop offset="1" style="stop-color:var(--sun);stop-opacity:0"/>
        </radialGradient>
    </defs>

    <!-- langit -->
    <rect width="1600" height="600" fill="url(#gLangit)"/>

    <!-- bintang (hanya terlihat di mode gelap) -->
    <g class="bintang">
        <?php
        mt_srand(7); // acak tapi selalu sama
        for ($i = 0; $i < 70; $i++) {
            $bx = mt_rand(0, 1600); $by = mt_rand(0, 300); $br = mt_rand(5, 16) / 10;
            $delay = mt_rand(0, 40) / 10;
            echo "<circle cx='$bx' cy='$by' r='$br' style='animation-delay:{$delay}s'/>";
        }
        ?>
    </g>

    <!-- matahari / bulan -->
    <g class="layer" data-depth="0.08">
        <circle cx="1180" cy="150" r="170" fill="url(#gCahaya)"/>
        <circle class="matahari" cx="1180" cy="150" r="58"/>
    </g>

    <!-- awan -->
    <g class="awan awan-1"><path d="M0 0h150a30 30 0 0 0-30-30a40 40 0 0 0-70-10a30 30 0 0 0-50 40z" transform="translate(180 130)"/></g>
    <g class="awan awan-2"><path d="M0 0h200a36 36 0 0 0-36-36a50 50 0 0 0-90-14a36 36 0 0 0-74 50z" transform="translate(760 90)"/></g>
    <g class="awan awan-3"><path d="M0 0h120a24 24 0 0 0-24-24a32 32 0 0 0-58-8a24 24 0 0 0-38 32z" transform="translate(1340 250)"/></g>

    <!-- bukit jauh -->
    <g class="layer" data-depth="0.15">
        <path d="M0 400 Q200 300 420 360 T860 340 T1300 330 T1600 350 V600 H0Z" fill="var(--hill-1)"/>
    </g>

    <!-- deretan rumah jauh (siluet) -->
    <g class="layer" data-depth="0.28">
        <path d="M0 440 Q400 390 800 420 T1600 410 V600 H0Z" fill="var(--hill-2)"/>
        <g fill="var(--house-far)">
            <?php
            for ($x = 30; $x < 1600; $x += 74) {
                $h = 34 + ($x % 3) * 8;
                $y = 450 - $h;
                echo "<rect x='$x' y='$y' width='52' height='$h'/>";
                echo "<polygon points='" . ($x - 5) . ",$y " . ($x + 26) . "," . ($y - 20) . " " . ($x + 57) . ",$y'/>";
                if ($x % 4 === 2) echo "<rect class='win win-far' x='" . ($x + 10) . "' y='" . ($y + 10) . "' width='9' height='9'/>";
            }
            ?>
        </g>
    </g>

    <!-- rumah utama -->
    <g class="layer" data-depth="0.45">
        <rect x="0" y="508" width="1600" height="92" fill="var(--ground)"/>
        <?php
        $deret = [
            [470, 150, 118, 'pelana', '--wall-2'],
            [680, 190, 150, 'modern', '--wall-1'],
            [915, 200, 168, 'pelana', '--wall-3'],
            [1160, 190, 150, 'modern', '--wall-2'],
            [1395, 170, 140, 'pelana', '--wall-1'],
        ];
        foreach ($deret as [$x, $w, $h, $m, $c]) echo gambar_rumah($x, $w, $h, $m, $c);

        foreach ([445, 650, 890, 1135, 1375, 1590] as $i => $px) echo gambar_pohon($px, $i % 2 ? 1 : 1.25);
        ?>
    </g>

    <!-- jalan -->
    <g class="layer" data-depth="0.6">
        <path d="M0 548 H1600 V600 H0Z" fill="var(--road)"/>
        <g class="marka"><?php for ($x = 0; $x < 1700; $x += 90) echo "<rect x='$x' y='571' width='46' height='5' rx='2'/>"; ?></g>
    </g>
</svg>