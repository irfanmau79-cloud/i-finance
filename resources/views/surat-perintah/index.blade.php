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

        .alert-success {
            border: 1px solid #0a0;
            background-color: #efe;
            padding: 10px;
            margin-bottom: 15px;
        }

        .actions {
            margin-bottom: 15px;
        }

        .row-actions {
            display: flex;
            gap: 8px;
        }

        .row-actions form {
            display: inline;
            margin: 0;
        }

        .row-actions button {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Daftar Surat Perintah</h1>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <div class="actions">
        <a href="{{ route('surat-perintah.create') }}">Tambah SP</a>
    </div>

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
                <th>Aksi</th>
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
                    <td>
                        <div class="row-actions">
                            <a href="{{ route('surat-perintah.edit', $suratPerintah) }}">Edit</a>
                            <form method="POST" action="{{ route('surat-perintah.destroy', $suratPerintah) }}" onsubmit="return confirm('Yakin ingin menghapus Surat Perintah {{ $suratPerintah->nomor_sp }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Belum ada data surat perintah.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
