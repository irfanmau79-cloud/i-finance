@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Rincian Data Perjalanan Dinas')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <a href="{{ route('manajemen-data.index') }}">Manajemen Data</a> / Rincian Data Perjalanan Dinas</div>
        <div class="ph-title">Rincian Data Perjalanan Dinas</div>
    </div>
    <div class="ph-actions">
        <a class="btn prim" href="{{ route('manajemen-data.rincian.perjalanan-dinas.create') }}">+ Tambah Rincian</a>
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
        Rincian per orang per bulan yang <strong>menambal periode sebelum migrasi</strong>. NPD periode itu sudah masuk lewat
        Import NPD Historis dan nilainya real, tetapi tanpa rincian anggota tim &mdash; padahal itulah yang dibaca
        <strong>Dashboard Perjalanan Dinas</strong>.
        Baris di sini <strong>tidak pernah ikut perhitungan anggaran</strong>; realisasi tetap murni dari NPD.
        Yang berasal dari NPD tidak muncul di halaman ini dan tidak bisa disunting dari sini.
    </div>

    <form method="GET" action="{{ route('manajemen-data.rincian.perjalanan-dinas') }}" class="tbl-tools" style="margin-bottom:14px;">
        <select name="tahun" style="max-width:140px;">
            @foreach ($daftarTahun as $opsi)
                <option value="{{ $opsi }}" @selected($tahun === $opsi)>{{ $opsi }}</option>
            @endforeach
        </select>
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nama / NIP&hellip;" style="max-width:280px;">
        <button type="submit" class="btn prim">Tampilkan</button>
        @if ($cari !== '')
            <a class="btn" href="{{ route('manajemen-data.rincian.perjalanan-dinas', ['tahun' => $tahun]) }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nama / NIP</th>
                    <th>Bulan</th>
                    <th class="num">Hari</th>
                    <th class="num">Uang Harian</th>
                    <th class="num">Akomodasi</th>
                    <th class="num">Transport</th>
                    <th class="num">Representatif</th>
                    <th class="num">Jumlah Diterima</th>
                    <th>Keterangan</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->pegawai?->nama ?? '-' }}</strong>
                            <div class="sub">{{ $item->pegawai?->nip ?? '-' }}</div>
                        </td>
                        <td>{{ \Carbon\Carbon::create(null, $item->bulan)->translatedFormat('F') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->hari, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="num">{{ number_format((float) $item->uang_harian, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $item->akomodasi, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $item->transport, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $item->representatif, 2, ',', '.') }}</td>
                        <td class="num" style="font-weight:600;">{{ number_format($item->jumlahDiterima(), 2, ',', '.') }}</td>
                        <td class="kol-uraian">{{ $item->keterangan ?: '-' }}</td>
                        <td style="text-align:center;">
                            <div class="aksi-wrap">
                                <a class="ic-btn" title="Edit" href="{{ route('manajemen-data.rincian.perjalanan-dinas.edit', $item) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                                <details class="npd-hapus-pop">
                                    <summary class="ic-btn danger" title="Hapus"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></summary>
                                    <form method="POST" action="{{ route('manajemen-data.rincian.perjalanan-dinas.destroy', $item) }}" class="npd-hapus-form">
                                        @csrf
                                        @method('DELETE')
                                        <div class="sub" style="margin-bottom:8px;">Hapus rincian ini?</div>
                                        <button type="submit" class="btn danger" style="width:100%;">Hapus</button>
                                    </form>
                                </details>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="text-align:center;color:var(--mut);padding:20px;">
                        Belum ada rincian untuk tahun {{ $tahun }}. Tambahkan satu per satu, atau unggah lewat Import di Manajemen Data.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $baris->links() }}
</div>
@endsection
