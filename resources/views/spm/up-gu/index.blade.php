@extends('layouts.app')

@section('activeNav', 'spm-upgu')
@section('title', 'Realisasi SP2D UP/GU/TU')

@section('content')
<div class="dash-card wf-card">
    <h3>Data Realisasi SP2D UP/GU/TU</h3>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if (boleh_ubah())
    <div class="tbl-tools">
        <a href="{{ route('spm.up-gu.create') }}" class="btn prim" style="white-space:nowrap;">Tambah Realisasi SP2D UP/GU/TU</a>
    </div>
    @endif

    <form method="GET" action="{{ route('spm.up-gu.index') }}" class="tbl-tools">
        <input type="text" name="cari" placeholder="Cari nomor SPM, penerima, atau uraian..." value="{{ request('cari') }}" style="min-width:280px;">
        <button type="submit" class="btn prim" style="white-space:nowrap;">Cari</button>
        @if (request()->hasAny(['cari']))
            <a href="{{ route('spm.up-gu.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Tanggal SPM</th>
                    <th>Nomor SPM</th>
                    <th>Tanggal SP2D</th>
                    <th>Nomor SP2D</th>
                    <th>Nominal</th>
                    <th>Uraian</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spms as $spm)
                    <tr>
                        <td>{{ $spm->tanggal_dokumen->format('d-m-Y') }}</td>
                        <td>{{ $spm->nomor_dokumen }}</td>
                        <td>{{ $spm->tanggal_sp2d?->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $spm->nomor_sp2d ?? '—' }}</td>
                        <td>Rp {{ number_format((float) $spm->nominal, 2, ',', '.') }}</td>
                        <td>{{ $spm->uraian ?? '—' }}</td>
                        <td style="display:flex;gap:6px;">
                            @if (boleh_ubah())
                            <a class="btn" href="{{ route('spm.up-gu.edit', $spm) }}">Edit</a>
                            <form method="POST" action="{{ route('spm.destroy', $spm) }}" onsubmit="return confirm('Hapus SPM {{ $spm->nomor_dokumen }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">Hapus</button>
                            </form>
                            @else
                            <span class="sub">Lihat saja</span>
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
