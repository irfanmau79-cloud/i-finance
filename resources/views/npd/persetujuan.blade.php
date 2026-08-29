@extends('layouts.app')

@section('activeNav', 'persetujuan')
@section('title', 'Persetujuan Nota Pencairan Dana')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Persetujuan NPD</b></div>
        <div class="ph-title">Persetujuan Nota Pencairan Dana</div>
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
    <div class="sub" style="margin-bottom:14px;">Nota Pencairan Dana yang menunggu tindakan Bendahara Pengeluaran Pembantu.</div>

    @include('npd._tabel-workflow', ['npds' => $npds])
</div>
@endsection
