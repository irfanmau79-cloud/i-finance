@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Versi Pagu')

@section('content')
<div class="dash-card">
    <h3>Versi Pagu — Tahun Anggaran {{ $tahun }}</h3>
    <div class="sub">
        Riwayat dokumen pagu: DPA Murni, DPA Pergeseran, DPA Perubahan, dan seterusnya.
        Hanya <strong>satu versi</strong> yang berlaku pada satu waktu &mdash; versi itulah yang dipakai seluruh perhitungan
        pagu, sisa tersedia, validasi NPD/SPM, dan dashboard.
    </div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('manajemen-data.import.master-anggaran.create') }}" class="btn prim">Import Versi Pagu Baru</a>
        <a href="{{ route('manajemen-data.index') }}" class="btn">Kembali ke Manajemen Data</a>
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:16px;overflow-x:auto;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Versi</th>
                    <th>Status</th>
                    <th class="num">Total Pagu</th>
                    <th class="num">Mata Anggaran</th>
                    <th>Dibuat</th>
                    <th>Diaktifkan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($versi as $v)
                    <tr>
                        <td>
                            <strong>{{ $v->nama }}</strong>
                            @if ($v->keterangan)
                                <br><small class="sub">{{ $v->keterangan }}</small>
                            @endif
                        </td>
                        <td>
                            @if ($v->status === \App\Models\VersiPagu::STATUS_AKTIF)
                                <span class="badge" style="background:#dcfce7;color:#166534;">BERLAKU</span>
                            @elseif ($v->status === \App\Models\VersiPagu::STATUS_DRAFT)
                                <span class="badge" style="background:#fef3c7;color:#92400e;">DRAFT</span>
                            @else
                                <span class="badge" style="background:#e5e7eb;color:#374151;">ARSIP</span>
                            @endif
                        </td>
                        <td class="num">Rp {{ fmt_rupiah((float) $v->total_pagu) }}</td>
                        <td class="num">{{ $v->jumlah_baris }}</td>
                        <td>
                            {{ $v->created_at?->format('d-m-Y H:i') }}
                            @if ($v->user)
                                <br><small class="sub">{{ $v->user->nama }}</small>
                            @endif
                        </td>
                        <td>
                            @if ($v->diaktifkan_at)
                                {{ $v->diaktifkan_at->format('d-m-Y H:i') }}
                                @if ($v->diaktifkanOleh)
                                    <br><small class="sub">{{ $v->diaktifkanOleh->nama }}</small>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="nav" style="gap:6px;">
                                <a class="btn" href="{{ route('versi-pagu.show', $v) }}">Rincian</a>

                                @if ($v->status !== \App\Models\VersiPagu::STATUS_AKTIF)
                                    <form method="POST" action="{{ route('versi-pagu.aktifkan', $v) }}"
                                          onsubmit="return confirm('Berlakukan versi &quot;{{ $v->nama }}&quot; sebagai pagu resmi? Seluruh pagu, sisa tersedia, dan dashboard akan langsung memakai angka versi ini.');">
                                        @csrf
                                        <button type="submit" class="btn prim">Aktifkan</button>
                                    </form>
                                @endif

                                @if ($v->status === \App\Models\VersiPagu::STATUS_DRAFT)
                                    <form method="POST" action="{{ route('versi-pagu.destroy', $v) }}"
                                          onsubmit="return confirm('Hapus versi draf &quot;{{ $v->nama }}&quot;? Tindakan ini permanen.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">
                            Belum ada versi pagu. Mulai dengan mengimpor DPA Murni.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sub" style="margin-top:12px;">
        Versi <strong>arsip</strong> sengaja tidak bisa dihapus &mdash; itu jejak riwayat pergeseran pagu.
        Versi arsip tetap bisa diaktifkan kembali kalau perlu mengembalikan pagu ke kondisi sebelumnya.
    </div>
</div>
@endsection
