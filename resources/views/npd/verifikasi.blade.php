@extends('layouts.app')

@section('activeNav', 'verifikasi')
@section('title', 'Verifikasi Nota Pencairan Dana')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Verifikasi NPD</b></div>
        <div class="ph-title">Verifikasi Nota Pencairan Dana</div>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block">
        <strong>Terjadi kesalahan:</strong>
        <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

{{-- NPD dari Sub Kegiatan tanpa Verifikator tidak bisa diverifikasi siapa
     pun, dan tidak muncul di antrean akun Verifikator mana pun - jadi tanpa
     pemberitahuan ini NPD-nya tertahan diam-diam. --}}
@if ($tanpaVerifikator > 0)
    <div class="sumbar" style="background:var(--warn-bg);color:var(--warn-teks);">
        <span>
            <b>{{ $tanpaVerifikator }} NPD</b> menunggu verifikasi tetapi Sub Kegiatannya belum punya Verifikator,
            sehingga belum bisa diverifikasi.
            @if (auth()->user()->isSuperadmin())
                <a href="{{ route('pelimpahan.index', ['status' => 'tanpa_verifikator']) }}" style="color:inherit;font-weight:700;">Tetapkan Verifikatornya di menu Pelimpahan</a>.
            @else
                Minta superadmin menetapkan Verifikatornya di menu Pelimpahan.
            @endif
        </span>
    </div>
@endif

<div class="dash-card wf-card">
    <div class="sub" style="margin-bottom:14px;">
        @if (auth()->user()->role === \App\Models\User::ROLE_VERIFIKATOR)
            Nota Pencairan Dana dari Sub Kegiatan yang dilimpahkan kepada Anda dan menunggu tindakan Verifikator.
        @else
            Nota Pencairan Dana yang menunggu tindakan Verifikator. Nama di bawah status adalah Verifikator Sub Kegiatannya.
        @endif
    </div>

    {{-- Penyaring jenis & status ada di baris penyaring dalam tabel. --}}

    {{-- Satu aksi saja. Nomor NPD unik, jadi tiap dokumen diberi nomornya
         sendiri lewat ringkasan yang muncul setelah Jalankan ditekan. --}}
    @include('npd._tabel-workflow', [
        'npds' => $npds,
        'aksiMassalDaftar' => ['verifikasi'],
        'petaVerifikator' => $petaVerifikator,
        'tampilkanVerifikator' => true,
    ])
</div>
@endsection
