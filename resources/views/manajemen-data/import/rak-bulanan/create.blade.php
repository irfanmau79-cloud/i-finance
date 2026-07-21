@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import RAK Bulanan')

@section('content')
<div class="dash-card">
    <h3>Import RAK Bulanan</h3>
    <div class="sub" style="font-weight:700;">Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</div>
    <div class="sub">
        Upload file Excel (.xlsx/.xls) dengan header yang sama seperti hasil unduhan export RAK Bulanan:
        Tahun Anggaran, Program, Kegiatan, Sub Kegiatan, Kode Rekening, Uraian Rekening, Pagu, lalu 12 kolom Januari-Desember (nilai bulanan, bukan kumulatif).
        RAK Bulanan hanya sampai tingkat <strong>Kode Rekening</strong> - satu baris mewakili satu kombinasi Tahun Anggaran + Sub Kegiatan + Kode Rekening.
        Kolom Program dan Kegiatan murni referensi (otomatis mengikuti Sub Kegiatan), tidak dibaca untuk pencocokan.
        Setiap baris wajib cocok ke Sub Kegiatan + Kode Rekening yang sudah ada dan aktif pada Master Anggaran - baris yang tidak cocok akan ditolak, bukan membuat mata anggaran baru.
        File akan ditampilkan sebagai <strong>preview</strong> dulu - belum ada yang tersimpan sampai Anda menekan Konfirmasi Simpan.
    </div>
    <div class="sub" style="margin-top:4px;">
        File format lama yang masih memiliki kolom Tagging tetap bisa diupload - kolom tersebut akan diabaikan sepenuhnya (RAK tidak lagi dibedakan per Tagging).
        Sumber GAS kumulatif hanya diterima bila workbook memiliki marker <code>IFINANCE_RAK_GAS_CUMULATIVE_V1</code>; preview menampilkan nilai asli dan hasil konversi bulanan.
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

    <form method="POST" action="{{ route('manajemen-data.import.rak-bulanan.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="fg">
            <label class="fl" for="tahun">Tahun Anggaran</label>
            <input type="number" id="tahun" name="tahun" min="{{ config('anggaran.tahun_aktif') }}" max="{{ config('anggaran.tahun_aktif') }}" value="{{ old('tahun', $tahunSekarang) }}" required readonly style="max-width:160px;">
        </div>

        <div class="fg">
            <label class="fl" for="file">File Excel</label>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </div>

        <div class="fg">
            <label class="fl" for="format_legacy">Format file tanpa marker (kompatibilitas sementara)</label>
            <select id="format_legacy" name="format_legacy">
                <option value="">Template baru bermarker / Laravel lama bulanan</option>
                <option value="legacy_laravel_monthly_v1">Laravel lama - bulanan</option>
                <option value="legacy_gas_cumulative_v1">GAS lama - kumulatif (marker tetap wajib)</option>
            </select>
        </div>

        <div class="sub" style="margin-top:8px;">
            {{ config('anggaran.catatan_scope') }}<br>
            Batas: 5 MB, maksimum {{ number_format(\App\Models\RakBulananImport::MAKS_BARIS, 0, ',', '.') }} baris mata anggaran per file.
            Sesi preview berlaku {{ \App\Models\RakBulananImport::MENIT_KEDALUWARSA }} menit sebelum harus upload ulang.
        </div>

        <div class="nav" style="margin-top:16px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Upload &amp; Preview</button>
        </div>
    </form>
</div>

@if (count($auditDuplikat))
<div class="dash-card" style="margin-top:16px;border-color:#f59e0b;">
    <h3>Audit Duplikat RAK Lama</h3>
    <div class="sub">Tidak ada data yang diubah. Setiap kelompok berikut memerlukan verifikasi sebelum migrasi.</div>
    <ul>
        @foreach ($auditDuplikat as $group)
            <li><strong>{{ $group['tahun'] }} — {{ $group['sub_kegiatan'] }} — {{ $group['kode_rekening'] }}</strong>: {{ $group['jumlah_sumber'] }} sumber, {{ $group['jenis'] }}. {{ $group['strategi'] }}</li>
        @endforeach
    </ul>
</div>
@endif
@endsection
