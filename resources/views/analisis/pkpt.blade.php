@extends('layouts.app')

@section('activeNav', 'pkpt')
@section('title', 'Monitoring PKPT')

@section('content')
<style>
  /* Lima kartu KPI - satu lebih banyak dari dashboard lain, jadi grid-nya
     ditimpa di sini saja supaya .kpi-grid global tetap empat kolom. */
  .pkpt-kpi{grid-template-columns:repeat(5,1fr)}
  @media(max-width:1280px){.pkpt-kpi{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:860px){.pkpt-kpi{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:440px){.pkpt-kpi{grid-template-columns:1fr}}
  /* Nominal PKPT jauh lebih panjang dari angka cacah di sebelahnya. */
  .pkpt-kpi .kpi-val.rp{font-size:16px}

  .pkpt-unit-list{display:flex;flex-direction:column;gap:16px;margin-top:14px}
  .pkpt-progress-head{display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px}
  .pkpt-progress-head strong{font-weight:700;color:var(--tegas)}
  .pkpt-progress-head span{color:var(--mut);font-size:12.5px}
  .pkpt-bar{height:26px;background:var(--surface-3);border-radius:6px;overflow:hidden;position:relative}
  .pkpt-bar i{height:100%;display:flex;align-items:center;justify-content:flex-end;padding-right:10px;box-sizing:border-box;border-radius:6px;min-width:4px;background:linear-gradient(90deg,#3f6187,#15314a);color:#fff;font-size:12.5px;font-weight:700}
  .pkpt-bar b{position:absolute;top:50%;transform:translateY(-50%);color:var(--tegas);font-weight:700;font-size:12.5px}

  .pkpt-filter{display:grid;grid-template-columns:repeat(4,1fr) auto;gap:12px;align-items:end;margin-top:14px}
  .pkpt-filter label{display:block;font-size:12px;font-weight:700;color:var(--tegas);margin-bottom:5px}
  @media(max-width:1100px){.pkpt-filter{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:600px){.pkpt-filter{grid-template-columns:1fr}}

  .pkpt-table-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px;margin-top:12px}
  .pkpt-table{min-width:1040px;table-layout:fixed}
  .pkpt-row{cursor:pointer}
  .pkpt-row td{vertical-align:top}
  .pkpt-no{font-weight:800;color:var(--tegas);font-size:12px;white-space:nowrap}
  .pkpt-caret{display:inline-block;transition:transform .15s;color:var(--mut);margin-right:2px}
  .pkpt-row[aria-expanded="true"] .pkpt-caret{transform:rotate(90deg)}
  .pkpt-detail>td{background:var(--surface-2,var(--surface-3));padding:0}
  .pkpt-detail-grid{padding:12px 16px;display:grid;grid-template-columns:repeat(3,1fr);gap:14px;font-size:12px}
  .pkpt-detail-grid dt{font-weight:700;color:var(--tegas);margin-bottom:3px}
  .pkpt-detail-grid dd{margin:0;color:var(--mut);line-height:1.5}
  @media(max-width:760px){.pkpt-detail-grid{grid-template-columns:1fr}}
</style>

@php
  $rp = fn ($nilai) => fmt_rupiah($nilai);
  $persen = fn ($nilai) => number_format((float) $nilai, 1, ',', '.').'%';
@endphp

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Monitoring PKPT</b></div>
    <div class="ph-title">Monitoring PKPT</div>
  </div>
  <div class="ph-actions">
    <a class="btn" href="{{ route('pkpt.index') }}" style="white-space:nowrap;">&#8635; Muat Ulang</a>
  </div>
</div>

<div class="kpi-grid pkpt-kpi">
  <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div><div><div class="kpi-lbl">Total Kegiatan</div></div></div>
    <div class="kpi-val">{{ number_format($kartu['total_kegiatan'], 0, ',', '.') }}</div>
    <div class="kpi-note">Tahun Anggaran {{ $tahun }}</div>
  </div>
  <div class="kpi" style="--kc:#166534;--kbg:#16653414;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="kpi-lbl">Kegiatan Terlaksana</div></div></div>
    <div class="kpi-val">{{ $persen($kartu['persen']) }}</div>
    <div class="kpi-note">{{ $kartu['terlaksana'] }} dari {{ $kartu['total_kegiatan'] }} kegiatan</div>
  </div>
  <div class="kpi" style="--kc:#b45309;--kbg:#b4530914;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1"/><path d="M21 12h-4a2 2 0 0 0 0 4h4"/><path d="M17 14h.01"/></svg></div><div><div class="kpi-lbl">Estimasi Anggaran berdasarkan PKPT</div></div></div>
    <div class="kpi-val rp">{{ $rp($kartu['total_estimasi']) }}</div>
  </div>
  <div class="kpi" style="--kc:#1d4ed8;--kbg:#1d4ed814;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg></div><div><div class="kpi-lbl">Total Realisasi</div></div></div>
    <div class="kpi-val rp">{{ $rp($kartu['total_realisasi']) }}</div>
  </div>
  <div class="kpi" style="--kc:#b3261e;--kbg:#b3261e14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div><div class="kpi-lbl">Estimasi Anggaran PKPT Belum Terealisasi</div></div></div>
    <div class="kpi-val rp">{{ $rp($kartu['belum_terealisasi']) }}</div>
  </div>
</div>

<div class="dash-card" style="margin-bottom:16px;">
  <h3 style="margin:0;">Persentase PKPT Terlaksana per Unit Kerja</h3>
  <div class="sub">Perbandingan capaian pelaksanaan PKPT tiap Inspektur Pembantu (I &ndash; IV) dan Investigasi.</div>
  <div class="pkpt-unit-list">
    @forelse ($perUnit as $unit)
      @php
        $lebar = max(0, min(100, (float) $unit['persen']));
        // Angka persen muat di dalam batang hanya kalau batangnya cukup
        // panjang; di bawah itu dicetak di sebelah kanannya - sama seperti GAS.
        $didalam = $lebar >= 14;
        $teks = $persen($unit['persen']);
      @endphp
      <div>
        <div class="pkpt-progress-head">
          <strong>{{ $unit['unit_singkat'] }}</strong>
          <span>{{ $unit['terlaksana'] }} / {{ $unit['total'] }} terlaksana</span>
        </div>
        <div class="pkpt-bar">
          <i style="width:{{ $lebar }}%">{{ $didalam ? $teks : '' }}</i>
          @unless($didalam)<b style="left:calc({{ $lebar }}% + 8px)">{{ $teks }}</b>@endunless
        </div>
      </div>
    @empty
      <div class="sub" style="margin:0;">Belum ada data unit.</div>
    @endforelse
  </div>
</div>

<div class="dash-card">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <h3 style="margin:0;">Rincian Program Kerja Pengawasan Tahunan</h3>
    <span class="badge" style="background:var(--info-bg);color:var(--info);">{{ $jumlahTersaring }}</span>
  </div>
  <div class="sub" style="margin-top:4px;">Daftar seluruh program kerja pengawasan tahunan beserta status pelaksanaannya. Klik satu baris untuk melihat jumlah tim, tujuan, dan ruang lingkupnya.</div>

  <form method="GET" class="pkpt-filter">
    <div>
      <label for="pkpt-area">Area Pengawasan dan Pembinaan</label>
      <select id="pkpt-area" name="area" data-cari>
        <option value="">Semua Area</option>
        @foreach ($opsi['area'] as $area)
          <option @selected($filters['area'] === $area)>{{ $area }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="pkpt-unit">Unit Kerja</label>
      <select id="pkpt-unit" name="unit" data-cari>
        <option value="">Semua Unit Kerja</option>
        @foreach ($opsi['unit'] as $unit)
          <option @selected($filters['unit'] === $unit)>{{ $unit }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="pkpt-periode">Periode</label>
      <select id="pkpt-periode" name="periode" data-cari>
        <option value="">Semua Periode</option>
        @foreach ($opsi['periode'] as $periode)
          <option @selected($filters['periode'] === $periode)>{{ $periode }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="pkpt-status">Status</label>
      <select id="pkpt-status" name="status">
        <option value="">Semua Status</option>
        <option value="{{ \App\Services\PkptService::STATUS_TERLAKSANA }}" @selected($filters['status'] === \App\Services\PkptService::STATUS_TERLAKSANA)>Terlaksana</option>
        <option value="{{ \App\Services\PkptService::STATUS_BELUM }}" @selected($filters['status'] === \App\Services\PkptService::STATUS_BELUM)>Belum terlaksana</option>
      </select>
    </div>
    <div><button class="btn prim">Terapkan</button> <a class="btn" href="{{ route('pkpt.index') }}">Reset</a></div>
  </form>

  <div class="pkpt-table-wrap">
    <table class="realisasi npd-table pkpt-table">
      <colgroup>
        <col style="width:6%;"><col style="width:7%;"><col style="width:22%;"><col style="width:10%;">
        <col style="width:12%;"><col style="width:12%;"><col style="width:10%;"><col style="width:10%;"><col style="width:11%;">
      </colgroup>
      <thead><tr>
        <th>No</th><th>Unit Kerja</th><th>Area Pengawasan dan Pembinaan</th><th>Jenis Kegiatan</th>
        <th class="num">Estimasi Anggaran</th><th class="num">Realisasi</th>
        <th>Rencana Pelaksanaan</th><th>Pelaksanaan</th><th>Status</th>
      </tr></thead>
      <tbody>
        @forelse ($baris as $r)
          <tr class="pkpt-row" data-baris="{{ $r['id'] }}" aria-expanded="false" tabindex="0">
            <td class="pkpt-no"><span class="pkpt-caret">&#9656;</span>{{ $r['nomor'] }}</td>
            <td>{{ $r['unit_singkat'] }}</td>
            <td>{{ $r['area'] }}</td>
            <td>{{ $r['jenis'] }}</td>
            <td class="num">{{ $rp($r['estimasi']) }}</td>
            <td class="num">{{ $rp($r['realisasi']) }}</td>
            <td>{{ $r['rencana'] ?: '-' }}</td>
            <td>{{ $r['pelaksanaan'] ?: '-' }}</td>
            <td><span class="badge {{ $r['terlaksana'] ? 'st-selesai' : 'st-verifikasi' }}">{{ $r['status'] }}</span></td>
          </tr>
          <tr class="pkpt-detail" data-detail="{{ $r['id'] }}" hidden>
            <td colspan="9">
              <dl class="pkpt-detail-grid">
                <div><dt>Jumlah Tim</dt><dd>{{ $r['jumlah_tim'] ?: '-' }}</dd></div>
                <div><dt>Tujuan / Sasaran</dt><dd>{{ $r['tujuan'] ?: '-' }}</dd></div>
                <div><dt>Ruang Lingkup</dt><dd>{{ $r['ruang_lingkup'] ?: '-' }}</dd></div>
              </dl>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" style="text-align:center;color:var(--mut);padding:20px;">
            @if ($kartu['total_kegiatan'] === 0)
              Belum ada data PKPT untuk Tahun Anggaran {{ $tahun }}. Unggah lewat Manajemen Data &rsaquo; Data PKPT.
            @else
              Tidak ada data yang cocok dengan filter ini.
            @endif
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($baris->hasPages())
    <div class="pager">
      <div class="pager-info">Menampilkan {{ $baris->firstItem() }}&ndash;{{ $baris->lastItem() }} dari {{ $baris->total() }} kegiatan</div>
      <div class="pager-btns">
        <a class="pg-btn" href="{{ $baris->previousPageUrl() ?? '#' }}"@if (! $baris->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
        <a class="pg-btn" href="{{ $baris->nextPageUrl() ?? '#' }}"@if (! $baris->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
      </div>
    </div>
  @endif
</div>

<script>
// Klik (atau Enter/Spasi) pada baris membuka keterangan tambahannya. Tanpa
// JavaScript barisnya tetap terbaca - hanya detailnya yang tidak terbuka.
document.querySelectorAll('.pkpt-row').forEach(function (baris) {
  function toggle() {
    var detail = document.querySelector('.pkpt-detail[data-detail="' + baris.dataset.baris + '"]');
    if (!detail) return;
    detail.hidden = !detail.hidden;
    baris.setAttribute('aria-expanded', detail.hidden ? 'false' : 'true');
  }
  baris.addEventListener('click', toggle);
  baris.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
  });
});
</script>
@endsection
