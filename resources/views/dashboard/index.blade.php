@extends('layouts.app')

@section('activeNav', 'dashboard')
@section('title', 'Dashboard Realisasi Anggaran')

@section('content')
<style>
  .dr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px}.dr-head h2{margin:0;color:var(--navy);font-size:22px}.dr-head p{margin:3px 0 0;color:var(--mut)}
  .dr-filter{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:12px;align-items:end}.dr-filter label{display:block;font-size:12px;font-weight:700;color:var(--navy);margin-bottom:5px}.dr-filter select{width:100%;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.dr-filter-actions{display:flex;gap:8px}.dr-filter-actions .btn{padding:9px 14px;white-space:nowrap}
  .dr-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:16px 0}.dr-kpi{background:#fff;border:1px solid var(--line);border-radius:13px;padding:15px 16px;box-shadow:var(--shadow)}.dr-kpi .label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--mut)}.dr-kpi .value{font-size:20px;font-weight:800;color:var(--navy);margin-top:5px;font-variant-numeric:tabular-nums}.dr-kpi-sub{display:flex;flex-direction:column;gap:2px;font-size:11.5px;color:var(--mut);margin-top:5px;font-variant-numeric:tabular-nums}.dr-kpi-warn{font-size:11px;color:var(--warn);margin-top:6px;line-height:1.4}
  .dr-visuals{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}.dr-chart-card h3{margin:0;color:var(--navy);font-size:16px}.dr-chart-card .sub{margin:3px 0 8px}.dr-donut{height:250px;position:relative}.dr-donut-center{position:absolute;inset:50% auto auto 50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none}.dr-donut-center strong{display:block;color:var(--navy);font-size:25px}.dr-donut-center span{font-size:11px;color:var(--mut)}
  .dr-notice{border-radius:10px;padding:11px 13px;margin:0 0 16px;font-size:13px;background:var(--warn-bg);color:var(--warn);border:1px solid #f0dcae}.dr-danger{background:var(--err-bg);color:var(--err);border-color:#f1b9b5}
  .dr-table-wrap{overflow:auto}.dr-table{min-width:1200px}.dr-table th a{display:inline-flex;gap:4px;color:inherit;text-decoration:none}.dr-table th.num,.dr-table td.num{text-align:right;white-space:nowrap}.dr-table .sub-name{font-weight:700;color:var(--navy);min-width:260px}.dr-table .program{display:block;color:var(--mut);font-size:11px;font-weight:400;margin-top:2px}.dr-positive{color:var(--ok);font-weight:700}.dr-negative{color:var(--err);font-weight:700}.dr-empty{text-align:center;color:var(--mut);padding:36px 12px}
  @media(max-width:1050px){.dr-kpis{grid-template-columns:1fr 1fr}.dr-visuals{grid-template-columns:1fr}}
  @media(max-width:720px){.dr-filter{grid-template-columns:1fr}.dr-kpis{grid-template-columns:1fr}}
</style>

@php
    $rupiah = fn (?float $nilai) => $nilai === null ? 'Belum tersedia' : fmt_rupiah($nilai);
    $persen = fn (?float $nilai) => $nilai === null ? 'Belum tersedia' : number_format($nilai, 2, ',', '.').' %';
    $sortUrl = function (string $key) use ($dashboard, $filters) {
        $nextDirection = $dashboard['sort'] === $key && $dashboard['direction'] === 'asc' ? 'desc' : 'asc';
        return route('dashboard.index', $filters + ['sort' => $key, 'direction' => $nextDirection]);
    };
    $sortMark = fn (string $key) => $dashboard['sort'] === $key ? ($dashboard['direction'] === 'asc' ? '▲' : '▼') : '';
@endphp

<div class="dr-head"><div><h2>Dashboard Realisasi Anggaran</h2></div></div>

<div class="dash-card">
  <form method="GET" action="{{ route('dashboard.index') }}" class="dr-filter" id="dr-filter-form">
    <div><label for="dr-sub">Sub Kegiatan</label><select name="sub_kegiatan" id="dr-sub"><option value="">Semua Sub Kegiatan</option>@foreach($pilihan['sub_kegiatan'] as $option)<option value="{{ $option['value'] }}" title="{{ $option['label'] }}" @selected($filters['sub_kegiatan'] === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></div>
    <div><label for="dr-kode">Kode Rekening</label><select name="kode_rekening" id="dr-kode"><option value="">Semua Kode Rekening</option>@foreach($pilihan['kode_rekening_berlabel'] as $opsi)<option value="{{ $opsi['value'] }}" title="{{ $opsi['label'] }}" @selected($filters['kode_rekening'] === $opsi['value'])>{{ $opsi['label'] }}</option>@endforeach</select></div>
    <div class="dr-filter-actions"><button class="btn prim" type="submit">Terapkan</button><a class="btn" href="{{ route('dashboard.index') }}">Reset</a></div>
  </form>
</div>

<div class="dr-kpis">
  <div class="dr-kpi">
    <div class="label">Pagu Anggaran</div>
    <div class="value">{{ $rupiah($dashboard['total']['pagu']) }}</div>
    <div class="dr-kpi-sub"><span>Realisasi: {{ $rupiah($dashboard['total']['realisasi_aktual']) }}</span><span>Sisa Anggaran: {{ $rupiah($dashboard['sisa_anggaran_spj3']['nominal']) }}</span></div>
  </div>
  <div class="dr-kpi">
    <div class="label">Realisasi SP2D</div>
    <div class="value">{{ $rupiah($dashboard['realisasi_sp2d']['nominal']) }}</div>
    <div class="dr-kpi-sub"><span>{{ $persen($dashboard['realisasi_sp2d']['persentase']) }}</span></div>
    @if($dashboard['filter_aktif'])<div class="dr-kpi-warn">SPM UP/GU/TU bersifat nasional, tidak per Sub Kegiatan &mdash; persentase saat filter aktif hanya indikatif.</div>@endif
  </div>
  <div class="dr-kpi">
    <div class="label">Realisasi SPJ3</div>
    <div class="value">{{ $rupiah($dashboard['total']['realisasi_aktual']) }}</div>
    <div class="dr-kpi-sub"><span>{{ $persen($dashboard['total']['persentase_realisasi']) }}</span></div>
  </div>
  <div class="dr-kpi">
    <div class="label">Sisa Anggaran</div>
    <div class="value">{{ $rupiah($dashboard['sisa_anggaran_spj3']['nominal']) }}</div>
    <div class="dr-kpi-sub"><span>{{ $persen($dashboard['sisa_anggaran_spj3']['persentase']) }}</span></div>
  </div>
</div>

@if($dashboard['total']['sisa_tersedia'] < 0)<div class="dr-notice dr-danger">Sisa tersedia bernilai negatif. Grafik komposisi membatasi irisan sisa pada nol, tetapi KPI dan tabel tetap menampilkan nilai sebenarnya.</div>@endif
@if($dashboard['pesan_rak'])<div class="dr-notice" role="status">{{ $dashboard['pesan_rak'] }}</div>@endif

@if($dashboard['kosong'])
  <div class="dash-card dr-empty">Tidak ada data anggaran aktif untuk filter ini.</div>
@else
<div class="dr-visuals">
  <div class="dash-card dr-chart-card"><h3>Realisasi 1 Tahun</h3><div class="sub">Tahun Anggaran {{ $dashboard['tahun'] }}</div><div class="dr-donut"><canvas id="dr-composition"></canvas><div class="dr-donut-center"><strong>{{ $persen($dashboard['total']['persentase_realisasi']) }}</strong><span>realisasi / pagu</span></div></div></div>
  <div class="dash-card dr-chart-card"><h3>Realisasi terhadap RAK</h3><div class="sub">Target kumulatif s.d. {{ $dashboard['bulan_acuan_label'] }} {{ $dashboard['tahun'] }}</div>
    @if($dashboard['target_rak_sd_bulan'] !== null && $dashboard['target_rak_sd_bulan'] > 0)
      <div class="dr-donut"><canvas id="dr-rak"></canvas><div class="dr-donut-center"><strong>{{ $persen($dashboard['persentase_target_rak']) }}</strong><span>{{ $rupiah($dashboard['realisasi_sd_bulan']) }} / {{ $rupiah($dashboard['target_rak_sd_bulan']) }}</span></div></div>
    @elseif($dashboard['target_rak_sd_bulan'] === 0.0)
      <div class="dr-empty">Target RAK resmi sampai bulan berjalan bernilai nol. Persentase tidak dapat dihitung.</div>
    @else
      <div class="dr-empty">Target RAK sampai bulan berjalan belum tersedia secara lengkap.</div>
    @endif
  </div>
</div>

<div class="dash-card">
  <h3 style="margin:0;color:var(--navy)">Realisasi per Sub Kegiatan</h3>
  <div class="dr-table-wrap"><table class="realisasi dr-table"><thead><tr>
    <th><a href="{{ $sortUrl('nama') }}">Sub Kegiatan {{ $sortMark('nama') }}</a></th>
    @foreach(['pagu'=>'Pagu','dana_terikat_npd'=>'Dana Terikat','realisasi_aktual'=>'Realisasi','sisa_tersedia'=>'Sisa','persentase_realisasi'=>'% Realisasi','target_rak'=>'Target RAK','deviasi_rupiah'=>'Deviasi'] as $key=>$label)<th class="num"><a href="{{ $sortUrl($key) }}">{{ $label }} {{ $sortMark($key) }}</a></th>@endforeach
  </tr></thead><tbody>
    @foreach($dashboard['rows'] as $row)<tr><td class="sub-name">{{ $row['nama'] }}<span class="program">{{ $row['program'] }} &middot; {{ $row['kegiatan'] }}</span></td>
      <td class="num">{{ $rupiah($row['angka']['pagu']) }}</td><td class="num">{{ $rupiah($row['angka']['dana_terikat_npd']) }}</td><td class="num">{{ $rupiah($row['angka']['realisasi_aktual']) }}</td><td class="num">{{ $rupiah($row['angka']['sisa_tersedia']) }}</td><td class="num">{{ $persen($row['angka']['persentase_realisasi']) }}</td><td class="num">{{ $rupiah($row['target_rak']) }}</td><td class="num {{ ($row['deviasi_rupiah'] ?? 0) >= 0 ? 'dr-positive' : 'dr-negative' }}">{{ $row['deviasi_rupiah'] !== null ? $persen($row['deviasi_persen']) : 'Belum tersedia' }}</td>
    </tr>@endforeach
  </tbody></table></div>
</div>
@endif

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form=document.getElementById('dr-filter-form'),sub=document.getElementById('dr-sub'),kode=document.getElementById('dr-kode');
  sub.addEventListener('change',function(){kode.value='';form.submit();}); kode.addEventListener('change',function(){form.submit();});
  if(typeof Chart==='undefined') return;
  const data={{ Illuminate\Support\Js::from($dashboard) }};
  const navy=(getComputedStyle(document.documentElement).getPropertyValue('--navy')||'').trim()||'#15314a';
  const rupiah=value=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(value||0);
  const common={responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:14}},tooltip:{callbacks:{label:item=>item.label+': '+rupiah(item.raw)}}}};
  const composition=document.getElementById('dr-composition'); if(composition)new Chart(composition,{type:'doughnut',data:{labels:['Realisasi Aktual','NPD Belum Selesai','Sisa Tersedia'],datasets:[{data:[data.total.realisasi_aktual,data.dana_terikat_belum_selesai,Math.max(0,data.total.sisa_tersedia)],backgroundColor:[navy,'#d9a938','#dbe5ee'],borderWidth:0}]},options:common});
  const rak=document.getElementById('dr-rak'); if(rak)new Chart(rak,{type:'doughnut',data:{labels:['Realisasi s.d. Bulan','Sisa Target RAK'],datasets:[{data:[data.realisasi_sd_bulan,Math.max(0,data.target_rak_sd_bulan-data.realisasi_sd_bulan)],backgroundColor:[navy,'#e5e9f0'],borderWidth:0}]},options:common});
});
</script>
@endsection
