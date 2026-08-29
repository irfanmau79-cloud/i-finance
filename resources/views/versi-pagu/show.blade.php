@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Rincian Versi Pagu')

@section('content')
<div class="dash-card">
    <h3>{{ $versi->nama }} — Tahun Anggaran {{ $versi->tahun }}</h3>

    <div class="sub">
        Status:
        @if ($versi->status === \App\Models\VersiPagu::STATUS_AKTIF)
            <span class="badge" style="background:#dcfce7;color:#166534;">BERLAKU</span>
        @elseif ($versi->status === \App\Models\VersiPagu::STATUS_DRAFT)
            <span class="badge" style="background:#fef3c7;color:#92400e;">DRAFT — belum berlaku</span>
        @else
            <span class="badge" style="background:#e5e7eb;color:#374151;">ARSIP</span>
        @endif
    </div>

    @if ($versi->keterangan)
        <div class="sub">{{ $versi->keterangan }}</div>
    @endif

    <div class="kpi-grid">
        <div class="dash-card"><h3>Rp {{ fmt_rupiah((float) $versi->total_pagu) }}</h3><div class="sub">Total Pagu Versi Ini</div></div>
        <div class="dash-card"><h3>{{ $versi->jumlah_baris }}</h3><div class="sub">Mata Anggaran</div></div>
        @if ($pembanding)
            <div class="dash-card">
                <h3>Rp {{ fmt_rupiah((float) $versi->total_pagu - (float) $pembanding->total_pagu) }}</h3>
                <div class="sub">Selisih terhadap {{ $pembanding->nama }}</div>
            </div>
        @endif
    </div>

    <div class="tbl-tools">
        <a href="{{ route('versi-pagu.index') }}" class="btn">Kembali ke Daftar Versi</a>
        @if ($versi->status !== \App\Models\VersiPagu::STATUS_AKTIF)
            <form method="POST" action="{{ route('versi-pagu.aktifkan', $versi) }}"
                  onsubmit="return confirm('Berlakukan versi &quot;{{ $versi->nama }}&quot; sebagai pagu resmi?');">
                @csrf
                <button type="submit" class="btn prim">Aktifkan Versi Ini</button>
            </form>
        @endif
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:16px;overflow-x:auto;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Kode Sub Kegiatan</th>
                    <th>Sub Kegiatan</th>
                    <th>Kode Rekening</th>
                    <th>Rekening</th>
                    <th>Tagging</th>
                    <th>Status</th>
                    @if ($pembanding)
                        <th class="num">{{ $pembanding->nama }}</th>
                        <th class="num">Selisih</th>
                    @endif
                    <th class="num">Pagu Versi Ini</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $d)
                    @php
                        $m = $d->masterAnggaran;
                        $nilaiPembanding = $pembanding ? (float) ($paguPembanding[$d->master_anggaran_id] ?? 0) : null;
                        $selisih = $nilaiPembanding !== null ? (float) $d->pagu - $nilaiPembanding : null;
                    @endphp
                    <tr>
                        <td>{{ $m?->kode_sub_kegiatan ?? '—' }}</td>
                        <td>{{ $m?->sub_kegiatan ?? '—' }}</td>
                        <td>{{ $m?->kode_rekening ?? '—' }}</td>
                        <td>{{ $m?->rekening ?? '—' }}</td>
                        <td>{{ $m?->tagging?->nama ?? '—' }}</td>
                        <td>{{ $d->aktif ? 'Aktif' : 'Non Aktif' }}</td>
                        @if ($pembanding)
                            <td class="num">Rp {{ fmt_rupiah($nilaiPembanding) }}</td>
                            <td class="num" style="color:{{ $selisih > 0 ? 'var(--ok)' : ($selisih < 0 ? '#b91c1c' : 'inherit') }};">
                                {{ $selisih > 0 ? '+' : '' }}Rp {{ fmt_rupiah($selisih) }}
                            </td>
                        @endif
                        <td class="num">Rp {{ fmt_rupiah((float) $d->pagu) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $pembanding ? 9 : 7 }}" style="text-align:center;color:var(--mut);padding:20px;">
                            Versi ini tidak punya baris mata anggaran.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($baris->hasPages())
    <div class="pager">
        <div class="pager-info">Menampilkan {{ $baris->firstItem() }}&ndash;{{ $baris->lastItem() }} dari {{ $baris->total() }} mata anggaran</div>
        <div class="pager-btns">
            <a class="pg-btn" href="{{ $baris->previousPageUrl() ?? '#' }}"@if (! $baris->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
            <a class="pg-btn" href="{{ $baris->nextPageUrl() ?? '#' }}"@if (! $baris->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
        </div>
    </div>
    @endif
</div>
@endsection
