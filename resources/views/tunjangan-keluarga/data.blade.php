@extends('layouts.app')

@section('activeNav', 'tk-data')
@section('title', 'Data Tunjangan Keluarga')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / Data Tunjangan Keluarga</div>
        <div class="ph-title">Data Tunjangan Keluarga</div>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block">{{ $errors->first() }}</div>
@endif

<div class="dash-card wf-card">
    <div class="sub" style="margin-bottom:14px;">Sumber data mentah yang dipakai Dashboard Tunjangan Keluarga. Kolom Nama Pasangan sampai Dokumen Pendukung diisi dan diperbarui langsung oleh superadmin.</div>

    <form method="GET" action="{{ route('tunjangan.data.index') }}" class="tbl-tools" style="margin-bottom:14px;">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nama / NIP…" style="max-width:320px;">
        <button type="submit" class="btn prim">Cari</button>
        @if ($cari !== '')
            <a class="btn" href="{{ route('tunjangan.data.index') }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi" style="min-width:1560px;">
            <thead>
                <tr>
                    <th>Nama Pegawai</th>
                    <th>NIP</th>
                    <th>Nama Pasangan</th>
                    <th>Status Tunjangan Pasangan</th>
                    <th>Nama Anak (Tanggungan-1)</th>
                    <th>Tanggal Lahir Anak (Tanggungan-1)</th>
                    <th>Status Tunjangan Anak Tanggungan-1</th>
                    <th>Nama Anak (Tanggungan-2)</th>
                    <th>Tanggal Lahir Anak (Tanggungan-2)</th>
                    <th>Status Tunjangan Anak Tanggungan-2</th>
                    <th>Dokumen Pendukung</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pegawaiList as $pegawai)
                    @php
                        $anggota = $pegawai->tunjanganKeluarga?->anggota ?? collect();
                        $pasangan = $anggota->firstWhere('hubungan', 'pasangan');
                        $anakList = $anggota->where('hubungan', 'anak')->values();
                        $anak1 = $anakList->get(0);
                        $anak2 = $anakList->get(1);
                        $umur1 = $anak1?->tanggal_lahir ? $service->umurRinci($anak1->tanggal_lahir) : null;
                        $umur2 = $anak2?->tanggal_lahir ? $service->umurRinci($anak2->tanggal_lahir) : null;
                    @endphp
                    <tr>
                        <td><strong>{{ $pegawai->nama }}</strong></td>
                        <td>{{ $pegawai->nip ?: '-' }}</td>
                        <td>{{ $pasangan?->nama ?: '-' }}</td>
                        <td>
                            @if ($pasangan)
                                <span class="badge {{ $pasangan->status_tunjangan ? 'st-aktif' : 'st-diterima' }}">{{ $pasangan->status_tunjangan ? 'Aktif' : 'Tidak' }}</span>
                            @else <span class="sub">-</span> @endif
                        </td>
                        <td>{{ $anak1?->nama ?: '-' }}</td>
                        <td>
                            @if ($anak1?->tanggal_lahir)
                                {{ $anak1->tanggal_lahir->format('d-m-Y') }}
                                <div class="sub">{{ $umur1['teks'] ?? '-' }}</div>
                            @else <span class="sub">-</span> @endif
                        </td>
                        <td>
                            @if ($anak1)
                                <span class="badge {{ $anak1->status_tunjangan ? 'st-aktif' : 'st-diterima' }}">{{ $anak1->status_tunjangan ? 'Aktif' : 'Tidak' }}</span>
                            @else <span class="sub">-</span> @endif
                        </td>
                        <td>{{ $anak2?->nama ?: '-' }}</td>
                        <td>
                            @if ($anak2?->tanggal_lahir)
                                {{ $anak2->tanggal_lahir->format('d-m-Y') }}
                                <div class="sub">{{ $umur2['teks'] ?? '-' }}</div>
                            @else <span class="sub">-</span> @endif
                        </td>
                        <td>
                            @if ($anak2)
                                <span class="badge {{ $anak2->status_tunjangan ? 'st-aktif' : 'st-diterima' }}">{{ $anak2->status_tunjangan ? 'Aktif' : 'Tidak' }}</span>
                            @else <span class="sub">-</span> @endif
                        </td>
                        <td>
                            @if ($pegawai->tunjanganKeluarga?->dokumen_pendukung_path)
                                <a href="{{ route('tunjangan.data.dokumen', $pegawai->tunjanganKeluarga) }}">{{ $pegawai->tunjanganKeluarga->dokumen_pendukung_nama ?? 'Unduh' }}</a>
                            @else <span class="sub">Belum ada</span> @endif
                        </td>
                        <td style="text-align:center;">
                            <a class="btn" href="{{ route('tunjangan.data.edit', $pegawai) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" style="text-align:center;color:var(--mut);padding:20px;">Belum ada pegawai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $pegawaiList->links() }}
</div>
@endsection
