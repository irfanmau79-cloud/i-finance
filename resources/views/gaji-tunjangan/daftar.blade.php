@extends('layouts.app')

@section('activeNav', 'gt-daftar')
@section('title', 'Daftar Rincian Penghasilan')

@section('content')
@include('gaji-tunjangan._styles')

<div class="dash-card gt-card">
    <h3>Daftar Rincian Penghasilan</h3>
    <div class="sub">Rekap dokumen keterangan penghasilan yang telah dibuat pegawai.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    {{-- Pencarian gtdRender(): di GAS menyaring di peramban, di sini lewat
         server supaya tetap bekerja lintas halaman paginasi. --}}
    <form method="GET" style="margin:12px 0;flex:0 0 auto;">
        <div class="gt-search" style="max-width:340px;">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="gtd-cari" class="gt-inp" type="text" name="q" value="{{ $cari }}"
                   placeholder="Cari nama / NIP&hellip;" style="padding-left:36px;width:100%;">
        </div>
    </form>

    <div class="gt-tabel-box">
        <div class="gt-tabel-wrap">
            @if ($dokumen->total() === 0)
                <div class="gt-empty">Belum ada dokumen{{ $cari !== '' ? ' yang cocok' : '' }}.</div>
            @else
                <table class="gt-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Nama / NIP</th>
                            <th style="text-align:left;">Jabatan</th>
                            <th>Periode Penghasilan</th>
                            <th>Nomor</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dokumen as $d)
                            <tr>
                                <td>
                                    <div class="gt-peg">
                                        <div class="n">{{ $d->nama }}</div>
                                        <div class="m">{{ $d->nip }}</div>
                                    </div>
                                </td>
                                <td>{{ $d->jabatan }}</td>
                                <td class="gt-ctr">{{ $d->labelPeriode() }}</td>
                                <td class="gt-ctr" style="font-size:11px;">{{ $d->nomor }}</td>
                                <td class="gt-ctr">
                                    <div class="aksi-wrap" style="grid-template-columns:repeat(2,30px);width:68px;">
                                        <a class="ic-btn" title="Cetak Dokumen" target="_blank" rel="noopener"
                                           href="{{ route('gaji-tunjangan.rincian.cetak', $d) }}">
                                            <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('gaji-tunjangan.rincian.destroy', $d) }}"
                                              style="display:contents;"
                                              onsubmit="return confirm('Nomor {{ $d->nomor }} akan dihapus permanen beserta berkas PDF-nya.\nNomor surat sesudahnya akan mundur satu urutan.\n\nTindakan ini tidak dapat dibatalkan.')">
                                            @csrf @method('DELETE')
                                            <button class="ic-btn danger" title="Hapus" type="submit">
                                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @include('gaji-tunjangan._pager', ['baris' => $dokumen, 'satuan' => 'dokumen'])
</div>
@endsection
