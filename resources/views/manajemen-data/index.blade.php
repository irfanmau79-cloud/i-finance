@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Manajemen Data')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Manajemen Data</b></div>
        <div class="ph-title">Manajemen Data</div>
    </div>
</div>

<div class="sub" style="margin-bottom:16px;">Import dan export data master/transaksi lewat file Excel (.xlsx). Setiap import ditampilkan sebagai preview dulu (baru/update/ditolak) sebelum dikonfirmasi simpan; setiap unduhan/import tercatat di Log Aktivitas.</div>

<div class="dash-grid">
    @foreach ($tipeData as $key => $meta)
        @php
            $butuhSuperadmin = in_array($key, ['npd', 'tunjangan-keluarga'], true);
            $bolehImport = ! $butuhSuperadmin || auth()->user()->isSuperadmin();
            $exportHref = $meta['export_jenis'] === 'rak-bulanan'
                ? route('manajemen-data.export', ['jenis' => 'rak-bulanan', 'tahun' => $tahunSekarang])
                : route('manajemen-data.export', $meta['export_jenis']);
        @endphp
        <div class="dash-card">
            <h3 style="margin-bottom:10px;">{{ $meta['label'] }}</h3>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @if ($meta['import_template'] && $bolehImport)
                    <a href="{{ route($meta['import_template'][0], $meta['import_template'][1]) }}" class="btn" style="white-space:nowrap;">Unduh Template</a>
                @endif
                @if ($bolehImport)
                    <a href="{{ route($meta['import_create'][0], $meta['import_create'][1]) }}" class="btn" style="white-space:nowrap;">Import Excel</a>
                @endif
                <a href="{{ $exportHref }}" class="btn prim" style="white-space:nowrap;">Unduh Excel</a>
            </div>
            @if (! $meta['import_template'] && $bolehImport)
                <div class="sub" style="margin-top:8px;margin-bottom:0;">Unduh Excel di atas juga berfungsi sebagai template import (baris Sub Kegiatan/Kode Rekening terisi, kolom bulan kosong siap diisi).</div>
            @endif
            @if ($butuhSuperadmin && ! $bolehImport)
                <div class="sub" style="margin-top:8px;margin-bottom:0;">Import khusus Superadmin.</div>
            @endif
        </div>
    @endforeach
</div>

<div class="dash-card" style="margin-top:18px;">
    <div class="sub" style="margin-bottom:0;">
        Catatan: export RAK Bulanan mengunduh tahun berjalan ({{ $tahunSekarang }}). Untuk tahun lain, ubah parameter <code>tahun</code> di URL unduhan.
        Setiap unduhan tercatat di Log Aktivitas (jenis, waktu, pengguna, dan jumlah baris).
    </div>
</div>
@endsection
