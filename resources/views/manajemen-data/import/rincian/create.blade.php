@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import '.$meta['judul'])

@section('content')
<div class="dash-card">
    <h3>Import {{ $meta['judul'] }}</h3>
    <div class="sub">{{ $catatan }}</div>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('manajemen-data.import.rincian.template', $jenis) }}" class="btn">Unduh Template</a>
        <a href="{{ route($meta['rute_rincian']) }}" class="btn">Lihat Rincian</a>
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.rincian.store', $jenis) }}" enctype="multipart/form-data" style="margin-top:14px;">
        @csrf

        <div class="form-grid-auto">
            <div class="fg">
                <label class="fl" for="tahun">Tahun Anggaran</label>
                <input type="number" id="tahun" name="tahun" value="{{ old('tahun', $tahunSekarang) }}" required>
                <div class="sub" style="margin-top:4px;">Seluruh baris pada berkas ini masuk ke tahun tersebut.</div>
            </div>
            <div class="fg span2">
                <label class="fl" for="file">Berkas Excel</label>
                <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
                <div class="sub" style="margin-top:4px;">
                    Batas: 5 MB. Berkas diperiksa lebih dulu &mdash; belum ada yang tersimpan sampai Anda menekan Konfirmasi Simpan.
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Periksa Berkas</button>
        </div>
    </form>
</div>

<div class="dash-card" style="margin-top:18px;">
    <h3>Petunjuk Pengisian Kolom</h3>
    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:10px;">
        <table class="realisasi">
            <thead>
                <tr><th>Kolom</th><th>Wajib</th><th>Format</th><th>Penjelasan</th><th>Contoh Isi</th></tr>
            </thead>
            <tbody>
                @foreach ($petunjuk as [$kolom, $wajib, $format, $penjelasan, $contoh])
                    <tr>
                        <td style="font-weight:600;">{{ $kolom }}</td>
                        <td>{{ $wajib }}</td>
                        <td>{{ $format }}</td>
                        <td class="kol-uraian">{{ $penjelasan }}</td>
                        <td>{{ $contoh }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
