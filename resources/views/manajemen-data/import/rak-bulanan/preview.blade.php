@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Preview Import RAK Bulanan')

@php($labelBulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agt',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'])

@section('content')
<div class="dash-card">
    <h3>Preview Import RAK Bulanan {{ $import->tahun }}</h3>
    <div class="sub">File: {{ $import->nama_file }}</div>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($import->status === \App\Models\RakBulananImport::STATUS_COMMITTED)
        <div class="sub" style="color:var(--ok);font-weight:700;">
            Sudah dikonfirmasi dan disimpan pada {{ $import->committed_at?->format('d-m-Y H:i:s') }}.
        </div>
    @elseif ($import->kedaluwarsa())
        <div class="err-box" style="display:block;">Sesi preview ini sudah kedaluwarsa. Silakan upload ulang.</div>
    @endif

    <div class="kpi-grid" style="margin-top:16px;">
        <div class="dash-card"><h3>{{ $import->total_baris }}</h3><div class="sub">Total Baris (per bulan)</div></div>
        <div class="dash-card"><h3 style="color:var(--ok);">{{ $import->jumlah_baru }}</h3><div class="sub">Baru</div></div>
        <div class="dash-card"><h3 style="color:var(--navy);">{{ $import->jumlah_update }}</h3><div class="sub">Update</div></div>
        <div class="dash-card"><h3 style="color:#b91c1c;">{{ $import->jumlah_ditolak }}</h3><div class="sub">Ditolak</div></div>
    </div>

    @if ($import->status === \App\Models\RakBulananImport::STATUS_STAGED && ! $import->kedaluwarsa())
        <div class="nav" style="margin-top:8px;">
            <form method="POST" action="{{ route('manajemen-data.import.rak-bulanan.batalkan', $import) }}" onsubmit="return confirm('Batalkan staging import ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn">Batalkan</button>
            </form>
            <form method="POST" action="{{ route('manajemen-data.import.rak-bulanan.konfirmasi', $import) }}" onsubmit="return confirm('Simpan {{ $import->jumlah_baru + $import->jumlah_update }} baris (baru + update) ke RAK Bulanan {{ $import->tahun }}? Baris yang ditolak tidak akan disimpan.');">
                @csrf
                <button type="submit" class="btn prim">Konfirmasi Simpan</button>
            </form>
        </div>
    @endif

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:16px;overflow-x:auto;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Baris</th>
                    <th>Bulan</th>
                    <th>Aksi</th>
                    <th>Mata Anggaran</th>
                    <th>Target</th>
                    <th>Alasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td>{{ $b->nomor_baris }}</td>
                        <td>{{ $labelBulan[$b->bulan] ?? $b->bulan }}</td>
                        <td>
                            @if ($b->aksi === 'baru')
                                <span class="badge" style="background:#dcfce7;color:#166534;">Baru</span>
                            @elseif ($b->aksi === 'update')
                                <span class="badge" style="background:#dbeafe;color:#1e3a8a;">Update</span>
                            @else
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Ditolak</span>
                            @endif
                        </td>
                        <td>{{ $b->kode_rekening ?? '—' }}{{ $b->tagging_nama ? ' ('.$b->tagging_nama.')' : '' }}</td>
                        <td>{{ $b->target !== null ? 'Rp '.fmt_rupiah($b->target) : '—' }}</td>
                        <td>{{ $b->alasan ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--mut);padding:20px;">Tidak ada baris.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($baris->hasPages())
    <div class="pager">
        <div class="pager-info">Menampilkan {{ $baris->firstItem() }}&ndash;{{ $baris->lastItem() }} dari {{ $baris->total() }} baris</div>
        <div class="pager-btns">
            <a class="pg-btn" href="{{ $baris->previousPageUrl() ?? '#' }}"@if (! $baris->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
            <a class="pg-btn" href="{{ $baris->nextPageUrl() ?? '#' }}"@if (! $baris->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
        </div>
    </div>
    @endif
</div>
@endsection
