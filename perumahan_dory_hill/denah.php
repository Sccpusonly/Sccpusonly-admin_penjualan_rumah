<?php
require_once "inc/cek_login.php";
$judul  = "Denah Site Plan";
$kicker = "Data Master";

// Semua unit + info booking aktif & konsumennya
$unit = ambil_semua(
    "SELECT u.*, t.nama_tipe, t.harga, t.luas_bangunan, t.luas_tanah,
            b.kode_booking, b.status AS status_booking, k.nama AS konsumen
     FROM unit u
     JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     LEFT JOIN booking b ON b.id_unit = u.id_unit AND b.status = 'Aktif'
     LEFT JOIN konsumen k ON b.id_konsumen = k.id_konsumen
     ORDER BY u.blok, u.nomor"
);

// Kelompokkan per blok
$blok = [];
foreach ($unit as $u) {
    $blok[$u['blok']][] = $u;
}
ksort($blok);

$jumlah = ['Tersedia' => 0, 'Booking' => 0, 'Terjual' => 0];
foreach ($unit as $u) {
    if (isset($jumlah[$u['status']])) $jumlah[$u['status']]++;
}

$f_status = $_GET['status'] ?? '';
$f_tipe   = (int) ($_GET['tipe'] ?? 0);
$semuaTipe = ambil_semua("SELECT * FROM tipe_rumah ORDER BY nama_tipe");

include "inc/header.php";
?>
<div class="page-heading fade-in">
    <span class="kicker">Visualisasi Kawasan</span>
    <h3>Denah Site Plan</h3>
    <p>Lihat seluruh unit per blok dalam satu peta kawasan — warna menandai status, klik unit untuk detail & aksi cepat.</p>
    <div class="heading-rule"></div>
</div>

<div class="denah-toolbar fade-in-1">
    <div class="legend">
        <span class="legend-item"><span class="swatch tersedia"></span> Tersedia (<?= $jumlah['Tersedia'] ?>)</span>
        <span class="legend-item"><span class="swatch booking"></span> Booking (<?= $jumlah['Booking'] ?>)</span>
        <span class="legend-item"><span class="swatch terjual"></span> Terjual (<?= $jumlah['Terjual'] ?>)</span>
    </div>
    <div class="tt-spacer" style="flex:1"></div>
    <form class="d-flex gap-2" method="get">
        <select name="tipe" class="form-select form-select-sm">
            <option value="0">Semua tipe</option>
            <?php foreach ($semuaTipe as $t): ?>
                <option value="<?= $t['id_tipe'] ?>" <?= $f_tipe == $t['id_tipe'] ? 'selected' : '' ?>><?= e($t['nama_tipe']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select form-select-sm">
            <option value="">Semua status</option>
            <?php foreach (['Tersedia', 'Booking', 'Terjual'] as $s): ?>
                <option <?= $f_status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-ghost"><i class="bi bi-funnel"></i></button>
    </form>
</div>

<?php if (!$blok): ?>
    <div class="card fade-in-2"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-map" style="font-size:2.5rem;opacity:.4"></i>
        <p class="mt-3 mb-0">Belum ada data unit. Tambahkan unit dulu di menu <a href="unit.php">Unit / Kavling</a>.</p>
    </div></div>
<?php endif; ?>

<?php $n = 0; foreach ($blok as $nama_blok => $daftar): 
    // terapkan filter
    $daftar = array_values(array_filter($daftar, function ($u) use ($f_status, $f_tipe) {
        if ($f_status !== '' && $u['status'] !== $f_status) return false;
        if ($f_tipe && $u['id_tipe'] != $f_tipe) return false;
        return true;
    }));
    if (!$daftar) continue;
    $n++;
    $kosong = count(array_filter($daftar, fn($u) => $u['status'] === 'Tersedia'));
?>
<div class="blok-group fade-in <?= $n <= 4 ? 'fade-in-' . $n : '' ?>">
    <div class="blok-head">
        <span class="blok-name"><i class="bi bi-signpost-split"></i> Blok <?= e($nama_blok) ?></span>
        <span class="blok-meta"><?= count($daftar) ?> unit &middot; <?= $kosong ?> tersedia</span>
    </div>
    <div class="denah-grid">
        <?php foreach ($daftar as $u):
            $kelas = ['Tersedia' => 's-tersedia', 'Booking' => 's-booking', 'Terjual' => 's-terjual'][$u['status']];
            $tipeKelas = $f_status !== '' && $u['status'] !== $f_status ? 'd-none' : ''; ?>
            <button type="button"
                    class="unit-tile <?= $kelas ?> <?= $tipeKelas ?>"
                    data-id="<?= $u['id_unit'] ?>"
                    data-kode="<?= e("{$u['blok']}-{$u['nomor']}") ?>"
                    data-tipe="<?= e($u['nama_tipe']) ?>"
                    data-lb="<?= $u['luas_bangunan'] ?>"
                    data-lt="<?= $u['luas_tanah'] ?>"
                    data-harga="<?= e(rupiah($u['harga'])) ?>"
                    data-status="<?= e($u['status']) ?>"
                    data-konsumen="<?= e($u['konsumen'] ?? '-') ?>"
                    data-booking="<?= e($u['kode_booking'] ?? '-') ?>">
                <span class="u-status"></span>
                <div class="u-code"><?= e($u['blok']) ?> <span>·</span> <?= e($u['nomor']) ?></div>
                <div class="u-tipe"><?= e($u['nama_tipe']) ?> · <?= $u['luas_bangunan'] ?>/<?= $u['luas_tanah'] ?> m²</div>
                <div class="u-harga"><?= e(rupiah($u['harga'])) ?></div>
                <div class="u-konsumen">
                    <?php if ($u['status'] === 'Tersedia'): ?>
                        <span class="status-dot st-ok">Siap dijual</span>
                    <?php else: ?>
                        <?= e($u['konsumen'] ?? $u['status']) ?>
                    <?php endif; ?>
                </div>
            </button>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- ============ MODAL DETAIL UNIT ============ -->
<div class="modal fade" id="modalUnit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="card-kicker">Detail Unit</span>
                    <h5 class="modal-title" id="mKode">Blok A-01</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td width="120" class="text-muted">Tipe</td><td id="mTipe"></td></tr>
                    <tr><td class="text-muted">Luas</td><td id="mLuas"></td></tr>
                    <tr><td class="text-muted">Harga</td><td class="money" id="mHarga"></td></tr>
                    <tr><td class="text-muted">Status</td><td id="mStatus"></td></tr>
                    <tr><td class="text-muted">Konsumen</td><td id="mKonsumen"></td></tr>
                    <tr><td class="text-muted">Booking</td><td id="mBooking"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <a href="unit.php" class="btn btn-sm btn-ghost" id="mEdit"><i class="bi bi-pencil"></i> Edit unit</a>
                <a href="booking.php?aksi=tambah" class="btn btn-sm btn-accent" id="mBooking2"><i class="bi bi-journal-plus"></i> Booking unit ini</a>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.unit-tile').forEach(function (t) {
    t.addEventListener('click', function () {
        var d = t.dataset;
        document.getElementById('mKode').textContent = 'Blok ' + d.kode;
        document.getElementById('mTipe').textContent = d.tipe;
        document.getElementById('mLuas').textContent = 'LB ' + d.lb + ' / LT ' + d.lt + ' m²';
        document.getElementById('mHarga').textContent = d.harga;

        var st = d.status, kelas = st === 'Tersedia' ? 'st-ok' : (st === 'Booking' ? 'st-warn' : 'st-info');
        document.getElementById('mStatus').innerHTML = '<span class="status-dot ' + kelas + '">' + st + '</span>';
        document.getElementById('mKonsumen').textContent = d.konsumen;
        document.getElementById('mBooking').textContent = d.booking;

        document.getElementById('mEdit').href = 'unit.php?aksi=edit&id=' + d.id;
        var tombol = document.getElementById('mBooking2');
        if (st === 'Tersedia') {
            tombol.style.display = '';
            tombol.href = 'booking.php?aksi=tambah&unit=' + d.id;
        } else {
            tombol.style.display = 'none';
        }

        new bootstrap.Modal(document.getElementById('modalUnit')).show();
    });
});

// Buka otomatis kalau ada ?unit= (hasil pencarian global)
(function () {
    var p = new URLSearchParams(location.search).get('unit');
    if (!p) return;
    var tile = document.querySelector('.unit-tile[data-id="' + p + '"]');
    if (tile) setTimeout(function () { tile.click(); }, 350);
})();
</script>
<?php include "inc/footer.php"; ?>
