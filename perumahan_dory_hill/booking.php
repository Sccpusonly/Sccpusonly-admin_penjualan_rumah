<?php
require_once "inc/cek_login.php";
$judul = "Booking Unit";
$aksi  = $_GET['aksi'] ?? 'list';

// ---------- SIMPAN BOOKING BARU ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal      = $_POST['tanggal'];
    $id_konsumen  = (int) $_POST['id_konsumen'];
    $id_unit      = (int) $_POST['id_unit'];
    $id_marketing = (int) $_POST['id_marketing'];
    $cara_bayar   = $_POST['cara_bayar'];
    $booking_fee  = angka($_POST['booking_fee']);
    $id_pasangan  = (int) ($_POST['id_pasangan'] ?? 0);   // pasangan pemohon (kosong = diajukan sendiri)

    // Transaksi database: semua langkah berhasil, atau semua dibatalkan
    mysqli_begin_transaction($koneksi);
    try {
        // Kunci baris unit supaya tidak dibooking 2 orang bersamaan
        $unit = ambil_satu(
            "SELECT u.*, t.harga FROM unit u JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
             WHERE u.id_unit = ? FOR UPDATE", "i", [$id_unit]
        );

        if (!tanggal_boleh($tanggal)) {
            throw new Exception("Tanggal booking tidak valid. Tanggal yang belum tiba tidak bisa dipilih.");
        }
        if (!$unit) {
            throw new Exception("Unit tidak ditemukan.");
        }
        if ($unit['status'] !== 'Tersedia') {
            throw new Exception("Unit Blok {$unit['blok']}-{$unit['nomor']} sudah {$unit['status']}. Silakan pilih unit lain.");
        }
        if ($booking_fee <= 0 || $booking_fee > $unit['harga']) {
            throw new Exception("Booking fee tidak valid (harus lebih dari 0 dan tidak melebihi harga unit).");
        }

        if ($id_pasangan && $id_pasangan === $id_konsumen) {
            throw new Exception("Pasangan pemohon tidak boleh orang yang sama dengan pemohon.");
        }
        if ($id_pasangan && !ambil_satu("SELECT id_konsumen FROM konsumen WHERE id_konsumen = ?", "i", [$id_pasangan])) {
            throw new Exception("Data pasangan pemohon tidak ditemukan.");
        }
        // Pemohon yang sudah punya pasangan tertaut hanya boleh memilih pasangannya itu (atau dikosongkan = diajukan sendiri)
        $tautPemohon = ambil_satu("SELECT id_pasangan FROM konsumen WHERE id_konsumen = ?", "i", [$id_konsumen]);
        if ($id_pasangan && $tautPemohon && $tautPemohon['id_pasangan'] && (int) $tautPemohon['id_pasangan'] !== $id_pasangan) {
            throw new Exception("Pemohon ini sudah punya pasangan tertaut. Pasangan pemohon hanya bisa pasangan tersebut.");
        }
        $tautPasangan = $id_pasangan ? ambil_satu("SELECT id_pasangan FROM konsumen WHERE id_konsumen = ?", "i", [$id_pasangan]) : null;
        if ($tautPasangan && $tautPasangan['id_pasangan'] && (int) $tautPasangan['id_pasangan'] !== $id_konsumen) {
            throw new Exception("Orang yang dipilih sebagai pasangan pemohon sudah tertaut dengan pasangan lain.");
        }

        // Kode booking otomatis, contoh: BK-20260929-001
        $urut = ambil_satu("SELECT COUNT(*) AS n FROM booking WHERE tanggal = ?", "s", [$tanggal])['n'] + 1;
        $kode = "BK-" . date('Ymd', strtotime($tanggal)) . "-" . str_pad($urut, 3, "0", STR_PAD_LEFT);

        query("INSERT INTO booking (kode_booking, tanggal, id_konsumen, id_unit, id_marketing, cara_bayar, harga_jual, booking_fee)
               VALUES (?,?,?,?,?,?,?,?)",
              "ssiiisii", [$kode, $tanggal, $id_konsumen, $id_unit, $id_marketing, $cara_bayar, $unit['harga'], $booking_fee]);
        $id_booking = mysqli_insert_id($koneksi);

        // Pemohon dan (jika ada) pasangan pemohon
        query("INSERT INTO booking_pemohon (id_booking, id_konsumen, peran) VALUES (?,?, 'Pemohon')",
              "ii", [$id_booking, $id_konsumen]);
        if ($id_pasangan) {
            query("INSERT INTO booking_pemohon (id_booking, id_konsumen, peran) VALUES (?,?, 'Pasangan Pemohon')",
                  "ii", [$id_booking, $id_pasangan]);
        }

        // Booking fee otomatis tercatat sebagai pembayaran pertama
        query("INSERT INTO pembayaran (id_booking, tanggal, jenis, jumlah, keterangan) VALUES (?,?, 'Booking Fee', ?, 'Tanda jadi')",
              "isi", [$id_booking, $tanggal, $booking_fee]);

        // Ubah status unit menjadi Booking
        query("UPDATE unit SET status = 'Booking' WHERE id_unit = ?", "i", [$id_unit]);

        mysqli_commit($koneksi);
        konfirmasi('berhasil', "Booking $kode untuk unit {$unit['blok']}-{$unit['nomor']} berhasil disimpan.", "kwitansi.php?id=$id_booking");
    } catch (Exception $ex) {
        mysqli_rollback($koneksi);
        konfirmasi('gagal', $ex->getMessage(), 'booking.php?aksi=tambah');
    }
}

// ---------- BATALKAN BOOKING ----------
if ($aksi === 'batal') {
    $b = ambil_satu("SELECT * FROM booking WHERE id_booking = ?", "i", [(int) $_GET['id']]);
    if (!$b || $b['status'] !== 'Aktif') {
        konfirmasi('gagal', 'Booking ini tidak bisa dibatalkan (sudah lunas atau sudah batal).', 'booking.php');
    }
    query("UPDATE booking SET status = 'Batal' WHERE id_booking = ?", "i", [$b['id_booking']]);
    query("UPDATE unit SET status = 'Tersedia' WHERE id_unit = ?", "i", [$b['id_unit']]);
    konfirmasi('berhasil', "Booking {$b['kode_booking']} dibatalkan. Unit kembali tersedia.", 'booking.php');
}

// Data untuk dropdown form
$konsumen  = ambil_semua("SELECT * FROM konsumen ORDER BY nama");
$marketing = ambil_semua("SELECT * FROM marketing ORDER BY nama");
$unitTersedia = ambil_semua(
    "SELECT u.*, t.nama_tipe, t.harga FROM unit u JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     WHERE u.status = 'Tersedia' ORDER BY u.blok, u.nomor"
);
$unitDipilih = (int) ($_GET['unit'] ?? 0);

$daftar = ambil_semua(
    "SELECT b.*, k.nama AS konsumen, m.nama AS marketing, u.blok, u.nomor, t.nama_tipe,
            (SELECT k2.nama FROM booking_pemohon bp2 JOIN konsumen k2 ON bp2.id_konsumen = k2.id_konsumen
             WHERE bp2.id_booking = b.id_booking AND bp2.peran = 'Pasangan Pemohon') AS pasangan,
            (SELECT COALESCE(SUM(p.jumlah),0) FROM pembayaran p WHERE p.id_booking = b.id_booking) AS terbayar
     FROM booking b
     JOIN konsumen k   ON b.id_konsumen = k.id_konsumen
     JOIN marketing m  ON b.id_marketing = m.id_marketing
     JOIN unit u       ON b.id_unit = u.id_unit
     JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     ORDER BY b.id_booking DESC"
);
// filter status (dari chip / kotak statistik)
$f_status = $_GET['status'] ?? '';
$semuaBooking = $daftar;
if (in_array($f_status, ['Aktif', 'Lunas', 'Batal'])) {
    $daftar = array_values(array_filter($daftar, function ($b) use ($f_status) { return $b['status'] === $f_status; }));
} else {
    $f_status = '';
}

// ---------- RINGKASAN ----------
$hitungB = ['Aktif' => 0, 'Lunas' => 0, 'Batal' => 0];
foreach ($semuaBooking as $b) $hitungB[$b['status']]++;
$feeBulanIni = ambil_satu(
    "SELECT COALESCE(SUM(booking_fee),0) AS n FROM booking
     WHERE status <> 'Batal' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())"
)['n'];
$caraBayar = ambil_semua("SELECT cara_bayar, COUNT(*) AS n FROM booking WHERE status <> 'Batal' GROUP BY cara_bayar ORDER BY n DESC");
$linkB = function ($s) {
    $q = [];
    if (($_GET['tampil'] ?? '') === 'tabel') $q['tampil'] = 'tabel';
    if ($s !== '') $q['status'] = $s;
    return 'booking.php' . ($q ? '?' . http_build_query($q) : '');
};
$formTerbuka = ($aksi === 'tambah');

$tampil = (($_GET['tampil'] ?? 'visual') === 'tabel') ? 'tabel' : 'visual';
$papan = ['Aktif' => [], 'Lunas' => [], 'Batal' => []];
foreach ($semuaBooking as $b) $papan[$b['status']][] = $b;
$gayaKolom = [
    'Aktif' => ['bi-hourglass-split', 'kuning', 'Masih mencicil'],
    'Lunas' => ['bi-patch-check', 'hijau', 'Unit terjual'],
    'Batal' => ['bi-x-octagon', 'abu', 'Unit kembali tersedia'],
];

$nilaiJual = 0; foreach ($semuaBooking as $b) if ($b['status'] !== 'Batal') $nilaiJual += $b['harga_jual'];
$totB = max(1, count($semuaBooking));
$fmtM = function ($n) { return $n >= 1e9 ? 'Rp ' . number_format($n / 1e9, 2, ',', '.') . ' M' : 'Rp ' . number_format($n / 1e6, 0, ',', '.') . ' jt'; };

$kicker = "Transaksi";
include "inc/header.php";
?>
<div class="hal-kepala">
    <div class="page-heading mb-0">
        <span class="kicker">Transaksi</span>
        <h3>Booking Unit</h3>
        <p>Pemesanan unit oleh konsumen disertai booking fee.</p>
    </div>
    <div class="hal-aksi">
        <?php tombol_tampilan($tampil); ?>
        <a href="denah.php" class="btn btn-ghost"><i class="bi bi-map"></i> Pilih dari Denah</a>
        <a href="booking.php?aksi=tambah" class="btn btn-accent" data-buka-form><i class="bi bi-plus-lg"></i> Booking Baru</a>
    </div>
</div>

<?php if ($tampil === 'tabel'): ?>
<div class="hal-stat">
    <?php
    kotak_stat('Booking Aktif', $hitungB['Aktif'], 'bi-hourglass-split', 'kuning', 'masih mencicil', $linkB('Aktif'), $f_status === 'Aktif');
    kotak_stat('Lunas', $hitungB['Lunas'], 'bi-patch-check', 'hijau', 'unit terjual', $linkB('Lunas'), $f_status === 'Lunas');
    kotak_stat('Dibatalkan', $hitungB['Batal'], 'bi-x-octagon', 'ungu', 'unit kembali tersedia', $linkB('Batal'), $f_status === 'Batal');
    kotak_stat('Booking Fee Bulan Ini', 'Rp ' . number_format($feeBulanIni / 1e6, 1, ',', '.') . ' jt', 'bi-cash-coin', 'netral', 'tanda jadi masuk', $linkB(''), $f_status === '');
    ?>
</div>

<div class="hal-grid">
    <div class="hal-utama">
        <div class="card">
            <div class="card-header hal-saring">
                <span><span class="card-kicker d-block">Riwayat</span>Daftar Booking</span>
                <?php chip_saring(['' => 'Semua', 'Aktif' => 'Aktif', 'Lunas' => 'Lunas', 'Batal' => 'Batal'], $f_status, $linkB); ?>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0 align-middle" data-tools="export">
                    <thead>
                        <tr><th>Kode</th><th>Pemohon</th><th>Unit</th><th>Progres Bayar</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$daftar): ?>
                        <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i> Belum ada booking<?= $f_status ? ' berstatus ' . e($f_status) : '' ?>.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $b):
                        $warna = ['Aktif' => 'warning', 'Lunas' => 'success', 'Batal' => 'secondary'][$b['status']];
                        $pr = $b['harga_jual'] > 0 ? min(100, round($b['terbayar'] / $b['harga_jual'] * 100)) : 0; ?>
                        <tr>
                            <td><a href="kwitansi.php?id=<?= $b['id_booking'] ?>" class="kode-unit kode-link"><?= e($b['kode_booking']) ?></a><small class="d-block text-muted"><?= tgl($b['tanggal']) ?></small></td>
                            <td><b><?= e($b['konsumen']) ?></b><?php if ($b['pasangan']): ?><small class="d-block text-muted">Pasangan: <?= e($b['pasangan']) ?></small><?php endif; ?><small class="d-block text-muted"><?= e($b['cara_bayar']) ?> · <?= e($b['marketing']) ?></small></td>
                            <td><?= e("{$b['blok']}-{$b['nomor']}") ?><small class="d-block text-muted"><?= e($b['nama_tipe']) ?></small></td>
                            <td style="min-width:150px">
                                <div class="pb-bar mt-0"><span style="width:<?= $pr ?>%"></span></div>
                                <small class="text-muted"><?= $pr ?>% · <?= rupiah($b['terbayar']) ?></small>
                            </td>
                            <td><span class="badge bg-<?= $warna ?>"><?= $b['status'] ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="kwitansi.php?id=<?= $b['id_booking'] ?>" class="btn btn-sm btn-info" title="Kwitansi"><i class="bi bi-receipt"></i></a>
                                <?php if ($b['status'] === 'Aktif'): ?>
                                    <a href="pembayaran.php?aksi=tambah&booking=<?= $b['id_booking'] ?>" class="btn btn-sm btn-success" title="Input pembayaran"><i class="bi bi-cash"></i></a>
                                    <a href="booking.php?aksi=batal&id=<?= $b['id_booking'] ?>" class="btn btn-sm btn-danger" title="Batalkan" onclick="return confirm('Batalkan booking ini?')"><i class="bi bi-x-lg"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="hal-samping">
        <div class="card kartu-ajakan">
            <div class="card-body">
                <span class="ka-angka"><?= count($unitTersedia) ?></span>
                <b>unit siap dibooking</b>
                <p>Pilih unit langsung dari daftar atau lewat peta denah.</p>
                <a href="booking.php?aksi=tambah" class="btn btn-light" data-buka-form><i class="bi bi-plus-lg"></i> Booking Baru</a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-kicker d-block">Komposisi</span>Cara Bayar</div>
            <div class="card-body pt-2">
                <?php $totCB = max(1, array_sum(array_column($caraBayar, 'n')));
                foreach ($caraBayar as $cb): ?>
                    <div class="blok-baris">
                        <div class="d-flex justify-content-between"><b><?= e($cb['cara_bayar']) ?></b><small><?= $cb['n'] ?> booking · <?= round($cb['n'] / $totCB * 100) ?>%</small></div>
                        <div class="blok-bar"><span class="aksen" style="width:<?= $cb['n'] / $totCB * 100 ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$caraBayar): ?><p class="text-muted small mb-0">Belum ada booking.</p><?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<?php else: ?>
<!-- ============ RINGKASAN GELAP ============ -->
<div class="bk-hero">
    <a href="<?= e($linkB('Aktif')) ?>">
        <small>Booking aktif</small><b><?= $hitungB['Aktif'] ?></b>
        <span class="bh-bar"><i style="width: <?= $hitungB['Aktif'] / $totB * 100 ?>%; background: #f59e0b"></i></span>
        <em>masih mencicil</em>
    </a>
    <a href="<?= e($linkB('Lunas')) ?>">
        <small>Lunas / terjual</small><b><?= $hitungB['Lunas'] ?></b>
        <span class="bh-bar"><i style="width: <?= $hitungB['Lunas'] / $totB * 100 ?>%; background: #10b981"></i></span>
        <em><?= $hitungB['Batal'] ?> dibatalkan</em>
    </a>
    <div>
        <small>Nilai penjualan</small><b><?= $fmtM($nilaiJual) ?></b>
        <span class="bh-bar"><i style="width: 100%; background: var(--accent-bright)"></i></span>
        <em><?= count($unitTersedia) ?> unit masih tersedia</em>
    </div>
</div>

<div class="mutasi-saring">
    <?php chip_saring(['' => 'Semua', 'Aktif' => 'Aktif', 'Lunas' => 'Lunas', 'Batal' => 'Batal'], $f_status, $linkB); ?>
    <small class="text-muted"><?= count($daftar) ?> booking</small>
</div>

<?php foreach ($papan as $status => $isi):
    if ($f_status && $f_status !== $status) continue;
    if (!$isi) continue;
    [$ikon, $w, $ket] = $gayaKolom[$status];
    $warna = ['kuning' => '#f59e0b', 'hijau' => '#10b981', 'abu' => '#9ca3af'][$w]; ?>
    <div class="mutasi-tgl"><span><i class="bi <?= $ikon ?>"></i> <?= $status ?> · <?= $ket ?></span><small class="text-muted"><?= count($isi) ?> booking</small></div>
    <div class="card mutasi-grup">
        <?php foreach ($isi as $b):
            $pr = $b['harga_jual'] > 0 ? min(100, round($b['terbayar'] / $b['harga_jual'] * 100)) : 0; ?>
            <div class="bk-baris">
                <span class="bk-unit" style="background: <?= $warna ?>"><?= e($b['blok'] . '-' . $b['nomor']) ?></span>
                <a href="kwitansi.php?id=<?= $b['id_booking'] ?>" class="bk-isi" data-judul="Kwitansi">
                    <b><?= e($b['konsumen']) ?><?= $b['pasangan'] ? ' & ' . e($b['pasangan']) : '' ?></b>
                    <small><?= e($b['kode_booking']) ?> · <?= e($b['nama_tipe']) ?> · <?= e($b['cara_bayar']) ?> · <?= tgl($b['tanggal']) ?></small>
                </a>
                <div class="bk-prog">
                    <span class="kp-rb-bar"><i style="width: <?= $pr ?>%; background: <?= $warna ?>"></i></span>
                    <small><?= $pr ?>% terbayar · <?= e($b['marketing']) ?></small>
                </div>
                <b class="bk-nilai"><?= $fmtM($b['harga_jual']) ?></b>
                <span class="bk-aksi">
                    <?php if ($status === 'Aktif'): ?>
                        <a href="pembayaran.php?aksi=tambah&booking=<?= $b['id_booking'] ?>" class="kk-ikon hijau" title="Input pembayaran" data-judul="Pembayaran"><i class="bi bi-cash"></i></a>
                        <a href="booking.php?aksi=batal&id=<?= $b['id_booking'] ?>" class="kk-ikon" title="Batalkan" onclick="return confirm('Batalkan booking ini?')"><i class="bi bi-x-lg"></i></a>
                    <?php else: ?>
                        <a href="kwitansi.php?id=<?= $b['id_booking'] ?>" class="kk-ikon" title="Kwitansi" data-judul="Kwitansi"><i class="bi bi-receipt"></i></a>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php if (!$daftar): ?>
    <div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada booking<?= $f_status ? ' berstatus ' . e($f_status) : '' ?>.</div></div>
<?php endif; ?>
<?php endif; ?>

<?php form_pop_awal('Booking Unit Baru', 'bi-journal-plus', $formTerbuka); ?>
    <?php if (!$unitTersedia): ?>
        <div class="alert alert-warning mb-0">Semua unit sudah dibooking atau terjual.</div>
    <?php else: ?>
    <form method="post" action="booking.php">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Unit (hanya yang tersedia)</label>
                <select name="id_unit" id="id_unit" class="form-select" required>
                    <option value="" data-harga="0">-- pilih unit --</option>
                    <?php foreach ($unitTersedia as $u): ?>
                        <option value="<?= $u['id_unit'] ?>" data-harga="<?= $u['harga'] ?>" <?= $u['id_unit'] == $unitDipilih ? 'selected' : '' ?>>
                            Blok <?= e($u['blok'] . '-' . $u['nomor']) ?> · <?= e($u['nama_tipe']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <div class="harga-tampil"><small>Harga unit</small><b id="harga">Rp 0</b></div>
            </div>
            <div class="col-12">
                <label class="form-label d-flex justify-content-between">Pemohon <a href="konsumen.php?aksi=tambah">+ konsumen baru</a></label>
                <select name="id_konsumen" id="id_konsumen" class="form-select" required>
                    <option value="">-- pilih pemohon --</option>
                    <?php foreach ($konsumen as $k): ?>
                        <option value="<?= $k['id_konsumen'] ?>"><?= e($k['nama']) ?> (<?= e($k['nik']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Pasangan Pemohon</label>
                <select name="id_pasangan" id="id_pasangan" class="form-select">
                    <option value="">-- diajukan sendiri (tanpa pasangan pemohon) --</option>
                </select>
                <small class="text-muted" id="ketPasangan"></small>
            </div>
            <div class="col-6">
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" data-bawaan="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Marketing</label>
                <select name="id_marketing" class="form-select" required data-bawaan="<?= $marketing[0]['id_marketing'] ?? '' ?>">
                    <?php foreach ($marketing as $m): ?>
                        <option value="<?= $m['id_marketing'] ?>"><?= e($m['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Cara Bayar</label>
                <select name="cara_bayar" class="form-select" required data-bawaan="KPR">
                    <option>KPR</option>
                    <option>Cash Bertahap</option>
                    <option>Cash Keras</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Booking Fee (Rp)</label>
                <input type="text" inputmode="numeric" autocomplete="off" name="booking_fee" class="form-control input-uang" value="5.000.000" data-bawaan="5000000" required>
            </div>
        </div>
        <?php form_pop_tombol('Simpan Booking'); ?>
    </form>
    <script>
        // harga otomatis saat unit dipilih (termasuk unit yang dipilih dari denah)
        document.getElementById('id_unit').addEventListener('change', function () {
            const harga = this.selectedOptions[0].dataset.harga || 0;
            document.getElementById('harga').textContent = 'Rp ' + Number(harga).toLocaleString('id-ID');
        });
        document.getElementById('id_unit').dispatchEvent(new Event('change'));

        // Pasangan pemohon mengikuti pemohon yang dipilih:
        //  - sudah punya pasangan tertaut -> otomatis terpilih; pilihannya hanya nama pasangannya atau "diajukan sendiri"
        //  - belum punya pasangan         -> boleh memilih konsumen lain yang juga belum berpasangan
        const dataK = <?= json_encode(array_map(function ($k) {
            return ['id' => (int) $k['id_konsumen'], 'nama' => $k['nama'], 'nik' => $k['nik'], 'psg' => (int) $k['id_pasangan']];
        }, $konsumen), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const selPemohon  = document.getElementById('id_konsumen');
        const selPasangan = document.getElementById('id_pasangan');
        const ketPasangan = document.getElementById('ketPasangan');
        function isiPasangan() {
            const idP = parseInt(selPemohon.value) || 0;
            const p = dataK.find(function (k) { return k.id === idP; });
            const tambah = function (v, t) {
                const o = document.createElement('option');
                o.value = v; o.textContent = t; selPasangan.appendChild(o);
            };
            selPasangan.innerHTML = '';
            tambah('', '-- diajukan sendiri (tanpa pasangan pemohon) --');
            ketPasangan.textContent = '';
            if (p && p.psg) {
                const s = dataK.find(function (k) { return k.id === p.psg; });
                if (s) {
                    tambah(s.id, s.nama + ' (' + s.nik + ')');
                    selPasangan.value = String(s.id);
                    ketPasangan.textContent = 'Pasangan pemohon terdeteksi otomatis. Pilih "diajukan sendiri" kalau booking atas nama pemohon saja.';
                }
            } else if (p) {
                dataK.filter(function (k) { return k.id !== idP && !k.psg; })
                     .forEach(function (k) { tambah(k.id, k.nama + ' (' + k.nik + ')'); });
                ketPasangan.textContent = 'Pemohon belum punya pasangan tertaut. Pilih kalau diajukan bersama pasangan.';
            }
        }
        selPemohon.addEventListener('change', isiPasangan);
        isiPasangan();
    </script>
    <?php endif; ?>
<?php form_pop_akhir(); ?>

<?php include "inc/footer.php"; ?>