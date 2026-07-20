@extends('layouts.app')

@section('activeNav', 'users')
@section('title', 'Ubah User')

@section('content')
<div class="dash-card">
    <h3>Ubah User</h3>
    <div class="sub">Perbarui data user {{ $user->username }}.</div>

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

    @if ($user->id !== auth()->id())
        <form method="POST" action="{{ route('users.username.update', $user) }}" style="border:1px solid var(--line);border-radius:8px;padding:14px;margin-bottom:18px;">
            @csrf
            @method('PATCH')
            <h4 style="margin:0 0 6px;">Ubah Username</h4>
            <div class="sub">Username disimpan dalam huruf kecil. Konfirmasi password Superadmin yang sedang login diperlukan.</div>
            <div class="form-grid" style="margin-top:12px;">
                <div class="fg">
                    <label class="fl" for="username_baru">Username Baru</label>
                    <input type="text" id="username_baru" name="username" maxlength="50" value="{{ old('username', $user->username) }}" autocomplete="off" required>
                </div>
                <div class="fg">
                    <label class="fl" for="current_password">Password Superadmin Saat Ini</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                <button type="submit" class="btn prim">Ubah Username</button>
            </div>
        </form>
    @endif

    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')

        @include('users._form', ['user' => $user, 'pegawaiList' => $pegawaiList])

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <a class="btn" href="{{ route('users.index') }}">Batal</a>
            <button type="submit" class="btn prim">Update</button>
        </div>
    </form>
</div>
@endsection
