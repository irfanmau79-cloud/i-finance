@extends('layouts.app')

@section('activeNav', 'spm-upgu')
@php($spmEdit = $spm ?? null)
@section('title', $spmEdit ? 'Edit Realisasi SP2D UP/GU/TU' : 'Buat Realisasi SP2D UP/GU/TU')

@section('content')
<div class="dash-card">
    <h3>{{ $spmEdit ? 'Edit' : 'Buat' }} Realisasi SP2D UP/GU/TU</h3>
    <div class="sub">Pengisian ulang kas — tidak memilih mata anggaran dan tidak mengurangi pagu.</div>

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

    <form method="POST" action="{{ $spmEdit ? route('spm.up-gu.update', $spmEdit) : route('spm.up-gu.store') }}">
        @csrf
        @if ($spmEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_dokumen">Tanggal SPM</label>
                <input type="date" id="tanggal_dokumen" name="tanggal_dokumen" value="{{ old('tanggal_dokumen', $spmEdit?->tanggal_dokumen?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="nomor_dokumen">Nomor SPM</label>
                <input type="text" id="nomor_dokumen" name="nomor_dokumen" value="{{ old('nomor_dokumen', $spmEdit?->nomor_dokumen) }}">
            </div>
        </div>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_sp2d">Tanggal SP2D (opsional)</label>
                <input type="date" id="tanggal_sp2d" name="tanggal_sp2d" value="{{ old('tanggal_sp2d', $spmEdit?->tanggal_sp2d?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="nomor_sp2d">Nomor SP2D (opsional)</label>
                <input type="text" id="nomor_sp2d" name="nomor_sp2d" value="{{ old('nomor_sp2d', $spmEdit?->nomor_sp2d) }}">
            </div>
        </div>
        <div class="fg">
            <label class="fl" for="nominal">Nominal (Rp)</label>
            <input type="number" step="0.01" min="0.01" id="nominal" name="nominal" value="{{ old('nominal', $spmEdit?->nominal) }}">
        </div>
        <div class="fg">
            <label class="fl" for="penerima">Penerima (opsional)</label>
            <input type="text" id="penerima" name="penerima" value="{{ old('penerima', $spmEdit?->penerima) }}">
        </div>
        <div class="fg">
            <label class="fl" for="uraian">Uraian</label>
            <input type="text" id="uraian" name="uraian" value="{{ old('uraian', $spmEdit?->uraian) }}">
        </div>

        <div class="nav">
            <a class="btn" href="{{ route('spm.up-gu.index') }}">Batal</a>
            <button type="submit" class="btn prim">{{ $spmEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
    </form>
</div>
@endsection
