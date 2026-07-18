<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Surat Perintah</title>
    <style>
        body {
            font-family: arial, sans-serif;
        }

        h1, h2 {
            text-align: center;
            margin: 0;
        }

        h1 {
            font-size: 13pt;
        }

        h2 {
            font-size: 10pt;
            font-weight: normal;
            margin-bottom: 10px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1pt solid #000;
            padding: 3px 4px;
        }

        th {
            font-size: 7pt;
            background-color: #eee;
            text-align: center;
        }

        td {
            font-size: 6.5pt;
        }

        td.center {
            text-align: center;
        }

        tfoot td {
            font-size: 6.5pt;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>DAFTAR SURAT PERINTAH</h1>
    <h2>Inspektorat Daerah Provinsi Jawa Barat</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 10%;">Nomor SP</th>
                <th style="width: 8%;">Tanggal SP</th>
                <th style="width: 12%;">Unit Kerja</th>
                <th style="width: 10%;">Lokasi</th>
                <th style="width: 12%;">Nama Pengirim</th>
                <th style="width: 12%;">Tujuan Transfer</th>
                <th style="width: 12%;">Rincian Tgl Bayar</th>
                <th style="width: 11%;">Status SP</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($suratPerintahs as $index => $suratPerintah)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $suratPerintah->nomor_sp }}</td>
                    <td class="center">{{ $suratPerintah->tanggal_sp?->format('d-m-Y') }}</td>
                    <td>{{ $suratPerintah->unit_kerja }}</td>
                    <td>{{ $suratPerintah->lokasi }}</td>
                    <td>{{ $suratPerintah->nama_pengirim }}</td>
                    <td>{{ $suratPerintah->tujuan_transfer }}</td>
                    <td>{{ $suratPerintah->rincian_tgl_bayar }}</td>
                    <td>{{ $suratPerintah->status_sp }}</td>
                    <td>{{ $suratPerintah->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center">Belum ada data surat perintah.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9" style="text-align: right;">Total Jumlah SP</td>
                <td class="center">{{ $suratPerintahs->count() }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
