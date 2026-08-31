@extends('layouts.app')

@section('activeNav', 'gt-gaji')
@section('title', 'Preview Import Data Gaji & Tunjangan')

@section('content')
@php
    $siap = $import->status === 'preview' && $import->baris_invalid === 0;
@endphp

<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Gaji dan Tunjangan</b> / Import Data / Preview</div>
        <div class="ph-title">Pemeriksaan Berkas #{{ $import->id }}</div>
        <div class="ph-sub">
            {{ $import->labelJenis() }} &middot; {{ $import->labelPeriode() }} &middot; {{ $import->nama_file }}
        </div>
    </div>
</div>

@if ($errors->any())
    <div class="err-box" style="display:block;margin-bottom:16px;">{{ $errors->first() }}</div>
@endif

<div class="dash-card">
    <div class="sub" style="margin-top:0;">
        <b>{{ $import->baris_valid }}</b> baris siap disimpan &middot;
        <b>{{ $import->baris_invalid }}</b> baris bermasalah dari total {{ $import->total_baris }} baris.
    </div>

    @if ($import->status === 'committed')
        <div class="sumbar ok" style="margin-bottom:14px;"><span>Batch ini sudah dikonfirmasi pada {{ $import->committed_at?->format('d-m-Y H:i') }}.</span></div>
    @elseif ($import->baris_tertimpa > 0)
        <div class="err-box" style="display:block;margin-bottom:14px;">
            Sudah ada <b>{{ $import->baris_tertimpa }}</b> baris {{ $import->labelJenis() }} untuk {{ $import->labelPeriode() }}.
            Konfirmasi akan <b>MENGHAPUS</b> seluruh baris lama periode itu lalu menggantinya dengan isi berkas ini.
        </div>
    @endif

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;max-height:60vh;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Baris</th>
                    <th>Status</th>
                    <th>Nama Pegawai</th>
                    <th>NIP</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($import->baris->sortBy('nomor_baris') as $row)
                    <tr>
                        <td>{{ $row->nomor_baris }}</td>
                        <td>
                            <span class="badge {{ $row->valid ? 'st-aktif' : 'st-danger' }}">
                                {{ $row->valid ? 'Siap' : 'Bermasalah' }}
                            </span>
                        </td>
                        <td>{{ $row->nama_pegawai ?: '-' }}</td>
                        <td>{{ $row->nip ?: '-' }}</td>
                        <td>{{ implode(' | ', $row->pesan ?? []) ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($siap)
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
            <form method="POST" action="{{ route('gaji-tunjangan.import.konfirmasi', $import) }}"
                  onsubmit="return confirm('Simpan {{ $import->baris_valid }} baris {{ $import->labelJenis() }} untuk {{ $import->labelPeriode() }}? Data periode ini akan ditimpa.')">
                @csrf
                <button class="btn prim">Konfirmasi Simpan</button>
            </form>
            <form method="POST" action="{{ route('gaji-tunjangan.import.batalkan', $import) }}">
                @csrf @method('DELETE')
                <button class="btn">Batalkan</button>
            </form>
        </div>
    @elseif ($import->baris_invalid > 0)
        <div class="err-box" style="display:block;margin-top:16px;">
            Perbaiki baris yang bermasalah lalu unggah ulang berkasnya. Pemeriksaan ini belum mengubah data apa pun.
        </div>
        <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn" href="{{ route('gaji-tunjangan.import.create') }}">Unggah Ulang</a>
            <form method="POST" action="{{ route('gaji-tunjangan.import.batalkan', $import) }}">
                @csrf @method('DELETE')
                <button class="btn">Batalkan</button>
            </form>
        </div>
    @endif
</div>
@endsection
