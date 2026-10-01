@extends('layouts.app')

@section('activeNav', 'npd-rekanan')
@section('title', 'Daftar Rekanan')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Nota Pencairan Dana (NPD)</b> / Daftar Rekanan</div>
        <div class="ph-title">Daftar Rekanan</div>
    </div>
    <div class="ph-actions">
        <a class="btn prim" href="{{ route('rekanan.create') }}">+ Tambah Rekanan</a>
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
        Penyedia yang dipakai sebagai penerima pada <strong>NPD Barang/Jasa</strong>, <strong>NPD Narasumber</strong>, dan <strong>SPM LS</strong>.
        Datanya sama dengan yang masuk lewat <strong>Manajemen Data &rsaquo; Data Rekanan</strong> - halaman ini hanya menambah satu pintu lagi:
        mengetik rekanan baru satu per satu tanpa menyiapkan berkas import. Hanya rekanan berstatus <strong>Aktif</strong> yang muncul di Pembuatan NPD.
    </div>

    <form method="GET" action="{{ route('rekanan.index') }}" class="tbl-tools" style="margin-bottom:14px;">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari Nama / Rekening / NPWP / Jenis Usaha&hellip;" style="max-width:320px;">
        <select name="status" style="max-width:200px;">
            <option value="">Semua Status</option>
            <option value="aktif" @selected($status === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected($status === 'nonaktif')>Tidak Aktif</option>
        </select>
        <button type="submit" class="btn prim">Cari</button>
        @if ($cari !== '' || $status !== '')
            <a class="btn" href="{{ route('rekanan.index') }}">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nama Rekanan</th>
                    <th>Rekening</th>
                    <th>NPWP</th>
                    <th>Status PKP</th>
                    <th>Jenis Usaha</th>
                    <th>Nomor Handphone</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rekananList as $rekanan)
                    <tr>
                        <td>
                            <strong>{{ $rekanan->nama }}</strong>
                            @unless ($rekanan->aktif)
                                <span class="badge" style="background:var(--surface-3);color:var(--ink);margin-left:6px;">Non Aktif</span>
                            @endunless
                        </td>
                        <td>{{ $rekanan->rekening ?: '-' }}</td>
                        <td>{{ $rekanan->npwp ?: '-' }}</td>
                        <td>
                            <span class="badge" style="background:{{ $rekanan->pkp ? '#dcfce7' : '#fef3c7' }};color:{{ $rekanan->pkp ? '#166534' : '#92400e' }};">
                                {{ $rekanan->pkp ? 'PKP' : 'Non-PKP' }}
                            </span>
                        </td>
                        <td>{{ $rekanan->jenis_usaha ?: '-' }}</td>
                        {{-- Ditampilkan supaya nomor yang masih kosong langsung
                             kelihatan - itu yang menahan Kirim Notifikasi di Data NPD. --}}
                        <td>
                            @if ($rekanan->nomor_handphone)
                                {{ \App\Helpers\NomorWhatsapp::tampilan($rekanan->nomor_handphone) ?? $rekanan->nomor_handphone }}
                            @else
                                <span style="color:var(--warn);">Belum diisi</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <a class="ic-btn" title="Edit" href="{{ route('rekanan.edit', $rekanan) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada rekanan. Tambahkan lewat tombol di atas, atau import lewat Manajemen Data &rsaquo; Data Rekanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rekananList->links() }}
</div>
@endsection
