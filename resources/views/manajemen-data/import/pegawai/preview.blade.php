@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Preview Import Pegawai')

@section('content')
<div class="dash-card">
    <h3>Preview Import Pegawai</h3>
    <div class="sub">Berkas: {{ $import->nama_file }}</div>

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

    @if ($import->status === \App\Models\PegawaiImport::STATUS_COMMITTED)
        <div class="sub" style="color:var(--ok);font-weight:700;">
            Sudah dikonfirmasi dan disimpan pada {{ $import->committed_at?->format('d-m-Y H:i:s') }}.
        </div>
    @elseif ($import->kedaluwarsa())
        <div class="err-box" style="display:block;">Masa berlaku pemeriksaan berkas ini sudah habis. Silakan unggah ulang berkasnya.</div>
    @endif

    <div class="kpi-grid">
        <div class="dash-card"><h3>{{ $import->total_baris }}</h3><div class="sub">Total Baris</div></div>
        <div class="dash-card"><h3 style="color:var(--ok);">{{ $import->jumlah_baru }}</h3><div class="sub">Data Baru</div></div>
        <div class="dash-card"><h3 style="color:var(--navy);">{{ $import->jumlah_update }}</h3><div class="sub">Diperbarui</div></div>
        <div class="dash-card"><h3 style="color:#b91c1c;">{{ $import->jumlah_ditolak }}</h3><div class="sub">Ditolak</div></div>
    </div>

    @if ($import->status === \App\Models\PegawaiImport::STATUS_STAGED && ! $import->kedaluwarsa())
        <div class="nav" style="margin-top:8px;">
            <form method="POST" action="{{ route('manajemen-data.import.pegawai.batalkan', $import) }}" onsubmit="return confirm('Batalkan pemeriksaan berkas ini? Berkas perlu diunggah ulang bila ingin dilanjutkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn">Batalkan</button>
            </form>
            <form method="POST" action="{{ route('manajemen-data.import.pegawai.konfirmasi', $import) }}" onsubmit="return confirm('Simpan {{ $import->jumlah_baru + $import->jumlah_update }} baris (baru + update) ke Pegawai? Baris yang ditolak tidak akan disimpan.');">
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
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th>Bidang</th>
                    <th>Alasan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td>{{ $b->nomor_baris }}</td>
                        <td>
                            @if ($b->aksi === 'baru')
                                <span class="badge" style="background:#dcfce7;color:#166534;">Baru</span>
                            @elseif ($b->aksi === 'update')
                                <span class="badge" style="background:#dbeafe;color:#1e3a8a;">Update</span>
                            @else
                                <span class="badge" style="background:#fee2e2;color:#991b1b;">Ditolak</span>
                            @endif
                        </td>
                        <td>{{ $b->nama }}</td>
                        <td>{{ $b->nip }}</td>
                        <td>{{ $b->jabatan }}</td>
                        <td>{{ $b->bidang }}</td>
                        <td>{{ $b->alasan ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Tidak ada baris.</td>
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
