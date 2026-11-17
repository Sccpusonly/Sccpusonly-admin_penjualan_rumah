<?php
require_once "inc/cek_login.php";
$judul = "Profil Saya";
$polos = true; // tanpa header atas & menu bawah
require_once "inc/ui.php";
pastikan_kolom_foto();

// ---------- GANTI FOTO PROFIL ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'foto') {
    $f = $_FILES['foto'] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        konfirmasi('gagal', 'Pilih foto dulu ya.', 'profil.php?buka=foto');
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > 2 * 1024 * 1024) {
        konfirmasi('gagal', 'Ukuran foto maksimal 2 MB.', 'profil.php?buka=foto');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        konfirmasi('gagal', 'Foto gagal diunggah, coba lagi.', 'profil.php?buka=foto');
    }
    // pastikan benar-benar gambar, lalu tentukan ekstensinya sendiri
    $info = @getimagesize($f['tmp_name']);
    $jenis = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($jenis[$info['mime']])) {
        konfirmasi('gagal', 'File harus berupa gambar JPG, PNG, atau WEBP.', 'profil.php?buka=foto');
    }
    $folder = __DIR__ . '/uploads/profil';
    if (!is_dir($folder)) mkdir($folder, 0755, true);
    $namaFile = 'uploads/profil/user' . $_SESSION['id_user'] . '_' . time() . '.' . $jenis[$info['mime']];
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/' . $namaFile)) {
        konfirmasi('gagal', 'Foto gagal disimpan. Cek folder uploads/profil.', 'profil.php?buka=foto');
    }
    $lama = foto_user($_SESSION['id_user']);
    if ($lama && strpos($lama, 'uploads/profil/') === 0) @unlink(__DIR__ . '/' . $lama);
    query("UPDATE users SET foto = ? WHERE id_user = ?", "si", [$namaFile, $_SESSION['id_user']]);
    konfirmasi('berhasil', 'Foto profil berhasil diganti.', 'profil.php');
}

// ---------- HAPUS FOTO PROFIL ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus_foto') {
    $lama = foto_user($_SESSION['id_user']);
    if ($lama && strpos($lama, 'uploads/profil/') === 0) @unlink(__DIR__ . '/' . $lama);
    query("UPDATE users SET foto = NULL WHERE id_user = ?", "i", [$_SESSION['id_user']]);
    konfirmasi('berhasil', 'Foto profil dihapus.', 'profil.php');
}

// ---------- SIMPAN NAMA ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'profil') {
    $nama = trim($_POST['nama'] ?? '');
    if ($nama === '') {
        konfirmasi('gagal', 'Nama tidak boleh kosong.', 'profil.php?buka=nama');
    }
    query("UPDATE users SET nama = ? WHERE id_user = ?", "si", [$nama, $_SESSION['id_user']]);
    $_SESSION['nama'] = $nama;
    konfirmasi('berhasil', 'Nama profil berhasil diperbarui.', 'profil.php');
}

// ---------- GANTI PASSWORD ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'password') {
    $lama  = $_POST['lama'] ?? '';
    $baru  = $_POST['baru'] ?? '';
    $ulang = $_POST['ulang'] ?? '';

    $user = ambil_satu("SELECT * FROM users WHERE id_user = ?", "i", [$_SESSION['id_user']]);

    if (!$user || !password_verify($lama, $user['password'])) {
        konfirmasi('gagal', 'Password lama tidak sesuai.', 'profil.php?buka=sandi');
    }
    if (strlen($baru) < 6) {
        konfirmasi('gagal', 'Password baru minimal 6 karakter.', 'profil.php?buka=sandi');
    }
    if ($baru !== $ulang) {
        konfirmasi('gagal', 'Konfirmasi password baru tidak sama.', 'profil.php?buka=sandi');
    }

    query("UPDATE users SET password = ? WHERE id_user = ?", "si", [password_hash($baru, PASSWORD_DEFAULT), $_SESSION['id_user']]);
    konfirmasi('berhasil', 'Password berhasil diganti. Gunakan password baru saat login berikutnya.', 'profil.php');
}

$user = ambil_satu("SELECT * FROM users WHERE id_user = ?", "i", [$_SESSION['id_user']]);

$totalBooking = ambil_satu("SELECT COUNT(*) AS n FROM booking")['n'];
$totalBayar   = ambil_satu("SELECT COALESCE(SUM(jumlah),0) AS n FROM pembayaran")['n'];
$totalUnit    = ambil_satu("SELECT COUNT(*) AS n FROM unit WHERE status = 'Terjual'")['n'];

$inisial = mb_strtoupper(mb_substr(trim($user['nama']), 0, 1));
$foto = foto_user($_SESSION['id_user']);
$bukaAwal = $_GET['buka'] ?? '';

// Rp 955.000.000 -> "955 jt"
$fmtJt = $totalBayar >= 1000000000
    ? rtrim(rtrim(number_format($totalBayar / 1000000000, 2, ',', '.'), '0'), ',') . ' M'
    : number_format($totalBayar / 1000000, 0, ',', '.') . ' jt';

include "inc/header.php";
?>
<?php $tabAwal = $bukaAwal === 'sandi' ? 'keamanan' : 'akun'; ?>
<div class="ps">
    <!-- ===== Kiri: identitas + menu ===== -->
    <aside class="ps-kiri">
        <a href="index.php" class="ps-kembali" data-judul="Beranda"><i class="bi bi-arrow-left"></i> Kembali ke Beranda</a>
        <nav class="ps-menu" id="psMenu">
            <button type="button" data-tab="akun" class="<?= $tabAwal === 'akun' ? 'aktif' : '' ?>"><i class="bi bi-person"></i> Akun <span><i class="bi bi-chevron-right"></i></span></button>
            <button type="button" data-tab="keamanan" class="<?= $tabAwal === 'keamanan' ? 'aktif' : '' ?>"><i class="bi bi-shield-lock"></i> Keamanan <span><i class="bi bi-chevron-right"></i></span></button>
            <button type="button" data-tab="tampilan"><i class="bi bi-circle-half"></i> Tampilan <span><i class="bi bi-chevron-right"></i></span></button>
        </nav>
        <a href="logout.php" class="ps-keluar" data-confirm="Yakin mau logout?"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </aside>

    <!-- ===== Kanan: isi tab ===== -->
    <main class="ps-kanan">
        <!-- Akun -->
        <section class="ps-tab" data-isi="akun" <?= $tabAwal !== 'akun' ? 'hidden' : '' ?>>
            <h1>Akun</h1>
            <p class="ps-sub">Identitas yang tampil di sistem.</p>

            <div class="ps-profil">
                <button type="button" class="ps-foto" data-buka-pop="popFoto" title="Ganti foto profil">
                    <?= avatar($foto, $inisial, 'ps-avatar besar') ?>
                    <span class="ps-kamera"><i class="bi bi-camera-fill"></i></span>
                </button>
                <b><?= e($user['nama']) ?></b>
                <small>@<?= e($user['username']) ?></small>
            </div>

            <span class="ps-grup-judul">Informasi</span>
            <div class="ps-grup">
                <button type="button" class="ps-baris" data-buka-pop="popNama">
                    <span class="ps-label">Nama tampilan</span>
                    <span class="ps-nilai"><?= e($user['nama']) ?></span>
                    <i class="bi bi-chevron-right"></i>
                </button>
                <div class="ps-baris">
                    <span class="ps-label">Username</span>
                    <span class="ps-nilai"><?= e($user['username']) ?></span>
                    <i class="bi bi-lock"></i>
                </div>
                <div class="ps-baris">
                    <span class="ps-label">Level</span>
                    <span class="ps-nilai"><?= e(ucfirst($user['level'])) ?></span>
                </div>
            </div>

            <span class="ps-grup-judul">Ringkasan kerja</span>
            <div class="ps-grup">
                <div class="ps-baris"><span class="ps-label">Booking tercatat</span><span class="ps-nilai"><?= $totalBooking ?></span></div>
                <div class="ps-baris"><span class="ps-label">Unit terjual</span><span class="ps-nilai"><?= $totalUnit ?></span></div>
                <div class="ps-baris"><span class="ps-label">Total pemasukan</span><span class="ps-nilai"><?= rupiah($totalBayar) ?></span></div>
            </div>
        </section>

        <!-- Keamanan -->
        <section class="ps-tab" data-isi="keamanan" <?= $tabAwal !== 'keamanan' ? 'hidden' : '' ?>>
            <h1>Keamanan</h1>
            <p class="ps-sub">Jaga akunmu tetap aman.</p>

            <span class="ps-grup-judul">Login</span>
            <div class="ps-grup">
                <button type="button" class="ps-baris" data-buka-pop="popSandi">
                    <span class="ps-ikon"><i class="bi bi-key"></i></span>
                    <span class="ps-label">Password</span>
                    <span class="ps-nilai">Ganti</span>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            <p class="ps-catatan">Gunakan minimal 6 karakter dan jangan bagikan password ke orang lain.</p>
        </section>

        <!-- Tampilan -->
        <section class="ps-tab" data-isi="tampilan" hidden>
            <h1>Tampilan</h1>
            <p class="ps-sub">Atur tampilan aplikasi sesuai selera.</p>

            <span class="ps-grup-judul">Tema</span>
            <div class="ps-tema">
                <button type="button" data-tema="light"><span class="pt-contoh terang"><i></i><i></i></span>Terang</button>
                <button type="button" data-tema="dark"><span class="pt-contoh gelap"><i></i><i></i></span>Gelap</button>
            </div>
        </section>
    </main>
</div>

<!-- tombol tema tersembunyi: dipakai app.js untuk menyimpan tema -->
<button id="themeToggle" type="button" hidden></button>

<!-- ===== Pop-up: ganti foto ===== -->
<div class="form-pop" id="popFoto" role="dialog" aria-modal="true">
    <div class="fp-kartu">
        <div class="fp-kepala">
            <span class="fp-ikon"><i class="bi bi-camera"></i></span>
            <div><span class="card-kicker d-block">Profil</span><h4>Foto Profil</h4></div>
            <button type="button" class="fp-tutup" data-tutup-pop aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="post" action="profil.php" enctype="multipart/form-data">
            <input type="hidden" name="aksi" value="foto">
            <label class="ps-unggah" for="inputFoto">
                <span class="ps-pratinjau <?= $foto ? 'ada-foto' : '' ?>" id="pratinjauFoto"
                      <?= $foto ? 'style="background-image:url(\'' . e($foto) . '\')"' : '' ?>><?= $foto ? '' : e($inisial) ?></span>
                <b id="namaFoto">Klik untuk pilih foto</b>
                <small>JPG, PNG, atau WEBP &middot; maks. 2 MB</small>
            </label>
            <input type="file" name="foto" id="inputFoto" accept="image/jpeg,image/png,image/webp" hidden>
            <div class="fp-aksi">
                <?php if ($foto): ?>
                    <button type="submit" form="formHapusFoto" class="btn btn-ghost me-auto text-danger"><i class="bi bi-trash"></i> Hapus</button>
                <?php endif; ?>
                <button type="button" class="btn btn-ghost" data-tutup-pop>Batal</button>
                <button class="btn btn-accent"><i class="bi bi-check2"></i> Simpan</button>
            </div>
        </form>
        <form method="post" action="profil.php" id="formHapusFoto">
            <input type="hidden" name="aksi" value="hapus_foto">
        </form>
    </div>
</div>

<!-- ===== Pop-up: ubah nama ===== -->
<div class="form-pop" id="popNama" role="dialog" aria-modal="true">
    <div class="fp-kartu">
        <div class="fp-kepala">
            <span class="fp-ikon"><i class="bi bi-person"></i></span>
            <div><span class="card-kicker d-block">Profil</span><h4>Ubah Nama</h4></div>
            <button type="button" class="fp-tutup" data-tutup-pop aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="post" action="profil.php">
            <input type="hidden" name="aksi" value="profil">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" value="<?= e($user['nama']) ?>" required>
            <div class="fp-aksi">
                <button type="button" class="btn btn-ghost" data-tutup-pop>Batal</button>
                <button class="btn btn-accent"><i class="bi bi-check2"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Pop-up: ganti password ===== -->
<div class="form-pop" id="popSandi" role="dialog" aria-modal="true">
    <div class="fp-kartu">
        <div class="fp-kepala">
            <span class="fp-ikon"><i class="bi bi-shield-lock"></i></span>
            <div><span class="card-kicker d-block">Keamanan</span><h4>Ganti Password</h4></div>
            <button type="button" class="fp-tutup" data-tutup-pop aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="post" action="profil.php">
            <input type="hidden" name="aksi" value="password">
            <label class="form-label">Password Lama</label>
            <div class="auth-input">
                <i class="bi bi-lock"></i>
                <input type="password" name="lama" id="pwLama" class="form-control" required>
                <i class="bi bi-eye" data-pw-toggle="pwLama"></i>
            </div>
            <label class="form-label">Password Baru <small class="text-muted">(min. 6 karakter)</small></label>
            <div class="auth-input">
                <i class="bi bi-shield-lock"></i>
                <input type="password" name="baru" id="pwBaru" class="form-control" minlength="6" required>
                <i class="bi bi-eye" data-pw-toggle="pwBaru"></i>
            </div>
            <label class="form-label">Ulangi Password Baru</label>
            <div class="auth-input mb-0">
                <i class="bi bi-shield-check"></i>
                <input type="password" name="ulang" id="pwUlang" class="form-control" minlength="6" required>
                <i class="bi bi-eye" data-pw-toggle="pwUlang"></i>
            </div>
            <div class="fp-aksi">
                <button type="button" class="btn btn-ghost" data-tutup-pop>Batal</button>
                <button class="btn btn-accent"><i class="bi bi-key"></i> Ganti Password</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // ----- pop-up -----
    function buka(id) {
        var p = document.getElementById(id);
        p.classList.remove('keluar'); p.classList.add('tampil');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { var i = p.querySelector('input:not([type=hidden])'); if (i) i.focus(); }, 260);
    }
    function tutup(p) {
        if (!p.classList.contains('tampil')) return;
        p.classList.add('keluar');
        document.body.style.overflow = '';
        setTimeout(function () { p.classList.remove('tampil', 'keluar'); }, 280);
    }
    document.querySelectorAll('[data-buka-pop]').forEach(function (b) {
        b.addEventListener('click', function () { buka(b.getAttribute('data-buka-pop')); });
    });
    document.querySelectorAll('.form-pop').forEach(function (p) {
        p.addEventListener('click', function (e) {
            if (e.target === p || e.target.closest('[data-tutup-pop]')) tutup(p);
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.querySelectorAll('.form-pop.tampil').forEach(tutup);
    });
    // kembali dari gagal simpan -> buka lagi pop-up-nya
    var awal = <?= json_encode($bukaAwal) ?>;
    if (awal === 'nama') buka('popNama');
    if (awal === 'sandi') buka('popSandi');
    if (awal === 'foto') buka('popFoto');

    // ----- pratinjau foto sebelum disimpan -----
    var inputFoto = document.getElementById('inputFoto');
    inputFoto.addEventListener('change', function () {
        var file = inputFoto.files[0];
        if (!file) return;
        var pv = document.getElementById('pratinjauFoto');
        var baca = new FileReader();
        baca.onload = function () {
            pv.style.backgroundImage = 'url(' + baca.result + ')';
            pv.classList.add('ada-foto');
            pv.textContent = '';
        };
        baca.readAsDataURL(file);
        document.getElementById('namaFoto').textContent = file.name;
    });

    // ----- tab menu kiri -----
    document.querySelectorAll('#psMenu [data-tab]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#psMenu [data-tab]').forEach(function (x) { x.classList.toggle('aktif', x === b); });
            document.querySelectorAll('.ps-tab').forEach(function (t) { t.hidden = t.getAttribute('data-isi') !== b.getAttribute('data-tab'); });
        });
    });

    // ----- pilihan tema -----
    var asli = document.getElementById('themeToggle');
    function segarkan() {
        var gelap = document.documentElement.getAttribute('data-theme') === 'dark';
        document.querySelectorAll('[data-tema]').forEach(function (b) { b.classList.toggle('aktif', (b.getAttribute('data-tema') === 'dark') === gelap); });
    }
    document.querySelectorAll('[data-tema]').forEach(function (b) {
        b.addEventListener('click', function () {
            var gelap = document.documentElement.getAttribute('data-theme') === 'dark';
            if ((b.getAttribute('data-tema') === 'dark') !== gelap) asli.click();
            segarkan();
        });
    });
    segarkan();
})();
</script>
<?php include "inc/footer.php"; ?>