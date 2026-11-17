<?php
require_once "inc/cek_login.php";
$judul = "Konsumen";
$aksi  = $_GET['aksi'] ?? 'list';

// ---------- SIMPAN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksi !== 'hapus') {
    $id        = (int) ($_POST['id_konsumen'] ?? 0);
    $nik       = trim($_POST['nik']);
    $nama      = trim($_POST['nama']);
    $no_hp     = trim($_POST['no_hp']);
    $alamat    = trim($_POST['alamat']);
    $pekerjaan = trim($_POST['pekerjaan']);

    // Pasangan pemohon: '' = tanpa pasangan, 'baru' = tambah orang baru, angka = konsumen yang sudah terdaftar
    $pilih        = $_POST['pasangan_pilih'] ?? '';
    $pasanganBaru = ($pilih === 'baru');
    $id_psg_ada   = (!$pasanganBaru && $pilih !== '') ? (int) $pilih : 0;
    $p_nama       = trim($_POST['pasangan_nama'] ?? '');
    $p_nik        = trim($_POST['pasangan_nik'] ?? '');
    $p_hp         = trim($_POST['pasangan_no_hp'] ?? '');
    $p_kerja      = trim($_POST['pasangan_pekerjaan'] ?? '');
    $kembali      = $id ? 'konsumen.php?aksi=edit&id=' . $id : 'konsumen.php?aksi=tambah';
    $namaPasangan = '';

    if ($pasanganBaru) {
        $namaPasangan = $p_nama;
        if ($p_nama === '' || $p_hp === '') {
            konfirmasi('gagal', 'Nama dan No. HP pasangan pemohon wajib diisi.', $kembali);
        }
        if (!preg_match('/^[0-9]{16}$/', $p_nik)) {
            konfirmasi('gagal', 'NIK pasangan pemohon harus berisi 16 digit angka.', $kembali);
        }
        if ($p_nik === $nik) {
            konfirmasi('gagal', 'NIK pasangan pemohon tidak boleh sama dengan NIK pemohon.', $kembali);
        }
        if (ambil_satu("SELECT id_konsumen FROM konsumen WHERE nik=?", "s", [$p_nik])) {
            konfirmasi('gagal', "NIK $p_nik sudah terdaftar. Pilih orangnya dari daftar pasangan pemohon, jangan ditambah baru.", $kembali);
        }
    }
    if ($id_psg_ada) {
        $psg = ambil_satu("SELECT nama, id_pasangan FROM konsumen WHERE id_konsumen=?", "i", [$id_psg_ada]);
        if (!$psg) {
            konfirmasi('gagal', 'Data pasangan pemohon tidak ditemukan.', $kembali);
        }
        if ($id_psg_ada === $id) {
            konfirmasi('gagal', 'Pasangan pemohon tidak boleh orang yang sama dengan pemohon.', $kembali);
        }
        if ($psg['id_pasangan'] && (int) $psg['id_pasangan'] !== $id) {
            konfirmasi('gagal', "{$psg['nama']} sudah tertaut dengan pasangan pemohon lain.", $kembali);
        }
        $namaPasangan = $psg['nama'];
    }

    // Validasi NIK harus 16 digit angka
    if (!preg_match('/^[0-9]{16}$/', $nik)) {
        konfirmasi('gagal', 'NIK harus berisi 16 digit angka.', 'konsumen.php?aksi=tambah');
    }
    // Validasi NIK tidak boleh dobel
    if (ambil_satu("SELECT id_konsumen FROM konsumen WHERE nik=? AND id_konsumen<>?", "si", [$nik, $id])) {
        konfirmasi('gagal', "Konsumen dengan NIK $nik sudah terdaftar.", 'konsumen.php?aksi=tambah');
    }

    mysqli_begin_transaction($koneksi);
    try {
        if ($id) {
            query("UPDATE konsumen SET nik=?, nama=?, no_hp=?, alamat=?, pekerjaan=? WHERE id_konsumen=?",
                  "sssssi", [$nik, $nama, $no_hp, $alamat, $pekerjaan, $id]);
            $id_pemohon = $id;
        } else {
            query("INSERT INTO konsumen (nik, nama, no_hp, alamat, pekerjaan) VALUES (?,?,?,?,?)",
                  "sssss", [$nik, $nama, $no_hp, $alamat, $pekerjaan]);
            $id_pemohon = mysqli_insert_id($koneksi);
        }

        // Lepas tautan pasangan lama (kalau ada), lalu pasang yang baru sesuai pilihan
        query("UPDATE konsumen SET id_pasangan = NULL WHERE id_konsumen = ? OR id_pasangan = ?", "ii", [$id_pemohon, $id_pemohon]);

        $id_psg = 0;
        if ($pasanganBaru) {
            // Alamat pasangan pemohon mengikuti alamat pemohon
            query("INSERT INTO konsumen (nik, nama, no_hp, alamat, pekerjaan) VALUES (?,?,?,?,?)",
                  "sssss", [$p_nik, $p_nama, $p_hp, $alamat, $p_kerja]);
            $id_psg = mysqli_insert_id($koneksi);
        } elseif ($id_psg_ada) {
            $id_psg = $id_psg_ada;
        }
        if ($id_psg) {
            query("UPDATE konsumen SET id_pasangan = ? WHERE id_konsumen = ?", "ii", [$id_psg, $id_pemohon]);
            query("UPDATE konsumen SET id_pasangan = ? WHERE id_konsumen = ?", "ii", [$id_pemohon, $id_psg]);
        }
        mysqli_commit($koneksi);
    } catch (mysqli_sql_exception $ex) {
        mysqli_rollback($koneksi);
        konfirmasi('gagal', 'Data konsumen gagal disimpan.', $kembali);
    }

    $awal = $id ? "Data konsumen $nama berhasil diubah" : "Konsumen $nama berhasil ditambahkan";
    konfirmasi('berhasil', $namaPasangan ? "$awal, berpasangan dengan $namaPasangan." : "$awal.", 'konsumen.php');
}

// ---------- HAPUS ----------
if ($aksi === 'hapus') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        konfirmasi('gagal', 'Penghapusan harus lewat tombol Hapus.', 'konsumen.php');
    }
    try {
        query("DELETE FROM konsumen WHERE id_konsumen = ?", "i", [(int) $_GET['id']]);
        konfirmasi('berhasil', 'Data konsumen berhasil dihapus.', 'konsumen.php');
    } catch (mysqli_sql_exception $ex) {
        konfirmasi('gagal', 'Konsumen tidak bisa dihapus karena sudah punya data booking.', 'konsumen.php');
    }
}

$data = ['id_konsumen' => '', 'nik' => '', 'nama' => '', 'no_hp' => '', 'alamat' => '', 'pekerjaan' => ''];
if ($aksi === 'edit') {
    $data = ambil_satu("SELECT * FROM konsumen WHERE id_konsumen = ?", "i", [(int) $_GET['id']]) ?: $data;
}

// Daftar konsumen untuk pilihan pasangan pemohon di form
$pilihanPasangan = ambil_semua("SELECT id_konsumen, nama, nik, id_pasangan FROM konsumen ORDER BY nama");

// Pencarian nama / NIK
$cari = trim($_GET['cari'] ?? '');
$daftar = ambil_semua(
    "SELECT k.*, p.nama AS nama_pasangan FROM konsumen k LEFT JOIN konsumen p ON k.id_pasangan = p.id_konsumen
     WHERE k.nama LIKE ? OR k.nik LIKE ? ORDER BY k.nama",
    "ss", ["%$cari%", "%$cari%"]
);

// ---------- RINGKASAN ----------
$statBooking = ambil_semua(
    "SELECT k.id_konsumen,
            SUM(b.status = 'Aktif') AS aktif, SUM(b.status = 'Lunas') AS lunas, COUNT(b.id_booking) AS semua
     FROM konsumen k
     LEFT JOIN booking_pemohon bp ON bp.id_konsumen = k.id_konsumen
     LEFT JOIN booking b ON b.id_booking = bp.id_booking
     GROUP BY k.id_konsumen"
);
$perKonsumen = array_column($statBooking, null, 'id_konsumen');
$totalKonsumen = count($statBooking);
$jmlAktif = count(array_filter($statBooking, function ($r) { return $r['aktif'] > 0; }));
$jmlLunas = count(array_filter($statBooking, function ($r) { return $r['lunas'] > 0; }));
$jmlProspek = count(array_filter($statBooking, function ($r) { return $r['semua'] == 0; }));
$pekerjaan = ambil_semua(
    "SELECT COALESCE(NULLIF(pekerjaan, ''), 'Lainnya') AS p, COUNT(*) AS n
     FROM konsumen GROUP BY p ORDER BY n DESC LIMIT 5"
);
$formTerbuka = ($aksi === 'tambah' || $aksi === 'edit');

$tampil = (($_GET['tampil'] ?? 'visual') === 'tabel') ? 'tabel' : 'visual';
$warnaAv = ['#e0303f', '#6366f1', '#0ea5e9', '#f59e0b', '#10b981', '#ec4899'];
$statusK = function ($id) use ($perKonsumen) {
    $sb = $perKonsumen[$id] ?? ['aktif' => 0, 'lunas' => 0];
    return $sb['aktif'] > 0 ? 'Mencicil' : ($sb['lunas'] > 0 ? 'Lunas' : 'Prospek');
};
$f_st = $_GET['st'] ?? '';
$kartuKonsumen = $daftar;
if (in_array($f_st, ['Prospek', 'Mencicil', 'Lunas'])) {
    $kartuKonsumen = array_values(array_filter($daftar, function ($k) use ($statusK, $f_st) { return $statusK($k['id_konsumen']) === $f_st; }));
} else { $f_st = ''; }
$jmlSt = ['' => count($daftar), 'Prospek' => 0, 'Mencicil' => 0, 'Lunas' => 0];
foreach ($daftar as $k) $jmlSt[$statusK($k['id_konsumen'])]++;
$linkSt = function ($v) use ($cari) { $q = []; if ($cari !== '') $q['cari'] = $cari; if ($v !== '') $q['st'] = $v; return 'konsumen.php' . ($q ? '?' . http_build_query($q) : ''); };

// riwayat booking per konsumen (untuk panel profil)
$riwayatK = [];
foreach (ambil_semua(
    "SELECT b.id_booking, bp.id_konsumen, bp.peran, b.kode_booking, b.tanggal, b.status, b.harga_jual, b.cara_bayar,
            u.blok, u.nomor, t.nama_tipe,
            (SELECT COALESCE(SUM(p.jumlah),0) FROM pembayaran p WHERE p.id_booking = b.id_booking) AS terbayar
     FROM booking b JOIN booking_pemohon bp ON bp.id_booking = b.id_booking
     JOIN unit u ON b.id_unit = u.id_unit JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     ORDER BY b.id_booking DESC"
) as $r) $riwayatK[$r['id_konsumen']][] = $r;
$pilihK = (int) ($_GET['pilih'] ?? ($kartuKonsumen[0]['id_konsumen'] ?? 0));

$kicker = "Data Master";
include "inc/header.php";
?>
<div class="hal-kepala">
    <div class="page-heading mb-0">
        <span class="kicker">Data Master</span>
        <h3>Konsumen</h3>
        <p>Data pembeli rumah: NIK, kontak, dan domisili.</p>
    </div>
    <div class="hal-aksi">
        <?php tombol_tampilan($tampil); ?>
        <a href="konsumen.php?aksi=tambah" class="btn btn-accent" data-buka-form><i class="bi bi-person-plus"></i> Tambah Konsumen</a>
    </div>
</div>

<?php if ($tampil === 'tabel'): ?>
<div class="hal-stat">
    <?php
    kotak_stat('Total Konsumen', $totalKonsumen, 'bi-people', 'netral', 'terdaftar');
    kotak_stat('Booking Aktif', $jmlAktif, 'bi-hourglass-split', 'kuning', 'sedang mencicil', 'booking.php?status=Aktif');
    kotak_stat('Sudah Lunas', $jmlLunas, 'bi-patch-check', 'hijau', 'pemilik rumah', 'booking.php?status=Lunas');
    kotak_stat('Belum Booking', $jmlProspek, 'bi-person-dash', 'ungu', 'calon pembeli');
    ?>
</div>

<div class="hal-grid">
    <div class="hal-utama">
        <div class="card">
            <div class="card-header hal-saring">
                <span><span class="card-kicker d-block">Direktori</span>Daftar Konsumen</span>
                <form class="cari-kapsul" method="get">
                    <i class="bi bi-search"></i>
                    <input type="hidden" name="tampil" value="tabel">
                    <input type="text" name="cari" class="form-control" placeholder="Cari nama / NIK" value="<?= e($cari) ?>">
                </form>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0 align-middle" data-tools="export">
                    <thead>
                        <tr><th>Konsumen</th><th>NIK</th><th>Kontak</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$daftar): ?>
                        <tr class="empty-row"><td colspan="5"><i class="bi bi-search"></i> Data tidak ditemukan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $k):
                        $sb = $perKonsumen[$k['id_konsumen']] ?? ['aktif' => 0, 'lunas' => 0, 'semua' => 0];
                        $inisialK = mb_strtoupper(mb_substr($k['nama'], 0, 1)); ?>
                        <tr>
                            <td>
                                <div class="orang">
                                    <span class="orang-av"><?= e($inisialK) ?></span>
                                    <span><b><?= e($k['nama']) ?></b><small><?= e($k['alamat']) ?></small></span>
                                </div>
                            </td>
                            <td class="text-muted"><?= e($k['nik']) ?></td>
                            <td><?= e($k['no_hp']) ?><small class="d-block text-muted"><?= e($k['pekerjaan']) ?></small></td>
                            <td>
                                <?php if ($sb['aktif'] > 0): ?><span class="badge bg-warning">Mencicil</span>
                                <?php elseif ($sb['lunas'] > 0): ?><span class="badge bg-success">Lunas</span>
                                <?php else: ?><span class="badge bg-secondary">Prospek</span><?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="konsumen.php?aksi=edit&id=<?= $k['id_konsumen'] ?>" class="btn btn-sm btn-warning" title="Edit"
                                   data-buka-form data-judul="Edit <?= e($k['nama']) ?>"
                                   data-isi="<?= e(json_encode(['id_konsumen' => $k['id_konsumen'], 'nik' => $k['nik'], 'nama' => $k['nama'], 'no_hp' => $k['no_hp'], 'alamat' => $k['alamat'], 'pekerjaan' => $k['pekerjaan'], 'pasangan_pilih' => $k['id_pasangan'] ?: ''])) ?>"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="btn btn-sm btn-danger" title="Hapus" onclick="return kirimPost('konsumen.php?aksi=hapus&id=<?= $k['id_konsumen'] ?>', 'Hapus konsumen ini?')"><i class="bi bi-trash"></i></a>
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
            <div class="card-header"><span class="card-kicker d-block">Profil</span>Pekerjaan Konsumen</div>
            <div class="card-body pt-2">
                <?php $maxP = $pekerjaan ? max(array_column($pekerjaan, 'n')) : 1;
                foreach ($pekerjaan as $pk): ?>
                    <div class="blok-baris">
                        <div class="d-flex justify-content-between"><b><?= e($pk['p']) ?></b><small><?= $pk['n'] ?> orang</small></div>
                        <div class="blok-bar"><span class="aksen" style="width:<?= $pk['n'] / $maxP * 100 ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$pekerjaan): ?><p class="text-muted small mb-0">Belum ada data.</p><?php endif; ?>
            </div>
        </div>
        <div class="card kartu-info">
            <div class="card-body">
                <i class="bi bi-shield-check"></i>
                <p class="mb-0">NIK wajib <b>16 digit angka</b> dan tidak boleh sama dengan konsumen lain.</p>
            </div>
        </div>
    </aside>
</div>

<?php else: ?>
<!-- ============ DAFTAR (kiri) + PROFIL (kanan) ============ -->
<div class="kp-split">
    <aside class="card kp-daftar">
        <form class="cari-kapsul" method="get">
            <i class="bi bi-search"></i>
            <?php if ($f_st): ?><input type="hidden" name="st" value="<?= e($f_st) ?>"><?php endif; ?>
            <input type="text" name="cari" class="form-control" placeholder="Cari nama / NIK" value="<?= e($cari) ?>">
        </form>
        <div class="kp-chip"><?php chip_saring(['' => 'Semua', 'Prospek' => 'Prospek', 'Mencicil' => 'Mencicil', 'Lunas' => 'Lunas'], $f_st, $linkSt); ?></div>
        <div class="kp-list">
            <?php if (!$kartuKonsumen): ?><p class="text-muted small p-3 mb-0">Tidak ada konsumen yang cocok.</p><?php endif; ?>
            <?php foreach ($kartuKonsumen as $k):
                $st = $statusK($k['id_konsumen']);
                $wSt = ['Mencicil' => 'kuning', 'Lunas' => 'hijau', 'Prospek' => 'abu'][$st]; ?>
                <button type="button" class="kp-item <?= $k['id_konsumen'] == $pilihK ? 'aktif' : '' ?>" data-pilih="<?= $k['id_konsumen'] ?>">
                    <span class="kp-av" style="background: <?= $warnaAv[$k['id_konsumen'] % count($warnaAv)] ?>"><?= e(mb_strtoupper(mb_substr($k['nama'], 0, 1))) ?></span>
                    <span class="kp-nm"><b><?= e($k['nama']) ?></b><small><?= e($k['pekerjaan'] ?: $k['no_hp']) ?></small></span>
                    <span class="kk-status st-<?= $wSt ?>"><?= $st ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="kp-profil-wadah">
        <?php foreach ($kartuKonsumen as $k):
            $st = $statusK($k['id_konsumen']);
            $wSt = ['Mencicil' => 'kuning', 'Lunas' => 'hijau', 'Prospek' => 'abu'][$st];
            $riw = $riwayatK[$k['id_konsumen']] ?? [];
            $pesan = 'Halo Bapak/Ibu ' . $k['nama'] . ', kami dari Dory Hill.'; ?>
            <section class="card kp-profil <?= $k['id_konsumen'] == $pilihK ? 'tampil' : '' ?>" data-profil="<?= $k['id_konsumen'] ?>">
                <div class="kp-banner"></div>
                <div class="kp-atas">
                    <span class="kp-av besar" style="background: <?= $warnaAv[$k['id_konsumen'] % count($warnaAv)] ?>"><?= e(mb_strtoupper(mb_substr($k['nama'], 0, 1))) ?></span>
                    <div class="kp-judul">
                        <h4><?= e($k['nama']) ?></h4>
                        <span><?= e($k['pekerjaan'] ?: '—') ?> · <?= e($k['alamat'] ?: '—') ?></span>
                    </div>
                    <div class="kp-tombol">
                        <a href="https://wa.me/<?= nomor_wa_id($k['no_hp']) ?>?text=<?= rawurlencode($pesan) ?>" target="_blank" class="kk-wa"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                        <a href="konsumen.php?aksi=edit&id=<?= $k['id_konsumen'] ?>" class="kk-ikon" title="Edit" data-buka-form data-judul="Edit <?= e($k['nama']) ?>"
                           data-isi="<?= e(json_encode(['id_konsumen' => $k['id_konsumen'], 'nik' => $k['nik'], 'nama' => $k['nama'], 'no_hp' => $k['no_hp'], 'alamat' => $k['alamat'], 'pekerjaan' => $k['pekerjaan'], 'pasangan_pilih' => $k['id_pasangan'] ?: ''])) ?>"><i class="bi bi-pencil"></i></a>
                        <a href="#" class="kk-ikon" title="Hapus" onclick="return kirimPost('konsumen.php?aksi=hapus&id=<?= $k['id_konsumen'] ?>', 'Hapus konsumen ini?')"><i class="bi bi-trash"></i></a>
                    </div>
                </div>
                <div class="kp-info">
                    <div><small>NIK</small><b><?= e($k['nik']) ?></b></div>
                    <div><small>No. HP</small><b><?= e($k['no_hp']) ?></b></div>
                    <?php if ($k['nama_pasangan']): ?>
                    <div><small>Pasangan Pemohon</small><b><?= e($k['nama_pasangan']) ?></b></div>
                    <?php endif; ?>
                    <div><small>Status</small><b class="st-teks-<?= $wSt ?>"><?= $st ?></b></div>
                </div>
                <div class="kp-riwayat">
                    <h6>Riwayat Booking (<?= count($riw) ?>)</h6>
                    <?php if (!$riw): ?>
                        <div class="kp-kosong">
                            <span>Belum pernah booking unit.</span>
                            <a href="booking.php?aksi=tambah" class="btn btn-sm btn-accent" data-judul="Booking Unit"><i class="bi bi-plus-lg"></i> Buat Booking</a>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($riw as $r):
                        $pr = $r['harga_jual'] > 0 ? min(100, round($r['terbayar'] / $r['harga_jual'] * 100)) : 0;
                        $wr = ['Aktif' => '#f59e0b', 'Lunas' => '#10b981', 'Batal' => '#9ca3af'][$r['status']]; ?>
                        <a href="kwitansi.php?id=<?= $r['id_booking'] ?>" class="kp-rb" data-judul="Kwitansi">
                            <b class="kp-rb-kode"><?= e($r['kode_booking']) ?></b>
                            <span class="kp-rb-unit">Unit <?= e($r['blok'] . '-' . $r['nomor']) ?> · <?= e($r['nama_tipe']) ?></span>
                            <span class="kp-rb-bar"><i style="width: <?= $pr ?>%; background: <?= $wr ?>"></i></span>
                            <b class="kp-rb-pr"><?= $r['status'] === 'Aktif' ? $pr . '%' : $r['status'] ?></b>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <?php if (!$kartuKonsumen): ?><div class="card"><div class="card-body text-center text-muted py-5">Pilih konsumen di daftar.</div></div><?php endif; ?>
    </div>
</div>
<script>
// pilih konsumen di daftar -> tampilkan profilnya (tanpa memuat ulang halaman)
document.querySelectorAll('[data-pilih]').forEach(function (b) {
    b.addEventListener('click', function () {
        var id = b.getAttribute('data-pilih');
        document.querySelectorAll('.kp-item').forEach(function (x) { x.classList.toggle('aktif', x === b); });
        document.querySelectorAll('.kp-profil').forEach(function (p) { p.classList.toggle('tampil', p.getAttribute('data-profil') === id); });
        if (window.innerWidth < 992) document.querySelector('.kp-profil.tampil').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
</script>
<?php endif; ?>

<?php form_pop_awal('Tambah Konsumen', 'bi-person-plus', $formTerbuka,
    $aksi === 'edit' ? 'Edit ' . $data['nama'] : null, $aksi === 'edit' ? 'Ubah Data' : 'Entri Baru'); ?>
    <form method="post" action="konsumen.php">
        <input type="hidden" name="id_konsumen" value="<?= e($data['id_konsumen']) ?>">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" value="<?= e($data['nama']) ?>" required>
            </div>
            <div class="col-7">
                <label class="form-label">NIK</label>
                <input type="text" name="nik" class="form-control" maxlength="16" placeholder="16 digit" value="<?= e($data['nik']) ?>" required>
            </div>
            <div class="col-5">
                <label class="form-label">No. HP</label>
                <input type="text" name="no_hp" class="form-control" value="<?= e($data['no_hp']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Alamat</label>
                <textarea name="alamat" class="form-control" rows="2"><?= e($data['alamat']) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Pekerjaan</label>
                <input type="text" name="pekerjaan" class="form-control" value="<?= e($data['pekerjaan']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Pasangan Pemohon <small class="text-muted">(opsional)</small></label>
                <select name="pasangan_pilih" id="pasanganPilih" class="form-select" data-bawaan="">
                    <option value="">Tanpa pasangan (diajukan sendiri)</option>
                    <option value="baru">+ Tambah pasangan baru</option>
                    <?php foreach ($pilihanPasangan as $kp): ?>
                        <option value="<?= $kp['id_konsumen'] ?>" data-pasangan="<?= $kp['id_pasangan'] ?: '' ?>"
                            <?= $kp['id_konsumen'] == ($data['id_pasangan'] ?? 0) ? 'selected' : '' ?>><?= e($kp['nama']) ?> (<?= e($kp['nik']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="row g-3 mt-1 d-none" id="isiPasangan">
                    <div class="col-12">
                        <label class="form-label">Nama Pasangan Pemohon</label>
                        <input type="text" name="pasangan_nama" class="form-control">
                    </div>
                    <div class="col-7">
                        <label class="form-label">NIK Pasangan Pemohon</label>
                        <input type="text" name="pasangan_nik" class="form-control" maxlength="16" placeholder="16 digit">
                    </div>
                    <div class="col-5">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="pasangan_no_hp" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Pekerjaan Pasangan Pemohon</label>
                        <input type="text" name="pasangan_pekerjaan" class="form-control">
                        <small class="text-muted">Alamat pasangan pemohon mengikuti alamat pemohon.</small>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                const pilih = document.getElementById('pasanganPilih');
                const isi = document.getElementById('isiPasangan');
                function tampilIsi() {
                    const baru = pilih.value === 'baru';
                    isi.classList.toggle('d-none', !baru);
                    isi.querySelectorAll('input').forEach(i => i.required = baru && i.name !== 'pasangan_pekerjaan');
                }
                // yang tidak bisa dipilih: diri sendiri dan orang yang sudah berpasangan dengan konsumen lain
                function siapkan(idEdit) {
                    Array.from(pilih.options).forEach(function (o) {
                        if (!o.value || o.value === 'baru') return;
                        const dgnLain = o.dataset.pasangan && o.dataset.pasangan !== String(idEdit);
                        o.disabled = o.value === String(idEdit) || !!dgnLain;
                    });
                }
                pilih.addEventListener('change', tampilIsi);
                window.addEventListener('click', function (e) {
                    const t = e.target.closest ? e.target.closest('[data-buka-form]') : null;
                    if (!t) return;
                    let idEdit = 0;
                    try { idEdit = JSON.parse(t.getAttribute('data-isi') || '{}').id_konsumen || 0; } catch (x) {}
                    siapkan(idEdit);
                }, true);
                siapkan(<?= (int) $data['id_konsumen'] ?>);
                tampilIsi();
            })();
            </script>
        </div>
        <?php form_pop_tombol(); ?>
    </form>
<?php form_pop_akhir(); ?>

<?php include "inc/footer.php"; ?>