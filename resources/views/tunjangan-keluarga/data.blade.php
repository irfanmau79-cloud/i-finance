@extends('layouts.app')

@section('activeNav', 'tk-data')
@section('title', 'Data Tunjangan Keluarga')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Data Kepegawaian</b> / Data Tunjangan Keluarga</div>
        <div class="ph-title">Data Tunjangan Keluarga</div>
    </div>
    <div class="ph-actions">
        <a class="btn" href="{{ route('manajemen-data.export', 'tunjangan-keluarga') }}">Unduh Excel</a>
        <a class="btn prim" href="{{ route('tunjangan.import.create') }}">Import Excel</a>
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
        Daftarnya mengikuti <a href="{{ route('tunjangan.pegawai.index') }}">Data Pegawai</a>, dibatasi pada status
        <strong>PNS</strong> dan <strong>PPPK Penuh Waktu</strong>. Status Tunjangan dihitung otomatis dari data pasangan dan anak.
    </div>

    <form method="GET" action="{{ route('tunjangan.data.index') }}" class="tbl-tools" style="margin-bottom:14px;">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nama / NIP&hellip;" style="max-width:320px;">
        <button type="submit" class="btn prim">Cari</button>
        @if ($cari !== '')
            <a class="btn" href="{{ route('tunjangan.data.index') }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nama Pegawai</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th style="text-align:center;">Status Tunjangan</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pegawaiList as $pegawai)
                    @php
                        $keluarga = $pegawai->tunjanganKeluarga;
                        $status = $service->statusTunjangan($keluarga);
                        $adaData = $keluarga && $keluarga->anggota->isNotEmpty();
                    @endphp
                    <tr>
                        <td><strong>{{ $pegawai->nama }}</strong></td>
                        <td>{{ $pegawai->nip ?: '-' }}</td>
                        <td>{{ $pegawai->jabatan ?: '-' }}</td>
                        <td style="text-align:center;">
                            <span class="badge {{ $adaData ? 'st-aktif' : 'st-diterima' }}" title="K = punya pasangan, TK = tidak. Angka = jumlah anak yang berhak tunjangan.">{{ $status }}</span>
                        </td>
                        <td style="text-align:center;">
                            <div class="aksi-wrap" style="width:auto;grid-template-columns:repeat({{ $adaData ? 3 : 2 }},30px);justify-content:center;">
                                @if ($keluarga?->dokumen_pendukung_path)
                                    <a class="ic-btn" title="Lihat Dokumen Pendukung" href="{{ route('tunjangan.data.dokumen', $keluarga) }}"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                @endif

                                <a class="ic-btn" title="Edit data tunjangan keluarga" href="{{ route('tunjangan.data.edit', $pegawai) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>

                                @if ($adaData)
                                    <form method="POST" action="{{ route('tunjangan.data.hapus', $pegawai) }}"
                                          onsubmit="return confirm('Kosongkan data tunjangan keluarga {{ $pegawai->nama }}?\nData pasangan dan anak akan dihapus, statusnya kembali TK/0. Baris pegawainya tetap ada.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ic-btn danger" title="Kosongkan data tunjangan keluarga"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--mut);padding:20px;">Belum ada pegawai berstatus PNS atau PPPK Penuh Waktu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $pegawaiList->links() }}
</div>
@endsection
