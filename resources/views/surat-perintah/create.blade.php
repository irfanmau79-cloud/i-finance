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

        .hint {
            color: #555;
            font-size: 0.9em;
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

    <form method="POST" action="{{ route(($isPublicForm ?? false) ? 'sp.input.store' : 'surat-perintah.store') }}" enctype="multipart/form-data">
        @csrf

        @include('surat-perintah._form')

        <div class="actions">
            <button type="submit">Simpan</button>
            @unless ($isPublicForm ?? false)
                <a href="{{ route('surat-perintah.index') }}">Batal</a>
            @endunless
        </div>
    </form>
</body>
</html>
