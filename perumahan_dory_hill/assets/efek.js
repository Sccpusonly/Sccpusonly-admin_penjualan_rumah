(function () {
  'use strict';

  var geraklambat = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------
     1. HERO: parallax saat scroll & gerak mouse
        Semakin besar data-depth, semakin jauh layer bergerak.
  --------------------------------------------------------- */
  function initHero() {
    var hero = document.getElementById('showcase');
    if (!hero || hero.classList.contains('ringkas')) return;
    var bg = document.getElementById('scBg');
    var layers = Array.prototype.slice.call(hero.querySelectorAll('.layer'));
    var mx = 0, my = 0, sy = 0, antre = false;

    function gambar() {
      antre = false;
      var tinggi = hero.offsetHeight || 1;
      var p = Math.min(Math.max(sy / tinggi, 0), 1); // 0 = atas, 1 = hero sudah lewat

      // teks & kartu naik lalu menghilang saat di-scroll
      hero.style.setProperty('--sy', (-p * 120) + 'px');
      hero.style.setProperty('--sy2', (-p * 60) + 'px');
      hero.style.setProperty('--so', Math.max(0, 1 - p * 1.6).toFixed(3));

      // latar sedikit membesar
      if (bg) bg.style.transform = 'scale(' + (1 + p * 0.12) + ')';

      // setiap layer ilustrasi bergeser dengan kecepatan berbeda
      layers.forEach(function (l) {
        var d = parseFloat(l.getAttribute('data-depth')) || 0;
        l.style.transform = 'translate(' + (mx * d * -40) + 'px,' + (my * d * -20 + sy * d * 0.5) + 'px)';
      });
    }
    function minta() { if (!antre) { antre = true; requestAnimationFrame(gambar); } }

    window.addEventListener('scroll', function () {
      sy = window.scrollY || window.pageYOffset;
      minta();
    }, { passive: true });

    hero.addEventListener('mousemove', function (e) {
      var r = hero.getBoundingClientRect();
      mx = (e.clientX - r.left) / r.width - 0.5;
      my = (e.clientY - r.top) / r.height - 0.5;
      minta();
    });
    hero.addEventListener('mouseleave', function () { mx = 0; my = 0; minta(); });

    // tombol "Scroll" menggulir halus ke ringkasan
    var panah = hero.querySelector('.sc-scroll');
    if (panah) panah.addEventListener('click', function (e) {
      var tujuan = document.getElementById('ringkasan');
      if (!tujuan) return;
      e.preventDefault();
      tujuan.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    gambar();
  }

  /* ---------------------------------------------------------
     2. ANIMASI SCROLL: kartu muncul satu per satu
  --------------------------------------------------------- */
  function initReveal() {
    if (geraklambat || !('IntersectionObserver' in window)) return;

    var target = Array.prototype.slice.call(
      document.querySelectorAll('.main-content .card, .main-content .stat-card, [data-reveal]')
    ).filter(function (el) {
      // lewati elemen yang sudah punya animasi masuk sendiri
      return !el.className.match(/\bfade-in/) && !el.closest('.showcase') && !el.closest('.welcome-pop');
    });

    // beri jeda berurutan untuk kartu yang bersebelahan
    var urutan = 0, induk = null;
    target.forEach(function (el) {
      if (el.parentElement !== induk) { induk = el.parentElement; urutan = 0; }
      el.style.setProperty('--rv-delay', Math.min(urutan, 5) * 0.08 + 's');
      urutan++;
      el.classList.add('rv');
    });

    var io = new IntersectionObserver(function (entri) {
      entri.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('rv-in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    target.forEach(function (el) { io.observe(el); });
  }

  /* ---------------------------------------------------------
     3. Topbar berbayang & tombol kembali ke atas
  --------------------------------------------------------- */
  function initScrollUi() {
    var tombol = document.createElement('button');
    tombol.type = 'button';
    tombol.className = 'ke-atas no-print';
    tombol.setAttribute('aria-label', 'Kembali ke atas');
    tombol.innerHTML = '<i class="bi bi-arrow-up"></i>';
    document.body.appendChild(tombol);

    tombol.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: geraklambat ? 'auto' : 'smooth' });
    });

    function cek() {
      var y = window.scrollY || window.pageYOffset;
      document.body.classList.toggle('sudah-scroll', y > 10);
      tombol.classList.toggle('tampil', y > 500);
    }
    window.addEventListener('scroll', cek, { passive: true });
    cek();
  }

  /* ---------------------------------------------------------
     4. Sorot kursor di kartu statistik
  --------------------------------------------------------- */
  function initSorot() {
    document.addEventListener('mousemove', function (e) {
      var kartu = e.target.closest ? e.target.closest('.stat-card') : null;
      if (!kartu) return;
      var r = kartu.getBoundingClientRect();
      kartu.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      kartu.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  }

  /* ---------------------------------------------------------
     5. Pop-up sambutan & notifikasi toast
  --------------------------------------------------------- */
  function tampilkanToast() {
    var semua = Array.prototype.slice.call(document.querySelectorAll('.toast-item'));
    semua.forEach(function (t, i) {
      setTimeout(function () { t.classList.add('tampil'); }, 300 + i * 350);

      // hilang sendiri setelah 9 detik
      setTimeout(function () { tutupToast(t); }, 9000 + i * 350);

      var x = t.querySelector('.toast-x');
      if (x) x.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        tutupToast(t);
      });
    });
  }
  function tutupToast(t) {
    if (!t.parentNode) return;
    t.classList.add('keluar');
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 600);
  }

  function initPopup() {
    var pop = document.getElementById('welcomePop');
    if (!pop) { tampilkanToast(); return; }

    document.body.style.overflow = 'hidden';
    var tombol = pop.querySelector('[data-tutup-pop]');
    if (tombol) setTimeout(function () { tombol.focus(); }, 500);

    function tutup() {
      if (pop.classList.contains('keluar')) return;
      pop.classList.add('keluar');
      document.body.style.overflow = '';
      setTimeout(function () {
        if (pop.parentNode) pop.parentNode.removeChild(pop);
        tampilkanToast(); // notifikasi muncul setelah pop-up ditutup
      }, 350);
    }

    if (tombol) tombol.addEventListener('click', tutup);
    pop.addEventListener('click', function (e) { if (e.target === pop) tutup(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });
  }

  /* ---------------------------------------------------------
     6. Pop-up konfirmasi berhasil / gagal
        "berhasil" tertutup otomatis (bisa ditahan dengan hover),
        "gagal" harus ditutup sendiri.
  --------------------------------------------------------- */
  function initNotifPop(selesai) {
    var pop = document.getElementById('notifPop');
    if (!pop) { selesai(); return; }

    var kartu = pop.querySelector('.np-card');
    var tombol = pop.querySelector('[data-tutup-notif]');
    var durasi = parseInt(pop.getAttribute('data-auto'), 10) || 0;
    var sisa = durasi, mulai = Date.now(), timer = null;

    document.body.style.overflow = 'hidden';
    if (tombol) setTimeout(function () { tombol.focus(); }, 400);

    function tutup() {
      if (pop.classList.contains('keluar')) return;
      clearTimeout(timer);
      pop.classList.add('keluar');
      document.body.style.overflow = '';
      setTimeout(function () {
        if (pop.parentNode) pop.parentNode.removeChild(pop);
        selesai();
      }, 300);
    }

    if (durasi) {
      pop.style.setProperty('--np-durasi', durasi + 'ms');
      timer = setTimeout(tutup, durasi);
      // tahan saat mouse di atas kartu
      kartu.addEventListener('mouseenter', function () {
        clearTimeout(timer);
        sisa -= Date.now() - mulai;
      });
      kartu.addEventListener('mouseleave', function () {
        mulai = Date.now();
        timer = setTimeout(tutup, Math.max(sisa, 800));
      });
    }

    if (tombol) tombol.addEventListener('click', tutup);
    pop.addEventListener('click', function (e) { if (e.target === pop) tutup(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });
  }

  /* ---------------------------------------------------------
     7. Pop-up konfirmasi aksi (pengganti confirm() bawaan browser)
        Otomatis berlaku untuk:
        - onclick="return confirm('...')"  (tombol hapus/batal)
        - atribut data-confirm="..."       (logout)
  --------------------------------------------------------- */
  function jenisAksi(pesan) {
    var p = pesan.toLowerCase();
    if (p.indexOf('logout') !== -1) {
      return { ikon: 'bi-box-arrow-right', ya: 'Ya, logout', ket: 'Kamu perlu login lagi untuk masuk ke sistem.', nada: 'netral' };
    }
        if (p.indexOf('simpan') !== -1) {
      return { ikon: 'bi-pencil-square', ya: 'Ya, simpan', ket: 'Data lama akan diganti dengan perubahan ini.', nada: 'netral' };
    }
    if (p.indexOf('batal') !== -1) {
      return { ikon: 'bi-x-octagon', ya: 'Ya, batalkan', ket: 'Booking dibatalkan dan unit kembali berstatus Tersedia.', nada: 'bahaya' };
    }
    return { ikon: 'bi-trash3', ya: 'Ya, hapus', ket: 'Data yang dihapus tidak bisa dikembalikan.', nada: 'bahaya' };
  }

  function tanya(pesan, lanjut) {
    var j = jenisAksi(pesan);
    var pop = document.createElement('div');
    pop.className = 'tanya-pop nada-' + j.nada;
    pop.setAttribute('role', 'alertdialog');
    pop.setAttribute('aria-modal', 'true');
    pop.innerHTML =
      '<div class="tp-card">' +
        '<div class="tp-ikon"><i class="bi ' + j.ikon + '"></i></div>' +
        '<h4></h4>' +
        '<p>' + j.ket + '</p>' +
        '<div class="tp-aksi">' +
          '<button type="button" class="tp-tidak">Batal</button>' +
          '<button type="button" class="tp-ya">' + j.ya + '</button>' +
        '</div>' +
      '</div>';
    pop.querySelector('h4').textContent = pesan; // textContent = aman dari HTML
    document.body.appendChild(pop);
    document.body.style.overflow = 'hidden';

    var tidak = pop.querySelector('.tp-tidak');
    var ya = pop.querySelector('.tp-ya');
    setTimeout(function () { tidak.focus(); }, 50); // fokus ke "Batal" supaya lebih aman

    function tutup(setuju) {
      document.removeEventListener('keydown', tombolKeyboard);
      pop.classList.add('keluar');
      document.body.style.overflow = '';
      setTimeout(function () { if (pop.parentNode) pop.parentNode.removeChild(pop); }, 250);
      if (setuju) {
        ya.disabled = true;
        ya.innerHTML = '<span class="tp-putar"></span> Memproses...';
        setTimeout(lanjut, 200);
      }
    }
    function tombolKeyboard(e) { if (e.key === 'Escape') tutup(false); }

    tidak.addEventListener('click', function () { tutup(false); });
    ya.addEventListener('click', function () { tutup(true); });
    pop.addEventListener('click', function (e) { if (e.target === pop) tutup(false); });
    document.addEventListener('keydown', tombolKeyboard);
  }

    window.tanyaPop = tanya; // dipakai tombol Hapus (inc/footer.php)

  function initTanya() {
    // form EDIT (kolom id sudah terisi): tanya dulu sebelum perubahan disimpan
    document.addEventListener('submit', function (e) {
      var f = e.target;
      if (!f || !f.querySelector || (f.method || '').toLowerCase() !== 'post' || f.dataset.sudahTanya === '1') return;
      var kunci = f.querySelector('input[type="hidden"][name="id_konsumen"], input[type="hidden"][name="id_marketing"], input[type="hidden"][name="id_tipe"], input[type="hidden"][name="id_unit"]');
      if (!kunci || !kunci.value) return; // form tambah: tidak perlu ditanya
      e.preventDefault();
      e.stopImmediatePropagation();
      tanya('Simpan perubahan data ini?', function () {
        f.dataset.sudahTanya = '1';
        if (f.requestSubmit) { f.requestSubmit(); return; }
        var ev = new Event('submit', { bubbles: true, cancelable: true });
        if (f.dispatchEvent(ev)) HTMLFormElement.prototype.submit.call(f);
      });
    }, true);
    // ubah onclick="return confirm('...')" menjadi data-confirm="..."
    Array.prototype.slice.call(document.querySelectorAll('[onclick*="confirm("]')).forEach(function (el) {
      var cocok = el.getAttribute('onclick').match(/confirm\(\s*(['"])(.*?)\1\s*\)/);
      if (!cocok) return;
      el.setAttribute('data-confirm', cocok[2]);
      el.removeAttribute('onclick');
    });

    // tangkap klik lebih dulu (capture) supaya confirm() bawaan di app.js tidak ikut jalan
    document.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('[data-confirm]') : null;
      if (!el) return;
      e.preventDefault();
      e.stopImmediatePropagation();

      tanya(el.getAttribute('data-confirm'), function () {
        if (el.tagName === 'A' && el.href) {
          var hapus = /hapus/i.test(el.getAttribute('data-confirm') || '');
          // pindah halaman SETELAH pesawat selesai terbang satu kali
          tungguYa(hapus ? 'hapus' : (/logout/i.test(el.href) ? 'keluar' : 'ubah'), function () { window.location.href = el.href; });
        } else if (el.form) {
          el.removeAttribute('data-confirm');
          el.click();
        }
      });
    }, true);
  }

  /* ---------------------------------------------------------
     8. Perayaan booking lunas: konfeti + pop-up piala
  --------------------------------------------------------- */
  function hujanKonfeti(kanvas) {
    var ctx = kanvas.getContext('2d');
    var warna = ['#f59e0b', '#d0243a', '#16a34a', '#3b82f6', '#a855f7', '#ec4899', '#fbbf24'];
    var partikel = [], jalan = true, mulai = performance.now();

    function ukur() {
      kanvas.width = kanvas.offsetWidth * (window.devicePixelRatio || 1);
      kanvas.height = kanvas.offsetHeight * (window.devicePixelRatio || 1);
      ctx.setTransform(window.devicePixelRatio || 1, 0, 0, window.devicePixelRatio || 1, 0, 0);
    }
    ukur();
    window.addEventListener('resize', ukur);

    var L = kanvas.offsetWidth, T = kanvas.offsetHeight;

    // ledakan dari dua sisi bawah, lalu hujan dari atas
    function tembak(x, y, arah, jumlah) {
      for (var i = 0; i < jumlah; i++) {
        var sudut = (arah + (Math.random() - 0.5) * 50) * Math.PI / 180;
        var laju = 9 + Math.random() * 9;
        partikel.push({
          x: x, y: y,
          vx: Math.cos(sudut) * laju, vy: Math.sin(sudut) * laju,
          w: 6 + Math.random() * 6, h: 9 + Math.random() * 8,
          r: Math.random() * 360, vr: (Math.random() - 0.5) * 14,
          c: warna[(Math.random() * warna.length) | 0],
          goyang: Math.random() * 10
        });
      }
    }
    tembak(0, T, -60, 90);
    tembak(L, T, -120, 90);
    setTimeout(function () { tembak(L * 0.5, T, -90, 70); }, 450);

    function gerak(waktu) {
      if (!jalan) return;
      ctx.clearRect(0, 0, L, T);
      // tambahan hujan dari atas selama 2,5 detik pertama
      if (waktu - mulai < 2500 && Math.random() < 0.5) {
        partikel.push({
          x: Math.random() * L, y: -20, vx: (Math.random() - 0.5) * 2, vy: 2 + Math.random() * 3,
          w: 6 + Math.random() * 5, h: 9 + Math.random() * 7, r: Math.random() * 360,
          vr: (Math.random() - 0.5) * 10, c: warna[(Math.random() * warna.length) | 0], goyang: Math.random() * 10
        });
      }
      partikel = partikel.filter(function (p) { return p.y < T + 40; });
      partikel.forEach(function (p) {
        p.vy += 0.28;          // gravitasi
        p.vx *= 0.985;         // hambatan udara
        p.vy = Math.min(p.vy, 6);
        p.goyang += 0.08;
        p.x += p.vx + Math.sin(p.goyang) * 0.8;
        p.y += p.vy;
        p.r += p.vr;
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.r * Math.PI / 180);
        ctx.scale(1, Math.abs(Math.cos(p.goyang))); // efek kertas berputar
        ctx.fillStyle = p.c;
        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
      });
      if (partikel.length) requestAnimationFrame(gerak);
    }
    requestAnimationFrame(gerak);

    return function berhenti() { jalan = false; window.removeEventListener('resize', ukur); };
  }

  function initPerayaan() {
    var pop = document.getElementById('perayaanPop');
    if (!pop) return false;

    var berhenti = function () {};
    var kanvas = document.getElementById('konfeti');
    if (kanvas && !geraklambat) berhenti = hujanKonfeti(kanvas);

    document.body.style.overflow = 'hidden';
    var lanjut = pop.querySelector('[data-tutup-perayaan]');
    var cetak = pop.querySelector('[data-cetak-perayaan]');
    if (lanjut) setTimeout(function () { lanjut.focus(); }, 900);

    function tutup(lalu) {
      if (pop.classList.contains('keluar')) return;
      pop.classList.add('keluar');
      document.body.style.overflow = '';
      setTimeout(function () {
        berhenti();
        if (pop.parentNode) pop.parentNode.removeChild(pop);
        if (lalu) lalu();
      }, 350);
    }
    if (lanjut) lanjut.addEventListener('click', function () { tutup(); });
    if (cetak) cetak.addEventListener('click', function () { tutup(function () { window.print(); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });
    return true;
  }

  /* ---------------------------------------------------------
     11. Transisi antar halaman: bilah-bilah gelap menutup layar
         sambil menampilkan nama halaman tujuan, lalu terangkat
         lagi di halaman baru.
  --------------------------------------------------------- */
  var JUMLAH_BILAH = 6;
  var LAMA_TUTUP = 500 + (JUMLAH_BILAH - 1) * 40;   // ms sampai semua bilah menutup
  var bilah = null;
  var gaya = 'bilah';

  // Gaya transisi berbeda-beda sesuai halaman tujuan
  //   bilah     : Data Master (tipe, unit, denah, konsumen, marketing)
  //   mendatar  : Transaksi (booking, pembayaran, kwitansi)
  //   lingkaran : Beranda & Profil (membesar dari titik klik)
  //   diagonal  : Laporan & Alat Bantu
  function gayaUntuk(url) {
    return 'bilah'; // semua halaman memakai bilah (variasi lain dimatikan)
    var f = (url.split('?')[0].split('#')[0].split('/').pop() || 'index.php');
    if (/^(tipe|unit|denah|konsumen|marketing)\.php$/.test(f)) return 'bilah';
    if (/^(booking|pembayaran|kwitansi)\.php$/.test(f)) return 'mendatar';
    if (/^(index|profil)\.php$/.test(f) || f === '') return 'lingkaran';
    return 'diagonal';
  }
  function kelas(sisa) {
    bilah.className = 'bilah g-' + gaya + (sisa ? ' ' + sisa : '');
  }
  // posisi klik terakhir (titik awal lingkaran)
  document.addEventListener('pointerdown', function (e) {
    if (!bilah) return;
    bilah.style.setProperty('--x', e.clientX + 'px');
    bilah.style.setProperty('--y', e.clientY + 'px');
  }, true);

  function buatBilah() {
    var el = document.createElement('div');
    el.className = 'bilah';
    var isi = '';
    for (var i = 0; i < JUMLAH_BILAH; i++) isi += '<i style="--n:' + i + '"></i>';
    el.innerHTML = isi + '<div class="bilah-judul"><small>Menuju</small><span></span></div>';
    document.body.appendChild(el);
    return el;
  }
  function isiJudul(judul) {
    bilah.querySelector('.bilah-judul span').textContent = judul || '';
  }

  function pindahHalaman(url, judul) {
    if (!bilah || geraklambat) { window.location.href = url; return; }
    gaya = gayaUntuk(url);
    try { sessionStorage.setItem('siperumahan-tirai', '1'); sessionStorage.setItem('siperumahan-gaya', gaya); } catch (e) {}
    siapkan(url);
    isiJudul(judul || 'Memuat…');
    kelas('aktif');
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { bilah.classList.add('tutup'); });
    });
    setTimeout(function () { window.location.href = url; }, LAMA_TUTUP + 60);
  }
  window.pindahHalaman = pindahHalaman;

  // muat halaman tujuan lebih awal (saat link disorot) supaya pindahnya cepat
  var sudahDimuat = {};
  function siapkan(url) {
    if (sudahDimuat[url]) return;
    sudahDimuat[url] = true;
    var l = document.createElement('link');
    l.rel = 'prefetch';
    l.href = url;
    document.head.appendChild(l);
  }

  // nama halaman dari link yang diklik (teks menu / judul / tooltip)
  function namaLink(a) {
    var t = a.getAttribute('data-judul') || a.getAttribute('title') || '';
    if (!t) {
      var span = a.querySelector('span, b');
      t = (span ? span.textContent : a.textContent) || '';
    }
    t = t.replace(/\s+/g, ' ').trim();
    return t.length > 28 ? t.slice(0, 26) + '…' : t;
  }

  function linkInternal(a) {
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-confirm')) return false;
    if (a.getAttribute('data-bs-toggle')) return false;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('logout') !== -1) return false;
    var url = new URL(a.href, window.location.href);
    if (url.origin !== window.location.origin) return false;
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;
    return true;
  }

  function bukaBilah() {
    var krit = document.getElementById('ptKritis');
    if (krit) krit.parentNode.removeChild(krit);
    document.documentElement.classList.remove('pt-masuk');
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        kelas('buka');
        // animasi halaman mulai tepat saat bilah mulai terangkat
        setTimeout(function () { document.documentElement.classList.remove('pt-tahan'); }, 120);
        setTimeout(function () { kelas(''); }, 600 + JUMLAH_BILAH * 40 + 400);
      });
    });
  }

  function initTransisi() {
    bilah = buatBilah();

    // halaman baru dibuka lewat transisi: mulai tertutup, lalu terangkat
    if (document.documentElement.classList.contains('pt-masuk')) {
      document.documentElement.classList.add('pt-tahan');
      var judul = document.querySelector('.tb-crumb b');
      isiJudul(judul ? judul.textContent.trim() : document.title.split('|')[0].trim());
      try { gaya = sessionStorage.getItem('siperumahan-gaya') || 'bilah'; } catch (e) {}
      kelas('aktif tertutup');

      // buka setelah halaman selesai dimuat (maks. 0,8 detik supaya tidak kelamaan)
      var dibuka = false;
      function sekali() { if (!dibuka) { dibuka = true; setTimeout(bukaBilah, 80); } }
      if (document.readyState === 'complete') sekali();
      else window.addEventListener('load', sekali);
      setTimeout(sekali, 800);
    }

    // kembali pakai tombol Back browser: pastikan layar tidak tertutup
    window.addEventListener('pageshow', function (e) {
      if (e.persisted && bilah) {
        kelas('');
        document.documentElement.classList.remove('pt-masuk', 'pt-tahan');
      }
    });

    document.addEventListener('mouseover', function (e) {
      var a = e.target.closest ? e.target.closest('a[href]') : null;
      if (linkInternal(a)) siapkan(a.href);
    });

    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest ? e.target.closest('a[href]') : null;
      if (!linkInternal(a)) return;
      e.preventDefault();
      pindahHalaman(a.href, namaLink(a));
    });
  }

  /* ---------------------------------------------------------
     12. Judul muncul per kata
  --------------------------------------------------------- */
  function pecahKata(el, jedaAwal) {
    if (!el || el.classList.contains('dipecah')) return;
    var k = 0;
    function proses(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (n) {
        if (n.nodeType === 3) {
          var bagian = n.textContent.split(/(\s+)/);
          var frag = document.createDocumentFragment();
          bagian.forEach(function (b) {
            if (!b) return;
            if (/^\s+$/.test(b)) { frag.appendChild(document.createTextNode(b)); return; }
            var luar = document.createElement('span'); luar.className = 'kata';
            var dalam = document.createElement('span'); dalam.textContent = b;
            dalam.style.setProperty('--k', k++);
            if (jedaAwal) dalam.style.setProperty('--k0', jedaAwal);
            luar.appendChild(dalam); frag.appendChild(luar);
          });
          node.replaceChild(frag, n);
        } else if (n.nodeType === 1 && n.classList.contains('sc-name')) {
          // teks bergradasi dibungkus utuh supaya warnanya tidak hilang
          var bungkus = document.createElement('span'); bungkus.className = 'kata';
          var isi = document.createElement('span');
          isi.style.setProperty('--k', k++);
          if (jedaAwal) isi.style.setProperty('--k0', jedaAwal);
          node.replaceChild(bungkus, n);
          isi.appendChild(n); bungkus.appendChild(isi);
        } else if (n.nodeType === 1 && n.tagName !== 'BR' && n.tagName !== 'I') {
          proses(n);
        }
      });
    }
    proses(el);
    el.classList.add('dipecah');
  }
  function initJudul() {
    if (geraklambat) return;
    var jeda = document.documentElement.classList.contains('pt-masuk') ? '0.35s' : '0s';
    pecahKata(document.querySelector('.sc-title'), '0.15s');
    document.querySelectorAll('.page-heading h3').forEach(function (h) { pecahKata(h, jeda); });
  }

  /* ---------------------------------------------------------
     13. Intro layar penuh di Beranda
         Gambar membesar & memudar saat di-scroll, lalu header muncul.
  --------------------------------------------------------- */
  function initIntro() {
    var intro = document.getElementById('intro');
    if (!intro) return;
    var lekat = intro.querySelector('.intro-lekat');
    var layers = Array.prototype.slice.call(intro.querySelectorAll('.layer'));
    var akar = document.documentElement;
    var antre = false;

    function jarak() { return Math.max(1, intro.offsetHeight - window.innerHeight * 0.75); }

    function gambar() {
      antre = false;
      var y = window.scrollY || window.pageYOffset;
      var p = Math.min(Math.max(y / jarak(), 0), 1);
      lekat.style.setProperty('--p', p.toFixed(4));
      layers.forEach(function (l) {
        var d = parseFloat(l.getAttribute('data-depth')) || 0;
        l.style.transform = 'translateY(' + (y * d * 0.35) + 'px)';
      });
      akar.classList.toggle('intro-lewat', p > 0.82);
    }
    function minta() { if (!antre) { antre = true; requestAnimationFrame(gambar); } }
    window.addEventListener('scroll', minta, { passive: true });
    window.addEventListener('resize', minta);
    gambar();

    // judul nama perumahan muncul per kata
    if (!geraklambat) pecahKata(intro.querySelector('.intro-nama'), '0.35s');

    // tombol "Scroll" menggulir halus sampai dashboard
    var tombol = document.getElementById('introScroll');
    if (tombol) tombol.addEventListener('click', function (e) {
      e.preventDefault();
      var tujuan = document.getElementById('showcase') || document.getElementById('konten');
      var top = tujuan ? tujuan.getBoundingClientRect().top + window.scrollY - 96 : jarak();
      window.scrollTo({ top: Math.max(top, jarak()), behavior: geraklambat ? 'auto' : 'smooth' });
    });
  }

  /* ---------------------------------------------------------
     14. Dock navigasi: kelompok menu membuka panel
         (disorot mouse di laptop, diketuk di HP)
  --------------------------------------------------------- */
  function initDock() {
    var dock = document.getElementById('dock');
    if (!dock) return;
    var grup = Array.prototype.slice.call(dock.querySelectorAll('.dock-grup'));
    var bisaHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var timer = null;

    function tutupSemua(kecuali) {
      grup.forEach(function (g) {
        if (g === kecuali) return;
        g.classList.remove('buka');
        g.querySelector('.dock-it').setAttribute('aria-expanded', 'false');
      });
    }
    function buka(g) {
      tutupSemua(g);
      g.classList.add('buka');
      g.querySelector('.dock-it').setAttribute('aria-expanded', 'true');
    }

    grup.forEach(function (g) {
      var tombol = g.querySelector('.dock-it');
      tombol.addEventListener('click', function (e) {
        e.stopPropagation();
        // di laptop panel sudah terbuka karena disorot, jadi klik tidak menutupnya
        if (g.classList.contains('buka') && !bisaHover) tutupSemua(); else buka(g);
      });
      if (bisaHover) {
        g.addEventListener('mouseenter', function () { clearTimeout(timer); buka(g); });
        g.addEventListener('mouseleave', function () {
          clearTimeout(timer);
          timer = setTimeout(function () { g.classList.remove('buka'); }, 220);
        });
      }
    });
    document.addEventListener('click', function (e) { if (!dock.contains(e.target)) tutupSemua(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupSemua(); });
  }

  /* ---------------------------------------------------------
     15. Pop-up form tambah / edit (dipakai semua halaman data)
         Tombol tambah : [data-buka-form]
         Tombol edit   : [data-buka-form] data-isi='{"nama":"..."}' data-judul="Edit ..."
         Nilai awal    : atribut data-bawaan di input (dipakai saat tambah)
  --------------------------------------------------------- */
  function initFormPop() {
    var pop = document.getElementById('formPop');
    if (!pop) return;
    var form = pop.querySelector('form');
    var judul = pop.querySelector('[data-fp-judul]');
    var kicker = pop.querySelector('[data-fp-kicker]');
    var judulTambah = pop.getAttribute('data-judul-tambah') || (judul ? judul.textContent : '');

    function kosongkan() {
      if (!form) return;
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name || el.type === 'submit' || el.type === 'button') return;
        el.value = el.hasAttribute('data-bawaan') ? el.getAttribute('data-bawaan') : '';
      });
    }
    function picuChange() {
      if (!form) return;
      form.querySelectorAll('select').forEach(function (s) { s.dispatchEvent(new Event('change')); });
    }
    function tampil() {
      pop.classList.remove('keluar');
      pop.classList.add('tampil');
      document.body.style.overflow = 'hidden';
      setTimeout(function () {
        var pertama = form && form.querySelector('input:not([type=hidden]):not([readonly]), select, textarea');
        if (pertama) pertama.focus();
      }, 260);
    }
    function buka(t) {
      kosongkan();
      var isi = t.getAttribute('data-isi');
      if (isi) {
        var d = JSON.parse(isi);
        Object.keys(d).forEach(function (k) { if (form.elements[k]) form.elements[k].value = d[k]; });
      }
      var edit = isi && t.hasAttribute('data-judul');
      if (judul) judul.textContent = edit ? t.getAttribute('data-judul') : judulTambah;
      if (kicker) kicker.textContent = edit ? 'Ubah Data' : 'Entri Baru';
      picuChange();
      tampil();
    }
    function tutup() {
      if (!pop.classList.contains('tampil')) return;
      pop.classList.add('keluar');
      document.body.style.overflow = '';
      setTimeout(function () { pop.classList.remove('tampil', 'keluar'); }, 280);
      if (location.search.indexOf('aksi=') !== -1) history.replaceState(null, '', location.pathname);
    }

    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target.closest('[data-buka-form]') : null;
      if (t) {
        e.preventDefault();
        e.stopImmediatePropagation();    // jangan jalankan transisi halaman
        buka(t);
        return;
      }
      if (pop.classList.contains('tampil') && (e.target === pop || (e.target.closest && e.target.closest('[data-tutup-form]')))) tutup();
    }, true);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !document.querySelector('.notif-pop, .tanya-pop')) tutup();
    });

    // dibuka dari alamat ?aksi=tambah / ?aksi=edit (mis. setelah validasi gagal)
    if (pop.getAttribute('data-buka-awal') === '1') { picuChange(); tampil(); }
  }

  /* ---------------------------------------------------------
     16. Loading kecil "Tunggu ya ;)" saat simpan / hapus / ubah data
  --------------------------------------------------------- */
  var KATA = {
    simpan: ['Lagi nyimpen data', 'Dicatat dulu ya', 'Sedikit lagi'],
    hapus:  ['Lagi dihapus', 'Bersih-bersih dulu', 'Sedikit lagi'],
    ubah:   ['Lagi diproses', 'Diurus dulu ya', 'Sedikit lagi'],
    keluar: ['Sampai jumpa', 'Lagi keluar', 'Dadah']
  };
  var EMOT = [';)', ':D', '^_^', ':3', '(^o^)'];

  // gambar kucing naik pesawat (SVG buatan sendiri)
  var KUCING_PESAWAT =
    '<svg viewBox="0 0 130 80" aria-hidden="true">' +
      // syal berkibar di belakang
      '<path class="kp-syal" d="M44 30 C34 26 26 34 14 28 C20 36 30 34 42 36 Z"/>' +
      // ekor & sayap belakang
      '<path class="kp-badan" d="M14 44 L6 24 L22 38 Z"/>' +
      // badan pesawat
      '<path class="kp-badan" d="M12 42 C12 34 30 32 70 32 C96 32 110 36 112 44 C110 52 96 56 70 56 C30 56 12 52 12 42 Z"/>' +
      '<path class="kp-garis" d="M24 46 L100 46"/>' +
      // kepala kucing di kokpit
      '<path class="kp-telinga" d="M47 20 L50 6 L58 16 Z"/>' +
      '<path class="kp-telinga" d="M66 16 L74 6 L76 20 Z"/>' +
      '<circle class="kp-kepala" cx="61" cy="24" r="14"/>' +
      '<path class="kp-dalam" d="M50 12 L51 17 L55 15 Z M72 12 L71 17 L68 15 Z"/>' +
      // mata: satu kedip (wink), satu senyum
      '<path class="kp-mata" d="M53 23 Q56 20 59 23"/>' +
      '<path class="kp-mata kp-kedip" d="M64 22 L70 22"/>' +
      '<circle class="kp-pipi" cx="53" cy="28" r="2.4"/><circle class="kp-pipi" cx="70" cy="28" r="2.4"/>' +
      '<path class="kp-mulut" d="M59 28 Q61 30 63 28"/>' +
      // kaca kokpit
      '<path class="kp-kaca" d="M44 33 C46 26 76 26 80 33 Z"/>' +
      // sayap depan
      '<path class="kp-sayap" d="M48 48 L70 48 L58 66 L46 66 Z"/>' +
      // baling-baling
      '<circle class="kp-hidung" cx="113" cy="44" r="4"/>' +
      '<ellipse class="kp-baling" cx="116" cy="44" rx="2.5" ry="16"/>' +
    '</svg>';

  function tungguYa(jenis, lalu) {
    if (document.getElementById('tungguYa')) return;
    var sudahLalu = false;
    var kata = KATA[jenis] || KATA.ubah;
    var el = document.createElement('div');
    el.id = 'tungguYa';
    el.className = 'tunggu-pop';
    el.setAttribute('role', 'status');
    el.innerHTML =
      '<svg class="ty-gelombang" aria-hidden="true">' +
        '<defs><clipPath id="tyJejak"><rect x="0" y="0" width="0" height="400"/></clipPath>' +
        '<linearGradient id="tyGrad" x1="0" x2="1"><stop offset="0" stop-color="#ffb35c"/><stop offset="1" stop-color="#e0303f"/></linearGradient></defs>' +
        '<path class="ty-jalur"/>' +
        '<path class="ty-jejak" clip-path="url(#tyJejak)"/>' +
      '</svg>' +
      '<div class="ty-kucing">' + KUCING_PESAWAT + '</div>' +
      '<div class="ty-teks">' +
        '<b>Tunggu ya <span class="ty-emot">;)</span></b>' +
        '<small class="ty-sub">' + kata[0] + '<i>.</i><i>.</i><i>.</i></small>' +
      '</div>';
    document.body.appendChild(el);

    var svg = el.querySelector('.ty-gelombang');
    var jalur = el.querySelector('.ty-jalur');
    var jejak = el.querySelector('.ty-jejak');
    var klip = el.querySelector('#tyJejak rect');
    var kucing = el.querySelector('.ty-kucing');
    var W = 0, H = 160, TENGAH = 80, AMP = 26, LAMBDA = 320;

    function ukur() {
      W = window.innerWidth;
      LAMBDA = Math.max(240, W / 4);
      AMP = W < 600 ? 18 : 26;
      svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    }
    function yDi(x, fase) { return TENGAH + AMP * Math.sin((x / LAMBDA) * Math.PI * 2 + fase); }
    function buatJalur(fase) {
      var d = 'M0 ' + yDi(0, fase).toFixed(1);
      for (var x = 8; x <= W + 8; x += 8) d += ' L' + x + ' ' + yDi(x, fase).toFixed(1);
      return d;
    }
    ukur();
    window.addEventListener('resize', ukur);

    var LAMA = 4000, mulai = performance.now();
    (function gerak(t) {
      if (!el.isConnected) return;
      var fase = t / 900;                         // gelombang ikut beriak pelan
      var jalan = (t - mulai) / LAMA;
      // satu kali terbang penuh dulu, baru proses dilanjutkan
      if (jalan >= 1 && !sudahLalu) { sudahLalu = true; if (lalu) lalu(); }
      var p = Math.min(jalan, 1);                 // berhenti di ujung kanan, tidak mengulang
      var x = -80 + p * (W + 200);
      var y = yDi(x, fase);
      var miring = Math.atan2(yDi(x + 4, fase) - yDi(x - 4, fase), 8) * 180 / Math.PI;
      var d = buatJalur(fase);
      jalur.setAttribute('d', d);
      jejak.setAttribute('d', d);
      klip.setAttribute('width', Math.max(0, x));
      var r = svg.getBoundingClientRect();
      kucing.style.transform = 'translate(' + (x - 96) + 'px,' + (r.top + (y / H) * r.height - 68) + 'px) rotate(' + (miring * 0.6).toFixed(1) + 'deg)';
      requestAnimationFrame(gerak);
    })(mulai);

    // ganti teks & emotikon pelan-pelan supaya terasa hidup
    var n = 0, sub = el.querySelector('.ty-sub'), emot = el.querySelector('.ty-emot');
    setInterval(function () {
      n++;
      sub.firstChild.nodeValue = kata[n % kata.length];
      emot.textContent = EMOT[n % EMOT.length];
      emot.classList.remove('ty-pop'); void emot.offsetWidth; emot.classList.add('ty-pop');
    }, 1100);
  }
  window.tungguYa = tungguYa;

  function initTunggu() {
    // semua form simpan (POST) -> tampilkan loading saat dikirim
    document.addEventListener('submit', function (e) {
      var f = e.target;
      if (e.defaultPrevented || (f.method || '').toLowerCase() !== 'post') return;
      if (!f.checkValidity()) return;
      var tombol = f.querySelector('button:not([type=button])');
      if (tombol) tombol.disabled = true;
      // tutup pop-up form dulu supaya loading terlihat jelas
      var pop = f.closest('.form-pop');
      if (pop) pop.classList.remove('tampil');
      // tahan dulu, kirim form setelah pesawat sampai di ujung kanan
      e.preventDefault();
      tungguYa('simpan', function () { HTMLFormElement.prototype.submit.call(f); });
    });
    // kembali pakai tombol Back: buang loading yang tertinggal
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) { var t = document.getElementById('tungguYa'); if (t) t.remove(); }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initJudul();
    initIntro();
    initDock();
    initFormPop();
    initTunggu();
    initTransisi();
    initHero();
    initReveal();
    initScrollUi();
    initSorot();
    initTanya();
    if (!initPerayaan()) {
      // pop-up konfirmasi dulu, setelah ditutup baru pop-up sambutan / toast
      initNotifPop(initPopup);
    }
  });
})();