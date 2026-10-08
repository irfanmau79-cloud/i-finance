@extends('layouts.app')

@section('activeNav', 'sp-data')
@section('title', 'Data Surat Perintah')

@section('content')
@php
    $role = auth()->user()->role;
    // Toggle Monitoring SP & kelola data: PPTK/superadmin (setPantauSP di GAS).
    $bolehKelola = in_array($role, ['pptk', 'superadmin'], true);
    // Toggle Sumber NPD sengaja lebih luas: BPP ikut boleh (setSumberNPD di GAS).
    $bolehSumber = in_array($role, ['pptk', 'bpp', 'superadmin'], true);
@endphp

<div class="dash-card wf-card">
    <h3>Data Surat Perintah</h3>
    <div class="sub">Daftar seluruh Surat Perintah yang telah dicatat.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('surat-perintah.create') }}" class="btn prim" style="white-space:nowrap;">+ Tambah SP</a>
        <a href="{{ route('surat-perintah.export-pdf') }}" class="btn" style="white-space:nowrap;">Export PDF</a>
        <input type="text" id="spd-search" placeholder="Cari Nomor SP, Unit, Tujuan, atau Keterangan&hellip;">
    </div>

    @error('duplikat')
        <div class="err-box" style="display:block;">{{ $message }}</div>
    @enderror

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        {{-- Lebar kolom ditetapkan (jumlahnya 100%). Kolom Jenis sudah dilebur
             ke kolom Status/Jenis Pembayaran, dan seluruh lebarnya diberikan
             ke Keterangan - kolom yang isinya paling panjang. --}}
        <table class="realisasi tbl-fixed" id="spd-table" style="min-width:1000px;">
            <colgroup>
                <col style="width:15%;"><col style="width:8%;"><col style="width:12%;"><col style="width:11%;">
                <col style="width:24%;"><col style="width:11%;"><col style="width:19%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Nomor SP</th>
                    <th>Tanggal SP</th>
                    <th>Unit Kerja</th>
                    <th>Tujuan Transfer</th>
                    <th>Keterangan</th>
                    <th>Status/Jenis Pembayaran</th>
                    <th class="mid">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suratPerintahs as $suratPerintah)
                    {{-- Keterangan "(Duplikat-n)" HANYA ditampilkan di halaman
                         ini; Monitoring SP dan NPD memakai nomor_sp apa adanya. --}}
                    <tr data-cari="{{ Str::lower($suratPerintah->nomorBerlabel().' '.$suratPerintah->unit_kerja.' '.$suratPerintah->tujuan_transfer.' '.$suratPerintah->keterangan) }}">
                        <td style="font-weight:600;">
                            {{ $suratPerintah->nomorBerlabel() }}
                            @if ($suratPerintah->isReimburse() && $suratPerintah->induk)
                                <br><small class="sub">induk: {{ $suratPerintah->induk->nomor_sp }}</small>
                            @endif
                        </td>
                        <td>{{ $suratPerintah->tanggal_sp->format('d-m-Y') }}</td>
                        <td>{{ $suratPerintah->unit_kerja }}</td>
                        <td>{{ $suratPerintah->tujuan_transfer ?: '—' }}</td>
                        <td>{{ $suratPerintah->keterangan ?: '—' }}</td>
                        <td>
                            <span class="badge st-diterima">{{ $suratPerintah->status }}</span>
                            <div style="margin-top:6px;">
                                @if ($suratPerintah->isReimburse())
                                    <span class="badge" style="background:var(--warn-bg);color:var(--warn-teks);">Reimburse</span>
                                @else
                                    <span class="badge" style="background:var(--info-bg);color:var(--info);">UH/Akomodasi</span>
                                @endif
                            </div>
                        </td>
                        <td class="mid">
                            {{-- Ikon aksi sebaris DI ATAS kedua penanda, bukan di
                                 sebelahnya: empat ikon ditambah penanda tidak
                                 muat berdampingan tanpa melebarkan kolom Aksi
                                 dan memakan lebar Keterangan. --}}
                            <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                                <div class="aksi-wrap" style="width:auto;grid-template-columns:repeat({{ $bolehKelola ? ($suratPerintah->dapatDiduplikat() ? 4 : 3) : 1 }},30px);">
                                    @if ($suratPerintah->fileTersedia())
                                        <a class="ic-btn" title="Lihat SP" href="{{ route('surat-perintah.file', $suratPerintah) }}" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                    @else
                                        <button type="button" class="ic-btn" title="File tidak tersedia" style="opacity:.5;cursor:not-allowed;" onclick="alert('File tidak tersedia.');"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    @endif
                                    @if ($bolehKelola)
                                        <a class="ic-btn" title="Edit" href="{{ route('surat-perintah.edit', $suratPerintah) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                                        @if ($suratPerintah->dapatDiduplikat())
                                            <form method="POST" action="{{ route('surat-perintah.duplikat', $suratPerintah) }}" onsubmit="return confirm('Duplikat SP {{ $suratPerintah->nomorBerlabel() }}?\nDuplikat menjadi baris SP baru yang bisa dijadikan acuan NPD Perjalanan Dinas lain.');">
                                                @csrf
                                                <button type="submit" class="ic-btn" title="Duplikat" aria-label="Duplikat SP"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('surat-perintah.destroy', $suratPerintah) }}" onsubmit="return confirm('Hapus data SP {{ $suratPerintah->nomorBerlabel() }} secara permanen?\nData akan hilang dari Data SP dan Monitoring SP. Tindakan ini tidak bisa dibatalkan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ic-btn danger" title="Hapus SP (permanen)"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                        </form>
                                    @endif
                                </div>

                                @if ($bolehKelola || $bolehSumber)
                                    <span class="sp-toggles">
                                        @if ($bolehKelola)
                                            <span class="sp-toggle{{ $suratPerintah->dipantau ? ' aktif' : '' }}" title="{{ $suratPerintah->dipantau ? 'Tampil di Monitoring SP' : 'Disembunyikan dari Monitoring SP' }}">
                                                <span class="sp-toggle-lbl">Monitoring SP</span>
                                                <label class="sw">
                                                    <input type="checkbox" class="sp-toggle-flag" data-flag="dipantau"
                                                           data-on="Tampil di Monitoring SP" data-off="Disembunyikan dari Monitoring SP"
                                                           data-url="{{ route('surat-perintah.toggle-pantau', $suratPerintah) }}" @checked($suratPerintah->dipantau)>
                                                    <span class="sl"></span>
                                                </label>
                                            </span>
                                        @endif
                                        @if ($bolehSumber)
                                            <span class="sp-toggle sumber{{ $suratPerintah->sumber_npd ? ' aktif' : '' }}" title="{{ $suratPerintah->sumber_npd ? 'Muncul sbg sumber data di Pembuatan NPD' : 'Disembunyikan dari sumber data NPD' }}">
                                                <span class="sp-toggle-lbl">Sumber NPD</span>
                                                <label class="sw">
                                                    <input type="checkbox" class="sp-toggle-flag" data-flag="sumber_npd"
                                                           data-on="Muncul sbg sumber data di Pembuatan NPD" data-off="Disembunyikan dari sumber data NPD"
                                                           data-url="{{ route('surat-perintah.toggle-sumber-npd', $suratPerintah) }}" @checked($suratPerintah->sumber_npd)>
                                                    <span class="sl"></span>
                                                </label>
                                            </span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data surat perintah.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sub" style="margin-top:10px;">
        <strong>Monitoring SP</strong> mengatur tampil atau tidaknya SP di halaman Monitoring.
        <strong>Sumber NPD</strong> mengatur muncul atau tidaknya SP sebagai sumber data di Pembuatan NPD Perjalanan Dinas
        &mdash; mematikannya tidak menghapus SP dari Monitoring.
        <strong>Duplikat</strong> membuat baris SP baru bernomor sama untuk SP yang dibayarkan lewat lebih dari satu NPD;
        keterangan (Duplikat-n) hanya tampil di halaman ini.
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.sp-toggle-flag').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const url = checkbox.dataset.url;
            const flag = checkbox.dataset.flag;
            const sebelum = ! checkbox.checked;

            const kepingan = checkbox.closest('.sp-toggle');
            checkbox.disabled = true;
            if (kepingan) kepingan.classList.add('menunggu');

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
                .then(function (res) {
                    if (! res.ok) throw new Error('gagal');
                    return res.json();
                })
                .then(function (data) {
                    checkbox.checked = data[flag];
                    if (kepingan) {
                        kepingan.title = data[flag] ? checkbox.dataset.on : checkbox.dataset.off;
                        kepingan.classList.toggle('aktif', data[flag]);
                    }
                })
                .catch(function () {
                    checkbox.checked = sebelum;
                    alert('Gagal memperbarui. Silakan coba lagi.');
                })
                .finally(function () {
                    checkbox.disabled = false;
                    if (kepingan) kepingan.classList.remove('menunggu');
                });
        });
    });

    const cari = document.getElementById('spd-search');
    if (cari) {
        cari.addEventListener('input', function () {
            const q = cari.value.trim().toLowerCase();
            document.querySelectorAll('#spd-table tbody tr[data-cari]').forEach(function (row) {
                row.hidden = q !== '' && ! row.dataset.cari.includes(q);
            });
        });
    }
})();
</script>
@endsection
