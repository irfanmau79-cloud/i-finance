<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'i-Finance') &mdash; Inspektorat Jabar</title>
@include('layouts.partials.styles')
</head>
<body>
<div style="max-width:1100px;margin:0 auto;">
@yield('content')
</div>
@include('layouts.partials.select-cari')
@include('layouts.partials.kalender-tanggal')
@include('layouts.partials.input-rupiah')
</body>
</html>
