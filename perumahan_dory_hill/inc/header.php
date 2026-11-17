<?php
require_once __DIR__ . '/ui.php';
// $judul diisi di masing-masing halaman sebelum include header
$halaman = basename($_SERVER['PHP_SELF']);
$judul   = $judul ?? 'Beranda';

function aktif($nama)
{
    global $halaman;
    return $halaman === $nama ? 'active' : '';
}

// Inisial untuk avatar
$inisial = array_reduce(
    explode(' ', trim($_SESSION['nama'] ?? 'U')),
    function ($a, $b) { return $a . mb_strtoupper(mb_substr($b, 0, 1)); },
    ''
);
$inisial = mb_substr($inisial, 0, 2);
$fotoSaya = foto_user($_SESSION['id_user'] ?? 0);

// Menu sidebar: grup label + item [file, label, ikon]
$menu = [
    [
        'group' => 'Utama',
        'items' => [
            ['index.php', 'Beranda', 'bi-grid-1x2-fill'],
        ],
    ],
    [
        'group' => 'Data Master',
        'items' => [
            ['tipe.php', 'Tipe Rumah', 'bi-houses'],
            ['unit.php', 'Unit / Kavling', 'bi-geo-alt'],
            ['denah.php', 'Denah Site Plan', 'bi-map'],
            ['konsumen.php', 'Konsumen', 'bi-people'],
            ['marketing.php', 'Marketing', 'bi-person-badge'],
        ],
    ],
    [
        'group' => 'Transaksi',
        'items' => [
            ['booking.php', 'Booking Unit', 'bi-journal-check'],
            ['pembayaran.php', 'Pembayaran', 'bi-credit-card'],
            ['kwitansi.php', 'Kwitansi', 'bi-receipt'],
        ],
    ],
    [
        'group' => 'Analitik',
        'items' => [
            ['laporan.php', 'Laporan', 'bi-graph-up-arrow'],
        ],
    ],
];

// Menu "Alat Bantu" di tombol bawah sidebar: [file, judul, keterangan, ikon]
// Isinya fitur yang TIDAK ada di sidebar, supaya tidak dobel
$alatBantu = [
    ['simulasi.php', 'Simulasi KPR', 'Hitung cicilan per bulan', 'bi-calculator'],
    ['tagihan.php', 'Tagihan Belum Lunas', 'Konsumen yang perlu ditagih', 'bi-bell'],
    ['pricelist.php', 'Price List', 'Cetak daftar harga unit', 'bi-tags'],
];

// Nama halaman aktif untuk crumb di topbar
$crumb = 'Beranda';
foreach ($menu as $g) {
    foreach ($g['items'] as [$f, $l, $i]) {
        if ($f === $halaman) $crumb = $l;
    }
}
foreach ($alatBantu as [$f, $l]) {
    if ($f === $halaman) $crumb = $l;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light" class="<?= !empty($intro) ? 'ada-intro' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        // Terapkan tema & state sidebar sebelum paint supaya tidak kedip
        (function () {
            try {
                var t = localStorage.getItem('siperumahan-theme-v2');
                if (t !== 'light' && t !== 'dark') t = 'light';
                document.documentElement.setAttribute('data-theme', t);
                // datang dari transisi halaman? layar langsung gelap dulu, nanti efek.js membukanya
                if (sessionStorage.getItem('siperumahan-tirai') === '1') {
                    document.documentElement.classList.add('pt-masuk');
                    sessionStorage.removeItem('siperumahan-tirai');
                    document.write('<style id="ptKritis">html.pt-masuk,html.pt-masuk body{background:#111014!important}html.pt-masuk body>*:not(.bilah){visibility:hidden}</style>');
                }
                if (localStorage.getItem('siperumahan-rail') === '1') {
                    document.documentElement.classList.add('rail-collapsed');
                }
            } catch (e) {}
        })();
    </script>
    <title><?= e($judul) ?> | SI Perumahan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?>" rel="stylesheet">
    <link href="assets/efek.css?v=<?= @filemtime(__DIR__ . '/../assets/efek.css') ?>" rel="stylesheet">
</head>
<body class="<?= !empty($polos) ? 'hal-polos' : '' ?>">
<a class="skip-link" href="#konten">Lewati ke konten</a>

<?php
// ================= POP-UP KONFIRMASI (berhasil / gagal) =================
// Diisi oleh fungsi konfirmasi() di config/koneksi.php, tampil sekali lalu dihapus
$notif = $_SESSION['konfirmasi'] ?? null;
unset($_SESSION['konfirmasi']);
if ($notif):
    $ok = $notif['status'] === 'berhasil';
?>
<div class="notif-pop <?= $ok ? 'is-ok' : 'is-gagal' ?>" id="notifPop" role="alertdialog" aria-modal="true" aria-labelledby="npJudul" aria-describedby="npPesan" data-auto="<?= $ok ? '4000' : '0' ?>">
    <div class="np-card">
        <div class="np-ikon">
            <svg viewBox="0 0 80 80" aria-hidden="true">
                <circle class="np-lingkar" cx="40" cy="40" r="34"/>
                <?php if ($ok): ?>
                    <path class="np-garis" d="M25 41 l10 10 l20 -22"/>
                <?php else: ?>
                    <path class="np-garis" d="M28 28 L52 52"/>
                    <path class="np-garis np-garis-2" d="M52 28 L28 52"/>
                <?php endif; ?>
            </svg>
        </div>
        <h4 id="npJudul"><?= $ok ? 'Berhasil!' : 'Gagal!' ?></h4>
        <p id="npPesan"><?= e($notif['pesan']) ?></p>
        <button type="button" class="np-btn" data-tutup-notif>
            <?= $ok ? 'Oke, lanjutkan' : 'Mengerti, coba lagi' ?>
        </button>
        <?php if ($ok): ?><span class="np-waktu"></span><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($intro)):
    // ================= INTRO LAYAR PENUH (hanya di Beranda) =================
    $fotoIntro = file_exists(__DIR__ . '/../assets/img/hero.jpg') ? 'assets/img/hero.jpg' : null;
?>
<section class="intro no-print" id="intro" aria-label="Pembuka">
    <div class="intro-lekat">
        <div class="intro-gambar" id="introGambar">
            <?php if ($fotoIntro): ?>
                <div class="intro-foto" style="background-image:url('<?= $fotoIntro ?>')"></div>
            <?php else: ?>
                <?php include __DIR__ . '/hero-scene.php'; ?>
            <?php endif; ?>
        </div>
        <div class="intro-gelap"></div>
        <div class="intro-teks" id="introTeks">
            <span class="intro-kicker">Selamat datang di</span>
            <h1 class="intro-nama"><?= e($namaPerumahan ?? 'Dory Hill') ?></h1>
            <p class="intro-sub">Sistem Informasi Pemesanan Unit Perumahan &middot; Batam</p>
        </div>
        <a href="#konten" class="intro-scroll" id="introScroll" aria-label="Gulir ke dashboard"><span></span>Scroll</a>
    </div>
</section>
<?php endif; ?>

<div class="app-shell tanpa-sidebar">

<?php if (empty($polos)): /* halaman "polos" (mis. profil) tanpa header & dock */ ?>
<!-- ================= HEADER ================= -->
<header class="kepala no-print" id="kepala">
    <a class="kp-logo" href="index.php" data-judul="Beranda">
        <span class="kp-ikon"><i class="bi bi-houses-fill"></i></span>
        <span class="kp-nama">SI Perumahan<small>Dory Hill</small></span>
    </a>

    <div class="tb-crumb kp-crumb"><span class="kp-titik"></span><b><?= e($crumb) ?></b></div>

    <div class="kp-kanan">
        <button class="kp-pil kp-bulat" id="themeToggle" type="button" aria-label="Ganti tema">
            <i class="bi bi-moon-stars"></i>
        </button>

        <div class="dropdown">
            <button class="kp-pil kp-user" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Akun">
                <?= avatar($fotoSaya, $inisial) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-header"><?= e($_SESSION['nama']) ?> &middot; <?= e($_SESSION['level']) ?></span></li>
                <li>
                    <a class="dropdown-item <?= aktif('profil.php') ?>" href="profil.php">
                        <i class="bi bi-person me-2"></i>Profil &amp; Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php" data-confirm="Yakin mau logout?">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>

    </div>
</header>

<!-- ================= DOCK NAVIGASI (bawah layar) ================= -->
<?php
// Kelompok menu di dock. Item: [file, label, keterangan, ikon]
$dock = [
    ['tipe' => 'link', 'file' => 'index.php', 'label' => 'Beranda', 'ikon' => 'bi-grid-1x2'],
    ['tipe' => 'grup', 'label' => 'Data Master', 'ikon' => 'bi-database', 'isi' => [
        ['tipe.php', 'Tipe Rumah', 'Katalog tipe & harga', 'bi-houses'],
        ['unit.php', 'Unit / Kavling', 'Status setiap kavling', 'bi-geo-alt'],
        ['denah.php', 'Denah Site Plan', 'Peta blok interaktif', 'bi-map'],
        ['konsumen.php', 'Konsumen', 'Data calon pembeli', 'bi-people'],
        ['marketing.php', 'Marketing', 'Tim penjualan', 'bi-person-badge'],
    ]],
    ['tipe' => 'grup', 'label' => 'Transaksi', 'ikon' => 'bi-arrow-left-right', 'isi' => [
        ['booking.php', 'Booking Unit', 'Reservasi unit oleh konsumen', 'bi-journal-check'],
        ['pembayaran.php', 'Pembayaran', 'DP, angsuran, pencairan KPR', 'bi-credit-card'],
        ['kwitansi.php', 'Kwitansi', 'Cetak bukti pembayaran', 'bi-receipt'],
    ]],
    ['tipe' => 'link', 'file' => 'laporan.php', 'label' => 'Laporan', 'ikon' => 'bi-graph-up-arrow'],
    ['tipe' => 'grup', 'label' => 'Alat Bantu', 'ikon' => 'bi-lightning-charge', 'aksen' => true, 'isi' => $alatBantu],
];
?>
<nav class="dock no-print" id="dock" aria-label="Menu utama">
    <?php foreach ($dock as $gi => $d):
        if ($d['tipe'] === 'link'): ?>
            <a class="dock-it <?= $halaman === $d['file'] ? 'aktif' : '' ?>" href="<?= $d['file'] ?>" data-judul="<?= e($d['label']) ?>">
                <i class="bi <?= $d['ikon'] ?>"></i><span><?= e($d['label']) ?></span>
            </a>
        <?php else:
            $aktifGrup = in_array($halaman, array_column($d['isi'], 0));
        ?>
            <div class="dock-grup">
                <button type="button" class="dock-it <?= $aktifGrup ? 'aktif' : '' ?> <?= !empty($d['aksen']) ? 'aksen' : '' ?>"
                        aria-expanded="false" aria-controls="dockPanel<?= $gi ?>">
                    <i class="bi <?= $d['ikon'] ?>"></i><span><?= e($d['label']) ?></span>
                </button>
                <div class="dock-panel" id="dockPanel<?= $gi ?>">
                    <span class="dock-panel-judul"><?= e($d['label']) ?></span>
                    <?php foreach ($d['isi'] as [$f, $l, $ket, $ic]): ?>
                        <a href="<?= $f ?>" class="<?= $halaman === $f ? 'aktif' : '' ?>" data-judul="<?= e($l) ?>">
                            <i class="bi <?= $ic ?>"></i>
                            <span><b><?= e($l) ?></b><small><?= e($ket) ?></small></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif;
        if ($gi === 0 || $gi === 3): ?><span class="dock-garis"></span><?php endif;
    endforeach; ?>
</nav>
<?php endif; ?>

<div class="app-main">
    <main class="container main-content" id="konten">