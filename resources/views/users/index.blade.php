@extends('layouts.app')

@section('activeNav', 'users')
@section('title', 'Manajemen Users')

@section('content')
@php
    $aktifBendaharaCount = $users->where('role', 'bendahara')->where('aktif', true)->count();
@endphp
<div class="dash-card wf-card">
    <h3>Manajemen Users</h3>
    <div class="sub">Kelola akun pengguna sistem i-Finance.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Tidak dapat memproses permintaan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('users.create') }}" class="btn prim" style="white-space:nowrap;">+ Tambah User</a>
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Terakhir Login</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    @php
                        $satuSatunyaBendaharaAktif = $u->role === 'bendahara' && $u->aktif && $aktifBendaharaCount <= 1;
                        $iniSayaSendiri = $u->id === auth()->id();
                    @endphp
                    <tr>
                        <td>{{ $u->username }}</td>
                        <td>{{ $u->nama }}</td>
                        <td>{{ $u->nip ?: ($u->pegawai->nip ?? '-') }}</td>
                        <td>{{ config('akses.role_label')[$u->role] ?? $u->role }}</td>
                        <td><span class="badge {{ $u->aktif ? 'st-aktif' : 'st-danger' }}">{{ $u->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>{{ $u->last_login_at?->format('d-m-Y H:i') ?? '-' }}</td>
                        <td style="text-align:center;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:10px;">
                                <div class="aksi-wrap" style="width:auto;grid-template-columns:repeat(2,30px);">
                                    <a class="ic-btn" title="Ubah" href="{{ route('users.edit', $u) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Yakin ingin menghapus PERMANEN user {{ $u->username }}? Tindakan ini tidak bisa dibatalkan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ic-btn danger" title="Hapus Permanen" @disabled($iniSayaSendiri || $satuSatunyaBendaharaAktif)><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('users.toggle-aktif', $u) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sw" title="{{ $u->aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <input type="checkbox" onchange="this.form.requestSubmit()" @checked($u->aktif) @disabled($iniSayaSendiri && $u->aktif || $satuSatunyaBendaharaAktif)>
                                        <span class="sl"></span>
                                    </label>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada user.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
