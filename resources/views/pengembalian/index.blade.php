@extends('layouts.app')

@section('activeNav', 'pengembalian')
@section('title', 'Daftar Pengembalian')

@section('content')
<div class="dash-card wf-card">
    <h3>Daftar Pengembalian</h3>
    <div class="sub">Pengurang realisasi aktual dari NPD Selesai atau SPM LS. Data SP2D/NPD/SPM sumber tidak pernah diubah — hanya efektif setelah disetujui.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('pengembalian.create') }}" class="btn prim" style="white-space:nowrap;">+ Input Pengembalian</a>
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
                    <th>Tanggal</th>
                    <th>Dokumen Sumber</th>
                    <th>Mata Anggaran</th>
                    <th>Total Nominal</th>
                    <th>Status</th>
                    <th>Dibuat Oleh</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pengembalian as $p)
                    @php
                        $nomorDokumen = $p->dokumen_tipe === 'npd'
                            ? ($npdMap[$p->dokumen_id]->nomor_lengkap ?? '(Draft #'.$p->dokumen_id.')')
                            : ($spmMap[$p->dokumen_id]->nomor_dokumen ?? '#'.$p->dokumen_id);
                        $mataAnggaran = $p->detail->map(fn ($d) => $d->masterAnggaran?->kode_rekening)->filter()->implode(', ');
                    @endphp
                    <tr>
                        <td>{{ $p->tanggal_pengembalian->format('d-m-Y') }}</td>
                        <td>{{ $p->dokumen_tipe === 'npd' ? 'NPD' : 'SPM LS' }} — {{ $nomorDokumen }}</td>
                        <td>{{ $mataAnggaran ?: '—' }}</td>
                        <td>Rp {{ number_format($p->totalNominal(), 2, ',', '.') }}</td>
                        <td><span class="badge {{ $p->status === 'disetujui' ? 'st-selesai' : 'st-npd' }}">{{ $p->status === 'disetujui' ? 'Disetujui' : 'Draft' }}</span></td>
                        <td>{{ $p->dibuatOleh?->nama ?? '—' }}</td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            @if ($p->dokumen_pendukung)
                                <a class="btn" href="{{ route('pengembalian.dokumen-pendukung', $p) }}">Dokumen</a>
                            @endif
                            @if ($p->status === 'draft')
                                @if ($bolehSetujui)
                                    <form method="POST" action="{{ route('pengembalian.setujui', $p) }}" onsubmit="return confirm('Setujui pengembalian ini? Realisasi akan berubah setelah disetujui.');">
                                        @csrf
                                        <button type="submit" class="btn prim">Setujui</button>
                                    </form>
                                @endif
                                @if ($p->dibuat_oleh === auth()->id() || $bolehSetujui)
                                    <form method="POST" action="{{ route('pengembalian.destroy', $p) }}" onsubmit="return confirm('Hapus draft pengembalian ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn">Hapus</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data pengembalian.</td>
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
