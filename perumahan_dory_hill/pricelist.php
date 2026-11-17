<?php
require_once "inc/cek_login.php";
$judul = "Price List";

// Asumsi estimasi cicilan yang ditampilkan di price list
$DP_PERSEN = 20;
$BUNGA     = 7.5;  // % per tahun
$TENOR     = 15;   // tahun
$namaPerumahan = 'Dory Hill';

$gaya = $_GET['gaya'] ?? 'a';
if (!in_array($gaya, ['a', 'b', 'c'], true)) $gaya = 'a';

// Tipe rumah + jumlah unit total / tersedia / terjual
$tipe = ambil_semua(
    "SELECT t.*,
            COUNT(u.id_unit) AS total,
            COALESCE(SUM(u.status = 'Tersedia'), 0) AS tersedia,
            COALESCE(SUM(u.status = 'Terjual'), 0) AS terjual
     FROM tipe_rumah t
     LEFT JOIN unit u ON u.id_tipe = t.id_tipe
     GROUP BY t.id_tipe
     ORDER BY t.harga"
);

// Unit yang masih tersedia, dikelompokkan per tipe
$unitTersedia = [];
foreach (ambil_semua("SELECT id_unit, id_tipe, blok, nomor FROM unit WHERE status = 'Tersedia' ORDER BY blok, nomor") as $u) {
    $unitTersedia[$u['id_tipe']][] = $u;
}

// Tipe paling laris (terjual terbanyak)
$terlaris = null; $maxJual = 0;
foreach ($tipe as $t) if ($t['terjual'] > $maxJual) { $maxJual = $t['terjual']; $terlaris = $t['id_tipe']; }

$totalTersedia = array_sum(array_column($tipe, 'tersedia'));
$hargaMulai    = $tipe ? min(array_column($tipe, 'harga')) : 0;

// Tanggal bahasa Indonesia
$bulanIndo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tglIndo = date('j') . ' ' . $bulanIndo[(int) date('n')] . ' ' . date('Y');

// Rp 350.000.000 -> "350" + "jt"  (dipakai untuk angka besar)
function harga_pendek($n)
{
    if ($n >= 1000000000) return [rtrim(rtrim(number_format($n / 1000000000, 2, ',', '.'), '0'), ','), 'M'];
    return [number_format($n / 1000000, 0, ',', '.'), 'jt'];
}

include "inc/header.php";
?>
<div class="hal-kepala no-print">
    <div class="page-heading mb-0">
        <span class="kicker">Alat Bantu</span>
        <h3>Price List</h3>
        <p>Daftar harga unit <?= e($namaPerumahan) ?>, siap dicetak untuk brosur.</p>
    </div>
    <div class="hal-aksi">
        <div class="pl-gaya">
            <a href="?gaya=a" class="<?= $gaya === 'a' ? 'aktif' : '' ?>">A · Brosur</a>
            <a href="?gaya=b" class="<?= $gaya === 'b' ? 'aktif' : '' ?>">B · Paket</a>
            <a href="?gaya=c" class="<?= $gaya === 'c' ? 'aktif' : '' ?>">C · Kalkulator</a>
        </div>
        <button onclick="window.print()" class="btn btn-accent"><i class="bi bi-printer"></i> Cetak</button>
    </div>
</div>

<?php if ($gaya === 'a'): ?>
<!-- ================= A. BROSUR ================= -->
<div class="pl-brosur">
    <div class="pl-sampul">
        <div>
            <small>PRICE LIST · <?= strtoupper($tglIndo) ?></small>
            <h2><?= e($namaPerumahan) ?></h2>
            <p>Hunian nyaman di Batam, siap huni &amp; bisa KPR.</p>
        </div>
        <div class="pl-sampul-angka">
            <div><small>Mulai dari</small><?php [$mA, $mS] = harga_pendek($hargaMulai); ?><b>Rp <?= $mA ?><em><?= $mS ?></em></b></div>
            <div><small>Unit tersedia</small><b><?= $totalTersedia ?><em>unit</em></b></div>
        </div>
    </div>

    <?php foreach ($tipe as $i => $t):
        $habis = $t['tersedia'] == 0;
        [$angka, $satuan] = harga_pendek($t['harga']); ?>
        <div class="pl-baris <?= $habis ? 'habis' : '' ?>">
            <div class="pl-no"><span><?= e(preg_replace('/\D+/', '', $t['nama_tipe']) ?: ($i + 1)) ?></span><small>tipe</small></div>
            <div class="pl-info">
                <h4><?= e($t['nama_tipe']) ?>
                    <?php if ($t['id_tipe'] == $terlaris): ?><span class="pl-label">Paling laris</span><?php endif; ?>
                    <?php if ($habis): ?><span class="pl-label abu">Sold out</span><?php endif; ?>
                </h4>
                <div class="pl-spek">
                    <span><i class="bi bi-house"></i> LB <?= $t['luas_bangunan'] ?> m²</span>
                    <span><i class="bi bi-bounding-box"></i> LT <?= $t['luas_tanah'] ?> m²</span>
                    <span><i class="bi bi-door-closed"></i> <?= $t['kamar_tidur'] ?> KT</span>
                </div>
                <div class="pl-unit">
                    <?php if ($habis): ?>
                        <em>Semua unit sudah terjual</em>
                    <?php else: foreach ($unitTersedia[$t['id_tipe']] ?? [] as $u): ?>
                        <a href="booking.php?aksi=tambah&unit=<?= $u['id_unit'] ?>"><?= e($u['blok'] . '-' . $u['nomor']) ?></a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="pl-harga">
                <b>Rp <?= $angka ?><em><?= $satuan ?></em></b>
                <small>Cicilan ± <?= rupiah(cicilan_kira($t['harga'], $DP_PERSEN, $BUNGA, $TENOR)) ?>/bln</small>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php elseif ($gaya === 'b'): ?>
<!-- ================= B. PAKET HARGA ================= -->
<div class="pl-paket">
    <?php foreach ($tipe as $t):
        $habis = $t['tersedia'] == 0;
        $unggul = $t['id_tipe'] == $terlaris;
        [$angka, $satuan] = harga_pendek($t['harga']); ?>
        <div class="pl-kartu <?= $unggul ? 'unggul' : '' ?> <?= $habis ? 'habis' : '' ?>">
            <?php if ($unggul): ?><span class="pl-pita">Paling laris</span><?php endif; ?>
            <small class="pl-kecil"><?= e($t['nama_tipe']) ?></small>
            <div class="pl-angka">Rp <?= $angka ?><em><?= $satuan ?></em></div>
            <div class="pl-cicil">± <?= rupiah(cicilan_kira($t['harga'], $DP_PERSEN, $BUNGA, $TENOR)) ?> / bulan</div>
            <ul>
                <li><i class="bi bi-check2"></i> Luas bangunan <?= $t['luas_bangunan'] ?> m²</li>
                <li><i class="bi bi-check2"></i> Luas tanah <?= $t['luas_tanah'] ?> m²</li>
                <li><i class="bi bi-check2"></i> <?= $t['kamar_tidur'] ?> kamar tidur</li>
                <li><i class="bi bi-check2"></i> DP mulai <?= rupiah($t['harga'] * $DP_PERSEN / 100) ?></li>
            </ul>
            <div class="pl-stok">
                <span><i style="width:<?= $t['total'] ? $t['tersedia'] / $t['total'] * 100 : 0 ?>%"></i></span>
                <small><?= $habis ? 'Sold out' : 'Sisa ' . $t['tersedia'] . ' dari ' . $t['total'] . ' unit' ?></small>
            </div>
            <?php if ($habis): ?>
                <span class="btn btn-light w-100 disabled no-print">Habis terjual</span>
            <?php else: ?>
                <a href="booking.php?aksi=tambah&unit=<?= $unitTersedia[$t['id_tipe']][0]['id_unit'] ?>" class="btn btn-accent w-100 no-print">Booking tipe ini</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- ================= C. KALKULATOR ================= -->
<div class="pl-kalk" id="plKalk" data-dp="<?= $DP_PERSEN ?>" data-bunga="<?= $BUNGA ?>" data-tenor="<?= $TENOR ?>">
    <div class="pl-tab">
        <?php foreach ($tipe as $i => $t): ?>
            <button type="button" class="<?= $i === 0 ? 'aktif' : '' ?>" data-tipe="<?= $i ?>">
                <b><?= e($t['nama_tipe']) ?></b>
                <small><?= $t['tersedia'] ? $t['tersedia'] . ' unit tersedia' : 'Sold out' ?></small>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="pl-panel">
        <div class="pl-kiri">
            <small class="pl-kecil" id="kNama"></small>
            <div class="pl-angka besar" id="kHarga"></div>
            <div class="pl-spek" id="kSpek"></div>
            <div class="pl-unit" id="kUnit"></div>
        </div>
        <div class="pl-kanan">
            <label>DP <b id="kDpTeks"></b></label>
            <input type="range" id="kDp" min="10" max="50" step="5" value="<?= $DP_PERSEN ?>">
            <label>Tenor <b id="kTenorTeks"></b></label>
            <input type="range" id="kTenor" min="5" max="25" step="5" value="<?= $TENOR ?>">
            <div class="pl-hasil">
                <small>Cicilan per bulan</small>
                <b id="kCicil"></b>
                <em id="kDpRp"></em>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var data = <?= json_encode(array_map(function ($t) use ($unitTersedia) {
        return [
            'nama' => $t['nama_tipe'], 'harga' => (float) $t['harga'],
            'lb' => $t['luas_bangunan'], 'lt' => $t['luas_tanah'], 'kt' => $t['kamar_tidur'],
            'unit' => array_map(fn($u) => ['id' => $u['id_unit'], 'k' => $u['blok'] . '-' . $u['nomor']], $unitTersedia[$t['id_tipe']] ?? []),
        ];
    }, $tipe)) ?>;
    var box = document.getElementById('plKalk'), bunga = parseFloat(box.dataset.bunga), pilih = 0;
    var $ = function (id) { return document.getElementById(id); };
    var rp = function (n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); };
    function hitung() {
        var t = data[pilih], dp = +$('kDp').value, tenor = +$('kTenor').value;
        var pokok = t.harga * (1 - dp / 100), r = bunga / 100 / 12, n = tenor * 12;
        $('kDpTeks').textContent = dp + '%';
        $('kTenorTeks').textContent = tenor + ' tahun';
        $('kCicil').textContent = rp(pokok * r / (1 - Math.pow(1 + r, -n)));
        $('kDpRp').textContent = 'DP ' + rp(t.harga * dp / 100) + ' · bunga ' + bunga + '%/thn';
    }
    function tampil() {
        var t = data[pilih];
        $('kNama').textContent = t.nama;
        $('kHarga').textContent = rp(t.harga);
        $('kSpek').innerHTML = '<span><i class="bi bi-house"></i> LB ' + t.lb + ' m²</span><span><i class="bi bi-bounding-box"></i> LT ' + t.lt + ' m²</span><span><i class="bi bi-door-closed"></i> ' + t.kt + ' KT</span>';
        $('kUnit').innerHTML = t.unit.length
            ? t.unit.map(function (u) { return '<a href="booking.php?aksi=tambah&unit=' + u.id + '">' + u.k + '</a>'; }).join('')
            : '<em>Semua unit sudah terjual</em>';
        hitung();
    }
    box.querySelectorAll('.pl-tab button').forEach(function (b) {
        b.addEventListener('click', function () {
            box.querySelector('.pl-tab .aktif').classList.remove('aktif');
            b.classList.add('aktif'); pilih = +b.dataset.tipe; tampil();
        });
    });
    $('kDp').addEventListener('input', hitung);
    $('kTenor').addEventListener('input', hitung);
    tampil();
})();
</script>
<?php endif; ?>

<p class="pl-catatan">
    * Estimasi cicilan KPR dengan DP <?= $DP_PERSEN ?>%, bunga <?= $BUNGA ?>%/tahun, tenor <?= $TENOR ?> tahun.
    Harga dapat berubah sewaktu-waktu. Hubungi marketing kami untuk info lebih lanjut.
</p>
<?php include "inc/footer.php"; ?>