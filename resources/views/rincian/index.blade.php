@extends('layouts.app')

@section('activeNav', 'rincian')
@section('title', 'Rincian Realisasi')

@section('content')
<style>
  .rr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px}
  .rr-head h2{margin:0;color:var(--navy);font-size:22px}.rr-head p{margin:3px 0 0;color:var(--mut)}
  .rr-actions{display:flex;gap:8px;flex-wrap:wrap}.rr-actions .btn{padding:8px 14px}
  .rr-filter{display:grid;grid-template-columns:minmax(210px,2fr) minmax(170px,1fr) minmax(170px,1fr) minmax(210px,1.5fr) auto;gap:10px;align-items:end}
  .rr-filter label{display:block;color:var(--navy);font-size:12px;font-weight:700;margin-bottom:5px}
  .rr-filter .rr-filter-actions{display:flex;gap:7px}.rr-filter .btn{padding:9px 14px;white-space:nowrap}
  .rr-summary{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;margin:16px 0}
  .rr-metric{border:1px solid var(--line);border-radius:11px;padding:11px 13px;background:#fff}
  .rr-metric span{display:block;color:var(--mut);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.35px}
  .rr-metric strong{display:block;color:var(--navy);font-size:15px;margin-top:3px;font-variant-numeric:tabular-nums}
  .rr-wrap{overflow:auto;border:1px solid var(--line);border-radius:11px}
  table.rr-tree{min-width:1060px;margin:0}table.rr-tree th{white-space:nowrap}
  table.rr-tree .rr-label{min-width:340px}table.rr-tree .rr-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
  .rr-toggle{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:0;background:transparent;color:var(--navy);cursor:pointer;border-radius:5px}
  .rr-toggle:hover{background:rgba(21,49,74,.09)}.rr-toggle svg{width:14px;height:14px;transition:transform .15s;stroke:currentColor;fill:none;stroke-width:2.2}
  .rr-toggle[aria-expanded="true"] svg{transform:rotate(90deg)}.rr-submeta{display:block;color:var(--mut);font-size:11px;font-weight:400;margin-left:21px}
  .rr-empty{text-align:center;padding:34px 16px;color:var(--mut)}
  @media(max-width:1200px){.rr-filter{grid-template-columns:1fr 1fr}.rr-filter .rr-filter-actions{grid-column:1/-1}.rr-summary{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:620px){.rr-filter{grid-template-columns:1fr}.rr-filter .rr-filter-actions{grid-column:auto}.rr-summary{grid-template-columns:1fr}}
</style>

@php
    $rupiah = fn (float $nilai) => fmt_rupiah($nilai);
    $persen = fn (float $nilai) => number_format($nilai, 2, ',', '.').' %';
@endphp

<div class="dash-card">
    <div class="rr-head">
        <div>
            <h2>Rincian Realisasi</h2>
            <p>Agregasi transaksi aktif menurut Sub Kegiatan, Kode Rekening, dan Tagging.</p>
        </div>
        <div class="rr-actions">
            <button type="button" class="btn" id="rr-close-all">Tutup Semua</button>
            <button type="button" class="btn prim" id="rr-open-all">Buka Semua</button>
        </div>
    </div>

    <form method="GET" action="{{ route('rincian.index') }}" class="rr-filter">
        <div>
            <label for="rr-sub">Sub Kegiatan</label>
            <select id="rr-sub" name="sub_kegiatan">
                <option value="">Semua Sub Kegiatan</option>
                @foreach ($subKegiatanOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($filters['sub_kegiatan'] === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="rr-kode">Kode Rekening</label>
            <select id="rr-kode" name="kode_rekening">
                <option value="">Semua Kode Rekening</option>
                @foreach ($kodeRekeningOptions as $kode)
                    <option value="{{ $kode }}" @selected($filters['kode_rekening'] === $kode)>{{ $kode }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="rr-tagging">Tagging</label>
            <select id="rr-tagging" name="tagging">
                <option value="">Semua Tagging</option>
                @if ($memilikiTanpaTagging)<option value="tanpa" @selected($filters['tagging'] === 'tanpa')>Tanpa Tagging</option>@endif
                @foreach ($taggingOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($filters['tagging'] === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
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

    <div class="rr-summary" aria-label="Total hasil filter">
        <div class="rr-metric"><span>Pagu</span><strong>{{ $rupiah($total['pagu']) }}</strong></div>
        <div class="rr-metric"><span>Dana Terikat NPD</span><strong>{{ $rupiah($total['dana_terikat_npd']) }}</strong></div>
        <div class="rr-metric"><span>Realisasi Aktual</span><strong>{{ $rupiah($total['realisasi_aktual']) }}</strong></div>
        <div class="rr-metric"><span>Sisa Tersedia</span><strong>{{ $rupiah($total['sisa_tersedia']) }}</strong></div>
        <div class="rr-metric"><span>Realisasi / Pagu</span><strong>{{ $persen($total['persentase_realisasi']) }}</strong></div>
    </div>

    <div class="rr-wrap">
        <table class="realisasi pivot rr-tree">
            <thead><tr><th class="rr-label">Uraian</th><th class="rr-num">Pagu</th><th class="rr-num">Dana Terikat NPD</th><th class="rr-num">Realisasi Aktual</th><th class="rr-num">Sisa Tersedia</th><th class="rr-num">Realisasi / Pagu</th></tr></thead>
            <tbody>
            @forelse ($tree as $subIndex => $sub)
                @php($subNode = 'rr-sub-'.$subIndex)
                <tr class="row-lvl0" data-rr-node="{{ $subNode }}">
                    <td class="rr-label"><div class="uraian"><button type="button" class="rr-toggle" data-rr-toggle="{{ $subNode }}" aria-expanded="true" aria-label="Tutup atau buka Sub Kegiatan"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button><span>{{ $sub['nama'] }}<small class="rr-submeta">{{ $sub['program'] }} &middot; {{ $sub['kegiatan'] }}</small></span></div></td>
                    <td class="rr-num">{{ $rupiah($sub['angka']['pagu']) }}</td><td class="rr-num">{{ $rupiah($sub['angka']['dana_terikat_npd']) }}</td><td class="rr-num">{{ $rupiah($sub['angka']['realisasi_aktual']) }}</td><td class="rr-num">{{ $rupiah($sub['angka']['sisa_tersedia']) }}</td><td class="rr-num">{{ $persen($sub['angka']['persentase_realisasi']) }}</td>
                </tr>
                @foreach ($sub['rekening'] as $rekeningIndex => $rekening)
                    @php($rekeningNode = $subNode.'-rekening-'.$rekeningIndex)
                    <tr class="row-lvl1" data-rr-node="{{ $rekeningNode }}" data-rr-ancestors="{{ $subNode }}">
                        <td class="rr-label ind1"><div class="uraian"><button type="button" class="rr-toggle" data-rr-toggle="{{ $rekeningNode }}" aria-expanded="true" aria-label="Tutup atau buka Kode Rekening"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button><span><span class="kode-chip">{{ $rekening['kode'] }}</span> {{ $rekening['uraian'] }}</span></div></td>
                        <td class="rr-num">{{ $rupiah($rekening['angka']['pagu']) }}</td><td class="rr-num">{{ $rupiah($rekening['angka']['dana_terikat_npd']) }}</td><td class="rr-num">{{ $rupiah($rekening['angka']['realisasi_aktual']) }}</td><td class="rr-num">{{ $rupiah($rekening['angka']['sisa_tersedia']) }}</td><td class="rr-num">{{ $persen($rekening['angka']['persentase_realisasi']) }}</td>
                    </tr>
                    @foreach ($rekening['tagging'] as $tagging)
                        <tr class="row-lvl2" data-rr-ancestors="{{ $subNode }} {{ $rekeningNode }}">
                            <td class="rr-label ind2"><div class="uraian"><span class="spacer"></span><span>{{ $tagging['nama'] }}</span></div></td>
                            <td class="rr-num">{{ $rupiah($tagging['angka']['pagu']) }}</td><td class="rr-num">{{ $rupiah($tagging['angka']['dana_terikat_npd']) }}</td><td class="rr-num">{{ $rupiah($tagging['angka']['realisasi_aktual']) }}</td><td class="rr-num">{{ $rupiah($tagging['angka']['sisa_tersedia']) }}</td><td class="rr-num">{{ $persen($tagging['angka']['persentase_realisasi']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @empty
                <tr><td colspan="6" class="rr-empty">Tidak ada mata anggaran aktif yang sesuai filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggles = Array.from(document.querySelectorAll('[data-rr-toggle]'));
    const expanded = new Set(toggles.map(button => button.dataset.rrToggle));

    function refresh() {
        document.querySelectorAll('[data-rr-ancestors]').forEach(function (row) {
            const ancestors = row.dataset.rrAncestors.split(/\s+/).filter(Boolean);
            row.hidden = !ancestors.every(node => expanded.has(node));
        });
        toggles.forEach(button => button.setAttribute('aria-expanded', expanded.has(button.dataset.rrToggle) ? 'true' : 'false'));
    }

    toggles.forEach(function (button) {
        button.addEventListener('click', function () {
            const node = button.dataset.rrToggle;
            expanded.has(node) ? expanded.delete(node) : expanded.add(node);
            refresh();
        });
    });
    document.getElementById('rr-close-all').addEventListener('click', function () { expanded.clear(); refresh(); });
    document.getElementById('rr-open-all').addEventListener('click', function () { toggles.forEach(button => expanded.add(button.dataset.rrToggle)); refresh(); });
});
</script>
@endsection
