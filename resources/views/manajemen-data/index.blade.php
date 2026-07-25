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

<div class="profil-sec-title">Import Data</div>
<div class="sub" style="margin-top:-8px;margin-bottom:16px;">Upload data master dan transaksi dari file Excel (.xlsx).</div>
<div class="dash-grid">
    @if(auth()->user()->isSuperadmin())
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div><h3 style="margin-bottom:0;">Import NPD Historis</h3><div class="sub" style="margin-bottom:0;">Upload, preview, validate, and import historical NPD data.</div></div>
        <a href="{{ route('manajemen-data.import.npd-historis.create') }}" class="btn prim" style="white-space:nowrap;">Import Excel</a>
    </div>
    @endif
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">Import Pagu / Master Anggaran</h3>
            <div class="sub" style="margin-bottom:0;">Upload Excel, preview baru/update/ditolak, lalu konfirmasi simpan.</div>
        </div>
        <a href="{{ route('manajemen-data.import.master-anggaran.create') }}" class="btn prim" style="white-space:nowrap;">Import Excel</a>
    </div>
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">Import SPM UP/GU</h3>
            <div class="sub" style="margin-bottom:0;">Tidak memerlukan mata anggaran.</div>
        </div>
        <a href="{{ route('manajemen-data.import.spm.create', 'spm-up-gu') }}" class="btn prim" style="white-space:nowrap;">Import Excel</a>
    </div>
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">Import SPM LS</h3>
            <div class="sub" style="margin-bottom:0;">Wajib cocok ke mata anggaran aktif.</div>
        </div>
        <a href="{{ route('manajemen-data.import.spm.create', 'spm-ls') }}" class="btn prim" style="white-space:nowrap;">Import Excel</a>
    </div>
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">Import RAK Bulanan</h3>
            <div class="sub" style="margin-bottom:0;">Wajib cocok ke mata anggaran aktif, nilai bulanan (bukan kumulatif).</div>
        </div>
        <a href="{{ route('manajemen-data.import.rak-bulanan.create') }}" class="btn prim" style="white-space:nowrap;">Import Excel</a>
    </div>
    @if(auth()->user()->isSuperadmin())
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">Import Data Tunjangan Keluarga</h3>
            <div class="sub" style="margin-bottom:0;">Import awal (dry-run): preview lalu konfirmasi. NIP wajib sudah terdaftar di master Pegawai.</div>
        </div>
        <a href="{{ route('tunjangan.import.create') }}" class="btn prim" style="white-space:nowrap;">Import Excel</a>
    </div>
    @endif
</div>

<div class="profil-divider"></div>

<div class="profil-sec-title">Export Data</div>
<div class="dash-grid">
    @foreach ($exports as $key => $meta)
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">{{ $meta['label'] }}</h3>
        </div>
        <a href="{{ $key === 'rak-bulanan' ? route('manajemen-data.export', ['jenis' => $key, 'tahun' => $tahunSekarang]) : route('manajemen-data.export', $key) }}" class="btn prim" style="white-space:nowrap;">Unduh Excel</a>
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
