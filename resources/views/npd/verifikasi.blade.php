@extends('layouts.app')

@section('activeNav', 'verifikasi')
@section('title', 'Verifikasi NPD')

@section('content')
<div class="dash-card wf-card">
    <h3>Verifikasi NPD</h3>
    <div class="sub">Seluruh NPD &mdash; tombol aksi di halaman detail aktif hanya untuk status "Verifikasi - Verifikator".</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @include('npd._tabel', ['npds' => $npds, 'routeName' => 'npd.verifikasi'])
</div>
@endsection
