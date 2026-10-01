@extends('layouts.app')

@section('activeNav', 'npd-rekanan')
@section('title', 'Tambah Rekanan')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Nota Pencairan Dana (NPD)</b> / <a href="{{ route('rekanan.index') }}">Daftar Rekanan</a> / Tambah Rekanan</div>
        <div class="ph-title">Tambah Rekanan</div>
    </div>
</div>

<div class="dash-card">
    <div class="sub">Untuk rekanan baru yang muncul satu-satu. Kalau datanya banyak, lebih cepat lewat Manajemen Data &rsaquo; Data Rekanan.</div>

    @if ($errors->any())
        <div class="err-box" style="display:block">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('rekanan.store') }}" style="margin-top:14px;">
        @csrf
        @include('rekanan._form')

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('rekanan.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan</button>
        </div>
    </form>
</div>
@endsection
