@extends('layouts.app')

@section('activeNav', 'sp-rekap')
@section('title', 'Rekapitulasi Pembayaran SP')

@section('content')
@php
    // Tanda centang yang sama untuk kedua keadaan; yang membedakan hanya
    // kelas pembungkusnya (lihat .rk-cek di styles).
    $centang = '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
    $labelStatus = [
        \App\Services\RekapPembayaranSpService::SELESAI => 'sudah dibayar (NPD Selesai)',
        \App\Services\RekapPembayaranSpService::PROSES => 'NPD sudah dibuat, belum Selesai',
    ];
@endphp

<div class="dash-card wf-card">
    <h3>Rekapitulasi Pembayaran SP</h3>
    <div class="sub">
        Komponen pembayaran tiap Surat Perintah yang sudah dibuatkan Nota Pencairan Dana.
        SP yang diduplikat dihitung sebagai satu SP.
    </div>

    <div class="tbl-tools">
        <input type="text" id="rk-search" placeholder="Cari Nomor SP, Unit Kerja, Koordinator, atau Keterangan&hellip;">
        <div class="rk-legenda" aria-label="Keterangan tanda">
            <span><span class="rk-cek selesai">{!! $centang !!}</span> NPD Selesai</span>
            <span><span class="rk-cek proses">{!! $centang !!}</span> NPD sudah dibuat, belum Selesai</span>
            <span><span class="rk-cek"></span> Belum ada NPD</span>
        </div>
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi tbl-fixed rk-tabel" id="rk-table" style="min-width:900px;">
            <colgroup>
                <col style="width:16%;"><col style="width:14%;"><col style="width:16%;"><col style="width:30%;">
                <col style="width:8%;"><col style="width:8%;"><col style="width:8%;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">Nomor SP</th>
                    <th rowspan="2">Unit Kerja</th>
                    <th rowspan="2">Koordinator Pembayaran</th>
                    <th rowspan="2">Keterangan</th>
                    <th colspan="{{ count($komponen) }}" class="mid">Pembayaran</th>
                </tr>
                <tr>
                    @foreach ($komponen as $judul)
                        <th class="mid">{{ $judul }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr data-cari="{{ Str::lower($b['nomor_sp'].' '.$b['unit_kerja'].' '.$b['koordinator'].' '.$b['keterangan']) }}">
                        <td style="font-weight:600;">{{ $b['nomor_sp'] }}</td>
                        <td>{{ $b['unit_kerja'] }}</td>
                        {{-- --tegas = navy untuk TULISAN (ikut terang di mode gelap). --}}
                        <td style="font-weight:700;color:var(--tegas);">{{ $b['koordinator'] ?: '—' }}</td>
                        <td>{{ $b['keterangan'] ?: '—' }}</td>
                        @foreach ($komponen as $kunci => $judul)
                            @php
                                $sel = $b['pembayaran'][$kunci];
                                $status = $sel['status'];
                                $keterangan = $status
                                    ? $judul.': '.$labelStatus[$status].' — '.implode('; ', $sel['npd'])
                                    : $judul.': belum ada NPD';
                            @endphp
                            <td class="mid">
                                <span class="rk-cek{{ $status ? ' '.$status : '' }}" data-komponen="{{ $kunci }}" data-status="{{ $status ?? 'kosong' }}"
                                      role="img" aria-label="{{ $keterangan }}" title="{{ $keterangan }}">@if ($status){!! $centang !!}@endif</span>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 4 + count($komponen) }}" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data surat perintah.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sub" style="margin-top:10px;">
        Kotak tercentang begitu NPD untuk komponen itu dibuat, walau masih Draft; centang hijau penuh berarti NPD-nya sudah
        <strong>Selesai</strong>. NPD yang dibatalkan tidak dihitung. Arahkan kursor ke kotak untuk melihat nomor NPD-nya.
    </div>
</div>

<script>
(function () {
    const cari = document.getElementById('rk-search');
    if (! cari) return;

    cari.addEventListener('input', function () {
        const q = cari.value.trim().toLowerCase();
        document.querySelectorAll('#rk-table tbody tr[data-cari]').forEach(function (row) {
            row.hidden = q !== '' && ! row.dataset.cari.includes(q);
        });
    });
})();
</script>
@endsection
