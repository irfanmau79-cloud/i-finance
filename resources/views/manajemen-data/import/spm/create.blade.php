@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import SPM '.strtoupper(str_replace('spm-', '', $jenis)))

@php($labelJenis = $jenisSpm === 'ls' ? 'LS' : 'UP/GU')

@section('content')
<div class="dash-card">
    <h3>Import SPM {{ $labelJenis }}</h3>
    <div class="sub">
        Upload file Excel (.xlsx/.xls) dengan header yang sama seperti hasil unduhan export SPM {{ $labelJenis }}.
        @if ($jenisSpm === 'ls')
            Kolomnya: <strong>Tanggal SPM, Nomor SPM, Tanggal SP2D, Nomor SP2D, Kode Sub Kegiatan, Sub Kegiatan,
            Kode Rekening, Rekening, Tagging, Nominal, PPN, Jenis PPh 1, Nominal PPh 1, Jenis PPh 2, Nominal PPh 2,
            Penerima, Uraian</strong>. Kode dan uraian berada di kolom terpisah - jangan digabung dalam satu sel.
            Kolom Sub Kegiatan + Kode Rekening + Tagging wajib cocok ke mata anggaran yang sudah ada dan aktif - baris yang tidak cocok akan ditolak, bukan membuat mata anggaran baru.
            Satu dokumen SPM LS boleh mencakup beberapa mata anggaran: tulis satu baris per mata anggaran dengan Tanggal/Nomor SPM yang sama persis.
        @else
            Kolomnya: <strong>Tanggal SPM, Nomor SPM, Tanggal SP2D, Nomor SP2D, Nominal, Uraian</strong>.
            SPM UP/GU/TU tidak memerlukan mata anggaran, dan PPN/PPh maupun Penerima tidak dibawa berkas ini -
            nilai yang sudah diisi lewat form SPM tidak akan tertimpa oleh import.
        @endif
        File akan ditampilkan sebagai diperiksa lebih dulu dulu - belum ada yang tersimpan sampai Anda menekan Konfirmasi Simpan.
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
        <a href="{{ route('manajemen-data.import.spm.template', $jenis) }}" class="btn">Unduh Template</a>
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.spm.store', $jenis) }}" enctype="multipart/form-data">
        @csrf

        <div class="fg">
            <label class="fl" for="file">File Excel</label>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </div>

        <div class="sub" style="margin-top:8px;">
            Batas: 5 MB, maksimum {{ number_format(\App\Models\SpmImport::MAKS_BARIS, 0, ',', '.') }} baris data per file.
            Berkas yang sudah diunggah dapat diperiksa selama {{ \App\Models\SpmImport::MENIT_KEDALUWARSA }} menit sebelum perlu diunggah ulang.
        </div>

        <div class="nav" style="margin-top:16px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Upload &amp; Preview</button>
        </div>
    </form>
</div>
@endsection
