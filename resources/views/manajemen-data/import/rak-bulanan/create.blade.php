@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import RAK Bulanan')

@section('content')
<style>
    .imp-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .imp-head h3 { margin:0; }
    .imp-year { display:inline-block; background:var(--navy-l); color:var(--navy); font-weight:700;
        font-size:12px; letter-spacing:.02em; padding:6px 12px; border-radius:999px; white-space:nowrap; }
    .imp-lead { color:var(--mut); max-width:56ch; margin-top:6px; line-height:1.6; }

    .imp-steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px; margin:20px 0 4px; }
    .imp-step { border:1px solid var(--line); border-radius:var(--radius-sm); padding:14px 16px; background:#fff; }
    .imp-step-no { width:24px; height:24px; border-radius:50%; background:var(--navy); color:#fff;
        font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; margin-bottom:8px; }
    .imp-step-t { font-weight:600; margin-bottom:2px; }
    .imp-step-d { color:var(--mut); font-size:13px; line-height:1.5; }

    .imp-form { margin-top:20px; border-top:1px solid var(--line); padding-top:20px; }
    .imp-drop { position:relative; display:flex; align-items:center; gap:14px; border:1.5px dashed var(--line);
        border-radius:var(--radius-sm); padding:18px 20px; background:#fbfcfe; cursor:pointer;
        transition:border-color .15s, background .15s; }
    .imp-drop:hover, .imp-drop.is-over { border-color:var(--navy); background:var(--navy-l); }
    .imp-drop input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .imp-drop svg { width:26px; height:26px; stroke:var(--navy); fill:none; stroke-width:1.8;
        stroke-linecap:round; stroke-linejoin:round; flex:none; }
    .imp-drop-t { font-weight:600; }
    .imp-drop-d { color:var(--mut); font-size:13px; margin-top:2px; }
</style>

<div class="dash-card">
    <div class="imp-head">
        <div>
            <h3>Import RAK Bulanan</h3>
            <div class="imp-lead">
                Rencana Anggaran Kas menentukan berapa dana yang direncanakan ditarik tiap bulan.
                Unduh formatnya, isi nilai per bulan, lalu unggah kembali di sini.
            </div>
        </div>
        <span class="imp-year">Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</span>
    </div>

    @if ($errors->any())
        <div class="err-box" style="display:block;margin-top:16px;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="imp-steps">
        <div class="imp-step">
            <div class="imp-step-no">1</div>
            <div class="imp-step-t">Unduh formatnya</div>
            <div class="imp-step-d">Berisi seluruh mata anggaran yang berlaku, siap diisi.</div>
        </div>
        <div class="imp-step">
            <div class="imp-step-no">2</div>
            <div class="imp-step-t">Isi nilai per bulan</div>
            <div class="imp-step-d">Januari sampai Desember. Kolom Total RAK terhitung sendiri.</div>
        </div>
        <div class="imp-step">
            <div class="imp-step-no">3</div>
            <div class="imp-step-t">Unggah &amp; periksa</div>
            <div class="imp-step-d">Isinya ditampilkan dulu sebelum ada yang tersimpan.</div>
        </div>
    </div>

    <div class="tbl-tools" style="margin-top:16px;">
        <a href="{{ route('manajemen-data.export', 'rak-bulanan') }}" class="btn">Unduh Format RAK Bulanan</a>
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.rak-bulanan.store') }}" enctype="multipart/form-data" class="imp-form">
        @csrf

        {{-- Tahun terkunci ke tahun anggaran berjalan, jadi tidak ada yang bisa dipilih.
             Nilainya sudah tampil sebagai chip di kepala halaman; kotak isian readonly
             hanya menyisakan kolom kosong yang menggantung di sebelah area unggah. --}}
        <input type="hidden" name="tahun" value="{{ old('tahun', $tahunSekarang) }}">

        <label class="imp-drop" id="imp-drop">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div>
                <div class="imp-drop-t" id="imp-drop-nama">Pilih berkas atau seret ke sini</div>
                <div class="imp-drop-d">Format .xlsx atau .xls &middot; untuk Tahun Anggaran {{ $tahunSekarang }}</div>
            </div>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </label>

        <div class="nav" style="margin-top:20px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Batal</a>
            <button type="submit" class="btn prim">Unggah &amp; Periksa</button>
        </div>
    </form>
</div>

@if (count($auditDuplikat))
<div class="dash-card" style="margin-top:16px;border-color:#f59e0b;">
    <h3>Audit Duplikat RAK Lama</h3>
    <div class="sub">Tidak ada data yang diubah. Kelompok berikut perlu diperiksa lebih dulu karena datanya terbaca ganda.</div>
    <ul>
        @foreach ($auditDuplikat as $group)
            <li><strong>{{ $group['tahun'] }} — {{ $group['sub_kegiatan'] }} — {{ $group['kode_rekening'] }}</strong>: {{ $group['jumlah_sumber'] }} sumber, {{ $group['jenis'] }}. {{ $group['strategi'] }}</li>
        @endforeach
    </ul>
</div>
@endif

<script>
    (function () {
        var zona = document.getElementById('imp-drop');
        var input = document.getElementById('file');
        var label = document.getElementById('imp-drop-nama');
        if (!zona || !input || !label) return;

        var kosong = label.textContent;

        input.addEventListener('change', function () {
            label.textContent = input.files.length ? input.files[0].name : kosong;
        });

        ['dragenter', 'dragover'].forEach(function (ev) {
            zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add('is-over'); });
        });

        ['dragleave', 'drop'].forEach(function (ev) {
            zona.addEventListener(ev, function () { zona.classList.remove('is-over'); });
        });

        // Seret-lepas: berkasnya dititipkan ke input supaya ikut terkirim saat submit.
        zona.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!e.dataTransfer || !e.dataTransfer.files.length) return;
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        });
    })();
</script>
@endsection
