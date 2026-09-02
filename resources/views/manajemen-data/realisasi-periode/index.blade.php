@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Data Realisasi Anggaran')

@section('content')

@php
    $rp = fn ($n) => 'Rp '.fmt_rupiah($n);
    $tgl = fn ($iso) => \Illuminate\Support\Carbon::parse($iso)->translatedFormat('d F Y');
    $total = $hasil['total'];

    // Tiap level punya indentasi dan bobotnya sendiri supaya hierarki
    // Program > Kegiatan > Sub Kegiatan > Kode Rekening > Tagging terbaca
    // tanpa perlu kolom tambahan.
    $level = [
        'program' => ['indent' => 0, 'weight' => 700, 'bg' => 'var(--navy-l)', 'color' => 'var(--tegas)'],
        'kegiatan' => ['indent' => 16, 'weight' => 600, 'bg' => 'transparent', 'color' => 'var(--ink)'],
        'sub' => ['indent' => 32, 'weight' => 600, 'bg' => 'transparent', 'color' => 'var(--ink)'],
        'rekening' => ['indent' => 48, 'weight' => 400, 'bg' => 'transparent', 'color' => 'var(--ink)'],
        'tagging' => ['indent' => 64, 'weight' => 400, 'bg' => 'transparent', 'color' => 'var(--mut)'],
    ];
@endphp

<div class="page-head">
    <div>
        <div class="ph-crumb"><a href="{{ route('manajemen-data.index') }}" style="color:inherit;">Manajemen Data</a> / <b>Data Realisasi Anggaran</b></div>
        <div class="ph-title">Data Realisasi Anggaran</div>
    </div>
</div>

<div class="dash-card">
    <h3>Pilih Periode</h3>
    <div class="sub">
        Realisasi dihitung dari transaksi yang <strong>tanggalnya berada di dalam rentang ini</strong> &mdash;
        NPD berstatus Selesai menurut tanggal NPD, SP2D LS menurut tanggal SPM, dan keduanya
        dikurangi pengembalian yang disetujui pada rentang yang sama.
    </div>

    <form method="GET" action="{{ route('manajemen-data.realisasi-periode.index') }}"
          style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label class="fl" style="margin:0;" for="dari">Tanggal Awal</label>
            <input type="date" id="dari" name="dari" value="{{ $dari }}" required>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label class="fl" style="margin:0;" for="sampai">Tanggal Akhir</label>
            <input type="date" id="sampai" name="sampai" value="{{ $sampai }}" required>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn prim">Tampilkan</button>
            <a class="btn" href="{{ route('manajemen-data.realisasi-periode.index') }}">Reset</a>
        </div>
    </form>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;">
        <a class="btn prim" href="{{ route('manajemen-data.realisasi-periode.excel', ['dari' => $dari, 'sampai' => $sampai]) }}">Unduh Excel</a>
        <a class="btn" target="_blank" href="{{ route('manajemen-data.realisasi-periode.pdf', ['dari' => $dari, 'sampai' => $sampai]) }}">Cetak PDF</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
        <div class="kpi-top"><div><div class="kpi-lbl">Pagu Setahun</div></div></div>
        <div class="kpi-val">{{ $rp($total['pagu']) }}</div>
        <div class="kpi-note">Seluruh mata anggaran aktif</div>
    </div>
    <div class="kpi" style="--kc:#0f6e56;--kbg:#0f6e5614;">
        <div class="kpi-top"><div><div class="kpi-lbl">Realisasi NPD</div></div></div>
        <div class="kpi-val">{{ $rp($total['realisasi_npd']) }}</div>
        <div class="kpi-note">NPD Selesai pada periode ini</div>
    </div>
    <div class="kpi" style="--kc:#7c3aed;--kbg:#7c3aed14;">
        <div class="kpi-top"><div><div class="kpi-lbl">Realisasi LS</div></div></div>
        <div class="kpi-val">{{ $rp($total['realisasi_ls']) }}</div>
        <div class="kpi-note">SP2D LS pada periode ini</div>
    </div>
    <div class="kpi" style="--kc:#b07d1d;--kbg:#b07d1d14;">
        <div class="kpi-top"><div><div class="kpi-lbl">Realisasi Aktual</div></div></div>
        <div class="kpi-val">{{ $rp($total['realisasi_aktual']) }}</div>
        <div class="kpi-note">{{ number_format($total['persentase_realisasi'], 2, ',', '.') }}% dari pagu setahun</div>
    </div>
</div>

<div class="dash-card">
    <h3>Rincian {{ $tgl($dari) }} &mdash; {{ $tgl($sampai) }}</h3>
    <div class="sub">
        Pagu adalah nilai <strong>setahun</strong> dan tidak mengikuti rentang tanggal &mdash; pagu memang
        tidak punya dimensi waktu. Persentasenya berarti berapa persen pagu setahun yang terserap
        pada periode yang dipilih.
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi tbl-fixed">
            <colgroup>
                <col style="width:34%;"><col style="width:13%;"><col style="width:13%;">
                <col style="width:13%;"><col style="width:14%;"><col style="width:13%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Program / Kegiatan / Sub Kegiatan / Kode Rekening / Tagging</th>
                    <th class="num">Pagu Setahun</th>
                    <th class="num">Realisasi NPD</th>
                    <th class="num">Realisasi LS</th>
                    <th class="num">Realisasi Aktual</th>
                    <th class="num">% thd Pagu</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hasil['tree'] as $program)
                    @include('manajemen-data.realisasi-periode._baris', [
                        'gaya' => $level['program'], 'nama' => $program['nama'],
                        'uraian' => null, 'angka' => $program['angka'],
                    ])

                    @foreach ($program['kegiatan'] as $kegiatan)
                        @include('manajemen-data.realisasi-periode._baris', [
                            'gaya' => $level['kegiatan'], 'nama' => $kegiatan['nama'],
                            'uraian' => null, 'angka' => $kegiatan['angka'],
                        ])

                        @foreach ($kegiatan['sub'] as $sub)
                            @include('manajemen-data.realisasi-periode._baris', [
                                'gaya' => $level['sub'], 'nama' => $sub['nama'],
                                'uraian' => null, 'angka' => $sub['angka'],
                            ])

                            @foreach ($sub['rekening'] as $rekening)
                                @include('manajemen-data.realisasi-periode._baris', [
                                    'gaya' => $level['rekening'], 'nama' => $rekening['nama'],
                                    'uraian' => $rekening['uraian'], 'angka' => $rekening['angka'],
                                ])

                                @foreach ($rekening['tagging'] as $tagging)
                                    @include('manajemen-data.realisasi-periode._baris', [
                                        'gaya' => $level['tagging'], 'nama' => $tagging['nama'],
                                        'uraian' => null, 'angka' => $tagging['angka'],
                                    ])
                                @endforeach
                            @endforeach
                        @endforeach
                    @endforeach
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--mut);padding:20px;">
                            Belum ada mata anggaran aktif untuk ditampilkan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background:var(--navy-l);font-weight:700;">
                    <td style="color:var(--tegas);">TOTAL</td>
                    <td class="num">{{ $rp($total['pagu']) }}</td>
                    <td class="num">{{ $rp($total['realisasi_npd']) }}</td>
                    <td class="num">{{ $rp($total['realisasi_ls']) }}</td>
                    <td class="num">{{ $rp($total['realisasi_aktual']) }}</td>
                    <td class="num">{{ number_format($total['persentase_realisasi'], 2, ',', '.') }}%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
