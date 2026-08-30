@extends('layouts.app')

@section('activeNav', 'profil')
@section('title', 'Profil Saya')

@section('content')
@php
    $initials = collect(preg_split('/\s+/', trim($user->nama ?? '')))
        ->filter()
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
    $initials = $initials !== '' ? $initials : '?';
@endphp

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Profil Saya</b></div>
    <div class="ph-title">Profil Saya</div>
  </div>
</div>

<div class="dash-card">
    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="profil-top">
        <div class="profil-av">{{ $initials }}</div>
        <div>
            <div class="profil-id-nama">{{ $user->nama }}</div>
            <div class="profil-id-role">{{ config('akses.role_label')[$user->role] ?? $user->role }}</div>
        </div>
    </div>

    <div class="profil-divider"></div>

    <div class="profil-sec-title">Informasi Akun</div>
    <div class="profil-info-grid">
        <div class="profil-info-item"><div class="lbl">Username</div><div class="val">{{ $user->username }}</div></div>
        <div class="profil-info-item"><div class="lbl">NIP</div><div class="val">{{ $user->nip ?: ($user->pegawai->nip ?? '-') }}</div></div>
        @if ($user->pegawai)
            <div class="profil-info-item"><div class="lbl">Pegawai Terkait</div><div class="val">{{ $user->pegawai->nama }} &mdash; {{ $user->pegawai->jabatan }}</div></div>
        @endif
        <div class="profil-info-item"><div class="lbl">Terakhir Login</div><div class="val">{{ $user->last_login_at?->format('d-m-Y H:i') ?? '-' }}</div></div>
    </div>

    <form method="POST" action="{{ route('profil.update') }}">
        @csrf
        @method('PUT')

        <div class="profil-divider"></div>

        <div class="profil-sec-title">Ubah Identitas Tampilan</div>
        <div class="profil-form">
            <div>
                <label class="fl" for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" value="{{ old('nama', $user->nama) }}">
                <div class="profil-hint">Dipakai sebagai identitas akun: muncul di menu profil dan pada riwayat aktivitas Anda.</div>
            </div>
            <div>
                <label class="fl" for="nama_panggilan">Nama Panggilan</label>
                <input type="text" id="nama_panggilan" name="nama_panggilan" maxlength="60" value="{{ old('nama_panggilan', $user->nama_panggilan) }}" placeholder="Contoh: Irfan">
                <div class="profil-hint">Dipakai untuk sapaan di bilah atas. Bila dikosongkan, yang disapa adalah Nama Lengkap.</div>
            </div>
            <div>
                <label class="fl" for="jenis_kelamin">Jenis Kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin">
                    <option value="">&mdash; Belum diisi &mdash;</option>
                    @foreach (\App\Models\User::KELAMIN as $kode => $label)
                        <option value="{{ $kode }}" @selected(old('jenis_kelamin', $user->jenis_kelamin) === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="profil-hint">Menentukan sapaan di bilah atas: <strong>Pak</strong> untuk laki-laki, <strong>Bu</strong> untuk perempuan. Bila dikosongkan, sapaannya "Pak/Bu".</div>
            </div>
        </div>

        <div class="profil-divider"></div>

        <div class="profil-sec-title">Ganti Password <span style="font-weight:500;color:var(--mut);font-size:12px;">&mdash; opsional</span></div>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="password_lama">Password Lama</label>
                <input type="password" id="password_lama" name="password_lama" autocomplete="current-password" placeholder="Kosongkan bila tidak ganti">
            </div>
            <div class="fg">
                <label class="fl" for="password_baru">Password Baru</label>
                <input type="password" id="password_baru" name="password_baru" autocomplete="new-password" placeholder="Minimal 6 karakter">
                <div class="profil-hint">Isi Password Lama untuk mengganti.</div>
            </div>
        </div>

        <div class="profil-actions">
            <button type="submit" class="btn prim">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection
