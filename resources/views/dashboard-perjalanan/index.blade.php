@extends('layouts.app')

@section('activeNav', 'dashpd')
@section('title', 'Dashboard Perjalanan Dinas')

@section('content')
<style>
  .pd-head h2{margin:0;color:var(--navy);font-size:22px}.pd-head p{margin:4px 0 16px;color:var(--mut)}
  .pd-filter{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end}.pd-filter label{display:block;font-size:12px;font-weight:700;color:var(--navy);margin-bottom:5px}
  .pd-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:16px 0}.pd-kpi{background:#fff;border:1px solid var(--line);border-radius:13px;padding:15px 16px;box-shadow:var(--shadow)}.pd-kpi small{display:block;color:var(--mut);font-weight:700;text-transform:uppercase}.pd-kpi strong{display:block;color:var(--navy);font-size:20px;margin-top:5px}
  .pd-chart{height:360px;position:relative}.pd-table-wrap{overflow:auto}.pd-table{min-width:1050px}.pd-toggle{border:0;background:transparent;color:var(--navy);cursor:pointer;font-weight:800}.pd-member{display:none;background:#fafbfd}.pd-member.show{display:table-row}.pd-member td:first-child{padding-left:34px}
  .pd-empty{text-align:center;padding:50px 16px;color:var(--mut)}
  @media(max-width:900px){.pd-filter{grid-template-columns:1fr 1fr}.pd-kpis{grid-template-columns:1fr 1fr}}@media(max-width:600px){.pd-filter,.pd-kpis{grid-template-columns:1fr}}
</style>
@php($rupiah = fn ($nilai) => fmt_rupiah((float) $nilai))
<div class="pd-head"><h2>Dashboard Perjalanan Dinas</h2><p>Agregasi otomatis NPD Perjalanan Dinas dan Transport berstatus Selesai, TA {{ $dashboard['tahun'] }}.</p></div>
<div class="dash-card"><form method="GET" class="pd-filter" id="pd-filter">
  <div><label for="pd-bidang">Bidang</label><select id="pd-bidang" name="bidang"><option value="">Semua Bidang</option>@foreach($dashboard['pilihan']['bidang'] as $bidang)<option @selected($filters['bidang']===$bidang)>{{ $bidang }}</option>@endforeach</select></div>
  <div><label for="pd-pegawai">Pegawai</label><select id="pd-pegawai" name="pegawai"><option value="">Semua Pegawai</option>@foreach($dashboard['pilihan']['pegawai'] as $pegawai)<option value="{{ $pegawai['value'] }}" data-bidang="{{ $pegawai['bidang'] }}" @selected($filters['pegawai']===$pegawai['value'])>{{ $pegawai['label'] }}</option>@endforeach</select></div>
  <div><label for="pd-metrik">Metrik Grafik</label><select id="pd-metrik" name="metrik">@foreach(['diterima'=>'Jumlah Diterima','hari'=>'Jumlah Hari','uang_harian'=>'Uang Harian','akomodasi'=>'Akomodasi','transport'=>'Transport','representatif'=>'Representatif'] as $key=>$label)<option value="{{ $key }}" @selected($dashboard['metrik']===$key)>{{ $label }}</option>@endforeach</select></div>
  <div><button class="btn prim">Terapkan</button> <a class="btn" href="{{ route('dashboard.perjalanan.index') }}">Reset</a></div>
</form></div>
<div class="pd-kpis">
  <div class="pd-kpi"><small>Jumlah Hari</small><strong>{{ number_format($dashboard['total']['hari'], 0, ',', '.') }}</strong></div>
  <div class="pd-kpi"><small>Uang Harian + Akomodasi</small><strong>{{ $rupiah($dashboard['total']['uang_harian']+$dashboard['total']['akomodasi']) }}</strong></div>
  <div class="pd-kpi"><small>Transport + Representatif</small><strong>{{ $rupiah($dashboard['total']['transport']+$dashboard['total']['representatif']) }}</strong></div>
  <div class="pd-kpi"><small>Jumlah Diterima</small><strong>{{ $rupiah($dashboard['total']['diterima']) }}</strong></div>
</div>
@if($dashboard['kosong'])<div class="dash-card pd-empty">Belum ada NPD Perjalanan Dinas atau Transport berstatus Selesai untuk filter ini.</div>@else
<div class="dash-card"><h3 style="margin:0;color:var(--navy)">Tren Bulanan — {{ $dashboard['metrik_label'] }}</h3><div class="pd-chart"><canvas id="pd-chart"></canvas></div></div>
<div class="dash-card" style="margin-top:16px"><h3 style="margin:0;color:var(--navy)">Rekap per Bidang</h3><div class="sub">Klik bidang untuk membuka rincian anggota.</div><div class="pd-table-wrap"><table class="realisasi pd-table"><thead><tr><th>Bidang / Pegawai</th><th class="num">Hari</th><th class="num">Uang Harian</th><th class="num">Akomodasi</th><th class="num">Transport</th><th class="num">Representatif</th><th class="num">Diterima</th></tr></thead><tbody>
@foreach($dashboard['bidang'] as $i=>$bidang)<tr><td><button type="button" class="pd-toggle" data-group="pd-{{ $i }}" aria-expanded="false">&#9656; {{ $bidang['bidang'] }} ({{ $bidang['jumlah_pegawai'] }})</button></td><td class="num">{{ number_format($bidang['hari'],0,',','.') }}</td><td class="num">{{ $rupiah($bidang['uang_harian']) }}</td><td class="num">{{ $rupiah($bidang['akomodasi']) }}</td><td class="num">{{ $rupiah($bidang['transport']) }}</td><td class="num">{{ $rupiah($bidang['representatif']) }}</td><td class="num"><strong>{{ $rupiah($bidang['diterima']) }}</strong></td></tr>
@foreach($bidang['pegawai'] as $pegawai)<tr class="pd-member" data-member="pd-{{ $i }}"><td>{{ $pegawai['nama'] }}<small style="display:block;color:var(--mut)">{{ $pegawai['jabatan'] ?: 'Jabatan tidak tersedia' }}</small></td><td class="num">{{ number_format($pegawai['hari'],0,',','.') }}</td><td class="num">{{ $rupiah($pegawai['uang_harian']) }}</td><td class="num">{{ $rupiah($pegawai['akomodasi']) }}</td><td class="num">{{ $rupiah($pegawai['transport']) }}</td><td class="num">{{ $rupiah($pegawai['representatif']) }}</td><td class="num">{{ $rupiah($pegawai['diterima']) }}</td></tr>@endforeach
@endforeach</tbody></table></div></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{const bidang=document.getElementById('pd-bidang'),pegawai=document.getElementById('pd-pegawai');function sesuaikan(){[...pegawai.options].forEach((o,i)=>{if(i)o.hidden=!!bidang.value&&o.dataset.bidang!==bidang.value});if(pegawai.selectedOptions[0]?.hidden)pegawai.value=''}bidang.addEventListener('change',sesuaikan);sesuaikan();document.querySelectorAll('.pd-toggle').forEach(b=>b.addEventListener('click',()=>{const open=b.getAttribute('aria-expanded')==='true';b.setAttribute('aria-expanded',String(!open));b.innerHTML=(open?'&#9656;':'&#9662;')+b.innerHTML.slice(1);document.querySelectorAll('[data-member="'+b.dataset.group+'"]').forEach(r=>r.classList.toggle('show',!open))}));if(typeof Chart!=='undefined'){const data={{ Illuminate\Support\Js::from($dashboard['bulan']) }};new Chart(document.getElementById('pd-chart'),{type:'bar',data:{labels:data.map(x=>x.label),datasets:[{label:{{ Illuminate\Support\Js::from($dashboard['metrik_label']) }},data:data.map(x=>x.nilai),backgroundColor:'#15314a',borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:i=>{{ Illuminate\Support\Js::from($dashboard['metrik']==='hari') }}?i.raw.toLocaleString('id-ID')+' hari':new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(i.raw)}}},legend:{display:false}},scales:{y:{beginAtZero:true}}}})}});</script>
@endif
@endsection
