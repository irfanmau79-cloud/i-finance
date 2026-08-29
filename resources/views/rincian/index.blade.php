@extends('layouts.app')

@section('activeNav', 'rincian')
@section('title', 'Rincian Realisasi')

@section('content')
@php
    $rupiah = fn (float $nilai) => fmt_rupiah($nilai);
    $persen = fn (float $nilai) => number_format($nilai, 2, ',', '.').' %';
@endphp

<div class="dash-card fullh-card" style="display:flex;flex-direction:column;">
    <h3 style="flex:0 0 auto;">Rincian Realisasi</h3>
    <div class="sub" style="flex:0 0 auto;">
        Sub Kegiatan &rsaquo; Kode Rekening &rsaquo; Tagging &middot; Tahun Anggaran
        <span id="rinc-tahun">{{ config('anggaran.tahun_aktif') }}</span>
    </div>

    <div class="tbl-tools" style="flex:0 0 auto;">
        <input type="text" id="rinc-filter" placeholder="Cari program / sub kegiatan / kode rekening / tagging&hellip;" value="{{ $filters['q'] }}">
        <button type="button" class="btn" id="rinc-buka" style="white-space:nowrap;">Buka Semua</button>
        <button type="button" class="btn" id="rinc-tutup" style="white-space:nowrap;">Tutup Semua</button>
    </div>

    <div style="overflow:auto;flex:1;min-height:0;border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi pivot" id="tbl-rincian">
            <thead><tr>
                <th>Uraian</th>
                <th class="num">Anggaran</th>
                <th class="num">Realisasi</th>
                <th class="num">Sisa Anggaran</th>
                <th class="num">% Realisasi</th>
            </tr></thead>
            <tbody id="rinc-body">
            @forelse ($tree as $iProgram => $program)
                @php($kProgram = 'p'.$iProgram)
                <tr class="row-lvl0" data-node="{{ $kProgram }}" data-teks="{{ Str::lower($program['nama']) }}">
                    <td><span class="uraian"><span class="tgl-slot"></span><span>{{ $program['nama'] }}</span></span></td>
                    <td class="num">{{ $rupiah($program['angka']['pagu']) }}</td>
                    <td class="num">{{ $rupiah($program['angka']['realisasi_aktual']) }}</td>
                    <td class="num">{{ $rupiah($program['angka']['sisa_tersedia']) }}</td>
                    <td class="num">{{ $persen($program['angka']['persentase_realisasi']) }}</td>
                </tr>

                @foreach ($program['sub'] as $iSub => $sub)
                    @php($kSub = $kProgram.'s'.$iSub)
                    <tr class="row-lvl1" data-node="{{ $kSub }}" data-induk="{{ $kProgram }}" data-teks="{{ Str::lower($sub['nama']) }}">
                        <td><span class="uraian ind1"><span class="tgl-slot"></span><span>{{ $sub['nama'] }}</span></span></td>
                        <td class="num">{{ $rupiah($sub['angka']['pagu']) }}</td>
                        <td class="num">{{ $rupiah($sub['angka']['realisasi_aktual']) }}</td>
                        <td class="num">{{ $rupiah($sub['angka']['sisa_tersedia']) }}</td>
                        <td class="num">{{ $persen($sub['angka']['persentase_realisasi']) }}</td>
                    </tr>

                    @foreach ($sub['rekening'] as $iRek => $rekening)
                        @php($kRek = $kSub.'k'.$iRek)
                        @php($namaRek = trim($rekening['kode'].' '.$rekening['uraian']))
                        <tr class="row-lvl2" data-node="{{ $kRek }}" data-induk="{{ $kProgram }} {{ $kSub }}" data-teks="{{ Str::lower($namaRek) }}">
                            <td><span class="uraian ind2"><span class="tgl-slot"></span><span>{{ $namaRek }}</span></span></td>
                            <td class="num">{{ $rupiah($rekening['angka']['pagu']) }}</td>
                            <td class="num">{{ $rupiah($rekening['angka']['realisasi_aktual']) }}</td>
                            <td class="num">{{ $rupiah($rekening['angka']['sisa_tersedia']) }}</td>
                            <td class="num">{{ $persen($rekening['angka']['persentase_realisasi']) }}</td>
                        </tr>

                        @foreach ($rekening['tagging'] as $tagging)
                            <tr class="row-lvl3" data-induk="{{ $kProgram }} {{ $kSub }} {{ $kRek }}" data-teks="{{ Str::lower($tagging['nama']) }}">
                                <td><span class="uraian ind3"><span class="spacer"></span>{{ $tagging['nama'] }}</span></td>
                                <td class="num">{{ $rupiah($tagging['angka']['pagu']) }}</td>
                                <td class="num">{{ $rupiah($tagging['angka']['realisasi_aktual']) }}</td>
                                <td class="num">{{ $rupiah($tagging['angka']['sisa_tersedia']) }}</td>
                                <td class="num">{{ $persen($tagging['angka']['persentase_realisasi']) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            @empty
                <tr id="rinc-kosong"><td colspan="5" style="text-align:center;color:var(--mut);padding:18px;">Tidak ada data</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var CARET = '<svg class="tgl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>';

    var body = document.getElementById('rinc-body');
    var filterInput = document.getElementById('rinc-filter');
    if (!body || !filterInput) return;

    var baris = Array.prototype.slice.call(body.querySelectorAll('tr[data-teks]'));
    if (!baris.length) return;

    // Caret hanya pada baris yang punya anak, sama seperti caret() di GAS.
    var punyaAnak = {};
    baris.forEach(function (tr) {
        (tr.dataset.induk || '').split(/\s+/).filter(Boolean).forEach(function (k) { punyaAnak[k] = true; });
    });
    baris.forEach(function (tr) {
        var slot = tr.querySelector('.tgl-slot');
        if (slot && punyaAnak[tr.dataset.node]) slot.outerHTML = CARET;
    });

    // Awalnya semua tertutup - rincOpen={} di GAS.
    var terbuka = {};

    function cocok(tr, q) { return tr.dataset.teks.indexOf(q) >= 0; }

    function render() {
        var q = (filterInput.value || '').toLowerCase().trim();

        // Saat menyaring, cabang yang memuat kecocokan ikut dibuka sendiri -
        // pra-pindai kecocokan pada seluruh keturunan tiap simpul.
        var adaCocok = {};
        if (q) {
            baris.forEach(function (tr) {
                if (!cocok(tr, q)) return;
                (tr.dataset.induk || '').split(/\s+/).filter(Boolean).forEach(function (k) { adaCocok[k] = true; });
            });
        }

        // GAS membangun ulang <tbody> tiap render dan HANYA memasukkan baris
        // yang terlihat. Itu penting: pola selang-seling berasal dari
        // `tbody tr:nth-child(even)`, yang menghitung baris DI DALAM DOM.
        // Kalau baris tertutup cuma disembunyikan (hidden) - bukan dilepas -
        // baris tersembunyi itu tetap ikut dihitung, sehingga warna Program
        // jadi acak: ada yang gelap, ada yang terang, tanpa pola.
        var terlihat = [];

        baris.forEach(function (tr) {
            var induk = (tr.dataset.induk || '').split(/\s+/).filter(Boolean);
            var simpul = tr.dataset.node;
            var sendiriCocok = !q || cocok(tr, q);
            var keturunanCocok = !!(simpul && adaCocok[simpul]);
            var indukCocok = induk.some(function (k) { return q && cocok2(k, q); });

            // Tampil bila: tidak menyaring, atau baris ini cocok, atau salah
            // satu induknya cocok, atau ada keturunannya yang cocok.
            var lolos = !q || sendiriCocok || keturunanCocok || indukCocok;

            // Induk harus dalam keadaan terbuka. Saat menyaring, simpul yang
            // cocok atau memuat kecocokan dianggap terbuka.
            var indukTerbuka = induk.every(function (k) {
                return q ? (adaCocok[k] || cocok2(k, q)) : terbuka[k];
            });

            if (lolos && indukTerbuka) terlihat.push(tr);

            var caret = tr.querySelector('.tgl');
            if (caret) {
                var buka = q ? (adaCocok[simpul] || sendiriCocok) : !!terbuka[simpul];
                caret.classList.toggle('open', buka);
            }
        });

        // Lepas semua baris lalu pasang ulang yang terlihat saja, urut sesuai
        // susunan aslinya - hasilnya sama persis dengan DOM keluaran GAS.
        var wadah = document.createDocumentFragment();
        terlihat.forEach(function (tr) { wadah.appendChild(tr); });

        while (body.firstChild) { body.removeChild(body.firstChild); }
        barisKosong = null;

        if (terlihat.length) {
            body.appendChild(wadah);
        } else {
            barisKosong = document.createElement('tr');
            barisKosong.innerHTML = '<td colspan="5" style="text-align:center;color:var(--mut);padding:18px;">Tidak ada data</td>';
            body.appendChild(barisKosong);
        }
    }

    // Apakah simpul (baris induk) itu sendiri cocok dengan kata kunci.
    var teksSimpul = {};
    baris.forEach(function (tr) { if (tr.dataset.node) teksSimpul[tr.dataset.node] = tr.dataset.teks; });
    function cocok2(simpul, q) { return (teksSimpul[simpul] || '').indexOf(q) >= 0; }

    var barisKosong = null;

    baris.forEach(function (tr) {
        if (!tr.dataset.node || !punyaAnak[tr.dataset.node]) return;
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', function () {
            var k = tr.dataset.node;
            terbuka[k] = !terbuka[k];
            filterInput.value = '';
            render();
        });
    });

    filterInput.addEventListener('input', render);

    document.getElementById('rinc-buka').addEventListener('click', function () {
        terbuka = {};
        baris.forEach(function (tr) { if (tr.dataset.node) terbuka[tr.dataset.node] = true; });
        render();
    });

    document.getElementById('rinc-tutup').addEventListener('click', function () {
        terbuka = {};
        render();
    });

    render();
})();
</script>
@endsection
