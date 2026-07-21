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

    @include('npd._tabel', ['npds' => $npds, 'filters' => $filters, 'routeName' => 'npd.index'])
</div>
@endsection
