@extends('layouts.app')

@section('activeNav', 'gt-potensi')
@section('title', 'Rekap Potensi Pengembalian')

@section('content')
@include('gaji-tunjangan._styles')

<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Data Gaji dan Tunjangan</b> / Rekap Potensi Pengembalian</div>
        <div class="ph-title">Rekap Potensi Pengembalian</div>
    </div>
</div>

<div class="dash-card wf-card">
    <div class="sub" style="margin-bottom:12px;">
        Kelebihan pembayaran per pegawai yang masih harus dikembalikan. Halaman ini baca-saja &mdash; datanya masuk lewat
        <strong>Manajemen Data &rsaquo; Data Rekap Potensi Pengembalian</strong>.
        <strong>Sisa Pengembalian</strong> selalu dihitung Potensi dikurangi Setoran, jadi ketiganya tidak mungkin saling bertentangan.
    </div>

    <div class="form-grid-auto" style="margin-bottom:14px;">
        <div class="fg"><label class="fl">Total Potensi</label><div class="ph-title" style="font-size:18px;">Rp {{ number_format($totalPotensi, 2, ',', '.') }}</div></div>
        <div class="fg"><label class="fl">Total Setoran</label><div class="ph-title" style="font-size:18px;">Rp {{ number_format($totalSetoran, 2, ',', '.') }}</div></div>
        <div class="fg"><label class="fl">Total Sisa</label><div class="ph-title" style="font-size:18px;color:{{ $totalSisa > 0 ? 'var(--warn)' : 'inherit' }};">Rp {{ number_format($totalSisa, 2, ',', '.') }}</div></div>
        <div class="fg"><label class="fl">Pegawai Belum Lunas</label><div class="ph-title" style="font-size:18px;">{{ $jumlahBelumLunas }}</div></div>
    </div>

    <form method="GET" action="{{ route('gaji-tunjangan.rekap-potensi') }}" class="tbl-tools" style="margin-bottom:14px;">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nama / NIP / Jabatan&hellip;" style="max-width:320px;">
        <select name="saring" style="max-width:240px;">
            <option value="semua" @selected($saring === 'semua')>Semua</option>
            <option value="ada" @selected($saring === 'ada')>Terdapat Pengembalian</option>
            <option value="tidak" @selected($saring === 'tidak')>Tidak Ada Pengembalian</option>
        </select>
        <button type="submit" class="btn prim">Cari</button>
        @if ($cari !== '' || $saring !== 'semua')
            <a class="btn" href="{{ route('gaji-tunjangan.rekap-potensi') }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="gt-table">
            <thead>
                <tr>
                    <th style="width:24%;">Nama / NIP</th>
                    <th style="width:16%;">Jabatan</th>
                    <th class="num" style="width:13%;">Potensi Kelebihan Pembayaran</th>
                    <th class="num" style="width:10%;">Setoran</th>
                    <th class="num" style="width:13%;">Sisa Pengembalian</th>
                    <th style="width:24%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rekap as $baris)
                    <tr>
                        <td class="gt-peg">
                            <strong>{{ $baris->nama }}</strong>
                            <div class="sub">{{ $baris->nip }}</div>
                        </td>
                        <td>{{ $baris->jabatan ?: '-' }}</td>
                        <td class="num">{{ number_format((float) $baris->potensi, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $baris->setoran, 2, ',', '.') }}</td>
                        <td class="num" style="{{ $baris->adaPengembalian() ? 'color:var(--warn);font-weight:600;' : '' }}">
                            {{ number_format($baris->sisa(), 2, ',', '.') }}
                        </td>
                        <td>{{ $baris->keterangan ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--mut);padding:20px;">
                        Belum ada data. Unggah lewat Manajemen Data &rsaquo; Data Rekap Potensi Pengembalian.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rekap->links() }}
</div>
@endsection
