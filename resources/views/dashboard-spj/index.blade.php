@extends('layouts.app')

@section('activeNav', 'dashspj')
@section('title', 'Dashboard SPJ Perjalanan Dinas')

@section('content')
<style>
  .spj-filter{display:grid;grid-template-columns:1fr 180px 1.4fr auto;gap:12px;align-items:end}.spj-filter label{display:block;font-size:12px;font-weight:700;color:var(--tegas);margin-bottom:5px}
  /* Mengikuti GAS: kartu per bidang melebar penuh DI ATAS tabel, bukan
     berdampingan. Batangnya 26px dengan persentase tercetak di dalam
     batang; kalau batangnya terlalu pendek, angkanya pindah ke luar. */
  .spj-bidang-list{display:flex;flex-direction:column;gap:16px;margin-top:14px}
  .spj-progress-head{display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px}
  .spj-progress-head strong{font-weight:700;color:var(--tegas)}
  .spj-progress-head span{color:var(--mut);font-size:12.5px}
  .spj-bar{height:26px;background:var(--surface-3);border-radius:6px;overflow:hidden;position:relative}
  .spj-bar i{height:100%;display:flex;align-items:center;justify-content:flex-end;padding-right:10px;box-sizing:border-box;border-radius:6px;min-width:4px;background:linear-gradient(90deg,#3f6187,#15314a);color:#fff;font-size:12.5px;font-weight:700}
  .spj-bar b{position:absolute;top:50%;transform:translateY(-50%);color:var(--tegas);font-weight:700;font-size:12.5px}
  .spj-table-wrap{overflow:auto}.spj-table{min-width:1050px}.spj-empty{text-align:center;padding:46px;color:var(--mut)}
  @media(max-width:960px){.spj-filter{grid-template-columns:1fr 1fr}}@media(max-width:600px){.spj-filter{grid-template-columns:1fr}}
</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Dashboard SPJ Perjalanan Dinas</b></div>
    <div class="ph-title">Dashboard SPJ Perjalanan Dinas</div>
  </div>
  <div class="ph-actions">
    <a class="btn" href="{{ route('dashboard.spj.index') }}" style="white-space:nowrap;">&#8635; Muat Ulang</a>
  </div>
</div>

<div class="dash-card"><form method="GET" class="spj-filter">
  <div><label for="spj-bidang">Bidang</label><select id="spj-bidang" name="bidang" data-cari><option value="">Semua Bidang</option>@foreach($dashboard['pilihan_bidang'] as $bidang)<option @selected($filters['bidang']===$bidang)>{{ $bidang }}</option>@endforeach</select></div>
  <div><label for="spj-status">Status SPJ</label><select id="spj-status" name="status"><option value="">Semua</option><option value="terverifikasi" @selected($filters['status']==='terverifikasi')>Terverifikasi</option><option value="belum" @selected($filters['status']==='belum')>Belum</option></select></div>
  <div><label for="spj-cari">Pencarian</label><input id="spj-cari" name="cari" value="{{ $filters['cari'] }}" placeholder="Nomor NPD/SP, sub kegiatan, uraian"></div>
  <div><button class="btn prim">Terapkan</button> <a class="btn" href="{{ route('dashboard.spj.index') }}">Reset</a></div>
</form></div>

<div class="kpi-grid">
  <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div><div class="kpi-lbl">Total SPJ</div></div></div>
    <div class="kpi-val">{{ $dashboard['total'] }}</div>
  </div>
  <div class="kpi" style="--kc:#166534;--kbg:#16653414;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="kpi-lbl">SPJ Selesai</div></div></div>
    <div class="kpi-val">{{ $dashboard['terverifikasi'] }}</div>
    <div class="kpi-note">sudah diverifikasi</div>
  </div>
  <div class="kpi" style="--kc:#b3261e;--kbg:#b3261e14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div><div class="kpi-lbl">SPJ Belum Selesai</div></div></div>
    <div class="kpi-val">{{ $dashboard['belum'] }}</div>
  </div>
  <div class="kpi" style="--kc:#0f6e56;--kbg:#0f6e5614;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M22 12A10 10 0 1 1 12 2"/><path d="M22 2 12 12"/></svg></div><div><div class="kpi-lbl">% Selesai</div></div></div>
    <div class="kpi-val">{{ number_format($dashboard['persen'],1,',','.') }}%</div>
  </div>
</div>

@if($dashboard['kosong'])<div class="dash-card spj-empty">Belum ada NPD Perjalanan Dinas berstatus Selesai untuk filter ini. Status NPD Selesai tidak otomatis berarti SPJ terverifikasi.</div>@else
<div class="dash-card" style="margin-bottom:16px;">
  <h3 style="margin:0;color:var(--tegas)">Persentase SPJ Selesai per Bidang</h3>
  <div class="sub">Progres verifikasi SPJ dikelompokkan per bidang pelaksana.</div>
  <div class="spj-bidang-list">
    @foreach($dashboard['bidang'] as $bidang)
      @php
        $lebar = max(0, min(100, (float) $bidang['persen']));
        // Angka persen muat di dalam batang hanya kalau batangnya cukup
        // panjang; di bawah itu dicetak di sebelah kanannya - sama seperti GAS.
        $didalam = $lebar >= 14;
        $teks = number_format($bidang['persen'], 1, ',', '.').'%';
      @endphp
      <div>
        <div class="spj-progress-head">
          <strong>{{ $bidang['bidang'] }}</strong>
          <span>{{ $bidang['terverifikasi'] }} / {{ $bidang['total'] }} selesai</span>
        </div>
        <div class="spj-bar">
          <i style="width:{{ $lebar }}%">{{ $didalam ? $teks : '' }}</i>
          @unless($didalam)<b style="left:calc({{ $lebar }}% + 8px)">{{ $teks }}</b>@endunless
        </div>
      </div>
    @endforeach
  </div>
</div>
<div class="dash-card"><h3 style="margin:0;color:var(--tegas)">Daftar Detail SPJ</h3><div class="sub">Verifikasi SPJ tidak mengubah status NPD pada alur persetujuan.</div><div class="spj-table-wrap"><table class="realisasi spj-table"><thead><tr><th>Tanggal / Nomor NPD</th><th>Nomor SP</th><th>Bidang</th><th>Sub Kegiatan / Uraian</th><th class="num">Nominal</th><th>Status SPJ</th><th>Aksi</th></tr></thead><tbody>@foreach($dashboard['rows'] as $row)<tr><td>{{ $row['tanggal']->format('d-m-Y') }}<br><strong>{{ $row['nomor_npd'] }}</strong></td><td>{{ $row['nomor_sp'] }}</td><td>{{ $row['bidang'] }}</td><td>{{ $row['sub_kegiatan'] }}<br><small>{{ $row['uraian'] }}</small></td><td class="num">{{ fmt_rupiah($row['nominal']) }}</td><td>@if($row['status_spj']==='terverifikasi')<span class="badge st-selesai">TERVERIFIKASI</span><small style="display:block;margin-top:4px">{{ $row['verified_at']->format('d-m-Y H:i') }} · {{ $row['verified_by'] }}</small>@else<span class="badge st-verifikasi">BELUM</span>@endif</td><td>@if($bolehVerifikasi)<form method="POST" action="{{ route('dashboard.spj.verify',$row['id']) }}">@csrf<input type="hidden" name="aksi" value="{{ $row['status_spj']==='terverifikasi'?'batalkan':'verifikasi' }}"><button class="btn {{ $row['status_spj']==='terverifikasi'?'':'prim' }}" onclick="return confirm('{{ $row['status_spj']==='terverifikasi'?'Batalkan verifikasi SPJ ini?':'Verifikasi SPJ ini sebagai selesai?' }}')">{{ $row['status_spj']==='terverifikasi'?'Batalkan':'Verifikasi' }}</button></form>@else<span class="sub">Lihat saja</span>@endif</td></tr>@endforeach</tbody></table></div></div>
@endif
@endsection
