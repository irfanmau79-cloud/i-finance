@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Rincian Data SPJ Perjalanan Dinas')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <a href="{{ route('manajemen-data.index') }}">Manajemen Data</a> / Rincian Data SPJ Perjalanan Dinas</div>
        <div class="ph-title">Rincian Data SPJ Perjalanan Dinas</div>
    </div>
    <div class="ph-actions">
        <a class="btn prim" href="{{ route('manajemen-data.rincian.spj-perjalanan-dinas.create') }}">+ Tambah Dokumen</a>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block">{{ $errors->first() }}</div>
@endif

<div class="dash-card wf-card">
    <div class="sub" style="margin-bottom:12px;">
        Dokumen SPJ perjalanan dinas periode <strong>sebelum migrasi</strong> yang tidak punya padanan baris di tabel NPD.
        Isinya digabung dengan baris dari NPD di <strong>Dashboard SPJ Perjalanan Dinas</strong>, dan di sana bisa disaring lewat pilihan Sumber.
        Berbeda dari <strong>Inventarisasi SPJ</strong>, yang bekerja atas NPD untuk seluruh jenis.
    </div>

    <form method="GET" action="{{ route('manajemen-data.rincian.spj-perjalanan-dinas') }}" class="tbl-tools" style="margin-bottom:14px;">
        <select name="tahun" style="max-width:140px;">
            @foreach ($daftarTahun as $opsi)
                <option value="{{ $opsi }}" @selected($tahun === $opsi)>{{ $opsi }}</option>
            @endforeach
        </select>
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nomor / Uraian / Bidang&hellip;" style="max-width:300px;">
        <button type="submit" class="btn prim">Tampilkan</button>
        @if ($cari !== '')
            <a class="btn" href="{{ route('manajemen-data.rincian.spj-perjalanan-dinas', ['tahun' => $tahun]) }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nomor Dokumen</th>
                    <th>Nomor SP</th>
                    <th>Bidang</th>
                    <th>Uraian</th>
                    <th class="num">Nominal</th>
                    <th>Status SPJ</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $item)
                    <tr>
                        <td>{{ $item->tanggal?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td style="font-weight:600;">{{ $item->nomor_npd }}</td>
                        <td>{{ $item->nomor_sp ?: '-' }}</td>
                        <td>{{ $item->bidang }}</td>
                        <td class="kol-uraian">{{ $item->uraian ?: '-' }}</td>
                        <td class="num">{{ number_format((float) $item->nominal, 2, ',', '.') }}</td>
                        <td>
                            @if ($item->spj_terverifikasi)
                                <span class="badge st-selesai">Terverifikasi</span>
                                @if ($item->tanggal_verifikasi)
                                    <div class="sub">{{ $item->tanggal_verifikasi->translatedFormat('d M Y') }}{{ $item->diverifikasi_oleh ? ' · '.$item->diverifikasi_oleh : '' }}</div>
                                @endif
                            @else
                                <span class="badge st-npd">Belum</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div class="aksi-wrap">
                                <a class="ic-btn" title="Edit" href="{{ route('manajemen-data.rincian.spj-perjalanan-dinas.edit', $item) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                                <details class="npd-hapus-pop">
                                    <summary class="ic-btn danger" title="Hapus"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></summary>
                                    <form method="POST" action="{{ route('manajemen-data.rincian.spj-perjalanan-dinas.destroy', $item) }}" class="npd-hapus-form">
                                        @csrf
                                        @method('DELETE')
                                        <div class="sub" style="margin-bottom:8px;">Hapus dokumen ini?</div>
                                        <button type="submit" class="btn danger" style="width:100%;">Hapus</button>
                                    </form>
                                </details>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--mut);padding:20px;">
                        Belum ada dokumen untuk tahun {{ $tahun }}. Tambahkan satu per satu, atau unggah lewat Import di Manajemen Data.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $baris->links() }}
</div>
@endsection
