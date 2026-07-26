@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import Pagu / Master Anggaran')

@section('content')
<div class="dash-card">
    <h3>Import Pagu / Master Anggaran</h3>
    <div class="sub" style="font-weight:700;">Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</div>
    <div class="sub">
        Upload file Excel (.xlsx/.xls) dengan header yang sama seperti hasil unduhan export Pagu/Master Anggaran:
        Tahun Anggaran, Program, Kegiatan, Sub Kegiatan, Kode Rekening, Uraian Rekening, Tagging, Pagu, Aktif.
        File lama tanpa kolom Tahun Anggaran diperlakukan sebagai TA {{ config('anggaran.tahun_aktif') }}; bila kolom tahun dicantumkan, nilainya wajib {{ config('anggaran.tahun_aktif') }}.
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

    <div class="tbl-tools">
        <a href="{{ route('manajemen-data.import.master-anggaran.template') }}" class="btn">Unduh Template</a>
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.master-anggaran.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="fg">
            <label class="fl" for="tahun">Tahun Anggaran</label>
            <input type="number" id="tahun" name="tahun" value="{{ old('tahun', config('anggaran.tahun_aktif')) }}" min="{{ config('anggaran.tahun_aktif') }}" max="{{ config('anggaran.tahun_aktif') }}" required readonly style="max-width:160px;">
        </div>

        <div class="fg">
            <label class="fl" for="file">File Excel</label>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </div>

        <div class="sub" style="margin-top:8px;">
            {{ config('anggaran.catatan_scope') }}<br>
            Batas: 5 MB, maksimum {{ number_format(\App\Models\MasterAnggaranImport::MAKS_BARIS, 0, ',', '.') }} baris data per file.
            Sesi preview berlaku {{ \App\Models\MasterAnggaranImport::MENIT_KEDALUWARSA }} menit sebelum harus upload ulang.
        </div>

        <div class="nav" style="margin-top:16px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Upload &amp; Preview</button>
        </div>
    </form>
</div>
@endsection
