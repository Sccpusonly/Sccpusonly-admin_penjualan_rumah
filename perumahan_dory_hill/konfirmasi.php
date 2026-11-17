<?php
// Halaman ini tidak dipakai lagi: pesan berhasil/gagal sekarang tampil
// sebagai pop-up (lihat inc/header.php). File dibiarkan supaya link lama
// tetap aman, dan langsung diarahkan ke halaman tujuan.
require_once "config/koneksi.php";

$tujuan = $_SESSION['konfirmasi']['kembali'] ?? 'index.php';
header("Location: $tujuan");
exit;