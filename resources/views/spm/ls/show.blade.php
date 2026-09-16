@extends('layouts.app')

@section('activeNav', 'spm-ls')
@section('title', 'Detail Realisasi SP2D LS')

@section('content')
@php
    $rp = fn ($n) => 'Rp '.fmt_rupiah((float) $n);
    $pajak = (float) $spm->ppn + (float) $spm->pph1 + (float) $spm->pph2;
@endphp

<div class="page-head">
    <div>
        <div class="ph-crumb"><a href="{{ route('spm.ls.index') }}" style="color:inherit;">Data Realisasi SP2D LS</a> / <b>Detail</b></div>
        <div class="ph-title">{{ $spm->nomor_dokumen }}</div>
    </div>
</div>

<div class="dash-card">
    <div class="spm-kepala">
        <h3 style="margin:0;">Rincian SPM LS</h3>
        @if ($spm->divalidasi())
            <span class="spm-lencana" title="Divalidasi {{ $spm->divalidasi_at->format('d-m-Y H:i') }}">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Tervalidasi
            </span>
        @else
            <span class="spm-lencana belum">Belum divalidasi</span>
        @endif
    </div>

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

    <div class="spm-grid">
        <div class="spm-item"><span class="k">Tanggal SPM</span><span class="v">{{ $spm->tanggal_dokumen->translatedFormat('d F Y') }}</span></div>
        <div class="spm-item"><span class="k">Nomor SPM</span><span class="v">{{ $spm->nomor_dokumen }}</span></div>
        <div class="spm-item"><span class="k">Tanggal SP2D</span><span class="v">{{ $spm->tanggal_sp2d?->translatedFormat('d F Y') ?? '—' }}</span></div>
        <div class="spm-item"><span class="k">Nomor SP2D</span><span class="v">{{ $spm->nomor_sp2d ?? '—' }}</span></div>
        <div class="spm-item"><span class="k">Penerima</span><span class="v">{{ $spm->penerima ?? '—' }}</span></div>
        <div class="spm-item"><span class="k">Bank / Rekening</span><span class="v">{{ trim(($spm->bank_tujuan ?? '').' · '.($spm->nomor_rekening ?? ''), ' ·') ?: '—' }}</span></div>
        <div class="spm-item lebar"><span class="k">Uraian</span><span class="v">{{ $spm->uraian ?? '—' }}</span></div>
    </div>

    <h3 style="margin-top:22px;">Mata Anggaran ({{ $spm->detail->count() }})</h3>
    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi tbl-fixed">
            <colgroup><col style="width:18%;"><col style="width:30%;"><col style="width:30%;"><col style="width:22%;"></colgroup>
            <thead>
                <tr>
                    <th>Kodering</th>
                    <th>Uraian Rekening</th>
                    <th>Sub Kegiatan</th>
                    <th class="num">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spm->detail as $baris)
                    <tr>
                        <td>{{ $baris->masterAnggaran?->kode_rekening_bersih ?? '—' }}</td>
                        <td>{{ $baris->masterAnggaran?->uraian_rekening ?? '—' }}</td>
                        <td>
                            {{ $baris->masterAnggaran?->subKegiatanNormal() ?? '—' }}
                            @if ($baris->masterAnggaran?->tagging)
                                <span class="spm-sub">{{ $baris->masterAnggaran->tagging->nama }}</span>
                            @endif
                        </td>
                        <td class="num">{{ fmt_rupiah((float) $baris->nominal) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--mut);padding:18px;">Tidak ada baris mata anggaran.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Jumlah</th>
                    <th class="num">{{ fmt_rupiah($spm->totalNominal()) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- PPN/PPh SPM LS berlaku untuk SELURUH dokumen, tidak dipecah per mata
         anggaran - karena itu ditampilkan di luar tabel, bukan sebagai kolom
         di dalamnya. Realisasi per mata anggaran tetap dihitung dari nominal
         bruto tiap baris (lihat MasterAnggaran::realisasiLs). --}}
    <h3 style="margin-top:22px;">Pajak Dokumen</h3>
    <div class="spm-grid">
        <div class="spm-item"><span class="k">PPN</span><span class="v">{{ $rp($spm->ppn) }}</span></div>
        <div class="spm-item"><span class="k">PPh 1 {{ $spm->jenis_pph1 ? '('.$spm->jenis_pph1.')' : '' }}</span><span class="v">{{ $rp($spm->pph1) }}</span></div>
        <div class="spm-item"><span class="k">PPh 2 {{ $spm->jenis_pph2 ? '('.$spm->jenis_pph2.')' : '' }}</span><span class="v">{{ $rp($spm->pph2) }}</span></div>
        <div class="spm-item"><span class="k">Total Pajak</span><span class="v">{{ $rp($pajak) }}</span></div>
    </div>

    <h3 style="margin-top:22px;">Jejak</h3>
    <div class="spm-grid">
        <div class="spm-item"><span class="k">Dibuat oleh</span><span class="v">{{ $spm->dibuatOleh?->nama ?? '—' }}</span></div>
        <div class="spm-item"><span class="k">Dibuat pada</span><span class="v">{{ $spm->created_at?->format('d-m-Y H:i') ?? '—' }}</span></div>
        <div class="spm-item"><span class="k">Divalidasi oleh</span><span class="v">{{ $spm->divalidasiOleh?->nama ?? '—' }}</span></div>
        <div class="spm-item"><span class="k">Divalidasi pada</span><span class="v">{{ $spm->divalidasi_at?->format('d-m-Y H:i') ?? '—' }}</span></div>
    </div>

    <div class="spm-kaki">
        <a class="btn" href="{{ route('spm.ls.index') }}">Kembali ke Daftar</a>

        @if (boleh_ubah())
            @if (! $spm->divalidasi())
                <a class="btn" href="{{ route('spm.ls.edit', $spm) }}">Edit</a>
                <form method="POST" action="{{ route('spm.validasi', $spm) }}">
                    @csrf
                    <button type="submit" class="btn prim">Validasi</button>
                </form>
            @else
                <a class="btn" href="{{ route('spm.ls.edit', $spm) }}">Edit</a>
                @if (auth()->user()->isSuperadmin())
                    {{-- Jalan keluar untuk klik keliru; hanya superadmin, supaya
                         kuncinya tetap berarti. --}}
                    <form method="POST" action="{{ route('spm.validasi.batal', $spm) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn danger">Batalkan Validasi</button>
                    </form>
                @endif
            @endif
        @endif
    </div>
</div>
@endsection
