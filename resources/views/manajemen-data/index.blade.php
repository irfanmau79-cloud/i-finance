@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Manajemen Data')

@section('content')
<div class="dash-card">
    <h3>Manajemen Data</h3>
    <div class="sub">Unduh data master dan transaksi dalam format Excel (.xlsx). Setiap file diawali baris header yang stabil.</div>
</div>

<div class="dash-grid" style="margin-top:18px;">
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">Import Pagu / Master Anggaran</h3>
            <div class="sub" style="margin-bottom:0;">Upload Excel, preview baru/update/ditolak, lalu konfirmasi simpan.</div>
        </div>
        <a href="{{ route('manajemen-data.import.master-anggaran.create') }}" class="btn prim" style="white-space:nowrap;">Import Excel</a>
    </div>
    @foreach ($exports as $key => $meta)
    <div class="dash-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="margin-bottom:0;">{{ $meta['label'] }}</h3>
        </div>
        <a href="{{ route('manajemen-data.export', $key) }}" class="btn prim" style="white-space:nowrap;">Unduh Excel</a>
    </div>
    @endforeach
</div>

<div class="dash-card" style="margin-top:18px;">
    <div class="sub" style="margin-bottom:0;">
        Catatan: export RAK Bulanan belum tersedia karena tabel sumbernya belum dibuat di sistem ini.
        Setiap unduhan tercatat di Log Aktivitas (jenis, waktu, pengguna, dan jumlah baris).
    </div>
</div>
@endsection
