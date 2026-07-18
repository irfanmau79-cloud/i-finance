<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Terima Kasih</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }

        .box {
            max-width: 500px;
            margin: 60px auto;
            text-align: center;
        }

        h1 {
            color: #0a0;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>Terima kasih!</h1>
        <p>Orderan Surat Perintah Anda berhasil dikirim dan akan segera diproses.</p>
        <p><a href="{{ route('sp.input.create') }}">Kirim orderan lain</a></p>
    </div>
</body>
</html>
