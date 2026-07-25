@extends('layouts.app')

@section('activeNav', 'npd')
@section('title', 'Pembuatan NPD')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Pembuatan NPD</b></div>
        <div class="ph-title">Pembuatan Nota Pencairan Dana</div>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block">
        <strong>Terjadi kesalahan:</strong>
        <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="dash-card">
    @if ($bolehBuat)
        <div id="npd-picker-wrap">
            <div style="display:flex;align-items:center;gap:10px;margin-top:6px;flex-wrap:wrap;">
                <div style="font-size:13.5px;color:var(--mut);">Pilih jenis Nota Pencairan Dana yang akan dibuat:</div>
                <button type="button" class="btn" id="npd-picker-toggle" style="margin-left:auto;padding:8px 14px;">Sembunyikan Pilihan Jenis</button>
            </div>
            <div class="pick" id="npd-picker">
                <a class="opt" href="{{ route('npd.bj.create') }}">
                    <div class="ic"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                    <div class="t">NPD Barang/Jasa</div>
                </a>
                <a class="opt" href="{{ route('npd.pd.create') }}">
                    <div class="ic"><svg viewBox="0 0 24 24"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg></div>
                    <div class="t">NPD Perjalanan Dinas</div>
                </a>
                <a class="opt" href="{{ route('npd.ns.create') }}">
                    <div class="ic"><svg viewBox="0 0 24 24"><path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 10v1a7 7 0 0 1-14 0v-1"/><line x1="12" y1="19" x2="12" y2="22"/></svg></div>
                    <div class="t">NPD Narasumber</div>
                </a>
                <a class="opt" href="{{ route('npd.tr.create') }}">
                    <div class="ic"><svg viewBox="0 0 24 24"><path d="M3 17h13v-5l-2-4H3z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/><path d="M16 12h3l2 3v2h-3"/></svg></div>
                    <div class="t">NPD Transport</div>
                </a>
                <a class="opt" href="{{ route('npd.kd.create') }}">
                    <div class="ic"><svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2.5 2.5 6 2.5s6-1.5 6-2.5v-5"/><line x1="22" y1="10" x2="22" y2="15"/></svg></div>
                    <div class="t">NPD Kontribusi Diklat</div>
                </a>
            </div>
        </div>
    @endif

    <div style="margin-top:26px;">
        <div style="font-size:15px;font-weight:700;color:var(--navy);margin-bottom:10px;">Daftar Nota Pencairan Dana</div>

        <form method="GET" action="{{ route('npd.index') }}" class="tbl-tools" style="margin-bottom:14px;">
            <select name="jenis" style="max-width:220px;">
                <option value="">-- Semua Jenis --</option>
                @foreach (\App\Models\Npd::JENIS_LABEL as $kode => $label)
                    <option value="{{ $kode }}" @selected($filters['jenis'] === $kode)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" style="max-width:260px;">
                <option value="semua" @selected($filters['status'] === 'semua')>-- Semua Status --</option>
                @foreach (\App\Models\Npd::STATUS_LIST as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn prim" style="white-space:nowrap;">Filter</button>
            @if ($filters['jenis'] !== '' || request()->has('status'))
                <a href="{{ route('npd.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
            @endif
        </form>

        @include('npd._tabel-workflow', ['npds' => $npds, 'tampilkanKelola' => true])
    </div>
</div>

<script>
(function () {
    const toggle = document.getElementById('npd-picker-toggle');
    const picker = document.getElementById('npd-picker');
    if (toggle && picker) {
        const KEY = 'npd-picker-hidden';
        const apply = (hidden) => {
            picker.style.display = hidden ? 'none' : '';
            toggle.textContent = hidden ? 'Tampilkan Pilihan Jenis' : 'Sembunyikan Pilihan Jenis';
        };
        apply(localStorage.getItem(KEY) === '1');
        toggle.addEventListener('click', () => {
            const hidden = picker.style.display !== 'none';
            localStorage.setItem(KEY, hidden ? '1' : '0');
            apply(hidden);
        });
    }
})();
</script>
@endsection
