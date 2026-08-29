@extends('layouts.app')

@section('activeNav', 'verifikasi')
@section('title', 'Verifikasi Nota Pencairan Dana')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Verifikasi NPD</b></div>
        <div class="ph-title">Verifikasi Nota Pencairan Dana</div>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block">
        <strong>Terjadi kesalahan:</strong>
        <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="dash-card wf-card">
    <div class="sub" style="margin-bottom:14px;">Nota Pencairan Dana yang menunggu tindakan Verifikator.</div>

    {{-- Penyaring jenis & status ada di baris penyaring dalam tabel. --}}

    @include('npd._tabel-workflow', ['npds' => $npds])
</div>
@endsection
