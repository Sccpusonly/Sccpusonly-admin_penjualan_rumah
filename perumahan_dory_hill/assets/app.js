(function () {
  'use strict';

  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------------------------------------------------------
     1. THEME TOGGLE (dark / light, persisted)
  --------------------------------------------------------- */
  var THEME_KEY = 'siperumahan-theme-v2';

  function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem(THEME_KEY, t); } catch (e) {}
    var btn = $('#themeToggle');
    if (btn) {
      btn.innerHTML = t === 'dark'
        ? '<i class="bi bi-sun"></i>'
        : '<i class="bi bi-moon-stars"></i>';
      btn.setAttribute('title', t === 'dark' ? 'Ganti ke mode terang' : 'Ganti ke mode gelap');
    }
  }

  function initTheme() {
    var saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (e) {}
    if (saved !== 'light' && saved !== 'dark') saved = 'light'; // tema terang = default
    applyTheme(saved);

    var btn = $('#themeToggle');
    if (btn) btn.addEventListener('click', function () {
      var cur = document.documentElement.getAttribute('data-theme');
      applyTheme(cur === 'dark' ? 'light' : 'dark');
    });
  }

  /* ---------------------------------------------------------
     2. SIDEBAR (off-canvas di layar kecil)
  --------------------------------------------------------- */
  function initNav() {
    var side = $('#sidebar');
    var btn = $('#btnMenu');
    var overlay = $('#sideOverlay');
    if (!side || !btn) return;

    function close() {
      side.classList.remove('open');
      document.body.classList.remove('nav-open');
      btn.setAttribute('aria-expanded', 'false');
    }
    function open() {
      side.classList.add('open');
      document.body.classList.add('nav-open');
      btn.setAttribute('aria-expanded', 'true');
    }

    btn.addEventListener('click', function () {
      if (side.classList.contains('open')) close(); else open();
    });
    if (overlay) overlay.addEventListener('click', close);

    document.addEventListener('click', function (e) {
      if (!side.contains(e.target) && !btn.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', function () { if (window.innerWidth >= 992) close(); });

    // klik link di sidebar menutup drawer (layar kecil)
    side.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a') : null;
      if (a) close();
    });
  }

  /* ---------------------------------------------------------
     2b. RAIL: sidebar meluncur antara lebar penuh & kolom ikon
  --------------------------------------------------------- */
  function initRail() {
    var btn = $('#btnRail');
    if (!btn) return;

    function apply(collapsed) {
      document.documentElement.classList.toggle('rail-collapsed', collapsed);
      btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      btn.setAttribute('aria-label', collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar');
      var ic = btn.querySelector('i');
      if (ic) ic.className = 'bi ' + (collapsed ? 'bi-chevron-right' : 'bi-chevron-left');
      try { localStorage.setItem('siperumahan-rail', collapsed ? '1' : '0'); } catch (e) {}
    }

    // selaraskan ikon dengan state yang sudah dibaca dari localStorage saat load
    apply(document.documentElement.classList.contains('rail-collapsed'));

    btn.addEventListener('click', function () {
      apply(!document.documentElement.classList.contains('rail-collapsed'));
    });
  }

  /* ---------------------------------------------------------
     2c. MENU AKSI CEPAT (dropdown di tombol Booking Baru)
  --------------------------------------------------------- */
  function initCtaMenu() {
    var wrap = document.getElementById('ctaWrap');
    var btn = document.getElementById('btnCta');
    var menu = document.getElementById('ctaMenu');
    if (!wrap || !btn || !menu) return;

    function close() {
      wrap.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    }
    function toggle() {
      var open = wrap.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      toggle();
    });

    // klik di luar menu -> tutup
    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) close();
    });

    // Escape -> tutup
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });

    // klik salah satu opsi -> tutup (navigasi tetap jalan)
    menu.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a') : null;
      if (a) close();
    });
  }

  /* ---------------------------------------------------------
     3. GLOBAL QUICK SEARCH (Ctrl/Cmd + K)
  --------------------------------------------------------- */
  function initQuickSearch() {
    var input = $('#qGlobal');
    var box = $('#gsearchResults');
    if (!input || !box) return;

    var timer = null, items = [], active = -1, controller = null;

    function close() {
      box.classList.remove('open');
      box.innerHTML = '';
      items = []; active = -1;
    }

    function highlight(i) {
      items.forEach(function (el, idx) { el.classList.toggle('active', idx === i); });
      if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
    }

    function render(groups) {
      box.innerHTML = '';
      items = []; active = -1;
      var ada = false;

      groups.forEach(function (g) {
        if (!g.data || !g.data.length) return;
        ada = true;
        var h = document.createElement('div');
        h.className = 'gsearch-group';
        h.textContent = g.label;
        box.appendChild(h);

        g.data.forEach(function (row) {
          var a = document.createElement('a');
          a.className = 'gsearch-item';
          a.href = row.url;
          a.innerHTML = '<i class="bi ' + row.icon + '"></i><span>' + esc(row.label) +
            '</span><span class="sub">' + esc(row.sub || '') + '</span>';
          box.appendChild(a);
          items.push(a);
        });
      });

      if (!ada) {
        box.innerHTML = '<div class="gsearch-empty"><i class="bi bi-search"></i> Tidak ada hasil untuk "' +
          esc(input.value) + '"</div>';
      }
      box.classList.add('open');
    }

    function search() {
      var q = input.value.trim();
      if (q.length < 1) { close(); return; }
      if (controller) controller.abort();
      controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;

      fetch('search.php?q=' + encodeURIComponent(q), controller ? { signal: controller.signal } : {})
        .then(function (r) { return r.json(); })
        .then(render)
        .catch(function () { /* aborted or offline */ });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(search, 220);
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (items.length) { active = (active + 1) % items.length; highlight(active); } }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (items.length) { active = (active - 1 + items.length) % items.length; highlight(active); } }
      else if (e.key === 'Enter') {
        if (active >= 0 && items[active]) { e.preventDefault(); window.location.href = items[active].href; }
        else if (items.length) { e.preventDefault(); window.location.href = items[0].href; }
      }
      else if (e.key === 'Escape') { close(); input.blur(); }
    });

    document.addEventListener('click', function (e) {
      if (!box.contains(e.target) && e.target !== input) close();
    });

    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        input.focus();
        input.select();
      }
      if (e.key === '/' && document.activeElement === document.body) {
        e.preventDefault();
        input.focus();
      }
    });
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /* ---------------------------------------------------------
     4. TABLE TOOLS — live search + CSV export + pagination
     Opt-in: <table ... data-tools> atau data-tools="export"
  --------------------------------------------------------- */
  function initTableTools() {
    $$('table[data-tools]').forEach(function (table) {
      var mode = table.getAttribute('data-tools'); // "" (all) | "export"
      var paginate = mode !== 'export';
      var tbody = table.tBodies[0];
      var headCount = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells.length : 1;
      var rows = $$('tbody tr', table).filter(function (r) { return !r.classList.contains('empty-row'); });
      var noResultRow = null;
      var si = null;

      if (!rows.length) return; // tabel kosong: pesan bawaan dibiarkan tampil

      // baris "tidak ditemukan" bawaan disembunyikan karena ada data
      var bawaan = $$('tbody tr.empty-row', table)[0];
      if (bawaan) bawaan.style.display = 'none';

      var tools = document.createElement('div');
      tools.className = 'table-tools no-print';

      var pageSize = 10;
      var page = 1;
      var filtered = rows.slice();

      if (mode !== 'export') {
        var wrap = document.createElement('div');
        wrap.className = 'tt-search';
        wrap.innerHTML = '<i class="bi bi-search"></i>' +
          '<input type="search" placeholder="Cari di tabel ini..." aria-label="Cari data">';
        tools.appendChild(wrap);
        si = $('input', wrap);

        si.addEventListener('input', function () {
          var q = si.value.trim().toLowerCase();
          filtered = rows.filter(function (r) {
            return r.textContent.toLowerCase().indexOf(q) !== -1;
          });
          page = 1;
          render();
        });
      }

      var spacer = document.createElement('div');
      spacer.className = 'tt-spacer';
      tools.appendChild(spacer);

      var info = document.createElement('span');
      info.className = 'tt-info';
      tools.appendChild(info);

      var csv = document.createElement('button');
      csv.type = 'button';
      csv.className = 'tt-btn tt-csv';
      csv.innerHTML = '<i class="bi bi-file-earmark-spreadsheet"></i> CSV';
      csv.addEventListener('click', function () { exportCSV(table, filtered); });
      tools.appendChild(csv);

      var prev, next;
      if (mode !== 'export') {
        prev = document.createElement('button');
        prev.type = 'button'; prev.className = 'tt-btn';
        prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
        prev.addEventListener('click', function () { if (page > 1) { page--; render(); } });

        next = document.createElement('button');
        next.type = 'button'; next.className = 'tt-btn';
        next.innerHTML = '<i class="bi bi-chevron-right"></i>';
        next.addEventListener('click', function () {
          if (page * pageSize < filtered.length) { page++; render(); }
        });
        tools.appendChild(prev);
        tools.appendChild(next);
      }

      // sisipkan toolbar sebelum pembungkus tabel
      var wrapTable = table.closest('.table-responsive') || table.parentElement;
      wrapTable.parentElement.insertBefore(tools, wrapTable);

      function clearNoResult() {
        if (noResultRow && noResultRow.parentNode) noResultRow.parentNode.removeChild(noResultRow);
        noResultRow = null;
      }

      function showNoResult() {
        if (noResultRow) return;
        var q = si ? si.value.trim() : '';
        noResultRow = document.createElement('tr');
        noResultRow.className = 'empty-row';
        var td = document.createElement('td');
        td.colSpan = headCount;
        td.innerHTML = '<i class="bi bi-search"></i> Tidak ada data yang cocok dengan &ldquo;' + esc(q) + '&rdquo;';
        noResultRow.appendChild(td);
        tbody.appendChild(noResultRow);
      }

      function render() {
        var total = filtered.length;

        // mode "export": semua baris tampil, tanpa halaman
        if (!paginate) {
          rows.forEach(function (r) { r.style.display = ''; });
          clearNoResult();
          info.textContent = total + ' data';
          return;
        }

        var pages = Math.max(1, Math.ceil(total / pageSize));
        if (page > pages) page = pages;
        var start = (page - 1) * pageSize;
        var end = Math.min(start + pageSize, total);

        rows.forEach(function (r) { r.style.display = 'none'; });
        filtered.slice(start, end).forEach(function (r) { r.style.display = ''; });

        if (total === 0) showNoResult(); else clearNoResult();

        info.textContent = total ? (start + 1) + '–' + end + ' dari ' + total : '0 data';
        if (prev) prev.disabled = page <= 1;
        if (next) next.disabled = page >= pages;
      }

      render();
    });
  }

  function exportCSV(table, rows) {
    var head = $$('thead th', table).map(function (th) { return th.innerText.trim(); });
    var body = rows.map(function (tr) {
      if (tr.classList.contains('empty-row')) return null;
      return $$('td', tr).map(function (td) { return td.innerText.replace(/\s+/g, ' ').trim(); });
    }).filter(Boolean);

    if (!head.length || !body.length) { alert('Tidak ada data untuk diekspor.'); return; }

    var csv = [head].concat(body).map(function (line) {
      return line.map(function (cell) {
        var v = String(cell).replace(/"/g, '""');
        // hindari Excel menginterpretasikan angka sebagai formula
        if (/^[=+\-@]/.test(v)) v = "'" + v;
        return '"' + v + '"';
      }).join(';');
    }).join('\r\n');

    var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    var judul = (document.querySelector('.page-heading h3') || document.body).innerText.trim();
    a.href = url;
    a.download = (judul.replace(/[^a-z0-9]+/gi, '-').toLowerCase().replace(/^-|-$/g, '') || 'data') +
      '-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
  }

  /* ---------------------------------------------------------
     5. COUNT-UP angka statistik  [data-countup="1234"]
  --------------------------------------------------------- */
  function initCountUp() {
    var els = $$('[data-countup]');
    if (!els.length) return;

    function animate(el) {
      var target = parseFloat(el.getAttribute('data-countup')) || 0;
      var prefix = el.getAttribute('data-prefix') || '';
      var suffix = el.getAttribute('data-suffix') || '';
      var dur = 1100, t0 = null;

      function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        var val = Math.round(target * eased);
        el.textContent = prefix + val.toLocaleString('id-ID') + suffix;
        if (p < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }

    if (!('IntersectionObserver' in window)) {
      els.forEach(animate);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { animate(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------------------------------------------------------
     6. UTIL: toggle password visibility  [data-pw-toggle]
  --------------------------------------------------------- */
  function initPwToggles() {
    $$('[data-pw-toggle]').forEach(function (icon) {
      icon.addEventListener('click', function () {
        var input = document.getElementById(icon.getAttribute('data-pw-toggle'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
      });
    });
  }

  /* ---------------------------------------------------------
     7. UTIL: konfirmasi hapus konsisten
  --------------------------------------------------------- */
  function initConfirm() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('[data-confirm]') : null;
      if (a && !confirm(a.getAttribute('data-confirm'))) e.preventDefault();
    });
  }

  /* ---------------------------------------------------------
     8. INPUT UANG: titik pemisah ribuan otomatis saat mengetik
        Pakai class "input-uang" pada <input type="text">.
        Nilai yang diset lewat JS (input.value = ...) ikut diformat,
        server membersihkan titiknya lewat fungsi angka().
  --------------------------------------------------------- */
  function formatRibuan(v) {
    var d = String(v == null ? '' : v).replace(/\D+/g, '').replace(/^0+(?=\d)/, '');
    return d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function initInputUang() {
    var desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    $$('input.input-uang').forEach(function (el) {
      // setiap penetapan value (termasuk dari script lain) otomatis diformat
      Object.defineProperty(el, 'value', {
        configurable: true,
        get: function () { return desc.get.call(el); },
        set: function (v) { desc.set.call(el, formatRibuan(v)); }
      });
      el.value = el.value;

      el.addEventListener('input', function () {
        var lama = desc.get.call(el);
        var pos = el.selectionStart == null ? lama.length : el.selectionStart;
        var digitSebelumKursor = lama.slice(0, pos).replace(/\D+/g, '').length;
        var baru = formatRibuan(lama);
        if (baru === lama) return;
        desc.set.call(el, baru);
        // kembalikan kursor ke posisi digit yang sama
        var n = 0, p = 0;
        while (p < baru.length && n < digitSebelumKursor) { if (/\d/.test(baru.charAt(p))) n++; p++; }
        try { el.setSelectionRange(p, p); } catch (e) {}
      });
    });
  }

  /* ---------------------------------------------------------
     9. INPUT TANGGAL: tanggal yang belum tiba tidak bisa dipilih
        (batas max = hari ini; juga menolak ketikan manual)
  --------------------------------------------------------- */
  function initBatasTanggal() {
    function hariIni() {
      var d = new Date();
      var m = ('0' + (d.getMonth() + 1)).slice(-2), t = ('0' + d.getDate()).slice(-2);
      return d.getFullYear() + '-' + m + '-' + t;
    }
    $$('input[type="date"][max]').forEach(function (el) {
      function jaga() {
        var maks = el.getAttribute('max') || hariIni();
        if (el.value && el.value > maks) el.value = maks;
      }
      el.addEventListener('change', jaga);
      el.addEventListener('blur', jaga);
    });
  }

  /* --------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    initInputUang();
    initBatasTanggal();
    initTheme();
    initNav();
    initRail();
    initCtaMenu();
    initQuickSearch();
    initTableTools();
    initCountUp();
    initPwToggles();
    initConfirm();
  });
})();
