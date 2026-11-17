<?php
require_once __DIR__ . "/../config/koneksi.php";

// Kalau belum login, lempar ke halaman login
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// Semua aksi yang mengubah data (POST) wajib membawa token CSRF yang benar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        konfirmasi('gagal', 'Sesi formulir tidak valid atau sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.', basename($_SERVER['PHP_SELF']));
    }
}
