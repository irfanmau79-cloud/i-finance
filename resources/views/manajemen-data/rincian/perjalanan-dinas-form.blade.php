@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', $data ? 'Edit Rincian Perjalanan Dinas' : 'Tambah Rincian Perjalanan Dinas')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <a href="{{ route('manajemen-data.index') }}">Manajemen Data</a> /
            <a href="{{ route('manajemen-data.rincian.perjalanan-dinas') }}">Rincian Data Perjalanan Dinas</a> /
            {{ $data ? 'Edit' : 'Tambah' }}</div>
        <div class="ph-title">{{ $data ? 'Edit Rincian Perjalanan Dinas' : 'Tambah Rincian Perjalanan Dinas' }}</div>
    </div>
</div>

<div class="dash-card">
    <div class="sub">
        Satu baris = satu orang pada satu bulan. Jumlah Diterima tidak diisi &mdash; selalu dijumlahkan dari
        Uang Harian + Akomodasi + Transport + Representatif, rumus yang sama dengan NPD.
    </div>

    @if ($errors->any())
        <div class="err-box" style="display:block">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $data ? route('manajemen-data.rincian.perjalanan-dinas.update', $data) : route('manajemen-data.rincian.perjalanan-dinas.store') }}" style="margin-top:14px;">
        @csrf
        @if ($data) @method('PUT') @endif

        <div class="form-grid-auto">
            <div class="fg span2">
                <label class="fl" for="pegawai_id">Pegawai</label>
                <select id="pegawai_id" name="pegawai_id" data-cari required>
                    <option value="">&mdash; pilih pegawai &mdash;</option>
                    @foreach ($pegawaiList as $pegawai)
                        <option value="{{ $pegawai->id }}" @selected((int) old('pegawai_id', $data->pegawai_id ?? 0) === $pegawai->id)>
                            {{ $pegawai->nama }} &middot; {{ $pegawai->nip }}
                        </option>
                    @endforeach
                </select>
                <div class="sub" style="margin-top:4px;">Ditautkan lewat Data Pegawai supaya baris ini dan baris dari NPD jatuh ke orang yang sama di dashboard.</div>
            </div>

            <div class="fg">
                <label class="fl" for="bulan">Bulan</label>
                <select id="bulan" name="bulan" required>
                    @foreach (range(1, 12) as $nomor)
                        <option value="{{ $nomor }}" @selected((int) old('bulan', $data->bulan ?? 0) === $nomor)>
                            {{ \Carbon\Carbon::create(null, $nomor)->translatedFormat('F') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="tahun">Tahun</label>
                <input type="number" id="tahun" name="tahun" value="{{ old('tahun', $data->tahun ?? config('anggaran.tahun_aktif')) }}" required>
            </div>
            <div class="fg">
                <label class="fl" for="hari">Jumlah Hari</label>
                <input type="number" step="0.01" min="0" id="hari" name="hari" value="{{ old('hari', $data->hari ?? 0) }}">
            </div>

            <div class="fg">
                <label class="fl" for="uang_harian">Uang Harian (Rp)</label>
                <input type="number" step="0.01" min="0" id="uang_harian" name="uang_harian" value="{{ old('uang_harian', $data->uang_harian ?? 0) }}">
            </div>
            <div class="fg">
                <label class="fl" for="akomodasi">Akomodasi (Rp)</label>
                <input type="number" step="0.01" min="0" id="akomodasi" name="akomodasi" value="{{ old('akomodasi', $data->akomodasi ?? 0) }}">
            </div>
            <div class="fg">
                <label class="fl" for="transport">Transport (Rp)</label>
                <input type="number" step="0.01" min="0" id="transport" name="transport" value="{{ old('transport', $data->transport ?? 0) }}">
                <div class="sub" style="margin-top:4px;">BBM + tol + tiket, sama seperti kolom Transportasi di dashboard.</div>
            </div>
            <div class="fg">
                <label class="fl" for="representatif">Representatif (Rp)</label>
                <input type="number" step="0.01" min="0" id="representatif" name="representatif" value="{{ old('representatif', $data->representatif ?? 0) }}">
            </div>

            <div class="fg span2">
                <label class="fl" for="keterangan">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" value="{{ old('keterangan', $data->keterangan ?? '') }}" placeholder="Opsional">
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('manajemen-data.rincian.perjalanan-dinas') }}">Batal</a>
            <button type="submit" class="btn prim">{{ $data ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
    </form>
</div>
@endsection
