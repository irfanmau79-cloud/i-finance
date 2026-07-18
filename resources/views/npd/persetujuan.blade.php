@extends('layouts.app')

@section('activeNav', 'persetujuan')
@section('title', 'Persetujuan NPD')

@section('content')
<div class="dash-card wf-card">
    <h3>Persetujuan NPD</h3>
    <div class="sub">Seluruh NPD &mdash; tombol aksi di halaman detail aktif hanya untuk status di tahap BPP (Draft NPD - BPP, NPD Disetujui - BPP, Selesai).</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @include('npd._tabel', ['npds' => $npds, 'routeName' => 'npd.persetujuan'])
</div>
@endsection
