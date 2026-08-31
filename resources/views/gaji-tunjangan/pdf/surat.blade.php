{{--
    SURAT KETERANGAN PENGHASILAN — port dari _gtBungkusSurat / _gtKop /
    _gtHalamanSurat / _rowKet / _rowSub / _rowTotal / _gtBlokTTE di
    CodeGajiTunjangan.gs (bentuk finalnya: perubahan 10-17).

    SATU BEDA TEKNIS YANG DISENGAJA: GAS merender lewat mesin HTML Google yang
    mendukung flexbox, sedangkan mPDF tidak. Semua blok yang di GAS memakai
    `display:flex` — kop surat dan kotak TTE — di sini disusun ulang memakai
    tabel dengan lebar kolom yang sama persis, sehingga hasil cetaknya identik
    meski markup-nya berbeda. Ukuran, jarak, tebal garis, dan ukuran huruf
    diambil apa adanya dari CSS GAS.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    /* Ukuran kertas & margin TIDAK ditulis di sini. GAS memakai
       `@page{size:A4;margin:15mm}`, tetapi pada mPDF `size: A4` di dalam
       @page memicu pemecahan halaman tanpa henti - satu baris teks pun
       tercetak menjadi 22 halaman. Nilainya sama persis, hanya dipindah ke
       MpdfFont::konfigA4([15, 15, 15, 15]) di RincianPenghasilanController. */

    body { font-family: "Times New Roman", serif; color: #111; margin: 0; font-size: 13px; }

    /* --- Kop surat --- */
    table.kt-kop { width: 100%; border-collapse: collapse; padding-bottom: 4px; }
    table.kt-kop td { border: none; padding: 0; vertical-align: middle; }
    td.kt-logo { width: 106px; text-align: left; }
    td.kt-logo img { width: 92px; height: auto; }
    td.kt-kop-txt { text-align: center; font-family: Arial, Helvetica, sans-serif; }
    .k1 { font-size: 15.5px; font-weight: normal; letter-spacing: .2px; }
    .k2 { font-size: 21px; font-weight: bold; letter-spacing: 4px; line-height: 1.1; }
    .k3 { font-size: 9.5px; line-height: 1.38; margin-top: 2px; font-family: Arial, Helvetica, sans-serif; }
    .k3 .web { font-style: italic; }
    .kt-line1 { border-top: 2.5px solid #000; margin-top: 1px; }
    .kt-line2 { border-top: 1px solid #000; margin-top: 1.8px; }

    /* --- Judul & identitas --- */
    .kt-head { text-align: center; font-weight: bold; text-decoration: underline; margin-top: 16px; font-size: 13px; }
    .kt-sub { text-align: center; font-size: 12px; }
    .kt-intro { margin: 10px 0; font-size: 12.5px; text-align: justify; }
    table.kt-id { margin: 6px 0 4px 6px; font-size: 12.5px; border-collapse: collapse; }
    table.kt-id td { padding: 1px 4px; vertical-align: top; border: none; }
    table.kt-id td.k { width: 80px; }

    /* --- Tabel rincian: nilai item di kolom tengah, subtotal & total di
           kolom kanan terpisah (meniru tata letak Excel aslinya). --- */
    table.kt-rinci { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 4px; }
    table.kt-rinci td { padding: 1.5px 4px; vertical-align: top; border: none; }
    .kt-no { width: 22px; text-align: center; }
    .rpC { width: 26px; text-align: left; }
    .numC { width: 96px; text-align: right; }
    .rpR { width: 26px; text-align: left; padding-left: 14px; }
    .numR { width: 104px; text-align: right; }
    .uline-top { border-top: 1px solid #000; }
    .uline-dbl { border-top: 1px solid #000; border-bottom: 3px double #000; }
    .sela-bab { padding-top: 6px; }
    .sela-bab-v { padding-top: 8px; }

    /* --- Blok tanda tangan elektronik --- */
    table.kt-ttd { width: 100%; border-collapse: collapse; margin-top: 16px; }
    table.kt-ttd td { border: none; padding: 0; vertical-align: top; }
    td.kt-ttd-inner { width: 330px; font-family: Arial, Helvetica, sans-serif; }
    .kt-ttd-kota { text-align: center; margin-bottom: 7px; font-family: "Times New Roman", serif; font-size: 12px; }
    .kt-ttd-box { border: 1.4px solid #222; border-radius: 11px; padding: 5px 13px; }
    table.kt-ttd-isi { border-collapse: collapse; width: 100%; }
    table.kt-ttd-isi td { border: none; padding: 0; vertical-align: middle; }
    td.kt-qr { width: 92px; }
    td.kt-qr img { width: 78px; height: auto; }
    td.kt-ttd-txt { font-size: 11px; line-height: 1.35; font-weight: normal; color: #111; }
    .kt-ttd-gap { height: 38px; font-size: 1px; line-height: 1; }
</style>
</head>
<body>

@php
    $rp = fn ($nilai) => fmt_rupiah($nilai);
    // Nilai nol dicetak sebagai "-" pada baris rincian, bukan "0,00".
    $item = fn ($nilai) => (float) $nilai === 0.0 ? '-' : fmt_rupiah($nilai);
    $namaProper = fn (?string $teks) => \App\Support\NamaProper::format($teks);
@endphp

@foreach ($halaman as $index => $h)
<div class="kt-page" @if ($index > 0) style="page-break-before:always;" @endif>

    <table class="kt-kop">
        <tr>
            <td class="kt-logo">@if ($logoKop)<img src="{{ $logoKop }}" alt="">@endif</td>
            <td class="kt-kop-txt">
                <div class="k1">PEMERINTAH&nbsp; DAERAH PROVINSI JAWA BARAT</div>
                <div class="k2">INSPEKTORAT&nbsp; DAERAH</div>
                <div class="k3">
                    JALAN SURAPATI No. 4 TELP. (022) 4237174 &ndash; 4231567 FAKSIMIL (022) 4231567<br>
                    <span class="web">Website: www.inspektorat.jabarprov.go.id&nbsp; e-mail: inspektorat@jabarprov.go.id</span><br>
                    BANDUNG &ndash; KODE POS 40115
                </div>
            </td>
        </tr>
    </table>
    <div class="kt-line1"></div>
    <div class="kt-line2"></div>

    <div class="kt-head">KETERANGAN</div>
    <div class="kt-sub">Perincian Gaji</div>
    <div class="kt-sub">Nomor : {{ $dokumen->nomor }}</div>

    <div class="kt-intro">Dengan ini menerangkan bahwa :</div>

    <table class="kt-id">
        <tr><td class="k">Nama</td><td>:</td><td>{{ $namaProper($dokumen->nama) }}</td></tr>
        <tr><td class="k">Jabatan</td><td>:</td><td>{{ $namaProper($dokumen->jabatan) }}</td></tr>
        <tr><td class="k">NIP</td><td>:</td><td>{{ $dokumen->nip }}</td></tr>
    </table>

    <div class="kt-intro">
        merupakan Aparatur Sipil Negara pada Inspektorat Daerah Provinsi Jawa Barat
        dan telah menerima penghasilan pada bulan <b>{{ $h['nama_bulan'] }} {{ $dokumen->tahun }}</b>,
        dengan perincian dibawah ini :
    </div>

    @php($g = $h['gaji'])
    @php($k = $h['kinerja'])

    <table class="kt-rinci">
        <tr><td colspan="6"><b>I. &nbsp; GAJI INDUK</b></td></tr>
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 1, 'label' => 'Gaji Pokok', 'nilai' => $g['pokok']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 2, 'label' => 'Tunjangan Suami/Istri', 'nilai' => $g['suami_istri']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 3, 'label' => 'Tunjangan Anak', 'nilai' => $g['anak']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 4, 'label' => 'Tunjangan Struktural/Fungsional/Umum', 'nilai' => $g['struktural_umum']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 5, 'label' => 'Tunjangan Beras', 'nilai' => $g['beras']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 6, 'label' => 'PPh 21', 'nilai' => $g['pph']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 7, 'label' => 'Pembulatan', 'nilai' => $g['pembulatan']])
        @include('gaji-tunjangan.pdf._row-total', ['label' => 'Jumlah Gaji Bruto', 'nilai' => $g['bruto']])

        <tr><td colspan="6" class="sela-bab"><b>II. &nbsp; POTONGAN</b></td></tr>
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 1, 'label' => 'Simpanan WAJIB ( TASPEN )', 'nilai' => $g['pot_wajib']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 2, 'label' => 'Iuran BPJS / Askes', 'nilai' => $g['pot_bpjs']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 3, 'label' => 'PPh 21', 'nilai' => $g['pot_pph']])
        @include('gaji-tunjangan.pdf._row-sub', ['label' => 'Jumlah Potongan', 'nilai' => $g['pot_total']])
        @include('gaji-tunjangan.pdf._row-total', ['label' => 'Jumlah Gaji Induk Netto', 'nilai' => $g['netto']])

        <tr><td colspan="6" class="sela-bab"><b>III. &nbsp; PENGHASILAN BERBASIS KINERJA</b></td></tr>
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 1, 'label' => 'Tunjangan Tambahan Penghasilan berdasarkan Beban Kerja', 'nilai' => $k['beban']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 2, 'label' => 'Tunjangan Tambahan Penghasilan berdasarkan Kondisi Kerja', 'nilai' => $k['kondisi']])
        @include('gaji-tunjangan.pdf._row-total', ['label' => 'Jumlah Penghasilan Berbasis Kinerja Bruto', 'nilai' => $k['bruto']])

        <tr><td colspan="6" class="sela-bab"><b>IV. &nbsp; POTONGAN PENGHASILAN BERBASIS KINERJA</b></td></tr>
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 1, 'label' => 'Simpanan Koperasi Praja', 'nilai' => $k['koperasi']])
        @include('gaji-tunjangan.pdf._row-ket', ['no' => 2, 'label' => 'Zakat', 'nilai' => $k['zakat']])
        @include('gaji-tunjangan.pdf._row-sub', ['label' => 'Jumlah Potongan', 'nilai' => $k['pot_total']])
        @include('gaji-tunjangan.pdf._row-total', ['label' => 'Jumlah Penghasilan Berbasis Kinerja Netto', 'nilai' => $k['netto']])

        @if ($h['tampil_pd'])
            <tr><td colspan="6" class="sela-bab-v"><b>V. &nbsp; PENGHASILAN LAINNYA</b></td></tr>
            @include('gaji-tunjangan.pdf._row-ket', ['no' => 1, 'label' => 'Uang Harian Perjalanan Dinas', 'nilai' => $h['nominal_pd']])
            @include('gaji-tunjangan.pdf._row-total', ['label' => 'Jumlah Penghasilan Lainnya', 'nilai' => $h['nominal_pd']])
        @endif

        <tr>
            <td colspan="4" style="text-align:left;padding-top:8px;"><b>Jumlah Penghasilan Seluruhnya</b></td>
            <td class="rpR uline-dbl"><b>Rp</b></td>
            <td class="numR uline-dbl"><b>{{ $rp($h['jumlah_seluruh']) }}</b></td>
        </tr>
    </table>

    <table class="kt-ttd">
        <tr>
            <td></td>
            <td class="kt-ttd-inner">
                <div class="kt-ttd-kota">Bandung, {{ $dokumen->tanggal_dokumen->translatedFormat('j F Y') }}</div>
                <div class="kt-ttd-box">
                    <table class="kt-ttd-isi">
                        <tr>
                            <td class="kt-qr">@if ($logoTte)<img src="{{ $logoTte }}" alt="">@endif</td>
                            <td class="kt-ttd-txt">
                                {{-- Jabatan tampil KAPITAL, nama apa adanya supaya gelar
                                     ("S.Ak.", "M.S.P.") tidak ikut berubah bentuk. --}}
                                <div>Ditandatangani secara elektronik oleh:<br>{{ mb_strtoupper($dokumen->penandatangan_jabatan) }}</div>
                                <div class="kt-ttd-gap">&nbsp;</div>
                                <div>{{ $dokumen->penandatangan_nama }}<br>{{ $dokumen->penandatangan_pangkat }}</div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

</div>
@endforeach

</body>
</html>
