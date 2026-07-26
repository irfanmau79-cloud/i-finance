<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color:#000; margin:0; line-height:1.4; }
  h1 { font-size: 14pt; margin: 0 0 2pt; }
  .sub { color:#555; margin-bottom: 10pt; }
  table.ringkasan { width:100%; border-collapse:collapse; margin-bottom: 10pt; }
  table.ringkasan td { padding: 4pt 8pt; border: 1pt solid #ccc; }
  table.ringkasan td.lbl { font-weight:bold; background:#f2f2f2; width: 33%; }
  table.detail { width:100%; border-collapse:collapse; }
  table.detail th, table.detail td { border: 0.5pt solid #999; padding: 3pt 5pt; }
  table.detail th { background:#e9eef3; text-align:left; }
  table.detail col.c-uraian { width:60%; }
  table.detail col.c-num { width:13.34%; }
  .num { text-align:right; white-space:nowrap; }
  .naik { color: #0f6e56; font-weight:bold; }
  .turun { color: #b3261e; font-weight:bold; }
  .prog-row td { font-weight:bold; background:#dbe5ee; }
  .keg-row td { font-weight:bold; background:#eef2f7; }
  .sub-row td { font-weight:bold; background:#f7f9fb; }
  .rek-row td { font-weight:bold; }
  .ind1 { padding-left: 8pt; }
  .ind2 { padding-left: 16pt; }
  .ind3 { padding-left: 24pt; }
  .ind4 { padding-left: 32pt; color:#333; }
</style>
</head>
<body>

@php
    $rupiah = fn (float $n) => number_format($n, 2, ',', '.');
    $totalEksisting = (float) $simulasiAnggaran->total_pagu_eksisting;
    $totalSimulasi = (float) $simulasiAnggaran->total_pagu_simulasi;
    $totalSelisih = (float) $simulasiAnggaran->total_selisih;
@endphp

<h1>Simulasi Pergeseran/Perubahan Anggaran &mdash; {{ $simulasiAnggaran->nama }}</h1>
<div class="sub">
    Dibuat oleh {{ $simulasiAnggaran->user->nama ?? '-' }} pada {{ $simulasiAnggaran->created_at->format('d-m-Y H:i') }}
    @if ($simulasiAnggaran->keterangan)
        <br>{{ $simulasiAnggaran->keterangan }}
    @endif
</div>

<table class="ringkasan">
    <tr>
        <td class="lbl">Pagu Anggaran (Eksisting)</td>
        <td class="num">Rp {{ $rupiah($totalEksisting) }}</td>
    </tr>
    <tr>
        <td class="lbl">Pagu Anggaran (Simulasi)</td>
        <td class="num">Rp {{ $rupiah($totalSimulasi) }}</td>
    </tr>
    <tr>
        <td class="lbl">Selisih</td>
        <td class="num {{ $totalSelisih > 0 ? 'naik' : ($totalSelisih < 0 ? 'turun' : '') }}">{{ $totalSelisih > 0 ? '+' : '' }}Rp {{ $rupiah($totalSelisih) }}</td>
    </tr>
</table>

<table class="detail">
    <colgroup>
        <col class="c-uraian">
        <col class="c-num">
        <col class="c-num">
        <col class="c-num">
    </colgroup>
    <thead>
        <tr>
            <th>Uraian</th>
            <th class="num">Anggaran (Eksisting)</th>
            <th class="num">Anggaran (Simulasi)</th>
            <th class="num">Selisih</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($tree as $prog)
            <tr class="prog-row">
                <td>{{ $prog['nama'] }}</td>
                <td class="num">Rp {{ $rupiah($prog['eksisting']) }}</td>
                <td class="num">Rp {{ $rupiah($prog['simulasi']) }}</td>
                <td class="num {{ $prog['selisih'] > 0 ? 'naik' : ($prog['selisih'] < 0 ? 'turun' : '') }}">{{ $prog['selisih'] > 0 ? '+' : '' }}Rp {{ $rupiah($prog['selisih']) }}</td>
            </tr>
            @foreach ($prog['kegiatan'] as $keg)
                <tr class="keg-row">
                    <td class="ind1">{{ $keg['nama'] }}</td>
                    <td class="num">Rp {{ $rupiah($keg['eksisting']) }}</td>
                    <td class="num">Rp {{ $rupiah($keg['simulasi']) }}</td>
                    <td class="num {{ $keg['selisih'] > 0 ? 'naik' : ($keg['selisih'] < 0 ? 'turun' : '') }}">{{ $keg['selisih'] > 0 ? '+' : '' }}Rp {{ $rupiah($keg['selisih']) }}</td>
                </tr>
                @foreach ($keg['subKegiatan'] as $sub)
                    <tr class="sub-row">
                        <td class="ind2">{{ $sub['nama'] }}</td>
                        <td class="num">Rp {{ $rupiah($sub['eksisting']) }}</td>
                        <td class="num">Rp {{ $rupiah($sub['simulasi']) }}</td>
                        <td class="num {{ $sub['selisih'] > 0 ? 'naik' : ($sub['selisih'] < 0 ? 'turun' : '') }}">{{ $sub['selisih'] > 0 ? '+' : '' }}Rp {{ $rupiah($sub['selisih']) }}</td>
                    </tr>
                    @foreach ($sub['rekening'] as $rekening)
                        <tr class="rek-row">
                            <td class="ind3">{{ $rekening['kode'] }} {{ $rekening['uraian'] }}</td>
                            <td class="num">Rp {{ $rupiah($rekening['eksisting']) }}</td>
                            <td class="num">Rp {{ $rupiah($rekening['simulasi']) }}</td>
                            <td class="num {{ $rekening['selisih'] > 0 ? 'naik' : ($rekening['selisih'] < 0 ? 'turun' : '') }}">{{ $rekening['selisih'] > 0 ? '+' : '' }}Rp {{ $rupiah($rekening['selisih']) }}</td>
                        </tr>
                        @foreach ($rekening['baris'] as $row)
                            <tr>
                                <td class="ind4">{{ $row->tagging_nama ?? 'Tanpa Tagging' }}</td>
                                <td class="num">Rp {{ $rupiah((float) $row->pagu_eksisting) }}</td>
                                <td class="num">Rp {{ $rupiah((float) $row->pagu_simulasi) }}</td>
                                <td class="num {{ (float) $row->selisih > 0 ? 'naik' : ((float) $row->selisih < 0 ? 'turun' : '') }}">{{ (float) $row->selisih > 0 ? '+' : '' }}Rp {{ $rupiah((float) $row->selisih) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            @endforeach
        @empty
            <tr><td colspan="4" style="text-align:center;padding:16pt;color:#777;">Simulasi ini belum memiliki baris mata anggaran.</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
