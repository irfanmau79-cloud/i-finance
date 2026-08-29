<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  @page { size: 215mm 330mm; margin: 12mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:8pt; color:#000; margin:0; line-height:1.3; }
  /* KOP kecil di kiri atas, disamakan dengan Daftar Bayar Perjalanan Dinas
     (body tpl_nara_daftar.html di GAS memakai kop kecil ini, bukan kop besar). */
  table.kop-mini { width:116pt; border-collapse:collapse; margin-bottom:0; }
  table.kop-mini td { border:none; border-bottom:1pt solid #000; padding:0 0 2pt; text-align:center; }
  .kopmini-l1 { font-size:5.5pt; font-weight:bold; }
  .kopmini-l2 { font-size:6pt; font-weight:bold; }
  .kopmini-l3 { font-size:4.4pt; }

  /* "Daftar :" digeser ke kanan, sama seperti Daftar Bayar Perjalanan Dinas. */
  table.daftar-line { width:100%; border-collapse:collapse; margin:8pt 0; }
  table.daftar-line td { border:none; padding:0; vertical-align:top; }
  table.daftar-line td.sp { width:110pt; }
  table.daftar-line td.lbl { white-space:nowrap; font-weight:bold; vertical-align:top; padding-right:6pt; font-size:7.5pt; width:44pt; }
  table.daftar-line td.isi { vertical-align:top; text-align:justify; font-size:7.5pt; }

  table.dt { width:100%; border-collapse:collapse; }
  table.dt tr.drow1 td { height:32pt; }
  table.dt th, table.dt td { border:1pt solid #000; padding:2pt 3pt; vertical-align:middle; }
  table.dt th { background:#f2f2f2; text-align:center; font-size:6.5pt; }
  table.dt td { font-size:6.8pt; }
  .num { text-align:right; white-space:nowrap; }
  .center { text-align:center; } .bold { font-weight:bold; }
  .terbilang { font-style:italic; margin:6pt 0; }

  table.ttd { width:100%; border-collapse:collapse; margin-top:14pt; }
  table.ttd td { border:none; padding:0; width:50%; text-align:center; vertical-align:top; }
  /* margin-bottom pada div di dalam sel tabel TIDAK dihormati mPDF -
     jarak tanda tangan dibuat lewat sel setinggi 46pt (.ttd-jarak). */
  .ttd-role { margin-bottom:0; }
  table.ttd-jarak { width:100%; border-collapse:collapse; }
  table.ttd-jarak td { height:46pt; border:none; padding:0; }
  .ttd-nama { font-weight:bold; }
</style>
</head>
<body>
  <table class="kop-mini">
    <tr><td>
      <div class="kopmini-l1">PEMERINTAH PROVINSI JAWA BARAT</div>
      <div class="kopmini-l2">INSPEKTORAT DAERAH</div>
      <div class="kopmini-l3">Jalan Surapati No. 4 Tlp. 4237174-4231567 Fax. 4231567</div>
      <div class="kopmini-l3">BANDUNG 40115</div>
    </td></tr>
  </table>

  <table class="daftar-line">
    <tr>
      <td class="sp"></td>
      <td class="lbl">Daftar :</td>
      <td class="isi">{{ $intro }}</td>
    </tr>
  </table>

  <table class="dt">
    <thead>
      <tr>
        <th rowspan="2" style="width:3%;">NO</th>
        <th rowspan="2" style="width:16%;">NAMA</th>
        <th rowspan="2" style="width:12%;">JABATAN</th>
        <th colspan="6">RINCIAN PERHITUNGAN</th>
        <th rowspan="2" style="width:11%;">JUMLAH YANG<br>DITERIMA</th>
        <th rowspan="2" style="width:9%;">TANDA<br>TANGAN</th>
      </tr>
      <tr>
        {{-- Lebar kolom dalam diukur dari dokumen tertandatangani; tanpa
             ini mPDF melebarkannya otomatis dan tabelnya meleset. --}}
        <th style="width:3.2%;">JP</th>
        <th style="width:7.2%;">SATUAN<br>(Rp)</th>
        <th style="width:11.4%;">JUMLAH<br>HONORARIUM</th>
        <th style="width:10.3%;">PENGGANTI<br>TRANSPORT</th>
        <th style="width:8.9%;">JUMLAH<br>KOTOR</th>
        <th style="width:8%;">PPh<br>PASAL 21</th>
      </tr>
    </thead>
    <tbody>
      {!! $rowsBody !!}
    </tbody>
  </table>

  <div class="terbilang">Terbilang : # {{ $npd->terbilang }} #</div>

  <table class="ttd">
    <tr>
      <td>
        <div class="ttd-role">Setuju dibayar<br>Kuasa Pengguna Anggaran</div><table class="ttd-jarak"><tr><td></td></tr></table>
        <div class="ttd-nama">{{ $kpa->nama }}</div>
        <div>{{ $kpa->pangkat }}</div>
        <div>NIP. {{ $kpa->nip }}</div>
      </td>
      <td>
        <div>Bandung, .................... {{ $bulanNpd }} {{ $npd->tahun }}</div>
        <div class="ttd-role">Lunas dibayar<br>Bendahara Pengeluaran Pembantu</div><table class="ttd-jarak"><tr><td></td></tr></table>
        <div class="ttd-nama">{{ $bpp->nama }}</div>
        <div>{{ $bpp->pangkat }}</div>
        <div>NIP. {{ $bpp->nip }}</div>
      </td>
    </tr>
  </table>
</body>
</html>
