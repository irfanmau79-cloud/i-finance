@extends('layouts.app')

@section('activeNav', 'tren-realisasi')
@section('title', 'Analisis dan Tren')

@section('content')
<style>
  .an-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px}
  .an-head h2{margin:0;color:var(--tegas);font-size:22px}
  .an-filter{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto;gap:12px;align-items:end}
  .an-filter label{display:block;font-size:12px;font-weight:700;color:var(--tegas);margin-bottom:5px}
  .an-filter-actions{display:flex;gap:8px}.an-filter-actions .btn{padding:9px 14px;white-space:nowrap}
  .an-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:16px 0}
  .an-kpi{background:var(--surface);border:1px solid var(--line);border-radius:13px;padding:15px 16px;box-shadow:var(--shadow)}
  .an-kpi .label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--mut)}
  .an-kpi .value{font-size:20px;font-weight:800;color:var(--tegas);margin-top:5px;font-variant-numeric:tabular-nums}
  .an-kpi .note{font-size:11.5px;color:var(--mut);margin-top:3px}.an-kpi .positive{color:var(--ok)}.an-kpi .negative{color:var(--err)}
  .an-notice{border-radius:10px;padding:11px 13px;margin:12px 0;font-size:13px;background:var(--warn-bg);color:var(--warn);border:1px solid var(--garis-warn)}
  .an-chart-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .an-chart-head h3{margin:0;color:var(--tegas)}
  .an-chart-box{position:relative;height:430px;margin-top:14px}.an-chart-error,.an-empty{padding:52px 16px;text-align:center;color:var(--mut)}
  .an-chart-error{display:none;color:var(--err)}
  @media(max-width:1050px){.an-kpis{grid-template-columns:1fr 1fr}}
  @media(max-width:720px){.an-filter{grid-template-columns:1fr}.an-kpis{grid-template-columns:1fr}.an-chart-box{height:340px}}
</style>

@php
    $rupiah = fn (?float $nilai) => $nilai === null ? 'Tidak tersedia' : fmt_rupiah($nilai);
    $persen = fn (?float $nilai) => $nilai === null ? 'Tidak tersedia' : number_format($nilai, 2, ',', '.').' %';
    $deviasiClass = ($analisis['deviasi_rupiah'] ?? 0) >= 0 ? 'positive' : 'negative';
    $cariLabel = function ($options, $value) {
        foreach ($options as $opt) {
            if ((string) $opt['value'] === (string) $value) return $opt['label'];
        }
        return '';
    };
    $subSelectedLabel = $filters['sub_kegiatan'] !== '' ? $cariLabel($pilihan['sub_kegiatan'], $filters['sub_kegiatan']) : '';
    $kodeSelectedLabel = $filters['kode_rekening'] !== '' ? $cariLabel($pilihan['kode_rekening_berlabel'], $filters['kode_rekening']) : '';
    $subOptionsJs = collect([['value' => '', 'label' => 'Semua Sub Kegiatan']])->concat($pilihan['sub_kegiatan']);
    $kodeOptionsJs = collect([['value' => '', 'label' => 'Semua Kode Rekening']])->concat($pilihan['kode_rekening_berlabel']);
@endphp

<div class="an-head">
    <div>
        <h2>Analisis dan Tren</h2>
    </div>
</div>

<div class="dash-card">
    <form method="GET" action="{{ route('analisis.index') }}" class="an-filter" id="an-filter-form">
        <div>
            <label for="an-sub-inp">Sub Kegiatan</label>
            <div class="nsearch" id="an-sub-wrap">
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="ns-inp" id="an-sub-inp" autocomplete="off" placeholder="Semua Sub Kegiatan" value="{{ $subSelectedLabel }}">
                <input type="hidden" name="sub_kegiatan" id="an-sub" value="{{ $filters['sub_kegiatan'] }}">
                <div class="ns-drop" id="an-sub-drop"></div>
            </div>
        </div>
        <div>
            <label for="an-kode-inp">Kode Rekening</label>
            <div class="nsearch" id="an-kode-wrap">
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="ns-inp" id="an-kode-inp" autocomplete="off" placeholder="Semua Kode Rekening" value="{{ $kodeSelectedLabel }}">
                <input type="hidden" name="kode_rekening" id="an-kode" value="{{ $filters['kode_rekening'] }}">
                <div class="ns-drop" id="an-kode-drop"></div>
            </div>
        </div>
        <div class="an-filter-actions">
            <button class="btn prim" type="submit">Terapkan</button>
            <a class="btn" href="{{ route('analisis.index') }}">Reset</a>
        </div>
    </form>
</div>

<div class="an-kpis">
    <div class="an-kpi"><div class="label">Total Pagu</div><div class="value">{{ $rupiah($analisis['pagu']) }}</div><div class="note">{{ $analisis['jumlah_master'] }} mata anggaran aktif</div></div>
    <div class="an-kpi"><div class="label">Realisasi Aktual</div><div class="value">{{ $rupiah($analisis['realisasi_aktual']) }}</div><div class="note">NPD Selesai + SPM LS TA {{ $analisis['tahun'] }}</div></div>
    <div class="an-kpi"><div class="label">Capaian Tahun</div><div class="value">{{ $persen($analisis['capaian_tahun']) }}</div><div class="note">Realisasi aktual terhadap total pagu</div></div>
    <div class="an-kpi"><div class="label">Deviasi terhadap RAK</div><div class="value {{ $analisis['deviasi_persen'] !== null ? $deviasiClass : '' }}">{{ $persen($analisis['deviasi_persen']) }}</div><div class="note">@if ($analisis['deviasi_rupiah'] !== null){{ $rupiah($analisis['deviasi_rupiah']) }} s.d. {{ $analisis['bulan_acuan_label'] }}@else RAK s.d. {{ $analisis['bulan_acuan_label'] }} belum lengkap @endif</div></div>
</div>

@if ($analisis['pesan_rak'])
    <div class="an-notice" role="status">{{ $analisis['pesan_rak'] }}</div>
@endif

<div class="dash-card">
    <div class="an-chart-head">
        <div><h3>Realisasi Aktual dan Target RAK</h3></div>
        <div class="an-seg" role="group" aria-label="Mode grafik">
            <button type="button" class="an-seg-btn active" data-an-mode="bulanan">Bulanan</button>
            <button type="button" class="an-seg-btn" data-an-mode="kumulatif">Kumulatif</button>
        </div>
    </div>
    @if ($analisis['kosong'])
        <div class="an-empty">Tidak ada data anggaran atau transaksi untuk filter ini.</div>
    @else
        <div class="an-chart-box"><canvas id="an-chart" aria-label="Grafik realisasi aktual dan target RAK" role="img"></canvas><div class="an-chart-error" id="an-chart-error">Grafik tidak dapat dimuat. Data KPI tetap tersedia.</div></div>
    @endif
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@include('layouts.partials.chart-tema')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('an-filter-form');

    function initSearchSelect(inputId, hiddenId, dropId, options, onSelect) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const drop = document.getElementById(dropId);
        let selectedLabel = input.value;

        function renderList(query) {
            const q = (query || '').toLowerCase().trim();
            const items = options.filter(o => !q || o.label.toLowerCase().includes(q));
            drop.innerHTML = items.length
                ? items.map(o => '<div class="ns-item" data-value="'+String(o.value).replace(/"/g,'&quot;')+'">'+o.label.replace(/</g,'&lt;')+'</div>').join('')
                : '<div class="ns-empty">Tidak ditemukan</div>';
            drop.classList.add('show');
        }
        function hide() { drop.classList.remove('show'); }

        input.addEventListener('focus', () => renderList(input.value === selectedLabel ? '' : input.value));
        input.addEventListener('input', () => renderList(input.value));
        input.addEventListener('blur', () => setTimeout(() => { hide(); input.value = selectedLabel; }, 150));
        drop.addEventListener('mousedown', function (e) {
            const item = e.target.closest('.ns-item[data-value]');
            if (!item) return;
            e.preventDefault();
            hidden.value = item.dataset.value;
            selectedLabel = item.textContent;
            input.value = selectedLabel;
            hide();
            if (onSelect) onSelect(item.dataset.value);
        });
    }

    initSearchSelect('an-sub-inp', 'an-sub', 'an-sub-drop', {{ Illuminate\Support\Js::from($subOptionsJs) }}, function () {
        document.getElementById('an-kode-inp').value = '';
        document.getElementById('an-kode').value = '';
        form.submit();
    });
    initSearchSelect('an-kode-inp', 'an-kode', 'an-kode-drop', {{ Illuminate\Support\Js::from($kodeOptionsJs) }}, function () {
        form.submit();
    });

    const canvas = document.getElementById('an-chart');
    if (!canvas) return;
    if (typeof Chart === 'undefined') {
        canvas.style.display = 'none';
        document.getElementById('an-chart-error').style.display = 'block';
        return;
    }

    const data = {{ Illuminate\Support\Js::from($analisis) }};
    const formatRupiah = value => new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:0}).format(value || 0);
    const formatPersen = value => new Intl.NumberFormat('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2}).format(value)+' %';
    let mode = 'bulanan';
    let chart;

    function datasets() {
        const realisasi = mode === 'kumulatif' ? data.realisasi_kumulatif : data.realisasi_bulanan;
        const target = mode === 'kumulatif' ? data.target_kumulatif : data.target_bulanan;
        const result = [{type:'bar',label:mode === 'kumulatif' ? 'Realisasi Kumulatif' : 'Realisasi Bulanan',data:realisasi,backgroundColor:warnaGrafik().utama,borderRadius:5,maxBarThickness:42,order:2}];
        if (data.rak_tersedia) result.push({type:'line',label:mode === 'kumulatif' ? 'Target RAK Kumulatif' : 'Target RAK Bulanan',data:target,borderColor:warnaGrafik().emas,backgroundColor:'#d9a938',borderWidth:3,tension:.25,pointRadius:4,pointHoverRadius:6,spanGaps:false,order:1});
        return result;
    }

    function render() {
        if (chart) chart.destroy();
        chart = new Chart(canvas, {
            data:{labels:data.bulan,datasets:datasets()},
            options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
                plugins:{legend:{position:'top',labels:{usePointStyle:true,padding:16}},tooltip:{callbacks:{
                    label:item => item.dataset.label+': '+(item.raw === null ? 'Tidak tersedia' : formatRupiah(item.raw)),
                    footer:items => {
                        const values = Object.fromEntries(items.map(item => [item.dataset.type, item.raw]));
                        return values.line !== undefined && values.line !== null && values.line > 0
                            ? 'Realisasi terhadap target: '+formatPersen((values.bar / values.line) * 100)
                            : '';
                    }
                }}},
                scales:{y:{beginAtZero:true,ticks:{callback:value => formatRupiah(value)},title:{display:true,text:'Rupiah'}}}
            }
        });
    }

    document.querySelectorAll('[data-an-mode]').forEach(function (button) {
        button.addEventListener('click', function () {
            mode = button.dataset.anMode;
            document.querySelectorAll('[data-an-mode]').forEach(item => item.classList.toggle('active', item === button));
            render();
        });
    });
    render();
});
</script>
@endsection
