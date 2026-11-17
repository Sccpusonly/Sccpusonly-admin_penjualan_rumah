<?php
require_once "inc/cek_login.php";
$judul = "Simulasi KPR";

// Unit tersedia untuk pilihan cepat
$unitTersedia = ambil_semua(
    "SELECT u.id_unit, u.blok, u.nomor, t.nama_tipe, t.harga
     FROM unit u JOIN tipe_rumah t ON u.id_tipe = t.id_tipe
     WHERE u.status = 'Tersedia' ORDER BY u.blok, u.nomor"
);

// Nilai input (dari GET supaya hasil bisa di-refresh / dibagikan)
$harga  = angka($_GET['harga'] ?? 0);
$dp_pct = (float) ($_GET['dp'] ?? 20);     // uang muka dalam persen
$bunga  = (float) ($_GET['bunga'] ?? 7.5); // bunga per tahun (%)
$tenor  = (int) ($_GET['tenor'] ?? 15);    // lama cicilan (tahun)

$hasil = null;
$error = "";

if (isset($_GET['hitung'])) {
    if ($harga <= 0) {
        $error = "Harga rumah harus diisi.";
    } elseif ($dp_pct < 0 || $dp_pct >= 100) {
        $error = "Uang muka harus antara 0% dan 99%.";
    } elseif ($bunga < 0 || $bunga > 30) {
        $error = "Bunga harus antara 0% dan 30% per tahun.";
    } elseif ($tenor < 1 || $tenor > 30) {
        $error = "Tenor harus antara 1 dan 30 tahun.";
    } else {
        $dp     = $harga * $dp_pct / 100;
        $pokok  = $harga - $dp;          // jumlah pinjaman ke bank
        $n      = $tenor * 12;           // jumlah bulan
        $r      = $bunga / 100 / 12;     // bunga per bulan

        // Rumus anuitas: cicilan = P * r / (1 - (1 + r)^-n)
        $cicilan = $r > 0 ? $pokok * $r / (1 - pow(1 + $r, -$n)) : $pokok / $n;

        // Rekap per tahun: berapa pokok & bunga yang dibayar, dan sisa pinjaman
        $rekap = [];
        $sisa  = $pokok;
        for ($th = 1; $th <= $tenor; $th++) {
            $bayarPokok = 0;
            $bayarBunga = 0;
            for ($b = 1; $b <= 12; $b++) {
                $bungaBulan  = $sisa * $r;
                $pokokBulan  = $cicilan - $bungaBulan;
                $sisa       -= $pokokBulan;
                $bayarPokok += $pokokBulan;
                $bayarBunga += $bungaBulan;
            }
            $rekap[] = [$th, $bayarPokok, $bayarBunga, max(0, $sisa)];
        }

        $hasil = [
            'dp'          => $dp,
            'pokok'       => $pokok,
            'cicilan'     => $cicilan,
            'total_bayar' => $cicilan * $n,
            'total_bunga' => $cicilan * $n - $pokok,
            // Aturan umum bank: cicilan maksimal ~1/3 penghasilan
            'min_gaji'    => $cicilan * 3,
        ];
    }
}

include "inc/header.php";
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="fw-bold mb-0">Simulasi KPR</h3>
        <small class="text-muted">Perkiraan cicilan bulanan untuk calon konsumen</small>
    </div>
</div>

<div class="row g-3">
    <!-- ---------- FORM ---------- -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><span class="card-kicker d-block">Input</span>Data Simulasi</div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><i class="bi bi-x-circle"></i> <?= e($error) ?></div>
                <?php endif; ?>

                <form method="get">
                    <input type="hidden" name="hitung" value="1">

                    <div class="mb-3">
                        <label class="form-label">Pilih unit (opsional)</label>
                        <select id="pilihUnit" class="form-select">
                            <option value="">-- isi harga manual --</option>
                            <?php foreach ($unitTersedia as $u): ?>
                                <option value="<?= $u['harga'] ?>">
                                    Blok <?= e($u['blok'] . '-' . $u['nomor']) ?> | <?= e($u['nama_tipe']) ?> | <?= rupiah($u['harga']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Harga Rumah (Rp)</label>
                        <input type="text" inputmode="numeric" autocomplete="off" name="harga" id="harga" class="form-control input-uang" value="<?= $harga ?: '' ?>" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">DP (%)</label>
                            <input type="number" name="dp" class="form-control" step="0.5" min="0" max="99" value="<?= $dp_pct ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Bunga/th (%)</label>
                            <input type="number" name="bunga" class="form-control" step="0.1" min="0" max="30" value="<?= $bunga ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Tenor (th)</label>
                            <select name="tenor" class="form-select">
                                <?php foreach ([5, 10, 15, 20, 25] as $t): ?>
                                    <option value="<?= $t ?>" <?= $tenor == $t ? 'selected' : '' ?>><?= $t ?> tahun</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button class="btn btn-success w-100 mt-4"><i class="bi bi-calculator"></i> Hitung Cicilan</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ---------- HASIL ---------- -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><span class="card-kicker d-block">Hasil</span>Perkiraan Cicilan</div>
            <div class="card-body">
                <?php if (!$hasil): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calculator" style="font-size:3rem"></i>
                        <p class="mt-2 mb-0">Isi data di samping lalu klik <b>Hitung Cicilan</b>.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center mb-4">
                        <div class="text-muted small">Cicilan per bulan</div>
                        <div class="fw-bold text-success" style="font-size:2.2rem"><?= rupiah($hasil['cicilan']) ?></div>
                        <div class="text-muted small">selama <?= $tenor ?> tahun (<?= $tenor * 12 ?> bulan)</div>
                    </div>

                    <table class="table table-sm">
                        <tr><td>Harga rumah</td><td class="text-end"><?= rupiah($harga) ?></td></tr>
                        <tr><td>Uang muka (DP <?= $dp_pct ?>%)</td><td class="text-end"><?= rupiah($hasil['dp']) ?></td></tr>
                        <tr><td>Pinjaman ke bank</td><td class="text-end fw-bold"><?= rupiah($hasil['pokok']) ?></td></tr>
                        <tr><td>Total bunga</td><td class="text-end text-danger"><?= rupiah($hasil['total_bunga']) ?></td></tr>
                        <tr><td>Total dibayar ke bank</td><td class="text-end"><?= rupiah($hasil['total_bayar']) ?></td></tr>
                        <tr><td>Saran penghasilan minimal</td><td class="text-end"><?= rupiah($hasil['min_gaji']) ?> /bulan</td></tr>
                    </table>

                    <details class="mt-3">
                        <summary class="fw-semibold">Rincian per tahun</summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-striped mb-0">
                                <thead><tr><th>Tahun</th><th class="text-end">Bayar Pokok</th><th class="text-end">Bayar Bunga</th><th class="text-end">Sisa Pinjaman</th></tr></thead>
                                <tbody>
                                <?php foreach ($rekap as [$th, $p, $bg, $s]): ?>
                                    <tr>
                                        <td><?= $th ?></td>
                                        <td class="text-end"><?= rupiah($p) ?></td>
                                        <td class="text-end"><?= rupiah($bg) ?></td>
                                        <td class="text-end"><?= rupiah($s) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <p class="text-muted small mt-3 mb-0">
                        * Simulasi memakai bunga tetap (anuitas). Angka sebenarnya tergantung kebijakan bank.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Pilih unit -> harga terisi otomatis
    document.getElementById('pilihUnit').addEventListener('change', function () {
        if (this.value) document.getElementById('harga').value = this.value;
    });
</script>
<?php include "inc/footer.php"; ?>