<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  @page { size: 215mm 330mm; margin: 12mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:8pt; color:#000; margin:0; line-height:1.35; }
  .kop { text-align:center; padding-bottom:6pt; }
  .kop .l1 { font-size:11pt; font-weight:bold; }
  .kop .l2 { font-size:10pt; font-weight:bold; margin-top:2pt; }

  .detail { margin:8pt 0 6pt; }
  table.drow { width:100%; border-collapse:collapse; }
  table.drow td { vertical-align:top; padding:1pt 0; border:none; }
  table.drow td.k { width:90pt; } table.drow td.s { width:8pt; }

  table.lamp { width:100%; border-collapse:collapse; margin-top:6pt; font-size:7pt; }
  table.lamp th, table.lamp td { border:1pt solid #000; padding:4pt 5pt; vertical-align:top; }
  table.lamp th { text-align:center; background:#f2f2f2; vertical-align:middle; }
  .num { text-align:right; white-space:nowrap; }
  .center { text-align:center; } .bold { font-weight:bold; } .vtop { vertical-align:top; }
  .ket { font-size:6.5pt; }

  table.ttd { width:100%; border-collapse:collapse; margin-top:30pt; }
  table.ttd td { vertical-align:top; padding:0; border:none; }
  table.ttd td.sp { width:55%; }
  table.ttd td.col { width:45%; text-align:center; }
  .ttd .role { margin-bottom:56pt; }
  .ttd .nama { font-weight:bold; }
</style>
</head>
<body>
  <div class="kop">
    <div class="l1">INSPEKTORAT DAERAH PROVINSI JAWA BARAT</div>
    <div class="l2">LAMPIRAN NOTA PENCAIRAN DANA (NPD)</div>
  </div>

  <div class="detail">
    <table class="drow">
      <tr><td class="k">Program</td><td class="s">:</td><td>{{ $npd->masterAnggaran->program }}</td></tr>
      <tr><td class="k">Kegiatan</td><td class="s">:</td><td>{{ $npd->masterAnggaran->kegiatan }}</td></tr>
      <tr><td class="k">Sub Kegiatan</td><td class="s">:</td><td>{{ $npd->masterAnggaran->sub_kegiatan }}</td></tr>
    </table>
  </div>

  <table class="lamp">
    <thead>
      <tr>
        <th rowspan="2" style="width:12%;">Nama Penerima</th>
        <th rowspan="2" style="width:12%;">Bank &amp; Nomor Rekening</th>
        <th colspan="5">Nominal</th>
        <th rowspan="2" style="width:24%;">Keterangan</th>
      </tr>
      <tr>
        <th>Bruto</th>
        <th>PPN</th>
        <th>PPh</th>
        <th>Biaya<br>KU/RTGS</th>
        <th>Transfer</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="vtop">{{ $penerima->nama }}</td>
        <td class="vtop">{{ $penerima->rekening }}</td>
        <td class="num vtop">{{ fmt_rupiah($npd->nominal) }}</td>
        <td class="vtop"></td>
        <td class="num vtop"></td>
        <td class="num vtop">{{ fmt_rupiah(0) }}</td>
        <td class="num vtop">{{ fmt_rupiah($npd->nominal) }}</td>
        <td class="vtop ket"><b>{{ $npd->masterAnggaran->kode_rekening }}</b><br>{{ $keterangan }}</td>
      </tr>
      <tr class="jml">
        <td colspan="2" class="center bold">J u m l a h</td>
        <td class="num bold">{{ fmt_rupiah($npd->nominal) }}</td>
        <td class="center bold">-</td>
        <td class="center bold">-</td>
        <td class="num bold">{{ fmt_rupiah(0) }}</td>
        <td class="num bold">{{ fmt_rupiah($npd->nominal) }}</td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <table class="ttd">
    <tr>
      <td class="sp"></td>
      <td class="col">
        <div class="role">Pejabat Pelaksana Teknis Kegiatan</div>
        <div class="nama">{{ $pptk->nama }}</div>
        <div>{{ $pptk->pangkat }}</div>
        <div>NIP. {{ $pptk->nip }}</div>
      </td>
    </tr>
  </table>
</body>
</html>
