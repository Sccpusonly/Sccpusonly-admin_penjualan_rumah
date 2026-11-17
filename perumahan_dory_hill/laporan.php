<?php
require_once "inc/cek_login.php";
$judul = "Laporan";

$bulan = (int) ($_GET['bulan'] ?? date('n'));
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
$namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
              'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Booking pada bulan terpilih
$booking = ambil_semua(
    "SELECT b.*, k.nama AS konsumen, m.nama AS marketing, u.blok, u.nomor, t.nama_tipe,
            (SELECT k2.nama FROM booking_pemohon bp2 JOIN konsumen k2 ON bp2.id_konsumen = k2.id_konsumen
             WHERE bp2.id_booking = b.id_booking AND bp2.peran = 'Pasangan Pemohon') AS pasangan
     FROM booking b
     JOIN konsumen k   ON b.id_konsumen = k.id_konsumen
     JOIN marketing m  ON b.id_marketing = m.id_marketing
     JOIN unit u       ON b.id_unit = u.id_unit
     JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     WHERE MONTH(b.tanggal) = ? AND YEAR(b.tanggal) = ?
     ORDER BY b.tanggal", "ii", [$bulan, $tahun]
);

// Pemasukan pada bulan terpilih, dikelompokkan per jenis
$pemasukan = ambil_semua(
    "SELECT jenis, COUNT(*) AS jml, SUM(jumlah) AS total FROM pembayaran
     WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY jenis", "ii", [$bulan, $tahun]
);
$totalPemasukan = array_sum(array_column($pemasukan, 'total'));
$totalBookingHarga = array_sum(array_column($booking, 'harga_jual'));
$jmlAktif = count(array_filter($booking, fn($b) => $b['status'] === 'Aktif'));
$jmlLunas = count(array_filter($booking, fn($b) => $b['status'] === 'Lunas'));

$kicker = "Analitik";
include "inc/header.php";
?>
<div class="page-heading fade-in">
    <span class="kicker">Analitik</span>
    <h3>Laporan Penjualan</h3>
    <p>Rekap booking dan pemasukan periode <?= $namaBulan[$bulan] ?> <?= $tahun ?> — siap cetak maupun diekspor ke CSV.</p>
    <div class="heading-rule"></div>
</div>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4 no-print fade-in-1">
    <div class="legend">
        <span class="legend-item"><span class="swatch booking"></span> <?= count($booking) ?> booking masuk</span>
        <span class="legend-item"><span class="swatch terjual"></span> <?= $jmlLunas ?> lunas</span>
        <span class="legend-item"><span class="swatch tersedia"></span> <?= $jmlAktif ?> aktif</span>
    </div>
    <form class="d-flex gap-2" method="get">
        <select name="bulan" class="form-select form-select-sm">
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>><?= $namaBulan[$i] ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" name="tahun" class="form-control form-control-sm" style="width:90px" value="<?= $tahun ?>">
        <button class="btn btn-sm btn-ghost">Tampilkan</button>
        <button type="button" onclick="window.print()" class="btn btn-sm btn-accent"><i class="bi bi-printer"></i> Cetak</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card fade-in fade-in-1">
            <div class="card-body">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Total Pemasukan</div>
                        <div class="stat-value money" data-countup="<?= $totalPemasukan ?>" data-prefix="Rp " style="font-size:1.55rem">Rp 0</div>
                        <div class="stat-sub">periode <?= $namaBulan[$bulan] ?> <?= $tahun ?></div>
                    </div>
                    <span class="stat-icon"><i class="bi bi-cash-stack"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card fade-in fade-in-2">
            <div class="card-body">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Nilai Booking</div>
                        <div class="stat-value money" data-countup="<?= $totalBookingHarga ?>" data-prefix="Rp " style="font-size:1.55rem">Rp 0</div>
                        <div class="stat-sub"><?= count($booking) ?> transaksi periode ini</div>
                    </div>
                    <span class="stat-icon"><i class="bi bi-journal-check"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card tone-info fade-in fade-in-3">
            <div class="card-body">
                <div class="stat-top">
                    <div>
                        <div class="stat-label">Booking Lunas</div>
                        <div class="stat-value" data-countup="<?= $jmlLunas ?>">0</div>
                        <div class="stat-sub">dari <?= count($booking) ?> booking bulan ini</div>
                    </div>
                    <span class="stat-icon"><i class="bi bi-patch-check"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 fade-in fade-in-2">
    <div class="card-header"><span class="card-kicker d-block">Periode</span>Booking Masuk (<?= count($booking) ?>)</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-bordered mb-0" data-tools="export">
            <thead><tr><th>No</th><th>Tanggal</th><th>Kode</th><th>Konsumen</th><th>Unit</th><th>Marketing</th><th>Harga</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$booking): ?>
                <tr class="empty-row"><td colspan="8"><i class="bi bi-inbox"></i> Tidak ada booking pada periode ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($booking as $i => $b): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= tgl($b['tanggal']) ?></td>
                    <td><?= e($b['kode_booking']) ?></td>
                    <td><?= e($b['konsumen']) ?><?= $b['pasangan'] ? ' & ' . e($b['pasangan']) : '' ?></td>
                    <td><?= e("{$b['blok']}-{$b['nomor']} ({$b['nama_tipe']})") ?></td>
                    <td><?= e($b['marketing']) ?></td>
                    <td><?= rupiah($b['harga_jual']) ?></td>
                    <td><?= $b['status'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card fade-in fade-in-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-kicker d-block">Rekap</span>Pemasukan per Jenis</span>
        <span class="badge bg-success"><?= rupiah($totalPemasukan) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0" data-tools="export">
            <thead><tr><th>Jenis Pembayaran</th><th>Jumlah Transaksi</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php if (!$pemasukan): ?>
                <tr class="empty-row"><td colspan="3"><i class="bi bi-inbox"></i> Belum ada pemasukan pada periode ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($pemasukan as $p): ?>
                <tr><td><?= $p['jenis'] ?></td><td><?= $p['jml'] ?></td><td class="text-end money"><?= rupiah($p['total']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="2" class="text-end">Total Pemasukan</th><th class="text-end money"><?= rupiah($totalPemasukan) ?></th></tr></tfoot>
        </table>
    </div>
</div>
<?php include "inc/footer.php"; ?>