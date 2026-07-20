<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  @page { size: 215mm 330mm; margin: 12mm 10mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:8pt; color:#000; margin:0; line-height:1.3; }
  .kop { text-align:left; border-bottom:2pt solid #000; padding-bottom:5pt; margin-bottom:6pt; }
  .kop .l1 { font-size:10pt; font-weight:bold; }
  .kop .l2 { font-size:12pt; font-weight:bold; }
  .kop .l3 { font-size:7.5pt; }

  table.intro { width:100%; border-collapse:collapse; font-size:8pt; margin:6pt 0 10pt; }
  table.intro td { border:none; padding:0; vertical-align:top; }
  table.intro td.lbl { white-space:nowrap; font-weight:bold; padding-right:4pt; width:40pt; }
  table.intro td.txt { text-align:justify; }

  table.dt { width:100%; border-collapse:collapse; }
  table.dt th, table.dt td { border:1pt solid #000; padding:2pt 3pt; vertical-align:middle; }
  table.dt th { background:#f2f2f2; text-align:center; font-size:6.5pt; }
  table.dt td { font-size:6.8pt; }
  .num { text-align:right; white-space:nowrap; }
  .center { text-align:center; } .bold { font-weight:bold; }
  .terbilang { font-style:italic; margin:6pt 0; }

  table.ttd { width:100%; border-collapse:collapse; margin-top:14pt; }
  table.ttd td { border:none; padding:0; width:50%; text-align:center; vertical-align:top; }
  .ttd .role { margin-bottom:46pt; }
  .ttd .nama { font-weight:bold; }
</style>
</head>
<body>
  <div class="kop">
    <div class="l1">PEMERINTAH PROVINSI JAWA BARAT</div>
    <div class="l2">INSPEKTORAT DAERAH</div>
    <div class="l3">Jalan Surapati No. 4 Telp. (022) 4237174 - 4231567 Faksimil (022) 4231567</div>
    <div class="l3">BANDUNG &ndash; KODE POS 40115</div>
  </div>

  <table class="intro">
    <tr>
      <td class="lbl">Daftar :</td>
      <td class="txt">{{ $intro }}</td>
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
        <th>JP</th>
        <th>SATUAN<br>(Rp)</th>
        <th>JUMLAH<br>HONORARIUM</th>
        <th>PENGGANTI<br>TRANSPORT</th>
        <th>JUMLAH<br>KOTOR</th>
        <th>PPh<br>PASAL 21</th>
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
        <div class="role">Setuju dibayar<br>Kuasa Pengguna Anggaran</div>
        <div class="nama">{{ $kpa->nama }}</div>
        <div>{{ $kpa->pangkat }}</div>
        <div>NIP. {{ $kpa->nip }}</div>
      </td>
      <td>
        <div>Bandung, .................... {{ $bulanNpd }} {{ $npd->tahun }}</div>
        <div class="role">Lunas dibayar<br>Bendahara Pengeluaran Pembantu</div>
        <div class="nama">{{ $bpp->nama }}</div>
        <div>{{ $bpp->pangkat }}</div>
        <div>NIP. {{ $bpp->nip }}</div>
      </td>
    </tr>
  </table>
</body>
</html>
