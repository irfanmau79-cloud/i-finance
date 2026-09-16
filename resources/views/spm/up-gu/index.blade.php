@extends('layouts.app')

@section('activeNav', 'spm-upgu')
@section('title', 'Realisasi SP2D UP/GU/TU')

@section('content')
<div class="dash-card wf-card">
    <h3>Data Realisasi SP2D UP/GU/TU</h3>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="sub">
        SPM UP/GU/TU adalah pengisian ulang kas &mdash; TIDAK mengurangi pagu dan tidak masuk realisasi.
    </div>

    {{-- Tombol tambah dan kotak cari disatukan dalam satu baris alat, sama
         seperti daftar SPM LS: dua baris alat yang berdiri sendiri membuat
         tabelnya terdorong jauh ke bawah tanpa alasan. --}}
    <div class="tbl-tools">
        @if (boleh_ubah())
            <a href="{{ route('spm.up-gu.create') }}" class="btn prim" style="white-space:nowrap;">Tambah Realisasi SP2D UP/GU/TU</a>
        @endif
        <form method="GET" action="{{ route('spm.up-gu.index') }}" class="spm-cari">
            <input type="text" name="cari" placeholder="Cari nomor SPM, penerima, atau uraian&hellip;" value="{{ request('cari') }}">
            <button type="submit" class="btn prim" style="white-space:nowrap;">Cari</button>
            @if (request()->hasAny(['cari']))
                <a href="{{ route('spm.up-gu.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
            @endif
        </form>
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi tbl-fixed">
            <colgroup>
                <col style="width:10%;"><col style="width:16%;"><col style="width:10%;">
                <col style="width:13%;"><col style="width:14%;"><col style="width:25%;"><col style="width:12%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Tanggal SPM</th>
                    <th>Nomor SPM</th>
                    <th>Tanggal SP2D</th>
                    <th>Nomor SP2D</th>
                    <th class="num">Nominal</th>
                    <th>Uraian</th>
                    <th class="mid">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spms as $spm)
                    <tr>
                        <td>{{ $spm->tanggal_dokumen->format('d-m-Y') }}</td>
                        <td>{{ $spm->nomor_dokumen }}</td>
                        <td>{{ $spm->tanggal_sp2d?->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $spm->nomor_sp2d ?? '—' }}</td>
                        <td class="num">Rp {{ number_format((float) $spm->nominal, 2, ',', '.') }}</td>
                        <td>
                            @if ($spm->uraian)
                                <span class="tbl-clamp" title="{{ $spm->uraian }}">{{ $spm->uraian }}</span>
                            @else
                                <span class="tbl-kosong">—</span>
                            @endif
                        </td>
                        {{-- Aksi berupa IKON, bukan tombol berteks: dua tombol
                             berteks "Edit"/"Hapus" tidak muat di kolom sesempit
                             ini, jadi teksnya membungkus dan barisnya melonjak
                             tinggi. Polanya disamakan dengan tabel NPD dan
                             SPM LS - satu bahasa aksi di seluruh aplikasi. --}}
                        <td class="mid">
                            @if (boleh_ubah())
                                <div class="spm-aksi">
                                    <a class="ic-btn" title="Edit" aria-label="Edit SPM" href="{{ route('spm.up-gu.edit', $spm) }}">
                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                    </a>
                                    {{-- Konfirmasi hapus muncul di halaman lewat
                                         <details>, bukan confirm() peramban -
                                         pola yang sama dengan hapus NPD. --}}
                                    <details class="spm-hapus">
                                        <summary class="ic-btn danger" title="Hapus" aria-label="Hapus SPM">
                                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </summary>
                                        <form method="POST" action="{{ route('spm.destroy', $spm) }}" class="spm-hapus-form">
                                            @csrf
                                            @method('DELETE')
                                            <div class="sub" style="margin:0 0 8px;">Hapus SPM <b>{{ $spm->nomor_dokumen }}</b>?</div>
                                            <button class="btn danger" type="submit">Ya, hapus</button>
                                        </form>
                                    </details>
                                </div>
                            @else
                                <span class="tbl-kosong">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data SPM UP/GU.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($spms->hasPages())
    <div class="pager">
        <div class="pager-info">Menampilkan {{ $spms->firstItem() }}&ndash;{{ $spms->lastItem() }} dari {{ $spms->total() }} data</div>
        <div class="pager-btns">
            <a class="pg-btn" href="{{ $spms->previousPageUrl() ?? '#' }}"@if (! $spms->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
            <a class="pg-btn" href="{{ $spms->nextPageUrl() ?? '#' }}"@if (! $spms->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
        </div>
    </div>
    @endif
</div>
@endsection
