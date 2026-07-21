@extends('layouts.app')

@section('activeNav', 'npd-create')
@section('title', 'Buat NPD')

@section('content')
<style>
    .npd-type-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:18px}
    .npd-type-card{display:flex;min-height:150px;flex-direction:column;justify-content:space-between;padding:20px;border:1px solid var(--line);border-radius:12px;background:#fff;box-shadow:var(--shadow);text-decoration:none;color:inherit;transition:.18s}
    .npd-type-card:hover{transform:translateY(-3px);border-color:var(--gold)}
    .npd-type-card strong{font-size:18px;color:var(--navy)}
    .npd-type-card span{margin-top:8px;color:var(--mut);line-height:1.5}
    .npd-type-card b{margin-top:18px;color:var(--navy)}
</style>
<div class="page-head">
    <div>
        <div class="ph-title">Buat NPD</div>
        <div class="ph-sub">Pilih jenis Nota Pencairan Dana yang akan dibuat.</div>
    </div>
    <a class="btn" href="{{ route('npd.index') }}">Daftar NPD</a>
</div>

<div class="npd-type-grid">
    @foreach ([
        ['route' => 'npd.bj.create', 'title' => 'Barang/Jasa', 'description' => 'Belanja barang atau jasa dengan satu atau beberapa penerima.'],
        ['route' => 'npd.pd.create', 'title' => 'Perjalanan Dinas', 'description' => 'Perjalanan dinas dengan tim dan perhitungan komponen biaya.'],
        ['route' => 'npd.tr.create', 'title' => 'Transport', 'description' => 'Pembayaran transport yang terkait perjalanan atau kegiatan.'],
        ['route' => 'npd.ns.create', 'title' => 'Narasumber', 'description' => 'Honorarium narasumber beserta pajak dan daftar pembayaran.'],
        ['route' => 'npd.kd.create', 'title' => 'Kontribusi Diklat', 'description' => 'Kontribusi atau perjalanan peserta pendidikan dan pelatihan.'],
    ] as $type)
        <a class="npd-type-card" href="{{ route($type['route']) }}">
            <div><strong>{{ $type['title'] }}</strong><span>{{ $type['description'] }}</span></div>
            <b>Buat NPD &rarr;</b>
        </a>
    @endforeach
</div>
@endsection
