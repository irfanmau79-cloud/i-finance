{{--
    Kotak cari + penyaring Bulan/Tahun pada daftar Realisasi SP2D (UP/GU/TU
    dan LS memakai formulir yang sama).

    Bulan dan Tahun menyaring menurut TANGGAL SP2D - tanggal realisasinya -
    bukan tanggal SPM (lihat Spm::tanggalRealisasiSql()). Memilih salah satunya
    langsung menerapkan penyaringnya, tanpa perlu menekan Cari.

    $rute       : nama rute daftar (spm.ls.index / spm.up-gu.index)
    $placeholder: petunjuk kotak cari
    $tahunList  : tahun yang punya data, terbaru di atas
--}}
@php
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $bulanDipilih = (int) request('bulan');
    $tahunDipilih = (int) request('tahun');
@endphp
<form method="GET" action="{{ route($rute) }}" class="spm-cari">
    <input type="text" name="cari" placeholder="{{ $placeholder }}" value="{{ request('cari') }}">

    <select name="bulan" class="spm-saring" aria-label="Saring menurut bulan SP2D" title="Bulan menurut Tanggal SP2D" onchange="this.form.submit()">
        <option value="">Semua bulan</option>
        @foreach ($namaBulan as $nomor => $nama)
            <option value="{{ $nomor }}" @selected($bulanDipilih === $nomor)>{{ $nama }}</option>
        @endforeach
    </select>

    {{-- Pilihan Tahun baru ditampilkan bila datanya memang lebih dari satu
         tahun; selama hanya satu tahun, ia cuma menambah kendali kosong. --}}
    @if (count($tahunList) > 1 || $tahunDipilih)
        <select name="tahun" class="spm-saring" aria-label="Saring menurut tahun SP2D" title="Tahun menurut Tanggal SP2D" onchange="this.form.submit()">
            <option value="">Semua tahun</option>
            @foreach ($tahunList as $tahun)
                <option value="{{ $tahun }}" @selected($tahunDipilih === $tahun)>{{ $tahun }}</option>
            @endforeach
        </select>
    @endif

    <button type="submit" class="btn prim" style="white-space:nowrap;">Cari</button>
    @if (request()->hasAny(['cari', 'bulan', 'tahun']) && (filled(request('cari')) || $bulanDipilih || $tahunDipilih))
        <a href="{{ route($rute) }}" class="btn" style="white-space:nowrap;">Reset</a>
    @endif
</form>
