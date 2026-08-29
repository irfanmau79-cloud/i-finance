@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Preview Import Pagu / Master Anggaran')

@section('content')
<div class="dash-card">
    <h3>Preview Import Pagu / Master Anggaran — Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</h3>
    <div class="sub">Berkas: {{ $import->nama_file }}</div>
    <div class="sub">Akan disimpan sebagai versi: <strong>{{ $import->versi_nama }}</strong>@if ($import->versi_keterangan) — {{ $import->versi_keterangan }}@endif</div>

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

    @if ($import->status === \App\Models\MasterAnggaranImport::STATUS_COMMITTED)
        <div class="sub" style="color:var(--ok);font-weight:700;">
            Sudah dikonfirmasi pada {{ $import->committed_at?->format('d-m-Y H:i:s') }}
            @if ($import->versi_pagu_id)
                — <a href="{{ route('versi-pagu.show', $import->versi_pagu_id) }}">lihat versi &ldquo;{{ $import->versi_nama }}&rdquo;</a>.
            @endif
        </div>
    @elseif ($import->kedaluwarsa())
        <div class="err-box" style="display:block;">Masa berlaku pemeriksaan berkas ini sudah habis. Silakan unggah ulang berkasnya.</div>
    @endif

    <div class="kpi-grid">
        <div class="dash-card"><h3>{{ $import->total_baris }}</h3><div class="sub">Baris di File</div></div>
        <div class="dash-card"><h3 style="color:var(--ok);">{{ $import->jumlah_baru }}</h3><div class="sub">Mata Anggaran Baru</div></div>
        <div class="dash-card"><h3 style="color:var(--navy);">{{ $import->jumlah_update }}</h3><div class="sub">Diperbarui</div></div>
        <div class="dash-card"><h3 style="color:#92400e;">{{ $import->jumlah_dinolkan }}</h3><div class="sub">Dinolkan</div></div>
        <div class="dash-card"><h3 style="color:#b91c1c;">{{ $import->jumlah_ditolak }}</h3><div class="sub">Ditolak</div></div>
    </div>

    @if ($import->jumlah_dinolkan > 0)
        <div class="sub" style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px;">
            <strong>{{ $import->jumlah_dinolkan }} mata anggaran</strong> ada di data sekarang tapi tidak dicantumkan di file ini.
            Pagunya akan menjadi 0 dan mata anggarannya dinonaktifkan <em>saat versi ini diaktifkan</em>.
            Kalau itu tidak disengaja, batalkan dan lengkapi filenya.
        </div>
    @endif

    @if ($import->status === \App\Models\MasterAnggaranImport::STATUS_STAGED && ! $import->kedaluwarsa())
        <div class="nav" style="margin-top:8px;">
            <form method="POST" action="{{ route('manajemen-data.import.master-anggaran.batalkan', $import) }}" onsubmit="return confirm('Batalkan pemeriksaan berkas ini? Berkas perlu diunggah ulang bila ingin dilanjutkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn">Batalkan</button>
            </form>
            <form method="POST" action="{{ route('manajemen-data.import.master-anggaran.konfirmasi', $import) }}" onsubmit="return confirm('Simpan sebagai versi pagu draft &quot;{{ $import->versi_nama }}&quot;? Pagu yang berlaku BELUM berubah sampai versi ini diaktifkan.');">
                @csrf
                <button type="submit" class="btn prim">Konfirmasi Simpan sebagai Draft</button>
            </form>
        </div>
        <div class="sub">Baris yang ditolak tidak ikut disimpan ke dalam versi.</div>
    @endif

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:16px;overflow-x:auto;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Baris</th>
                    <th>Aksi</th>
                    <th>Kode Sub Kegiatan</th>
                    <th>Sub Kegiatan</th>
                    <th>Kode Rekening</th>
                    <th>Rekening</th>
                    <th>Tagging</th>
                    <th>Status</th>
                    <th class="num">Pagu Lama</th>
                    <th class="num">Pagu Versi Ini</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td>{{ $b->nomor_baris > 0 ? $b->nomor_baris : '—' }}</td>
                        <td>
                            @if ($b->aksi === \App\Models\MasterAnggaranImportRow::AKSI_BARU)
                                <span class="badge" style="background:#dcfce7;color:#166534;">Baru</span>
                            @elseif ($b->aksi === \App\Models\MasterAnggaranImportRow::AKSI_UPDATE)
                                <span class="badge" style="background:#dbeafe;color:#1e3a8a;">Update</span>
                            @elseif ($b->aksi === \App\Models\MasterAnggaranImportRow::AKSI_DINOLKAN)
                                <span class="badge" style="background:#fef3c7;color:#92400e;">Dinolkan</span>
                            @else
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Ditolak</span>
                            @endif
                        </td>
                        <td>{{ $b->kode_sub_kegiatan ?? '—' }}</td>
                        <td>{{ $b->sub_kegiatan ?? '—' }}</td>
                        <td>{{ $b->kode_rekening ?? '—' }}</td>
                        <td>{{ $b->rekening ?? '—' }}</td>
                        <td>{{ $b->tagging_nama ?? '—' }}</td>
                        <td>{{ $b->aktif ? 'Aktif' : 'Non Aktif' }}</td>
                        <td class="num">{{ $b->pagu_lama !== null ? 'Rp '.fmt_rupiah($b->pagu_lama) : '—' }}</td>
                        <td class="num">{{ $b->pagu_baru !== null ? 'Rp '.fmt_rupiah($b->pagu_baru) : '—' }}</td>
                        <td>{{ $b->alasan ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center;color:var(--mut);padding:20px;">Tidak ada baris.</td>
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
