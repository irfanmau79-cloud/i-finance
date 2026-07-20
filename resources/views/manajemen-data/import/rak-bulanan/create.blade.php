@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import RAK Bulanan')

@section('content')
<div class="dash-card">
    <h3>Import RAK Bulanan</h3>
    <div class="sub">
        Upload file Excel (.xlsx/.xls) dengan header yang sama seperti hasil unduhan export RAK Bulanan:
        Sub Kegiatan, Kode Rekening, Uraian Rekening, Tagging, Pagu, lalu 12 kolom Januari-Desember (nilai bulanan, bukan kumulatif).
        Setiap baris wajib cocok ke mata anggaran yang sudah ada dan aktif - baris yang tidak cocok akan ditolak, bukan membuat mata anggaran baru.
        File akan ditampilkan sebagai <strong>preview</strong> dulu - belum ada yang tersimpan sampai Anda menekan Konfirmasi Simpan.
    </div>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('manajemen-data.import.rak-bulanan.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="fg">
            <label class="fl" for="tahun">Tahun Anggaran</label>
            <input type="number" id="tahun" name="tahun" min="2020" max="2100" value="{{ old('tahun', $tahunSekarang) }}" required style="max-width:160px;">
        </div>

        <div class="fg">
            <label class="fl" for="file">File Excel</label>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </div>

        <div class="sub" style="margin-top:8px;">
            Batas: 5 MB, maksimum {{ number_format(\App\Models\RakBulananImport::MAKS_BARIS, 0, ',', '.') }} baris mata anggaran per file.
            Sesi preview berlaku {{ \App\Models\RakBulananImport::MENIT_KEDALUWARSA }} menit sebelum harus upload ulang.
        </div>

        <div class="nav" style="margin-top:16px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Upload &amp; Preview</button>
        </div>
    </form>
</div>
@endsection
