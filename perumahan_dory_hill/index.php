<?php
require_once "inc/cek_login.php";
$judul  = "Beranda";
$kicker = "Dashboard";

// ---------- Statistik ----------
$tersedia = (int) ambil_satu("SELECT COUNT(*) AS n FROM unit WHERE status = 'Tersedia'")['n'];
$dibooking = (int) ambil_satu("SELECT COUNT(*) AS n FROM unit WHERE status = 'Booking'")['n'];
$terjual  = (int) ambil_satu("SELECT COUNT(*) AS n FROM unit WHERE status = 'Terjual'")['n'];
$totalUnit = $tersedia + $dibooking + $terjual;
$konsumen = (int) ambil_satu("SELECT COUNT(*) AS n FROM konsumen")['n'];
$pemasukan_bulan_ini = (float) ambil_satu(
    "SELECT COALESCE(SUM(jumlah),0) AS n FROM pembayaran
     WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())"
)['n'];
$pemasukan_kemarin = (float) ambil_satu(
    "SELECT COALESCE(SUM(jumlah),0) AS n FROM pembayaran
     WHERE MONTH(tanggal) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
       AND YEAR(tanggal) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
)['n'];

// ---------- Pemasukan 12 bulan terakhir (untuk grafik) ----------
$namaBulanSingkat = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$baris = ambil_semua(
    "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS ym, SUM(jumlah) AS total
     FROM pembayaran
     WHERE tanggal >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
     GROUP BY ym"
);
$peta = [];
foreach ($baris as $r) $peta[$r['ym']] = (float) $r['total'];

$labelBulan = [];
$nilaiBulan = [];
for ($i = 11; $i >= 0; $i--) {
    $ts = strtotime(date('Y-m-01') . " -$i months");
    $kunci = date('Y-m', $ts);
    $labelBulan[] = $namaBulanSingkat[(int) date('n', $ts) - 1];
    $nilaiBulan[] = $peta[$kunci] ?? 0;
}

// ---------- Booking terbaru ----------
$terbaru = ambil_semua(
    "SELECT b.*, k.nama AS konsumen, u.blok, u.nomor, t.nama_tipe
     FROM booking b
     JOIN konsumen k ON b.id_konsumen = k.id_konsumen
     JOIN unit u     ON b.id_unit = u.id_unit
     JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     ORDER BY b.id_booking DESC LIMIT 8"
);

// ---------- Data untuk notifikasi pop-up ----------
// Booking aktif yang sudah > 30 hari tidak ada pembayaran
$perluDitagih = (int) ambil_satu(
    "SELECT COUNT(*) AS n FROM (
        SELECT b.id_booking, MAX(p.tanggal) AS terakhir
        FROM booking b LEFT JOIN pembayaran p ON p.id_booking = b.id_booking
        WHERE b.status = 'Aktif'
        GROUP BY b.id_booking
        HAVING COALESCE(terakhir, '1970-01-01') < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ) x"
)['n'];

// Pop-up sambutan hanya muncul sekali setiap login
$tampilSambutan = empty($_SESSION['sudah_disambut']);
$_SESSION['sudah_disambut'] = true;
// Saat baru login, pesan "Login berhasil" diganti pop-up sambutan saja
if ($tampilSambutan) unset($_SESSION['konfirmasi']);

// Kalau ada foto asli di assets/img/hero.jpg, foto itu yang dipakai di hero
// Intro layar penuh (gambar + nama perumahan) sebelum dashboard, dibuat di inc/header.php
$intro = true;
$namaPerumahan = 'Dory Hill';

include "inc/header.php";

// Tanggal lengkap bahasa Indonesia, contoh: Rabu, 30 September 2026
$namaHari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
              'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tanggalIndo = $namaHari[date('w')] . ', ' . date('j') . ' ' . $namaBulan[date('n') - 1] . ' ' . date('Y');

$sapaan = date('G') < 11 ? 'Selamat pagi' : (date('G') < 15 ? 'Selamat siang' : (date('G') < 18 ? 'Selamat sore' : 'Selamat malam'));
$persenTerjual = $totalUnit ? round($terjual / $totalUnit * 100) : 0;
$persenBooking = $totalUnit ? round($dibooking / $totalUnit * 100) : 0;
$persenTersedia = $totalUnit ? round($tersedia / $totalUnit * 100) : 0;
$selisih = $pemasukan_kemarin > 0
    ? round(($pemasukan_bulan_ini - $pemasukan_kemarin) / $pemasukan_kemarin * 100)
    : null;
?>
<!-- ============ HERO SHOWCASE ============ -->
<section class="showcase ringkas" id="showcase">

    <div class="sc-content" id="scContent">
        <span class="sc-kicker"><i class="bi bi-calendar3"></i> <?= $tanggalIndo ?></span>
        <h1 class="sc-title">
            <?= $sapaan ?>,<br>
            <span class="sc-name"><?= e(explode(' ', trim($_SESSION['nama']))[0]) ?>.</span>
        </h1>
        <p class="sc-text">
            <strong><?= $tersedia ?> unit</strong> siap dipasarkan, <?= $dibooking ?> sedang dibooking,
            dan <?= $terjual ?> sudah terjual dari total <?= $totalUnit ?> unit.
        </p>
        <div class="sc-actions">
            <a href="booking.php?aksi=tambah" class="btn btn-hero"><i class="bi bi-plus-lg"></i> Booking Baru</a>
            <a href="denah.php" class="btn btn-hero-ghost"><i class="bi bi-map"></i> Denah Site Plan</a>
        </div>
    </div>

    <div class="sc-chips" id="scChips">
        <div class="sc-chip">
            <span class="sc-chip-ic"><i class="bi bi-wallet2"></i></span>
            <div>
                <small>Pemasukan bulan ini</small>
                <b data-countup="<?= $pemasukan_bulan_ini ?>" data-prefix="Rp ">Rp 0</b>
                <?php if ($selisih !== null): ?>
                    <em class="<?= $selisih >= 0 ? 'naik' : 'turun' ?>">
                        <i class="bi bi-arrow-<?= $selisih >= 0 ? 'up' : 'down' ?>-right"></i>
                        <?= abs($selisih) ?>% vs bulan lalu
                    </em>
                <?php endif; ?>
            </div>
        </div>
        <div class="sc-chip">
            <span class="sc-chip-ic"><i class="bi bi-house-check"></i></span>
            <div>
                <small>Unit terjual</small>
                <b><?= $persenTerjual ?>%</b>
                <em><?= $terjual ?> dari <?= $totalUnit ?> unit</em>
            </div>
        </div>
    </div>

</section>

<!-- ============ BENTO DASHBOARD ============ -->
<div class="bento" id="ringkasan">

<!-- ---- kartu KPI ---- -->
    <div class="stat-card span-3">
        <div class="card-body">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Unit Tersedia</div>
                    <div class="stat-value" data-countup="<?= $tersedia ?>">0</div>
                    <div class="stat-sub"><?= $persenTersedia ?>% dari <?= $totalUnit ?> unit</div>
                </div>
                <span class="stat-icon"><i class="bi bi-house-door"></i></span>
            </div>
            <div class="mini-bar"><span style="width:<?= $persenTersedia ?>%"></span></div>
        </div>
    </div>

    <div class="stat-card tone-warn span-3">
        <div class="card-body">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Unit Dibooking</div>
                    <div class="stat-value" data-countup="<?= $dibooking ?>">0</div>
                    <div class="stat-sub"><?= $persenBooking ?>% dari total unit</div>
                </div>
                <span class="stat-icon"><i class="bi bi-bookmark-check"></i></span>
            </div>
            <div class="mini-bar"><span style="width:<?= $persenBooking ?>%"></span></div>
        </div>
    </div>

    <div class="stat-card tone-info span-3">
        <div class="card-body">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Unit Terjual</div>
                    <div class="stat-value" data-countup="<?= $terjual ?>">0</div>
                    <div class="stat-sub"><?= $persenTerjual ?>% dari total unit</div>
                </div>
                <span class="stat-icon"><i class="bi bi-house-check"></i></span>
            </div>
            <div class="mini-bar"><span style="width:<?= $persenTerjual ?>%"></span></div>
        </div>
    </div>

    <div class="stat-card span-3">
        <div class="card-body">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Konsumen</div>
                    <div class="stat-value" data-countup="<?= $konsumen ?>">0</div>
                    <div class="stat-sub">terdaftar di database</div>
                </div>
                <span class="stat-icon"><i class="bi bi-people"></i></span>
            </div>
            <div class="mini-bar"><span style="width:<?= $konsumen ? 100 : 0 ?>%"></span></div>
        </div>
    </div>

    <!-- ---- grafik ---- -->
    <div class="card chart-card span-8">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><span class="card-kicker d-block">Tren</span>Pemasukan 12 Bulan Terakhir</span>
            <span class="badge bg-success">Bulan ini</span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="chartPemasukan"></canvas>
            </div>
        </div>
    </div>

    <div class="card chart-card span-4">
        <div class="card-header"><span class="card-kicker d-block">Komposisi</span>Status Unit</div>
        <div class="card-body">
            <div class="chart-container chart-sm">
                <canvas id="chartStatus"></canvas>
            </div>
        </div>
    </div>

    <!-- ---- capaian unit ---- -->
    <section class="card capaian span-4">
        <div class="card-header"><span class="card-kicker d-block">Capaian</span>Unit Terjual</div>
        <div class="capaian-body">
            <div class="wh-ring" style="--p: <?= $persenTerjual ?>">
                <div class="wh-ring-inner">
                    <strong><?= $persenTerjual ?>%</strong>
                    <span>Terjual</span>
                </div>
            </div>
            <ul class="capaian-list">
                <li>
                    <span class="cl-top"><i class="cl-dot" style="background:#10b981"></i>Tersedia <b><?= $tersedia ?></b></span>
                    <span class="cl-bar"><span style="width:<?= $persenTersedia ?>%;background:#10b981"></span></span>
                </li>
                <li>
                    <span class="cl-top"><i class="cl-dot" style="background:#f59e0b"></i>Dibooking <b><?= $dibooking ?></b></span>
                    <span class="cl-bar"><span style="width:<?= $persenBooking ?>%;background:#f59e0b"></span></span>
                </li>
                <li>
                    <span class="cl-top"><i class="cl-dot" style="background:#6366f1"></i>Terjual <b><?= $terjual ?></b></span>
                    <span class="cl-bar"><span style="width:<?= $persenTerjual ?>%;background:#6366f1"></span></span>
                </li>
            </ul>
        </div>
    </section>

    <div class="card span-8">
        <div class="card-header">
            <span><span class="card-kicker d-block">Aktivitas</span>Booking Terbaru</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Kode</th><th>Tanggal</th><th>Konsumen</th><th>Unit</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$terbaru): ?>
                        <tr class="empty-row"><td colspan="5"><i class="bi bi-inbox"></i> Belum ada booking. Mulai dari <a href="booking.php?aksi=tambah">Booking Baru</a>.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($terbaru as $b): ?>
                        <tr>
                            <td><a href="kwitansi.php?id=<?= $b['id_booking'] ?>"><?= e($b['kode_booking']) ?></a></td>
                            <td><?= tgl($b['tanggal']) ?></td>
                            <td><?= e($b['konsumen']) ?></td>
                            <td><?= e("{$b['blok']}-{$b['nomor']}") ?> <small class="text-muted"><?= e($b['nama_tipe']) ?></small></td>
                            <td><span class="badge bg-<?= $b['status'] === 'Lunas' ? 'success' : ($b['status'] === 'Batal' ? 'secondary' : 'warning') ?>"><?= $b['status'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /.bento -->


<script>
// Chart.js dimuat di footer, jadi tunggu halaman selesai dimuat dulu
window.addEventListener('load', function () {
    if (typeof Chart === 'undefined') return;

    var css = getComputedStyle(document.documentElement);
    var accent = css.getPropertyValue('--accent').trim() || '#d0243a';
    var accentBright = css.getPropertyValue('--accent-bright').trim() || '#ee3d52';
    var muted = css.getPropertyValue('--muted').trim() || '#667085';
    var line = css.getPropertyValue('--line').trim() || '#e8eaee';
    var surface = css.getPropertyValue('--surface').trim() || '#ffffff';
    var textCol = css.getPropertyValue('--text').trim() || '#1c2128';
    var emerald = css.getPropertyValue('--c-tersedia').trim() || '#10b981';
    var amber = css.getPropertyValue('--c-booking').trim() || '#f59e0b';
    var blue = css.getPropertyValue('--c-terjual').trim() || '#6366f1';

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = muted;

    var tooltipStyle = {
        backgroundColor: surface,
        titleColor: textCol,
        bodyColor: textCol,
        borderColor: line,
        borderWidth: 1,
        padding: 11,
        cornerRadius: 9,
        displayColors: false
    };

    var ctx = document.getElementById('chartPemasukan');
    if (ctx) {
        var g = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        g.addColorStop(0, 'rgba(208,36,58,0.28)');
        g.addColorStop(1, 'rgba(208,36,58,0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labelBulan) ?>,
                datasets: [{
                    label: 'Pemasukan',
                    data: <?= json_encode($nilaiBulan) ?>,
                    borderColor: accent,
                    backgroundColor: g,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: accentBright,
                    pointBorderColor: surface,
                    pointBorderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: {
                            label: function (c) {
                                return 'Rp ' + Number(c.parsed.y).toLocaleString('id-ID');
                            }
                        }
                    })
                },
                scales: {
                    x: { grid: { display: false }, border: { color: line }, ticks: { color: muted } },
                    y: {
                        beginAtZero: true,
                        grid: { color: line },
                        border: { display: false },
                        ticks: {
                            color: muted,
                            callback: function (v) {
                                if (v >= 1e9) return (v / 1e9).toLocaleString('id-ID') + ' M';
                                if (v >= 1e6) return (v / 1e6).toLocaleString('id-ID') + ' jt';
                                return v.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    var ctx2 = document.getElementById('chartStatus');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Tersedia', 'Booking', 'Terjual'],
                datasets: [{
                    data: [<?= (int) $tersedia ?>, <?= (int) $dibooking ?>, <?= (int) $terjual ?>],
                    backgroundColor: [emerald, amber, blue],
                    borderColor: 'transparent',
                    borderWidth: 0,
                    hoverOffset: 9,
                    spacing: 3,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: muted, usePointStyle: true, pointStyle: 'circle', padding: 16, boxWidth: 8 }
                    },
                    tooltip: Object.assign({}, tooltipStyle, { displayColors: false })
                }
            }
        });
    }
});
</script>
<!-- ============ POP-UP SAMBUTAN ============ -->
<?php if ($tampilSambutan): ?>
<div class="welcome-pop" id="welcomePop" role="dialog" aria-modal="true" aria-labelledby="wpJudul">
    <div class="wp-card">
        <div class="wp-art">
            <i class="bi bi-houses-fill"></i>
            <span class="wp-spark s1"></span><span class="wp-spark s2"></span><span class="wp-spark s3"></span>
        </div>
        <h4 id="wpJudul">Selamat datang, <?= e(explode(' ', trim($_SESSION['nama']))[0]) ?>!</h4>
        <p>Ini ringkasan Dory Hill hari ini.</p>
        <div class="wp-stats">
            <div><b><?= $tersedia ?></b><small>Tersedia</small></div>
            <div><b><?= $dibooking ?></b><small>Dibooking</small></div>
            <div><b><?= $terjual ?></b><small>Terjual</small></div>
        </div>
        <button type="button" class="wp-btn" data-tutup-pop>Mulai bekerja <i class="bi bi-arrow-right"></i></button>
    </div>
</div>
<?php endif; ?>

<!-- ============ NOTIFIKASI (TOAST) ============ -->
<div class="toast-stack" id="toastStack">
    <?php if ($perluDitagih > 0): ?>
        <a href="tagihan.php" class="toast-item warn">
            <i class="bi bi-bell-fill"></i>
            <span><b><?= $perluDitagih ?> tagihan perlu ditagih</b><small>Lebih dari 30 hari tanpa pembayaran</small></span>
            <button type="button" class="toast-x" aria-label="Tutup">&times;</button>
        </a>
    <?php endif; ?>
    <?php if ($tersedia > 0 && $tersedia <= 3): ?>
        <a href="pricelist.php" class="toast-item">
            <i class="bi bi-fire"></i>
            <span><b>Tinggal <?= $tersedia ?> unit tersedia</b><small>Lihat price list terbaru</small></span>
            <button type="button" class="toast-x" aria-label="Tutup">&times;</button>
        </a>
    <?php endif; ?>
</div>


<?php include "inc/footer.php"; ?>