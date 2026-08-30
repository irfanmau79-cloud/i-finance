<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'i-Finance') &mdash; Inspektorat Jabar</title>
@include('layouts.partials.styles')
</head>
<body>
<div class="wrap">
@yield('content')
</div>
@include('layouts.partials.select-cari')
@include('layouts.partials.kalender-tanggal')
@include('layouts.partials.input-rupiah')
</body>
</html>
