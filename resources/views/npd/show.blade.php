@extends('layouts.app')

@section('activeNav', 'npd')
@section('title', 'Detail NPD')

@section('content')
<div class="dash-card wf-card">
    <h3>Detail NPD &mdash; {{ \App\Models\Npd::JENIS_LABEL[$npd->jenis] ?? strtoupper($npd->jenis) }}</h3>
    <div class="sub">{{ $npd->nomor_lengkap ?? 'Belum bernomor (masih Draft)' }}</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="rev">
        <div class="grp">
            <div class="gt">Informasi Umum</div>
            <div class="li"><span class="k">Status</span><span class="v"><span class="badge st-diterima">{{ $npd->status }}</span></span></div>
            <div class="li"><span class="k">Tanggal NPD</span><span class="v">{{ $npd->tanggal_npd->format('d-m-Y') }}</span></div>
            <div class="li"><span class="k">Bulan / Tahun</span><span class="v">{{ $npd->bulan }} / {{ $npd->tahun }}</span></div>
            <div class="li"><span class="k">KEU</span><span class="v">{{ $npd->keu }}</span></div>
            <div class="li"><span class="k">Dibuat oleh</span><span class="v">{{ $npd->dibuatOleh->nama ?? '—' }}</span></div>
        </div>

        <div class="grp">
            <div class="gt">Sumber Dana</div>
            <div class="li"><span class="k">Program</span><span class="v">{{ $npd->masterAnggaran->program }}</span></div>
            <div class="li"><span class="k">Kegiatan</span><span class="v">{{ $npd->masterAnggaran->kegiatan }}</span></div>
            <div class="li"><span class="k">Sub Kegiatan</span><span class="v">{{ $npd->masterAnggaran->sub_kegiatan }}</span></div>
            <div class="li"><span class="k">Kode Rekening</span><span class="v">{{ $npd->masterAnggaran->kode_rekening }}</span></div>
            <div class="li"><span class="k">Tagging</span><span class="v">{{ $npd->masterAnggaran->tagging->nama ?? '-' }}</span></div>
            <div class="li"><span class="k">Pagu</span><span class="v">Rp {{ number_format((float) $npd->masterAnggaran->pagu, 2, ',', '.') }}</span></div>
        </div>

        <div class="grp">
            <div class="gt">Nominal</div>
            <div class="li"><span class="k">Nominal NPD</span><span class="v">Rp {{ number_format((float) $npd->nominal, 2, ',', '.') }}</span></div>
            <div class="li"><span class="k">Terbilang</span><span class="v">{{ $npd->terbilang }}</span></div>
        </div>

        @if ($npd->catatan)
        <div class="grp">
            <div class="gt">Catatan</div>
            <div class="li"><span class="v">{{ $npd->catatan }}</span></div>
        </div>
        @endif
    </div>

    <h3 style="margin-top:22px;">Daftar Penerima</h3>
    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Rekening</th>
                    <th>Bruto</th>
                    <th>PPh</th>
                    <th>Biaya</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($npd->penerima as $p)
                    <tr>
                        <td>{{ $p->nama }}</td>
                        <td>{{ $p->rekening ?? '—' }}</td>
                        <td>Rp {{ number_format((float) $p->bruto, 2, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $p->pph, 2, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $p->biaya, 2, ',', '.') }}</td>
                        <td>{{ $p->keterangan ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--mut);padding:20px;">Belum ada penerima.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:16px;">
        <a class="btn" href="{{ route('npd.index') }}">Kembali ke Daftar NPD</a>
    </div>
</div>
@endsection
