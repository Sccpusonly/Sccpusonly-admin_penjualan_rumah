<?php
require_once "inc/cek_login.php";
$judul = "Tagihan Belum Lunas";

// Batas hari sejak pembayaran terakhir sebelum dianggap perlu ditagih
$BATAS_HARI = 30;

$daftar = ambil_semua(
    "SELECT b.id_booking, b.kode_booking, b.tanggal, b.cara_bayar, b.harga_jual,
            k.nama, k.no_hp, u.blok, u.nomor, t.nama_tipe,
            COALESCE(SUM(p.jumlah), 0) AS terbayar,
            MAX(p.tanggal) AS bayar_terakhir
     FROM booking b
     JOIN konsumen k   ON b.id_konsumen = k.id_konsumen
     JOIN unit u       ON b.id_unit = u.id_unit
     JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     LEFT JOIN pembayaran p ON p.id_booking = b.id_booking
     WHERE b.status = 'Aktif'
     GROUP BY b.id_booking
     ORDER BY bayar_terakhir ASC"
);

// Hitung sisa & lama tidak bayar di PHP
$totalSisa = 0;
$perluDitagih = 0;
foreach ($daftar as &$d) {
    $d['sisa'] = $d['harga_jual'] - $d['terbayar'];
    $acuan     = $d['bayar_terakhir'] ?: $d['tanggal'];
    $d['hari'] = (int) floor((time() - strtotime($acuan)) / 86400);
    $d['persen'] = $d['harga_jual'] > 0 ? round($d['terbayar'] / $d['harga_jual'] * 100) : 0;

    $totalSisa += $d['sisa'];
    if ($d['hari'] > $BATAS_HARI) $perluDitagih++;
}
unset($d);

// Ubah 0812xxx menjadi 62812xxx untuk link WhatsApp
function nomor_wa($hp)
{
    $hp = preg_replace('/[^0-9]/', '', $hp);
    return substr($hp, 0, 1) === '0' ? '62' . substr($hp, 1) : $hp;
}

include "inc/header.php";
?>
<div class="mb-3">
    <h3 class="fw-bold mb-0">Tagihan Belum Lunas</h3>
    <small class="text-muted">Booking aktif yang masih punya sisa pembayaran</small>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small">Booking belum lunas</div>
            <div class="fs-3 fw-bold"><?= count($daftar) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small">Perlu ditagih (&gt; <?= $BATAS_HARI ?> hari tanpa bayar)</div>
            <div class="fs-3 fw-bold text-danger"><?= $perluDitagih ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small">Total sisa tagihan</div>
            <div class="fs-3 fw-bold"><?= rupiah($totalSisa) ?></div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-kicker d-block">Daftar</span>Konsumen dengan Sisa Tagihan</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr><th>Kode</th><th>Konsumen</th><th>Unit</th><th>Progres Bayar</th><th class="text-end">Sisa</th><th>Bayar Terakhir</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php if (!$daftar): ?>
                <tr class="empty-row"><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-emoji-smile"></i> Semua booking sudah lunas.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftar as $d):
                $telat = $d['hari'] > $BATAS_HARI;
                $pesan = "Halo Bapak/Ibu {$d['nama']}, kami dari SI Perumahan ingin mengingatkan pembayaran "
                       . "unit Blok {$d['blok']}-{$d['nomor']} (kode {$d['kode_booking']}). "
                       . "Sisa tagihan saat ini " . rupiah($d['sisa']) . ". Terima kasih.";
            ?>
                <tr>
                    <td><a href="kwitansi.php?id=<?= $d['id_booking'] ?>"><?= e($d['kode_booking']) ?></a></td>
                    <td><?= e($d['nama']) ?><br><small class="text-muted"><?= e($d['cara_bayar']) ?></small></td>
                    <td><?= e("{$d['blok']}-{$d['nomor']}") ?><br><small class="text-muted"><?= e($d['nama_tipe']) ?></small></td>
                    <td style="min-width:140px">
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-success" style="width:<?= $d['persen'] ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $d['persen'] ?>% dari <?= rupiah($d['harga_jual']) ?></small>
                    </td>
                    <td class="text-end fw-semibold"><?= rupiah($d['sisa']) ?></td>
                    <td>
                        <?= $d['bayar_terakhir'] ? tgl($d['bayar_terakhir']) : '-' ?><br>
                        <span class="badge bg-<?= $telat ? 'danger' : 'secondary' ?>"><?= $d['hari'] ?> hari lalu</span>
                    </td>
                    <td class="text-nowrap">
                        <a href="pembayaran.php?aksi=tambah&booking=<?= $d['id_booking'] ?>" class="btn btn-sm btn-success" title="Input pembayaran"><i class="bi bi-cash"></i></a>
                        <a href="https://wa.me/<?= nomor_wa($d['no_hp']) ?>?text=<?= rawurlencode($pesan) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Kirim pengingat WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include "inc/footer.php"; ?>