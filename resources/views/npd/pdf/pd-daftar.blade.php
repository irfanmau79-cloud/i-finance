<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  @page { size: 215mm 330mm; margin: 7mm 7mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:6.5pt; color:#000; margin:0; line-height:1.25; }
  .judul { text-align:center; font-weight:bold; font-size:10pt; margin:8pt 0 4pt; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; }
  thead { display:table-header-group; }
  tr { page-break-inside:avoid; }
  th, td { border:1pt solid #000; padding:1.5pt 3pt; vertical-align:middle; word-wrap:break-word; overflow-wrap:break-word; }
  th { background:#fff; text-align:center; font-size:6pt; font-weight:bold; line-height:1.1; }
  td { font-size:6.5pt; }
  tr.drow1 td { height:32pt; }
  .num { text-align:right; white-space:nowrap; }
  .center { text-align:center; } .bold { font-weight:bold; }

  /* Sel nominal: label "Rp" DISEMBUNYIKAN supaya kolomnya muat (GAS:
     td.rp .rp-l { display:none } pada tpl_pd_daftar), angka rata kanan. */
  td.rp { padding:1.5pt 3pt; white-space:nowrap; text-align:right; }

  table.kop-mini { width:116pt; border-collapse:collapse; margin-bottom:0; }
  table.kop-mini td { border:none; border-bottom:1pt solid #000; padding:0 0 2pt; text-align:center; }
  .kopmini-l1 { font-size:5.5pt; font-weight:bold; }
  .kopmini-l2 { font-size:6pt; font-weight:bold; }
  .kopmini-l3 { font-size:4.4pt; }

  table.daftar-line { width:100%; border-collapse:collapse; margin:8pt 0; }
  table.daftar-line td { border:none; padding:0; vertical-align:top; }
  table.daftar-line td.sp { width:110pt; }
  table.daftar-line td.lbl { white-space:nowrap; font-weight:bold; vertical-align:top; padding-right:6pt; font-size:7.5pt; width:44pt; }
  table.daftar-line td.isi { vertical-align:top; text-align:justify; font-size:7.5pt; }

  .terbilang { font-style:italic; margin:6pt 0; font-size:7.5pt; }

  table.ttd { width:100%; border-collapse:collapse; margin-top:14pt; }
  table.ttd td { border:none; padding:0; width:50%; text-align:center; vertical-align:top; }
  /* margin-bottom pada div di dalam sel tabel TIDAK dihormati mPDF -
     jarak tanda tangan dibuat lewat sel setinggi 48pt (.ttd-jarak). */
  .ttd-role { margin-bottom:0; }
  table.ttd-jarak { width:100%; border-collapse:collapse; }
  table.ttd-jarak td { height:48pt; border:none; padding:0; }
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
      <td class="isi">{{ $uraianBiaya }} terhitung tanggal {{ $tglBerangkat }} s.d {{ $tglPulang }} dalam rangka {{ $detail['uraian_sp'] ?? '' }}, berdasarkan Surat Perintah Nomor: {{ $detail['nomor_sp'] ?? '' }} tanggal {{ $tglSp }}</td>
    </tr>
  </table>

  <table>
    <colgroup>
      {{-- Lebar kolom diukur langsung dari dokumen tertandatangani di
           storage/app/acuan-pdf, bukan dari tpl_pd_daftar.html yang ternyata
           sudah tertinggal dari dokumen yang benar-benar dipakai kantor. --}}
      <col style="width:3%;"><col style="width:12%;"><col style="width:8%;"><col style="width:4%;">
      <col style="width:9.5%;"><col style="width:10%;"><col style="width:4%;"><col style="width:8%;">
      <col style="width:9%;"><col style="width:8%;"><col style="width:8.5%;"><col style="width:8%;"><col style="width:8%;">
    </colgroup>
    <thead>
      <tr>
        {{-- Lebar dipasang LANGSUNG pada sel: mPDF mengabaikan <colgroup>,
             sehingga tabel jatuh ke pelebaran otomatis dan kolomnya meleset
             jauh dari dokumen aslinya. --}}
        <th rowspan="2" style="width:3%;">NO</th>
        <th rowspan="2" style="width:12%;">NAMA</th>
        <th rowspan="2" style="width:8%;">JABATAN</th>
        <th colspan="6">RINCIAN PERHITUNGAN</th>
        <th rowspan="2" style="width:8%;">UANG REPRE-<br>SENTATIF (Rp)</th>
        <th rowspan="2" style="width:8.5%;">TRANSPORT<br>/BBM/TIKET</th>
        <th rowspan="2" style="width:8%;">JUMLAH YANG<br>DITERIMA (Rp)</th>
        <th rowspan="2" style="width:8%;">TANDA TANGAN</th>
      </tr>
      <tr>
        <th style="width:4%;">JML<br>HARI</th>
        <th style="width:9.5%;">UANG HARIAN<br>DLM/LUAR<br>DAERAH (Rp)</th>
        <th style="width:10%;">JML UANG<br>HARIAN DLM/<br>LUAR DAERAH<br>(Rp)</th>
        <th style="width:4%;">JML<br>MLM</th>
        <th style="width:8%;">UANG<br>AKOMODASI<br>(Rp)</th>
        <th style="width:9%;">JUMLAH UANG<br>AKOMODASI<br>(Rp)</th>
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
        <div style="height:14pt;"></div>
        <div class="ttd-role">Setuju dibayar<br>Kuasa Pengguna Anggaran</div><table class="ttd-jarak"><tr><td></td></tr></table>
        <div class="ttd-nama">{{ $kpa->nama }}</div>
        <div>{{ $kpa->pangkat }}</div>
        <div>NIP. {{ $kpa->nip }}</div>
      </td>
      <td>
        <div style="text-align:right;padding-right:20pt;">Bandung, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {{ $bulanNpd }} {{ $npd->tahun }}</div>
        <div class="ttd-role">Lunas dibayar<br>Bendahara Pengeluaran Pembantu</div><table class="ttd-jarak"><tr><td></td></tr></table>
        <div class="ttd-nama">{{ $bpp->nama }}</div>
        <div>{{ $bpp->pangkat }}</div>
        <div>NIP. {{ $bpp->nip }}</div>
      </td>
    </tr>
  </table>
</body>
</html>
