@extends('layouts.app')

@section('activeNav', 'simulasi-pergeseran')
@section('title', 'Simulasi Pergeseran/Perubahan Anggaran')

@section('content')
<style>
    /* Nama simulasi tetap bisa diklik, tanpa garis bawah pranala. */
    .sim-nama-tautan{font-weight:600;color:var(--tegas);text-decoration:none;}
    .sim-nama-tautan:hover,.sim-nama-tautan:focus{text-decoration:none;color:var(--tegas);}

    /* Empat aksi (buka, Excel, PDF, hapus) disusun 2x2. Grid bawaan
       .aksi-wrap tiga kolom, sehingga empat tombol pecah jadi 3 + 1. */
    .aksi-wrap.aksi-2x2{grid-template-columns:repeat(2,30px);width:64px;}
    .aksi-wrap.aksi-2x2 form{display:flex;justify-content:center;}

    /* Lebar kolom dikunci proporsinya supaya tiga kolom nominal sama besar
       dan tidak saling menggeser saat angkanya berbeda panjang. min-width
       menjaga kolom nominal tetap muat sebelum tabelnya digulir mendatar. */
    table.sim-tabel{table-layout:fixed;min-width:1040px;}
    table.sim-tabel col.c-nama{width:26%}
    table.sim-tabel col.c-pembuat{width:12%}
    table.sim-tabel col.c-tanggal{width:11%}
    table.sim-tabel col.c-uang{width:14%}
    table.sim-tabel col.c-aksi{width:9%}
    table.sim-tabel td,table.sim-tabel th{overflow-wrap:anywhere;}
    table.sim-tabel td.num,table.sim-tabel th.num{overflow-wrap:normal;}
    /* Judul kolom nominal ikut rata kanan agar sejajar angkanya.
       Sengaja dibatasi pada tabel ini - aturan dasar `td.num` saja
       (judul rata kiri) dipertahankan di tabel lain karena menyamai GAS. */
    table.sim-tabel th.num{text-align:right;}
</style>

<div class="dash-card wf-card">
    <h3>Simulasi Pergeseran/Perubahan Anggaran</h3>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        @if (boleh_ubah())
        <a href="{{ route('simulasi-anggaran.create') }}" class="btn prim" style="white-space:nowrap;">+ Buat Simulasi Baru</a>
        @endif
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi sim-tabel">
            <colgroup>
                <col class="c-nama">
                <col class="c-pembuat">
                <col class="c-tanggal">
                <col class="c-uang">
                <col class="c-uang">
                <col class="c-uang">
                <col class="c-aksi">
            </colgroup>
            <thead>
                <tr>
                    <th>Nama Simulasi</th>
                    <th>Dibuat oleh</th>
                    <th>Terakhir Diubah</th>
                    <th class="num">Pagu Eksisting</th>
                    <th class="num">Pagu Simulasi</th>
                    <th class="num">Selisih</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($simulasi as $s)
                    <tr>
                        <td>
                            <a href="{{ route('simulasi-anggaran.show', $s) }}" class="sim-nama-tautan">{{ $s->nama }}</a>
                            @if ($s->keterangan)
                                <div class="pen-sub">{{ \Illuminate\Support\Str::limit($s->keterangan, 80) }}</div>
                            @endif
                        </td>
                        <td>{{ $s->user->nama ?? '-' }}</td>
                        <td>{{ $s->updated_at->format('d-m-Y H:i') }}</td>
                        <td class="num">Rp {{ number_format((float) $s->total_pagu_eksisting, 2, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((float) $s->total_pagu_simulasi, 2, ',', '.') }}</td>
                        <td class="num" style="color:{{ (float) $s->total_selisih > 0 ? 'var(--ok)' : ((float) $s->total_selisih < 0 ? 'var(--err)' : 'inherit') }};font-weight:600;">
                            {{ (float) $s->total_selisih > 0 ? '+' : '' }}Rp {{ number_format((float) $s->total_selisih, 2, ',', '.') }}
                        </td>
                        <td style="text-align:center;">
                            <div class="aksi-wrap aksi-2x2">
                                <a class="ic-btn" title="Buka Simulasi" href="{{ route('simulasi-anggaran.show', $s) }}"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                <a class="ic-btn" title="Export Excel" href="{{ route('simulasi-anggaran.export-excel', $s) }}"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></a>
                                <a class="ic-btn" title="Export PDF" href="{{ route('simulasi-anggaran.export-pdf', $s) }}" target="_blank"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="9" y1="11" x2="15" y2="11"/></svg></a>
                                @if (boleh_ubah())
                                <form method="POST" action="{{ route('simulasi-anggaran.destroy', $s) }}" onsubmit="return confirm('Hapus simulasi &quot;{{ $s->nama }}&quot;? Tindakan ini tidak bisa dibatalkan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ic-btn danger" title="Hapus Simulasi"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada simulasi tersimpan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($simulasi->hasPages())
        <div class="pager">
            <div class="pager-info">Menampilkan {{ $simulasi->firstItem() }}&ndash;{{ $simulasi->lastItem() }} dari {{ $simulasi->total() }} simulasi</div>
            <div class="pager-btns">
                <a class="pg-btn" href="{{ $simulasi->previousPageUrl() ?? '#' }}"@if (! $simulasi->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
                <a class="pg-btn" href="{{ $simulasi->nextPageUrl() ?? '#' }}"@if (! $simulasi->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
            </div>
        </div>
    @endif
</div>
@endsection
