@extends('layouts.app')

@section('activeNav', 'dashpd')
@section('title', 'Riwayat Perjalanan Dinas — '.$pegawai->nama)

@section('content')
<style>
  .rp-head h2{margin:0;color:var(--tegas);font-size:22px}.rp-head p{margin:4px 0 16px;color:var(--mut)}
  .rp-filter{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:12px;align-items:end}.rp-filter label{display:block;font-size:12px;font-weight:700;color:var(--tegas);margin-bottom:5px}
  .rp-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:16px 0}.rp-kpi{background:var(--surface);border:1px solid var(--line);border-radius:13px;padding:15px 16px;box-shadow:var(--shadow)}.rp-kpi small{display:block;color:var(--mut);font-weight:700;text-transform:uppercase}.rp-kpi strong{display:block;color:var(--tegas);font-size:20px;margin-top:5px}
  .rp-table-wrap{overflow:auto}.rp-table{min-width:1050px}.rp-row-link{cursor:pointer}.rp-row-link:hover{background:var(--surface-2)}.rp-tag{display:inline-block;margin-left:6px;font-size:11px;font-weight:700;color:var(--mut);border:1px solid var(--line);border-radius:6px;padding:1px 6px}
  .rp-empty{text-align:center;padding:50px 16px;color:var(--mut)}
  @media(max-width:900px){.rp-filter{grid-template-columns:1fr 1fr}.rp-kpis{grid-template-columns:1fr 1fr}}@media(max-width:600px){.rp-filter,.rp-kpis{grid-template-columns:1fr}}
</style>
@php($rupiah = fn ($nilai) => fmt_rupiah((float) $nilai))
<div class="rp-head">
  <h2>Riwayat Perjalanan Dinas — {{ $pegawai->nama }}</h2>
  <p>{{ $pegawai->jabatan ?: 'Jabatan tidak tersedia' }} &middot; {{ $pegawai->bidang ?: 'Bidang tidak tersedia' }}</p>
  <p><a class="btn" href="{{ route('dashboard.perjalanan.index') }}">&larr; Kembali ke Dashboard Perjalanan Dinas</a></p>
</div>

<div class="dash-card"><form method="GET" class="rp-filter">
  <div><label for="rp-dari">Dari Tanggal</label><input type="date" id="rp-dari" name="dari" value="{{ $filters['dari'] }}"></div>
  <div><label for="rp-sampai">Sampai Tanggal</label><input type="date" id="rp-sampai" name="sampai" value="{{ $filters['sampai'] }}"></div>
  <div><label for="rp-jenis">Jenis</label><select id="rp-jenis" name="jenis"><option value="">Semua Jenis</option>@foreach(['pd' => 'Perjalanan Dinas', 'tr' => 'Transport', 'kd' => 'Kontribusi Diklat'] as $kode => $label)<option value="{{ $kode }}" @selected($filters['jenis']===$kode)>{{ $label }}</option>@endforeach</select></div>
  <div><label for="rp-status">Status</label><select id="rp-status" name="status"><option value="">Semua Status</option>@foreach(\App\Models\Npd::STATUS_LIST as $status)<option value="{{ $status }}" @selected($filters['status']===$status)>{{ $status }}</option>@endforeach</select></div>
  <div><button class="btn prim">Terapkan</button> <a class="btn" href="{{ route('dashboard.perjalanan.pegawai', $pegawai->id) }}">Reset</a></div>
</form></div>

<div class="rp-kpis">
  <div class="rp-kpi"><small>Total NPD</small><strong>{{ number_format($riwayat['ringkasan']['total_npd'], 0, ',', '.') }}</strong></div>
  <div class="rp-kpi"><small>Total Hari Dinas</small><strong>{{ number_format($riwayat['ringkasan']['total_hari'], 0, ',', '.') }}</strong></div>
  <div class="rp-kpi"><small>Total Nominal Diterima</small><strong>{{ $rupiah($riwayat['ringkasan']['total_nominal']) }}</strong></div>
  <div class="rp-kpi"><small>Rentang Tanggal Data</small><strong>@if($riwayat['ringkasan']['tanggal_awal'])@if($riwayat['ringkasan']['tanggal_awal']->isSameDay($riwayat['ringkasan']['tanggal_akhir'])){{ $riwayat['ringkasan']['tanggal_awal']->format('d-m-Y') }}@else{{ $riwayat['ringkasan']['tanggal_awal']->format('d-m-Y') }} &ndash; {{ $riwayat['ringkasan']['tanggal_akhir']->format('d-m-Y') }}@endif@else&mdash;@endif</strong></div>
</div>

@if($riwayat['kosong'])
<div class="dash-card rp-empty">Belum ada riwayat NPD Perjalanan Dinas, Transport, atau Kontribusi Diklat (Perjalanan) untuk pegawai ini dengan filter saat ini.</div>
@else
<div class="dash-card">
<div class="rp-table-wrap"><table class="realisasi rp-table">
  <thead><tr><th>Tanggal NPD</th><th>Nomor NPD</th><th>Jenis</th><th>Nomor SP</th><th>Tujuan/Uraian</th><th class="num">Jumlah Hari</th><th class="num">Nominal Bagian</th><th>Status</th></tr></thead>
  <tbody>
  @foreach($riwayat['halaman'] as $baris)
    @php($bisaKlik = $bolehLihatDetailNpd)
    <tr @if($bisaKlik) class="rp-row-link" data-href="{{ route('npd.show', $baris['npd_id']) }}" @endif>
      <td>{{ $baris['tanggal_npd']->format('d-m-Y') }}</td>
      <td>
        @if($bisaKlik)<a href="{{ route('npd.show', $baris['npd_id']) }}">{{ $baris['nomor_lengkap'] ?? '— (Draft)' }}</a>@else{{ $baris['nomor_lengkap'] ?? '— (Draft)' }}@endif
        @if($baris['kecocokan_nama'])<span class="rp-tag" title="Cocok berdasarkan nama snapshot, bukan pegawai_id yang pasti">Kecocokan Nama</span>@endif
      </td>
      <td>{{ \App\Models\Npd::JENIS_LABEL[$baris['jenis']] ?? strtoupper($baris['jenis']) }}</td>
      <td>{{ $baris['nomor_sp'] ?? '-' }}</td>
      <td>{{ $baris['uraian'] ?? '-' }}</td>
      <td class="num">{{ $baris['jumlah_hari'] === null ? '-' : number_format($baris['jumlah_hari'], 0, ',', '.') }}</td>
      <td class="num">{{ $rupiah($baris['nominal_bagian']) }}</td>
      <td><span class="badge {{ \App\Models\Npd::STATUS_BADGE_CLASS[$baris['status']] ?? 'st-diterima' }}">{{ $baris['status'] }}</span></td>
    </tr>
  @endforeach
  </tbody>
</table></div>
</div>

@if($riwayat['halaman']->hasPages())
<div class="pager">
  <div class="pager-info">Menampilkan {{ $riwayat['halaman']->firstItem() }}&ndash;{{ $riwayat['halaman']->lastItem() }} dari {{ $riwayat['halaman']->total() }} data</div>
  <div class="pager-btns">
    <a class="pg-btn" href="{{ $riwayat['halaman']->previousPageUrl() ?? '#' }}"@if(! $riwayat['halaman']->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
    <a class="pg-btn" href="{{ $riwayat['halaman']->nextPageUrl() ?? '#' }}"@if(! $riwayat['halaman']->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
  </div>
</div>
@endif
@endif

<script>document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.rp-row-link').forEach(r=>r.addEventListener('click',e=>{if(e.target.closest('a'))return;window.location.href=r.dataset.href}))});</script>
@endsection
