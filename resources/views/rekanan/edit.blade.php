@extends('layouts.app')

@section('activeNav', 'npd-rekanan')
@section('title', 'Edit Rekanan')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Nota Pencairan Dana (NPD)</b> / <a href="{{ route('rekanan.index') }}">Daftar Rekanan</a> / Edit Rekanan</div>
        <div class="ph-title">{{ $rekanan->nama }}</div>
    </div>
</div>

<div class="dash-card">
    @if ($errors->any())
        <div class="err-box" style="display:block">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('rekanan.update', $rekanan) }}" style="margin-top:14px;">
        @csrf
        @method('PUT')
        @include('rekanan._form')

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('rekanan.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection
