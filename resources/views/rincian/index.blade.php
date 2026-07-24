@extends('layouts.app')

@section('activeNav', 'rincian')
@section('title', 'Rincian Realisasi')

@section('content')
<style>
  .rr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px}
  .rr-head h2{margin:0;color:var(--navy);font-size:22px}
  .rr-actions{display:flex;gap:8px;flex-wrap:wrap}.rr-actions .btn{padding:8px 14px}
  .rr-filter{display:grid;grid-template-columns:minmax(190px,1fr) minmax(190px,1fr) minmax(190px,1fr) minmax(210px,1.3fr) auto;gap:10px;align-items:end}
  .rr-filter label{display:block;color:var(--navy);font-size:12px;font-weight:700;margin-bottom:5px}
  .rr-filter .rr-filter-actions{display:flex;gap:7px}.rr-filter .btn{padding:9px 14px;white-space:nowrap}
  .rr-wrap{overflow:auto;border:1px solid var(--line);border-radius:11px;margin-top:16px}
  table.rr-tree{min-width:1060px;margin:0}table.rr-tree th{white-space:nowrap}
  table.rr-tree .rr-label{min-width:340px}table.rr-tree .rr-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
  .rr-toggle{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:0;background:transparent;color:var(--navy);cursor:pointer;border-radius:5px}
  .rr-toggle:hover{background:rgba(21,49,74,.09)}.rr-toggle svg{width:14px;height:14px;transition:transform .15s;stroke:currentColor;fill:none;stroke-width:2.2}
  .rr-toggle[aria-expanded="true"] svg{transform:rotate(90deg)}.rr-submeta{display:block;color:var(--mut);font-size:11px;font-weight:400;margin-left:21px}
  .rr-empty{text-align:center;padding:34px 16px;color:var(--mut)}
  @media(max-width:1200px){.rr-filter{grid-template-columns:1fr 1fr}.rr-filter .rr-filter-actions{grid-column:1/-1}}
  @media(max-width:620px){.rr-filter{grid-template-columns:1fr}.rr-filter .rr-filter-actions{grid-column:auto}}
</style>

@php
    $rupiah = fn (float $nilai) => fmt_rupiah($nilai);
    $persen = fn (float $nilai) => number_format($nilai, 2, ',', '.').' %';
    $cariLabel = function ($options, $value) {
        foreach ($options as $opt) {
            if ((string) $opt['value'] === (string) $value) return $opt['label'];
        }
        return '';
    };
    $subSelectedLabel = $filters['sub_kegiatan'] !== '' ? $cariLabel($subKegiatanOptions, $filters['sub_kegiatan']) : '';
    $kodeSelectedLabel = $filters['kode_rekening'] !== '' ? $cariLabel($kodeRekeningOptions, $filters['kode_rekening']) : '';
    $taggingSelectedLabel = $filters['tagging'] === 'tanpa'
        ? 'Tanpa Tagging'
        : ($filters['tagging'] !== '' ? $cariLabel($taggingOptions, $filters['tagging']) : '');

    $subOptionsJs = collect([['value' => '', 'label' => 'Semua Sub Kegiatan']])->concat($subKegiatanOptions);
    $kodeOptionsJs = collect([['value' => '', 'label' => 'Semua Kode Rekening']])->concat($kodeRekeningOptions);
    $taggingOptionsJs = collect([['value' => '', 'label' => 'Semua Tagging']])
        ->when($memilikiTanpaTagging, fn ($c) => $c->push(['value' => 'tanpa', 'label' => 'Tanpa Tagging']))
        ->concat($taggingOptions);
@endphp

<div class="dash-card">
    <div class="rr-head">
        <div>
            <h2>Rincian Realisasi</h2>
        </div>
        <div class="rr-actions">
            <button type="button" class="btn" id="rr-close-all">Tutup Semua</button>
            <button type="button" class="btn prim" id="rr-open-all">Buka Semua</button>
        </div>
    </div>

    <form method="GET" action="{{ route('rincian.index') }}" class="rr-filter">
        <div>
            <label for="rr-sub-inp">Sub Kegiatan</label>
            <div class="nsearch" id="rr-sub-wrap">
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="ns-inp" id="rr-sub-inp" autocomplete="off" placeholder="Semua Sub Kegiatan" value="{{ $subSelectedLabel }}">
                <input type="hidden" name="sub_kegiatan" id="rr-sub" value="{{ $filters['sub_kegiatan'] }}">
                <div class="ns-drop" id="rr-sub-drop"></div>
            </div>
        </div>
        <div>
            <label for="rr-kode-inp">Kode Rekening</label>
            <div class="nsearch" id="rr-kode-wrap">
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="ns-inp" id="rr-kode-inp" autocomplete="off" placeholder="Semua Kode Rekening" value="{{ $kodeSelectedLabel }}">
                <input type="hidden" name="kode_rekening" id="rr-kode" value="{{ $filters['kode_rekening'] }}">
                <div class="ns-drop" id="rr-kode-drop"></div>
            </div>
        </div>
        <div>
            <label for="rr-tagging-inp">Tagging</label>
            <div class="nsearch" id="rr-tagging-wrap">
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="ns-inp" id="rr-tagging-inp" autocomplete="off" placeholder="Semua Tagging" value="{{ $taggingSelectedLabel }}">
                <input type="hidden" name="tagging" id="rr-tagging" value="{{ $filters['tagging'] }}">
                <div class="ns-drop" id="rr-tagging-drop"></div>
            </div>
        </div>
        <div>
            <label for="rr-search">Pencarian</label>
            <input id="rr-search" name="q" value="{{ $filters['q'] }}" placeholder="Program, kegiatan, rekening, tagging...">
        </div>
        <div class="rr-filter-actions">
            <button class="btn prim" type="submit">Terapkan</button>
            <a class="btn" href="{{ route('rincian.index') }}">Reset</a>
        </div>
    </form>

    <div class="rr-wrap">
        <table class="realisasi pivot rr-tree">
            <thead><tr><th class="rr-label">Uraian</th><th class="rr-num">Pagu Anggaran</th><th class="rr-num">Realisasi</th><th class="rr-num">Sisa Anggaran</th><th class="rr-num">%Realisasi</th></tr></thead>
            <tbody>
            @forelse ($tree as $subIndex => $sub)
                @php($subNode = 'rr-sub-'.$subIndex)
                <tr class="row-lvl0 {{ $subIndex % 2 === 0 ? 'lvl0-gelap' : 'lvl0-terang' }}" data-rr-node="{{ $subNode }}">
                    <td class="rr-label"><div class="uraian"><button type="button" class="rr-toggle" data-rr-toggle="{{ $subNode }}" aria-expanded="true" aria-label="Tutup atau buka Sub Kegiatan"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button><span>{{ $sub['nama'] }}<small class="rr-submeta">{{ $sub['program'] }} &middot; {{ $sub['kegiatan'] }}</small></span></div></td>
                    <td class="rr-num">{{ $rupiah($sub['angka']['pagu']) }}</td><td class="rr-num">{{ $rupiah($sub['angka']['realisasi_aktual']) }}</td><td class="rr-num">{{ $rupiah($sub['angka']['sisa_tersedia']) }}</td><td class="rr-num">{{ $persen($sub['angka']['persentase_realisasi']) }}</td>
                </tr>
                @foreach ($sub['rekening'] as $rekeningIndex => $rekening)
                    @php($rekeningNode = $subNode.'-rekening-'.$rekeningIndex)
                    <tr class="row-lvl1" data-rr-node="{{ $rekeningNode }}" data-rr-ancestors="{{ $subNode }}">
                        <td class="rr-label ind1"><div class="uraian"><button type="button" class="rr-toggle" data-rr-toggle="{{ $rekeningNode }}" aria-expanded="true" aria-label="Tutup atau buka Kode Rekening"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button><span><span class="kode-chip">{{ $rekening['kode'] }}</span> {{ $rekening['uraian'] }}</span></div></td>
                        <td class="rr-num">{{ $rupiah($rekening['angka']['pagu']) }}</td><td class="rr-num">{{ $rupiah($rekening['angka']['realisasi_aktual']) }}</td><td class="rr-num">{{ $rupiah($rekening['angka']['sisa_tersedia']) }}</td><td class="rr-num">{{ $persen($rekening['angka']['persentase_realisasi']) }}</td>
                    </tr>
                    @foreach ($rekening['tagging'] as $tagging)
                        <tr class="row-lvl2" data-rr-ancestors="{{ $subNode }} {{ $rekeningNode }}">
                            <td class="rr-label ind2"><div class="uraian"><span class="spacer"></span><span>{{ $tagging['nama'] }}</span></div></td>
                            <td class="rr-num">{{ $rupiah($tagging['angka']['pagu']) }}</td><td class="rr-num">{{ $rupiah($tagging['angka']['realisasi_aktual']) }}</td><td class="rr-num">{{ $rupiah($tagging['angka']['sisa_tersedia']) }}</td><td class="rr-num">{{ $persen($tagging['angka']['persentase_realisasi']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @empty
                <tr><td colspan="5" class="rr-empty">Tidak ada mata anggaran aktif yang sesuai filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const expanded = new Set();
    document.querySelectorAll('[data-rr-toggle]').forEach(btn => expanded.add(btn.dataset.rrToggle));

    function refresh() {
        document.querySelectorAll('[data-rr-ancestors]').forEach(function (row) {
            const ancestors = row.dataset.rrAncestors.split(/\s+/).filter(Boolean);
            row.hidden = !ancestors.every(node => expanded.has(node));
        });
        document.querySelectorAll('[data-rr-toggle]').forEach(btn => btn.setAttribute('aria-expanded', expanded.has(btn.dataset.rrToggle) ? 'true' : 'false'));
    }

    document.querySelectorAll('tr[data-rr-node]').forEach(function (row) {
        const node = row.dataset.rrNode;
        row.addEventListener('click', function () {
            expanded.has(node) ? expanded.delete(node) : expanded.add(node);
            refresh();
        });
    });

    document.getElementById('rr-close-all').addEventListener('click', function () { expanded.clear(); refresh(); });
    document.getElementById('rr-open-all').addEventListener('click', function () { document.querySelectorAll('[data-rr-toggle]').forEach(btn => expanded.add(btn.dataset.rrToggle)); refresh(); });

    function initSearchSelect(inputId, hiddenId, dropId, options) {
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
        });
    }

    initSearchSelect('rr-sub-inp', 'rr-sub', 'rr-sub-drop', {{ Illuminate\Support\Js::from($subOptionsJs) }});
    initSearchSelect('rr-kode-inp', 'rr-kode', 'rr-kode-drop', {{ Illuminate\Support\Js::from($kodeOptionsJs) }});
    initSearchSelect('rr-tagging-inp', 'rr-tagging', 'rr-tagging-drop', {{ Illuminate\Support\Js::from($taggingOptionsJs) }});
});
</script>
@endsection
