@extends('layouts.app')

@section('activeNav', 'npd')
@section('title', 'Daftar NPD')

@section('content')
<div class="dash-card wf-card">
    <h3>Daftar NPD</h3>
    <div class="sub">Seluruh Nota Pencairan Dana yang telah dibuat.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if (in_array(auth()->user()->role, [\App\Models\User::ROLE_SUPERADMIN, \App\Models\User::ROLE_PPTK], true))
        <div class="tbl-tools">
            <a href="{{ route('npd.bj.create') }}" class="btn prim" style="white-space:nowrap;">+ NPD Barang/Jasa</a>
            <a href="{{ route('npd.pd.create') }}" class="btn prim" style="white-space:nowrap;">+ NPD Perjalanan Dinas</a>
            <a href="{{ route('npd.ns.create') }}" class="btn prim" style="white-space:nowrap;">+ NPD Narasumber</a>
            <a href="{{ route('npd.kd.create') }}" class="btn prim" style="white-space:nowrap;">+ NPD Kontribusi Diklat</a>
            <a href="{{ route('npd.tr.create') }}" class="btn prim" style="white-space:nowrap;">+ NPD Transport</a>
        </div>
    @endif

    @include('npd._tabel', ['npds' => $npds, 'routeName' => 'npd.index'])
</div>
@endsection
