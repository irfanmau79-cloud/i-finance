{{--
    Cetak PDF Data Realisasi Anggaran (mPDF, A4 melintang).

    Gayanya ditulis ulang di sini dan tidak memakai stylesheet aplikasi:
    mPDF hanya memahami sebagian kecil CSS, dan variabel warna (var(--navy))
    tidak didukung sama sekali.
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
    tr.kegiatan td { font-weight: bold; }
    tr.sub td { font-weight: bold; }
    tr.tagging td { color: #64748b; }
    tr.total td { background: #e9eef3; font-weight: bold; color: #15314a; }
    .uraian { color: #64748b; font-size: 7.5pt; }
</style>

@php
    $tgl = fn ($iso) => \Illuminate\Support\Carbon::parse($iso)->translatedFormat('d F Y');
    $total = $hasil['total'];
    $indent = ['program' => 0, 'kegiatan' => 8, 'sub' => 16, 'rekening' => 24, 'tagging' => 32];
@endphp

<h1>DATA REALISASI ANGGARAN</h1>
<p class="meta">Inspektorat Daerah Provinsi Jawa Barat &mdash; Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</p>
<p class="meta">Periode {{ $tgl($dari) }} s.d. {{ $tgl($sampai) }}</p>
<p class="meta">
    Pagu adalah nilai setahun dan tidak mengikuti rentang tanggal; persentase berarti bagian
    pagu setahun yang terserap pada periode ini.
</p>

<table>
    <thead>
        <tr>
            <th style="width:34%;">Program / Kegiatan / Sub Kegiatan / Kode Rekening / Tagging</th>
            <th class="num" style="width:13%;">Pagu Setahun</th>
            <th class="num" style="width:13%;">Realisasi NPD</th>
            <th class="num" style="width:13%;">Realisasi LS</th>
            <th class="num" style="width:14%;">Realisasi Aktual</th>
            <th class="num" style="width:13%;">% thd Pagu</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($hasil['tree'] as $program)
            @include('manajemen-data.realisasi-periode._baris-pdf', [
                'kelas' => 'program', 'pad' => $indent['program'],
                'nama' => $program['nama'], 'uraian' => null, 'angka' => $program['angka'],
            ])

            @foreach ($program['kegiatan'] as $kegiatan)
                @include('manajemen-data.realisasi-periode._baris-pdf', [
                    'kelas' => 'kegiatan', 'pad' => $indent['kegiatan'],
                    'nama' => $kegiatan['nama'], 'uraian' => null, 'angka' => $kegiatan['angka'],
                ])

                @foreach ($kegiatan['sub'] as $sub)
                    @include('manajemen-data.realisasi-periode._baris-pdf', [
                        'kelas' => 'sub', 'pad' => $indent['sub'],
                        'nama' => $sub['nama'], 'uraian' => null, 'angka' => $sub['angka'],
                    ])

                    @foreach ($sub['rekening'] as $rekening)
                        @include('manajemen-data.realisasi-periode._baris-pdf', [
                            'kelas' => 'rekening', 'pad' => $indent['rekening'],
                            'nama' => $rekening['nama'], 'uraian' => $rekening['uraian'], 'angka' => $rekening['angka'],
                        ])

                        @foreach ($rekening['tagging'] as $tagging)
                            @include('manajemen-data.realisasi-periode._baris-pdf', [
                                'kelas' => 'tagging', 'pad' => $indent['tagging'],
                                'nama' => $tagging['nama'], 'uraian' => null, 'angka' => $tagging['angka'],
                            ])
                        @endforeach
                    @endforeach
                @endforeach
            @endforeach
        @empty
            <tr><td colspan="6" style="text-align:center;">Belum ada mata anggaran aktif untuk ditampilkan.</td></tr>
        @endforelse

        <tr class="total">
            <td>TOTAL SELURUH MATA ANGGARAN</td>
            <td class="num">Rp {{ fmt_rupiah($total['pagu']) }}</td>
            <td class="num">Rp {{ fmt_rupiah($total['realisasi_npd']) }}</td>
            <td class="num">Rp {{ fmt_rupiah($total['realisasi_ls']) }}</td>
            <td class="num">Rp {{ fmt_rupiah($total['realisasi_aktual']) }}</td>
            <td class="num">{{ number_format($total['persentase_realisasi'], 2, ',', '.') }}%</td>
        </tr>
    </tbody>
</table>
