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

        .user-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-bar > div {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-bar form {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="user-bar">
        <h1>Daftar Surat Perintah</h1>
        <div>
            {{ auth()->user()->nama }} ({{ auth()->user()->role }})
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @php
        $bolehEditHapus = in_array(auth()->user()->role, ['pptk', 'bendahara'], true);
    @endphp

    <div class="actions">
        <a href="{{ route('surat-perintah.create') }}">Tambah SP</a>
        <a href="{{ route('surat-perintah.export-pdf') }}">Export PDF</a>
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
                        @if ($bolehEditHapus)
                            <div class="row-actions">
                                <a href="{{ route('surat-perintah.edit', $suratPerintah) }}">Edit</a>
                                <form method="POST" action="{{ route('surat-perintah.destroy', $suratPerintah) }}" onsubmit="return confirm('Yakin ingin menghapus Surat Perintah {{ $suratPerintah->nomor_sp }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">Hapus</button>
                                </form>
                            </div>
                        @else
                            &mdash;
                        @endif
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
