<?php
require_once "inc/cek_login.php";
$judul = "Tipe Rumah";
$aksi  = $_GET['aksi'] ?? 'list';   // list | tambah | edit | hapus

// ---------- SIMPAN (tambah / edit) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksi !== 'hapus') {
    $id     = (int) ($_POST['id_tipe'] ?? 0);
    $nama   = trim($_POST['nama_tipe']);
    $lb     = (int) $_POST['luas_bangunan'];
    $lt     = (int) $_POST['luas_tanah'];
    $kamar  = (int) $_POST['kamar_tidur'];
    $harga  = angka($_POST['harga']);

    if ($nama === '' || $lb <= 0 || $lt <= 0 || $harga <= 0) {
        konfirmasi('gagal', 'Data tipe rumah tidak lengkap atau tidak valid.', 'tipe.php');
    }

    if ($id) {
        query("UPDATE tipe_rumah SET nama_tipe=?, luas_bangunan=?, luas_tanah=?, kamar_tidur=?, harga=? WHERE id_tipe=?",
              "siiiii", [$nama, $lb, $lt, $kamar, $harga, $id]);
        konfirmasi('berhasil', "Tipe rumah \"$nama\" berhasil diubah.", 'tipe.php');
    } else {
        query("INSERT INTO tipe_rumah (nama_tipe, luas_bangunan, luas_tanah, kamar_tidur, harga) VALUES (?,?,?,?,?)",
              "siiii", [$nama, $lb, $lt, $kamar, $harga]);
        konfirmasi('berhasil', "Tipe rumah \"$nama\" berhasil ditambahkan.", 'tipe.php');
    }
}

// ---------- HAPUS ----------
if ($aksi === 'hapus') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        konfirmasi('gagal', 'Penghapusan harus lewat tombol Hapus.', 'tipe.php');
    }
    try {
        query("DELETE FROM tipe_rumah WHERE id_tipe = ?", "i", [(int) $_GET['id']]);
        konfirmasi('berhasil', 'Tipe rumah berhasil dihapus.', 'tipe.php');
    } catch (mysqli_sql_exception $ex) {
        konfirmasi('gagal', 'Tipe rumah tidak bisa dihapus karena masih dipakai oleh data unit.', 'tipe.php');
    }
}

// ---------- DATA UNTUK FORM EDIT ----------
$data = ['id_tipe' => '', 'nama_tipe' => '', 'luas_bangunan' => '', 'luas_tanah' => '', 'kamar_tidur' => '', 'harga' => ''];
if ($aksi === 'edit') {
    $data = ambil_satu("SELECT * FROM tipe_rumah WHERE id_tipe = ?", "i", [(int) $_GET['id']]) ?: $data;
}

$daftar = ambil_semua(
    "SELECT t.*, (SELECT COUNT(*) FROM unit u WHERE u.id_tipe = t.id_tipe) AS jumlah_unit
     FROM tipe_rumah t ORDER BY t.harga"
);

// ---------- RINGKASAN ----------
$jmlTipe  = count($daftar);
$hargaMin = $daftar ? min(array_column($daftar, 'harga')) : 0;
$hargaMax = $daftar ? max(array_column($daftar, 'harga')) : 0;
$stok = ambil_semua(
    "SELECT t.id_tipe, t.nama_tipe, COUNT(u.id_unit) AS total,
            SUM(u.status = 'Tersedia') AS tersedia, SUM(u.status = 'Terjual') AS terjual
     FROM tipe_rumah t LEFT JOIN unit u ON u.id_tipe = t.id_tipe
     GROUP BY t.id_tipe ORDER BY t.harga"
);
$stokTipe = array_column($stok, null, 'id_tipe');
$totalUnitTipe = array_sum(array_column($stok, 'total'));
$formTerbuka = ($aksi === 'tambah' || $aksi === 'edit');

$kicker = "Data Master";
include "inc/header.php";
?>
<div class="hal-kepala">
    <div class="page-heading mb-0">
        <span class="kicker">Data Master</span>
        <h3>Tipe Rumah</h3>
        <p>Katalog tipe rumah yang ditawarkan, dasar harga setiap unit.</p>
    </div>
    <div class="hal-aksi">
        <a href="pricelist.php" class="btn btn-ghost"><i class="bi bi-tags"></i> Price List</a>
        <a href="tipe.php?aksi=tambah" class="btn btn-accent" data-buka-form><i class="bi bi-plus-lg"></i> Tambah Tipe</a>
    </div>
</div>

<div class="hal-stat">
    <?php
    kotak_stat('Jumlah Tipe', $jmlTipe, 'bi-houses', 'netral', 'tipe ditawarkan');
    kotak_stat('Harga Termurah', $hargaMin ? 'Rp ' . number_format($hargaMin / 1e6, 0, ',', '.') . ' jt' : '-', 'bi-arrow-down-circle', 'hijau', 'mulai dari');
    kotak_stat('Harga Termahal', $hargaMax ? 'Rp ' . number_format($hargaMax / 1e6, 0, ',', '.') . ' jt' : '-', 'bi-arrow-up-circle', 'ungu', 'paling premium');
    kotak_stat('Total Unit', $totalUnitTipe, 'bi-geo-alt', 'kuning', 'dari semua tipe', 'unit.php');
    ?>
</div>

<div class="hal-grid">
    <div class="hal-utama">
        <div class="card">
            <div class="card-header"><span class="card-kicker d-block">Katalog</span>Daftar Tipe Rumah</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0 align-middle" data-tools>
                    <thead>
                        <tr><th>Tipe</th><th>Luas (LB / LT)</th><th>Kamar</th><th>Harga</th><th>Unit</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$daftar): ?>
                        <tr class="empty-row"><td colspan="6"><i class="bi bi-inbox"></i> Belum ada tipe rumah.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $t): ?>
                        <tr>
                            <td><span class="kode-unit"><?= e($t['nama_tipe']) ?></span></td>
                            <td><?= $t['luas_bangunan'] ?> / <?= $t['luas_tanah'] ?> m²</td>
                            <td><i class="bi bi-door-closed text-muted"></i> <?= $t['kamar_tidur'] ?> KT</td>
                            <td class="money"><?= rupiah($t['harga']) ?></td>
                            <td><?= $t['jumlah_unit'] ?></td>
                            <td class="text-end text-nowrap">
                                <a href="tipe.php?aksi=edit&id=<?= $t['id_tipe'] ?>" class="btn btn-sm btn-warning" title="Edit"
                                   data-buka-form data-judul="Edit <?= e($t['nama_tipe']) ?>"
                                   data-isi="<?= e(json_encode(['id_tipe' => $t['id_tipe'], 'nama_tipe' => $t['nama_tipe'], 'luas_bangunan' => $t['luas_bangunan'], 'luas_tanah' => $t['luas_tanah'], 'kamar_tidur' => $t['kamar_tidur'], 'harga' => $t['harga']])) ?>"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="btn btn-sm btn-danger" title="Hapus" onclick="return kirimPost('tipe.php?aksi=hapus&id=<?= $t['id_tipe'] ?>', 'Hapus tipe ini?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="hal-samping">
        <div class="card">
            <div class="card-header"><span class="card-kicker d-block">Stok</span>Ketersediaan per Tipe</div>
            <div class="card-body pt-2">
                <?php foreach ($stok as $st):
                    $tot = max(1, $st['total']); ?>
                    <div class="blok-baris">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <b><?= e($st['nama_tipe']) ?></b>
                            <small><?= (int) $st['tersedia'] ?> dari <?= $st['total'] ?> tersedia</small>
                        </div>
                        <div class="blok-bar">
                            <span class="h" style="width:<?= $st['tersedia'] / $tot * 100 ?>%"></span>
                            <span class="k" style="width:<?= ($st['total'] - $st['tersedia'] - $st['terjual']) / $tot * 100 ?>%"></span>
                            <span class="u" style="width:<?= $st['terjual'] / $tot * 100 ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="blok-legenda"><span><i class="h"></i>Tersedia</span><span><i class="k"></i>Booking</span><span><i class="u"></i>Terjual</span></div>
            </div>
        </div>
        <div class="card kartu-info">
            <div class="card-body">
                <i class="bi bi-lightbulb"></i>
                <p class="mb-0">Tipe yang masih dipakai unit <b>tidak bisa dihapus</b>. Ubah harga di sini, dan harga unit baru ikut berubah.</p>
            </div>
        </div>
    </aside>
</div>

<?php form_pop_awal('Tambah Tipe Rumah', 'bi-houses', $formTerbuka,
    $aksi === 'edit' ? 'Edit ' . $data['nama_tipe'] : null, $aksi === 'edit' ? 'Ubah Data' : 'Entri Baru'); ?>
    <form method="post" action="tipe.php">
        <input type="hidden" name="id_tipe" value="<?= e($data['id_tipe']) ?>">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Nama Tipe</label>
                <input type="text" name="nama_tipe" class="form-control" placeholder="contoh: Tipe 36" value="<?= e($data['nama_tipe']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Luas Bangunan (m²)</label>
                <input type="number" name="luas_bangunan" class="form-control" min="1" value="<?= e($data['luas_bangunan']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Luas Tanah (m²)</label>
                <input type="number" name="luas_tanah" class="form-control" min="1" value="<?= e($data['luas_tanah']) ?>" required>
            </div>
            <div class="col-5">
                <label class="form-label">Kamar Tidur</label>
                <input type="number" name="kamar_tidur" class="form-control" min="1" value="<?= e($data['kamar_tidur']) ?>" required>
            </div>
            <div class="col-7">
                <label class="form-label">Harga (Rp)</label>
                <input type="text" inputmode="numeric" autocomplete="off" name="harga" class="form-control input-uang" value="<?= e($data['harga']) ?>" required>
            </div>
        </div>
        <?php form_pop_tombol(); ?>
    </form>
<?php form_pop_akhir(); ?>

<?php include "inc/footer.php"; ?>