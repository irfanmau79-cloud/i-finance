@extends('layouts.app')

@section('activeNav', 'gt-gaji')
@section('title', 'Import Data Gaji & Tunjangan')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Gaji dan Tunjangan</b> / Import Data</div>
        <div class="ph-title">Import Data Gaji &amp; Tunjangan</div>
        <div class="ph-sub">Unggah hanya membuat preview. Data baru berubah setelah dikonfirmasi.</div>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif

<div class="dash-card" style="max-width:640px;">
    <div class="sub" style="margin-top:0;">
        Unggah berkas <b>Template SIPD</b> apa adanya - tidak perlu menambah kolom
        bulan dan tahun, keduanya diambil dari pilihan di bawah. Susunan kolomnya
        harus sama persis dengan template ({{ \App\Support\GajiTunjanganKolom::jumlahKolom('gaji') }} kolom untuk Gaji Induk,
        {{ \App\Support\GajiTunjanganKolom::jumlahKolom('beban') }} kolom untuk TPP).
    </div>

    @if ($errors->any())
        <div class="err-box" style="display:block;margin-top:12px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('gaji-tunjangan.import.store') }}" enctype="multipart/form-data" style="margin-top:6px;">
        @csrf

        <label class="fl" for="imp-jenis">Jenis Penghasilan</label>
        <select id="imp-jenis" name="jenis" required>
            @foreach ($jenisTersedia as $kunci => $label)
                <option value="{{ $kunci }}" @selected(old('jenis') === $kunci)>{{ $label }}</option>
            @endforeach
        </select>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:160px;">
                <label class="fl" for="imp-bulan">Bulan</label>
                <select id="imp-bulan" name="bulan" required>
                    @foreach ($namaBulan as $nomor => $nama)
                        <option value="{{ $nomor }}" @selected((int) old('bulan', $bulanTerpilih) === $nomor)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;min-width:120px;">
                <label class="fl" for="imp-tahun">Tahun</label>
                <input type="number" id="imp-tahun" name="tahun" min="2000" max="2100"
                       value="{{ old('tahun', $tahunTerpilih) }}" required>
            </div>
        </div>

        <label class="fl" for="imp-file">Berkas Excel (maks. 10 MB)</label>
        <input type="file" id="imp-file" name="file" accept=".xlsx,.xls,.csv" required>

        <button class="btn prim" style="margin-top:16px;">Unggah dan Periksa</button>
    </form>

    <div class="sub" style="margin-top:16px;margin-bottom:0;">
        <b>Kolom yang diisi sendiri sebelum diunggah</b> (berkas SIPD memuat kolomnya tetapi kosong):
        Nilai Kinerja <span class="sub">(wajib, tulis apa adanya seperti <code>98.74</code>)</span>,
        TPP Maksimum, Simpanan Koperasi Praja, dan Zakat. Kolom yang dibiarkan kosong
        dianggap 0, kecuali Nilai Kinerja. Pada berkas TPP Kondisi Kerja, kolom Simpanan
        Koperasi Praja dan Zakat tidak dipakai.
    </div>
</div>
@endsection
