<?php
date_default_timezone_set('Asia/Jakarta');

// Di hosting (bukan localhost), pesan error PHP tidak ditampilkan ke pengunjung.
$di_localhost = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1', '::1'], true);
ini_set('display_errors', $di_localhost ? '1' : '0');

// Website ini khusus admin: minta mesin pencari tidak mengindeksnya.
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
}

// ===== PENGATURAN DATABASE =====
// XAMPP (localhost): biarkan seperti ini.
// HOSTING: ganti keempat nilai di bawah dengan data dari panel hosting
// ($host BUKAN "localhost", biasanya sqlXXX.infinityfree.com).
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_perumahan";

// Supaya error MySQL jadi exception yang bisa ditangkap try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Token CSRF: dipasang otomatis ke semua form POST (lihat inc/footer.php)
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// ================== FUNGSI BANTUAN ==================

// Mengamankan teks sebelum ditampilkan di HTML
function e($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// Format angka ke Rupiah, contoh: Rp 350.000.000
function rupiah($angka)
{
    return "Rp " . number_format((float) $angka, 0, ',', '.');
}

// Ubah isian angka berformat ("5.000.000" / "Rp 5.000.000") jadi bilangan bulat 5000000
function angka($nilai)
{
    return (int) preg_replace('/\D+/', '', (string) $nilai);
}

// Tanggal valid (Y-m-d) dan belum lewat hari ini? (tanggal masa depan ditolak)
function tanggal_boleh($tanggal)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $tanggal);
    return $d && $d->format('Y-m-d') === $tanggal && $tanggal <= date('Y-m-d');
}

// Format tanggal: 2026-09-29 -> 29/09/2026
function tgl($tanggal)
{
    return date('d/m/Y', strtotime($tanggal));
}

// Pindah ke halaman konfirmasi (berhasil / gagal)
function konfirmasi($status, $pesan, $kembali)
{
    $_SESSION['konfirmasi'] = [
        'status'  => $status,   // 'berhasil' atau 'gagal'
        'pesan'   => $pesan,
        'kembali' => $kembali,  // halaman tujuan setelah aksi
    ];
    // Langsung ke halaman tujuan; pesan tampil sebagai pop-up di sana
    // (pop-up dibuat di inc/header.php)
    header("Location: $kembali");
    exit;
}

// Menjalankan query dengan prepared statement (aman dari SQL Injection)
function query($sql, $tipe = "", $params = [])
{
    global $koneksi;
    $stmt = mysqli_prepare($koneksi, $sql);
    if ($tipe !== "") {
        mysqli_stmt_bind_param($stmt, $tipe, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

// Ambil banyak baris sebagai array
function ambil_semua($sql, $tipe = "", $params = [])
{
    $hasil = mysqli_stmt_get_result(query($sql, $tipe, $params));
    return mysqli_fetch_all($hasil, MYSQLI_ASSOC);
}

// Ambil satu baris
function ambil_satu($sql, $tipe = "", $params = [])
{
    $hasil = mysqli_stmt_get_result(query($sql, $tipe, $params));
    return mysqli_fetch_assoc($hasil);
}