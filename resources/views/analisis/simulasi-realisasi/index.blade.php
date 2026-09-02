@extends('layouts.app')

@section('activeNav', 'simulasi-realisasi')
@section('title', 'Simulasi Realisasi')

@section('content')

<div class="page-head">
    <div>
        <div class="ph-crumb">Analisis / <b>Simulasi Realisasi</b></div>
        <div class="ph-title">Simulasi Realisasi</div>
    </div>
</div>

<div class="dash-card">
    <h3 style="margin:0;color:var(--tegas)">Daftar Simulasi</h3>
    <div class="sub">
        Memperkirakan capaian anggaran sampai akhir tahun. Tiap mata anggaran diisi rencana belanja
        bernama &mdash; misalnya pada tagging On Call: &ldquo;Perjalanan dinas ke Cirebon&rdquo; lalu
        &ldquo;Rapat koordinasi&rdquo; &mdash; lalu proyeksinya dihitung dari realisasi berjalan
        ditambah seluruh rencana itu.
    </div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        @if (boleh_ubah())
        <a href="{{ route('simulasi-realisasi.create') }}" class="btn prim" style="white-space:nowrap;">+ Buat Simulasi Baru</a>
        @endif
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi tbl-fixed">
            <colgroup>
                <col style="width:26%;"><col style="width:14%;"><col style="width:14%;">
                <col style="width:12%;"><col style="width:13%;"><col style="width:11%;"><col style="width:10%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Nama Simulasi</th>
                    <th class="num">Pagu</th>
                    <th class="num">Proyeksi</th>
                    <th class="num">% thd Pagu</th>
                    <th>Dibuat Oleh</th>
                    <th>Terakhir Diubah</th>
                    <th class="mid">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($simulasi as $s)
                    @php
                        $pagu = (float) $s->total_pagu;
                        $persen = $pagu > 0 ? ((float) $s->total_proyeksi / $pagu) * 100 : 0.0;
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('simulasi-realisasi.show', $s) }}" style="font-weight:600;">{{ $s->nama }}</a>
                            @if ($s->keterangan)
                                <span class="sub tbl-clamp" title="{{ $s->keterangan }}">{{ $s->keterangan }}</span>
                            @endif
                        </td>
                        <td class="num">Rp {{ fmt_rupiah($s->total_pagu) }}</td>
                        <td class="num">Rp {{ fmt_rupiah($s->total_proyeksi) }}</td>
                        <td class="num">{{ number_format($persen, 2, ',', '.') }}%</td>
                        <td>{{ $s->user?->nama ?? '—' }}</td>
                        <td>{{ $s->updated_at?->format('d-m-Y H:i') ?? '—' }}</td>
                        <td class="mid">
                            <div class="aksi-wrap aksi-2x2">
                                <a class="ic-btn" title="Buka Simulasi" href="{{ route('simulasi-realisasi.show', $s) }}"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                <a class="ic-btn" title="Export Excel" href="{{ route('simulasi-realisasi.export-excel', $s) }}"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></a>
                                <a class="ic-btn" title="Export PDF" href="{{ route('simulasi-realisasi.export-pdf', $s) }}" target="_blank"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="9" y1="11" x2="15" y2="11"/></svg></a>
                                @if (boleh_ubah())
                                <form method="POST" action="{{ route('simulasi-realisasi.destroy', $s) }}" onsubmit="return confirm('Hapus simulasi &quot;{{ $s->nama }}&quot;? Tindakan ini tidak bisa dibatalkan.');">
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
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:26px;">
                            Belum ada simulasi realisasi. Buat yang pertama untuk memproyeksikan capaian akhir tahun.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $simulasi->links() }}
</div>
@endsection
