@extends('layouts.app')

@section('activeNav', 'simulasi-pergeseran')
@section('title', 'Buat Simulasi Baru')

@section('content')
<div class="dash-card wf-card" style="max-width:560px;">
    <h3>Buat Simulasi Baru</h3>
    <div class="sub">Simulasi akan dibuat dari seluruh mata anggaran aktif saat ini (Pagu Simulasi awal = Pagu Eksisting). Anda bisa mengubah nilainya di halaman berikutnya.</div>

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

    <form method="POST" action="{{ route('simulasi-anggaran.store') }}">
        @csrf

        <label class="fl">Nama Simulasi</label>
        <input type="text" name="nama" value="{{ old('nama') }}" required maxlength="150" placeholder="Contoh: Simulasi Pergeseran Semester 2" style="width:100%;box-sizing:border-box;">

        <label class="fl">Keterangan</label>
        <textarea name="keterangan" rows="3" maxlength="1000" placeholder="Opsional" style="width:100%;box-sizing:border-box;">{{ old('keterangan') }}</textarea>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
            <a class="btn" href="{{ route('simulasi-anggaran.index') }}">Batal</a>
            <button type="submit" class="btn prim">Buat &amp; Lanjutkan</button>
        </div>
    </form>
</div>
@endsection
