{{--
    Cetak PDF Simulasi Realisasi (mPDF, A4 melintang).

    Gayanya ditulis ulang di sini dan tidak memakai stylesheet aplikasi: mPDF
    hanya memahami sebagian kecil CSS, dan variabel warna tidak didukung.
--}}
<style>
    body { font-family: arial, sans-serif; font-size: 8.5pt; color: #1f2937; }
    h1 { font-size: 13pt; margin: 0 0 2px; color: #15314a; }
    .meta { font-size: 8.5pt; color: #64748b; margin: 0 0 2px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 0.4pt solid #cbd5e1; padding: 3.5pt 5pt; vertical-align: top; }
    th { background: #e9eef3; color: #15314a; font-size: 7.5pt; text-transform: uppercase; }
    td.num, th.num { text-align: right; }
    tr.program td { background: #e9eef3; font-weight: bold; color: #15314a; }
    tr.kegiatan td, tr.sub td { font-weight: bold; }
    tr.tagging td { color: #64748b; }
    tr.total td { background: #e9eef3; font-weight: bold; color: #15314a; }
    .rencana { color: #64748b; font-size: 7.5pt; }
    .lewat { color: #b3261e; font-weight: bold; }
</style>

@php
    $rp = fn ($n) => number_format((float) $n, 2, ',', '.');
    $ps = fn ($n) => number_format((float) $n, 2, ',', '.').'%';
@endphp

<h1>SIMULASI REALISASI &mdash; {{ $simulasiRealisasi->nama }}</h1>
<p class="meta">Inspektorat Daerah Provinsi Jawa Barat &mdash; Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</p>
@if ($simulasiRealisasi->keterangan)<p class="meta">{{ $simulasiRealisasi->keterangan }}</p>@endif
<p class="meta">
    Realisasi (Estimasi) = realisasi yang sudah terjadi + Proyeksi. Angka Proyeksi bersifat
    perkiraan dan tidak tercatat sebagai transaksi mana pun.
</p>

<table>
    <thead>
        <tr>
            <th style="width:29%;">Program / Kegiatan / Sub Kegiatan / Kode Rekening / Tagging</th>
            <th class="num" style="width:12%;">Pagu</th>
            <th class="num" style="width:11%;">Realisasi</th>
            <th class="num" style="width:12%;">Sisa Anggaran</th>
            <th class="num" style="width:12%;">Proyeksi</th>
            <th class="num" style="width:12%;">Realisasi (Estimasi)</th>
            <th class="num" style="width:12%;">Sisa Anggaran (Estimasi)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($tree as $program)
            <tr class="program">
                <td>{{ $program['nama'] }}</td>
                <td class="num">{{ $rp($program['pagu']) }}</td>
                <td class="num">{{ $rp($program['realisasi']) }}</td>
                <td class="num">{{ $rp($program['sisa_anggaran']) }}</td>
                <td class="num">{{ $rp($program['proyeksi']) }}</td>
                <td class="num">{{ $rp($program['realisasi_estimasi']) }}</td>
                <td class="num">{{ $rp($program['sisa_estimasi']) }}</td>
            </tr>

            @foreach ($program['kegiatan'] as $kegiatan)
                <tr class="kegiatan">
                    <td style="padding-left:13pt;">{{ $kegiatan['nama'] }}</td>
                    <td class="num">{{ $rp($kegiatan['pagu']) }}</td>
                    <td class="num">{{ $rp($kegiatan['realisasi']) }}</td>
                    <td class="num">{{ $rp($kegiatan['sisa_anggaran']) }}</td>
                    <td class="num">{{ $rp($kegiatan['proyeksi']) }}</td>
                    <td class="num">{{ $rp($kegiatan['realisasi_estimasi']) }}</td>
                    <td class="num">{{ $rp($kegiatan['sisa_estimasi']) }}</td>
                </tr>

                @foreach ($kegiatan['subKegiatan'] as $sub)
                    <tr class="sub">
                        <td style="padding-left:21pt;">{{ $sub['nama'] }}</td>
                        <td class="num">{{ $rp($sub['pagu']) }}</td>
                        <td class="num">{{ $rp($sub['realisasi']) }}</td>
                        <td class="num">{{ $rp($sub['sisa_anggaran']) }}</td>
                        <td class="num">{{ $rp($sub['proyeksi']) }}</td>
                        <td class="num">{{ $rp($sub['realisasi_estimasi']) }}</td>
                        <td class="num">{{ $rp($sub['sisa_estimasi']) }}</td>
                    </tr>

                    @foreach ($sub['rekening'] as $rekening)
                        <tr>
                            <td style="padding-left:29pt;">{{ $rekening['kode'] }} {{ $rekening['uraian'] }}</td>
                            <td class="num">{{ $rp($rekening['pagu']) }}</td>
                            <td class="num">{{ $rp($rekening['realisasi']) }}</td>
                            <td class="num">{{ $rp($rekening['sisa_anggaran']) }}</td>
                            <td class="num">{{ $rp($rekening['proyeksi']) }}</td>
                            <td class="num">{{ $rp($rekening['realisasi_estimasi']) }}</td>
                            <td class="num">{{ $rp($rekening['sisa_estimasi']) }}</td>
                        </tr>

                        @foreach ($rekening['baris'] as $row)
                            <tr class="tagging">
                                <td style="padding-left:37pt;">
                                    {{ $row->tagging_nama ?? 'Tanpa Tagging' }}
                                    @if ($row->items->isNotEmpty())
                                        <br><span class="rencana">{{ $row->items->map(fn ($i) => $i->nama.' ('.$rp($i->nominal).')')->implode('; ') }}</span>
                                    @endif
                                </td>
                                <td class="num">{{ $rp($row->pagu) }}</td>
                                <td class="num">{{ $rp($row->realisasi) }}</td>
                                <td class="num">{{ $rp($row->sisa_anggaran) }}</td>
                                <td class="num">{{ $rp($row->proyeksi_total) }}</td>
                                <td class="num">{{ $rp($row->realisasi_estimasi) }}</td>
                                <td class="num {{ $row->sisa_estimasi < 0 ? 'lewat' : '' }}">{{ $rp($row->sisa_estimasi) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            @endforeach
        @endforeach

        <tr class="total">
            <td>TOTAL SELURUH MATA ANGGARAN</td>
            <td class="num">{{ $rp($total['pagu']) }}</td>
            <td class="num">{{ $rp($total['realisasi']) }}</td>
            <td class="num">{{ $rp($total['sisa_anggaran']) }}</td>
            <td class="num">{{ $rp($total['proyeksi']) }}</td>
            <td class="num">{{ $rp($total['realisasi_estimasi']) }}</td>
            <td class="num">{{ $rp($total['sisa_estimasi']) }}</td>
        </tr>
    </tbody>
</table>
