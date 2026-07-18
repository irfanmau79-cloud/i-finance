<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Surat Perintah</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #999;
            padding: 6px 10px;
            text-align: left;
        }

        th {
            background-color: #eee;
        }
    </style>
</head>
<body>
    <h1>Daftar Surat Perintah</h1>

    <table>
        <thead>
            <tr>
                <th>Nomor SP</th>
                <th>Tanggal SP</th>
                <th>Unit Kerja</th>
                <th>Lokasi</th>
                <th>Nama Pengirim</th>
                <th>Tujuan Transfer</th>
                <th>Status SP</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($suratPerintahs as $suratPerintah)
                <tr>
                    <td>{{ $suratPerintah->nomor_sp }}</td>
                    <td>{{ $suratPerintah->tanggal_sp->format('d-m-Y') }}</td>
                    <td>{{ $suratPerintah->unit_kerja }}</td>
                    <td>{{ $suratPerintah->lokasi }}</td>
                    <td>{{ $suratPerintah->nama_pengirim }}</td>
                    <td>{{ $suratPerintah->tujuan_transfer }}</td>
                    <td>{{ $suratPerintah->status_sp }}</td>
                    <td>{{ $suratPerintah->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Belum ada data surat perintah.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
