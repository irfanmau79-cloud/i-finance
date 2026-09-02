@extends('layouts.app')

@section('activeNav', $navKey)
@section('title', $judul)

@section('content')
<style>
  .up-wrap{display:flex;align-items:center;justify-content:center;min-height:56vh;padding:24px;}
  .up-kartu{max-width:520px;width:100%;text-align:center;padding:44px 34px;border:1px solid var(--line);
    border-radius:18px;background:var(--surface);box-shadow:var(--shadow);}

  /* Ikon gir yang berputar pelan - penanda "sedang dikerjakan" tanpa
     mengganggu; berhenti berputar bila pengguna meminta gerakan dikurangi. */
  .up-gir{position:relative;width:76px;height:76px;margin:0 auto 20px;}
  .up-gir svg{position:absolute;inset:0;width:100%;height:100%;fill:none;stroke:var(--tegas);stroke-width:1.4;
    stroke-linecap:round;stroke-linejoin:round;animation:up-putar 7s linear infinite;}
  .up-gir svg.kecil{width:38px;height:38px;inset:auto -4px -4px auto;stroke:var(--gold);stroke-width:1.7;
    animation:up-putar-balik 5s linear infinite;}
  @keyframes up-putar{to{transform:rotate(360deg);}}
  @keyframes up-putar-balik{to{transform:rotate(-360deg);}}

  .up-judul{font-size:20px;font-weight:800;color:var(--tegas);}
  .up-modul{display:inline-block;margin-top:10px;padding:4px 12px;border-radius:20px;background:var(--navy-l);
    color:var(--tegas);font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;}
  .up-teks{margin-top:14px;color:var(--mut);font-size:13.5px;line-height:1.6;}

  /* Bar tak-tentu: bergerak terus, tidak menyiratkan persentase kemajuan. */
  .up-bar{position:relative;height:5px;border-radius:3px;background:var(--surface-3);overflow:hidden;margin-top:24px;}
  .up-bar i{position:absolute;top:0;bottom:0;width:38%;border-radius:3px;
    background:linear-gradient(90deg,var(--navy),var(--gold));animation:up-geser 1.9s ease-in-out infinite;}
  @keyframes up-geser{0%{left:-40%;}100%{left:102%;}}

  .up-titik{display:inline-flex;gap:4px;margin-left:2px;vertical-align:middle;}
  .up-titik i{width:4px;height:4px;border-radius:50%;background:var(--mut);animation:up-kedip 1.4s ease-in-out infinite;}
  .up-titik i:nth-child(2){animation-delay:.2s;}
  .up-titik i:nth-child(3){animation-delay:.4s;}
  @keyframes up-kedip{0%,80%,100%{opacity:.25;transform:translateY(0);}40%{opacity:1;transform:translateY(-2px);}}

  @media (prefers-reduced-motion: reduce){
    .up-gir svg,.up-bar i,.up-titik i{animation:none;}
    .up-bar i{left:0;width:100%;}
  }
</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / {{ $modul }} / <b>{{ $judul }}</b></div>
    <div class="ph-title">{{ $judul }}</div>
  </div>
</div>

<div class="up-wrap">
  <div class="up-kartu">
    <div class="up-gir" aria-hidden="true">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3.2"/>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
      </svg>
      <svg class="kecil" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3.2"/>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
      </svg>
    </div>

    <div class="up-judul">Under Progress<span class="up-titik"><i></i><i></i><i></i></span></div>
    <div class="up-modul">{{ $modul }}</div>
    <div class="up-teks">Halaman <strong>{{ $judul }}</strong> sedang disiapkan. Menunya sudah tersedia agar alurnya terlihat, isinya menyusul.</div>

    <div class="up-bar" role="progressbar" aria-label="Sedang dikerjakan"><i></i></div>
  </div>
</div>
@endsection
