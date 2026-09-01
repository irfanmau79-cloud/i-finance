@extends('layouts.app')

@section('activeNav', 'pengembalian')
@section('title', 'Daftar Pengembalian')

@section('content')
<div class="dash-card wf-card">
    <h3>Daftar Pengembalian</h3>
    <div class="sub">Pencatatan dana yang dikembalikan ke kas daerah. Nilainya mengurangi realisasi setelah disetujui Bendahara Pengeluaran; dokumen sumbernya tetap utuh.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        @if (boleh_ubah())
        <a href="{{ route('pengembalian.create') }}" class="btn prim" style="white-space:nowrap;">+ Input Pengembalian</a>
        @endif
    </div>

    <form method="GET" action="{{ route('pengembalian.index') }}" class="tbl-tools">
        <select name="status">
            <option value="">-- Semua Status --</option>
            <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
            <option value="disetujui" @selected($filters['status'] === 'disetujui')>Disetujui</option>
        </select>
        <select name="dokumen_tipe">
            <option value="">-- Semua Jenis Dokumen --</option>
            <option value="npd" @selected($filters['dokumen_tipe'] === 'npd')>NPD</option>
            <option value="spm_ls" @selected($filters['dokumen_tipe'] === 'spm_ls')>SPM LS</option>
        </select>
        <input type="date" name="dari" value="{{ $filters['dari'] }}" title="Dari tanggal">
        <input type="date" name="sampai" value="{{ $filters['sampai'] }}" title="Sampai tanggal">
        <input type="text" name="cari" placeholder="Cari nomor NPD/SPM..." value="{{ $filters['cari'] }}" style="min-width:220px;">
        <button type="submit" class="btn prim" style="white-space:nowrap;">Filter</button>
        @if (request()->hasAny(['status', 'dokumen_tipe', 'dari', 'sampai', 'cari']))
            <a href="{{ route('pengembalian.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Tanggal Pengembalian</th>
                    <th>Nomor Dokumen Sumber</th>
                    <th>Sub Kegiatan</th>
                    <th style="text-align:right;">Nominal</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pengembalian as $p)
                    @php
                        $nomorDokumen = $p->dokumen_tipe === 'npd'
                            ? ($npdMap[$p->dokumen_id]->nomor_lengkap ?? '(Draft #'.$p->dokumen_id.')')
                            : ($spmMap[$p->dokumen_id]->nomor_dokumen ?? '#'.$p->dokumen_id);
                        $subKegiatan = $p->detail->map(fn ($d) => $d->masterAnggaran?->sub_kegiatan_lengkap)->filter()->unique()->implode(', ');
                        $bolehKelola = $p->dibuat_oleh === auth()->id() || $bolehSetujui;
                    @endphp
                    <tr>
                        <td>{{ $p->tanggal_pengembalian->format('d-m-Y') }}</td>
                        <td>{{ $p->dokumen_tipe === 'npd' ? 'NPD' : 'SPM LS' }} &mdash; {{ $nomorDokumen }}</td>
                        <td>{{ $subKegiatan ?: '—' }}</td>
                        <td style="text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;">Rp {{ number_format($p->totalNominal(), 2, ',', '.') }}</td>
                        <td style="text-align:center;">
                            <div style="display:inline-flex;gap:6px;">
                                @if ($p->status === 'draft' && $bolehSetujui)
                                    <form method="POST" action="{{ route('pengembalian.setujui', $p) }}" onsubmit="return confirm('Setujui pengembalian ini? Realisasi akan berubah setelah disetujui.');">
                                        @csrf
                                        <button type="submit" class="ic-btn" title="Setujui"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></button>
                                    </form>
                                @endif
                                @if ($p->status === 'draft' && $bolehKelola)
                                    <a class="ic-btn" title="Edit" href="{{ route('pengembalian.edit', $p) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                                @endif
                                <a class="ic-btn" title="Lihat" href="{{ route('pengembalian.show', $p) }}"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                @if ($p->status === 'draft' && $bolehKelola)
                                    <form method="POST" action="{{ route('pengembalian.destroy', $p) }}" onsubmit="return confirm('Hapus draft pengembalian ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ic-btn danger" title="Hapus"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data pengembalian.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pengembalian->hasPages())
    <div class="pager">
        <div class="pager-info">Menampilkan {{ $pengembalian->firstItem() }}&ndash;{{ $pengembalian->lastItem() }} dari {{ $pengembalian->total() }} data</div>
        <div class="pager-btns">
            <a class="pg-btn" href="{{ $pengembalian->previousPageUrl() ?? '#' }}"@if (! $pengembalian->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
            <a class="pg-btn" href="{{ $pengembalian->nextPageUrl() ?? '#' }}"@if (! $pengembalian->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
        </div>
    </div>
    @endif
</div>
@endsection
