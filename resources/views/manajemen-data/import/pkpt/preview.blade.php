@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Preview Import Data PKPT')

@section('content')
<div class="dash-card">
    <h3>Preview Import Data PKPT</h3>
    <div class="sub">Berkas: {{ $import->nama_file }} &middot; Tahun Anggaran {{ $import->tahun }}</div>

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

    @if ($import->status === \App\Models\PkptImport::STATUS_COMMITTED)
        <div class="sub" style="color:var(--ok);font-weight:700;">
            Sudah dikonfirmasi dan disimpan pada {{ $import->committed_at?->format('d-m-Y H:i:s') }}.
        </div>
    @elseif ($import->kedaluwarsa())
        <div class="err-box" style="display:block;">Masa berlaku pemeriksaan berkas ini sudah habis. Silakan unggah ulang berkasnya.</div>
    @endif

    <div class="kpi-grid">
        <div class="dash-card"><h3>{{ $import->total_baris }}</h3><div class="sub">Total Baris</div></div>
        <div class="dash-card"><h3 style="color:var(--ok);">{{ $import->jumlah_baru }}</h3><div class="sub">Kegiatan Baru</div></div>
        <div class="dash-card"><h3 style="color:var(--tegas);">{{ $import->jumlah_update }}</h3><div class="sub">Diperbarui</div></div>
        <div class="dash-card"><h3 style="color:var(--err-teks);">{{ $import->jumlah_ditolak }}</h3><div class="sub">Ditolak</div></div>
    </div>

    @if ($import->status === \App\Models\PkptImport::STATUS_STAGED && ! $import->kedaluwarsa())
        <div class="nav" style="margin-top:8px;">
            <form method="POST" action="{{ route('manajemen-data.import.pkpt.batalkan', $import) }}" onsubmit="return confirm('Batalkan pemeriksaan berkas ini? Berkas perlu diunggah ulang bila ingin dilanjutkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn">Batalkan</button>
            </form>
            <form method="POST" action="{{ route('manajemen-data.import.pkpt.konfirmasi', $import) }}" onsubmit="return confirm('Simpan {{ $import->jumlah_baru + $import->jumlah_update }} baris (baru + update) ke Data PKPT {{ $import->tahun }}? Baris yang ditolak tidak akan disimpan.');">
                @csrf
                <button type="submit" class="btn prim">Konfirmasi Simpan</button>
            </form>
        </div>
    @endif

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:16px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Baris</th>
                    <th>Aksi</th>
                    <th>Nomor</th>
                    <th>Unit Kerja</th>
                    <th>Area</th>
                    <th>Jenis Kegiatan</th>
                    <th class="num">Estimasi</th>
                    <th class="num">Realisasi</th>
                    <th>Status</th>
                    <th>Alasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td>{{ $b->nomor_baris }}</td>
                        <td>
                            @if ($b->aksi === 'baru')
                                <span class="badge" style="background:var(--ok-bg);color:var(--ok-teks);">Baru</span>
                            @elseif ($b->aksi === 'update')
                                <span class="badge" style="background:var(--info-bg);color:var(--info);">Update</span>
                            @else
                                <span class="badge" style="background:var(--err-bg);color:var(--err-teks);">Ditolak</span>
                            @endif
                        </td>
                        <td>{{ $b->nomor ?: '—' }}</td>
                        <td>{{ $b->unit_kerja ?: '—' }}</td>
                        <td>{{ $b->area ?? '—' }}</td>
                        <td>{{ $b->jenis_kegiatan ?? '—' }}</td>
                        <td class="num">{{ fmt_rupiah($b->estimasi_anggaran) }}</td>
                        <td class="num">{{ fmt_rupiah($b->realisasi) }}</td>
                        <td>{{ $b->terlaksana ? 'Terlaksana' : 'Belum terlaksana' }}</td>
                        <td>{{ $b->alasan ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;color:var(--mut);padding:20px;">Tidak ada baris.</td>
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
