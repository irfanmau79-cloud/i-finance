@extends('layouts.app')

@section('activeNav', 'dashboard')
@section('title', 'Dashboard Realisasi Anggaran')

@section('content')
<style>
  .dr-filter{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:12px;align-items:end}.dr-filter label{display:block;font-size:12px;font-weight:700;color:var(--tegas);margin-bottom:5px}.dr-filter-actions{display:flex;gap:8px}.dr-filter-actions .btn{padding:9px 14px;white-space:nowrap}
  .dr-donut{height:250px}.dr-notice{border-radius:10px;padding:11px 13px;margin:0 0 16px;font-size:13px;background:var(--warn-bg);color:var(--warn);border:1px solid var(--garis-warn)}.dr-danger{background:var(--err-bg);color:var(--err);border-color:#f1b9b5}
  .dr-table-wrap{width:100%;max-width:100%;overflow-x:hidden}.dr-table{width:100%;min-width:0;table-layout:fixed}.dr-table th:first-child{width:28%}.dr-table th:not(:first-child){width:12%}.dr-table th a{display:inline-flex;max-width:100%;gap:4px;color:inherit;text-decoration:none;white-space:normal;overflow-wrap:anywhere}.dr-table th.num,.dr-table td.num{text-align:right;white-space:normal;overflow-wrap:anywhere}.dr-table .sub-name{font-weight:700;color:var(--tegas);min-width:0;overflow-wrap:anywhere}.dr-table .program{display:block;color:var(--mut);font-size:11px;font-weight:400;margin-top:2px;overflow-wrap:anywhere}.dr-positive{color:var(--ok);font-weight:700}.dr-negative{color:var(--err);font-weight:700}.dr-empty{text-align:center;color:var(--mut);padding:36px 12px}
  @media(max-width:720px){.dr-filter{grid-template-columns:1fr}}
</style>

@php
    $rupiah = fn (?float $nilai) => $nilai === null ? 'Belum tersedia' : fmt_rupiah($nilai);
    $persen = fn (?float $nilai) => $nilai === null ? 'Belum tersedia' : number_format($nilai, 2, ',', '.').' %';
    $ringkas = function (?float $nilai) {
        if ($nilai === null) return 'Belum tersedia';
        $abs = abs($nilai);
        if ($abs >= 1_000_000_000) return number_format($nilai / 1_000_000_000, 1, '.', '').'M';
        if ($abs >= 1_000_000) return number_format($nilai / 1_000_000, 1, '.', '').'Jt';
        if ($abs >= 1_000) return number_format($nilai / 1_000, 1, '.', '').'Rb';
        return number_format($nilai, 0, '.', '');
    };
    $persenRak = fn (?float $realisasi, ?float $target) => ($target === null || $target <= 0)
        ? 'Belum tersedia'
        : $persen(($realisasi / $target) * 100);
    $sortUrl = function (string $key) use ($dashboard, $filters) {
        $nextDirection = $dashboard['sort'] === $key && $dashboard['direction'] === 'asc' ? 'desc' : 'asc';
        return route('dashboard.index', $filters + ['sort' => $key, 'direction' => $nextDirection]);
    };
    $sortMark = fn (string $key) => $dashboard['sort'] === $key ? ($dashboard['direction'] === 'asc' ? '▲' : '▼') : '';
@endphp

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Dashboard Realisasi Anggaran</b></div>
    <div class="ph-title">Dashboard Realisasi Anggaran</div>
  </div>
  <div class="ph-actions">
    <div class="ph-year">Tahun Anggaran <b>{{ $dashboard['tahun'] }}</b></div>
  </div>
</div>

<div class="dash-card">
  <form method="GET" action="{{ route('dashboard.index') }}" class="dr-filter" id="dr-filter-form">
    @php
      $labelTerpilih = function (array $daftar, string $nilai) {
          foreach ($daftar as $item) {
              if ((string) $item['value'] === $nilai) return $item['label'];
          }
          return '';
      };
    @endphp
    <div>
      <label for="dr-sub-inp">Sub Kegiatan</label>
      <div class="kombo" id="dr-sub-wrap" data-semua="Semua Sub Kegiatan">
        <input type="text" class="kb-inp" id="dr-sub-inp" autocomplete="off" role="combobox" aria-expanded="false"
               placeholder="Semua Sub Kegiatan" value="{{ $labelTerpilih($pilihan['sub_kegiatan']->all(), $filters['sub_kegiatan']) }}">
        <svg class="kb-chev" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        <input type="hidden" name="sub_kegiatan" id="dr-sub" value="{{ $filters['sub_kegiatan'] }}">
        <div class="kb-drop" id="dr-sub-drop" role="listbox"></div>
      </div>
    </div>
    <div>
      <label for="dr-kode-inp">Kode Rekening</label>
      <div class="kombo" id="dr-kode-wrap" data-semua="Semua Kode Rekening">
        <input type="text" class="kb-inp" id="dr-kode-inp" autocomplete="off" role="combobox" aria-expanded="false"
               placeholder="Semua Kode Rekening" value="{{ $labelTerpilih($pilihan['kode_rekening_berlabel']->all(), $filters['kode_rekening']) }}">
        <svg class="kb-chev" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        <input type="hidden" name="kode_rekening" id="dr-kode" value="{{ $filters['kode_rekening'] }}">
        <div class="kb-drop" id="dr-kode-drop" role="listbox"></div>
      </div>
    </div>
    <div class="dr-filter-actions"><button class="btn prim" type="submit">Terapkan</button><a class="btn" href="{{ route('dashboard.index') }}">Reset</a></div>
  </form>
</div>

<div class="kpi-grid">
  <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
    <div class="kpi-top">
      <div class="kpi-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="14" x2="8" y2="16"/><line x1="12" y1="14" x2="12" y2="16"/><line x1="16" y1="14" x2="16" y2="16"/></svg></div>
      <div><div class="kpi-lbl">Pagu Anggaran</div></div>
    </div>
    <div class="kpi-val">{{ $rupiah($dashboard['total']['pagu']) }}</div>
    <div class="kpi-note">Realisasi {{ $rupiah($dashboard['total']['realisasi_aktual']) }}</div>
  </div>
  <div class="kpi" style="--kc:#0f6e56;--kbg:#0f6e5614;">
    <div class="kpi-top">
      <div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
      <div><div class="kpi-lbl">Realisasi SP2D</div></div>
    </div>
    <div class="kpi-val">{{ $rupiah($dashboard['realisasi_sp2d']['nominal']) }}</div>
    <div class="kpi-note">{{ $persen($dashboard['realisasi_sp2d']['persentase']) }}</div>
  </div>
  <div class="kpi" style="--kc:#7c3aed;--kbg:#7c3aed14;">
    <div class="kpi-top">
      <div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
      <div><div class="kpi-lbl">Realisasi SPJ3</div></div>
    </div>
    <div class="kpi-val">{{ $rupiah($dashboard['total']['realisasi_aktual']) }}</div>
    <div class="kpi-note">{{ $persen($dashboard['total']['persentase_realisasi']) }}</div>
  </div>
  <div class="kpi" style="--kc:#b07d1d;--kbg:#b07d1d14;">
    <div class="kpi-top">
      <div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg></div>
      <div><div class="kpi-lbl">Sisa Anggaran</div></div>
    </div>
    <div class="kpi-val">{{ $rupiah($dashboard['sisa_anggaran_spj3']['nominal']) }}</div>
    <div class="kpi-note">{{ $persen($dashboard['sisa_anggaran_spj3']['persentase']) }}</div>
  </div>
</div>

@if($dashboard['filter_aktif'])<div class="dr-notice">SPM UP/GU/TU bersifat nasional, tidak per Sub Kegiatan &mdash; persentase Realisasi SP2D saat filter aktif hanya indikatif.</div>@endif
@if($dashboard['total']['sisa_tersedia'] < 0)<div class="dr-notice dr-danger">Sisa tersedia bernilai negatif. Grafik komposisi membatasi irisan sisa pada nol, tetapi KPI dan tabel tetap menampilkan nilai sebenarnya.</div>@endif
@if($dashboard['pesan_rak'])<div class="dr-notice" role="status">{{ $dashboard['pesan_rak'] }}</div>@endif

@if($dashboard['kosong'])
  <div class="dash-card dr-empty">Tidak ada data anggaran aktif untuk filter ini.</div>
@else
<div class="dash-grid">
  <div class="dash-card">
    <h3>Realisasi 1 Tahun</h3>
    <div class="sub">Tahun Anggaran {{ $dashboard['tahun'] }}</div>
    <div class="donut-wrap dr-donut"><canvas id="dr-composition"></canvas>
      <div class="donut-center"><div class="big">{{ $persen($dashboard['total']['persentase_realisasi']) }}</div><div class="lbl">realisasi / pagu</div></div>
    </div>
    <div class="dash-legend" id="dr-composition-legend"></div>
  </div>
  <div class="dash-card">
    <h3>Realisasi terhadap RAK</h3>
    <div class="sub">Target kumulatif s.d. {{ $dashboard['bulan_acuan_label'] }} {{ $dashboard['tahun'] }}</div>
    @if($dashboard['target_rak_sd_bulan'] !== null && $dashboard['target_rak_sd_bulan'] > 0)
      <div class="donut-wrap dr-donut"><canvas id="dr-rak"></canvas>
        <div class="donut-center"><div class="big">{{ $persen($dashboard['persentase_target_rak']) }}</div><div class="lbl">{{ $ringkas($dashboard['realisasi_sd_bulan']) }}/{{ $ringkas($dashboard['target_rak_sd_bulan']) }}</div></div>
      </div>
      <div class="dash-legend" id="dr-rak-legend"></div>
    @elseif($dashboard['target_rak_sd_bulan'] === 0.0)
      <div class="dr-empty">Target RAK resmi sampai bulan berjalan bernilai nol. Persentase tidak dapat dihitung.</div>
    @else
      <div class="dr-empty">Target RAK sampai bulan berjalan belum tersedia secara lengkap.</div>
    @endif
  </div>
</div>

<div class="dash-card">
  <h3>Realisasi per Sub Kegiatan</h3>
  <div class="dr-table-wrap"><table class="realisasi dr-table"><thead><tr>
    <th><a href="{{ $sortUrl('nama') }}">Sub Kegiatan {{ $sortMark('nama') }}</a></th>
    @foreach(['pagu'=>'Pagu Anggaran','realisasi_aktual'=>'Realisasi','sisa_tersedia'=>'Sisa Anggaran','persentase_realisasi'=>'%Realisasi','target_rak'=>'%Realisasi RAK','deviasi_rupiah'=>'%Deviasi'] as $key=>$label)<th class="num"><a href="{{ $sortUrl($key) }}">{{ $label }} {{ $sortMark($key) }}</a></th>@endforeach
  </tr></thead><tbody>
    @foreach($dashboard['rows'] as $row)<tr><td class="sub-name">{{ $row['nama'] }}<span class="program">{{ $row['program'] }} &middot; {{ $row['kegiatan'] }}</span></td>
      <td class="num">{{ $rupiah($row['angka']['pagu']) }}</td><td class="num">{{ $rupiah($row['angka']['realisasi_aktual']) }}</td><td class="num">{{ $rupiah($row['angka']['sisa_tersedia']) }}</td><td class="num">{{ $persen($row['angka']['persentase_realisasi']) }}</td><td class="num">{{ $persenRak($row['realisasi_sd_bulan'], $row['target_rak']) }}</td><td class="num {{ ($row['deviasi_rupiah'] ?? 0) >= 0 ? 'dr-positive' : 'dr-negative' }}">{{ $row['deviasi_rupiah'] !== null ? $persen($row['deviasi_persen']) : 'Belum tersedia' }}</td>
    </tr>@endforeach
  </tbody></table></div>
</div>
@endif

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@include('layouts.partials.chart-tema')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form=document.getElementById('dr-filter-form');

  /**
   * Combobox yang bisa diketik. Nilainya disimpan di input tersembunyi;
   * memilih satu pilihan langsung mengirim formulir, sama seperti perilaku
   * <select> sebelumnya - jadi tidak perlu menekan Terapkan.
   */
  function buatKombo(id, pilihan, saatPilih) {
    const wrap = document.getElementById('dr-' + id + '-wrap');
    const inp = document.getElementById('dr-' + id + '-inp');
    const drop = document.getElementById('dr-' + id + '-drop');
    const nilai = document.getElementById('dr-' + id);
    const semua = [{value: '', label: wrap.dataset.semua}].concat(pilihan);
    const esc = t => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };

    let label = inp.value;
    let sorot = -1;

    const tampil = () => {
      const q = inp.value.trim().toLowerCase();

      return semua.filter(o => !q || q === label.toLowerCase() || o.label.toLowerCase().includes(q));
    };

    function gambar() {
      const daftar = tampil();
      drop.innerHTML = daftar.length
        ? daftar.map((o, i) => '<div class="kb-item' + (String(o.value) === nilai.value ? ' terpilih' : '') +
            (i === sorot ? ' sorot' : '') + '" role="option" data-nilai="' + esc(o.value) + '">' + esc(o.label) + '</div>').join('')
        : '<div class="kb-kosong">Tidak ditemukan</div>';
    }

    function buka() { sorot = -1; gambar(); wrap.classList.add('buka'); inp.setAttribute('aria-expanded', 'true'); }
    function tutup() { wrap.classList.remove('buka'); inp.setAttribute('aria-expanded', 'false'); inp.value = label; }

    function pilih(v) {
      nilai.value = v;
      if (saatPilih) saatPilih();
      form.submit();
    }

    inp.addEventListener('focus', buka);
    inp.addEventListener('click', buka);
    inp.addEventListener('input', function () { sorot = -1; gambar(); wrap.classList.add('buka'); });
    inp.addEventListener('blur', () => setTimeout(tutup, 130));
    inp.addEventListener('keydown', function (e) {
      const daftar = tampil();
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!wrap.classList.contains('buka')) buka();
        sorot = Math.min(Math.max(sorot + (e.key === 'ArrowDown' ? 1 : -1), 0), daftar.length - 1);
        gambar();
        const el = drop.querySelector('.kb-item.sorot');
        if (el) el.scrollIntoView({block: 'nearest'});
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (daftar[sorot]) pilih(daftar[sorot].value);
      } else if (e.key === 'Escape') { tutup(); inp.blur(); }
    });
    drop.addEventListener('mousedown', function (e) {
      const item = e.target.closest('.kb-item[data-nilai]');
      if (!item) return;
      e.preventDefault();
      pilih(item.dataset.nilai);
    });
  }

  // Mengganti Sub Kegiatan mengosongkan Kode Rekening - kodenya menyempit
  // mengikuti sub kegiatan, jadi pilihan lama belum tentu masih ada.
  buatKombo('sub', {{ Illuminate\Support\Js::from($pilihan['sub_kegiatan']) }},
    function () { document.getElementById('dr-kode').value = ''; });
  buatKombo('kode', {{ Illuminate\Support\Js::from($pilihan['kode_rekening_berlabel']) }});
  if(typeof Chart==='undefined') return;
  const data={{ Illuminate\Support\Js::from($dashboard) }};
  const palet=warnaGrafik();
  const rupiah=value=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(value||0);
  const common={responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{display:false},tooltip:{callbacks:{label:item=>item.label+': '+rupiah(item.raw)}}}};
  function renderLegend(elId,labels,colors){
    const el=document.getElementById(elId); if(!el) return;
    el.innerHTML=labels.map((label,i)=>'<div class="li"><span class="dot" style="background:'+colors[i]+'"></span>'+label+'</div>').join('');
  }
  const composition=document.getElementById('dr-composition');
  if(composition){
    const compColors=[palet.utama,palet.emas,palet.sisa];
    new Chart(composition,{type:'doughnut',data:{labels:['Realisasi Aktual','NPD Belum Selesai','Sisa Tersedia'],datasets:[{data:[data.total.realisasi_aktual,data.dana_terikat_belum_selesai,Math.max(0,data.total.sisa_tersedia)],backgroundColor:compColors,borderWidth:0}]},options:common});
    renderLegend('dr-composition-legend',['Realisasi Aktual','NPD Belum Selesai','Sisa Tersedia'],compColors);
  }
  const rak=document.getElementById('dr-rak');
  if(rak){
    const rakColors=[palet.utama,palet.sisa];
    new Chart(rak,{type:'doughnut',data:{labels:['Realisasi s.d. Bulan','Sisa Target RAK'],datasets:[{data:[data.realisasi_sd_bulan,Math.max(0,data.target_rak_sd_bulan-data.realisasi_sd_bulan)],backgroundColor:rakColors,borderWidth:0}]},options:common});
    renderLegend('dr-rak-legend',['Realisasi s.d. Bulan','Sisa Target RAK'],rakColors);
  }
});
</script>
@endsection
