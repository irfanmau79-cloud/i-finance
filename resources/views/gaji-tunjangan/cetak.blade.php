@extends('layouts.app')

@section('activeNav', 'gt-cetak')
@section('title', 'Cetak Rincian Penghasilan')

@section('content')
<style>
    .gtc-periode { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
    .gtc-periode label { display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px;
        border: 1px solid var(--line); border-radius: 50px; font-size: 12.5px; font-weight: 600;
        color: var(--navy); cursor: pointer; background: #fff; }
    .gtc-periode label:hover { background: var(--navy-l); border-color: var(--navy); }
    .gtc-pd { margin-top: 10px; display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px; }
    .gtc-pd .item { border: 1px solid var(--line); border-radius: 8px; padding: 9px 11px; }
    .gtc-pd .item .nm { font-size: 11.5px; font-weight: 700; color: var(--navy); }
    .gtc-pd input { margin-top: 4px; }
</style>

<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Gaji dan Tunjangan</b> / Cetak Rincian Penghasilan</div>
        <div class="ph-title">Cetak Rincian Penghasilan</div>
    </div>
</div>

@if ($errors->any())
    <div class="err-box" style="display:block;margin-bottom:16px;">{{ $errors->first() }}</div>
@endif

<div class="dash-card">
    <form method="POST" action="{{ route('gaji-tunjangan.rincian.store') }}" id="gtc-form">
        @csrf

        <label class="fl" for="gtc-nip">Nama Pegawai</label>
        <select id="gtc-nip" name="nip" data-cari required>
            <option value="">— Pilih Pegawai —</option>
            @foreach ($pegawai as $p)
                <option value="{{ $p['nip'] }}"
                        data-sub="{{ $p['nip'] }}{{ $p['jabatan'] ? ' · '.$p['jabatan'] : '' }}"
                        data-nama="{{ $p['nama'] }}"
                        data-jabatan="{{ $p['jabatan'] }}"
                        @selected(old('nip') === $p['nip'])>{{ $p['nama'] }}</option>
            @endforeach
        </select>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <div style="flex:2;min-width:240px;">
                <label class="fl" for="gtc-nama">Nama pada Dokumen</label>
                <input type="text" id="gtc-nama" name="nama" value="{{ old('nama') }}" required>
            </div>
            <div style="flex:2;min-width:240px;">
                <label class="fl" for="gtc-jabatan">Jabatan pada Dokumen</label>
                <input type="text" id="gtc-jabatan" name="jabatan" value="{{ old('jabatan') }}">
            </div>
            <div style="flex:1;min-width:130px;">
                <label class="fl" for="gtc-tahun">Tahun</label>
                <select id="gtc-tahun" name="tahun" required>
                    @foreach ($tahunTersedia as $t)
                        <option value="{{ $t }}" @selected((int) old('tahun', $tahunTersedia[0]) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <label class="fl">Periode Penghasilan</label>
        <div class="gtc-periode" id="gtc-periode">
            @foreach ($namaBulan as $nomor => $nama)
                <label>
                    <input type="checkbox" name="periode[]" value="{{ $nomor }}"
                           @checked(in_array((string) $nomor, old('periode', []), true))>
                    {{ $nama }}
                </label>
            @endforeach
        </div>

        <label class="fl" style="display:flex;align-items:center;gap:9px;">
            <input type="checkbox" id="gtc-adapd" name="ada_pd" value="1" @checked(old('ada_pd'))>
            Sertakan Penghasilan Perjalanan Dinas
        </label>
        <div id="gtc-pd" class="gtc-pd" hidden></div>

        <label class="fl" for="gtc-ttd">Penandatangan</label>
        <select id="gtc-ttd" name="penandatangan" required>
            @foreach ($penandatangan as $kunci => $orang)
                <option value="{{ $kunci }}" @selected(old('penandatangan') === $kunci)>
                    {{ $orang['nama'] }} — {{ $orang['jabatan'] }} — {{ $orang['pangkat'] }}
                </option>
            @endforeach
        </select>

        <button class="btn prim" style="margin-top:18px;">Cetak Dokumen</button>
    </form>
</div>

<script>
(function () {
    'use strict';

    var selPegawai = document.getElementById('gtc-nip');
    var inpNama    = document.getElementById('gtc-nama');
    var inpJabatan = document.getElementById('gtc-jabatan');
    var selTahun   = document.getElementById('gtc-tahun');
    var cbPd       = document.getElementById('gtc-adapd');
    var kotakPd    = document.getElementById('gtc-pd');
    var periode    = document.getElementById('gtc-periode');
    var NAMA_BULAN = @json($namaBulan);

    function rupiah(n) {
        return Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bulanTerpilih() {
        return Array.prototype.slice
            .call(periode.querySelectorAll('input[type=checkbox]:checked'))
            .map(function (c) { return Number(c.value); })
            .sort(function (a, b) { return a - b; });
    }

    // Identitas terisi otomatis dari data terbaru, tetapi tetap bisa disunting
    // sebelum dokumen dibuat.
    selPegawai.addEventListener('change', function () {
        var opt = selPegawai.selectedOptions[0];
        if (! opt || ! opt.value) return;
        inpNama.value = opt.dataset.nama || '';
        inpJabatan.value = opt.dataset.jabatan || '';
        tarikUangHarian();
    });

    /**
     * Nominal Uang Harian ditarik dari data Perjalanan Dinas dan bersifat
     * read-only: yang tercetak di surat selalu angka yang sama dengan yang
     * terlihat di Dashboard Perjalanan Dinas, bukan angka ketikan.
     */
    function tarikUangHarian() {
        if (! cbPd.checked) { kotakPd.hidden = true; kotakPd.innerHTML = ''; return; }

        var bulan = bulanTerpilih();
        kotakPd.hidden = false;

        if (! selPegawai.value || bulan.length === 0) {
            kotakPd.innerHTML = '<div class="sub" style="margin:0;">Pilih pegawai dan minimal satu periode untuk menarik Uang Harian.</div>';
            return;
        }

        kotakPd.innerHTML = '<div class="sub" style="margin:0;">Menarik data Uang Harian…</div>';

        fetch(@json(route('gaji-tunjangan.rincian.uang-harian')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            },
            body: JSON.stringify({ nip: selPegawai.value, tahun: Number(selTahun.value), periode: bulan })
        })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
        .then(function (data) {
            kotakPd.innerHTML = bulan.map(function (b) {
                return '<div class="item"><div class="nm">' + NAMA_BULAN[b] + '</div>' +
                       '<input type="text" value="Rp ' + rupiah(data.nominal[b]) + '" readonly></div>';
            }).join('');
        })
        .catch(function () {
            kotakPd.innerHTML = '<div class="err-box" style="display:block;margin:0;">Gagal menarik data Uang Harian. Coba lagi.</div>';
        });
    }

    cbPd.addEventListener('change', tarikUangHarian);
    selTahun.addEventListener('change', tarikUangHarian);
    periode.addEventListener('change', tarikUangHarian);

    tarikUangHarian();
})();
</script>
@endsection
