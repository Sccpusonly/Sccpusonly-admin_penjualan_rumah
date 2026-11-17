<?php
require_once "inc/cek_login.php";
$judul = "Kwitansi";

// ================= DAFTAR KWITANSI (kalau belum memilih booking) =================
if (empty($_GET['id'])) {
    $filter = $_GET['status'] ?? '';
    $sql = "SELECT b.id_booking, b.kode_booking, b.tanggal, b.cara_bayar, b.harga_jual, b.status,
                   k.nama AS konsumen, u.blok, u.nomor, t.nama_tipe,
                   (SELECT COALESCE(SUM(jumlah),0) FROM pembayaran p WHERE p.id_booking = b.id_booking) AS terbayar,
                   (SELECT COUNT(*) FROM pembayaran p WHERE p.id_booking = b.id_booking) AS jml_bayar
            FROM booking b
            JOIN konsumen k   ON b.id_konsumen = k.id_konsumen
            JOIN unit u       ON b.id_unit = u.id_unit
            JOIN tipe_rumah t ON u.id_tipe = t.id_tipe";
    $daftar = in_array($filter, ['Aktif', 'Lunas', 'Batal'])
        ? ambil_semua($sql . " WHERE b.status = ? ORDER BY b.id_booking DESC", "s", [$filter])
        : ambil_semua($sql . " ORDER BY b.id_booking DESC");

    $hitung = ['' => 0, 'Aktif' => 0, 'Lunas' => 0, 'Batal' => 0];
    foreach (ambil_semua("SELECT status, COUNT(*) AS n FROM booking GROUP BY status") as $r) {
        $hitung[$r['status']] = (int) $r['n'];
        $hitung[''] += (int) $r['n'];
    }

    $kicker = "Transaksi";
    include "inc/header.php";
    ?>
    <div class="page-heading">
        <span class="kicker">Transaksi</span>
        <h3>Kwitansi</h3>
        <p>Pilih booking untuk melihat, mencetak, atau mengunduh bukti pembayarannya.</p>
        <div class="heading-rule"></div>
    </div>

    <!-- tab filter status -->
    <div class="kw-tab mb-3">
        <?php foreach (['' => 'Semua', 'Aktif' => 'Belum Lunas', 'Lunas' => 'Lunas', 'Batal' => 'Batal'] as $st => $label): ?>
            <a href="kwitansi.php<?= $st ? '?status=' . $st : '' ?>" class="<?= $filter === $st ? 'aktif' : '' ?>">
                <?= $label ?> <span><?= $hitung[$st] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-kicker d-block">Daftar</span>Kwitansi Booking</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" data-tools>
                    <thead>
                        <tr><th>Kode</th><th>Tanggal</th><th>Konsumen</th><th>Unit</th><th>Pembayaran</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$daftar): ?>
                        <tr class="empty-row"><td colspan="7"><i class="bi bi-receipt"></i> Belum ada kwitansi.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $d):
                        $pr = $d['harga_jual'] > 0 ? min(100, round($d['terbayar'] / $d['harga_jual'] * 100)) : 0;
                        $warna = ['Aktif' => 'warning', 'Lunas' => 'success', 'Batal' => 'secondary'][$d['status']]; ?>
                        <tr>
                            <td><a href="kwitansi.php?id=<?= $d['id_booking'] ?>" class="fw-semibold"><?= e($d['kode_booking']) ?></a></td>
                            <td><?= tgl($d['tanggal']) ?></td>
                            <td><?= e($d['konsumen']) ?><br><small class="text-muted"><?= e($d['cara_bayar']) ?></small></td>
                            <td><?= e("{$d['blok']}-{$d['nomor']}") ?><br><small class="text-muted"><?= e($d['nama_tipe']) ?></small></td>
                            <td style="min-width:150px">
                                <div class="pb-bar mt-0"><span style="width:<?= $pr ?>%"></span></div>
                                <small class="text-muted"><?= $pr ?>% &middot; <?= $d['jml_bayar'] ?>x bayar</small>
                            </td>
                            <td><span class="badge bg-<?= $warna ?>"><?= $d['status'] ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="kwitansi.php?id=<?= $d['id_booking'] ?>" class="btn btn-sm btn-ghost" title="Lihat kwitansi"><i class="bi bi-eye"></i> Lihat</a>
                                <a href="kwitansi.php?id=<?= $d['id_booking'] ?>&cetak=1" class="btn btn-sm btn-danger" title="Langsung cetak"><i class="bi bi-printer"></i> Cetak</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    include "inc/footer.php";
    exit;
}

$b = ambil_satu(
    "SELECT b.*, k.nama AS konsumen, k.nik, k.no_hp, k.alamat, m.nama AS marketing,
            u.blok, u.nomor, t.nama_tipe, t.luas_bangunan, t.luas_tanah
     FROM booking b
     JOIN konsumen k   ON b.id_konsumen = k.id_konsumen
     JOIN marketing m  ON b.id_marketing = m.id_marketing
     JOIN unit u       ON b.id_unit = u.id_unit
     JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     WHERE b.id_booking = ?", "i", [(int) ($_GET['id'] ?? 0)]
);
$pasangan = $b ? ambil_satu(
    "SELECT k.nama, k.nik, k.no_hp FROM booking_pemohon bp
     JOIN konsumen k ON bp.id_konsumen = k.id_konsumen
     WHERE bp.id_booking = ? AND bp.peran = 'Pasangan Pemohon'", "i", [$b['id_booking']]
) : null;
if (!$b) {
    konfirmasi('gagal', 'Data booking tidak ditemukan.', 'kwitansi.php');
}

$bayar = ambil_semua("SELECT * FROM pembayaran WHERE id_booking = ? ORDER BY tanggal, id_pembayaran", "i", [$b['id_booking']]);
$total = array_sum(array_column($bayar, 'jumlah'));
$sisa  = $b['harga_jual'] - $total;
$persen = $b['harga_jual'] > 0 ? min(100, round($total / $b['harga_jual'] * 100)) : 0;

// ---------- Tahapan pembayaran (untuk garis progres) ----------
// [judul, ikon, jenis pembayaran yang dihitung pada tahap ini]
if ($b['cara_bayar'] === 'KPR') {
    $tahap = [
        ['Booking', 'bi-bookmark-check', ['Booking Fee']],
        ['DP', 'bi-cash-stack', ['DP']],
        ['Pencairan KPR', 'bi-bank', ['Pencairan KPR', 'Angsuran', 'Pelunasan']],
    ];
} elseif ($b['cara_bayar'] === 'Cash Bertahap') {
    $tahap = [
        ['Booking', 'bi-bookmark-check', ['Booking Fee']],
        ['DP', 'bi-cash-stack', ['DP']],
        ['Angsuran', 'bi-calendar2-check', ['Angsuran', 'Pencairan KPR', 'Pelunasan']],
    ];
} else { // Cash Keras
    $tahap = [
        ['Booking', 'bi-bookmark-check', ['Booking Fee']],
        ['Pelunasan', 'bi-cash-stack', ['DP', 'Angsuran', 'Pencairan KPR', 'Pelunasan']],
    ];
}
$langkah = [];
foreach ($tahap as [$judulTahap, $ikon, $jenis]) {
    $cocok = array_filter($bayar, function ($p) use ($jenis) { return in_array($p['jenis'], $jenis); });
    $langkah[] = [
        'judul'   => $judulTahap,
        'ikon'    => $ikon,
        'selesai' => count($cocok) > 0,
        'jumlah'  => array_sum(array_column($cocok, 'jumlah')),
        'tanggal' => $cocok ? max(array_column($cocok, 'tanggal')) : null,
    ];
}
$langkah[] = [
    'judul' => 'Lunas', 'ikon' => 'bi-trophy-fill', 'final' => true,
    'selesai' => $b['status'] === 'Lunas', 'jumlah' => null,
    'tanggal' => $b['status'] === 'Lunas' && $bayar ? max(array_column($bayar, 'tanggal')) : null,
];
// tahap berikutnya yang sedang ditunggu diberi tanda berkedip
$sekarang = null;
if ($b['status'] === 'Aktif') {
    foreach ($langkah as $i => $l) { if (!$l['selesai']) { $sekarang = $i; break; } }
}

// ---------- Pop-up perayaan (hanya sekali, tepat setelah lunas) ----------
$perayaan = null;
if (!empty($_SESSION['perayaan']) && $_SESSION['perayaan']['id_booking'] == $b['id_booking']) {
    $perayaan = $_SESSION['perayaan'];
}
unset($_SESSION['perayaan']);

$kicker = "Transaksi";
include "inc/header.php";
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4 no-print fade-in">
    <div class="page-heading mb-0">
        <span class="kicker">Transaksi</span>
        <h3>Bukti Booking &amp; Pembayaran</h3>
        <p>Rincian transaksi resmi — siap dicetak atau disimpan sebagai CSV.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="kwitansi.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left"></i> Daftar Kwitansi</a>
        <button type="button" class="btn btn-sm btn-accent" data-csv-bayar><i class="bi bi-file-earmark-spreadsheet"></i> CSV</button>
        <button onclick="window.print()" class="btn btn-success btn-sm"><i class="bi bi-printer"></i> Cetak</button>
    </div>
</div>

<div class="card p-4 fade-in-1">
    <div class="d-flex flex-wrap justify-content-between gap-3 border-bottom pb-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-3">
                <div class="brand-icon" style="width:46px;height:46px;font-size:1.3rem"><i class="bi bi-buildings-fill"></i></div>
                <div>
                    <h4 class="fw-bold mb-0" style="font-family:var(--font-display)">SI Perumahan</h4>
                    <small class="text-muted">Bukti Pemesanan Unit Rumah &middot; Dory Hill</small>
                </div>
            </div>
        </div>
        <div class="text-end">
            <div class="card-kicker">Kode Booking</div>
            <div class="fw-bold fs-5 money"><?= e($b['kode_booking']) ?></div>
            <small class="text-muted">Tanggal: <?= tgl($b['tanggal']) ?></small><br>
            <span class="badge bg-<?= $b['status'] === 'Lunas' ? 'success' : ($b['status'] === 'Batal' ? 'secondary' : 'warning') ?>"><?= $b['status'] ?></span>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <h6 class="card-kicker mb-3">Data Pemohon</h6>
            <table class="table table-sm table-borderless">
                <tr><td width="110" class="text-muted">Nama</td><td>: <?= e($b['konsumen']) ?></td></tr>
                <tr><td class="text-muted">NIK</td><td>: <?= e($b['nik']) ?></td></tr>
                <tr><td class="text-muted">No. HP</td><td>: <?= e($b['no_hp']) ?></td></tr>
                <tr><td class="text-muted">Alamat</td><td>: <?= e($b['alamat']) ?></td></tr>
                <?php if ($pasangan): ?>
                <tr><td class="text-muted">Pasangan Pemohon</td><td>: <?= e($pasangan['nama']) ?> (<?= e($pasangan['nik']) ?>)</td></tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="card-kicker mb-3">Data Unit</h6>
            <table class="table table-sm table-borderless">
                <tr><td width="110" class="text-muted">Unit</td><td>: Blok <?= e("{$b['blok']}-{$b['nomor']}") ?></td></tr>
                <tr><td class="text-muted">Tipe</td><td>: <?= e($b['nama_tipe']) ?> (LB <?= $b['luas_bangunan'] ?> / LT <?= $b['luas_tanah'] ?> m²)</td></tr>
                <tr><td class="text-muted">Cara Bayar</td><td>: <?= $b['cara_bayar'] ?></td></tr>
                <tr><td class="text-muted">Marketing</td><td>: <?= e($b['marketing']) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- ---------- Garis progres pembayaran ---------- -->
    <h6 class="card-kicker mb-3">Progres Pembayaran</h6>
    <div class="progres-bayar <?= $b['status'] === 'Batal' ? 'is-batal' : '' ?>">
        <?php foreach ($langkah as $i => $l):
            $kelas = $l['selesai'] ? 'selesai' : '';
            if (!empty($l['final'])) $kelas .= ' final';
            if ($i === $sekarang) $kelas .= ' sekarang';
        ?>
            <div class="pb-langkah <?= $kelas ?>" style="--i: <?= $i ?>">
                <div class="pb-titik"><i class="bi <?= $l['selesai'] ? (!empty($l['final']) ? 'bi-trophy-fill' : 'bi-check-lg') : $l['ikon'] ?>"></i></div>
                <b><?= $l['judul'] ?></b>
                <small>
                    <?php if ($l['selesai'] && $l['tanggal']): ?><?= tgl($l['tanggal']) ?><br><?php endif; ?>
                    <?php if ($l['jumlah']): ?><?= rupiah($l['jumlah']) ?>
                    <?php elseif (!empty($l['final']) && $l['selesai']): ?>Unit terjual
                    <?php elseif ($i === $sekarang): ?>Menunggu
                    <?php else: ?>&nbsp;<?php endif; ?>
                </small>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="pb-total mb-4">
        <div class="d-flex justify-content-between small">
            <span class="text-muted"><?= $b['status'] === 'Batal' ? 'Booking dibatalkan' : 'Total dibayar' ?></span>
            <b><?= rupiah($total) ?> <span class="text-muted fw-normal">/ <?= rupiah($b['harga_jual']) ?> (<?= $persen ?>%)</span></b>
        </div>
        <div class="pb-bar"><span style="width: <?= $persen ?>%"></span></div>
    </div>

    <h6 class="card-kicker mb-3">Riwayat Pembayaran</h6>
    <table class="table table-bordered" id="tabelBayar">
        <thead><tr><th width="50">No</th><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="text-end">Jumlah</th></tr></thead>
        <tbody>
        <?php if (!$bayar): ?>
            <tr class="empty-row"><td colspan="5">Belum ada pembayaran tercatat.</td></tr>
        <?php endif; ?>
        <?php foreach ($bayar as $i => $p): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= tgl($p['tanggal']) ?></td>
                <td><?= $p['jenis'] ?></td>
                <td><?= e($p['keterangan']) ?></td>
                <td class="text-end money"><?= rupiah($p['jumlah']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="4" class="text-end">Harga Unit</th><th class="text-end"><?= rupiah($b['harga_jual']) ?></th></tr>
            <tr><th colspan="4" class="text-end">Total Dibayar</th><th class="text-end money"><?= rupiah($total) ?></th></tr>
            <tr><th colspan="4" class="text-end">Sisa Tagihan</th><th class="text-end text-danger"><?= rupiah($sisa) ?></th></tr>
        </tfoot>
    </table>

    <div class="row mt-4 text-center">
        <?php $kol = $pasangan ? 4 : 6; ?>
        <div class="col-<?= $kol ?>">Pemohon<br><br><br><br>( <?= e($b['konsumen']) ?> )</div>
        <?php if ($pasangan): ?>
        <div class="col-<?= $kol ?>">Pasangan Pemohon<br><br><br><br>( <?= e($pasangan['nama']) ?> )</div>
        <?php endif; ?>
        <div class="col-<?= $kol ?>">Marketing<br><br><br><br>( <?= e($b['marketing']) ?> )</div>
    </div>
</div>

<script>
// Ekspor riwayat pembayaran ke CSV
document.querySelector('[data-csv-bayar]').addEventListener('click', function () {
    var table = document.getElementById('tabelBayar');
    var head = ['No', 'Tanggal', 'Jenis', 'Keterangan', 'Jumlah'];
    var rows = [];
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.classList.contains('empty-row')) return;
        rows.push([].map.call(tr.cells, function (td) { return td.innerText.replace(/\s+/g, ' ').trim(); }));
    });
    if (!rows.length) { alert('Belum ada pembayaran untuk diekspor.'); return; }
    var csv = [head].concat(rows).map(function (l) {
        return l.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(';');
    }).join('\r\n');
    var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'pembayaran-<?= e($b['kode_booking']) ?>.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
});
</script>
<?php if (!empty($_GET['cetak']) && !$perayaan): ?>
<script>
    // dibuka dari tombol "Cetak" di daftar kwitansi -> langsung tampilkan dialog cetak
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 600); });
</script>
<?php endif; ?>
<?php if ($perayaan): ?>
<!-- ============ POP-UP PERAYAAN LUNAS ============ -->
<div class="perayaan-pop" id="perayaanPop" role="dialog" aria-modal="true" aria-labelledby="pyJudul">
    <canvas class="konfeti-kanvas" id="konfeti" aria-hidden="true"></canvas>
    <div class="py-card">
        <div class="py-sinar"></div>
        <div class="py-piala"><i class="bi bi-trophy-fill"></i></div>
        <h3 id="pyJudul">Selamat! Unit Terjual 🎉</h3>
        <div class="py-stempel">SOLD</div>
        <p>
            Pembayaran <b><?= e($perayaan['konsumen']) ?></b> untuk unit
            <b><?= e($perayaan['unit']) ?> (<?= e($perayaan['tipe']) ?>)</b> sudah lunas
            sebesar <b><?= rupiah($perayaan['harga']) ?></b>.
            Status unit otomatis berubah menjadi <b>Terjual</b>.
        </p>
        <div class="py-aksi">
            <button type="button" class="py-cetak" data-cetak-perayaan><i class="bi bi-printer"></i> Cetak Kwitansi</button>
            <button type="button" class="wp-btn" data-tutup-perayaan>Lanjutkan <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include "inc/footer.php"; ?>