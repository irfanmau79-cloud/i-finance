@extends('layouts.app')

@section('activeNav', 'sp-data')
@section('title', 'Edit Surat Perintah')

@section('content')
<div class="dash-card">
    <h3>Edit Surat Perintah</h3>
    <div class="sub">Perbarui data Surat Perintah {{ $suratPerintah->nomor_sp }}.</div>

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

    <form method="POST" action="{{ route('surat-perintah.update', $suratPerintah) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        @include('surat-perintah._form', ['suratPerintah' => $suratPerintah])

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <a class="btn" href="{{ route('surat-perintah.index') }}">Batal</a>
            <button type="submit" class="btn prim">Update</button>
        </div>
    </form>
</div>
@endsection
