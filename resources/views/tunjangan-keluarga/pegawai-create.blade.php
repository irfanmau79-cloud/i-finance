@extends('layouts.app')

@section('activeNav', 'tk-pegawai')
@section('title', 'Tambah Pegawai')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Data Kepegawaian</b> / <a href="{{ route('tunjangan.pegawai.index') }}">Data Pegawai</a> / Tambah Pegawai</div>
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

    <form method="POST" action="{{ route('tunjangan.pegawai.store') }}" style="margin-top:14px;">
        @csrf
        @include('tunjangan-keluarga._pegawai-form')

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('tunjangan.pegawai.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan</button>
        </div>
    </form>
</div>
@endsection
