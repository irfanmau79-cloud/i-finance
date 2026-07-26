@extends('layouts.app')

@section('activeNav', 'persetujuan')
@section('title', 'Persetujuan NPD')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Persetujuan NPD</b></div>
        <div class="ph-title">Persetujuan NPD</div>
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
    <form method="GET" action="{{ route('npd.persetujuan') }}" class="tbl-tools" style="margin-bottom:14px;">
        <select name="jenis" style="max-width:220px;">
            <option value="">-- Semua Jenis --</option>
            @foreach (\App\Models\Npd::JENIS_LABEL as $kode => $label)
                <option value="{{ $kode }}" @selected($filters['jenis'] === $kode)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" style="max-width:260px;">
            <option value="semua" @selected($filters['status'] === 'semua')>-- Semua Status --</option>
            @foreach (\App\Models\Npd::STATUS_LIST as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn prim" style="white-space:nowrap;">Filter</button>
        @if ($filters['jenis'] !== '' || request()->has('status'))
            <a href="{{ route('npd.persetujuan') }}" class="btn" style="white-space:nowrap;">Reset</a>
        @endif
    </form>

    @include('npd._tabel-workflow', ['npds' => $npds])
</div>
@endsection
