@extends('layouts.app')

@section('activeNav', 'profil')
@section('title', 'Profil Saya')

@section('content')
<div class="dash-card" style="max-width:640px;">
    <h3>Profil Saya</h3>
    <div class="sub">Informasi akun dan pengaturan pribadi Anda.</div>

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

    <div style="margin-bottom:20px;">
        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line);">
            <div style="flex:0 0 160px;color:var(--mut);font-size:12.5px;">Username</div>
            <div style="flex:1;font-size:13px;font-weight:600;">{{ $user->username }}</div>
        </div>
        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line);">
            <div style="flex:0 0 160px;color:var(--mut);font-size:12.5px;">Role</div>
            <div style="flex:1;font-size:13px;font-weight:600;">{{ config('akses.role_label')[$user->role] ?? $user->role }}</div>
        </div>
        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line);">
            <div style="flex:0 0 160px;color:var(--mut);font-size:12.5px;">NIP</div>
            <div style="flex:1;font-size:13px;font-weight:600;">{{ $user->nip ?: ($user->pegawai->nip ?? '-') }}</div>
        </div>
        @if ($user->pegawai)
            <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--line);">
                <div style="flex:0 0 160px;color:var(--mut);font-size:12.5px;">Pegawai Terkait</div>
                <div style="flex:1;font-size:13px;font-weight:600;">{{ $user->pegawai->nama }} — {{ $user->pegawai->jabatan }}</div>
            </div>
        @endif
        <div style="display:flex;gap:10px;padding:8px 0;">
            <div style="flex:0 0 160px;color:var(--mut);font-size:12.5px;">Terakhir Login</div>
            <div style="flex:1;font-size:13px;font-weight:600;">{{ $user->last_login_at?->format('d-m-Y H:i') ?? '-' }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('profil.update') }}">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="fg span2">
                <label class="fl" for="nama">Nama</label>
                <input type="text" id="nama" name="nama" value="{{ old('nama', $user->nama) }}">
            </div>
            <div class="fg">
                <label class="fl" for="password_lama">Password Lama</label>
                <input type="password" id="password_lama" name="password_lama" autocomplete="current-password">
            </div>
            <div class="fg">
                <label class="fl" for="password_baru">Password Baru</label>
                <input type="password" id="password_baru" name="password_baru" autocomplete="new-password" placeholder="Kosongkan jika tidak ingin mengganti">
                <p class="mini">Minimal 6 karakter. Isi Password Lama untuk mengganti.</p>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <button type="submit" class="btn prim">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection
