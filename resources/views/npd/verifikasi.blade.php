@extends('layouts.app')

@section('activeNav', 'verifikasi')
@section('title', 'Verifikasi NPD')

@section('content')
<div class="dash-card wf-card">
    <h3>Verifikasi NPD</h3>
    <div class="sub">Secara default menampilkan NPD yang memerlukan tindakan Verifikator. Gunakan filter status untuk melihat arsip proses maupun NPD Selesai.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @include('npd._tabel', ['npds' => $npds, 'filters' => $filters, 'routeName' => 'npd.verifikasi'])
</div>
@endsection
