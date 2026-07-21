<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  @page { size: 215mm 330mm; margin: 15mm 15mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color:#000; margin:0; line-height:1.4; }
  .sheet { width: 100%; }
  table.kop { width:100%; border-collapse:collapse; margin-bottom:6pt; }
  table.kop td { vertical-align:middle; padding:0; border:none; }
  .kop .logo { width:70pt; text-align:center; }
  .kop .logo img { width:58pt; height:auto; }
  .kop .titles { text-align:left; font-family:'Arial Narrow', Arial, sans-serif; }
  .kop .titles .l1 { font-size:12pt; font-weight:bold; }
  .kop .titles .l2 { font-size:11pt; font-weight:bold; margin-top:2pt; }
  .kop .sp { width:70pt; }

  table.head-meta { width:100%; border-collapse:collapse; margin:10pt 0 6pt; }
  table.head-meta td { width:50%; vertical-align:top; padding:0; border:none; }
  table.head-meta td.r { text-align:left; }

  .detail { margin:6pt 0; }
  table.drow { width:100%; border-collapse:collapse; }
  table.drow td { vertical-align:top; padding:1pt 0; border:none; }
  table.drow td.k { width:130pt; }
  table.drow td.s { width:8pt; }
  table.drow td.v { font-weight:bold; }

  span.box { display:inline-block; width:11pt; height:11pt; border:1pt solid #000; text-align:center; line-height:10pt; margin-right:4pt; font-weight:bold; }
  table.jenis-row { width:100%; border-collapse:collapse; margin:6pt 0; }
  table.jenis-row td { vertical-align:middle; padding:0; border:none; width:50%; }
  .jenis-row .jl { display:inline-block; width:130pt; }
  .jenis-row .js { display:inline-block; width:8pt; }

  .terbilang { font-style:italic; margin:5pt 0 8pt 138pt; }

  table.rincian { width:99.5%; border-collapse:collapse; margin-top:4pt; table-layout:fixed; }
  table.rincian th, table.rincian td { border:1pt solid #000; padding:4pt 4pt; vertical-align:top; word-wrap:break-word; overflow-wrap:break-word; }
  table.rincian th { text-align:center; background:#f2f2f2; }
  table.rincian col.c-kode { width:22%; }
  table.rincian col.c-uraian { width:27%; }
  table.rincian col.c-angg { width:18%; }
  table.rincian col.c-sisa { width:17%; }
  table.rincian col.c-cair { width:15%; }
  .num { text-align:right; white-space:nowrap; }
  .center { text-align:center; } .bold { font-weight:bold; }
  .muted { color:#555; } .sub-tag { color:#555; }

  table.ttd { width:100%; border-collapse:collapse; margin-top:30pt; }
  table.ttd td { width:50%; text-align:center; vertical-align:top; padding:0; border:none; }
  .ttd .role { margin-bottom:56pt; }
  .ttd .nama { font-weight:bold; }
</style>
</head>
<body>
<div class="sheet">
  <table class="kop">
    <tr>
      <td class="logo">
        @if ($logoPath)
          <img src="{{ $logoPath }}" alt="Logo" style="width:58pt;height:auto;">
        @endif
      </td>
      <td class="titles">
        <div class="l1">INSPEKTORAT DAERAH PROVINSI JAWA BARAT</div>
        <div class="l2">NOTA PENCAIRAN DANA (NPD)</div>
      </td>
      <td class="sp"></td>
    </tr>
  </table>

  <table class="head-meta">
    <tr>
      <td>Nomor&nbsp;&nbsp;: {{ $npd->nomor_lengkap }}</td>
      <td class="r">Tanggal&nbsp;&nbsp;: {{ $npd->tanggal_npd->translatedFormat('d F Y') }}</td>
    </tr>
  </table>

  <div class="detail">
    <table class="jenis-row">
      <tr>
        <td>
          <span class="jl">Jenis NPD</span><span class="js">:</span><span class="box">{!! $npd->jenis_panjar === 'Panjar' ? '&#10003;' : '&nbsp;' !!}</span><b>Panjar</b>
        </td>
        <td>
          <span class="box">{!! $npd->jenis_panjar === 'Tanpa Panjar' ? '&#10003;' : '&nbsp;' !!}</span><b>Tanpa Panjar</b>
        </td>
      </tr>
    </table>
    <table class="drow">
      <tr><td class="k">PPTK</td><td class="s">:</td><td class="v">{{ $pptk->nama }}</td></tr>
      <tr><td class="k">Program</td><td class="s">:</td><td class="v">{{ $npd->masterAnggaran->program }}</td></tr>
      <tr><td class="k">Kegiatan</td><td class="s">:</td><td class="v">{{ $npd->masterAnggaran->kegiatan }}</td></tr>
      <tr><td class="k">Sub Kegiatan</td><td class="s">:</td><td class="v">{{ $npd->masterAnggaran->sub_kegiatan }}</td></tr>
      <tr><td class="k">No. DPA</td><td class="s">:</td><td class="v">{{ $noDpa }}</td></tr>
      <tr><td class="k">Tahun Anggaran</td><td class="s">:</td><td class="v">{{ $npd->tahun }}</td></tr>
      <tr><td class="k">Nominal</td><td class="s">:</td><td class="v">Rp{{ fmt_rupiah($npd->nominal) }}</td></tr>
    </table>
  </div>

  <div class="terbilang">terbilang : # {{ $npd->terbilang }} #</div>

  <table class="rincian">
    <colgroup>
      <col class="c-kode"><col class="c-uraian"><col class="c-angg"><col class="c-sisa"><col class="c-cair">
    </colgroup>
    <thead>
      <tr>
        <th>KODE REKENING</th>
        <th>URAIAN</th>
        <th>ANGGARAN</th>
        <th>SISA ANGGARAN</th>
        <th>PENCAIRAN</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="border-bottom:none;white-space:nowrap;">{{ $npd->masterAnggaran->kode_rekening }}</td>
        <td class="muted" style="border-bottom:none;">{{ $npd->masterAnggaran->uraian_rekening }}</td>
        <td class="num" style="border-bottom:none;">{{ fmt_rupiah($npd->masterAnggaran->pagu) }}</td>
        <td class="num" style="border-bottom:none;">{{ fmt_rupiah($sisaSebelum) }}</td>
        <td class="num" style="border-bottom:none;">{{ fmt_rupiah($npd->nominal) }}</td>
      </tr>
      <tr>
        <td style="border-top:none;"></td>
        <td class="sub-tag" style="border-top:none;">{{ $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging->nama ?? '') }}</td>
        <td class="num" style="border-top:none;">{{ fmt_rupiah($npd->masterAnggaran->pagu) }}</td>
        <td style="border-top:none;"></td>
        <td style="border-top:none;"></td>
      </tr>
      <tr>
        <td colspan="2" class="center bold">J u m l a h</td>
        <td class="num bold">{{ fmt_rupiah($npd->masterAnggaran->pagu) }}</td>
        <td class="num bold">{{ fmt_rupiah($sisaSebelum) }}</td>
        <td class="num bold">{{ fmt_rupiah($npd->nominal) }}</td>
      </tr>
    </tbody>
  </table>

  <table class="ttd">
    <tr>
      <td>
        <div class="role">Disetujui oleh,<br>Kuasa Pengguna Anggaran</div>
        <div class="nama">{{ $kpa->nama }}</div>
        <div>{{ $kpa->pangkat }}</div>
        <div>NIP. {{ $kpa->nip }}</div>
      </td>
      <td>
        <div class="role">Disiapkan oleh,<br>Pejabat Pelaksana Teknis Kegiatan</div>
        <div class="nama">{{ $pptk->nama }}</div>
        <div>{{ $pptk->pangkat }}</div>
        <div>NIP. {{ $pptk->nip }}</div>
      </td>
    </tr>
  </table>
</div>
</body>
</html>
