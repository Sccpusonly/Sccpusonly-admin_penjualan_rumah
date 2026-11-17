</main>

        <footer class="app-footer container no-print">
            <span>&copy; <?= date('Y') ?> <span class="foot-brand">SI Perumahan</span> — Sistem Informasi Pemesanan Unit Perumahan</span>
            <span>Dory Hill &middot; v2.2 Signature Edition</span>
        </footer>
    </div><!-- /.app-main -->
</div><!-- /.app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/../assets/app.js') ?>"></script>
<script src="assets/efek.js?v=<?= @filemtime(__DIR__ . '/../assets/efek.js') ?>"></script>
<script>
window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
(function () {
    // Semua form POST otomatis membawa token CSRF
    function pasang(f) {
        if (!f || !f.method || f.method.toLowerCase() !== 'post') return;
        if (f.querySelector('input[name="csrf"]')) return;
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'csrf'; i.value = window.CSRF_TOKEN;
        f.appendChild(i);
    }
    document.addEventListener('submit', function (e) { pasang(e.target); }, true);

    // Tombol Hapus / Batalkan: pop-up konfirmasi di tengah halaman, lalu kirim sebagai POST
    window.kirimPost = function (url, pesan) {
        function kirim() {
            var f = document.createElement('form');
            f.method = 'post'; f.action = url; f.style.display = 'none';
            pasang(f);
            document.body.appendChild(f);
            f.submit();
        }
        if (pesan && window.tanyaPop) {
            window.tanyaPop(pesan, function () {
                if (window.tungguYa) window.tungguYa('hapus', kirim); else kirim();
            });
            return false;
        }
        if (pesan && !window.confirm(pesan)) return false;
        kirim();
        return false;
    };
})();
</script>
</body>
</html>