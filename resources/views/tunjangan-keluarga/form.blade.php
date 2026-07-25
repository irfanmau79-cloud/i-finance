@extends('layouts.app')

@section('activeNav', 'tk-form')
@section('title', 'Perubahan Data Tunjangan Keluarga')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / Perubahan Data</div>
        <div class="ph-title">Perubahan Data Tunjangan Keluarga</div>
    </div>
</div>

<div class="dash-card">
    <div class="sub">Gunakan form ini untuk pemutakhiran data keluarga yang mendapatkan tunjangan. Berkas disimpan secara private dan hanya dapat diakses petugas berwenang.</div>

    @if (session('success'))
        <div class="sumbar ok" style="margin-top:14px"><span>{{ session('success') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="err-box" style="display:block;margin-top:14px"><strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tunjangan.submit') }}" enctype="multipart/form-data" style="margin-top:6px">
        @csrf
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important">

        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="nama-pegawai">Nama Lengkap Pegawai</label>
                <input id="nama-pegawai" name="nama_pegawai" value="{{ old('nama_pegawai') }}" placeholder="Masukkan nama lengkap" required maxlength="150">
            </div>
            <div class="fg">
                <label class="fl" for="nip">NIP <span style="font-weight:500;color:var(--mut)">(opsional)</span></label>
                <input id="nip" name="nip" value="{{ old('nip') }}" placeholder="Nomor Induk Pegawai" maxlength="30">
            </div>
        </div>
        <div class="fg" style="margin-top:6px">
            <label class="fl" for="keterangan">Keterangan Perubahan Data</label>
            <textarea id="keterangan" name="keterangan" placeholder="Contoh: Menikah / Cerai / Lahir Anak" required>{{ old('keterangan') }}</textarea>
        </div>

        <div style="font-weight:600;color:var(--navy);font-size:13.5px;margin:20px 0 12px;">Anggota Keluarga yang Mendapatkan Tunjangan</div>
        <div class="fam-grid" id="fam-grid">
            <div class="fam-card">
                <div class="fam-head"><span class="fam-ic">&hearts;</span> Pasangan</div>
                <label class="fl" for="pasangan-nama">Nama Suami/Istri</label>
                <input id="pasangan-nama" name="pasangan[nama]" value="{{ old('pasangan.nama') }}" placeholder="Nama pasangan">
                <div class="form-grid2">
                    <div>
                        <label class="fl" for="pasangan-tanggal-lahir">Tanggal Lahir</label>
                        <input id="pasangan-tanggal-lahir" type="date" name="pasangan[tanggal_lahir]" value="{{ old('pasangan.tanggal_lahir') }}">
                    </div>
                    <div>
                        <label class="fl" for="pasangan-status">Dapat Tunjangan?</label>
                        <select id="pasangan-status" name="pasangan[status_tunjangan]">
                            <option value="0" @selected(! old('pasangan.status_tunjangan'))>Tidak</option>
                            <option value="1" @selected(old('pasangan.status_tunjangan'))>Ya</option>
                        </select>
                    </div>
                </div>
            </div>

            @foreach (old('anak', [[], []]) as $i => $anak)
                @include('tunjangan-keluarga._anak-card', ['i' => $i, 'anak' => $anak])
            @endforeach
        </div>

        <div style="margin-top:14px">
            <button type="button" class="btn" id="add-anak">+ Tambah Anak</button>
        </div>

        <div class="fg" style="margin-top:18px;max-width:520px;">
            <label class="fl" for="lampiran">Lampiran Bukti Dukung</label>
            <input id="lampiran" type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png" required>
            <div style="font-size:11px;color:var(--mut);margin-top:4px;font-style:italic;">Upload dokumen pendukung (Buku Nikah / Akta Lahir / Surat Keterangan Kuliah) &mdash; PDF/JPG/PNG, maks. 5 MB.</div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:18px;">
            <button type="submit" class="btn prim">Kirim Data Perubahan</button>
        </div>
    </form>
</div>

<script>
document.getElementById('add-anak').addEventListener('click', function () {
    const grid = document.getElementById('fam-grid');
    const index = grid.querySelectorAll('[data-anak-card]').length;
    if (index >= 10) return;
    const nomor = index + 1;

    const card = document.createElement('div');
    card.className = 'fam-card';
    card.setAttribute('data-anak-card', '');
    card.innerHTML = ''
        + '<div class="fam-head"><span class="fam-ic">' + nomor + '</span> Anak Ke-' + nomor + '</div>'
        + '<label class="fl" for="anak-' + index + '-nama">Nama Anak Ke-' + nomor + '</label>'
        + '<input id="anak-' + index + '-nama" name="anak[' + index + '][nama]" placeholder="Nama anak ke-' + nomor + '">'
        + '<div class="form-grid2">'
        + '<div><label class="fl" for="anak-' + index + '-tanggal-lahir">Tanggal Lahir</label><input id="anak-' + index + '-tanggal-lahir" type="date" name="anak[' + index + '][tanggal_lahir]"></div>'
        + '<div><label class="fl" for="anak-' + index + '-status">Dapat Tunjangan?</label><select id="anak-' + index + '-status" name="anak[' + index + '][status_tunjangan]"><option value="0">Tidak</option><option value="1">Ya</option></select></div>'
        + '</div>'
        + '<div class="form-grid2">'
        + '<div><label class="fl" for="anak-' + index + '-kuliah">Perpanjangan Kuliah?</label><select id="anak-' + index + '-kuliah" name="anak[' + index + '][perpanjangan_kuliah]"><option value="0">Tidak</option><option value="1">Ya</option></select></div>'
        + '<div><label class="fl" for="anak-' + index + '-keterangan">Keterangan</label><input id="anak-' + index + '-keterangan" name="anak[' + index + '][keterangan]" placeholder="Opsional, cth: perpanjangan usia 21–25"></div>'
        + '</div>';
    grid.appendChild(card);
});
</script>
@endsection
