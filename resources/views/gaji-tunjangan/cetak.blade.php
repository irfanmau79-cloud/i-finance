@extends('layouts.app')

@section('activeNav', 'gt-cetak')
@section('title', 'Cetak Rincian Penghasilan')

@section('content')
@include('gaji-tunjangan._styles')

@php
    $periodeLama = array_map('intval', (array) old('periode', []));
@endphp

@if (session('success'))
    <div class="sumbar ok" style="margin-bottom:14px;"><span>{{ session('success') }}</span></div>
@endif

<div class="dash-card">
    <h3>Cetak Rincian Penghasilan</h3>

    <form class="gtc-form" method="POST" action="{{ route('gaji-tunjangan.rincian.store') }}" id="gtc-form">
        @csrf

        <div class="gtc-row">
            <label class="fl" for="gtc-nama">Nama Pegawai</label>
            <div class="gtc-search-wrap">
                <input id="gtc-nama" name="nama" class="gtc-inp" type="text" autocomplete="off"
                       placeholder="Ketik nama pegawai&hellip;" value="{{ old('nama') }}">
                <div id="gtc-suggest" class="gtc-suggest" style="display:none;"></div>
            </div>
        </div>
        <input type="hidden" id="gtc-nip-sel" name="nip" value="{{ old('nip') }}">

        <div class="gtc-row2">
            <div>
                {{-- Sama seperti GAS: NIP hanya ditampilkan, yang dikirim ke
                     server adalah NIP pegawai yang dipilih (#gtc-nip-sel). --}}
                <label class="fl" for="gtc-nip">NIP</label>
                <input id="gtc-nip" class="gtc-inp" type="text" placeholder="NIP" value="{{ old('nip') }}">
            </div>
            <div>
                <label class="fl" for="gtc-jabatan">Jabatan</label>
                <input id="gtc-jabatan" name="jabatan" class="gtc-inp" type="text"
                       placeholder="Jabatan" value="{{ old('jabatan') }}">
            </div>
        </div>

        <div class="gtc-row">
            <label class="fl" for="gtc-tahun">Tahun Penghasilan</label>
            <select id="gtc-tahun" name="tahun" class="gtc-inp" style="max-width:160px;">
                @foreach ($tahunTersedia as $t)
                    <option value="{{ $t }}" @selected((int) old('tahun', $tahunTersedia[0]) === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        <div class="gtc-row">
            <label class="fl">Pilih Periode Penghasilan <span class="sub">(boleh lebih dari satu)</span></label>
            <div id="gtc-bulan" class="gtc-bulan">
                @foreach ($namaBulan as $nomor => $nama)
                    <label class="gtc-chip {{ in_array($nomor, $periodeLama, true) ? 'on' : '' }}" id="gtc-chip-{{ $nomor }}">
                        <input type="checkbox" name="periode[]" value="{{ $nomor }}"
                               @checked(in_array($nomor, $periodeLama, true))>{{ $nama }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="gtc-row">
            <div class="gtc-toggle-line">
                <label class="gtc-switch">
                    <input type="checkbox" id="gtc-pd" name="ada_pd" value="1" @checked(old('ada_pd'))>
                    <span class="gtc-slider"></span>
                </label>
                <span>Penghasilan Perjalanan Dinas</span>
            </div>
            <div id="gtc-pd-wrap" style="display:none;margin-top:10px;">
                <label class="fl">Uang Harian Perjalanan Dinas per Periode (Rp)</label>
                <div id="gtc-pd-list" class="gtc-pd-list"></div>
            </div>
        </div>

        <div class="gtc-row">
            <label class="fl" for="gtc-ttd">Pilih Penandatangan</label>
            <select id="gtc-ttd" name="penandatangan" class="gtc-inp">
                @forelse ($penandatangan as $ttd)
                    <option value="{{ $ttd->kunci }}" @selected(old('penandatangan') === $ttd->kunci)>{{ $ttd->label() }}</option>
                @empty
                    <option value="">&mdash; Belum ada penandatangan &mdash;</option>
                @endforelse
            </select>
            @if ($penandatangan->isEmpty())
                <div class="sub" style="margin:0;">
                    Daftar penandatangan masih kosong. Superadmin dapat menambahkannya dari Data Pegawai.
                </div>
            @endif
        </div>

        <div class="gtc-actions">
            <button class="gt-btn-tampil" id="gtc-btn" type="submit">Cetak Dokumen</button>
            <div id="gtc-status" class="sub" style="align-self:center;margin:0;color:#c0392b;">{{ $errors->first() }}</div>
        </div>
    </form>
</div>

@if ($bolehKelolaTtd)
    {{--
        Panel kelola penandatangan - hanya superadmin. Ditaruh di kartu
        terpisah, DI LUAR form cetak di atas, karena form tidak boleh
        bersarang di dalam form lain.
    --}}
    <div class="dash-card" style="margin-top:16px;">
        <h3>Kelola Penandatangan</h3>
        <div class="sub">
            Khusus superadmin. Pejabat diambil dari Data Pegawai, lalu dipakai
            bersama oleh semua role pada pilihan Penandatangan di atas. Dokumen
            yang sudah dicetak tidak ikut berubah - identitas penandatangannya
            sudah dibekukan di dokumen masing-masing.
        </div>

        <div class="gt-tabel-box" style="margin:12px 0 18px;">
            <div class="gt-tabel-wrap" style="min-width:0;">
                @if ($semuaTtd->isEmpty())
                    <div class="gt-empty">Belum ada penandatangan.</div>
                @else
                    <table class="gt-table">
                        <thead>
                            <tr>
                                <th style="text-align:left;">Nama</th>
                                <th style="text-align:left;">Jabatan</th>
                                <th style="text-align:left;">Pangkat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($semuaTtd as $ttd)
                                <tr>
                                    <td><span class="gt-strong">{{ $ttd->nama }}</span></td>
                                    <td>{{ $ttd->jabatan }}</td>
                                    <td>{{ $ttd->pangkat }}</td>
                                    <td class="gt-ctr">
                                        <form method="POST" action="{{ route('gaji-tunjangan.rincian.ttd.destroy', $ttd) }}"
                                              style="margin:0;"
                                              onsubmit="return confirm('Hapus {{ $ttd->nama }} dari daftar penandatangan?\n\nDokumen yang sudah dicetak tidak terpengaruh.')">
                                            @csrf @method('DELETE')
                                            <button class="gtd-del" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('gaji-tunjangan.rincian.ttd.store') }}" class="gtc-form" style="margin-top:0;">
            @csrf

            <div class="gtc-row">
                <label class="fl" for="ttd-pegawai">Ambil dari Data Pegawai</label>
                <select id="ttd-pegawai" name="ttd_pegawai_id" class="gtc-inp" data-cari>
                    <option value="">&mdash; Pilih Pegawai &mdash;</option>
                    @foreach ($pegawaiOpd as $p)
                        <option value="{{ $p->id }}"
                                data-sub="{{ $p->nip }}{{ $p->jabatan ? ' · '.$p->jabatan : '' }}"
                                data-nama="{{ $p->nama }}"
                                data-jabatan="{{ $p->jabatan }}"
                                data-pangkat="{{ $p->pangkat }}"
                                @selected((int) old('ttd_pegawai_id') === $p->id)>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Terisi otomatis dari pegawai terpilih, tetapi masih bisa
                 disunting: redaksi pada surat kadang berbeda dari Data
                 Pegawai (mis. penulisan gelar). --}}
            <div class="gtc-row2">
                <div>
                    <label class="fl" for="ttd-nama">Nama pada Surat</label>
                    <input id="ttd-nama" name="ttd_nama" class="gtc-inp" type="text" value="{{ old('ttd_nama') }}">
                </div>
                <div>
                    <label class="fl" for="ttd-jabatan">Jabatan</label>
                    <input id="ttd-jabatan" name="ttd_jabatan" class="gtc-inp" type="text" value="{{ old('ttd_jabatan') }}">
                </div>
            </div>

            <div class="gtc-row" style="max-width:340px;">
                <label class="fl" for="ttd-pangkat">Pangkat</label>
                <input id="ttd-pangkat" name="ttd_pangkat" class="gtc-inp" type="text" value="{{ old('ttd_pangkat') }}">
            </div>

            <div class="gtc-actions">
                <button class="gt-btn-tampil" type="submit">Tambah Penandatangan</button>
                <div class="sub" style="align-self:center;margin:0;color:#c0392b;">{{ $errors->ttd->first() }}</div>
            </div>
        </form>
    </div>

    <script>
    (function () {
        'use strict';

        // Identitas penandatangan baru terisi dari pegawai yang dipilih.
        var sel = document.getElementById('ttd-pegawai');
        var isian = {
            nama: document.getElementById('ttd-nama'),
            jabatan: document.getElementById('ttd-jabatan'),
            pangkat: document.getElementById('ttd-pangkat')
        };

        sel.addEventListener('change', function () {
            var opt = sel.selectedOptions[0];
            if (! opt || ! opt.value) return;
            isian.nama.value = opt.dataset.nama || '';
            isian.jabatan.value = opt.dataset.jabatan || '';
            isian.pangkat.value = opt.dataset.pangkat || '';
        });
    })();
    </script>
@endif

<script>
(function () {
    'use strict';

    // Daftar pegawai disiapkan server (gtDaftarPegawai() di GAS memuatnya
    // lewat google.script.run saat halaman dibuka).
    var PEG = @json($pegawai);
    var NAMA_BULAN = @json($namaBulan);
    var URL_UH = @json(route('gaji-tunjangan.rincian.uang-harian'));
    var CSRF = document.querySelector('meta[name=csrf-token]').content;

    var inpNama   = document.getElementById('gtc-nama');
    var boxSaran  = document.getElementById('gtc-suggest');
    var nipSel    = document.getElementById('gtc-nip-sel');
    var inpNip    = document.getElementById('gtc-nip');
    var inpJab    = document.getElementById('gtc-jabatan');
    var selTahun  = document.getElementById('gtc-tahun');
    var kotakChip = document.getElementById('gtc-bulan');
    var cbPd      = document.getElementById('gtc-pd');
    var wrapPd    = document.getElementById('gtc-pd-wrap');
    var listPd    = document.getElementById('gtc-pd-list');

    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function fmt(n) { return Number(n || 0).toLocaleString('id-ID'); }

    // ---- Autocomplete pegawai (gtcCariPegawai/gtcPilih) ----
    function cariPegawai() {
        var q = (inpNama.value || '').trim().toLowerCase();
        var hasil = PEG.filter(function (p) {
            return (String(p.nama) + ' ' + String(p.nip)).toLowerCase().indexOf(q) >= 0;
        }).slice(0, 30);

        if (! hasil.length) {
            boxSaran.style.display = 'block';
            boxSaran.innerHTML = '<div class="none">Tidak ada pegawai cocok.</div>';
            return;
        }

        boxSaran.innerHTML = hasil.map(function (p) {
            return '<div class="it" data-nip="' + esc(p.nip) + '">' +
                '<div class="n">' + esc(p.nama) + '</div>' +
                '<div class="m">' + esc(p.nip) + ' &middot; ' + esc(p.jabatan) + '</div></div>';
        }).join('');
        boxSaran.style.display = 'block';
    }

    function pilihPegawai(nip) {
        var p = null;
        for (var i = 0; i < PEG.length; i++) { if (PEG[i].nip === nip) { p = PEG[i]; break; } }
        if (! p) return;

        inpNama.value = p.nama;
        nipSel.value = p.nip;
        inpNip.value = p.nip;
        inpJab.value = p.jabatan || '';
        boxSaran.style.display = 'none';
        tarikUangHarian();
    }

    inpNama.addEventListener('input', cariPegawai);
    inpNama.addEventListener('focus', cariPegawai);

    boxSaran.addEventListener('click', function (e) {
        var it = e.target.closest('.it');
        if (it) pilihPegawai(it.dataset.nip);
    });

    document.addEventListener('click', function (e) {
        var w = document.querySelector('.gtc-search-wrap');
        if (w && ! w.contains(e.target)) boxSaran.style.display = 'none';
    });

    // ---- Chip periode (gtcChipToggle) ----
    function bulanTerpilih() {
        return Array.prototype.slice
            .call(kotakChip.querySelectorAll('input:checked'))
            .map(function (c) { return Number(c.value); })
            .sort(function (a, b) { return a - b; });
    }

    kotakChip.addEventListener('change', function (e) {
        var cb = e.target;
        if (cb.type !== 'checkbox') return;
        cb.closest('.gtc-chip').classList.toggle('on', cb.checked);
        tarikUangHarian();
    });

    // ---- Uang Harian Perjalanan Dinas (gtcRenderPDList/gtcTarikUH) ----
    // Nilainya read-only: yang tercetak selalu sama dengan Dashboard
    // Perjalanan Dinas, bukan angka ketikan.
    function tarikUangHarian() {
        wrapPd.style.display = cbPd.checked ? 'block' : 'none';
        if (! cbPd.checked) return;

        var bulan = bulanTerpilih();

        if (! nipSel.value) {
            listPd.innerHTML = '<div class="sub">Pilih pegawai dulu untuk menarik data Uang Harian.</div>';
            return;
        }
        if (! bulan.length) {
            listPd.innerHTML = '<div class="sub">Pilih periode dulu di atas.</div>';
            return;
        }

        listPd.innerHTML = bulan.map(function (b) {
            return '<div class="gtc-pd-item"><label>' + esc(NAMA_BULAN[b]) + '</label>' +
                '<input class="gtc-inp" data-bln="' + b + '" type="text" value="&hellip;" readonly ' +
                'style="background:#f1f5f9;color:var(--ink);cursor:default;"></div>';
        }).join('');

        fetch(URL_UH, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({ nip: nipSel.value, tahun: Number(selTahun.value), periode: bulan })
        })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
        .then(function (data) {
            listPd.querySelectorAll('input[data-bln]').forEach(function (inp) {
                inp.value = fmt(data.nominal[inp.getAttribute('data-bln')]);
            });
        })
        .catch(function () {
            listPd.querySelectorAll('input[data-bln]').forEach(function (inp) { inp.value = '0'; });
            var st = document.getElementById('gtc-status');
            st.style.color = '#c0392b';
            st.textContent = 'Gagal menarik Uang Harian. Coba lagi.';
        });
    }

    cbPd.addEventListener('change', tarikUangHarian);
    selTahun.addEventListener('change', tarikUangHarian);

    tarikUangHarian();
})();
</script>
@endsection
