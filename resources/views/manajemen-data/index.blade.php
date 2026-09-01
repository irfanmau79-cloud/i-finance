@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Manajemen Data')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Manajemen Data</b></div>
        <div class="ph-title">Manajemen Data</div>
    </div>
</div>

<div class="sub" style="margin-bottom:16px;">Mengunduh dan mengunggah data pokok lewat berkas Excel. Setiap unggahan ditampilkan lebih dulu untuk diperiksa sebelum disimpan.</div>

@if (session('success'))
    <div class="sumbar ok" style="margin-bottom:16px;"><span>{{ session('success') }}</span></div>
@endif

@if ($errors->any())
    <div class="err-box" style="display:block;margin-bottom:16px;">
        <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="dash-card" style="margin-bottom:18px;border-left:3px solid var(--navy);">
    <h3 style="margin-bottom:2px;">
        <a href="{{ route('manajemen-data.realisasi-periode.index') }}" style="color:inherit;text-decoration:none;">Data Realisasi Anggaran</a>
    </h3>
    <div class="sub" style="margin-bottom:12px;">
        Menarik realisasi seluruh mata anggaran pada rentang tanggal pilihan &mdash; misalnya
        1 Januari s.d. 31 Agustus, atau 1 s.d. 31 Agustus saja &mdash; dirinci Program,
        Kegiatan, Sub Kegiatan, Kode Rekening, sampai Tagging. Bukan berkas unggahan:
        angkanya dihitung dari NPD dan SP2D, bukan dari data yang diimpor.
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <a class="btn prim" href="{{ route('manajemen-data.realisasi-periode.index') }}">Buka Laporan</a>
        <a class="btn" href="{{ route('manajemen-data.realisasi-periode.excel') }}">Unduh Excel</a>
        <a class="btn" target="_blank" href="{{ route('manajemen-data.realisasi-periode.pdf') }}">Cetak PDF</a>
    </div>
</div>

<div class="dash-grid">
    @foreach ($tipeData as $key => $meta)
        @php
            $butuhSuperadmin = in_array($key, ['npd', 'perjalanan-dinas', 'spj-perjalanan-dinas', 'tunjangan-keluarga'], true);
            $bolehImport = ! $butuhSuperadmin || auth()->user()->isSuperadmin();
            // Data Gaji & Tunjangan tidak punya Export - berkasnya datang dari
            // SIPD dan dipakai apa adanya, jadi export_jenis-nya null.
            $exportHref = $meta['export_jenis'] === null
                ? null
                : ($meta['export_jenis'] === 'rak-bulanan'
                    ? route('manajemen-data.export', ['jenis' => 'rak-bulanan', 'tahun' => $tahunSekarang])
                    : route('manajemen-data.export', $meta['export_jenis']));
            // RAK tidak punya template kosong generik - templatenya HARUS mengacu ke Master
            // Anggaran aktif saat ini (baris Sub Kegiatan/Kode Rekening), jadi tombol Template
            // mengunduh sumber yang sama dengan tombol Export.
            $templateHref = $meta['import_template']
                ? route($meta['import_template'][0], $meta['import_template'][1])
                : ($key === 'rak' ? $exportHref : null);
            $resetKeywordIni = $resetKeyword[$key] ?? null;
            // Judul kartu Pagu sekaligus pintu masuk ke riwayat tahapan pagu
            // (DPA Murni, DPA Pergeseran, ...). Sengaja lewat judul, bukan
            // tombol kelima, supaya deretan tombol tetap seragam antar kartu.
            $judulHref = $key === 'pagu' ? route('versi-pagu.index') : null;
        @endphp
        <div class="dash-card">
            <h3 style="margin-bottom:{{ $judulHref ? '2px' : '10px' }};">
                @if ($judulHref)
                    <a href="{{ $judulHref }}" style="color:inherit;text-decoration:none;">{{ $meta['label'] }}</a>
                @else
                    {{ $meta['label'] }}
                @endif
            </h3>
            @if ($judulHref)
                <div class="sub" style="margin:0 0 10px;">Klik judul untuk melihat tahapan pagu, Nomor DPA, dan histori pagu.</div>
            @endif
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
                @if ($templateHref && ($bolehImport || ! $meta['import_create']))
                    <a href="{{ $templateHref }}" class="btn" style="white-space:nowrap;">Unduh Template</a>
                @endif
                @if ($meta['import_create'] && $bolehImport)
                    <a href="{{ route($meta['import_create'][0], $meta['import_create'][1]) }}" class="btn" style="white-space:nowrap;">Import Excel</a>
                @endif
                @if ($exportHref)
                    <a href="{{ $exportHref }}" class="btn prim" style="white-space:nowrap;">Unduh Excel</a>
                @endif
                @if ($resetKeywordIni && auth()->user()->isSuperadmin())
                    <details class="reset-data-details">
                        <summary class="btn danger" style="display:inline-block;white-space:nowrap;">Reset Data</summary>
                    </details>
                @endif
            </div>
            @if ($key === 'rak' && $bolehImport)
                <div class="sub" style="margin-top:8px;margin-bottom:0;">Templatenya sama dengan hasil unduhan: mata anggarannya sudah terisi, kolom bulanan tinggal dilengkapi.</div>
            @endif
            @if (($meta['import_note'] ?? null) && ($bolehImport || ! $meta['import_create']))
                <div class="sub" style="margin-top:8px;margin-bottom:0;">{{ $meta['import_note'] }}</div>
            @endif
            @if ($butuhSuperadmin && ! $bolehImport && $meta['import_create'])
                <div class="sub" style="margin-top:8px;margin-bottom:0;">Import khusus Superadmin.</div>
            @endif

            @if ($resetKeywordIni && auth()->user()->isSuperadmin())
                @php($konfirmasi = 'HAPUS '.$resetKeywordIni)
                <div class="reset-data-panel" style="display:none;margin-top:12px;padding:12px;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;">
                    <div class="sub" style="color:#991b1b;margin:0 0 8px;">
                        Menghapus SEMUA {{ $meta['label'] }} secara permanen. Tindakan ini tidak bisa dibatalkan.
                        @if ($key === 'npd')
                            Data Pengembalian yang terkait NPD ikut terhapus.
                        @elseif ($key === 'spm-ls')
                            Data Pengembalian yang terkait SPM LS ikut terhapus.
                        @elseif ($key === 'pegawai')
                            Data Tunjangan Keluarga milik pegawai ikut terhapus, dan reset akan gagal kalau pegawai masih menjabat KPA/BPP/PPTK.
                        @elseif ($key === 'pagu')
                            Reset ini akan gagal kalau masih ada NPD atau SPM LS yang memakai mata anggaran - reset Data NPD dan Data SPM LS terlebih dahulu.
                        @endif
                    </div>
                    <form method="POST" action="{{ route('manajemen-data.reset', $key) }}" class="reset-data-form" data-confirm="{{ $konfirmasi }}">
                        @csrf
                        <label class="fl" style="display:block;margin-bottom:6px;">Ketik <code>{{ $konfirmasi }}</code> untuk konfirmasi:</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <input type="text" name="konfirmasi" class="reset-data-input" autocomplete="off" style="flex:1;min-width:180px;">
                            <button type="submit" class="btn danger" disabled>Hapus Permanen</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @endforeach
</div>

<script>
document.querySelectorAll('.reset-data-details').forEach(function (details) {
    const panel = details.closest('.dash-card').querySelector('.reset-data-panel');
    details.addEventListener('toggle', function () {
        panel.style.display = details.open ? 'block' : 'none';
    });
});

document.querySelectorAll('.reset-data-form').forEach(function (form) {
    const expected = form.dataset.confirm;
    const input = form.querySelector('.reset-data-input');
    const button = form.querySelector('button[type="submit"]');
    input.addEventListener('input', function () {
        button.disabled = input.value.trim() !== expected;
    });
    form.addEventListener('submit', function (e) {
        if (input.value.trim() !== expected || ! confirm('Yakin? Data yang dihapus TIDAK BISA dikembalikan.')) {
            e.preventDefault();
        }
    });
});
</script>

<div class="dash-card" style="margin-top:18px;">
    <div class="sub" style="margin-bottom:0;">
        Catatan: export RAK Bulanan mengunduh tahun berjalan ({{ $tahunSekarang }}). Untuk tahun lain, ubah parameter <code>tahun</code> di URL unduhan.
        Setiap unduhan tercatat di Log Aktivitas (jenis, waktu, pengguna, dan jumlah baris).
    </div>
</div>
@endsection
