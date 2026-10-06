<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login &mdash; i-Finance</title>
@include('layouts.partials.styles')
<style>
  /* ---- Halaman login dua panel ----
     Kiri (60%): ilustrasi di atas latar terang. Kanan (40%): logo, nama
     aplikasi, dan kartu formulir di atas latar navy. Permukaan terang dan
     tulisannya memakai token (--surface, --ink, --tegas, ...) seperti
     halaman lain; panel navy dan isinya memang tetap gelap di semua mode. */
  #auth-screen{position:fixed;inset:0;z-index:200;overflow:auto;background:#0b1a30;}
  /* Tinggi minimal selayar, tetapi boleh memanjang: di layar pendek isi
     panel melebihi tinggi layar, dan tanpa ini bagian atasnya terpotong
     tanpa bisa digulung. Kedua panel ikut memanjang bersama. */
  .lg-rangka{display:flex;min-height:100%;}

  /* -- Panel kiri -- */
  .lg-kiri{position:relative;flex:0 0 60%;min-width:0;display:flex;flex-direction:column;justify-content:space-between;
    padding:26px 40px 40px;overflow:hidden;background:var(--surface);color:var(--ink);}
  .lg-cahaya{position:absolute;width:384px;height:384px;border-radius:50%;filter:blur(64px);pointer-events:none;}
  .lg-cahaya.a{top:-128px;left:-128px;background:rgba(191,219,254,.5);animation:lg-denyut 5s ease-in-out infinite;}
  .lg-cahaya.b{bottom:-96px;right:40px;background:rgba(186,230,253,.4);}
  .lg-atas{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:12px;}
  .lg-online{display:inline-flex;align-items:center;gap:11px;padding:6px 14px;border-radius:50px;background:rgba(255,255,255,.9);
    border:1px solid var(--line);box-shadow:0 1px 2px rgba(15,23,42,.06);font-size:12px;font-weight:600;letter-spacing:.4px;color:var(--ink);text-transform:uppercase;}
  .lg-titik{position:relative;width:10px;height:10px;border-radius:50%;background:#10b981;}
  .lg-titik::after{content:"";position:absolute;inset:0;border-radius:50%;background:#34d399;animation:lg-ping 1.6s cubic-bezier(0,0,.2,1) infinite;}
  .lg-prov{font-size:12px;font-weight:500;color:var(--mut);}
  .lg-tengah{position:relative;z-index:1;flex:1 1 auto;padding:24px 0;display:flex;flex-direction:column;align-items:center;}
  /* Isi panel sengaja duduk DI ATAS titik tengah: ruang di bawahnya dua
     kali ruang di atasnya. Tepat di tengah membuat bagian atas layar
     terasa kosong. */
  .lg-tengah::before{content:"";flex:1 1 0;}
  .lg-tengah::after{content:"";flex:2 1 0;}
  .lg-ilustrasi{width:100%;max-width:580px;height:auto;max-height:400px;overflow:visible;}
  .lg-judul{margin:22px 0 0;text-align:center;font-size:30px;line-height:1.3;font-weight:700;letter-spacing:-.4px;color:var(--tegas);}
  .lg-judul span{display:block;font-weight:400;}
  .lg-anak{margin:12px 0 0;text-align:center;font-size:15.5px;color:var(--mut);}
  .lg-garis{position:relative;z-index:1;border-top:1px solid var(--line);}

  /* Ilustrasi melayang naik-turun tanpa henti. Tiap benda punya tempo dan
     jeda sendiri supaya tidak bergerak serempak; bayangan di lantai
     mengecil saat bendanya naik dan melebar saat turun. */
  .lg-apung{animation:lg-apung 3.6s ease-in-out infinite;}
  .lg-apung.kalk{animation-name:lg-apung-kalk;animation-duration:4.4s;animation-delay:-1.5s;}
  .lg-apung.chart{animation-name:lg-apung-chart;animation-duration:3.1s;animation-delay:-.8s;}
  .lg-bayang{transform-box:fill-box;transform-origin:center;animation:lg-bayang 3.6s ease-in-out infinite;}
  .lg-bayang.kalk{animation-duration:4.4s;animation-delay:-1.5s;}
  .lg-bayang.chart{animation-duration:3.1s;animation-delay:-.8s;}
  @keyframes lg-apung{0%,100%{transform:translateY(0);}50%{transform:translateY(-24px);}}
  @keyframes lg-apung-kalk{0%,100%{transform:translateY(0);}50%{transform:translateY(-30px);}}
  @keyframes lg-apung-chart{0%,100%{transform:translateY(0);}50%{transform:translateY(-35px);}}
  @keyframes lg-bayang{0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(.74);opacity:.56;}}
  @keyframes lg-denyut{0%,100%{opacity:.35;transform:scale(1);}50%{opacity:.7;transform:scale(1.08);}}
  @keyframes lg-ping{75%,100%{transform:scale(2.2);opacity:0;}}

  /* -- Panel kanan -- */
  .lg-kanan{position:relative;flex:1 1 40%;min-width:0;display:flex;flex-direction:column;align-items:center;
    padding:48px;overflow:hidden;color:#fff;background:linear-gradient(180deg,#0b1a30 0%,#0d213f 50%,#0a182c 100%);}
  .lg-kanan::before{content:"";position:absolute;top:-96px;right:-96px;width:320px;height:320px;border-radius:50%;
    background:rgba(14,165,233,.1);filter:blur(64px);pointer-events:none;}
  .lg-kepala{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;text-align:center;}
  .lg-logo{width:96px;height:auto;display:block;margin-bottom:12px;}
  .lg-nama{margin:0 0 4px;font-size:38px;line-height:1.15;font-weight:600;letter-spacing:-.4px;color:#fff;}
  .lg-unit{font-size:14.5px;font-weight:500;color:rgba(203,213,225,.92);line-height:1.6;}
  .lg-instansi{font-size:12.5px;color:#94a3b8;}

  .lg-kartu{position:relative;z-index:1;width:100%;max-width:448px;margin:36px 0 28px;padding:32px;background:var(--surface);color:var(--tegas);
    border-radius:16px;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 50px -12px rgba(0,0,0,.5);}
  .lg-title{font-size:20px;font-weight:700;color:var(--tegas);letter-spacing:-.3px;line-height:1.3;}
  .lg-subtitle{font-size:12.5px;color:var(--mut);margin:4px 0 22px;line-height:1.5;}
  .lg-subtitle button{font:inherit;font-weight:600;color:var(--ink);background:none;border:none;padding:0;cursor:pointer;}
  .lg-subtitle button:hover{text-decoration:underline;color:var(--tegas);}
  .lg-kartu label.fl{font-size:12.5px;font-weight:600;color:var(--ink);margin:16px 0 6px;}
  .lg-kartu label.fl:first-of-type{margin-top:0;}

  /* Kolom isian dengan ikon di kiri; kolom password juga punya tombol
     mata di kanan. Ikon mata = password masih tersembunyi (tekan untuk
     melihat); mata dicoret = sedang terlihat. */
  .lg-isian{position:relative;}
  .lg-isian > svg{position:absolute;top:50%;left:14px;width:16px;height:16px;transform:translateY(-50%);pointer-events:none;
    stroke:#94a3b8;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
  .lg-isian input{width:100%;box-sizing:border-box;padding:11px 12px 11px 40px;font-size:14px;color:var(--tegas);
    background:var(--surface-2);border:1px solid var(--line);border-radius:8px;box-shadow:0 1px 2px rgba(15,23,42,.05);}
  .lg-isian input::placeholder{color:#94a3b8;}
  .lg-isian input:focus{outline:none;background:var(--surface);border-color:var(--aksen);box-shadow:0 0 0 3px rgba(2,132,199,.22);}
  .lg-isian.sandi input{padding-right:44px;}
  .lg-mata{position:absolute;top:0;right:0;bottom:0;width:42px;display:flex;align-items:center;justify-content:center;
    background:none;border:none;padding:0;cursor:pointer;color:#94a3b8;border-radius:0 8px 8px 0;}
  .lg-mata:hover,.lg-mata:focus-visible{color:var(--mut);}
  .lg-mata svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
  .lg-mata .mata-tutup,.lg-mata.terlihat .mata-buka{display:none;}
  .lg-mata.terlihat .mata-tutup{display:block;}

  .lg-help{text-align:right;margin-top:14px;}
  .lg-help a{font-size:12.5px;color:var(--ink);text-decoration:none;font-weight:600;}
  .lg-help a:hover{color:var(--aksen);}
  .lg-btn{width:100%;margin-top:14px;padding:12px 18px;display:flex;align-items:center;justify-content:center;gap:8px;
    font:inherit;font-size:14px;font-weight:600;color:#fff;background:#112240;border:1px solid #112240;border-radius:8px;cursor:pointer;
    box-shadow:0 4px 8px -2px rgba(15,23,42,.3);transition:background .2s,box-shadow .2s,transform .1s;}
  .lg-btn:hover{background:#0b192e;box-shadow:0 10px 16px -4px rgba(15,23,42,.35);}
  .lg-btn:active{transform:scale(.99);}
  .lg-btn:focus-visible{outline:2px solid var(--tegas);outline-offset:2px;}

  .lg-bawah{position:relative;z-index:1;width:100%;display:flex;flex-direction:column;align-items:center;}
  /* Kaki menempel di dasar panel; tombol Pengguna Layanan tetap dekat kartu. */
  .lg-kaki{margin-top:auto;padding-top:20px;}
  .lg-bawah{flex:1 1 auto;}
  .lg-layanan{width:100%;max-width:448px;padding:12px 20px;border-radius:50px;font:inherit;font-size:14px;
    font-weight:600;color:#fff;cursor:pointer;background:rgba(8,47,73,.5);border:1px solid rgba(56,189,248,.4);
    box-shadow:inset 0 2px 4px rgba(0,0,0,.2);transition:background .2s,border-color .2s;}
  .lg-layanan:hover{background:rgba(12,74,110,.6);border-color:#7dd3fc;}
  .lg-layanan:focus-visible{outline:2px solid #7dd3fc;outline-offset:2px;}
  .lg-kaki{font-size:11px;color:rgba(148,163,184,.8);text-align:center;}

  /* Layar sempit: formulirnya yang penting, jadi ilustrasi disembunyikan
     dan panel navy mengisi seluruh layar. */
  @media (max-width:960px){
    .lg-kiri{display:none;}
    .lg-kanan{flex-basis:100%;padding:32px 20px 24px;}
    .lg-kartu{padding:26px 22px;}
    .lg-nama{font-size:32px;}
  }
  @media (min-width:961px) and (max-height:760px){
    .lg-kiri{padding-top:20px;padding-bottom:24px;}
    .lg-kanan{padding-top:28px;padding-bottom:24px;}
    .lg-ilustrasi{max-height:300px;}
    .lg-logo{width:72px;}
    .lg-kartu{margin:16px 0;padding:26px 30px;}
  }

  /* ---- Jendela kata sandi Pengguna Layanan ----
     Muncul dengan fade in: latar meredup, kartunya ikut naik sedikit. Kelas
     .tampil dipasang lewat JS supaya transisinya sempat berjalan (kalau
     display langsung diubah dari none, peramban melewati animasinya). */
  .gl-ov{position:fixed;inset:0;z-index:400;display:none;align-items:center;justify-content:center;padding:18px;
    background:rgba(8,20,36,.62);backdrop-filter:blur(3px);opacity:0;transition:opacity .28s ease;}
  .gl-ov.siap{display:flex;}
  .gl-ov.tampil{opacity:1;}
  .gl-kartu{width:100%;max-width:392px;background:var(--surface);border-radius:18px;padding:30px 28px 24px;text-align:center;
    box-shadow:0 26px 64px rgba(0,0,0,.42);transform:translateY(16px) scale(.965);opacity:0;
    transition:transform .32s cubic-bezier(.2,.8,.3,1), opacity .28s ease;}
  .gl-ov.tampil .gl-kartu{transform:none;opacity:1;}
  .gl-ikon{width:56px;height:56px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(140deg,var(--info-bg),var(--info-bg));color:var(--tegas);}
  .gl-ikon svg{width:26px;height:26px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
  .gl-judul{font-size:18px;font-weight:800;color:var(--tegas);letter-spacing:-.2px;}
  .gl-ket{font-size:12.5px;color:var(--mut);margin:6px 0 18px;line-height:1.5;}
  .gl-kartu input[type="password"]{width:100%;box-sizing:border-box;text-align:center;letter-spacing:2px;font-size:15px;padding:12px 14px;}
  .gl-salah{display:none;margin-top:10px;font-size:12.5px;font-weight:600;color:var(--err);}
  .gl-salah.ada{display:block;}
  .gl-aksi{display:flex;gap:10px;margin-top:18px;}
  .gl-aksi .btn{flex:1;justify-content:center;padding:11px 16px;}
  @media (prefers-reduced-motion:reduce){
    .gl-ov,.gl-kartu{transition:none;}
    .lg-apung,.lg-bayang,.lg-cahaya.a,.lg-titik::after{animation:none;}
  }
</style>
</head>
<body>

<div id="auth-screen">
<div class="lg-rangka">
  <section class="lg-kiri" aria-hidden="true">
    <div class="lg-cahaya a"></div>
    <div class="lg-cahaya b"></div>

    <div class="lg-atas">
      <div class="lg-online"><span class="lg-titik"></span>Online</div>
      <div class="lg-prov">Pemerintah Provinsi Jawa Barat</div>
    </div>

    <div class="lg-tengah">
      {{-- Tiap benda yang punya posisi sendiri dibungkus dua lapis <g>:
           lapis luar memegang posisinya (atribut transform), lapis dalam
           yang dianimasikan. Kalau digabung, transform dari CSS menimpa
           posisi itu dan bendanya melompat ke pojok. --}}
      <svg class="lg-ilustrasi" fill="none" viewBox="0 0 740 520" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient gradientUnits="userSpaceOnUse" id="lgPena" x1="140" x2="220" y1="90" y2="340">
            <stop stop-color="#38bdf8"></stop>
            <stop offset="0.6" stop-color="#0284c7"></stop>
            <stop offset="1" stop-color="#0369a1"></stop>
          </linearGradient>
          <linearGradient gradientUnits="userSpaceOnUse" id="lgKalk" x1="470" x2="600" y1="210" y2="420">
            <stop stop-color="#ffffff"></stop>
            <stop offset="1" stop-color="#e2e8f0"></stop>
          </linearGradient>
          <filter height="150%" id="lgLembut" width="150%" x="-25%" y="-15%">
            <feDropShadow dx="0" dy="12" flood-color="#0f172a" flood-opacity="0.14" stdDeviation="16"></feDropShadow>
          </filter>
        </defs>

        {{-- Bayangan di lantai: satu per benda, bergerak seirama dengannya. --}}
        <ellipse class="lg-bayang" cx="345" cy="480" fill="#cbd5e1" fill-opacity="0.65" rx="235" ry="22"></ellipse>
        <ellipse class="lg-bayang kalk" cx="560" cy="476" fill="#cbd5e1" fill-opacity="0.6" rx="88" ry="15"></ellipse>
        <ellipse class="lg-bayang chart" cx="320" cy="200" fill="#cbd5e1" fill-opacity="0.45" rx="72" ry="9"></ellipse>

        {{-- Buku besar + pena bulu --}}
        <g class="lg-apung">
          <g filter="url(#lgLembut)">
            <path d="M190 395 C280 390, 360 415, 370 422 C380 415, 460 390, 550 395 L565 245 C475 240, 385 268, 370 274 C355 268, 265 240, 175 245 Z" fill="#0f172a" opacity="0.3"></path>
            <path d="M185 240 C270 235, 355 260, 370 270 L370 410 C355 400, 270 375, 185 380 Z" fill="#fffdfa" stroke="#e2e8f0" stroke-width="3"></path>
            <path d="M370 270 C385 260, 470 235, 555 240 L555 380 C470 375, 385 400, 370 410 Z" fill="#f8fafc" stroke="#e2e8f0" stroke-width="3"></path>
            <line stroke="#cbd5e1" stroke-linecap="round" stroke-width="4" x1="370" x2="370" y1="270" y2="410"></line>
            <line stroke="#94a3b8" stroke-linecap="round" stroke-width="2.5" x1="220" x2="340" y1="275" y2="288"></line>
            <line stroke="#cbd5e1" stroke-linecap="round" stroke-width="2" x1="215" x2="345" y1="300" y2="313"></line>
            <line stroke="#cbd5e1" stroke-linecap="round" stroke-width="2" x1="215" x2="340" y1="325" y2="338"></line>
            <line stroke="#cbd5e1" stroke-linecap="round" stroke-width="2" x1="220" x2="330" y1="350" y2="361"></line>
            <line stroke="#94a3b8" stroke-linecap="round" stroke-width="2.5" x1="400" x2="520" y1="288" y2="275"></line>
            <line stroke="#cbd5e1" stroke-linecap="round" stroke-width="2" x1="395" x2="525" y1="313" y2="300"></line>
            <line stroke="#cbd5e1" stroke-linecap="round" stroke-width="2" x1="395" x2="520" y1="338" y2="325"></line>
            <line stroke="#cbd5e1" stroke-linecap="round" stroke-width="2" x1="400" x2="510" y1="361" y2="350"></line>
            <circle cx="485" cy="335" fill="#10b981" fill-opacity="0.12" r="20" stroke="#10b981" stroke-dasharray="3 2" stroke-width="2"></circle>
            <path d="M476 335 L482 341 L494 329" stroke="#059669" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.8"></path>
          </g>
          <g transform="translate(40, -10)">
            <path d="M125 350 C132 315, 142 220, 205 130 C207 127, 212 126, 214 130 C216 142, 212 185, 195 240 C182 285, 160 330, 145 355 Z" fill="url(#lgPena)"></path>
            <path d="M125 350 L145 355 L120 375 Z" fill="#0f172a"></path>
            <path d="M120 375 L117 380" stroke="#0284c7" stroke-linecap="round" stroke-width="2"></path>
            <path d="M125 350 C145 285, 175 210, 208 128" opacity="0.75" stroke="#ffffff" stroke-linecap="round" stroke-width="2"></path>
          </g>
        </g>

        {{-- Kalkulator --}}
        <g class="lg-apung kalk">
          <g filter="url(#lgLembut)">
            <rect fill="url(#lgKalk)" height="180" rx="16" stroke="#cbd5e1" stroke-width="2.5" width="125" x="495" y="240"></rect>
            <rect fill="#0f172a" height="34" rx="6" width="95" x="510" y="258"></rect>
            <text fill="#38bdf8" font-family="monospace" font-size="16" font-weight="bold" text-anchor="end" x="590" y="282">279.9</text>
            <circle cx="525" cy="315" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="548" cy="315" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="571" cy="315" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="594" cy="315" fill="#0284c7" r="7.5"></circle>
            <circle cx="525" cy="338" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="548" cy="338" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="571" cy="338" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="594" cy="338" fill="#0284c7" r="7.5"></circle>
            <circle cx="525" cy="361" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="548" cy="361" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="571" cy="361" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="594" cy="361" fill="#38bdf8" r="7.5"></circle>
            <rect fill="#e2e8f0" height="15" rx="7.5" width="37" x="518" y="380"></rect>
            <circle cx="571" cy="387" fill="#e2e8f0" r="7.5"></circle>
            <circle cx="594" cy="387" fill="#10b981" r="7.5"></circle>
          </g>
        </g>

        {{-- Kartu grafik Realisasi Anggaran --}}
        <g transform="translate(240, 70)">
          <g class="lg-apung chart">
            <rect fill="#ffffff" filter="url(#lgLembut)" height="95" rx="12" stroke="#e2e8f0" stroke-width="2" width="160" x="0" y="0"></rect>
            <text fill="#64748b" font-size="11" font-weight="600" x="14" y="24">Realisasi Anggaran</text>
            <text fill="#0f172a" font-size="15" font-weight="700" x="14" y="46">98.4%</text>
            <rect fill="#cbd5e1" height="30" rx="3" width="10" x="92" y="52"></rect>
            <rect fill="#93c5fd" height="40" rx="3" width="10" x="107" y="42"></rect>
            <rect fill="#38bdf8" height="50" rx="3" width="10" x="122" y="32"></rect>
            <rect fill="#2563eb" height="56" rx="3" width="10" x="137" y="26"></rect>
          </g>
        </g>
      </svg>

      <h1 class="lg-judul">i-Finance<span>Inspectorate Finance</span></h1>
      <p class="lg-anak">Inspektorat Daerah Provinsi Jawa Barat</p>
    </div>

    <div class="lg-garis"></div>
  </section>

  <section class="lg-kanan">
    <header class="lg-kepala">
      {{-- Logo ditanam langsung (data URI), sama seperti logo di sidebar
           (layouts/app.blade.php). Sebagai berkas di public/ ia tidak muncul
           di server yang akar web-nya bukan folder public proyek ini. --}}
      <img class="lg-logo" src="data:image/webp;base64,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" alt="Logo Inspektorat Jabar" width="96" height="104">
      <div class="lg-nama">i-Finance</div>
      <div class="lg-unit">Subbagian Tata Usaha - Keuangan</div>
      <div class="lg-instansi">Inspektorat Daerah Provinsi Jawa Barat</div>
    </header>

    <div class="lg-kartu">
      <div class="lg-title">Selamat datang, Akang/Teteh!</div>
      <div class="lg-subtitle">Silakan login atau <button type="button" data-gl-buka>Masuk sebagai Pengguna Layanan</button></div>
      <div id="auth-login">
        <form method="POST" action="{{ route('login') }}">
          @csrf
          <label class="fl" for="auth-user">Username</label>
          <div class="lg-isian">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/></svg>
            <input type="text" name="username" id="auth-user" placeholder="username" autocomplete="off" value="{{ old('username') }}" autofocus>
          </div>
          <label class="fl" for="auth-pass">Password</label>
          <div class="lg-isian sandi">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/></svg>
            <input type="password" name="password" id="auth-pass" placeholder="password">
            <button type="button" class="lg-mata" id="auth-mata" aria-label="Tampilkan password" aria-pressed="false" title="Tampilkan password">
              <svg class="mata-buka" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="mata-tutup" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <div class="lg-help"><a href="https://wa.me/6281315763086" target="_blank" rel="noopener">Hubungi Admin</a></div>
          {{-- Hanya galat formulir login. Galat kata sandi Pengguna Layanan
               tampil di jendelanya sendiri, bukan di sini. --}}
          @if ($errors->has('username') || $errors->has('password'))
            <div class="err-box" id="auth-err" style="display:block;">{{ $errors->first() }}</div>
          @else
            <div class="err-box" id="auth-err"></div>
          @endif
          <button type="submit" class="lg-btn" id="auth-submit">Masuk</button>
        </form>
      </div>
    </div>

    <div class="lg-bawah">
      <button type="button" class="lg-layanan" id="gl-buka" data-gl-buka>Masuk sebagai Pengguna Layanan</button>
      <div class="lg-kaki">&copy; {{ date('Y') }} Inspektorat Daerah Provinsi Jawa Barat. All rights reserved.</div>
    </div>
  </section>
</div>
</div>

{{-- Pengguna Layanan tidak punya akun, tapi tetap harus memasukkan kata sandi
     bersama. Yang menilai benar/salahnya peladen (AuthController::masukLayanan);
     jendela ini semata-mata tampilan. --}}
<div class="gl-ov" id="gl-ov" role="dialog" aria-modal="true" aria-labelledby="gl-judul">
  <form class="gl-kartu" method="POST" action="{{ route('layanan.masuk') }}">
    @csrf
    <div class="gl-ikon">
      <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <div class="gl-judul" id="gl-judul">Masukkan Kata Sandi</div>
    <div class="gl-ket">Pengguna Layanan tidak perlu mendaftar. Cukup masukkan kata sandi yang dibagikan.</div>
    <input type="password" name="sandi" id="gl-sandi" placeholder="Kata sandi" autocomplete="off" required>
    <div class="gl-salah {{ $errors->has('sandi') ? 'ada' : '' }}" id="gl-salah">{{ $errors->first('sandi') ?: 'Kata sandi salah.' }}</div>
    <div class="gl-aksi">
      <button type="button" class="btn" id="gl-batal">Batal</button>
      <button type="submit" class="btn prim">Masuk</button>
    </div>
  </form>
</div>

<script>
/* Lihat/sembunyikan password di formulir login. Hanya mengganti jenis
   isiannya; nilainya tidak disentuh dan tidak dikirim ke mana pun. */
(function () {
  var isian = document.getElementById('auth-pass');
  var mata = document.getElementById('auth-mata');

  mata.addEventListener('click', function () {
    var terlihat = isian.type === 'password';
    isian.type = terlihat ? 'text' : 'password';
    mata.classList.toggle('terlihat', terlihat);
    mata.setAttribute('aria-pressed', terlihat ? 'true' : 'false');
    mata.setAttribute('aria-label', terlihat ? 'Sembunyikan password' : 'Tampilkan password');
    mata.title = terlihat ? 'Sembunyikan password' : 'Tampilkan password';
    isian.focus();
  });

  // Password tidak boleh terkirim/tersimpan peramban sebagai teks biasa.
  isian.form.addEventListener('submit', function () { isian.type = 'password'; });
})();

(function () {
  var ov = document.getElementById('gl-ov');
  var batal = document.getElementById('gl-batal');
  var sandi = document.getElementById('gl-sandi');
  var salah = document.getElementById('gl-salah');

  function tampilkan() {
    ov.classList.add('siap');
    // Dipaksa reflow dulu supaya peramban mencatat keadaan awal (opacity 0)
    // dan benar-benar menganimasikan perubahannya, bukan melompat.
    void ov.offsetWidth;
    ov.classList.add('tampil');
    setTimeout(function () { sandi.focus(); }, 60);
  }

  function sembunyikan() {
    ov.classList.remove('tampil');
    salah.classList.remove('ada');
    setTimeout(function () { ov.classList.remove('siap'); sandi.value = ''; }, 300);
  }

  // Dua pintu ke jendela yang sama: tautan di dalam kartu dan tombol pil di bawahnya.
  document.querySelectorAll('[data-gl-buka]').forEach(function (el) { el.addEventListener('click', tampilkan); });
  batal.addEventListener('click', sembunyikan);
  ov.addEventListener('click', function (e) { if (e.target === ov) sembunyikan(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ov.classList.contains('tampil')) sembunyikan();
  });

  @if ($errors->has('sandi') || session('buka_gerbang_layanan'))
    tampilkan();
  @endif
})();
</script>

</body>
</html>
