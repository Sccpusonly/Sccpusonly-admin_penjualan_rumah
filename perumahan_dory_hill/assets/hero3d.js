(function () {
  'use strict';

  var hero = document.getElementById('showcase');
  var wadah = document.getElementById('scBg');
  if (!hero || !wadah || typeof THREE === 'undefined') return;

  // cek dukungan WebGL
  try {
    var uji = document.createElement('canvas');
    if (!(uji.getContext('webgl') || uji.getContext('experimental-webgl'))) return;
  } catch (e) { return; }

  var geraklambat = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var unit = (window.DATA_UNIT || []).slice(0, 30);

  /* ---------- warna ---------- */
  var WARNA_STATUS = { Tersedia: 0x10b981, Booking: 0xf59e0b, Terjual: 0x6366f1 };
  var TEMA = {
    siang: {
      matahari: 0xffd2a6, kuatMatahari: 1.25, langit: 0xffe2cc, tanahHemi: 0x8a4a4a, kuatHemi: 0.75,
      rumput: 0x7fbf6a, tanah: 0x8a5a44, jalan: 0x4a3a40, jendela: 0x9fc6e0, cahayaJendela: 0.0,
      lampu: 0.0, awan: 0.9, bintang: 0.0, kabut: 0xffb89a
    },
    malam: {
      matahari: 0x9fb4ff, kuatMatahari: 0.45, langit: 0x3a3a7a, tanahHemi: 0x100818, kuatHemi: 0.35,
      rumput: 0x2c4a36, tanah: 0x3a2630, jalan: 0x1c1620, jendela: 0xffcf6e, cahayaJendela: 1.3,
      lampu: 1.0, awan: 0.12, bintang: 1.0, kabut: 0x1b1030
    }
  };
  function temaSekarang() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? TEMA.malam : TEMA.siang;
  }

  /* ---------- renderer, scene, kamera ---------- */
  var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.domElement.className = 'hero-3d';
  wadah.appendChild(renderer.domElement);

  var scene = new THREE.Scene();
  scene.fog = new THREE.Fog(0xffb89a, 70, 140);

  var kamera = new THREE.PerspectiveCamera(32, 1, 0.1, 200);

  /* ---------- cahaya ---------- */
  var hemi = new THREE.HemisphereLight(0xffe2cc, 0x8a4a4a, 0.75);
  scene.add(hemi);
  var matahari = new THREE.DirectionalLight(0xffd2a6, 1.25);
  matahari.position.set(14, 22, 10);
  matahari.castShadow = true;
  matahari.shadow.mapSize.set(1024, 1024);
  matahari.shadow.camera.left = -18; matahari.shadow.camera.right = 18;
  matahari.shadow.camera.top = 18; matahari.shadow.camera.bottom = -18;
  matahari.shadow.bias = -0.0008;
  scene.add(matahari);

  /* ---------- material bersama ---------- */
  function mat(warna, opsi) {
    var m = new THREE.MeshStandardMaterial(Object.assign({ color: warna, flatShading: true, roughness: 0.85, metalness: 0 }, opsi || {}));
    return m;
  }
  var M = {
    rumput: mat(0x7fbf6a), tanah: mat(0x8a5a44), jalan: mat(0x4a3a40), marka: mat(0xffffff, { roughness: 1 }),
    dinding: [mat(0xfff7f2), mat(0xf4e3da), mat(0xffffff)],
    atap: mat(0x3b2a30), kayu: mat(0xc98b5e), pintu: mat(0x5b3a2e),
    jendela: mat(0x9fc6e0, { emissive: 0xffcf6e, emissiveIntensity: 0 }),
    batang: mat(0x6b4a3a), daun: [mat(0x3f8f5a), mat(0x4fa56a), mat(0x2f7549)],
    tiang: mat(0x2a2a30), bohlam: mat(0xfff1c9, { emissive: 0xffd27a, emissiveIntensity: 0 }),
    awan: new THREE.MeshStandardMaterial({ color: 0xffffff, flatShading: true, transparent: true, opacity: 0.9, roughness: 1 }),
    mobil: [mat(0xd0243a), mat(0x3b82f6), mat(0xf5f5f5)], kaca: mat(0x223, { roughness: 0.3 })
  };

  function kotak(w, h, d, m) {
    var mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), m);
    mesh.castShadow = true; mesh.receiveShadow = true;
    return mesh;
  }

  /* ---------- tata letak: satu baris per blok ---------- */
  var perBlok = {};
  unit.forEach(function (u) { (perBlok[u.blok] = perBlok[u.blok] || []).push(u); });
  var daftarBlok = Object.keys(perBlok).sort();
  if (!daftarBlok.length) { daftarBlok = ['A']; perBlok.A = []; }

  var JARAK_X = 2.6, JARAK_BARIS = 5.2;
  var maksRumah = Math.max(4, Math.max.apply(null, daftarBlok.map(function (b) { return perBlok[b].length; })));
  var LEBAR = maksRumah * JARAK_X + 4;
  var DALAM = daftarBlok.length * JARAK_BARIS + 3;

  var diorama = new THREE.Group();
  scene.add(diorama);

  // pulau (rumput + lapisan tanah)
  var pulau = kotak(LEBAR, 0.5, DALAM, M.rumput);
  pulau.position.y = -0.25;
  diorama.add(pulau);
  var bawah = kotak(LEBAR - 0.4, 1.4, DALAM - 0.4, M.tanah);
  bawah.position.y = -1.2;
  diorama.add(bawah);

  var rumahInteraktif = []; // mesh yang bisa disorot
  var penanda = [];         // pin status yang bergoyang
  var lampuGlow = [];       // sprite cahaya lampu jalan

  // tekstur lingkaran bercahaya (untuk lampu jalan)
  var glowTex = (function () {
    var c = document.createElement('canvas'); c.width = c.height = 64;
    var g = c.getContext('2d');
    var grad = g.createRadialGradient(32, 32, 0, 32, 32, 32);
    grad.addColorStop(0, 'rgba(255,220,150,1)');
    grad.addColorStop(0.3, 'rgba(255,190,110,0.45)');
    grad.addColorStop(1, 'rgba(255,190,110,0)');
    g.fillStyle = grad; g.fillRect(0, 0, 64, 64);
    return new THREE.CanvasTexture(c);
  })();

  function buatRumah(u, varian) {
    var g = new THREE.Group();
    var dinding = M.dinding[varian % 3];

    var badan = kotak(1.7, 1.15, 1.5, dinding);
    badan.position.y = 0.575;
    g.add(badan);

    if (varian % 2 === 0) {
      // atap limas
      var atap = new THREE.Mesh(new THREE.ConeGeometry(1.42, 0.85, 4), M.atap);
      atap.rotation.y = Math.PI / 4;
      atap.scale.set(1, 1, 0.9);
      atap.position.y = 1.15 + 0.425;
      atap.castShadow = true;
      g.add(atap);
    } else {
      // atap datar modern + panel kayu
      var dak = kotak(1.95, 0.14, 1.75, M.atap);
      dak.position.y = 1.22;
      g.add(dak);
      var kayu = kotak(0.6, 1.15, 0.06, M.kayu);
      kayu.position.set(0.55, 0.575, 0.76);
      g.add(kayu);
    }

    var pintu = kotak(0.34, 0.62, 0.05, M.pintu);
    pintu.position.set(-0.45, 0.31, 0.77);
    g.add(pintu);

    [-0.02, 0.5].forEach(function (x, i) {
      if (varian % 2 === 1 && i === 1) return;
      var j = kotak(0.3, 0.3, 0.05, M.jendela);
      j.position.set(x, 0.72, 0.77);
      j.castShadow = false;
      g.add(j);
    });

    // pin status
    var warna = WARNA_STATUS[u.status] || 0x999999;
    var pinMat = new THREE.MeshStandardMaterial({ color: warna, emissive: warna, emissiveIntensity: 0.35, flatShading: true });
    var pin = new THREE.Group();
    var bola = new THREE.Mesh(new THREE.IcosahedronGeometry(0.2, 0), pinMat);
    var ujung = new THREE.Mesh(new THREE.ConeGeometry(0.13, 0.3, 6), pinMat);
    ujung.rotation.x = Math.PI; ujung.position.y = -0.24;
    pin.add(bola); pin.add(ujung);
    pin.position.y = 2.55;
    pin.userData.fase = Math.random() * Math.PI * 2;
    g.add(pin);
    penanda.push(pin);

    badan.userData.unit = u;
    badan.userData.grup = g;
    rumahInteraktif.push(badan);
    return g;
  }

  function buatPohon(x, z, s) {
    var g = new THREE.Group();
    var batang = kotak(0.14, 0.5, 0.14, M.batang);
    batang.position.y = 0.25;
    g.add(batang);
    var daun1 = new THREE.Mesh(new THREE.ConeGeometry(0.5, 0.9, 6), M.daun[(Math.random() * 3) | 0]);
    daun1.position.y = 0.85; daun1.castShadow = true;
    var daun2 = new THREE.Mesh(new THREE.ConeGeometry(0.36, 0.7, 6), M.daun[(Math.random() * 3) | 0]);
    daun2.position.y = 1.25; daun2.castShadow = true;
    g.add(daun1); g.add(daun2);
    g.position.set(x, 0, z);
    g.scale.setScalar(s);
    g.userData.fase = Math.random() * 6;
    return g;
  }

  function buatLampu(x, z) {
    var g = new THREE.Group();
    var tiang = kotak(0.06, 1.3, 0.06, M.tiang);
    tiang.position.y = 0.65;
    g.add(tiang);
    var bohlam = new THREE.Mesh(new THREE.SphereGeometry(0.1, 8, 6), M.bohlam);
    bohlam.position.y = 1.35;
    g.add(bohlam);
    var glow = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex, transparent: true, depthWrite: false, opacity: 0, blending: THREE.AdditiveBlending }));
    glow.scale.set(1.6, 1.6, 1);
    glow.position.y = 1.35;
    g.add(glow);
    lampuGlow.push(glow);
    g.position.set(x, 0, z);
    return g;
  }

  var pohon = [];
  var zAwal = -DALAM / 2 + 2.2;
  daftarBlok.forEach(function (b, bi) {
    var z = zAwal + bi * JARAK_BARIS;
    var isi = perBlok[b];
    var xAwal = -((isi.length - 1) * JARAK_X) / 2;

    isi.forEach(function (u, i) {
      var r = buatRumah(u, bi + i);
      r.position.set(xAwal + i * JARAK_X, 0, z);
      diorama.add(r);
    });

    // jalan di depan setiap baris
    var jalan = kotak(LEBAR - 0.2, 0.04, 1.3, M.jalan);
    jalan.position.set(0, 0.02, z + 2.2);
    jalan.castShadow = false;
    diorama.add(jalan);
    for (var mx = -LEBAR / 2 + 1; mx < LEBAR / 2 - 0.5; mx += 1.4) {
      var marka = kotak(0.6, 0.02, 0.07, M.marka);
      marka.position.set(mx, 0.05, z + 2.2);
      marka.castShadow = false;
      diorama.add(marka);
    }
    // lampu jalan
    for (var lx = -LEBAR / 2 + 2; lx < LEBAR / 2 - 1; lx += 5.2) diorama.add(buatLampu(lx, z + 1.4));
  });

  // pohon di pinggir pulau
  for (var t = 0; t < 26; t++) {
    var sisi = t % 4, px, pz;
    if (sisi === 0) { px = -LEBAR / 2 + 0.6; pz = (Math.random() - 0.5) * (DALAM - 1.5); }
    else if (sisi === 1) { px = LEBAR / 2 - 0.6; pz = (Math.random() - 0.5) * (DALAM - 1.5); }
    else if (sisi === 2) { px = (Math.random() - 0.5) * (LEBAR - 1.5); pz = -DALAM / 2 + 0.6; }
    else { px = (Math.random() - 0.5) * (LEBAR - 1.5); pz = DALAM / 2 - 0.5; }
    var ph = buatPohon(px, pz, 0.8 + Math.random() * 0.6);
    pohon.push(ph);
    diorama.add(ph);
  }

  // mobil yang berjalan di jalan
  var mobil = [];
  daftarBlok.forEach(function (b, bi) {
    if (bi > 2) return;
    var m = new THREE.Group();
    var badanMobil = kotak(1.0, 0.32, 0.5, M.mobil[bi % 3]);
    badanMobil.position.y = 0.26;
    var kabin = kotak(0.55, 0.26, 0.44, M.kaca);
    kabin.position.set(-0.05, 0.5, 0);
    m.add(badanMobil); m.add(kabin);
    m.userData = { z: zAwal + bi * JARAK_BARIS + 2.2 + (bi % 2 ? 0.3 : -0.3), arah: bi % 2 ? -1 : 1, laju: 2.2 + bi * 0.5, x: (Math.random() - 0.5) * LEBAR };
    diorama.add(m);
    mobil.push(m);
  });

  // awan
  var awan = [];
  for (var a = 0; a < 0; a++) { // awan dimatikan (menutupi pandangan)
    var ga = new THREE.Group();
    for (var k = 0; k < 3; k++) {
      var gumpal = new THREE.Mesh(new THREE.IcosahedronGeometry(0.55 + Math.random() * 0.35, 0), M.awan);
      gumpal.position.set(k * 0.8 - 0.8, Math.random() * 0.3, Math.random() * 0.4);
      ga.add(gumpal);
    }
    ga.position.set((Math.random() - 0.5) * LEBAR * 1.6, 7 + Math.random() * 3, -DALAM * (0.7 + Math.random() * 0.6));
    ga.userData.laju = 0.3 + Math.random() * 0.4;
    scene.add(ga);
    awan.push(ga);
  }

  // bintang
  var posBintang = [];
  for (var s = 0; s < 400; s++) {
    var th = Math.random() * Math.PI * 2, ph2 = Math.random() * Math.PI * 0.45;
    posBintang.push(Math.cos(th) * Math.sin(ph2) * 90, Math.cos(ph2) * 90, Math.sin(th) * Math.sin(ph2) * 90);
  }
  var geoBintang = new THREE.BufferGeometry();
  geoBintang.setAttribute('position', new THREE.Float32BufferAttribute(posBintang, 3));
  var matBintang = new THREE.PointsMaterial({ color: 0xffffff, size: 0.35, transparent: true, opacity: 0 });
  scene.add(new THREE.Points(geoBintang, matBintang));

  /* ---------- transisi tema siang <-> malam (halus) ---------- */
  var nilai = {}; // nilai yang sedang dipakai
  function salin(t) { for (var k in t) nilai[k] = t[k]; }
  salin(temaSekarang());
  var target = temaSekarang();

  function lerpWarna(a, b, f) {
    var ca = new THREE.Color(a), cb = new THREE.Color(b);
    return ca.lerp(cb, f).getHex();
  }
  function terapkanTema(f) {
    for (var k in target) {
      if (typeof target[k] === 'number' && k.indexOf('kuat') === 0 || ['cahayaJendela', 'lampu', 'awan', 'bintang'].indexOf(k) !== -1) {
        nilai[k] += (target[k] - nilai[k]) * f;
      } else {
        nilai[k] = lerpWarna(nilai[k], target[k], f);
      }
    }
    matahari.color.setHex(nilai.matahari); matahari.intensity = nilai.kuatMatahari;
    hemi.color.setHex(nilai.langit); hemi.groundColor.setHex(nilai.tanahHemi); hemi.intensity = nilai.kuatHemi;
    M.rumput.color.setHex(nilai.rumput); M.tanah.color.setHex(nilai.tanah); M.jalan.color.setHex(nilai.jalan);
    M.jendela.color.setHex(nilai.jendela); M.jendela.emissiveIntensity = nilai.cahayaJendela;
    M.bohlam.emissiveIntensity = nilai.lampu * 1.5;
    lampuGlow.forEach(function (l) { l.material.opacity = nilai.lampu; });
    M.awan.opacity = nilai.awan;
    matBintang.opacity = nilai.bintang;
    scene.fog.color.setHex(nilai.kabut);
  }
  terapkanTema(1);
  new MutationObserver(function () { target = temaSekarang(); }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  /* ---------- ukuran & posisi (diorama di sisi kanan pada layar lebar) ---------- */
  var W = 1, H = 1, layarLebar = true;
  function ukur() {
    W = wadah.clientWidth || hero.clientWidth;
    H = wadah.clientHeight || hero.clientHeight;
    layarLebar = W > 900;
    renderer.setSize(W, H, false);
    kamera.aspect = W / H;
    if (layarLebar) kamera.setViewOffset(W * 1.42, H, 0, 0, W, H); // pusat gambar bergeser ke kanan
    else kamera.clearViewOffset();
    kamera.updateProjectionMatrix();
  }
  ukur();
  window.addEventListener('resize', ukur);

  /* ---------- interaksi: mouse, seret, scroll ---------- */
  var mouseX = 0, mouseY = 0, sudut = 0.6, sudutTarget = 0.6, seret = false, seretX = 0, sudahSeret = false;
  var scrollP = 0;
  var raycaster = new THREE.Raycaster();
  var penunjuk = new THREE.Vector2(-9, -9);
  var disorot = null;

  var tip = document.createElement('div');
  tip.className = 'hero3d-tip';
  hero.appendChild(tip);

  var kanvas = renderer.domElement;
  hero.addEventListener('pointermove', function (e) {
    var r = hero.getBoundingClientRect();
    mouseX = (e.clientX - r.left) / r.width - 0.5;
    mouseY = (e.clientY - r.top) / r.height - 0.5;
    var rk = kanvas.getBoundingClientRect();
    penunjuk.x = ((e.clientX - rk.left) / rk.width) * 2 - 1;
    penunjuk.y = -((e.clientY - rk.top) / rk.height) * 2 + 1;
    tip.style.transform = 'translate(' + (e.clientX - r.left + 16) + 'px,' + (e.clientY - r.top + 16) + 'px)';
    if (seret) {
      sudutTarget += (e.clientX - seretX) * 0.008;
      if (Math.abs(e.clientX - seretX) > 2) sudahSeret = true;
      seretX = e.clientX;
    }
  });
  hero.addEventListener('pointerleave', function () { mouseX = mouseY = 0; penunjuk.set(-9, -9); seret = false; });
  kanvas.addEventListener('pointerdown', function (e) { seret = true; sudahSeret = false; seretX = e.clientX; hero.classList.add('menyeret'); });
  window.addEventListener('pointerup', function () { seret = false; hero.classList.remove('menyeret'); });
  kanvas.addEventListener('click', function () {
    if (!sudahSeret && disorot) window.location.href = 'denah.php';
  });
  window.addEventListener('scroll', function () {
    scrollP = Math.min(Math.max((window.scrollY || 0) / (hero.offsetHeight || 1), 0), 1);
  }, { passive: true });

  /* ---------- loop animasi ---------- */
  var jam = new THREE.Clock();
  var terlihat = true;
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { terlihat = en[0].isIntersecting; }).observe(hero);
  }

  var RADIUS = Math.max(LEBAR, DALAM) * 2.45;
  var pusat = new THREE.Vector3(0, 0.4, 0);

  function sorot() {
    raycaster.setFromCamera(penunjuk, kamera);
    var kena = raycaster.intersectObjects(rumahInteraktif, false)[0];
    var baru = kena ? kena.object : null;
    if (baru === disorot) return;
    if (disorot) disorot.userData.grup.userData.naik = false;
    disorot = baru;
    if (disorot) {
      var u = disorot.userData.unit;
      disorot.userData.grup.userData.naik = true;
      tip.innerHTML = '<b>Blok ' + u.blok + '-' + u.nomor + '</b><span>' + u.tipe + '</span>' +
        '<em class="st-' + u.status.toLowerCase() + '">' + u.status + '</em>';
      tip.classList.add('tampil');
      hero.classList.add('ada-sorot');
    } else {
      tip.classList.remove('tampil');
      hero.classList.remove('ada-sorot');
    }
  }

  function gambar() {
    requestAnimationFrame(gambar);
    if (!terlihat || document.hidden) return;
    var dt = Math.min(jam.getDelta(), 0.05);
    var waktu = jam.elapsedTime;

    terapkanTema(Math.min(1, dt * 3));

    // kamera: berputar pelan + ikut mouse + mundur saat di-scroll
    if (!geraklambat && !seret) sudutTarget += dt * 0.05;
    sudut += (sudutTarget - sudut) * 0.06;
    var az = sudut + mouseX * 0.35;
    var tinggi = 0.78 - mouseY * 0.1 - scrollP * 0.3; // sudut pandang dari atas
    var rad = RADIUS * (1 + scrollP * 0.35) * (layarLebar ? 1 : 1.25);
    kamera.position.set(Math.sin(az) * Math.cos(tinggi) * rad, Math.sin(tinggi) * rad, Math.cos(az) * Math.cos(tinggi) * rad);
    kamera.lookAt(pusat);

    if (!geraklambat) {
      penanda.forEach(function (p) { p.position.y = 2.55 + Math.sin(waktu * 2 + p.userData.fase) * 0.12; p.rotation.y += dt * 1.2; });
      pohon.forEach(function (p) { p.rotation.z = Math.sin(waktu * 1.3 + p.userData.fase) * 0.03; });
      awan.forEach(function (a) {
        a.position.x += a.userData.laju * dt;
        if (a.position.x > LEBAR) a.position.x = -LEBAR;
      });
      mobil.forEach(function (m) {
        var d = m.userData;
        d.x += d.arah * d.laju * dt;
        if (d.x > LEBAR / 2 + 1) d.x = -LEBAR / 2 - 1;
        if (d.x < -LEBAR / 2 - 1) d.x = LEBAR / 2 + 1;
        m.position.set(d.x, 0, d.z);
        m.rotation.y = d.arah > 0 ? 0 : Math.PI;
        m.visible = Math.abs(d.x) < LEBAR / 2 - 0.2;
      });
    }

    // rumah yang disorot sedikit terangkat
    rumahInteraktif.forEach(function (b) {
      var g = b.userData.grup;
      var tuju = g.userData.naik ? 0.35 : 0;
      g.position.y += (tuju - g.position.y) * 0.15;
    });

    sorot();
    renderer.render(scene, kamera);
  }

  hero.classList.add('is-3d');
  gambar();
})();