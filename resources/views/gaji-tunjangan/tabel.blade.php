@extends('layouts.app')

@section('activeNav', $navKey)
@section('title', $judul)

@section('content')
@php
    $rp = fn ($nilai) => fmt_rupiah($nilai);
    $bolehKelola = in_array(\App\Helpers\GuestSession::role(), config('gaji_tunjangan.role_kelola'), true);
    // Jumlah kolom tabel, dipakai baris "tidak ada data" agar rentangnya pas.
    $lebar = ['gaji' => 23, 'total' => 15, 'beban' => 12, 'kondisi' => 12][$jenis];
    // TPP Beban Kerja & TPP Kondisi Kerja bentuk tabelnya sama persis, jadi
    // keduanya memakai partial 'tpp' yang sama.
    $partial = in_array($jenis, ['beban', 'kondisi'], true) ? 'tpp' : $jenis;
@endphp

<style>
    /* Header dua tingkat ala cetakan SIPD. table.realisasi membuat setiap
       <th> sticky di top:0; untuk baris kedua posisinya digeser turun
       setinggi baris pertama supaya keduanya tidak saling menumpuk. */
    .gt-tabel thead tr:nth-child(2) th { top: 33px; }
    .gt-tabel th.grup { text-align: center; border-left: 1px solid var(--line); }
    .gt-tabel th.num, .gt-tabel td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .gt-tabel td.ident { white-space: normal; min-width: 190px; }
    .gt-tabel th { cursor: default; }
    .gt-filter { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 14px; }
    .gt-filter label.fl { margin: 0 0 5px; }
    .gt-filter .f { display: flex; flex-direction: column; }
    .gt-filter .f.tumbuh { flex: 1; min-width: 180px; }
    .gt-persen { display: inline-block; padding: 3px 10px; border-radius: 50px; background: var(--navy-l); color: var(--navy); font-weight: 700; font-size: 11.5px; }
</style>

<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Gaji dan Tunjangan</b> / {{ $judul }}</div>
        <div class="ph-title">{{ $judul }}</div>
    </div>
    @if ($bolehKelola)
        <div class="ph-actions"><a class="btn" href="{{ route('gaji-tunjangan.import.create') }}">Import Data</a></div>
    @endif
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif

@if ($terkunci)
    @include('gaji-tunjangan._gate')
@else

<div class="dash-card">
    @if ($terbatas)
        <div class="sumbar ok" style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <span>Menampilkan data untuk NIP <b>{{ $nipSesi }}</b> saja.</span>
            <form method="POST" action="{{ route('gaji-tunjangan.ganti-nip') }}" style="margin:0;">
                @csrf
                <button class="btn" type="submit">Ganti NIP</button>
            </form>
        </div>
    @endif

    <form method="GET" class="gt-filter">
        <div class="f">
            <label class="fl" for="gt-mode">Tampilan</label>
            <select id="gt-mode" name="mode">
                <option value="bulan" @selected($mode === 'bulan')>Bulanan</option>
                <option value="tahun" @selected($mode === 'tahun')>Kumulatif</option>
            </select>
        </div>
        <div class="f">
            <label class="fl" for="gt-bulan">Bulan</label>
            <select id="gt-bulan" name="bulan" @disabled($mode === 'tahun')>
                @foreach ($namaBulan as $nomor => $nama)
                    <option value="{{ $nomor }}" @selected($nomor === $bulan)>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="f">
            <label class="fl" for="gt-tahun">Tahun</label>
            <select id="gt-tahun" name="tahun">
                @foreach ($tahunTersedia as $t)
                    <option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="f tumbuh">
            <label class="fl" for="gt-cari">Pencarian</label>
            <input type="text" id="gt-cari" name="q" value="{{ $cari }}" placeholder="Cari Nama / NIP / Jabatan&hellip;">
        </div>
        <div class="f">
            <button class="btn prim" type="submit">Tampilkan</button>
        </div>
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi gt-tabel">
            @include('gaji-tunjangan.kolom.'.$partial)

            <tbody>
                @forelse ($baris as $r)
                    @include('gaji-tunjangan.baris.'.$partial, ['r' => $r, 'rp' => $rp])
                @empty
                    <tr>
                        <td colspan="{{ $lebar }}" style="text-align:center;padding:26px;color:var(--mut);">
                            Belum ada data {{ $judul }} untuk periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $baris->withQueryString()->links() }}
</div>

@endif
@endsection
