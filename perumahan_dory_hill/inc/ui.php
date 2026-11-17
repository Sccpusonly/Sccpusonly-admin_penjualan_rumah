<?php
// ============================================================
// Komponen tampilan yang dipakai bersama di halaman data
// ============================================================

// Kotak statistik. $href boleh null (tidak bisa diklik).
// $warna: netral | hijau | kuning | ungu | biru
function kotak_stat($label, $angka, $ikon, $warna = 'netral', $ket = '', $href = null, $aktif = false)
{
    $tag   = $href ? 'a' : 'div';
    $attr  = $href ? ' href="' . e($href) . '"' : '';
    $kelas = 'hs-kotak w-' . $warna . ($aktif ? ' aktif' : '') . ($href ? '' : ' statis');
    echo "<$tag class=\"$kelas\"$attr>"
       . '<span class="hs-ikon"><i class="bi ' . $ikon . '"></i></span>'
       . '<span class="hs-label">' . e($label) . '</span>'
       . '<b class="hs-angka' . (mb_strlen(strip_tags((string) $angka)) > 6 ? ' panjang' : '') . '">' . $angka . '</b>'
       . '<span class="hs-ket">' . e($ket) . '</span>'
       . "</$tag>";
}

// Deretan chip penyaring. $pilihan = [nilai => label], $link = function($nilai) => url
function chip_saring($pilihan, $sekarang, $link)
{
    echo '<div class="chip-saring">';
    foreach ($pilihan as $nilai => $label) {
        $kls = ((string) $sekarang === (string) $nilai) ? 'aktif' : '';
        echo '<a href="' . e($link($nilai)) . '" class="' . $kls . '">' . e($label) . '</a>';
    }
    echo '</div>';
}

// Pembuka pop-up form. Tutup dengan form_pop_akhir().
function form_pop_awal($judulTambah, $ikon, $bukaAwal, $judulAwal = null, $kickerAwal = 'Entri Baru')
{
    echo '<div class="form-pop" id="formPop" role="dialog" aria-modal="true" aria-labelledby="fpJudul"'
       . ' data-buka-awal="' . ($bukaAwal ? '1' : '0') . '" data-judul-tambah="' . e($judulTambah) . '">'
       . '<div class="fp-kartu"><div class="fp-kepala">'
       . '<span class="fp-ikon"><i class="bi ' . $ikon . '"></i></span>'
       . '<div><span class="card-kicker d-block" data-fp-kicker>' . e($kickerAwal) . '</span>'
       . '<h4 id="fpJudul" data-fp-judul>' . e($judulAwal ?? $judulTambah) . '</h4></div>'
       . '<button type="button" class="fp-tutup" data-tutup-form aria-label="Tutup"><i class="bi bi-x-lg"></i></button>'
       . '</div>';
}
function form_pop_akhir()
{
    echo '</div></div>';
}

// Tombol simpan/batal di bawah form pop-up
function form_pop_tombol($teks = 'Simpan')
{
    echo '<div class="fp-aksi"><button type="button" class="btn btn-ghost" data-tutup-form>Batal</button>'
       . '<button class="btn btn-accent"><i class="bi bi-check2"></i> ' . e($teks) . '</button></div>';
}

// Tombol pindah tampilan (visual <-> tabel). Filter lain di alamat tetap dibawa.
function tombol_tampilan($tampil)
{
    $q = $_GET;
    unset($q['aksi'], $q['id']);
    $q['tampil'] = $tampil === 'tabel' ? 'visual' : 'tabel';
    $ikon = $tampil === 'tabel' ? 'bi-grid-3x3-gap' : 'bi-table';
    $teks = $tampil === 'tabel' ? 'Tampilan visual' : 'Tampilan tabel';
    echo '<a href="?' . e(http_build_query($q)) . '" class="btn btn-ghost"><i class="bi ' . $ikon . '"></i> ' . $teks . '</a>';
}

// Nomor HP 08xx -> 628xx untuk link WhatsApp
function nomor_wa_id($hp)
{
    $hp = preg_replace('/[^0-9]/', '', (string) $hp);
    return substr($hp, 0, 1) === '0' ? '62' . substr($hp, 1) : $hp;
}

// Perkiraan cicilan KPR per bulan (DP 20%, bunga 7,5%/th, 15 tahun)
function cicilan_kira($harga, $dp = 20, $bunga = 7.5, $tenor = 15)
{
    $pokok = $harga * (1 - $dp / 100);
    $r = $bunga / 100 / 12; $n = $tenor * 12;
    return $r > 0 ? $pokok * $r / (1 - pow(1 + $r, -$n)) : $pokok / $n;
}

// ---------- FOTO PROFIL ----------
// Tambahkan kolom `foto` di tabel users kalau belum ada (sekali saja)
function pastikan_kolom_foto()
{
    $ada = ambil_semua("SHOW COLUMNS FROM users LIKE 'foto'");
    if (!$ada) query("ALTER TABLE users ADD foto VARCHAR(255) NULL DEFAULT NULL");
}
// Alamat foto profil user, atau '' kalau belum punya / file hilang
function foto_user($id)
{
    $u = ambil_satu("SELECT * FROM users WHERE id_user = ?", "i", [$id]);
    $foto = $u['foto'] ?? '';
    return ($foto && is_file(__DIR__ . '/../' . $foto)) ? $foto : '';
}
// Bulatan avatar: tampilkan foto kalau ada, kalau tidak inisial nama
function avatar($foto, $inisial, $kelas = 'avatar')
{
    if ($foto) {
        return '<span class="' . $kelas . ' ada-foto" style="background-image:url(\'' . e($foto) . '\')"></span>';
    }
    return '<span class="' . $kelas . '">' . e($inisial) . '</span>';
}