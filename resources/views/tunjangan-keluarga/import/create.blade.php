@extends('layouts.app')
@section('activeNav','tk-monitor')
@section('title','Import Awal Tunjangan Keluarga')
@section('content')
<div class="page-head"><div><div class="ph-title">Import Awal Tunjangan Keluarga</div><div class="ph-sub">Upload hanya membuat preview/dry-run. Master keluarga belum berubah sebelum konfirmasi.</div></div></div><div class="dash-card"><div class="auto"><strong>Header:</strong> Nama Pegawai, NIP, Nama Pasangan, Tanggal Lahir Pasangan, Status Pasangan, Nama Anak 1, Tanggal Lahir Anak 1, Status Anak 1, Keterangan Anak 1, Nama Anak 2, Tanggal Lahir Anak 2, Status Anak 2, Keterangan Anak 2.</div>@if($errors->any())<div class="err-box" style="display:block">{{ $errors->first() }}</div>@endif<form method="POST" action="{{ route('tunjangan.import.store') }}" enctype="multipart/form-data">@csrf<label class="fl">File Excel/CSV (maks. 10 MB)</label><input type="file" name="file" accept=".xlsx,.xls,.csv" required><button class="btn prim" style="margin-top:14px">Upload dan Preview</button></form></div>
@endsection
