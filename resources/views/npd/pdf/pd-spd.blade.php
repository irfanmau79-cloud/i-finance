<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  @page { size: 215mm 330mm; margin: 13mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:7pt; color:#000; margin:0; line-height:1.1; }

  table.kop { width:100%; border-collapse:collapse; }
  table.kop td { border:none; padding:0; vertical-align:middle; }
  /* Kelas langsung, bukan `.kop .l1`: mPDF tidak menerapkan selector turunan
     pada isi sel tabel, sehingga seluruh kop tercetak 7pt biasa. Ukuran di
     bawah diukur dari dokumen tertandatangani (storage/app/acuan-pdf). */
  .kop .logo { width:86pt; text-align:center; }
  .kop .titles { text-align:center; }
  .spd-l1 { font-size:12pt; }
  .spd-l2 { font-size:15.5pt; font-weight:bold; letter-spacing:2pt; }
  .spd-l3 { font-size:8.5pt; }
  .kop .sp { width:86pt; }
  .kop-garis { border-bottom:1pt solid #000; height:2pt; margin-top:2pt; }
  .kop-garis2 { border-bottom:0.5pt solid #000; height:2.5pt; }

  .judul { text-align:center; font-weight:bold; font-size:8.5pt; margin:4pt 0 4pt; }
  table.head2 { width:100%; border-collapse:collapse; margin-bottom:2pt; }
  table.head2 td { border:none; padding:0; vertical-align:top; font-size:7pt; }
  table.head2 td.right { text-align:left; width:160pt; }
  table.rowkv { width:100%; border-collapse:collapse; }
  table.rowkv td { border:none; padding:0; }
  table.rowkv td.k { width:54pt; }

  table.wrap { width:100%; border-collapse:collapse; }
  /* mPDF mengabaikan pemilih berjenjang "table.wrap > tbody > tr > td",
     jadi garis tepi dan perataan atas dipasang langsung pada kelas selnya.
     Tanpa ini kolom Keterangan kehilangan kotaknya dan isinya tercetak
     melayang di tengah tabel. */
  .wrap-kiri, .wrap-kanan { padding:0; vertical-align:top; border:1pt solid #000; }
  .wrap-kiri { width:87%; }
  .wrap-kanan { width:13%; }

  table.rinci { width:100%; border-collapse:collapse; table-layout:fixed; }
  table.rinci th, table.rinci td { border-left:1pt solid #000; border-right:1pt solid #000; padding:1.5pt 4pt; vertical-align:top; line-height:1.15; word-wrap:break-word; overflow-wrap:break-word; }
  table.rinci th { text-align:center; font-weight:bold; border-top:1pt solid #000; border-bottom:1pt solid #000; }
  table.rinci tr.uraian td { border-bottom:1pt solid #000; }
  table.rinci tr.kat-row td { border-bottom:1pt solid #000; }
  table.rinci tr.jml-row td { border-top:1pt solid #000; border-bottom:1pt solid #000; }
  table.rinci tr.dat td { border-top:none; border-bottom:none; }
  /* Buang garis vertikal terluar - sudah ditangani border wrapper. GAS memakai
     td:first-child/:last-child; mPDF tidak mendukung pseudo-class itu (crash di
     Tag\Td::open), jadi dipasang sebagai kelas eksplisit dengan hasil sama. */
  table.rinci .bl0 { border-left:none; }
  table.rinci .br0 { border-right:none; }

  table.ketbl { width:100%; height:100%; border-collapse:collapse; }
  table.ketbl th { text-align:center; font-weight:bold; border-bottom:1pt solid #000; padding:1.5pt 4pt; }
  table.ketbl td { vertical-align:top; padding:2pt 4pt; }

  .num { text-align:right; white-space:nowrap; }
  .center { text-align:center; } .bold { font-weight:bold; }
  .kat { font-weight:bold; }

  .terbilang-line { margin:3pt 0; font-style:italic; }

  .tgl-kanan { text-align:right; margin:3pt 0; padding-right:30pt; }
  table.ttd { width:100%; border-collapse:collapse; margin-top:2pt; }
  .ttd-lbl { text-align:center; }
  table.ttd td { border:none; padding:0; width:50%; vertical-align:top; text-align:center; font-size:7pt; }
  /* Jarak tanda tangan dibuat dengan tabel bersarang, BUKAN div kosong:
     di dalam sel tabel mPDF mengabaikan tinggi div kosong sehingga jaraknya
     ambruk jadi 8pt padahal dokumen aslinya 34pt. */
  table.ttd-jarak { width:100%; border-collapse:collapse; }
  table.ttd-jarak td { height:32pt; border:none; padding:0; }
  table.ttd-jeda td { height:1pt; border:none; padding:0; }
  .ttd-nama { font-weight:bold; }
  .garis-penuh { border-top:1.5pt solid #000; margin:6pt 0; }

  table.rampung { border-collapse:collapse; margin:0 auto; }
  table.rampung td { border:none; padding:1.5pt 8pt; font-size:7.5pt; }
  table.rampung .lbl { text-align:left; }
  table.rampung .rp { text-align:left; width:22pt; }
  table.rampung .val { text-align:right; white-space:nowrap; min-width:90pt; }
  table.rampung .val.uline { border-bottom:1pt solid #000; }

  .kpa-box { margin-top:14pt; width:50%; margin-left:50%; text-align:center; font-size:7.5pt; page-break-inside:avoid; }
  .kpabox-sp { height:46pt; }
  .kpabox-nama { font-weight:bold; }
  table.rinci tr { page-break-inside:avoid; }
  table.ttd { page-break-inside:avoid; }
  table.rampung { page-break-inside:avoid; }
  /* Blok penutup (terbilang s.d. Kuasa Pengguna Anggaran) diusahakan tetap utuh. */
  .blok-penutup { page-break-inside:avoid; }
</style>
</head>
<body>
  <table class="kop">
    <tr>
      <td class="logo">
        @if ($logoPath ?? false)
          <img src="{{ $logoPath }}" alt="Logo" style="width:58pt;height:auto;">
        @endif
      </td>
      <td class="titles">
        <div class="spd-l1">PEMERINTAH DAERAH PROVINSI JAWA BARAT</div>
        <div class="spd-l2">INSPEKTORAT&nbsp;&nbsp;DAERAH</div>
        <div class="spd-l3">JALAN SURAPATI No.4 TELP. (022) 4237174 - 4231567 FAKSIMIL (022) 4231567</div>
        <div class="spd-l3" style="font-style:italic;">Website: www.inspektorat.jabarprov.go.id e-mail: inspektorat@jabarprov.go.id</div>
        <div class="spd-l3">BANDUNG &ndash; KODE POS 40115</div>
      </td>
      <td class="sp"></td>
    </tr>
  </table>
  <div class="kop-garis"></div>
  <div class="kop-garis2"></div>

  <div class="judul">RINCIAN BIAYA PERJALANAN DINAS</div>

  <table class="head2">
    <tr>
      <td>
        <table class="rowkv"><tr><td class="k">Nomor SP</td><td>: {{ $detail['nomor_sp'] ?? '' }}</td></tr></table>
        <table class="rowkv"><tr><td class="k">Tanggal</td><td>: {{ $tglSp }}</td></tr></table>
      </td>
      <td class="right">
        <table class="rowkv"><tr><td class="k">Nomor BKU</td><td>:</td></tr></table>
        <table class="rowkv"><tr><td class="k">Tanggal BKU</td><td>:</td></tr></table>
        <table class="rowkv"><tr><td class="k">Kodering</td><td>: {{ $npd->masterAnggaran->kode_rekening_bersih }}</td></tr></table>
      </td>
    </tr>
  </table>

  <table class="wrap"><tr>
   <td class="wrap-kiri">
    <table class="rinci">
      <colgroup>
        <col style="width:4%;"><col style="width:30%;"><col style="width:28%;">
        <col style="width:10%;"><col style="width:13%;"><col style="width:15%;">
      </colgroup>
      <thead>
        <tr>
          <th class="bl0" style="width:4%;">No</th>
          <th colspan="4">Perincian Biaya</th>
          <th class="br0" style="width:15%;">Jumlah (Rp)</th>
        </tr>
      </thead>
      <tbody>
        <tr class="uraian"><td class="bl0 br0" colspan="6" style="font-size:7pt;">{{ $uraianBiaya }} terhitung tanggal {{ $tglBerangkat }} s.d {{ $tglPulang }} dalam rangka {{ $detail['uraian_sp'] ?? '' }}, berdasarkan Surat Perintah Nomor: {{ $detail['nomor_sp'] ?? '' }} tanggal {{ $tglSp }}</td></tr>
        <tr class="kat-row"><td class="center bold bl0">I</td><td class="kat br0" colspan="5">Perjalanan Dinas Biasa Dalam Daerah</td></tr>
        {!! $sr['rows_uh'] !!}
        <tr class="jml-row"><td colspan="5" class="bold bl0" style="text-align:right;padding-right:8pt;">Jumlah Uang Harian</td><td class="num bold br0">{{ fmt_rupiah($sr['t_uh']) }}</td></tr>
        <tr class="kat-row"><td class="center bold bl0">II</td><td class="kat br0" colspan="5">Uang Akomodasi</td></tr>
        {!! $sr['rows_ak'] !!}
        <tr class="jml-row"><td colspan="5" class="bold bl0" style="text-align:right;padding-right:8pt;">Jumlah Uang Akomodasi</td><td class="num bold br0">{{ fmt_rupiah($sr['t_ak']) }}</td></tr>
        <tr class="kat-row"><td class="center bold bl0">III</td><td class="kat br0" colspan="5">Uang Transportasi</td></tr>
        {!! $sr['rows_tr'] !!}
        <tr class="jml-row"><td colspan="5" class="bold bl0" style="text-align:right;padding-right:8pt;">Jumlah Uang Transportasi</td><td class="num bold br0">{{ fmt_rupiah($sr['t_tr']) }}</td></tr>
        {!! $blokRepresentatif !!}
        <tr class="jml-row total-row"><td colspan="5" class="bold bl0" style="text-align:right;padding-right:8pt;">Jumlah Total</td><td class="num bold br0">{{ fmt_rupiah($npd->nominal) }}</td></tr>
      </tbody>
    </table>
   </td>
   <td class="wrap-kanan">
    <table class="ketbl">
      <thead><tr><th>Keterangan</th></tr></thead>
      <tbody><tr><td>&nbsp;</td></tr></tbody>
    </table>
   </td>
  </tr></table>

  <div class="blok-penutup">
  <div class="terbilang-line">Terbilang : # {{ $npd->terbilang }} #</div>

  <div class="tgl-kanan">Bandung, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {{ $bulanNpd }} {{ $npd->tahun }}</div>
  <table class="ttd">
    <tr>
      <td>
        <div class="ttd-lbl">Telah dibayar sejumlah</div>
        <div class="ttd-lbl">Rp {{ fmt_rupiah($npd->nominal) }}</div>
        <table class="ttd-jeda"><tr><td></td></tr></table>
        <div class="ttd-lbl">Bendahara Pengeluaran Pembantu</div>
        <table class="ttd-jarak"><tr><td></td></tr></table>
        <div class="ttd-nama">{{ $bpp->nama }}</div>
        <div>NIP. {{ $bpp->nip }}</div>
      </td>
      <td>
        <div class="ttd-lbl">Telah menerima jumlah uang sebesar</div>
        <div class="ttd-lbl">Rp {{ fmt_rupiah($npd->nominal) }}</div>
        <table class="ttd-jeda"><tr><td></td></tr></table>
        <div class="ttd-lbl">Yang menerima</div>
        <table class="ttd-jarak"><tr><td></td></tr></table>
        <div class="ttd-nama">{{ $penerima->nama }}</div>
        <div>NIP. {{ $penerima->nip }}</div>
      </td>
    </tr>
  </table>

  <div class="garis-penuh"></div>

  <div class="judul" style="margin:4pt 0;">PERHITUNGAN SPD RAMPUNG</div>
  <table class="rampung">
    <tr><td class="ttd-lbl">Ditetapkan sejumlah</td><td class="rp">Rp</td><td class="val">{{ fmt_rupiah($npd->nominal) }}</td></tr>
    <tr><td class="ttd-lbl">Yang telah dibayar</td><td class="rp">Rp</td><td class="val uline">{{ fmt_rupiah($npd->nominal) }}</td></tr>
    <tr><td class="ttd-lbl">Sisa kurang/lebih</td><td class="rp">Rp</td><td class="val">0,00</td></tr>
  </table>

  <div class="garis-penuh"></div>

  <div class="kpa-box">
    <div>Kuasa Pengguna Anggaran</div>
    <div class="kpabox-sp"></div>
    <div class="ttd-nama">{{ $kpa->nama }}</div>
    <div>{{ $kpa->pangkat }}</div>
    <div>NIP. {{ $kpa->nip }}</div>
  </div>
  </div>
</body>
</html>
