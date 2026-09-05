@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import Data PKPT')

@section('content')
<div class="dash-card">
    <h3>Import Data PKPT</h3>
    <div class="sub">
        Unggah berkas Excel (.xlsx/.xls) Program Kerja Pengawasan Tahunan. Satu baris = satu kegiatan pengawasan.
        Baris dikenali dari gabungan <b>Tahun Anggaran + Unit Kerja + Nomor</b>: kombinasi yang sudah ada akan DIPERBARUI,
        yang belum ada ditambahkan. Berkasnya ditampilkan untuk diperiksa lebih dulu &mdash; belum ada yang tersimpan
        sampai Anda menekan Konfirmasi Simpan.
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
        <a href="{{ route('manajemen-data.import.pkpt.template') }}" class="btn">Unduh Template</a>
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.pkpt.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="fg">
            <label class="fl" for="tahun">Tahun Anggaran</label>
            <input type="number" id="tahun" name="tahun" min="2020" max="2100" step="1"
                   value="{{ old('tahun', $tahunAktif) }}" required style="max-width:180px;">
            <div class="sub" style="margin-top:6px;margin-bottom:0;">
                Tidak diambil dari berkas &mdash; dokumen PKPT tidak mencantumkan tahun di tiap barisnya.
                Monitoring PKPT menampilkan Tahun Anggaran {{ $tahunAktif }}.
            </div>
        </div>

        <div class="fg">
            <label class="fl" for="file">File Excel</label>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </div>

        <div class="sub" style="margin-top:8px;">
            Batas: 5 MB, maksimum {{ number_format(\App\Models\PkptImport::MAKS_BARIS, 0, ',', '.') }} baris data per berkas.
            Berkas yang sudah diunggah dapat diperiksa selama {{ \App\Models\PkptImport::menitKedaluwarsa() }} menit sebelum perlu diunggah ulang.
        </div>

        <div class="nav" style="margin-top:16px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Upload &amp; Preview</button>
        </div>
    </form>
</div>
@endsection
