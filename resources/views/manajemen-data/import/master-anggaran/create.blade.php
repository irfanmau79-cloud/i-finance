@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import Pagu / Master Anggaran')

@section('content')
<div class="dash-card">
    <h3>Import Pagu / Master Anggaran</h3>
    <div class="sub" style="font-weight:700;">Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</div>

    <div class="sub">
        Upload file Excel (.xlsx/.xls) dengan 12 kolom berikut, urut dari kolom A:
        <strong>Tahun, Kode Program, Program, Kode Kegiatan, Kegiatan, Kode Sub Kegiatan, Sub Kegiatan,
        Kode Rekening, Rekening, Tagging, Pagu, Aktif/Non Aktif</strong>.
        Kode dan uraian berada di kolom terpisah &mdash; jangan digabung dalam satu sel.
        Kolom <strong>Pagu</strong> diisi angka nominal saja (contoh <code>15000000</code>), tanpa &ldquo;Rp&rdquo;, huruf, atau simbol lain.
        Kolom Tahun boleh dikosongkan; bila diisi, nilainya wajib {{ config('anggaran.tahun_aktif') }}.
    </div>

    <div class="sub">
        File akan ditampilkan sebagai diperiksa lebih dulu dulu &mdash; belum ada yang tersimpan sampai Anda menekan Konfirmasi Simpan.
        Setelah dikonfirmasi pun, pagunya tersimpan sebagai <strong>versi draf</strong> dan
        <strong>belum berlaku</strong> sampai diaktifkan di halaman <a href="{{ route('versi-pagu.index') }}">Versi Pagu</a>.
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
        <a href="{{ route('manajemen-data.export', 'master-anggaran') }}" class="btn">Unduh Pagu Berlaku</a>
        <a href="{{ route('versi-pagu.index') }}" class="btn">Daftar Versi Pagu ({{ $jumlahVersi }})</a>
    </div>

    <div class="sub" style="margin-top:8px;">
        Pagu yang berlaku sekarang:
        @if ($versiAktif)
            <strong>{{ $versiAktif->nama }}</strong>
            &mdash; Rp {{ fmt_rupiah((float) $versiAktif->total_pagu) }} pada {{ $versiAktif->jumlah_baris }} mata anggaran.
        @else
            <strong>belum ada versi yang diaktifkan.</strong>
        @endif
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.master-anggaran.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="fg">
            <label class="fl" for="tahun">Tahun Anggaran</label>
            <input type="number" id="tahun" name="tahun" value="{{ old('tahun', config('anggaran.tahun_aktif')) }}" min="{{ config('anggaran.tahun_aktif') }}" max="{{ config('anggaran.tahun_aktif') }}" required readonly style="max-width:160px;">
        </div>

        <div class="fg">
            <label class="fl" for="versi_nama">Nama Versi Pagu</label>
            <input type="text" id="versi_nama" name="versi_nama" value="{{ old('versi_nama', $saranNama) }}" maxlength="150" required
                   placeholder="Contoh: DPA Murni, DPA Pergeseran 1, DPA Perubahan" style="max-width:420px;">
            <div class="sub">Nama harus unik dalam satu tahun anggaran. Nama inilah yang muncul di riwayat pergeseran pagu.</div>
        </div>

        <div class="fg">
            <label class="fl" for="versi_keterangan">Keterangan Versi (opsional)</label>
            <textarea id="versi_keterangan" name="versi_keterangan" rows="2" maxlength="2000"
                      placeholder="Contoh: dasar SK Pergeseran Nomor ... tanggal ...">{{ old('versi_keterangan') }}</textarea>
        </div>

        <div class="fg">
            <label class="fl" for="file">File Excel</label>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </div>

        <div class="sub" style="margin-top:8px;">
            Batas: 5 MB, maksimum {{ number_format(\App\Models\MasterAnggaranImport::MAKS_BARIS, 0, ',', '.') }} baris data per file.
            Berkas yang sudah diunggah dapat diperiksa selama {{ \App\Models\MasterAnggaranImport::MENIT_KEDALUWARSA }} menit sebelum perlu diunggah ulang.<br>
            Mata anggaran yang ada sekarang tapi <strong>tidak dicantumkan</strong> di file ini akan berpagu 0 dan dinonaktifkan saat versi diaktifkan &mdash;
            dokumen DPA diperlakukan utuh, bukan sebagai perubahan sebagian.
        </div>

        <div class="nav" style="margin-top:16px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Upload &amp; Preview</button>
        </div>
    </form>
</div>
@endsection
