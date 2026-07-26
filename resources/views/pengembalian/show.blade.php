@extends('layouts.app')

@section('activeNav', 'pengembalian')
@section('title', 'Detail Pengembalian')

@section('content')
<div class="dash-card">
    <h3>Detail Pengembalian &mdash; {{ $labelDokumen }}</h3>
    <div class="sub">{{ $pengembalian->dokumen_tipe === \App\Models\Pengembalian::TIPE_NPD ? 'NPD' : 'SPM LS' }}</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="rev">
        <div class="grp">
            <div class="gt">Informasi Umum</div>
            <div class="li"><span class="k">Status</span><span class="v"><span class="badge {{ $pengembalian->status === \App\Models\Pengembalian::STATUS_DISETUJUI ? 'st-selesai' : 'st-npd' }}">{{ $pengembalian->status === \App\Models\Pengembalian::STATUS_DISETUJUI ? 'Disetujui' : 'Draft' }}</span></span></div>
            <div class="li"><span class="k">Dokumen Sumber</span><span class="v">{{ $pengembalian->dokumen_tipe === \App\Models\Pengembalian::TIPE_NPD ? 'NPD' : 'SPM LS' }} &mdash; {{ $labelDokumen }}</span></div>
            <div class="li"><span class="k">Tanggal Pengembalian</span><span class="v">{{ $pengembalian->tanggal_pengembalian->format('d-m-Y') }}</span></div>
            <div class="li"><span class="k">Total Nominal</span><span class="v">Rp {{ number_format($pengembalian->totalNominal(), 2, ',', '.') }}</span></div>
            <div class="li"><span class="k">Dibuat oleh</span><span class="v">{{ $pengembalian->dibuatOleh?->nama ?? '—' }}</span></div>
            @if ($pengembalian->status === \App\Models\Pengembalian::STATUS_DISETUJUI)
                <div class="li"><span class="k">Disetujui oleh</span><span class="v">{{ $pengembalian->disetujuiOleh?->nama ?? '—' }}</span></div>
                <div class="li"><span class="k">Disetujui pada</span><span class="v">{{ $pengembalian->disetujui_at?->format('d-m-Y H:i') }}</span></div>
            @endif
            @if ($pengembalian->keterangan)
                <div class="li"><span class="k">Keterangan</span><span class="v">{{ $pengembalian->keterangan }}</span></div>
            @endif
            <div class="li">
                <span class="k">Dokumen Pendukung</span>
                <span class="v">
                    @if ($pengembalian->dokumen_pendukung)
                        <a href="{{ route('pengembalian.dokumen-pendukung', $pengembalian) }}">Unduh</a>
                    @else
                        Belum diunggah
                    @endif
                </span>
            </div>
        </div>

        <div class="grp">
            <div class="gt">Breakdown Mata Anggaran ({{ $pengembalian->detail->count() }})</div>
            @forelse ($pengembalian->detail as $baris)
                <div class="li">
                    <span class="k">{{ $baris->masterAnggaran?->kode_rekening }} &mdash; {{ $baris->masterAnggaran?->sub_kegiatan }}</span>
                    <span class="v">Rp {{ number_format((float) $baris->nominal, 2, ',', '.') }}</span>
                </div>
            @empty
                <div class="li"><span class="v">Belum ada baris.</span></div>
            @endforelse
        </div>
    </div>

    <div class="nav">
        <a class="btn" href="{{ route('pengembalian.index') }}">&larr; Kembali ke Daftar Pengembalian</a>
    </div>
</div>
@endsection
