<?php
require_once "inc/cek_login.php";
$judul = "Pembayaran";
$aksi  = $_GET['aksi'] ?? 'list';

// ---------- SIMPAN PEMBAYARAN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_booking = (int) $_POST['id_booking'];
    $tanggal    = $_POST['tanggal'];
    $jenis      = $_POST['jenis'];
    $jumlah     = angka($_POST['jumlah']);
    $keterangan = trim($_POST['keterangan']);

    $b = ambil_satu(
        "SELECT b.*, (SELECT COALESCE(SUM(jumlah),0) FROM pembayaran WHERE id_booking = b.id_booking) AS terbayar
         FROM booking b WHERE b.id_booking = ?", "i", [$id_booking]
    );
    $kembali = "pembayaran.php?aksi=tambah&booking=$id_booking";

    if (!$b || $b['status'] !== 'Aktif') {
        konfirmasi('gagal', 'Booking tidak ditemukan atau sudah tidak aktif.', 'pembayaran.php');
    }
    $sisa = $b['harga_jual'] - $b['terbayar'];
    if (!tanggal_boleh($tanggal)) {
        konfirmasi('gagal', 'Tanggal bayar tidak valid. Tanggal yang belum tiba tidak bisa dipilih.', $kembali);
    }
    if ($jumlah <= 0) {
        konfirmasi('gagal', 'Jumlah pembayaran harus lebih dari 0.', $kembali);
    }
    if ($jumlah > $sisa) {
        konfirmasi('gagal', 'Jumlah pembayaran melebihi sisa tagihan (' . rupiah($sisa) . ').', $kembali);
    }

    mysqli_begin_transaction($koneksi);
    query("INSERT INTO pembayaran (id_booking, tanggal, jenis, jumlah, keterangan) VALUES (?,?,?,?,?)",
          "issis", [$id_booking, $tanggal, $jenis, $jumlah, $keterangan]);

    $pesan = "Pembayaran " . rupiah($jumlah) . " untuk {$b['kode_booking']} berhasil dicatat.";

    // Kalau sudah lunas: booking = Lunas, unit = Terjual
    $lunas = ($jumlah == $sisa);
    if ($lunas) {
        query("UPDATE booking SET status = 'Lunas' WHERE id_booking = ?", "i", [$id_booking]);
        query("UPDATE unit SET status = 'Terjual' WHERE id_unit = ?", "i", [$b['id_unit']]);
    }
    mysqli_commit($koneksi);

    if ($lunas) {
        // Siapkan data untuk pop-up perayaan di halaman kwitansi
        $info = ambil_satu(
            "SELECT k.nama, u.blok, u.nomor, t.nama_tipe
             FROM booking b
             JOIN konsumen k   ON b.id_konsumen = k.id_konsumen
             JOIN unit u       ON b.id_unit = u.id_unit
             JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
             WHERE b.id_booking = ?", "i", [$id_booking]
        );
        $_SESSION['perayaan'] = [
            'id_booking' => $id_booking,
            'konsumen'   => $info['nama'],
            'unit'       => "Blok {$info['blok']}-{$info['nomor']}",
            'tipe'       => $info['nama_tipe'],
            'harga'      => $b['harga_jual'],
        ];
        header("Location: kwitansi.php?id=$id_booking");
        exit;
    }

    konfirmasi('berhasil', $pesan, "kwitansi.php?id=$id_booking");
}

// Booking aktif untuk dropdown
$bookingAktif = ambil_semua(
    "SELECT b.id_booking, b.kode_booking, b.harga_jual, k.nama, u.blok, u.nomor,
            (SELECT COALESCE(SUM(jumlah),0) FROM pembayaran WHERE id_booking = b.id_booking) AS terbayar
     FROM booking b
     JOIN konsumen k ON b.id_konsumen = k.id_konsumen
     JOIN unit u     ON b.id_unit = u.id_unit
     WHERE b.status = 'Aktif' ORDER BY b.kode_booking"
);
$dipilih = (int) ($_GET['booking'] ?? 0);

$daftar = ambil_semua(
    "SELECT p.*, b.kode_booking, k.nama AS konsumen
     FROM pembayaran p
     JOIN booking b  ON p.id_booking = b.id_booking
     JOIN konsumen k ON b.id_konsumen = k.id_konsumen
     ORDER BY p.tanggal DESC, p.id_pembayaran DESC"
);
$semuaBayar = $daftar;
$f_jenis = $_GET['jenis'] ?? '';
$jenisList = ['Booking Fee', 'DP', 'Angsuran', 'Pencairan KPR', 'Pelunasan'];
if (in_array($f_jenis, $jenisList)) {
    $daftar = array_values(array_filter($daftar, function ($p) use ($f_jenis) { return $p['jenis'] === $f_jenis; }));
} else {
    $f_jenis = '';
}

// ---------- RINGKASAN ----------
$blnIni = date('Y-m');
$masukBulanIni = 0; $trxBulanIni = 0; $totalMasuk = 0; $perJenis = [];
foreach ($semuaBayar as $p) {
    $totalMasuk += $p['jumlah'];
    $perJenis[$p['jenis']] = ($perJenis[$p['jenis']] ?? 0) + $p['jumlah'];
    if (substr($p['tanggal'], 0, 7) === $blnIni) { $masukBulanIni += $p['jumlah']; $trxBulanIni++; }
}
arsort($perJenis);
$sisaTotal = array_sum(array_map(function ($b) { return $b['harga_jual'] - $b['terbayar']; }, $bookingAktif));
$belumLunas = $bookingAktif;
usort($belumLunas, function ($a, $b) { return ($b['harga_jual'] - $b['terbayar']) <=> ($a['harga_jual'] - $a['terbayar']); });
$juta = function ($n) { return 'Rp ' . number_format($n / 1e6, 0, ',', '.') . ' jt'; };
$linkJ = function ($j) { return 'pembayaran.php' . ($j !== '' ? '?jenis=' . urlencode($j) : ''); };
$formTerbuka = ($aksi === 'tambah');

$kicker = "Transaksi";
include "inc/header.php";
?>
<div class="hal-kepala">
    <div class="page-heading mb-0">
        <span class="kicker">Transaksi</span>
        <h3>Pembayaran</h3>
        <p>DP, angsuran, pencairan KPR, dan pelunasan. Booking otomatis lunas saat tagihan habis.</p>
    </div>
    <div class="hal-aksi">
        <a href="tagihan.php" class="btn btn-ghost"><i class="bi bi-bell"></i> Tagihan</a>
        <a href="pembayaran.php?aksi=tambah" class="btn btn-accent" data-buka-form><i class="bi bi-plus-lg"></i> Input Pembayaran</a>
    </div>
</div>

<?php
// grafik uang masuk per bulan (tahun berjalan)
$tahun = date('Y');
$perBulan = array_fill(1, 12, 0);
$totalTahun = 0;
foreach ($semuaBayar as $p) {
    if (substr($p['tanggal'], 0, 4) === $tahun) {
        $perBulan[(int) substr($p['tanggal'], 5, 2)] += $p['jumlah'];
        $totalTahun += $p['jumlah'];
    }
}
$maxBulan = max(1, max($perBulan));
$singkat = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// kelompokkan riwayat per tanggal
$perTanggal = [];
foreach ($daftar as $p) $perTanggal[$p['tanggal']][] = $p;
$hariIndo  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function tanggal_panjang($t) {
    global $hariIndo, $bulanIndo;
    $x = strtotime($t);
    return $hariIndo[date('w', $x)] . ', ' . date('j', $x) . ' ' . $bulanIndo[date('n', $x)] . ' ' . date('Y', $x);
}
// ikon & warna per jenis pembayaran
$gayaJenis = [
    'Booking Fee'   => ['bi-bookmark-check', 'kuning'],
    'DP'            => ['bi-cash-stack', 'hijau'],
    'Angsuran'      => ['bi-calendar2-check', 'ungu'],
    'Pencairan KPR' => ['bi-bank', 'biru'],
    'Pelunasan'     => ['bi-patch-check', 'merah'],
];
?>

<!-- ============ RINGKASAN ============ -->
<div class="mutasi-atas">
    <div class="mutasi-total">
        <small>Total uang masuk <?= $tahun ?></small>
        <b><?= rupiah($totalTahun) ?></b>
        <div class="mt-grafik">
            <?php for ($b = 1; $b <= 12; $b++): ?>
                <span class="<?= $b == date('n') ? 'kini' : '' ?>" title="<?= $singkat[$b] ?>: <?= rupiah($perBulan[$b]) ?>">
                    <i style="height: <?= max(4, $perBulan[$b] / $maxBulan * 100) ?>%"></i>
                    <em><?= $singkat[$b] ?></em>
                </span>
            <?php endfor; ?>
        </div>
    </div>
    <div class="mutasi-kecil">
        <small>Bulan ini</small>
        <b><?= $juta($masukBulanIni) ?></b>
        <span><?= $trxBulanIni ?> transaksi</span>
    </div>
    <a href="tagihan.php" class="mutasi-kecil merah">
        <small>Sisa tagihan</small>
        <b><?= $juta($sisaTotal) ?></b>
        <span><?= count($bookingAktif) ?> booking belum lunas <i class="bi bi-arrow-right"></i></span>
    </a>
</div>

<!-- ============ MUTASI PER TANGGAL ============ -->
<div class="mutasi-saring">
    <?php chip_saring(array_merge(['' => 'Semua'], array_combine($jenisList, ['Booking Fee', 'DP', 'Angsuran', 'KPR', 'Pelunasan'])), $f_jenis, $linkJ); ?>
    <small class="text-muted"><?= count($daftar) ?> transaksi · <?= rupiah(array_sum(array_column($daftar, 'jumlah'))) ?></small>
</div>

<?php if (!$perTanggal): ?>
    <div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada pembayaran.</div></div>
<?php endif; ?>

<?php foreach ($perTanggal as $tgl => $isi): ?>
    <div class="mutasi-tgl">
        <span><?= tanggal_panjang($tgl) ?></span>
        <small>+ <?= rupiah(array_sum(array_column($isi, 'jumlah'))) ?></small>
    </div>
    <div class="card mutasi-grup">
        <?php foreach ($isi as $p):
            [$ikon, $w] = $gayaJenis[$p['jenis']] ?? ['bi-cash', 'hijau']; ?>
            <a href="kwitansi.php?id=<?= $p['id_booking'] ?>" class="mutasi-baris" data-judul="Kwitansi">
                <span class="mb-ikon w-<?= $w ?>"><i class="bi <?= $ikon ?>"></i></span>
                <span class="mb-isi">
                    <b><?= e($p['jenis']) ?> · <?= e($p['konsumen']) ?></b>
                    <small><?= e($p['kode_booking']) ?><?= $p['keterangan'] ? ' · ' . e($p['keterangan']) : '' ?></small>
                </span>
                <span class="mb-jumlah">+ <?= rupiah($p['jumlah']) ?></span>
                <i class="bi bi-chevron-right mb-panah"></i>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php form_pop_awal('Input Pembayaran', 'bi-cash-coin', $formTerbuka); ?>
    <?php if (!$bookingAktif): ?>
        <div class="alert alert-warning mb-0">Tidak ada booking aktif. <a href="booking.php?aksi=tambah">Buat booking dulu</a>.</div>
    <?php else: ?>
    <form method="post" action="pembayaran.php">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Booking</label>
                <select name="id_booking" id="id_booking" class="form-select" required>
                    <option value="" data-sisa="0">-- pilih booking --</option>
                    <?php foreach ($bookingAktif as $b): $sisa = $b['harga_jual'] - $b['terbayar']; ?>
                        <option value="<?= $b['id_booking'] ?>" data-sisa="<?= $sisa ?>" <?= $dipilih == $b['id_booking'] ? 'selected' : '' ?>>
                            <?= e("{$b['kode_booking']} · {$b['nama']} · {$b['blok']}-{$b['nomor']}") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <div class="harga-tampil merah"><small>Sisa tagihan</small><b id="sisa">Rp 0</b></div>
            </div>
            <div class="col-6">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="tanggal" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" data-bawaan="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Jenis</label>
                <select name="jenis" class="form-select" required data-bawaan="DP">
                    <option>DP</option>
                    <option>Angsuran</option>
                    <option>Pencairan KPR</option>
                    <option>Pelunasan</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label d-flex justify-content-between">Jumlah (Rp) <a href="#" id="isiLunas">isi sisa tagihan</a></label>
                <input type="text" inputmode="numeric" autocomplete="off" name="jumlah" class="form-control input-uang" placeholder="0" required>
            </div>
            <div class="col-12">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" class="form-control" placeholder="contoh: Transfer BTN">
            </div>
        </div>
        <?php form_pop_tombol('Simpan Pembayaran'); ?>
    </form>
    <script>
        // tampilkan sisa tagihan + tombol cepat "isi sisa tagihan" (untuk pelunasan)
        const pilih = document.getElementById('id_booking');
        function tampilSisa() {
            const sisa = pilih.selectedOptions[0].dataset.sisa || 0;
            document.getElementById('sisa').textContent = 'Rp ' + Number(sisa).toLocaleString('id-ID');
        }
        pilih.addEventListener('change', tampilSisa);
        tampilSisa();
        document.getElementById('isiLunas').addEventListener('click', function (e) {
            e.preventDefault();
            const sisa = pilih.selectedOptions[0].dataset.sisa || '';
            if (sisa > 0) pilih.form.jumlah.value = sisa;
        });
    </script>
    <?php endif; ?>
<?php form_pop_akhir(); ?>

<?php include "inc/footer.php"; ?>