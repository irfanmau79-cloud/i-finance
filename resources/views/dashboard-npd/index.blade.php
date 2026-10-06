@extends('layouts.app')

@section('activeNav', 'dashnpd')
@section('title', 'Dashboard Nota Pencairan Dana')

@section('content')
@php
    $rupiah = fn ($n) => 'Rp '.number_format((float) $n, 2, ',', '.');
    $namaBulan = \App\Services\AnggaranRealisasiService::BULAN;
    $kpi = $dashboard['kpi'];
    $adaSaringan = collect($filters)->filter()->isNotEmpty();

    // Kartu NPD Selesai / Dalam Proses adalah sakelar saringan status:
    // ditekan sekali menyaring, ditekan lagi melepasnya. Saringan lain ikut
    // terbawa supaya menekan kartu tidak mengulang dari awal.
    $tautanStatus = function (string $status) use ($filters) {
        $q = array_filter(array_merge($filters, ['status' => $filters['status'] === $status ? '' : $status]));

        return route('dashboard.npd.index', $q).'#dnpd-rincian';
    };
@endphp
<style>
  .dnpd-filter{display:grid;grid-template-columns:170px 170px 1fr 1.3fr auto;gap:12px;align-items:end;}
  .dnpd-filter label{display:block;font-size:12px;font-weight:700;color:var(--tegas);margin-bottom:5px;}
  .dnpd-filter select,.dnpd-filter input{width:100%;box-sizing:border-box;}
  .dnpd-filter .aksi{display:flex;gap:6px;white-space:nowrap;}
  @media(max-width:1100px){.dnpd-filter{grid-template-columns:1fr 1fr;}}
  @media(max-width:600px){.dnpd-filter{grid-template-columns:1fr;}}

  /* Tiga kartu saja, jadi kisi KPI bawaan (empat kolom) dipersempit. */
  .dnpd-kpi{grid-template-columns:repeat(3,1fr);}
  @media(max-width:900px){.dnpd-kpi{grid-template-columns:1fr;}}
  a.kpi.dnpd-klik{display:block;text-decoration:none;color:inherit;cursor:pointer;transition:transform .15s,box-shadow .15s,border-color .15s;}
  a.kpi.dnpd-klik:hover{transform:translateY(-2px);border-color:var(--kc,var(--aksen));}
  a.kpi.dnpd-klik:focus-visible{outline:2px solid var(--aksen);outline-offset:2px;}
  a.kpi.dnpd-klik.aktif{border-color:var(--kc,var(--aksen));box-shadow:0 0 0 2px var(--kbg,var(--aksen-l)),var(--shadow);}
  .dnpd-petunjuk{margin-top:var(--sp-2);font-size:11px;color:var(--mut);}
  a.kpi.dnpd-klik.aktif .dnpd-petunjuk{color:var(--kc,var(--aksen));font-weight:700;}
  .dnpd-rumus{margin-top:var(--sp-2);font-size:11px;color:var(--mut);line-height:1.5;}

  /* Dua kelompok rincian yang bisa dibuka-tutup. */
  .dnpd-kel{margin-bottom:16px;padding:0;overflow:hidden;}
  .dnpd-kel > summary{display:flex;align-items:center;gap:12px;padding:16px 20px;list-style:none;cursor:pointer;user-select:none;}
  .dnpd-kel > summary::-webkit-details-marker{display:none;}
  .dnpd-kel > summary:hover{background:var(--surface-2);}
  .dnpd-kel > summary:focus-visible{outline:2px solid var(--aksen);outline-offset:-2px;}
  .dnpd-kel .panah{width:18px;height:18px;flex:0 0 18px;stroke:var(--mut);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform .18s;}
  .dnpd-kel[open] .panah{transform:rotate(90deg);}
  .dnpd-kel .judul{font-size:15px;font-weight:700;color:var(--tegas);}
  .dnpd-kel .jml{padding:2px 10px;border-radius:50px;background:var(--navy-l);color:var(--tegas);font-size:11px;font-weight:700;}
  .dnpd-kel .total{margin-left:auto;font-size:13.5px;font-weight:700;color:var(--tegas);font-variant-numeric:tabular-nums;white-space:nowrap;}
  .dnpd-isi{padding:0 20px 18px;border-top:1px solid var(--line);}
  .dnpd-scroll{overflow:auto;margin-top:14px;border:1px solid var(--line);border-radius:8px;}
  table.dnpd-tabel{min-width:940px;table-layout:fixed;}
  table.dnpd-tabel td{vertical-align:top;overflow-wrap:anywhere;}
  table.dnpd-tabel .nm{font-weight:700;color:var(--tegas);}
  table.dnpd-tabel .sub-nm{display:block;margin-top:2px;font-size:11px;color:var(--mut);}
  .dnpd-lihat{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;font-size:12px;font-weight:600;white-space:nowrap;
    color:var(--tegas);text-decoration:none;background:var(--surface-2);border:1px solid var(--line);border-radius:8px;}
  .dnpd-lihat:hover{background:var(--surface-3);border-color:var(--aksen);}
  .dnpd-lihat svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
  .dnpd-kosong{padding:26px;text-align:center;color:var(--mut);}
</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Dashboard Nota Pencairan Dana</b></div>
    <div class="ph-title">Dashboard Nota Pencairan Dana</div>
  </div>
  <div class="ph-actions">
    <a class="btn" href="{{ route('dashboard.npd.index') }}" style="white-space:nowrap;">&#8635; Muat Ulang</a>
  </div>
</div>

<div class="dash-card"><form method="GET" action="{{ route('dashboard.npd.index') }}" class="dnpd-filter">
  <div>
    <label for="dnpd-status">Status</label>
    <select id="dnpd-status" name="status">
      <option value="">Semua Status</option>
      <option value="selesai" @selected($filters['status'] === 'selesai')>NPD Selesai</option>
      <option value="proses" @selected($filters['status'] === 'proses')>NPD Dalam Proses</option>
    </select>
  </div>
  <div>
    <label for="dnpd-bulan">Bulan</label>
    <select id="dnpd-bulan" name="bulan">
      <option value="">Semua Bulan</option>
      @foreach ($dashboard['pilihan_bulan'] as $b)
        <option value="{{ $b }}" @selected($filters['bulan'] === (string) $b)>{{ $namaBulan[$b - 1] ?? $b }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label for="dnpd-unit">Unit Kerja</label>
    <select id="dnpd-unit" name="unit" data-cari>
      <option value="">Semua Unit Kerja</option>
      @foreach ($dashboard['pilihan_unit'] as $unit)
        <option value="{{ $unit }}" @selected($filters['unit'] === $unit)>{{ $unit }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label for="dnpd-cari">Pencarian</label>
    <input id="dnpd-cari" name="cari" value="{{ $filters['cari'] }}" placeholder="Nomor NPD/SP, penerima, uraian">
  </div>
  <div class="aksi">
    <button class="btn prim" type="submit">Terapkan</button>
    <a class="btn" href="{{ route('dashboard.npd.index') }}">Reset</a>
  </div>
</form></div>

<div class="kpi-grid dnpd-kpi">
  <a class="kpi dnpd-klik{{ $filters['status'] === 'selesai' ? ' aktif' : '' }}" href="{{ $tautanStatus('selesai') }}"
     style="--kc:#166534;--kbg:#16653424;" aria-pressed="{{ $filters['status'] === 'selesai' ? 'true' : 'false' }}">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="kpi-lbl">NPD Selesai</div></div></div>
    <div class="kpi-val">{{ number_format($kpi['selesai']['jumlah'], 0, ',', '.') }} <span style="font-size:13px;font-weight:600;color:var(--mut);">NPD</span></div>
    <div class="kpi-note">{{ $rupiah($kpi['selesai']['nominal']) }}</div>
    <div class="dnpd-petunjuk">{{ $filters['status'] === 'selesai' ? 'Sedang disaring - klik untuk melepas' : 'Klik untuk menyaring rincian' }}</div>
  </a>
  <a class="kpi dnpd-klik{{ $filters['status'] === 'proses' ? ' aktif' : '' }}" href="{{ $tautanStatus('proses') }}"
     style="--kc:#b45309;--kbg:#b4530924;" aria-pressed="{{ $filters['status'] === 'proses' ? 'true' : 'false' }}">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="kpi-lbl">NPD Dalam Proses</div></div></div>
    <div class="kpi-val">{{ number_format($kpi['proses']['jumlah'], 0, ',', '.') }} <span style="font-size:13px;font-weight:600;color:var(--mut);">NPD</span></div>
    <div class="kpi-note">{{ $rupiah($kpi['proses']['nominal']) }}</div>
    <div class="dnpd-petunjuk">{{ $filters['status'] === 'proses' ? 'Sedang disaring - klik untuk melepas' : 'Klik untuk menyaring rincian' }}</div>
  </a>
  <div class="kpi" style="--kc:#1b3161;--kbg:#1b316118;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></div><div><div class="kpi-lbl">Sisa Uang Persediaan (IBC)</div></div></div>
    <div class="kpi-val">{{ $rupiah($kpi['sisa_up']['sisa']) }}</div>
    <div class="dnpd-rumus">
      SP2D UP/GU/TU {{ $rupiah($kpi['sisa_up']['sp2d']) }}<br>
      dikurangi NPD Selesai {{ $rupiah($kpi['sisa_up']['npd_selesai']) }}
    </div>
  </div>
</div>

<div id="dnpd-rincian">
@foreach ($dashboard['kelompok'] as $kelompok)
  {{-- Tertutup saat halaman baru dibuka; langsung terbuka begitu ada
       saringan, karena saat itu hasilnyalah yang dicari. --}}
  <details class="dash-card dnpd-kel" data-dnpd-kel{!! $adaSaringan ? ' open' : '' !!}>
    <summary>
      <svg class="panah" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
      <span class="judul">{{ $kelompok['label'] }}</span>
      <span class="jml">{{ number_format($kelompok['jumlah'], 0, ',', '.') }} NPD</span>
      <span class="total">{{ $rupiah($kelompok['nominal']) }}</span>
    </summary>
    <div class="dnpd-isi">
      @if ($kelompok['rows'] === [])
        <div class="dnpd-kosong">Tidak ada NPD pada kelompok ini{{ $adaSaringan ? ' untuk saringan yang dipilih' : '' }}.</div>
      @else
        <div class="dnpd-scroll">
          <table class="realisasi dnpd-tabel">
            <colgroup>
              <col style="width:10%;"><col style="width:17%;"><col style="width:13%;"><col style="width:13%;">
              <col style="width:13%;"><col style="width:23%;"><col style="width:11%;">
            </colgroup>
            <thead>
              <tr>
                <th>Tanggal NPD</th><th>Penerima</th><th>Unit Kerja</th><th class="num">Nominal</th>
                <th>Status</th><th>Uraian</th><th>Aksi</th>
              </tr>
            </thead>
            <tbody data-dnpd-body>
              @foreach ($kelompok['rows'] as $r)
                <tr>
                  <td>{{ $r['tanggal'] }}</td>
                  <td><span class="nm">{{ $r['penerima'] }}</span><span class="sub-nm">@if ($r['nomor'] !== '-'){{ $r['nomor'] }} &middot; @endif{{ $r['jenis'] }}</span></td>
                  <td>{{ $r['unit_kerja'] }}</td>
                  <td class="num">{{ $rupiah($r['nominal']) }}</td>
                  <td><span class="badge {{ $r['badge'] }}">{{ $r['status'] }}</span></td>
                  <td>{{ $r['uraian'] }}</td>
                  <td>
                    {{-- Seluruh kelengkapan dokumen dalam satu berkas, versi
                         terkini tanpa coretan: NPD, Lampiran, Daftar Bayar dan
                         SPD Rampung bila ada, lalu berkas SPJ yang diunggah. --}}
                    <a class="dnpd-lihat" href="{{ route('npd.cetak-gabungan', $r['id']) }}" target="_blank" rel="noopener">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      Lihat NPD
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="inv-pager" data-dnpd-pager style="padding:12px 0 0;"></div>
      @endif
    </div>
  </details>
@endforeach
</div>

<script>
/* Penomoran halaman per kelompok: paling banyak 10 baris sekali tampil.
   Barisnya sudah digambar peladen (dan sudah tersaring di sana); yang
   dikerjakan di sini hanya memilah halamannya. */
(function () {
  const PER_HALAMAN = 10;

  document.querySelectorAll('[data-dnpd-kel]').forEach(function (kel) {
    const body = kel.querySelector('[data-dnpd-body]');
    const pager = kel.querySelector('[data-dnpd-pager]');
    if (!body || !pager) return;

    const baris = Array.prototype.slice.call(body.querySelectorAll('tr'));
    const total = baris.length;
    const halamanTotal = Math.max(1, Math.ceil(total / PER_HALAMAN));
    let halaman = 1;

    function gambar() {
      halaman = Math.min(Math.max(halaman, 1), halamanTotal);
      const mulai = (halaman - 1) * PER_HALAMAN;
      baris.forEach(function (tr, i) { tr.style.display = i >= mulai && i < mulai + PER_HALAMAN ? '' : 'none'; });

      if (halamanTotal <= 1) { pager.innerHTML = ''; return; }

      const tampil = Math.min(PER_HALAMAN, total - mulai);
      let tombol = '<button class="inv-pg" ' + (halaman <= 1 ? 'disabled' : '') + ' data-go="' + (halaman - 1) + '">&lsaquo;</button>';
      const daftar = [];
      for (let i = 1; i <= halamanTotal; i++) {
        if (i === 1 || i === halamanTotal || (i >= halaman - 1 && i <= halaman + 1)) daftar.push(i);
        else if (daftar[daftar.length - 1] !== '…') daftar.push('…');
      }
      daftar.forEach(function (i) {
        tombol += i === '…' ? '<span class="inv-pg dots">…</span>'
          : '<button class="inv-pg' + (i === halaman ? ' active' : '') + '" data-go="' + i + '">' + i + '</button>';
      });
      tombol += '<button class="inv-pg" ' + (halaman >= halamanTotal ? 'disabled' : '') + ' data-go="' + (halaman + 1) + '">&rsaquo;</button>';

      pager.innerHTML = '<div class="pg-info">Menampilkan ' + (mulai + 1) + '&ndash;' + (mulai + tampil) + ' dari ' + total + ' NPD</div>'
        + '<div class="pg-btns">' + tombol + '</div>';
      pager.querySelectorAll('[data-go]').forEach(function (b) {
        b.addEventListener('click', function () { halaman = Number(b.dataset.go); gambar(); });
      });
    }

    gambar();
  });
})();
</script>
@endsection
