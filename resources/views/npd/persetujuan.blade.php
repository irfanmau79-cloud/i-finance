@extends('layouts.app')

@section('activeNav', 'persetujuan')
@section('title', 'Persetujuan NPD')

@section('content')
<div class="dash-card wf-card">
    <h3>Persetujuan NPD</h3>
    <div class="sub">Secara default menampilkan NPD yang memerlukan tindakan BPP. Gunakan filter status untuk melihat arsip proses maupun NPD Selesai.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @include('npd._tabel', ['npds' => $npds, 'filters' => $filters, 'routeName' => 'npd.persetujuan'])
</div>
@endsection
