<?php
require_once "inc/cek_login.php";

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

$out = ['konsumen' => [], 'unit' => [], 'booking' => []];

if ($q !== '') {
    $like = "%$q%";

    foreach (ambil_semua(
        "SELECT id_konsumen, nama, nik FROM konsumen
         WHERE nama LIKE ? OR nik LIKE ? OR no_hp LIKE ? ORDER BY nama LIMIT 4",
        "sss", [$like, $like, $like]
    ) as $k) {
        $out['konsumen'][] = [
            'label' => $k['nama'],
            'sub'   => 'NIK ' . $k['nik'],
            'icon'  => 'bi-person',
            'url'   => 'konsumen.php?cari=' . urlencode($q),
        ];
    }

    foreach (ambil_semua(
        "SELECT u.id_unit, u.blok, u.nomor, u.status, t.nama_tipe, t.harga FROM unit u
         JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
         WHERE u.blok LIKE ? OR u.nomor LIKE ? OR t.nama_tipe LIKE ?
         ORDER BY u.blok, u.nomor LIMIT 4",
        "sss", [$like, $like, $like]
    ) as $u) {
        $out['unit'][] = [
            'label' => "Unit {$u['blok']}-{$u['nomor']} · {$u['nama_tipe']}",
            'sub'   => $u['status'] . ' · ' . rupiah($u['harga']),
            'icon'  => 'bi-houses',
            'url'   => 'denah.php?unit=' . $u['id_unit'],
        ];
    }

    foreach (ambil_semua(
        "SELECT b.id_booking, b.kode_booking, b.status, k.nama FROM booking b
         JOIN konsumen k ON b.id_konsumen = k.id_konsumen
         WHERE b.kode_booking LIKE ? OR EXISTS (
             SELECT 1 FROM booking_pemohon bp JOIN konsumen k2 ON bp.id_konsumen = k2.id_konsumen
             WHERE bp.id_booking = b.id_booking AND k2.nama LIKE ?)
         ORDER BY b.id_booking DESC LIMIT 4",
        "ss", [$like, $like]
    ) as $b) {
        $out['booking'][] = [
            'label' => $b['kode_booking'],
            'sub'   => $b['nama'] . ' · ' . $b['status'],
            'icon'  => 'bi-journal-check',
            'url'   => 'kwitansi.php?id=' . $b['id_booking'],
        ];
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);