@extends('layouts.app')

@section('activeNav', 'users')
@section('title', 'Tambah User')

@section('content')
<div class="dash-card">
    <h3>Tambah User</h3>
    <div class="sub">Buat akun baru untuk staf yang login ke sistem.</div>

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

    <form method="POST" action="{{ route('users.store') }}">
        @csrf

        @include('users._form', ['pegawaiList' => $pegawaiList])

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <a class="btn" href="{{ route('users.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan</button>
        </div>
    </form>
</div>
@endsection
