@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import Rekap Potensi Pengembalian')

@section('content')
<div class="dash-card">
    <h3>Import Rekap Potensi Pengembalian</h3>
    <div class="sub">
        Upload file Excel (.xlsx/.xls) dengan header: NIP, Nama Pegawai, Jabatan, Potensi Kelebihan Pembayaran, Setoran, Keterangan.
        NIP yang sudah terdaftar akan DIPERBARUI; NIP baru akan ditambahkan. Kolom Sisa Pengembalian tidak perlu diisi &mdash; nilainya selalu dihitung Potensi dikurangi Setoran.
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
        <a href="{{ route('manajemen-data.import.rekap-potensi.template') }}" class="btn">Unduh Template</a>
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.rekap-potensi.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="fg">
            <label class="fl" for="file">File Excel</label>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </div>

        <div class="sub" style="margin-top:8px;">
            Batas: 5 MB, maksimum {{ number_format(\App\Models\RekapPotensiImport::MAKS_BARIS, 0, ',', '.') }} baris data per file.
            Berkas yang sudah diunggah dapat diperiksa selama {{ \App\Models\RekapPotensiImport::menitKedaluwarsa() }} menit sebelum perlu diunggah ulang.
        </div>

        <div class="nav" style="margin-top:16px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Upload &amp; Preview</button>
        </div>
    </form>
</div>
@endsection
