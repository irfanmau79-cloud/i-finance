@extends('layouts.app')

@section('activeNav', 'simulasi-pergeseran')
@section('title', 'Simulasi: '.$simulasiAnggaran->nama)

@php
    $rupiah = fn (float $n) => 'Rp '.number_format($n, 2, ',', '.');
    $totalEksisting = (float) $simulasiAnggaran->total_pagu_eksisting;
    $totalSimulasi = (float) $simulasiAnggaran->total_pagu_simulasi;
    $totalSelisih = (float) $simulasiAnggaran->total_selisih;
@endphp

@section('content')
<style>
    .sim-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:16px 0}
    @media(max-width:900px){.sim-kpis{grid-template-columns:1fr}}
    /* Nilai pada baris Tagging adalah SATU-SATUNYA yang diisi tangan. Yang
       ditonjolkan cukup kotak isiannya - baris, sel, dan judul kolomnya
       dibiarkan sama seperti yang lain supaya tabelnya tetap tenang. */
    .sim-rek-input{
        width:100%;max-width:190px;text-align:right;box-sizing:border-box;font-variant-numeric:tabular-nums;
        border:1.5px solid var(--gold);border-radius:9px;padding:8px 11px;background:var(--surface);
        font-weight:700;color:var(--tegas);transition:border-color .15s,box-shadow .15s;
    }
    .sim-rek-input:hover{border-color:var(--gold-d)}
    .sim-rek-input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.13)}

    table.pivot .row-lvl4{background:var(--surface);color:var(--mut);}
    .sim-summary{border:1px solid var(--line);border-radius:14px;margin-bottom:16px;overflow:hidden;background:var(--surface)}
    .sim-summary-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 18px;background:linear-gradient(135deg,var(--surface-2),var(--surface-2));border-bottom:1px solid var(--line)}
    .sim-summary-title{display:flex;align-items:center;gap:10px;font-weight:800;color:var(--tegas)}
    .sim-summary-icon{width:34px;height:34px;border-radius:10px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center}
    .sim-summary-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2}
    .sim-summary-count{font-size:11px;font-weight:700;color:var(--tegas);background:var(--surface);border:1px solid var(--line);border-radius:20px;padding:4px 10px}
    .sim-summary-body{padding:14px}
    .sim-summary-empty{text-align:center;color:var(--mut);padding:24px 12px}
    .sim-sum-node{border-left:2px solid var(--line);margin-left:10px;padding-left:12px}
    .sim-sum-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:8px 10px;border-radius:8px}
    .sim-sum-row:hover{background:var(--surface-2)}
    .sim-sum-label{min-width:0;overflow-wrap:anywhere}
    .sim-sum-level{display:inline-block;margin-right:7px;color:var(--mut);font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}
    .sim-sum-name{font-size:12.5px;color:var(--ink);font-weight:600}
    .sim-sum-value{text-align:right;white-space:nowrap;font-size:12px;font-weight:800;font-variant-numeric:tabular-nums}
    .sim-sum-program>.sim-sum-row{background:var(--surface-3)}.sim-sum-program>.sim-sum-row .sim-sum-name{color:var(--tegas);font-size:13.5px;font-weight:800}
    .sim-sum-kegiatan>.sim-sum-row .sim-sum-name{color:var(--tegas);font-weight:700}
    .sim-sum-rekening>.sim-sum-row{background:var(--surface-2)}
    .sim-sum-tagging{margin-left:22px;border-left:2px solid var(--line)}
    .sim-sum-tagging .sim-sum-row{grid-template-columns:minmax(0,1fr) minmax(230px,auto)}
    .sim-sum-flow{display:flex;align-items:center;justify-content:flex-end;gap:7px;color:var(--mut);font-size:11px;font-weight:500}
    .sim-sum-flow b{color:var(--ink);font-variant-numeric:tabular-nums}.sim-sum-flow .arrow{color:var(--mut)}
    @media(max-width:720px){.sim-sum-tagging .sim-sum-row{grid-template-columns:1fr}.sim-sum-flow{justify-content:flex-start;flex-wrap:wrap}.sim-summary-head{align-items:flex-start}}

    .rr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px}
    .rr-actions{display:flex;gap:8px;flex-wrap:wrap}.rr-actions .btn{padding:8px 14px}
    .rr-wrap{overflow:auto;border:1px solid var(--line);border-radius:11px;margin-top:16px}
    table.rr-tree{min-width:1060px;margin:0;table-layout:fixed;width:100%;}
    table.rr-tree th{white-space:nowrap}
    table.rr-tree col.c-uraian{width:48%}
    table.rr-tree col.c-eksisting{width:13%}
    table.rr-tree col.c-realisasi{width:13%}
    table.rr-tree col.c-simulasi{width:16%}
    table.rr-tree col.c-selisih{width:10%}
    table.rr-tree .rr-label{overflow-wrap:anywhere;}
    table.rr-tree .rr-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
    .rr-toggle{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:0;background:transparent;color:var(--tegas);cursor:pointer;border-radius:5px;flex:0 0 22px;}
    .rr-toggle:hover{background:rgba(21,49,74,.09)}
    .rr-toggle svg{width:14px;height:14px;transition:transform .15s;stroke:currentColor;fill:none;stroke-width:2.2}
    .rr-toggle[aria-expanded="true"] svg{transform:rotate(90deg)}
    .rr-empty{text-align:center;padding:34px 16px;color:var(--mut)}
    table.pivot .ind3{padding-left:70px;}
    table.pivot .ind4{padding-left:94px;color:var(--mut);}
    table.pivot .row-lvl3{background:var(--surface);cursor:pointer;}
    table.pivot .row-lvl3:hover{background:var(--surface-2);}
</style>

<div class="dash-card wf-card">
    <div class="rr-head">
        <div>
            <h3 style="margin:0;">{{ $simulasiAnggaran->nama }}</h3>
            <div class="sub">Dibuat oleh {{ $simulasiAnggaran->user->nama ?? '-' }} pada {{ $simulasiAnggaran->created_at->format('d-m-Y H:i') }}</div>
        </div>
        <div class="rr-actions">
            <a class="btn" href="{{ route('simulasi-anggaran.index') }}">&larr; Daftar Simulasi</a>
            <a class="btn" href="{{ route('simulasi-anggaran.export-excel', $simulasiAnggaran) }}">Export Excel</a>
            <a class="btn" href="{{ route('simulasi-anggaran.export-pdf', $simulasiAnggaran) }}" target="_blank">Export PDF</a>
        </div>
    </div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Gagal menyimpan simulasi:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('simulasi-anggaran.update', $simulasiAnggaran) }}" id="sim-form">
        @csrf
        @method('PUT')

        <div class="form-grid" style="margin-top:14px;">
            <div class="fg">
                <label class="fl">Nama Simulasi</label>
                <input type="text" name="nama" value="{{ old('nama', $simulasiAnggaran->nama) }}" required maxlength="150">
            </div>
            <div class="fg">
                <label class="fl">Keterangan</label>
                <input type="text" name="keterangan" value="{{ old('keterangan', $simulasiAnggaran->keterangan) }}" maxlength="1000" placeholder="Opsional">
            </div>
        </div>

        <div class="sim-kpis">
            <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
                <div class="kpi-top">
                    <div class="kpi-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg></div>
                    <div class="kpi-lbl">Pagu Anggaran</div>
                </div>
                <div class="kpi-val" id="sim-total-eksisting">{{ $rupiah($totalEksisting) }}</div>
                <div class="kpi-note">Eksisting saat ini</div>
            </div>
            <div class="kpi" style="--kc:#0f6e56;--kbg:#0f6e5614;">
                <div class="kpi-top">
                    <div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.1-4-4L3 15.5"/></svg></div>
                    <div class="kpi-lbl">Pagu Anggaran (Simulasi)</div>
                </div>
                <div class="kpi-val" id="sim-total-simulasi">{{ $rupiah($totalSimulasi) }}</div>
                <div class="kpi-note">Hasil percobaan di bawah</div>
            </div>
            <div class="kpi" id="sim-kpi-selisih" style="--kc:{{ $totalSelisih > 0 ? '#0f6e56' : ($totalSelisih < 0 ? '#b3261e' : '#64748b') }};--kbg:{{ $totalSelisih > 0 ? '#0f6e5614' : ($totalSelisih < 0 ? '#b3261e14' : '#64748b14') }};">
                <div class="kpi-top">
                    <div class="kpi-ic"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg></div>
                    <div class="kpi-lbl">Selisih</div>
                </div>
                <div class="kpi-val" id="sim-total-selisih">{{ $totalSelisih > 0 ? '+' : '' }}{{ $rupiah($totalSelisih) }}</div>
                <div class="kpi-note">Simulasi &minus; Eksisting</div>
            </div>
        </div>

        <div class="sim-summary">
            <div class="sim-summary-head">
                <div class="sim-summary-title">
                    <span class="sim-summary-icon"><svg viewBox="0 0 24 24"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19H2"/></svg></span>
                    <span>Ringkasan Perubahan</span>
                </div>
                <span class="sim-summary-count" id="sim-summary-count">0 tagging berubah</span>
            </div>
            <div class="sim-summary-body" id="sim-summary-list">
                <div class="sim-summary-empty">Belum ada perubahan pagu pada simulasi ini.</div>
            </div>
        </div>

        <div class="rr-actions" style="margin-bottom:10px;">
            <button type="button" class="btn" id="sim-close-all">Tutup Semua</button>
            <button type="button" class="btn" id="sim-open-all">Buka Semua</button>
        </div>

        <div class="rr-wrap">
            <table class="realisasi pivot rr-tree">
                <colgroup>
                    <col class="c-uraian">
                    <col class="c-eksisting">
                    <col class="c-realisasi">
                    <col class="c-simulasi">
                    <col class="c-selisih">
                </colgroup>
                <thead>
                    <tr>
                        <th class="rr-label">Uraian</th>
                        <th class="rr-num">Anggaran (Eksisting)</th>
                        <th class="rr-num">Realisasi</th>
                        <th class="rr-num">Anggaran (Simulasi)</th>
                        <th class="rr-num">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tree as $progIndex => $prog)
                        @php($progNode = 'sim-p'.$progIndex)
                        <tr class="row-lvl0 {{ $progIndex % 2 === 0 ? 'lvl0-gelap' : 'lvl0-terang' }}" data-rr-node="{{ $progNode }}">
                            <td class="rr-label">
                                <div class="uraian">
                                    <button type="button" class="rr-toggle" data-rr-toggle="{{ $progNode }}" aria-expanded="false" aria-label="Tutup atau buka Program"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
                                    <span>{{ $prog['nama'] }}</span>
                                </div>
                            </td>
                            <td class="rr-num">{{ $rupiah($prog['eksisting']) }}</td>
                            <td class="rr-num">{{ $rupiah($prog['realisasi']) }}</td>
                            <td class="rr-num" id="sim-agg-simulasi-p-{{ $progIndex }}">{{ $rupiah($prog['simulasi']) }}</td>
                            <td class="rr-num" id="sim-agg-selisih-p-{{ $progIndex }}">{{ $prog['selisih'] > 0 ? '+' : '' }}{{ $rupiah($prog['selisih']) }}</td>
                        </tr>
                        @foreach ($prog['kegiatan'] as $kegIndex => $keg)
                            @php($kegNode = $progNode.'-k'.$kegIndex)
                            @php($kegKey = $progIndex.'-'.$kegIndex)
                            <tr class="row-lvl1" data-rr-node="{{ $kegNode }}" data-rr-ancestors="{{ $progNode }}">
                                <td class="rr-label ind1">
                                    <div class="uraian">
                                        <button type="button" class="rr-toggle" data-rr-toggle="{{ $kegNode }}" aria-expanded="false" aria-label="Tutup atau buka Kegiatan"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
                                        <span>{{ $keg['nama'] }}</span>
                                    </div>
                                </td>
                                <td class="rr-num">{{ $rupiah($keg['eksisting']) }}</td>
                                <td class="rr-num">{{ $rupiah($keg['realisasi']) }}</td>
                                <td class="rr-num" id="sim-agg-simulasi-k-{{ $kegKey }}">{{ $rupiah($keg['simulasi']) }}</td>
                                <td class="rr-num" id="sim-agg-selisih-k-{{ $kegKey }}">{{ $keg['selisih'] > 0 ? '+' : '' }}{{ $rupiah($keg['selisih']) }}</td>
                            </tr>
                            @foreach ($keg['subKegiatan'] as $subIndex => $sub)
                                @php($subNode = $kegNode.'-s'.$subIndex)
                                @php($subKey = $kegKey.'-'.$subIndex)
                                <tr class="row-lvl2" data-rr-node="{{ $subNode }}" data-rr-ancestors="{{ $progNode }} {{ $kegNode }}">
                                    <td class="rr-label ind2">
                                        <div class="uraian">
                                            <button type="button" class="rr-toggle" data-rr-toggle="{{ $subNode }}" aria-expanded="false" aria-label="Tutup atau buka Sub Kegiatan"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
                                            <span>{{ $sub['nama'] }}</span>
                                        </div>
                                    </td>
                                    <td class="rr-num">{{ $rupiah($sub['eksisting']) }}</td>
                                    <td class="rr-num">{{ $rupiah($sub['realisasi']) }}</td>
                                    <td class="rr-num" id="sim-agg-simulasi-s-{{ $subKey }}">{{ $rupiah($sub['simulasi']) }}</td>
                                    <td class="rr-num" id="sim-agg-selisih-s-{{ $subKey }}">{{ $sub['selisih'] > 0 ? '+' : '' }}{{ $rupiah($sub['selisih']) }}</td>
                                </tr>
                                @foreach ($sub['rekening'] as $rekeningIndex => $rekening)
                                    @php($rekeningNode = $subNode.'-r'.$rekeningIndex)
                                    @php($rekKey = $subKey.'-'.$rekeningIndex)
                                    <tr class="row-lvl3" data-rr-node="{{ $rekeningNode }}" data-rr-ancestors="{{ $progNode }} {{ $kegNode }} {{ $subNode }}">
                                        <td class="rr-label ind3">
                                            <div class="uraian">
                                                <button type="button" class="rr-toggle" data-rr-toggle="{{ $rekeningNode }}" aria-expanded="false" aria-label="Tutup atau buka Kode Rekening"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
                                                <span><span class="kode-chip">{{ $rekening['kode'] }}</span> {{ $rekening['uraian'] }}</span>
                                            </div>
                                        </td>
                                        <td class="rr-num">{{ $rupiah($rekening['eksisting']) }}</td>
                                        <td class="rr-num">{{ $rupiah($rekening['realisasi']) }}</td>
                                        <td class="rr-num" id="sim-agg-simulasi-r-{{ $rekKey }}">{{ $rupiah($rekening['simulasi']) }}</td>
                                        <td class="rr-num" id="sim-agg-selisih-r-{{ $rekKey }}">{{ $rekening['selisih'] > 0 ? '+' : '' }}{{ $rupiah($rekening['selisih']) }}</td>
                                    </tr>
                                    @foreach ($rekening['baris'] as $row)
                                        @php($rawSimulasi = old('rows.'.$row->id, (float) $row->pagu_simulasi))
                                        <tr class="row-lvl4" data-rr-ancestors="{{ $progNode }} {{ $kegNode }} {{ $subNode }} {{ $rekeningNode }}">
                                            <td class="rr-label ind4"><div class="uraian"><span class="spacer"></span><span>{{ $row->tagging_nama ?? 'Tanpa Tagging' }}</span></div></td>
                                            <td class="rr-num">{{ $rupiah((float) $row->pagu_eksisting) }}</td>
                                            <td class="rr-num">{{ $rupiah((float) $row->realisasi) }}</td>
                                            <td class="rr-num">
                                                <input type="text" inputmode="decimal" autocomplete="off" class="sim-rek-input"
                                                    data-row-id="{{ $row->id }}"
                                                    data-eksisting="{{ (float) $row->pagu_eksisting }}"
                                                    data-prog="{{ $progIndex }}"
                                                    data-keg="{{ $kegKey }}"
                                                    data-sub="{{ $subKey }}"
                                                    data-rek="{{ $rekKey }}"
                                                    data-program-label="{{ $prog['nama'] }}"
                                                    data-kegiatan-label="{{ $keg['nama'] }}"
                                                    data-sub-label="{{ $sub['nama'] }}"
                                                    data-rekening-label="{{ $rekening['kode'] }} {{ $rekening['uraian'] }}"
                                                    data-tagging-label="{{ $row->tagging_nama ?? 'Tanpa Tagging' }}"
                                                    value="{{ number_format((float) $rawSimulasi, 2, ',', '.') }}">
                                                <input type="hidden" name="rows[{{ $row->id }}]" data-raw-for="{{ $row->id }}" value="{{ $rawSimulasi }}">
                                            </td>
                                            <td class="rr-num" id="sim-sel-{{ $row->id }}">{{ (float) $row->selisih > 0 ? '+' : '' }}{{ $rupiah((float) $row->selisih) }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endforeach
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="rr-empty">Simulasi ini belum memiliki baris mata anggaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (boleh_ubah())
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <button type="submit" class="btn prim">Simpan Simulasi</button>
        </div>
        @else
        <div class="sub" style="margin-top:16px;">Anda membuka simulasi ini sebagai pemantau &mdash; perubahan tidak dapat disimpan.</div>
        @endif
    </form>
</div>

<script>
(function () {
    // ---- Buka/tutup tree: mulai dari tertutup semua, mulai Program ----
    const expanded = new Set();

    function refreshTree() {
        document.querySelectorAll('[data-rr-ancestors]').forEach(function (row) {
            const ancestors = row.dataset.rrAncestors.split(/\s+/).filter(Boolean);
            row.hidden = !ancestors.every(node => expanded.has(node));
        });
        document.querySelectorAll('[data-rr-toggle]').forEach(btn => btn.setAttribute('aria-expanded', expanded.has(btn.dataset.rrToggle) ? 'true' : 'false'));
    }

    document.querySelectorAll('tr[data-rr-node]').forEach(function (row) {
        const node = row.dataset.rrNode;
        row.addEventListener('click', function (e) {
            if (e.target.closest('input')) return;
            expanded.has(node) ? expanded.delete(node) : expanded.add(node);
            refreshTree();
        });
    });

    document.getElementById('sim-close-all').addEventListener('click', function () { expanded.clear(); refreshTree(); });
    document.getElementById('sim-open-all').addEventListener('click', function () { document.querySelectorAll('[data-rr-toggle]').forEach(btn => expanded.add(btn.dataset.rrToggle)); refreshTree(); });

    refreshTree();

    // ---- Format ribuan otomatis pada input nominal (mis. 1.393.109,90) ----
    function formatDisplay(raw) {
        let cleaned = String(raw).replace(/[^0-9,]/g, '');
        const firstComma = cleaned.indexOf(',');
        if (firstComma !== -1) {
            cleaned = cleaned.slice(0, firstComma + 1) + cleaned.slice(firstComma + 1).replace(/,/g, '');
        }
        let [intPart, decPart] = cleaned.split(',');
        intPart = (intPart || '').replace(/^0+(?=\d)/, '');
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        if (decPart !== undefined) {
            decPart = decPart.slice(0, 2);
            return intPart + ',' + decPart;
        }
        return intPart;
    }

    function parseDisplay(display) {
        if (!display) return 0;
        const normalized = String(display).replace(/\./g, '').replace(',', '.');
        return parseFloat(normalized) || 0;
    }

    function reformatInput(input) {
        const oldValue = input.value;
        const oldPos = input.selectionStart ?? oldValue.length;
        const digitsBeforeCaret = oldValue.slice(0, oldPos).replace(/[^0-9,]/g, '').length;
        const newValue = formatDisplay(oldValue);
        input.value = newValue;
        let count = 0, pos = newValue.length;
        for (let i = 0; i < newValue.length; i++) {
            if (/[0-9,]/.test(newValue[i])) count++;
            if (count >= digitsBeforeCaret) { pos = i + 1; break; }
        }
        if (digitsBeforeCaret === 0) pos = 0;
        input.setSelectionRange(pos, pos);
    }

    // ---- Hitung ulang total & ringkasan setiap ada input berubah ----
    const TOTAL_EKSISTING = {{ $totalEksisting }};

    function formatRupiah(n) {
        const neg = n < 0;
        const abs = Math.abs(n).toFixed(2);
        const parts = abs.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return (neg ? '-' : '') + 'Rp ' + parts[0] + ',' + parts[1];
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function warnaSelisih(n) {
        if (n > 0.004) return 'var(--ok)';
        if (n < -0.004) return 'var(--err)';
        return 'inherit';
    }

    function recalc() {
        const progTotals = {};
        const kegTotals = {};
        const subTotals = {};
        const rekTotals = {};
        let grandSimulasi = 0;
        const changed = [];

        document.querySelectorAll('[data-row-id]').forEach(function (input) {
            const eksisting = parseFloat(input.dataset.eksisting) || 0;
            const simulasi = parseDisplay(input.value);
            const progIdx = input.dataset.prog;
            const kegKey = input.dataset.keg;
            const subKey = input.dataset.sub;
            const rekKey = input.dataset.rek;

            progTotals[progIdx] = progTotals[progIdx] || { eksisting: 0, simulasi: 0 };
            progTotals[progIdx].eksisting += eksisting;
            progTotals[progIdx].simulasi += simulasi;

            kegTotals[kegKey] = kegTotals[kegKey] || { eksisting: 0, simulasi: 0 };
            kegTotals[kegKey].eksisting += eksisting;
            kegTotals[kegKey].simulasi += simulasi;

            subTotals[subKey] = subTotals[subKey] || { eksisting: 0, simulasi: 0 };
            subTotals[subKey].eksisting += eksisting;
            subTotals[subKey].simulasi += simulasi;

            rekTotals[rekKey] = rekTotals[rekKey] || { eksisting: 0, simulasi: 0 };
            rekTotals[rekKey].eksisting += eksisting;
            rekTotals[rekKey].simulasi += simulasi;

            grandSimulasi += simulasi;

            const selisih = simulasi - eksisting;
            const selEl = document.getElementById('sim-sel-' + input.dataset.rowId);
            if (selEl) {
                selEl.textContent = (selisih > 0 ? '+' : '') + formatRupiah(selisih);
                selEl.style.color = warnaSelisih(selisih);
            }

            const rawInput = document.querySelector('[data-raw-for="' + input.dataset.rowId + '"]');
            if (rawInput) rawInput.value = simulasi.toFixed(2);

            if (Math.abs(selisih) > 0.004) {
                changed.push({
                    progKey: progIdx, kegKey: kegKey, subKey: subKey, rekKey: rekKey,
                    program: input.dataset.programLabel,
                    kegiatan: input.dataset.kegiatanLabel,
                    sub: input.dataset.subLabel,
                    rekening: input.dataset.rekeningLabel,
                    tagging: input.dataset.taggingLabel,
                    eksisting: eksisting, simulasi: simulasi, selisih: selisih
                });
            }
        });

        function terapkanAgregat(totals, prefix) {
            Object.keys(totals).forEach(function (key) {
                const t = totals[key];
                const selisih = t.simulasi - t.eksisting;
                const simEl = document.getElementById('sim-agg-simulasi-' + prefix + '-' + key);
                const selEl = document.getElementById('sim-agg-selisih-' + prefix + '-' + key);
                if (simEl) simEl.textContent = formatRupiah(t.simulasi);
                if (selEl) { selEl.textContent = (selisih > 0 ? '+' : '') + formatRupiah(selisih); selEl.style.color = warnaSelisih(selisih); }
            });
        }

        terapkanAgregat(progTotals, 'p');
        terapkanAgregat(kegTotals, 'k');
        terapkanAgregat(subTotals, 's');
        terapkanAgregat(rekTotals, 'r');

        document.getElementById('sim-total-simulasi').textContent = formatRupiah(grandSimulasi);
        const grandSelisih = grandSimulasi - TOTAL_EKSISTING;
        const selisihCard = document.getElementById('sim-total-selisih');
        selisihCard.textContent = (grandSelisih > 0 ? '+' : '') + formatRupiah(grandSelisih);
        const kpiSelisih = document.getElementById('sim-kpi-selisih');
        kpiSelisih.style.setProperty('--kc', grandSelisih > 0 ? '#0f6e56' : (grandSelisih < 0 ? '#b3261e' : '#64748b'));
        kpiSelisih.style.setProperty('--kbg', grandSelisih > 0 ? '#0f6e5614' : (grandSelisih < 0 ? '#b3261e14' : '#64748b14'));

        const listEl = document.getElementById('sim-summary-list');
        const countEl = document.getElementById('sim-summary-count');
        countEl.textContent = changed.length + ' tagging berubah';

        if (changed.length === 0) {
            listEl.innerHTML = '<div class="sim-summary-empty">Belum ada perubahan pagu pada simulasi ini.</div>';
        } else {
            const tree = {};
            changed.forEach(function (c) {
                const prog = tree[c.progKey] ||= { label:c.program, total:0, children:{} };
                const keg = prog.children[c.kegKey] ||= { label:c.kegiatan, total:0, children:{} };
                const sub = keg.children[c.subKey] ||= { label:c.sub, total:0, children:{} };
                const rek = sub.children[c.rekKey] ||= { label:c.rekening, total:0, children:[] };
                prog.total += c.selisih;
                keg.total += c.selisih;
                sub.total += c.selisih;
                rek.total += c.selisih;
                rek.children.push(c);
            });

            function nilaiPerubahan(total) {
                return '<span class="sim-sum-value" style="color:' + warnaSelisih(total) + '">'
                    + (total > 0 ? '+' : '') + formatRupiah(total) + '</span>';
            }
            function barisLevel(level, label, total) {
                return '<div class="sim-sum-row"><div class="sim-sum-label"><span class="sim-sum-level">'
                    + level + '</span><span class="sim-sum-name">' + escapeHtml(label) + '</span></div>'
                    + nilaiPerubahan(total) + '</div>';
            }
            function taggingHtml(c) {
                return '<div class="sim-sum-tagging"><div class="sim-sum-row">'
                    + '<div class="sim-sum-label"><span class="sim-sum-level">Tagging</span><span class="sim-sum-name">' + escapeHtml(c.tagging) + '</span></div>'
                    + '<div class="sim-sum-flow"><b>' + formatRupiah(c.eksisting) + '</b><span class="arrow">&rarr;</span><b>' + formatRupiah(c.simulasi) + '</b>'
                    + nilaiPerubahan(c.selisih) + '</div></div></div>';
            }

            listEl.innerHTML = Object.values(tree).map(function (prog) {
                return '<div class="sim-sum-node sim-sum-program">' + barisLevel('Program', prog.label, prog.total)
                    + Object.values(prog.children).map(function (keg) {
                        return '<div class="sim-sum-node sim-sum-kegiatan">' + barisLevel('Kegiatan', keg.label, keg.total)
                            + Object.values(keg.children).map(function (sub) {
                                return '<div class="sim-sum-node sim-sum-sub">' + barisLevel('Sub Kegiatan', sub.label, sub.total)
                                    + Object.values(sub.children).map(function (rek) {
                                        return '<div class="sim-sum-node sim-sum-rekening">' + barisLevel('Kode Rekening', rek.label, rek.total)
                                            + rek.children.map(taggingHtml).join('') + '</div>';
                                    }).join('') + '</div>';
                            }).join('') + '</div>';
                    }).join('') + '</div>';
            }).join('');
        }
    }

    document.querySelectorAll('[data-row-id]').forEach(function (input) {
        input.addEventListener('input', function () {
            reformatInput(input);
            recalc();
        });
    });

    recalc();
})();
</script>
@endsection
