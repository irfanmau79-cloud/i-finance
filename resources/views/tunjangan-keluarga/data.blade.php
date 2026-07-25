@extends('layouts.app')

@section('activeNav', 'tk-data')
@section('title', 'Data Tunjangan Keluarga')

@section('content')
<style>
    #tk-data-table{table-layout:fixed;min-width:0;}
    #tk-data-table th{white-space:normal;word-break:normal;overflow-wrap:break-word;vertical-align:middle;line-height:1.25;padding:8px 4px;font-size:12.5px;text-transform:none;letter-spacing:normal;text-align:left;}
    #tk-data-table th.col-aksi,#tk-data-table th.col-status-pasangan,#tk-data-table th.col-status1,#tk-data-table th.col-status2{text-align:center;}
    #tk-data-table td{word-break:normal;overflow-wrap:break-word;padding:8px 6px;}
    #tk-data-table .col-nama{width:13%;}
    #tk-data-table .col-nip{width:10%;}
    #tk-data-table .col-pasangan{width:11%;}
    #tk-data-table .col-status-pasangan{width:6%;}
    #tk-data-table .col-anak1{width:11%;}
    #tk-data-table .col-tgl1{width:9%;}
    #tk-data-table .col-status1{width:6%;}
    #tk-data-table .col-anak2{width:11%;}
    #tk-data-table .col-tgl2{width:9%;}
    #tk-data-table .col-status2{width:6%;}
    #tk-data-table .col-aksi{width:8%;}
    #tk-data-table td.col-aksi{padding-left:2px;padding-right:2px;}
    #tk-data-table .ic-btn{width:26px;height:26px;}
    #tk-data-table .ic-btn svg{width:14px;height:14px;}
    #tk-data-table .aksi-wrap{grid-template-columns:repeat(2,26px);gap:6px;width:auto;}
</style>
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / Data Tunjangan Keluarga</div>
        <div class="ph-title">Data Tunjangan Keluarga</div>
    </div>
    <div class="ph-actions">
        <a class="btn prim" href="{{ route('tunjangan.pegawai.create') }}">+ Tambah Pegawai</a>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block">{{ $errors->first() }}</div>
@endif

<div class="dash-card wf-card">
    <form method="GET" action="{{ route('tunjangan.data.index') }}" class="tbl-tools" style="margin-bottom:14px;">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nama / NIP…" style="max-width:320px;">
        <button type="submit" class="btn prim">Cari</button>
        @if ($cari !== '')
            <a class="btn" href="{{ route('tunjangan.data.index') }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;overflow-x:hidden;">
        <table class="realisasi" id="tk-data-table">
            <thead>
                <tr>
                    <th class="col-nama">Nama Pegawai</th>
                    <th class="col-nip">NIP</th>
                    <th class="col-pasangan">Nama Pasangan</th>
                    <th class="col-status-pasangan">Status Tunjangan Pasangan</th>
                    <th class="col-anak1">Nama Anak<br>(Tanggungan-1)</th>
                    <th class="col-tgl1">Tanggal Lahir Anak (Tanggungan-1)</th>
                    <th class="col-status1">Status Tunjangan (Anak-1)</th>
                    <th class="col-anak2">Nama Anak<br>(Tanggungan-2)</th>
                    <th class="col-tgl2">Tanggal Lahir Anak (Tanggungan-2)</th>
                    <th class="col-status2">Status Tunjangan (Anak-2)</th>
                    <th class="col-aksi" style="text-align:center;">Aksi</th>
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
                        <td class="col-aksi" style="text-align:center;">
                            <div class="aksi-wrap">
                                @if ($pegawai->tunjanganKeluarga?->dokumen_pendukung_path)
                                    <a class="ic-btn" title="Lihat Dokumen Pendukung" href="{{ route('tunjangan.data.dokumen', $pegawai->tunjanganKeluarga) }}"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                @else
                                    <span class="ic-btn" title="Belum ada dokumen pendukung" style="opacity:.35;cursor:not-allowed;"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                                @endif
                                <a class="ic-btn" title="Edit" href="{{ route('tunjangan.data.edit', $pegawai) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" style="text-align:center;color:var(--mut);padding:20px;">Belum ada pegawai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $pegawaiList->links() }}
</div>
@endsection
