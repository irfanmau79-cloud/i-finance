@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Preview Import '.$meta['judul'])

@section('content')
<div class="dash-card">
    <h3>Preview Import {{ $meta['judul'] }}</h3>
    <div class="sub">Berkas: {{ $import->nama_file }} &middot; Tahun Anggaran {{ $import->tahun }}</div>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="form-grid-auto" style="margin-top:12px;">
        <div class="fg"><label class="fl">Total Baris</label><div class="ph-title" style="font-size:18px;">{{ $import->total_baris }}</div></div>
        <div class="fg"><label class="fl">Akan Ditambah</label><div class="ph-title" style="font-size:18px;">{{ $import->jumlah_baru }}</div></div>
        <div class="fg"><label class="fl">Akan Diperbarui</label><div class="ph-title" style="font-size:18px;">{{ $import->jumlah_update }}</div></div>
        <div class="fg"><label class="fl">Ditolak</label><div class="ph-title" style="font-size:18px;color:{{ $import->jumlah_ditolak ? 'var(--warn)' : 'inherit' }};">{{ $import->jumlah_ditolak }}</div></div>
    </div>

    @if ($import->status === $meta['model']::STATUS_COMMITTED)
        <div class="sumbar ok" style="margin-top:12px;"><span>Berkas ini sudah disimpan pada {{ $import->committed_at?->translatedFormat('d M Y H:i') }}.</span></div>
    @elseif (! $import->kedaluwarsa())
        <div style="display:flex;gap:10px;margin-top:14px;">
            <form method="POST" action="{{ route('manajemen-data.import.rincian.batalkan', [$jenis, $import]) }}"
                  onsubmit="return confirm('Batalkan pemeriksaan berkas ini?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn">Batalkan</button>
            </form>
            <form method="POST" action="{{ route('manajemen-data.import.rincian.konfirmasi', [$jenis, $import]) }}"
                  onsubmit="return confirm('Simpan {{ $import->jumlah_baru + $import->jumlah_update }} baris (baru + diperbarui)? Baris yang ditolak tidak disimpan.');">
                @csrf
                <button type="submit" class="btn prim">Konfirmasi Simpan</button>
            </form>
        </div>
    @endif

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:14px;">
        <table class="realisasi">
            <thead>
                <tr><th>Baris</th><th>Aksi</th><th>Isi</th><th>Alasan Ditolak</th></tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td>{{ $b->nomor_baris }}</td>
                        <td>
                            <span class="badge {{ $b->aksi === 'ditolak' ? 'st-dikembalikan' : ($b->aksi === 'baru' ? 'st-selesai' : 'st-npd') }}">
                                {{ ucfirst($b->aksi) }}
                            </span>
                        </td>
                        <td class="kol-uraian">
                            @foreach (($b->isi ?? []) as $kunci => $nilai)
                                @continue($nilai === null || $nilai === '' || $nilai === false)
                                <span style="margin-right:10px;"><span class="sub">{{ str_replace('_', ' ', $kunci) }}:</span> {{ is_bool($nilai) ? 'ya' : $nilai }}</span>
                            @endforeach
                        </td>
                        <td class="kol-uraian" style="color:var(--warn);">{{ $b->alasan ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--mut);padding:20px;">Tidak ada baris.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $baris->links() }}
</div>
@endsection
