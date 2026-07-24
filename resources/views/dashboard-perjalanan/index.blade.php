@extends('layouts.app')

@section('activeNav', 'dashpd')
@section('title', 'Dashboard Perjalanan Dinas')

@section('content')
<style>
  .pd-filter{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end}.pd-filter label{display:block;font-size:12px;font-weight:700;color:var(--navy);margin-bottom:5px}
  .pd-chart{height:360px;position:relative}.pd-table-wrap{overflow:auto}.pd-table{min-width:1050px}.pd-toggle{border:0;background:transparent;color:var(--navy);cursor:pointer;font-weight:800}.pd-member{display:none;background:#fafbfd}.pd-member.show{display:table-row}.pd-member td:first-child{padding-left:34px}
  .pd-empty{text-align:center;padding:50px 16px;color:var(--mut)}
  @media(max-width:900px){.pd-filter{grid-template-columns:1fr 1fr}}@media(max-width:600px){.pd-filter{grid-template-columns:1fr}}
</style>
@php($rupiah = fn ($nilai) => fmt_rupiah((float) $nilai))

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Dashboard Perjalanan Dinas</b></div>
    <div class="ph-title">Dashboard Perjalanan Dinas</div>
  </div>
  <div class="ph-actions">
    <a class="btn" href="{{ request()->fullUrl() }}" style="white-space:nowrap;">&#8635; Muat Ulang</a>
  </div>
</div>

<div class="dash-card"><form method="GET" class="pd-filter" id="pd-filter">
  <div><label for="pd-bidang">Bidang</label><select id="pd-bidang" name="bidang"><option value="">Semua Bidang</option>@foreach($dashboard['pilihan']['bidang'] as $bidang)<option @selected($filters['bidang']===$bidang)>{{ $bidang }}</option>@endforeach</select></div>
  <div><label for="pd-pegawai">Pegawai</label><select id="pd-pegawai" name="pegawai"><option value="">Semua Pegawai</option>@foreach($dashboard['pilihan']['pegawai'] as $pegawai)<option value="{{ $pegawai['value'] }}" data-bidang="{{ $pegawai['bidang'] }}" @selected($filters['pegawai']===$pegawai['value'])>{{ $pegawai['label'] }}</option>@endforeach</select></div>
  <input type="hidden" name="metrik" value="{{ $dashboard['metrik'] }}">
  <div><button class="btn prim">Terapkan</button> <a class="btn" href="{{ route('dashboard.perjalanan.index') }}">Reset</a></div>
</form></div>

<div class="kpi-grid">
  <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><div><div class="kpi-lbl">Jumlah Hari</div></div></div>
    <div class="kpi-val">{{ number_format($dashboard['total']['hari'], 0, ',', '.') }}</div>
  </div>
  <div class="kpi" style="--kc:#0f6e56;--kbg:#0f6e5614;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg></div><div><div class="kpi-lbl">Uang Harian + Akomodasi</div></div></div>
    <div class="kpi-val">{{ $rupiah($dashboard['total']['uang_harian']+$dashboard['total']['akomodasi']) }}</div>
  </div>
  <div class="kpi" style="--kc:#b07d1d;--kbg:#b07d1d14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M3 6h18l-2 13H5z"/><path d="M8 10v4M12 10v4M16 10v4"/></svg></div><div><div class="kpi-lbl">Transport + Representatif</div></div></div>
    <div class="kpi-val">{{ $rupiah($dashboard['total']['transport']+$dashboard['total']['representatif']) }}</div>
  </div>
  <div class="kpi" style="--kc:#7c3aed;--kbg:#7c3aed14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div><div class="kpi-lbl">Jumlah Diterima</div></div></div>
    <div class="kpi-val">{{ $rupiah($dashboard['total']['diterima']) }}</div>
  </div>
</div>

@if($dashboard['kosong'])<div class="dash-card pd-empty">Belum ada NPD Perjalanan Dinas atau Transport berstatus Selesai untuk filter ini.</div>@else
<div class="dash-card">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:6px;">
    <div><h3 style="margin:0;">Tren Bulanan</h3><div class="sub">{{ $dashboard['metrik_label'] }} &middot; Januari &ndash; Desember</div></div>
    <div class="an-seg">
      @foreach(['diterima'=>'Jumlah Diterima','uang_harian'=>'Uang Harian','akomodasi'=>'Akomodasi','transport'=>'Transport','representatif'=>'Representatif','hari'=>'Jumlah Hari'] as $key=>$label)
        <a href="{{ route('dashboard.perjalanan.index', array_merge($filters, ['metrik'=>$key])) }}" class="an-seg-btn {{ $dashboard['metrik']===$key ? 'active' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>
  <div class="pd-chart"><canvas id="pd-chart"></canvas></div>
</div>
<div class="dash-card" style="margin-top:16px"><h3 style="margin:0;color:var(--navy)">Rekap per Bidang</h3><div class="sub">Klik bidang untuk membuka rincian anggota.</div><div class="pd-table-wrap"><table class="realisasi pd-table"><thead><tr><th>Bidang / Pegawai</th><th class="num">Hari</th><th class="num">Uang Harian</th><th class="num">Akomodasi</th><th class="num">Transport</th><th class="num">Representatif</th><th class="num">Diterima</th></tr></thead><tbody>
@foreach($dashboard['bidang'] as $i=>$bidang)<tr><td><button type="button" class="pd-toggle" data-group="pd-{{ $i }}" aria-expanded="false">&#9656; {{ $bidang['bidang'] }} ({{ $bidang['jumlah_pegawai'] }})</button></td><td class="num">{{ number_format($bidang['hari'],0,',','.') }}</td><td class="num">{{ $rupiah($bidang['uang_harian']) }}</td><td class="num">{{ $rupiah($bidang['akomodasi']) }}</td><td class="num">{{ $rupiah($bidang['transport']) }}</td><td class="num">{{ $rupiah($bidang['representatif']) }}</td><td class="num"><strong>{{ $rupiah($bidang['diterima']) }}</strong></td></tr>
@foreach($bidang['pegawai'] as $pegawai)<tr class="pd-member" data-member="pd-{{ $i }}"><td>@if($pegawai['pegawai_id'])<a href="{{ route('dashboard.perjalanan.pegawai', $pegawai['pegawai_id']) }}">{{ $pegawai['nama'] }}</a>@else{{ $pegawai['nama'] }}@endif<small style="display:block;color:var(--mut)">{{ $pegawai['jabatan'] ?: 'Jabatan tidak tersedia' }}</small></td><td class="num">{{ number_format($pegawai['hari'],0,',','.') }}</td><td class="num">{{ $rupiah($pegawai['uang_harian']) }}</td><td class="num">{{ $rupiah($pegawai['akomodasi']) }}</td><td class="num">{{ $rupiah($pegawai['transport']) }}</td><td class="num">{{ $rupiah($pegawai['representatif']) }}</td><td class="num">{{ $rupiah($pegawai['diterima']) }}</td></tr>@endforeach
@endforeach</tbody></table></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{const bidang=document.getElementById('pd-bidang'),pegawai=document.getElementById('pd-pegawai');function sesuaikan(){[...pegawai.options].forEach((o,i)=>{if(i)o.hidden=!!bidang.value&&o.dataset.bidang!==bidang.value});if(pegawai.selectedOptions[0]?.hidden)pegawai.value=''}bidang.addEventListener('change',sesuaikan);sesuaikan();document.querySelectorAll('.pd-toggle').forEach(b=>b.addEventListener('click',()=>{const open=b.getAttribute('aria-expanded')==='true';b.setAttribute('aria-expanded',String(!open));b.innerHTML=(open?'&#9656;':'&#9662;')+b.innerHTML.slice(1);document.querySelectorAll('[data-member="'+b.dataset.group+'"]').forEach(r=>r.classList.toggle('show',!open))}));if(typeof Chart!=='undefined'){const data={{ Illuminate\Support\Js::from($dashboard['bulan']) }};new Chart(document.getElementById('pd-chart'),{type:'bar',data:{labels:data.map(x=>x.label),datasets:[{label:{{ Illuminate\Support\Js::from($dashboard['metrik_label']) }},data:data.map(x=>x.nilai),backgroundColor:'#15314a',borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:i=>{{ Illuminate\Support\Js::from($dashboard['metrik']==='hari') }}?i.raw.toLocaleString('id-ID')+' hari':new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(i.raw)}}},legend:{display:false}},scales:{y:{beginAtZero:true}}}})}});</script>
@endif
@endsection
