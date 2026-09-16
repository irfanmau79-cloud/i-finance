@extends('layouts.app')

@section('activeNav', 'rincian-periodik')
@section('title', 'Realisasi Periodik')

@section('content')
@php
    $rupiah = fn (float $nilai) => fmt_rupiah($nilai);

    /**
     * Bulan bernilai nol ditulis "-", bukan "0,00". Dua belas kolom yang
     * penuh angka nol membuat bulan yang BENAR-BENAR berisi angka jadi sulit
     * ditemukan; strip menjaga yang terisi tetap menonjol.
     */
    $angkaBulan = fn (float $nilai) => abs($nilai) < 0.005 ? '-' : fmt_rupiah($nilai);
    $adaNilai = fn (float $nilai) => abs($nilai) >= 0.005;

    /**
     * Tingkat "panas" satu sel bulan: kosong, minus, atau 1-4 dibanding bulan
     * TERBESAR di seluruh tabel. Gunanya sekali lihat tahu bulan mana yang
     * ramai tanpa membaca dua belas angka per baris.
     *
     * Pembandingnya angka terbesar di TABEL, bukan per baris: kalau tiap
     * baris dinormalkan sendiri, sub kegiatan bernilai 2 juta akan tampak
     * sama pekat dengan yang bernilai 2 miliar - justru menyesatkan.
     */
    $puncak = 0.0;
    foreach ($pohon as $sub) {
        foreach ($sub['bulanan'] as $nilai) {
            $puncak = max($puncak, abs($nilai));
        }
    }
    $panas = function (float $nilai) use ($puncak): string {
        if (abs($nilai) < 0.005) {
            return 'rp-nol';
        }
        if ($nilai < 0) {
            return 'rp-minus';
        }
        if ($puncak <= 0) {
            return '';
        }

        return 'rp-h'.min(4, max(1, (int) ceil($nilai / $puncak * 4)));
    };
@endphp

<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Realisasi Periodik</b></div>
        <div class="ph-title">Realisasi Periodik</div>
    </div>
</div>

<div class="dash-card">
    <h3>Realisasi SPJ3 per Bulan &middot; Tahun Anggaran {{ $tahun }}</h3>
    <div class="sub">
        Tiap kolom bulan berisi <strong>realisasi SPJ3</strong> bulan itu &mdash; NPD berstatus Selesai
        menurut tanggal NPD ditambah SP2D LS menurut tanggal SPM, dikurangi pengembalian yang
        disetujui pada bulan pengembalian. NPD yang belum Selesai dan SPM UP/GU/TU tidak dihitung.
        Kolom Anggaran adalah pagu <strong>setahun</strong>, bukan angka bulanan.
    </div>

    <form method="GET" action="{{ route('rincian.periodik') }}" class="rp-saring">
        <div class="rp-saring-isi">
            <label class="fl" for="sub_kegiatan">Sub Kegiatan</label>
            <select id="sub_kegiatan" name="sub_kegiatan">
                <option value="">Semua Sub Kegiatan</option>
                @foreach ($subKegiatanOptions as $opsi)
                    <option value="{{ $opsi['value'] }}" @selected($filters['sub_kegiatan'] === $opsi['value'])>{{ $opsi['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-saring-isi">
            <label class="fl" for="kode_rekening">Kodering</label>
            <select id="kode_rekening" name="kode_rekening">
                <option value="">Semua Kodering</option>
                @foreach ($kodeRekeningOptions as $opsi)
                    <option value="{{ $opsi['value'] }}" @selected($filters['kode_rekening'] === $opsi['value'])>{{ $opsi['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="rp-saring-isi">
            <label class="fl" for="q">Pencarian</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] }}"
                   placeholder="Sub kegiatan / kodering / tagging&hellip;">
        </div>
        <div class="rp-saring-aksi">
            <button type="submit" class="btn prim">Terapkan</button>
            <a class="btn" href="{{ route('rincian.periodik') }}">Reset</a>
        </div>
    </form>
</div>

<div class="dash-card">
    <div class="rp-atas">
        <div class="rp-ubin-baris">
            <div class="rp-ubin">
                <span class="rp-ubin-lbl">Anggaran</span>
                <span class="rp-ubin-nil">{{ $rupiah($total['pagu']) }}</span>
            </div>
            <div class="rp-ubin aksen">
                <span class="rp-ubin-lbl">Realisasi SPJ3</span>
                <span class="rp-ubin-nil">{{ $rupiah($total['realisasi']) }}</span>
            </div>
            <div class="rp-ubin">
                <span class="rp-ubin-lbl">Capaian</span>
                <span class="rp-ubin-nil">{{ number_format($total['persentase_realisasi'], 2, ',', '.') }}%</span>
            </div>
            <div class="rp-ubin">
                <span class="rp-ubin-lbl">Mata Anggaran</span>
                <span class="rp-ubin-nil">{{ $baris->count() }}</span>
            </div>
        </div>
        <div class="rp-alat">
            <button type="button" class="btn" id="rp-buka">Buka Semua</button>
            <button type="button" class="btn" id="rp-tutup">Tutup Semua</button>
        </div>
    </div>

    {{-- Lima belas kolom tidak akan pernah muat di layar mana pun, jadi
         tabelnya digulung SENDIRI ke samping (bukan halamannya), dan dua
         kolom identitas dipatok (position:sticky) supaya baris yang sedang
         dibaca tidak kehilangan namanya saat digulung ke Desember. --}}
    <div class="rp-wrap">
        <table class="realisasi rp-tabel">
            <thead>
                <tr>
                    <th class="rp-lekat rp-c0">Sub Kegiatan / Kodering</th>
                    <th class="rp-lekat rp-c1">Tagging</th>
                    <th class="num">Anggaran</th>
                    @foreach ($bulan as $namaBulan)
                        <th class="num" title="{{ $namaBulan }}">{{ Str::substr($namaBulan, 0, 3) }}</th>
                    @endforeach
                    <th class="num rp-jumlah">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pohon as $iSub => $sub)
                    @php($kSub = 's'.$iSub)
                    {{-- Baris Sub Kegiatan: angkanya JUMLAH seluruh kodering di
                         bawahnya, dihitung di server. Ia tetap terbaca penuh
                         walau anaknya tertutup - itu gunanya. --}}
                    <tr class="rp-lvl0" data-simpul="{{ $kSub }}">
                        <td class="rp-lekat rp-c0">
                            <button type="button" class="rp-tgl" aria-expanded="false">
                                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                                <span>{{ $sub['sub_kegiatan'] }}</span>
                            </button>
                        </td>
                        <td class="rp-lekat rp-c1"><span class="rp-cacah">{{ $sub['kodering']->count() }} kodering</span></td>
                        <td class="num">{{ $rupiah($sub['pagu']) }}</td>
                        @foreach ($sub['bulanan'] as $nilai)
                            <td class="num {{ $panas($nilai) }}">{{ $angkaBulan($nilai) }}</td>
                        @endforeach
                        <td class="num rp-jumlah {{ $adaNilai($sub['realisasi']) ? '' : 'rp-nol' }}">{{ $angkaBulan($sub['realisasi']) }}</td>
                    </tr>

                    @foreach ($sub['kodering'] as $iKode => $kode)
                        @php($kKode = $kSub.'k'.$iKode)
                        @php($tunggal = $kode['tagging']->count() === 1)
                        <tr class="rp-lvl1" data-induk="{{ $kSub }}" @if (! $tunggal) data-simpul="{{ $kKode }}" @endif hidden>
                            <td class="rp-lekat rp-c0">
                                @if ($tunggal)
                                    {{-- Satu tagging saja: tombol buka tidak
                                         disediakan, isinya cuma akan mengulang
                                         baris ini. --}}
                                    <span class="rp-daun ind1">
                                        <span class="rp-kode">{{ $kode['kodering'] }}</span>
                                        @if ($kode['uraian_rekening'] !== '')
                                            <span class="rp-uraian">{{ $kode['uraian_rekening'] }}</span>
                                        @endif
                                    </span>
                                @else
                                    <button type="button" class="rp-tgl ind1" aria-expanded="false">
                                        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                                        <span>
                                            <span class="rp-kode">{{ $kode['kodering'] }}</span>
                                            @if ($kode['uraian_rekening'] !== '')
                                                <span class="rp-uraian">{{ $kode['uraian_rekening'] }}</span>
                                            @endif
                                        </span>
                                    </button>
                                @endif
                            </td>
                            <td class="rp-lekat rp-c1">
                                @if ($tunggal)
                                    {{ $kode['tagging']->first()['tagging'] }}
                                @else
                                    <span class="rp-cacah">{{ $kode['tagging']->count() }} tagging</span>
                                @endif
                            </td>
                            <td class="num">{{ $rupiah($kode['pagu']) }}</td>
                            @foreach ($kode['bulanan'] as $nilai)
                                <td class="num {{ $panas($nilai) }}">{{ $angkaBulan($nilai) }}</td>
                            @endforeach
                            <td class="num rp-jumlah {{ $adaNilai($kode['realisasi']) ? '' : 'rp-nol' }}">{{ $angkaBulan($kode['realisasi']) }}</td>
                        </tr>

                        @unless ($tunggal)
                            @foreach ($kode['tagging'] as $tag)
                                <tr class="rp-lvl2" data-induk="{{ $kSub }} {{ $kKode }}" hidden>
                                    <td class="rp-lekat rp-c0"></td>
                                    <td class="rp-lekat rp-c1"><span class="rp-daun">{{ $tag['tagging'] }}</span></td>
                                    <td class="num">{{ $rupiah($tag['pagu']) }}</td>
                                    @foreach ($tag['bulanan'] as $nilai)
                                        <td class="num {{ $panas($nilai) }}">{{ $angkaBulan($nilai) }}</td>
                                    @endforeach
                                    <td class="num rp-jumlah {{ $adaNilai($tag['realisasi']) ? '' : 'rp-nol' }}">{{ $angkaBulan($tag['realisasi']) }}</td>
                                </tr>
                            @endforeach
                        @endunless
                    @endforeach
                @empty
                    <tr>
                        <td colspan="16" style="text-align:center;color:var(--mut);padding:18px;">Tidak ada data</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($pohon->isNotEmpty())
                <tfoot>
                    <tr>
                        {{-- Label kaki menumpang dua kolom identitas, jadi
                             lebarnya ditentukan kedua kolom itu - bukan .rp-c0
                             yang punya max-width sendiri. --}}
                        <th class="rp-lekat rp-kaki" colspan="2">Jumlah</th>
                        <th class="num">{{ $rupiah($total['pagu']) }}</th>
                        @foreach ($total['bulanan'] as $nilai)
                            <th class="num {{ $adaNilai($nilai) ? '' : 'rp-nol' }}">{{ $angkaBulan($nilai) }}</th>
                        @endforeach
                        <th class="num rp-jumlah">{{ $rupiah($total['realisasi']) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<style>
  .rp-saring{display:flex;flex-wrap:wrap;gap:var(--sp-3);align-items:flex-end;margin-top:var(--sp-1);}
  .rp-saring-isi{display:flex;flex-direction:column;gap:var(--sp-1);flex:1 1 220px;min-width:200px;}
  .rp-saring-isi .fl{margin:0;}
  .rp-saring-aksi{display:flex;gap:var(--sp-2);}

  .rp-atas{display:flex;flex-wrap:wrap;gap:var(--sp-4);align-items:center;
    justify-content:space-between;margin-bottom:var(--sp-5);}
  .rp-ubin-baris{display:flex;flex-wrap:wrap;gap:var(--sp-3);}
  /* Ubin berangka, bukan baris teks: keempat angka ini yang dicari lebih
     dulu sebelum tabelnya dibaca. */
  .rp-ubin{display:flex;flex-direction:column;gap:2px;padding:var(--sp-3) var(--sp-4);
    border:1px solid var(--line);border-radius:var(--r-md);background:var(--surface-2);min-width:150px;}
  .rp-ubin.aksen{background:var(--navy-l);border-color:var(--aksen-garis);}
  .rp-ubin-lbl{font-size:10.5px;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.8px;}
  .rp-ubin-nil{font-size:16px;font-weight:800;color:var(--tegas);font-variant-numeric:tabular-nums;}
  .rp-alat{display:flex;gap:var(--sp-2);}

  .rp-wrap{overflow:auto;max-height:72vh;border:1px solid var(--line);border-radius:var(--r-md);}
  table.rp-tabel{width:auto;min-width:100%;border-collapse:separate;border-spacing:0;}
  table.rp-tabel th,
  table.rp-tabel td{white-space:nowrap;vertical-align:top;padding:var(--sp-2) var(--sp-3);
    border-bottom:1px solid var(--line);}
  table.rp-tabel td.num,
  table.rp-tabel th.num{text-align:right;font-variant-numeric:tabular-nums;}

  /* Dua kolom identitas dipatok ke kiri. Nilai left kolom kedua = lebar
     kolom pertama; menyetel salah satu tanpa yang lain membuat keduanya
     saling menumpuk saat tabel digulung. */
  table.rp-tabel .rp-c0{min-width:330px;max-width:330px;white-space:normal;left:0;}
  table.rp-tabel .rp-c1{min-width:150px;max-width:150px;white-space:normal;left:330px;}
  table.rp-tabel .rp-lekat{position:sticky;z-index:3;background:var(--surface);
    box-shadow:inset -1px 0 0 var(--line);}
  table.rp-tabel thead th{position:sticky;top:0;z-index:5;background:var(--surface-2);
    box-shadow:inset 0 -1px 0 var(--line);cursor:default;}
  table.rp-tabel thead .rp-lekat{z-index:6;}
  table.rp-tabel tfoot th{position:sticky;bottom:0;z-index:5;background:var(--surface-2);
    color:var(--tegas);font-weight:800;box-shadow:inset 0 1px 0 var(--line);}
  table.rp-tabel tfoot .rp-lekat{z-index:6;}
  table.rp-tabel .rp-kaki{left:0;}

  /* Tiga tingkat baris dibedakan oleh BOBOT dan latar tipis, bukan garis
     tambahan - tabel selebar ini sudah cukup padat. */
  tr.rp-lvl0 > td{background:var(--surface-2);font-weight:700;color:var(--tegas);}
  tr.rp-lvl1 > td{font-weight:600;}
  tr.rp-lvl2 > td{color:var(--ink);}
  tr.rp-lvl1:hover > td,
  tr.rp-lvl2:hover > td{background:var(--surface-2);}

  /* Pemicu buka-tutup berupa <button>: bisa dicapai Tab lalu ditekan Enter,
     dan status bukanya terbaca pembaca layar lewat aria-expanded - baris
     <tr> yang diberi onclick tidak memberi keduanya. */
  .rp-tgl{display:flex;align-items:flex-start;gap:var(--sp-2);width:100%;text-align:left;
    background:none;border:0;padding:0;font:inherit;color:inherit;cursor:pointer;}
  .rp-tgl svg{width:14px;height:14px;flex:0 0 14px;margin-top:2px;stroke:var(--aksen);fill:none;
    stroke-width:2.5;transition:transform .15s ease;}
  .rp-tgl[aria-expanded="true"] svg{transform:rotate(90deg);}
  .rp-tgl:hover{color:var(--aksen-d);}
  .rp-tgl:focus-visible{outline:2px solid var(--aksen);outline-offset:2px;border-radius:var(--r-sm);}
  .rp-tgl.ind1{padding-left:22px;}
  .rp-daun{display:block;}
  .rp-daun.ind1{padding-left:36px;}
  .rp-kode{display:block;}
  .rp-uraian{display:block;font-weight:400;color:var(--mut);font-size:11.5px;margin-top:2px;
    white-space:normal;}
  .rp-cacah{font-size:11.5px;font-weight:600;color:var(--mut);}

  /* Peta panas: makin besar realisasi bulan itu dibanding bulan terbesar di
     tabel, makin pekat latarnya. Angkanya tetap tertulis - yang berubah cuma
     latar, jadi ini tambahan keterangan, bukan pengganti angka. */
  table.rp-tabel .rp-h1{background:rgba(32,64,111,.05);}
  table.rp-tabel .rp-h2{background:rgba(32,64,111,.10);}
  table.rp-tabel .rp-h3{background:rgba(32,64,111,.17);}
  table.rp-tabel .rp-h4{background:rgba(32,64,111,.26);color:var(--tegas);font-weight:700;}
  table.rp-tabel .rp-nol{color:var(--mut);}
  /* Bulan negatif = pengembalian melebihi realisasi bulan itu. Nyata, dan
     harus terlihat beda dari sekadar bernilai besar. */
  table.rp-tabel .rp-minus{background:var(--err-bg);color:var(--err-teks);font-weight:700;}
  table.rp-tabel .rp-jumlah{background:var(--surface-3);font-weight:700;color:var(--tegas);}

  :root[data-tema="gelap"] table.rp-tabel .rp-h1{background:rgba(125,211,252,.07);}
  :root[data-tema="gelap"] table.rp-tabel .rp-h2{background:rgba(125,211,252,.13);}
  :root[data-tema="gelap"] table.rp-tabel .rp-h3{background:rgba(125,211,252,.20);}
  :root[data-tema="gelap"] table.rp-tabel .rp-h4{background:rgba(125,211,252,.28);}

  @media(max-width:900px){
    table.rp-tabel .rp-c0,
    table.rp-tabel .rp-c1{position:static;min-width:180px;max-width:220px;box-shadow:none;}
    .rp-atas{align-items:flex-start;}
  }
</style>

<script>
(function () {
    var tabel = document.querySelector('.rp-tabel tbody');
    if (! tabel) return;

    var baris = Array.prototype.slice.call(tabel.querySelectorAll('tr[data-induk]'));
    var pemicu = Array.prototype.slice.call(tabel.querySelectorAll('.rp-tgl'));
    if (! baris.length) return;

    var terbuka = {};
    var simpulDari = function (btn) {
        var tr = btn.closest('tr');
        return tr ? (tr.dataset.simpul || '') : '';
    };

    /**
     * Satu baris terlihat kalau SELURUH induknya terbuka - bukan cuma induk
     * langsungnya. Tanpa syarat itu, menutup Sub Kegiatan akan meninggalkan
     * baris Tagging menggantung di layar karena induk langsungnya (Kodering)
     * masih tercatat terbuka.
     */
    function render() {
        baris.forEach(function (tr) {
            var induk = (tr.dataset.induk || '').split(/\s+/).filter(Boolean);
            tr.hidden = ! induk.every(function (k) { return terbuka[k]; });
        });

        pemicu.forEach(function (btn) {
            var simpul = simpulDari(btn);
            btn.setAttribute('aria-expanded', simpul && terbuka[simpul] ? 'true' : 'false');
        });
    }

    pemicu.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var simpul = simpulDari(btn);
            if (! simpul) return;
            terbuka[simpul] = ! terbuka[simpul];
            render();
        });
    });

    document.getElementById('rp-buka').addEventListener('click', function () {
        tabel.querySelectorAll('tr[data-simpul]').forEach(function (tr) {
            terbuka[tr.dataset.simpul] = true;
        });
        render();
    });

    document.getElementById('rp-tutup').addEventListener('click', function () {
        terbuka = {};
        render();
    });

    render();
})();
</script>
@endsection
