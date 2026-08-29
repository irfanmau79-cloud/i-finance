@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import Data Nota Pencairan Dana')

@section('content')
<style>
    .imp-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .imp-head h3 { margin:0; }
    .imp-year { display:inline-block; background:var(--navy-l); color:var(--navy); font-weight:700;
        font-size:12px; letter-spacing:.02em; padding:6px 12px; border-radius:999px; white-space:nowrap; }
    .imp-lead { color:var(--mut); max-width:58ch; margin-top:6px; line-height:1.6; }

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

    .imp-kol { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:10px; margin-top:14px; }
    .imp-kol-item { border:1px solid var(--line); border-radius:var(--radius-sm); padding:10px 14px; background:#fff; }
    .imp-kol-nama { font-size:12px; color:var(--mut); display:flex; align-items:center; gap:6px; }
    .imp-kol-wajib { color:var(--err); font-weight:700; }
    .imp-kol-isi { font-weight:600; margin-top:3px; word-break:break-word; }
</style>

<div class="dash-card">
    <div class="imp-head">
        <div>
            <h3>Import Data Nota Pencairan Dana</h3>
            <div class="imp-lead">
                Import Data Nota Pencairan Dana (NPD) dengan status Selesai. NPD yang nomornya
                sudah ada akan dilewati, bukan ditimpa. Silakan unduh template di bawah ini.
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
            <div class="imp-step-t">Unduh templatenya</div>
            <div class="imp-step-d">Isi data mulai baris 5, tiga baris pertama biarkan apa adanya.</div>
        </div>
        <div class="imp-step">
            <div class="imp-step-no">2</div>
            <div class="imp-step-t">Isi satu NPD per baris</div>
            <div class="imp-step-d">Contoh isian tiap kolom ada di bawah.</div>
        </div>
        <div class="imp-step">
            <div class="imp-step-no">3</div>
            <div class="imp-step-t">Unggah &amp; periksa</div>
            <div class="imp-step-d">Isinya ditampilkan dulu sebelum ada yang tersimpan.</div>
        </div>
    </div>

    <div class="tbl-tools" style="margin-top:16px;">
        <a class="btn" href="{{ route('manajemen-data.import.npd-historis.template') }}">Unduh Template Import NPD</a>
    </div>

    <form method="POST" action="{{ route('manajemen-data.import.npd-historis.store') }}" enctype="multipart/form-data" class="imp-form">
        @csrf

        <label class="imp-drop" id="imp-drop">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div>
                <div class="imp-drop-t" id="imp-drop-nama">Pilih berkas atau seret ke sini</div>
                <div class="imp-drop-d">Format .xlsx atau .xls &middot; untuk Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</div>
            </div>
            <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
        </label>

        <div class="nav" style="margin-top:20px;">
            <a class="btn" href="{{ route('manajemen-data.index') }}">Kembali</a>
            <button class="btn prim">Unggah &amp; Periksa</button>
        </div>
    </form>
</div>

<div class="dash-card" style="margin-top:16px;">
    <h3>Contoh Isi Kolom</h3>
    <div class="sub">Urut sesuai kolom pada template. Bertanda <span class="imp-kol-wajib">*</span> wajib diisi.</div>

    <div class="imp-kol">
        @foreach ($petunjukKolom as $kolom)
            <div class="imp-kol-item">
                <div class="imp-kol-nama">
                    {{ $kolom[0] }}
                    @if ($kolom[1] === 'Ya')<span class="imp-kol-wajib" title="Wajib diisi">*</span>@endif
                </div>
                <div class="imp-kol-isi">{{ $kolom[4] }}</div>
            </div>
        @endforeach
    </div>
</div>

<div class="dash-card" style="margin-top:16px;">
    <h3>Riwayat Import</h3>
    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Diunggah oleh</th>
                    <th>Waktu Unggah</th>
                    <th>Waktu Simpan</th>
                    <th>Status</th>
                    <th style="text-align:right;">Baris</th>
                    <th style="text-align:right;">Berhasil</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($riwayat as $batch)
                    <tr>
                        <td>{{ $batch->nama_file }}</td>
                        <td>{{ $batch->user?->username ?? '—' }}</td>
                        <td>{{ $batch->created_at?->format('d-m-Y H:i') }}</td>
                        <td>{{ $batch->executed_at?->format('d-m-Y H:i') ?? '—' }}</td>
                        <td>{{ $batch->status }}</td>
                        <td style="text-align:right;">{{ number_format((int) $batch->total_baris, 0, ',', '.') }}</td>
                        <td style="text-align:right;">{{ number_format((int) $batch->jumlah_berhasil, 0, ',', '.') }}</td>
                        <td style="text-align:center;">
                            <a class="btn" href="{{ route('manajemen-data.import.npd-historis.preview', $batch) }}">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--mut);padding:20px;">Belum ada import.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $riwayat->links() }}
</div>

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
