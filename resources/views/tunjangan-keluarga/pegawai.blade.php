@extends('layouts.app')

@section('activeNav', 'tk-pegawai')
@section('title', 'Data Pegawai')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Data Kepegawaian</b> / Data Pegawai</div>
        <div class="ph-title">Data Pegawai</div>
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
    <div class="sub" style="margin-bottom:12px;">
        Daftar induk modul ini. <strong>Data Tunjangan Keluarga</strong> hanya menampilkan pegawai berstatus
        <strong>PNS</strong> dan <strong>PPPK Penuh Waktu</strong>; PPPK Paruh Waktu tidak berhak tunjangan keluarga.
    </div>

    <form method="GET" action="{{ route('tunjangan.pegawai.index') }}" class="tbl-tools" style="margin-bottom:14px;">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nama / NIP / Jabatan / Unit Kerja&hellip;" style="max-width:320px;">
        <select name="status" style="max-width:220px;">
            <option value="">Semua Status Kepegawaian</option>
            @foreach (\App\Models\Pegawai::STATUS_KEPEGAWAIAN as $opsi)
                <option value="{{ $opsi }}" @selected($status === $opsi)>{{ $opsi }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn prim">Cari</button>
        @if ($cari !== '' || $status !== '')
            <a class="btn" href="{{ route('tunjangan.pegawai.index') }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th>Pangkat/Golongan</th>
                    <th>Unit Kerja</th>
                    <th>Periode KGB</th>
                    <th>Status Kepegawaian</th>
                    <th>Nomor Handphone</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pegawaiList as $pegawai)
                    <tr>
                        <td>
                            <strong>{{ $pegawai->nama }}</strong>
                            @unless ($pegawai->aktif)
                                <span class="badge" style="background:#e5e7eb;color:#374151;margin-left:6px;">Non Aktif</span>
                            @endunless
                        </td>
                        <td>{{ $pegawai->nip ?: '-' }}</td>
                        <td>{{ $pegawai->jabatan ?: '-' }}</td>
                        <td>{{ $pegawai->pangkatGolongan() }}</td>
                        <td>{{ $pegawai->bidang ?: '-' }}</td>
                        <td>{{ $pegawai->periode_kgb ?: '-' }}</td>
                        <td>
                            @php($berhak = in_array($pegawai->status_kepegawaian, \App\Models\Pegawai::STATUS_BERHAK_TUNJANGAN, true))
                            <span class="badge" style="background:{{ $berhak ? '#dcfce7' : '#fef3c7' }};color:{{ $berhak ? '#166534' : '#92400e' }};">
                                {{ $pegawai->status_kepegawaian }}
                            </span>
                        </td>
                        {{-- Ditampilkan supaya nomor yang masih kosong langsung
                             kelihatan - itu yang menahan Kirim Notifikasi di Data NPD. --}}
                        <td>
                            @if ($pegawai->nomor_handphone)
                                {{ \App\Helpers\NomorWhatsapp::tampilan($pegawai->nomor_handphone) ?? $pegawai->nomor_handphone }}
                            @else
                                <span style="color:var(--warn);">Belum diisi</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <a class="ic-btn" title="Edit" href="{{ route('tunjangan.pegawai.edit', $pegawai) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;color:var(--mut);padding:20px;">Belum ada pegawai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $pegawaiList->links() }}
</div>
@endsection
