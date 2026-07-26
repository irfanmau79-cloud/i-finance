@extends('layouts.app')

@section('activeNav', 'simulasi-pergeseran')
@section('title', 'Simulasi Pergeseran/Perubahan Anggaran')

@section('content')
<div class="dash-card wf-card">
    <h3>Simulasi Pergeseran/Perubahan Anggaran</h3>
    <div class="sub">Coba geser/ubah Pagu per mata anggaran secara what-if, tanpa pernah mengubah data Master Anggaran asli. Setiap percobaan bisa disimpan dengan nama sendiri, lalu dibuka lagi atau diexport kapan saja.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('simulasi-anggaran.create') }}" class="btn prim" style="white-space:nowrap;">+ Buat Simulasi Baru</a>
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nama Simulasi</th>
                    <th>Dibuat oleh</th>
                    <th>Tanggal</th>
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
                            <a href="{{ route('simulasi-anggaran.show', $s) }}" style="font-weight:600;">{{ $s->nama }}</a>
                            @if ($s->keterangan)
                                <div class="pen-sub">{{ \Illuminate\Support\Str::limit($s->keterangan, 80) }}</div>
                            @endif
                        </td>
                        <td>{{ $s->user->nama ?? '-' }}</td>
                        <td>{{ $s->created_at->format('d-m-Y H:i') }}</td>
                        <td class="num">Rp {{ number_format((float) $s->total_pagu_eksisting, 2, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((float) $s->total_pagu_simulasi, 2, ',', '.') }}</td>
                        <td class="num" style="color:{{ (float) $s->total_selisih > 0 ? 'var(--ok)' : ((float) $s->total_selisih < 0 ? 'var(--err)' : 'inherit') }};font-weight:600;">
                            {{ (float) $s->total_selisih > 0 ? '+' : '' }}Rp {{ number_format((float) $s->total_selisih, 2, ',', '.') }}
                        </td>
                        <td style="text-align:center;">
                            <div class="aksi-wrap">
                                <a class="ic-btn" title="Buka Simulasi" href="{{ route('simulasi-anggaran.show', $s) }}"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                <a class="ic-btn" title="Export Excel" href="{{ route('simulasi-anggaran.export-excel', $s) }}"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></a>
                                <a class="ic-btn" title="Export PDF" href="{{ route('simulasi-anggaran.export-pdf', $s) }}" target="_blank"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="9" y1="11" x2="15" y2="11"/></svg></a>
                                <form method="POST" action="{{ route('simulasi-anggaran.destroy', $s) }}" style="display:inline;" onsubmit="return confirm('Hapus simulasi &quot;{{ $s->nama }}&quot;? Tindakan ini tidak bisa dibatalkan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ic-btn danger" title="Hapus Simulasi"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                </form>
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
