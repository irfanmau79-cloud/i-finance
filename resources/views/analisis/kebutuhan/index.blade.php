@extends('layouts.app')

@section('activeNav', 'keb-data')
@section('title', 'Data Kebutuhan Anggaran Pengawasan')

@section('content')
<style>
  .keb-table-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px;margin-top:12px}
  .keb-table{min-width:1100px;table-layout:fixed}
  .keb-unit{font-size:12px;font-weight:600;color:var(--tegas)}
  .keb-hapus{display:inline}
</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Data Kebutuhan Anggaran Pengawasan</b></div>
    <div class="ph-title">Data Kebutuhan Anggaran Pengawasan</div>
  </div>
  <div class="ph-actions">
    @if ($unitRole)
      <a class="btn prim" href="{{ route('kebutuhan.create') }}" style="white-space:nowrap;">+ Estimasi Kebutuhan</a>
    @endif
    <a class="btn" href="{{ route('kebutuhan.index') }}" style="white-space:nowrap;">&#8635; Muat Ulang</a>
  </div>
</div>

@if (session('success'))
  <div class="sumbar ok" style="margin-bottom:16px;"><span>{{ session('success') }}</span></div>
@endif

<div class="dash-card">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <h3 style="margin:0;">Rekap Kebutuhan Anggaran Pengawasan</h3>
    <span class="badge" style="background:var(--info-bg);color:var(--info);">{{ $jumlah }}</span>
    <span class="sub" style="margin:0 0 0 auto;">
      {{ $unitRole ? 'Menampilkan data unit: '.$unitRole : 'Menampilkan seluruh unit (hanya-baca)' }}
    </span>
  </div>
  <div class="sub" style="margin-top:4px;">
    Estimasi kebutuhan anggaran kegiatan pengawasan Tahun Anggaran {{ $tahun }}, diinput masing-masing Inspektur Pembantu.
  </div>

  <div class="keb-table-wrap">
    <table class="realisasi npd-table keb-table">
      <colgroup>
        <col style="width:12%;"><col style="width:12%;"><col style="width:11%;"><col style="width:11%;"><col style="width:10%;">
        <col style="width:10%;"><col style="width:12%;"><col style="width:15%;"><col style="width:7%;">
      </colgroup>
      <thead><tr>
        <th>Unit Kerja</th><th>Tanggal</th>
        <th class="num">Uang Harian Dalam Kota</th>
        <th class="num">Uang Harian Luar Kota</th>
        <th class="num">Akomodasi</th>
        <th class="num">Transport</th>
        <th class="num">Estimasi Kebutuhan</th>
        <th>Keterangan</th>
        <th style="text-align:center;">Aksi</th>
      </tr></thead>
      <tbody>
        @forelse ($baris as $k)
          <tr>
            <td class="keb-unit">{{ $k->unitSingkat() }}</td>
            <td>{{ $k->rentangTanggal() }}</td>
            <td class="num">{{ $k->tarif_uh_dalam ?: '-' }}</td>
            <td class="num">{{ $k->tarif_uh_luar ?: '-' }}</td>
            <td class="num">{{ fmt_rupiah($k->total_akomodasi) }}</td>
            <td class="num">{{ fmt_rupiah($k->total_transport) }}</td>
            <td class="num" style="font-weight:700;">{{ fmt_rupiah($k->total_estimasi) }}</td>
            <td>{{ $k->keteranganTampil() }}</td>
            <td style="text-align:center;">
              @if ($unitRole && $k->unit_kerja === $unitRole)
                <form method="POST" action="{{ route('kebutuhan.destroy', $k) }}" class="keb-hapus"
                      onsubmit="return confirm('Hapus data kebutuhan anggaran ini secara permanen? Tindakan ini tidak dapat dibatalkan.');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn" style="padding:3px 9px;font-size:11px;color:var(--err-teks);">Hapus</button>
                </form>
              @else
                <span class="sub" style="margin:0;">&mdash;</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="9" style="text-align:center;color:var(--mut);padding:20px;">
            Belum ada data kebutuhan anggaran{{ $unitRole ? ' untuk '.$unitRole : '' }}.
          </td></tr>
        @endforelse
      </tbody>
      @if ($jumlah > 0)
        <tfoot>
          <tr>
            <td colspan="6" style="text-align:right;font-weight:700;">Total Estimasi Kebutuhan</td>
            <td class="num" style="font-weight:800;">{{ fmt_rupiah($totalEstimasi) }}</td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>

  @if ($baris->hasPages())
    <div class="pager">
      <div class="pager-info">Menampilkan {{ $baris->firstItem() }}&ndash;{{ $baris->lastItem() }} dari {{ $baris->total() }} kegiatan</div>
      <div class="pager-btns">
        <a class="pg-btn" href="{{ $baris->previousPageUrl() ?? '#' }}"@if (! $baris->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
        <a class="pg-btn" href="{{ $baris->nextPageUrl() ?? '#' }}"@if (! $baris->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
      </div>
    </div>
  @endif
</div>
@endsection
