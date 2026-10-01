@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', $data ? 'Edit Dokumen SPJ Perjalanan Dinas' : 'Tambah Dokumen SPJ Perjalanan Dinas')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <a href="{{ route('manajemen-data.index') }}">Manajemen Data</a> /
            <a href="{{ route('manajemen-data.rincian.spj-perjalanan-dinas') }}">Rincian Data SPJ Perjalanan Dinas</a> /
            {{ $data ? 'Edit' : 'Tambah' }}</div>
        <div class="ph-title">{{ $data ? 'Edit Dokumen' : 'Tambah Dokumen' }}</div>
    </div>
</div>

<div class="dash-card">
    <div class="sub">Satu baris = satu dokumen SPJ perjalanan dinas. Bentuknya mengikuti baris yang dihasilkan NPD, supaya keduanya bisa dibaca sebagai satu daftar di dashboard.</div>

    @if ($errors->any())
        <div class="err-box" style="display:block">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $data ? route('manajemen-data.rincian.spj-perjalanan-dinas.update', $data) : route('manajemen-data.rincian.spj-perjalanan-dinas.store') }}" style="margin-top:14px;">
        @csrf
        @if ($data) @method('PUT') @endif

        <div class="form-grid-auto">
            <div class="fg">
                <label class="fl" for="tahun">Tahun</label>
                <input type="number" id="tahun" name="tahun" value="{{ old('tahun', $data->tahun ?? config('anggaran.tahun_aktif')) }}" required>
            </div>
            <div class="fg">
                <label class="fl" for="tanggal">Tanggal Dokumen</label>
                <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', $data?->tanggal?->format('Y-m-d')) }}" required>
            </div>
            <div class="fg">
                <label class="fl" for="nomor_npd">Nomor Dokumen</label>
                <input type="text" id="nomor_npd" name="nomor_npd" value="{{ old('nomor_npd', $data->nomor_npd ?? '') }}" required placeholder="Contoh: 12/NPD-Keu.1.IBC/III/2026">
            </div>
            <div class="fg">
                <label class="fl" for="nomor_sp">Nomor Surat Perintah</label>
                <input type="text" id="nomor_sp" name="nomor_sp" value="{{ old('nomor_sp', $data->nomor_sp ?? '') }}" placeholder="Opsional">
            </div>

            <div class="fg">
                <label class="fl" for="bidang">Bidang</label>
                <select id="bidang" name="bidang" required>
                    <option value="">&mdash; pilih bidang &mdash;</option>
                    @foreach (\App\Support\BidangOrganisasi::PENGAWASAN as $opsi)
                        <option value="{{ $opsi }}" @selected(old('bidang', $data->bidang ?? '') === $opsi)>{{ $opsi }}</option>
                    @endforeach
                </select>
                <div class="sub" style="margin-top:4px;">Dibatasi daftar yang sama dengan pengelompokan di dashboard.</div>
            </div>
            <div class="fg">
                <label class="fl" for="nominal">Nominal (Rp)</label>
                <input type="number" step="0.01" min="0" id="nominal" name="nominal" value="{{ old('nominal', $data->nominal ?? 0) }}" required>
            </div>
            <div class="fg span2">
                <label class="fl" for="sub_kegiatan">Sub Kegiatan</label>
                <input type="text" id="sub_kegiatan" name="sub_kegiatan" value="{{ old('sub_kegiatan', $data->sub_kegiatan ?? '') }}" placeholder="Opsional">
            </div>

            <div class="fg span2">
                <label class="fl" for="uraian">Uraian</label>
                <input type="text" id="uraian" name="uraian" value="{{ old('uraian', $data->uraian ?? '') }}" placeholder="Maksud perjalanan">
            </div>

            <div class="fg span2">
                <label class="komp-chip" style="display:inline-flex;">
                    <input type="checkbox" name="spj_terverifikasi" value="1" @checked(old('spj_terverifikasi', $data->spj_terverifikasi ?? false))>
                    <span class="komp-box"><svg viewBox="0 0 16 16" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4.5"/></svg></span>
                    <span class="komp-txt">SPJ sudah terverifikasi</span>
                </label>
                <div class="sub" style="margin-top:4px;">Tanggal dan nama verifikator di bawah hanya disimpan bila kotak ini dicentang.</div>
            </div>

            <div class="fg">
                <label class="fl" for="tanggal_verifikasi">Tanggal Verifikasi</label>
                <input type="date" id="tanggal_verifikasi" name="tanggal_verifikasi" value="{{ old('tanggal_verifikasi', $data?->tanggal_verifikasi?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="diverifikasi_oleh">Diverifikasi Oleh</label>
                <input type="text" id="diverifikasi_oleh" name="diverifikasi_oleh" value="{{ old('diverifikasi_oleh', $data->diverifikasi_oleh ?? '') }}" placeholder="Nama petugas">
            </div>

            <div class="fg span2">
                <label class="fl" for="keterangan">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $data->keterangan ?? '') }}" placeholder="Opsional">
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('manajemen-data.rincian.spj-perjalanan-dinas') }}">Batal</a>
            <button type="submit" class="btn prim">{{ $data ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
    </form>
</div>
@endsection
