@extends('layouts.app')

@section('activeNav', 'tk-data')
@section('title', 'Tambah Pegawai')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / <a href="{{ route('tunjangan.data.index') }}">Data Tunjangan Keluarga</a> / Tambah Pegawai</div>
        <div class="ph-title">Tambah Pegawai</div>
    </div>
</div>

<div class="dash-card">
    <div class="sub">Pegawai baru perlu terdaftar di sini sebelum data tunjangan keluarganya bisa diisi atau di-import.</div>

    @if ($errors->any())
        <div class="err-box" style="display:block">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tunjangan.pegawai.store') }}" style="margin-top:14px;max-width:640px;">
        @csrf

        <div class="form-grid2">
            <div class="fg">
                <label class="fl" for="nama">Nama Pegawai</label>
                <input id="nama" name="nama" value="{{ old('nama') }}" placeholder="Nama lengkap" required>
            </div>
            <div class="fg">
                <label class="fl" for="nip">NIP</label>
                <input id="nip" name="nip" value="{{ old('nip') }}" placeholder="Nomor Induk Pegawai" required>
            </div>
        </div>

        <div class="fg">
            <label class="fl" for="jabatan">Jabatan</label>
            <input id="jabatan" name="jabatan" value="{{ old('jabatan') }}" placeholder="Jabatan" required>
        </div>

        <div class="form-grid2">
            <div class="fg">
                <label class="fl" for="golongan">Golongan</label>
                <input id="golongan" name="golongan" value="{{ old('golongan') }}" placeholder="Opsional">
            </div>
            <div class="fg">
                <label class="fl" for="pangkat">Pangkat</label>
                <input id="pangkat" name="pangkat" value="{{ old('pangkat') }}" placeholder="Opsional">
            </div>
        </div>

        <div class="form-grid2">
            <div class="fg">
                <label class="fl" for="bidang">Bidang</label>
                <input id="bidang" name="bidang" value="{{ old('bidang') }}" placeholder="Bidang" required>
            </div>
            <div class="fg">
                <label class="fl" for="rekening">Rekening</label>
                <input id="rekening" name="rekening" value="{{ old('rekening') }}" placeholder="Opsional">
            </div>
        </div>

        <div class="fg">
            <label class="fl" for="aktif">Status</label>
            <select id="aktif" name="aktif">
                <option value="1" @selected(old('aktif', '1') == '1')>Aktif</option>
                <option value="0" @selected(old('aktif') == '0')>Tidak Aktif</option>
            </select>
        </div>

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('tunjangan.data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan</button>
        </div>
    </form>
</div>
@endsection
