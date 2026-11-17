<?php
require_once "config/koneksi.php";

// Kalau sudah login, langsung ke beranda
if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $user = ambil_satu("SELECT * FROM users WHERE username = ?", "s", [$username]);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['nama']    = $user['nama'];
        $_SESSION['level']   = $user['level'];
        konfirmasi('berhasil', "Login berhasil. Selamat datang, {$user['nama']}!", 'index.php');
    } else {
        usleep(700000); // perlambat tebakan password
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | SI Perumahan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('siperumahan-theme-v2');
                if (t !== 'light' && t !== 'dark') t = 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
</head>
<body>
<div class="auth-page">

    <!-- ================= PANEL KIRI ================= -->
    <aside class="auth-aside">
        <div class="auth-brand">
            <div class="brand-icon"><i class="bi bi-buildings-fill"></i></div>
            <div class="brand-text">SI Perumahan<small>Dory Hill</small></div>
        </div>

        <div class="auth-hero">
            <span class="kicker">Sistem Informasi Pemesanan</span>
            <h1>Kelola rumah impian dengan <em>tertata.</em></h1>
            <p>Platform internal untuk mencatat tipe rumah, unit &amp; kavling, konsumen, marketing, booking, hingga pembayaran sampai lunas — rapi, cepat, dan terukur.</p>
            <ul class="auth-points">
                <li><i class="bi bi-map"></i> Denah site plan interaktif per blok</li>
                <li><i class="bi bi-graph-up-arrow"></i> Laporan penjualan &amp; ekspor CSV</li>
                <li><i class="bi bi-shield-check"></i> Hak akses admin</li>
            </ul>
        </div>

        <div class="auth-foot">&copy; <?= date('Y') ?> Dory Hill</div>
    </aside>

    <!-- ================= FORM LOGIN ================= -->
    <div class="auth-form-wrap">
        <div class="auth-card fade-in">
            <div class="auth-brand-sm">
                <div class="brand-icon"><i class="bi bi-buildings-fill"></i></div>
                <div class="brand-text">SI Perumahan<small>Dory Hill</small></div>
            </div>

            <span class="kicker">Masuk ke akun</span>
            <h2>Selamat datang kembali</h2>
            <p class="sub">Silakan gunakan akun Anda untuk melanjutkan.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger auth-alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <label class="form-label">Username</label>
                <div class="auth-input">
                    <i class="bi bi-person"></i>
                    <input type="text" name="username" class="form-control" placeholder="nama pengguna" required autofocus>
                </div>

                <label class="form-label">Password</label>
                <div class="auth-input">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="password" id="loginPw" class="form-control" placeholder="••••••••" required>
                    <i class="bi bi-eye" data-pw-toggle="loginPw"></i>
                </div>

                <button type="submit" class="btn btn-login">
                    Masuk <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
