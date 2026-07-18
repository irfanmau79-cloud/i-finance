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

    <div class="tbl-tools">
        <a href="{{ route('npd.bj.create') }}" class="btn prim" style="white-space:nowrap;">+ NPD Barang/Jasa</a>
    </div>

    @include('npd._tabel', ['npds' => $npds, 'routeName' => 'npd.index'])
</div>
@endsection
