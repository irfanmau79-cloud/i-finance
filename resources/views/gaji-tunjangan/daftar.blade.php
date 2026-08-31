@extends('layouts.app')

@section('activeNav', 'gt-daftar')
@section('title', 'Daftar Rincian Penghasilan')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Gaji dan Tunjangan</b> / Daftar Rincian Penghasilan</div>
        <div class="ph-title">Daftar Rincian Penghasilan</div>
    </div>
    <div class="ph-actions"><a class="btn" href="{{ route('gaji-tunjangan.rincian.create') }}">Buat Dokumen</a></div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif

<div class="dash-card">
    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th>Periode Penghasilan</th>
                    <th>Tanggal</th>
                    <th>Penandatangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dokumen as $d)
                    <tr>
                        <td><strong>{{ $d->nomor }}</strong></td>
                        <td>{{ $d->nama }}</td>
                        <td>{{ $d->nip }}</td>
                        <td><span class="sub">{{ $d->jabatan ?: '-' }}</span></td>
                        <td>{{ $d->labelPeriode() }}</td>
                        <td>{{ $d->tanggal_dokumen->format('d-m-Y') }}</td>
                        <td><span class="sub">{{ $d->penandatangan_nama }}</span></td>
                        <td style="white-space:nowrap;">
                            <a class="btn" href="{{ route('gaji-tunjangan.rincian.cetak', $d) }}" target="_blank" rel="noopener">Cetak</a>
                            <form method="POST" action="{{ route('gaji-tunjangan.rincian.destroy', $d) }}"
                                  style="display:inline;"
                                  onsubmit="return confirm('Hapus dokumen nomor {{ $d->nomor }} secara permanen?\n\nNomor surat sesudahnya akan mundur satu urutan. Tindakan ini tidak dapat dibatalkan.')">
                                @csrf @method('DELETE')
                                <button class="btn danger" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:26px;color:var(--mut);">
                            Belum ada dokumen yang dibuat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $dokumen->links() }}
</div>
@endsection
