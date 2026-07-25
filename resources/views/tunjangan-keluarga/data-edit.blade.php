@extends('layouts.app')

@section('activeNav', 'tk-data')
@section('title', 'Edit Data Tunjangan Keluarga')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / <a href="{{ route('tunjangan.data.index') }}">Data Tunjangan Keluarga</a> / Edit</div>
        <div class="ph-title">{{ $pegawai->nama }}</div>
    </div>
</div>

@if ($errors->any())
    <div class="err-box" style="display:block">
        <strong>Terjadi kesalahan:</strong>
        <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@php
    $anggota = $pegawai->tunjanganKeluarga?->anggota ?? collect();
    $pasangan = $anggota->firstWhere('hubungan', 'pasangan');
    $anakList = $anggota->where('hubungan', 'anak')->values();
    $slotAnak = [1 => $anakList->get(0), 2 => $anakList->get(1)];
@endphp

<div class="dash-card">
    <div class="sub">NIP {{ $pegawai->nip ?: '-' }} &middot; {{ $pegawai->jabatan ?: '-' }}</div>

    <form method="POST" action="{{ route('tunjangan.data.simpan', $pegawai) }}" enctype="multipart/form-data" style="margin-top:14px;">
        @csrf

        <div class="fam-grid">
            <div class="fam-card">
                <div class="fam-head"><span class="fam-ic">&hearts;</span> Pasangan</div>
                <label class="fl" for="pasangan-nama">Nama Suami/Istri</label>
                <input id="pasangan-nama" name="pasangan[nama]" value="{{ old('pasangan.nama', $pasangan?->nama) }}" placeholder="Nama pasangan">
                <div class="form-grid2">
                    <div>
                        <label class="fl" for="pasangan-tgl">Tanggal Lahir</label>
                        <input id="pasangan-tgl" type="date" name="pasangan[tanggal_lahir]" value="{{ old('pasangan.tanggal_lahir', $pasangan?->tanggal_lahir?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="fl" for="pasangan-status">Dapat Tunjangan?</label>
                        <select id="pasangan-status" name="pasangan[status_tunjangan]">
                            <option value="0" @selected(! old('pasangan.status_tunjangan', $pasangan?->status_tunjangan))>Tidak</option>
                            <option value="1" @selected(old('pasangan.status_tunjangan', $pasangan?->status_tunjangan))>Ya</option>
                        </select>
                    </div>
                </div>
            </div>

            @foreach ($slotAnak as $nomor => $anak)
                @php($idx = $nomor - 1)
                <div class="fam-card">
                    <div class="fam-head"><span class="fam-ic">{{ $nomor }}</span> Anak Ke-{{ $nomor }} (Tanggungan-{{ $nomor }})</div>
                    <label class="fl" for="anak-{{ $idx }}-nama">Nama Anak Ke-{{ $nomor }}</label>
                    <input id="anak-{{ $idx }}-nama" name="anak[{{ $idx }}][nama]" value="{{ old('anak.'.$idx.'.nama', $anak?->nama) }}" placeholder="Nama anak ke-{{ $nomor }}">
                    <div class="form-grid2">
                        <div>
                            <label class="fl" for="anak-{{ $idx }}-tgl">Tanggal Lahir</label>
                            <input id="anak-{{ $idx }}-tgl" type="date" name="anak[{{ $idx }}][tanggal_lahir]" value="{{ old('anak.'.$idx.'.tanggal_lahir', $anak?->tanggal_lahir?->format('Y-m-d')) }}">
                            @php($umur = $anak?->tanggal_lahir ? $service->umurRinci($anak->tanggal_lahir) : null)
                            @if ($umur)
                                <div class="sub" style="margin-top:4px;">Usia saat ini: {{ $umur['teks'] }}</div>
                            @endif
                        </div>
                        <div>
                            <label class="fl" for="anak-{{ $idx }}-status">Dapat Tunjangan?</label>
                            <select id="anak-{{ $idx }}-status" name="anak[{{ $idx }}][status_tunjangan]">
                                <option value="0" @selected(! old('anak.'.$idx.'.status_tunjangan', $anak?->status_tunjangan))>Tidak</option>
                                <option value="1" @selected(old('anak.'.$idx.'.status_tunjangan', $anak?->status_tunjangan))>Ya</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid2">
                        <div>
                            <label class="fl" for="anak-{{ $idx }}-kuliah">Perpanjangan Kuliah?</label>
                            <select id="anak-{{ $idx }}-kuliah" name="anak[{{ $idx }}][perpanjangan_kuliah]">
                                <option value="0" @selected(! old('anak.'.$idx.'.perpanjangan_kuliah', $anak?->perpanjangan_kuliah))>Tidak</option>
                                <option value="1" @selected(old('anak.'.$idx.'.perpanjangan_kuliah', $anak?->perpanjangan_kuliah))>Ya</option>
                            </select>
                        </div>
                        <div>
                            <label class="fl" for="anak-{{ $idx }}-keterangan">Keterangan</label>
                            <input id="anak-{{ $idx }}-keterangan" name="anak[{{ $idx }}][keterangan]" value="{{ old('anak.'.$idx.'.keterangan', $anak?->keterangan) }}" placeholder="Opsional">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="fg" style="margin-top:18px;max-width:520px;">
            <label class="fl" for="dokumen_pendukung">Dokumen Pendukung (opsional)</label>
            @if ($pegawai->tunjanganKeluarga?->dokumen_pendukung_path)
                <div class="sub" style="margin-bottom:6px;">Saat ini: <a href="{{ route('tunjangan.data.dokumen', $pegawai->tunjanganKeluarga) }}">{{ $pegawai->tunjanganKeluarga->dokumen_pendukung_nama }}</a></div>
            @endif
            <input id="dokumen_pendukung" type="file" name="dokumen_pendukung" accept=".pdf,.jpg,.jpeg,.png">
            <div class="sub" style="margin-top:4px;">Upload untuk mengganti dokumen yang ada. PDF/JPG/PNG, maks 5 MB.</div>
        </div>

        <div style="display:flex;justify-content:space-between;margin-top:20px;">
            <a class="btn" href="{{ route('tunjangan.data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan</button>
        </div>
    </form>
</div>
@endsection
