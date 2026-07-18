<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tambah Surat Perintah</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }

        form {
            max-width: 600px;
        }

        .field {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 4px;
        }

        input[type="text"],
        input[type="date"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 80px;
        }

        .error {
            color: #c00;
            font-size: 0.9em;
            margin-top: 4px;
        }

        .alert {
            border: 1px solid #c00;
            background-color: #fee;
            padding: 10px;
            margin-bottom: 15px;
        }

        .actions {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Tambah Surat Perintah</h1>

    @if ($errors->any())
        <div class="alert">
            <strong>Terjadi kesalahan:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('surat-perintah.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label for="nomor_sp">Nomor SP</label>
            <input type="text" id="nomor_sp" name="nomor_sp" value="{{ old('nomor_sp') }}">
        </div>

        <div class="field">
            <label for="tanggal_sp">Tanggal SP</label>
            <input type="date" id="tanggal_sp" name="tanggal_sp" value="{{ old('tanggal_sp') }}">
        </div>

        <div class="field">
            <label for="unit_kerja">Unit Kerja</label>
            <select id="unit_kerja" name="unit_kerja">
                <option value="">-- Pilih Unit Kerja --</option>
                @foreach ([
                    'Inspektur Pembantu I',
                    'Inspektur Pembantu II',
                    'Inspektur Pembantu III',
                    'Inspektur Pembantu IV',
                    'Inspektur Pembantu Investigasi',
                    'Sekretariat',
                    'Subbagian Tata Usaha',
                ] as $unit)
                    <option value="{{ $unit }}" @selected(old('unit_kerja') === $unit)>{{ $unit }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="lokasi">Lokasi</label>
            <input type="text" id="lokasi" name="lokasi" value="{{ old('lokasi') }}">
        </div>

        <div class="field">
            <label for="nama_pengirim">Nama Pengirim</label>
            <input type="text" id="nama_pengirim" name="nama_pengirim" value="{{ old('nama_pengirim') }}">
        </div>

        <div class="field">
            <label for="tujuan_transfer">Tujuan Transfer</label>
            <input type="text" id="tujuan_transfer" name="tujuan_transfer" value="{{ old('tujuan_transfer') }}">
        </div>

        <div class="field">
            <label for="irban_dibayar">Irban Dibayar</label>
            <select id="irban_dibayar" name="irban_dibayar">
                <option value="">-- Pilih --</option>
                <option value="1" @selected(old('irban_dibayar') === '1')>Ya</option>
                <option value="0" @selected(old('irban_dibayar') === '0')>Tidak</option>
            </select>
        </div>

        <div class="field">
            <label for="rincian_tgl_bayar">Rincian Tanggal Bayar</label>
            <input type="text" id="rincian_tgl_bayar" name="rincian_tgl_bayar" value="{{ old('rincian_tgl_bayar') }}">
        </div>

        <div class="field">
            <label for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan">{{ old('keterangan') }}</textarea>
        </div>

        <div class="field">
            <label for="status_sp">Status SP</label>
            <select id="status_sp" name="status_sp">
                <option value="">-- Pilih Status SP --</option>
                <option value="Baru" @selected(old('status_sp') === 'Baru')>Baru</option>
                <option value="Revisi" @selected(old('status_sp') === 'Revisi')>Revisi</option>
            </select>
        </div>

        <div class="field">
            <label for="file_url">File SP (PDF)</label>
            <input type="file" id="file_url" name="file_url" accept="application/pdf">
        </div>

        <div class="actions">
            <button type="submit">Simpan</button>
            <a href="{{ route('surat-perintah.index') }}">Batal</a>
        </div>
    </form>
</body>
</html>
