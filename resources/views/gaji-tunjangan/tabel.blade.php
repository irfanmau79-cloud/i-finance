@extends('layouts.app')

@section('activeNav', $navKey)
@section('title', $judul)

@section('content')
@php
    // gtFmt() di GAS memakai toLocaleString('id-ID') tanpa desimal, bukan
    // fmt_rupiah() yang selalu menampilkan dua angka di belakang koma.
    $rp = fn ($nilai) => number_format((float) $nilai, 0, ',', '.');

    // gtPeriodeStr() di GAS.
    $periode = $mode === 'tahun'
        ? 'Kumulatif '.$tahun
        : $namaBulan[$bulan].' '.$tahun;

    // TPP Beban Kerja & TPP Kondisi Kerja bentuk tabelnya sama, hanya kolom
    // "Pengurang IKP" yang khusus Kondisi Kerja (lihat colPot di gtTabelTPP).
    $partial = in_array($jenis, ['beban', 'kondisi'], true) ? 'tpp' : $jenis;
@endphp

@include('gaji-tunjangan._styles')

<div class="dash-card gt-card">
    <h3>{{ $judul }}</h3>
    <div class="sub">{{ $subJudul }}</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($terkunci)
        @include('gaji-tunjangan._gate')
    @else
        @if ($terbatas)
            {{-- Bar identitas terverifikasi (#gt-authbar di GAS). --}}
            <div style="flex:0 0 auto;margin:6px 0 4px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:12.5px;color:var(--navy);">Menampilkan data untuk NIP <b>{{ $nipSesi }}</b></span>
                <form method="POST" action="{{ route('gaji-tunjangan.ganti-nip') }}" style="margin:0;">
                    @csrf
                    <button class="btn" style="padding:4px 12px;font-size:12px;" type="submit">Ganti NIP</button>
                </form>
            </div>
        @endif

        <form method="GET" class="gt-toolbar">
            <div class="gt-field">
                <label for="gt-mode">Tampilan</label>
                <select id="gt-mode" name="mode" class="gt-inp">
                    <option value="bulan" @selected($mode === 'bulan')>Bulanan</option>
                    <option value="tahun" @selected($mode === 'tahun')>Kumulatif</option>
                </select>
            </div>
            {{-- Mode Kumulatif menjumlah seluruh bulan, jadi pilihan Bulan
                 disembunyikan sama seperti gtOnModeChange() di GAS. --}}
            <div class="gt-field" id="gt-wrap-bulan" @style(['display:none' => $mode === 'tahun'])>
                <label for="gt-bulan">Bulan</label>
                <select id="gt-bulan" name="bulan" class="gt-inp">
                    @foreach ($namaBulan as $nomor => $nama)
                        <option value="{{ $nomor }}" @selected($nomor === $bulan)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="gt-field">
                <label for="gt-tahun">Tahun</label>
                <select id="gt-tahun" name="tahun" class="gt-inp">
                    @foreach ($tahunTersedia as $t)
                        <option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="gt-field gt-field-search">
                <label for="gt-cari">Cari</label>
                <div class="gt-search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input id="gt-cari" class="gt-inp" type="text" name="q" value="{{ $cari }}"
                           placeholder="Nama / NIP / jabatan&hellip;">
                </div>
            </div>
            <button class="gt-btn-tampil" type="submit">Tampilkan</button>
        </form>

        {{-- gtRenderTabel(): "N pegawai &middot; Agustus 2026 &middot; pencarian "kata"". --}}
        <div class="gt-info">
            {{ $baris->total() }} pegawai &middot; {{ $periode }}@if ($cari !== '') &middot; pencarian "{{ $cari }}" @endif
        </div>

        <div class="gt-tabel-box">
            <div class="gt-tabel-wrap">
                @if ($baris->total() === 0)
                    <div class="gt-empty">
                        Tidak ada data untuk periode <b>{{ $periode }}</b>{{ $cari !== '' ? ' dengan kata kunci tersebut' : '' }}.
                    </div>
                @else
                    <table class="gt-table @if ($jenis === 'total') gt-table-total @endif">
                        @include('gaji-tunjangan.kolom.'.$partial)
                        <tbody>
                            @foreach ($baris as $r)
                                @include('gaji-tunjangan.baris.'.$partial, ['r' => $r, 'rp' => $rp])
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @include('gaji-tunjangan._pager')
    @endif
</div>

<script>
(function () {
    'use strict';

    // gtOnModeChange(): pilihan Bulan tidak relevan di mode Kumulatif.
    var mode = document.getElementById('gt-mode');
    var wrapBulan = document.getElementById('gt-wrap-bulan');
    if (! mode || ! wrapBulan) return;

    mode.addEventListener('change', function () {
        wrapBulan.style.display = (mode.value === 'tahun') ? 'none' : '';
    });
})();
</script>
@endsection
