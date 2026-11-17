<?php
require_once "inc/cek_login.php";
$judul = "Marketing";
$aksi  = $_GET['aksi'] ?? 'list';

// ---------- SIMPAN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksi !== 'hapus') {
    $id    = (int) ($_POST['id_marketing'] ?? 0);
    $nama  = trim($_POST['nama']);
    $no_hp = trim($_POST['no_hp']);
    $email = trim($_POST['email']);

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        konfirmasi('gagal', 'Format email tidak valid.', 'marketing.php?aksi=tambah');
    }

    if ($id) {
        query("UPDATE marketing SET nama=?, no_hp=?, email=? WHERE id_marketing=?", "sssi", [$nama, $no_hp, $email, $id]);
        konfirmasi('berhasil', "Data marketing $nama berhasil diubah.", 'marketing.php');
    } else {
        query("INSERT INTO marketing (nama, no_hp, email) VALUES (?,?,?)", "sss", [$nama, $no_hp, $email]);
        konfirmasi('berhasil', "Marketing $nama berhasil ditambahkan.", 'marketing.php');
    }
}

// ---------- HAPUS ----------
if ($aksi === 'hapus') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        konfirmasi('gagal', 'Penghapusan harus lewat tombol Hapus.', 'marketing.php');
    }
    try {
        query("DELETE FROM marketing WHERE id_marketing = ?", "i", [(int) $_GET['id']]);
        konfirmasi('berhasil', 'Data marketing berhasil dihapus.', 'marketing.php');
    } catch (mysqli_sql_exception $ex) {
        konfirmasi('gagal', 'Marketing tidak bisa dihapus karena sudah menangani booking.', 'marketing.php');
    }
}

$data = ['id_marketing' => '', 'nama' => '', 'no_hp' => '', 'email' => ''];
if ($aksi === 'edit') {
    $data = ambil_satu("SELECT * FROM marketing WHERE id_marketing = ?", "i", [(int) $_GET['id']]) ?: $data;
}

$daftar = ambil_semua(
    "SELECT m.*, (SELECT COUNT(*) FROM booking b WHERE b.id_marketing = m.id_marketing AND b.status <> 'Batal') AS jumlah_booking
     FROM marketing m ORDER BY m.nama"
);

// ---------- RINGKASAN ----------
$kinerja = ambil_semua(
    "SELECT m.id_marketing, m.nama, m.email, m.no_hp,
            COUNT(CASE WHEN b.status <> 'Batal' THEN 1 END) AS booking,
            COUNT(CASE WHEN b.status = 'Lunas' THEN 1 END) AS terjual,
            COALESCE(SUM(CASE WHEN b.status <> 'Batal' THEN b.harga_jual END), 0) AS nilai
     FROM marketing m LEFT JOIN booking b ON b.id_marketing = m.id_marketing
     GROUP BY m.id_marketing ORDER BY nilai DESC, booking DESC, m.nama"
);
$kinerjaPer = array_column($kinerja, null, 'id_marketing');
$totalBookingM = array_sum(array_column($kinerja, 'booking'));
$totalNilaiM   = array_sum(array_column($kinerja, 'nilai'));
$bulanIni = ambil_satu("SELECT COUNT(*) AS n FROM booking WHERE status <> 'Batal' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())")['n'];
$formTerbuka = ($aksi === 'tambah' || $aksi === 'edit');

$kicker = "Data Master";
include "inc/header.php";
?>
<div class="hal-kepala">
    <div class="page-heading mb-0">
        <span class="kicker">Data Master</span>
        <h3>Marketing</h3>
        <p>Papan peringkat tim penjualan, dihitung otomatis dari setiap booking.</p>
    </div>
    <div class="hal-aksi">
        <a href="marketing.php?aksi=tambah" class="btn btn-accent" data-buka-form><i class="bi bi-person-badge"></i> Tambah Marketing</a>
    </div>
</div>

<?php
// urutan podium: juara 2 di kiri, juara 1 di tengah, juara 3 di kanan
$juara = array_slice($kinerja, 0, 3);
$urutPodium = array_filter([1 => $juara[1] ?? null, 0 => $juara[0] ?? null, 2 => $juara[2] ?? null]);
$warnaAvatar = ['#e0303f', '#6366f1', '#0ea5e9', '#f59e0b', '#10b981', '#ec4899'];
$nilaiTertinggi = max(1, $kinerja[0]['nilai'] ?? 1);
$jt = function ($n) { return 'Rp ' . number_format($n / 1e6, 0, ',', '.') . ' jt'; };
?>

<?php if ($kinerja): ?>
<!-- ============ PODIUM JUARA ============ -->
<section class="podium">
    <?php foreach ($urutPodium as $peringkat => $m): ?>
        <div class="pd pd-<?= $peringkat + 1 ?>" style="--i: <?= $peringkat ?>">
            <div class="pd-av" style="background: <?= $warnaAvatar[$m['id_marketing'] % count($warnaAvatar)] ?>">
                <?= e(mb_strtoupper(mb_substr($m['nama'], 0, 1))) ?>
                <?php if ($peringkat === 0): ?><span class="pd-mahkota">👑</span><?php endif; ?>
            </div>
            <b class="pd-nama"><?= e($m['nama']) ?></b>
            <span class="pd-nilai"><?= $jt($m['nilai']) ?></span>
            <small class="pd-ket"><?= $m['booking'] ?> booking · <?= $m['terjual'] ?> terjual</small>
            <div class="pd-tangga"><span><?= $peringkat + 1 ?></span></div>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ============ SEMUA ANGGOTA ============ -->
<div class="card anggota">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-kicker d-block">Tim Penjualan</span>Semua Anggota</span>
        <small class="text-muted"><?= count($kinerja) ?> orang · <?= $totalBookingM ?> booking · <?= $jt($totalNilaiM) ?></small>
    </div>
    <div class="card-body p-0">
        <?php if (!$kinerja): ?>
            <p class="text-muted text-center py-4 mb-0"><i class="bi bi-inbox"></i> Belum ada data marketing.</p>
        <?php endif; ?>
        <?php foreach ($kinerja as $i => $m): ?>
            <div class="ag-baris">
                <span class="ag-no"><?= $i + 1 ?></span>
                <div class="orang ag-orang">
                    <span class="orang-av"><?= e(mb_strtoupper(mb_substr($m['nama'], 0, 1))) ?></span>
                    <span><b><?= e($m['nama']) ?></b><small><?= e($m['email'] ?: $m['no_hp']) ?></small></span>
                </div>
                <div class="ag-bar"><i style="width: <?= $m['nilai'] / $nilaiTertinggi * 100 ?>%"></i></div>
                <span class="ag-info"><?= $m['booking'] ?> booking</span>
                <b class="ag-nilai"><?= $jt($m['nilai']) ?></b>
                <span class="ag-aksi">
                    <a href="marketing.php?aksi=edit&id=<?= $m['id_marketing'] ?>" class="btn btn-sm btn-warning" title="Edit"
                       data-buka-form data-judul="Edit <?= e($m['nama']) ?>"
                       data-isi="<?= e(json_encode(['id_marketing' => $m['id_marketing'], 'nama' => $m['nama'], 'no_hp' => $m['no_hp'], 'email' => $m['email']])) ?>"><i class="bi bi-pencil"></i></a>
                    <a href="#" class="btn btn-sm btn-danger" title="Hapus" onclick="return kirimPost('marketing.php?aksi=hapus&id=<?= $m['id_marketing'] ?>', 'Hapus marketing ini?')"><i class="bi bi-trash"></i></a>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php form_pop_awal('Tambah Marketing', 'bi-person-badge', $formTerbuka,
    $aksi === 'edit' ? 'Edit ' . $data['nama'] : null, $aksi === 'edit' ? 'Ubah Data' : 'Entri Baru'); ?>
    <form method="post" action="marketing.php">
        <input type="hidden" name="id_marketing" value="<?= e($data['id_marketing']) ?>">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Nama</label>
                <input type="text" name="nama" class="form-control" value="<?= e($data['nama']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">No. HP</label>
                <input type="text" name="no_hp" class="form-control" value="<?= e($data['no_hp']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Email</label>
                <input type="text" name="email" class="form-control" value="<?= e($data['email']) ?>">
            </div>
        </div>
        <?php form_pop_tombol(); ?>
    </form>
<?php form_pop_akhir(); ?>

<?php include "inc/footer.php"; ?>