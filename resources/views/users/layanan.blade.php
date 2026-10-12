@extends('layouts.app')

@section('activeNav', 'users')
@section('title', 'Kata Sandi Pengguna Layanan')

@section('content')
<div class="dash-card">
    <h3>Kata Sandi Pengguna Layanan</h3>
    <div class="sub">
        Pengguna Layanan masuk tanpa akun, cukup dengan satu kata sandi bersama.
        {{ $terakhirDiubah ? 'Terakhir diganti '.$terakhirDiubah->format('d-m-Y H:i').'.' : 'Saat ini masih memakai kata sandi bawaan server.' }}
    </div>

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

    <form method="POST" action="{{ route('users.layanan.update') }}"
        data-konfirmasi="Ganti kata sandi Pengguna Layanan?&#10;Kata sandi lama langsung tidak berlaku. Sampaikan kata sandi baru kepada pegawai."
        data-konfirmasi-ya="Ya, Ganti">
        @csrf
        @method('PUT')

        <div class="form-grid" style="margin-top:12px;">
            <div class="fg">
                <label class="fl" for="password">Kata Sandi Baru</label>
                <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Minimal {{ \App\Support\SandiLayanan::MIN_PANJANG }} karakter" required>
            </div>
            <div class="fg">
                <label class="fl" for="password_confirmation">Ulangi Kata Sandi Baru</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <a class="btn" href="{{ route('users.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan</button>
        </div>
    </form>
</div>
@endsection
