<?php
require_once "inc/cek_login.php";
$judul = "Unit / Kavling";
$aksi  = $_GET['aksi'] ?? 'list';

// ---------- SIMPAN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksi !== 'hapus') {
    $id      = (int) ($_POST['id_unit'] ?? 0);
    $blok    = strtoupper(trim($_POST['blok']));
    $nomor   = trim($_POST['nomor']);
    $id_tipe = (int) $_POST['id_tipe'];

    // Validasi: blok + nomor tidak boleh kembar
    $kembar = ambil_satu("SELECT id_unit FROM unit WHERE blok=? AND nomor=? AND id_unit<>?", "ssi", [$blok, $nomor, $id]);
    if ($kembar) {
        konfirmasi('gagal', "Unit Blok $blok nomor $nomor sudah terdaftar.", 'unit.php?aksi=tambah');
    }

    if ($id) {
        query("UPDATE unit SET blok=?, nomor=?, id_tipe=? WHERE id_unit=?", "ssii", [$blok, $nomor, $id_tipe, $id]);
        konfirmasi('berhasil', "Unit $blok-$nomor berhasil diubah.", 'unit.php');
    } else {
        query("INSERT INTO unit (blok, nomor, id_tipe) VALUES (?,?,?)", "ssi", [$blok, $nomor, $id_tipe]);
        konfirmasi('berhasil', "Unit $blok-$nomor berhasil ditambahkan.", 'unit.php');
    }
}

// ---------- HAPUS ----------
if ($aksi === 'hapus') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        konfirmasi('gagal', 'Penghapusan harus lewat tombol Hapus.', 'unit.php');
    }
    try {
        query("DELETE FROM unit WHERE id_unit = ?", "i", [(int) $_GET['id']]);
        konfirmasi('berhasil', 'Unit berhasil dihapus.', 'unit.php');
    } catch (mysqli_sql_exception $ex) {
        konfirmasi('gagal', 'Unit tidak bisa dihapus karena sudah punya data booking.', 'unit.php');
    }
}

$data = ['id_unit' => '', 'blok' => '', 'nomor' => '', 'id_tipe' => ''];
if ($aksi === 'edit') {
    $data = ambil_satu("SELECT * FROM unit WHERE id_unit = ?", "i", [(int) $_GET['id']]) ?: $data;
}

// ---------- FILTER / PENCARIAN ----------
$f_status = $_GET['status'] ?? '';
$f_tipe   = (int) ($_GET['tipe'] ?? 0);
$sql = "SELECT u.*, t.nama_tipe, t.harga FROM unit u JOIN tipe_rumah t ON u.id_tipe = t.id_tipe WHERE 1=1";
$tipe_param = "";
$params = [];
if ($f_status !== '') { $sql .= " AND u.status = ?";  $tipe_param .= "s"; $params[] = $f_status; }
if ($f_tipe)          { $sql .= " AND u.id_tipe = ?"; $tipe_param .= "i"; $params[] = $f_tipe; }
$sql .= " ORDER BY u.blok, u.nomor";

$daftar    = ambil_semua($sql, $tipe_param, $params);
$semuaTipe = ambil_semua("SELECT * FROM tipe_rumah ORDER BY nama_tipe");

// ---------- RINGKASAN untuk kotak statistik & panel samping ----------
$hitung = ['Tersedia' => 0, 'Booking' => 0, 'Terjual' => 0];
foreach (ambil_semua("SELECT status, COUNT(*) AS n FROM unit GROUP BY status") as $r) $hitung[$r['status']] = (int) $r['n'];
$totalUnit = array_sum($hitung);
$perBlok = ambil_semua(
    "SELECT blok, COUNT(*) AS total,
            SUM(status = 'Tersedia') AS tersedia, SUM(status = 'Booking') AS booking, SUM(status = 'Terjual') AS terjual
     FROM unit GROUP BY blok ORDER BY blok"
);
$perTipe = ambil_semua(
    "SELECT t.nama_tipe, t.harga, COUNT(u.id_unit) AS total, SUM(u.status = 'Tersedia') AS tersedia
     FROM tipe_rumah t LEFT JOIN unit u ON u.id_tipe = t.id_tipe
     GROUP BY t.id_tipe ORDER BY t.harga"
);
// link filter yang tetap membawa filter tipe
function linkStatus($s) {
    global $f_tipe;
    $q = [];
    if ($s !== '') $q['status'] = $s;
    if ($f_tipe) $q['tipe'] = $f_tipe;
    return 'unit.php' . ($q ? '?' . http_build_query($q) : '');
}
$formTerbuka = ($aksi === 'tambah' || $aksi === 'edit');

$kicker = "Data Master";
include "inc/header.php";
?>
<!-- ============ KEPALA HALAMAN ============ -->
<div class="hal-kepala">
    <div class="page-heading mb-0">
        <span class="kicker">Data Master</span>
        <h3>Unit / Kavling</h3>
        <p>Inventaris seluruh unit per blok beserta status jualnya.</p>
    </div>
    <div class="hal-aksi">
        <a href="denah.php" class="btn btn-ghost"><i class="bi bi-map"></i> Lihat Denah</a>
        <a href="unit.php?aksi=tambah" class="btn btn-accent" data-buka-form><i class="bi bi-plus-lg"></i> Tambah Unit</a>
    </div>
</div>

<!-- ============ STATISTIK (bisa diklik untuk menyaring) ============ -->
<div class="hal-stat">
    <?php
    $pct = function ($n) use ($totalUnit) { return ($totalUnit ? round($n / $totalUnit * 100) : 0) . '% dari total'; };
    kotak_stat('Total Unit', $totalUnit, 'bi-houses', 'netral', 'semua blok', linkStatus(''), $f_status === '');
    kotak_stat('Tersedia', $hitung['Tersedia'], 'bi-house', 'hijau', $pct($hitung['Tersedia']), linkStatus('Tersedia'), $f_status === 'Tersedia');
    kotak_stat('Dibooking', $hitung['Booking'], 'bi-bookmark-check', 'kuning', $pct($hitung['Booking']), linkStatus('Booking'), $f_status === 'Booking');
    kotak_stat('Terjual', $hitung['Terjual'], 'bi-house-check', 'ungu', $pct($hitung['Terjual']), linkStatus('Terjual'), $f_status === 'Terjual');
    ?>
</div>

<!-- ============ ISI: DAFTAR (kiri) + PANEL SAMPING (kanan) ============ -->
<div class="hal-grid">
    <div class="hal-utama">
        <div class="card">
            <div class="card-header hal-saring">
                <span><span class="card-kicker d-block">Inventaris</span>Daftar Unit</span>
                <?php chip_saring(['' => 'Semua', 'Tersedia' => 'Tersedia', 'Booking' => 'Booking', 'Terjual' => 'Terjual'], $f_status, 'linkStatus'); ?>
                <form method="get" class="d-flex gap-2">
                    <?php if ($f_status !== ''): ?><input type="hidden" name="status" value="<?= e($f_status) ?>"><?php endif; ?>
                    <select name="tipe" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">Semua tipe</option>
                        <?php foreach ($semuaTipe as $t): ?>
                            <option value="<?= $t['id_tipe'] ?>" <?= $f_tipe == $t['id_tipe'] ? 'selected' : '' ?>><?= e($t['nama_tipe']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0 align-middle" data-tools="export">
                    <thead>
                        <tr><th>Unit</th><th>Tipe</th><th>Harga</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$daftar): ?>
                        <tr class="empty-row"><td colspan="5"><i class="bi bi-search"></i> Data tidak ditemukan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $u):
                        $warna = ['Tersedia' => 'success', 'Booking' => 'warning', 'Terjual' => 'primary'][$u['status']]; ?>
                        <tr>
                            <td><span class="kode-unit"><?= e($u['blok'] . '-' . $u['nomor']) ?></span></td>
                            <td><?= e($u['nama_tipe']) ?></td>
                            <td class="money"><?= rupiah($u['harga']) ?></td>
                            <td><span class="badge bg-<?= $warna ?>"><?= $u['status'] ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="unit.php?aksi=edit&id=<?= $u['id_unit'] ?>" class="btn btn-sm btn-warning" title="Edit"
                                   data-buka-form data-judul="Edit Unit <?= e($u['blok'] . '-' . $u['nomor']) ?>"
                                   data-isi="<?= e(json_encode(['id_unit' => $u['id_unit'], 'blok' => $u['blok'], 'nomor' => $u['nomor'], 'id_tipe' => $u['id_tipe']])) ?>"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="btn btn-sm btn-danger" title="Hapus" onclick="return kirimPost('unit.php?aksi=hapus&id=<?= $u['id_unit'] ?>', 'Hapus unit ini?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="hal-samping">

        <!-- ringkasan per blok -->
        <div class="card">
            <div class="card-header"><span class="card-kicker d-block">Ringkasan</span>Per Blok</div>
            <div class="card-body pt-2">
                <?php foreach ($perBlok as $bk):
                    $t = max(1, $bk['total']); ?>
                    <div class="blok-baris">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <b>Blok <?= e($bk['blok']) ?></b>
                            <small><?= $bk['tersedia'] ?> dari <?= $bk['total'] ?> tersedia</small>
                        </div>
                        <div class="blok-bar" title="Hijau = tersedia, kuning = booking, ungu = terjual">
                            <span class="h" style="width:<?= $bk['tersedia'] / $t * 100 ?>%"></span>
                            <span class="k" style="width:<?= $bk['booking'] / $t * 100 ?>%"></span>
                            <span class="u" style="width:<?= $bk['terjual'] / $t * 100 ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="blok-legenda"><span><i class="h"></i>Tersedia</span><span><i class="k"></i>Booking</span><span><i class="u"></i>Terjual</span></div>
            </div>
        </div>

        <!-- ringkasan per tipe -->
        <div class="card">
            <div class="card-header"><span class="card-kicker d-block">Ringkasan</span>Per Tipe</div>
            <div class="card-body pt-2">
                <?php foreach ($perTipe as $pt): ?>
                    <div class="tipe-baris">
                        <div>
                            <b><?= e($pt['nama_tipe']) ?></b>
                            <small><?= rupiah($pt['harga']) ?></small>
                        </div>
                        <span class="tipe-sisa <?= (int) $pt['tersedia'] === 0 ? 'habis' : '' ?>">
                            <?= (int) $pt['tersedia'] === 0 ? 'Habis' : $pt['tersedia'] . ' tersedia' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
</div>
<!-- ============ POP-UP FORM TAMBAH / EDIT ============ -->
<?php form_pop_awal('Tambah Unit', 'bi-house-add', $formTerbuka,
    $aksi === 'edit' ? 'Edit Unit ' . $data['blok'] . '-' . $data['nomor'] : null, $aksi === 'edit' ? 'Ubah Data' : 'Entri Baru'); ?>
    <form method="post" action="unit.php">
        <input type="hidden" name="id_unit" value="<?= e($data['id_unit']) ?>">
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label">Blok</label>
                <input type="text" name="blok" class="form-control" maxlength="5" placeholder="A" value="<?= e($data['blok']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Nomor</label>
                <input type="text" name="nomor" class="form-control" maxlength="5" placeholder="01" value="<?= e($data['nomor']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Tipe Rumah</label>
                <select name="id_tipe" class="form-select" required>
                    <option value="">-- pilih tipe --</option>
                    <?php foreach ($semuaTipe as $t): ?>
                        <option value="<?= $t['id_tipe'] ?>" <?= $t['id_tipe'] == $data['id_tipe'] ? 'selected' : '' ?>><?= e($t['nama_tipe']) ?> - <?= rupiah($t['harga']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php form_pop_tombol(); ?>
    </form>
<?php form_pop_akhir(); ?>

<?php include "inc/footer.php"; ?>