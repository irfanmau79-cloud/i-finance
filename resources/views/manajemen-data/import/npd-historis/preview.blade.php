@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Preview Import NPD Historis')

@section('content')
<div class="dash-card">
    <h3>Batch #{{ $import->id }} — {{ $import->nama_file }}</h3>
    <div class="sub" style="font-weight:700;">Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</div>
    <div class="sub">Status {{ $import->status }} · Hash {{ $import->file_hash }} · Eksekusi batch bersifat atomik: seluruh baris yang valid/warning berhasil bersama atau transaksi dibatalkan.</div>
    @if(session('success'))<div class="sub" style="color:var(--ok);font-weight:700;">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="err-box" style="display:block;"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="kpi-grid" style="margin-top:14px;">
        <div class="dash-card"><h3>{{ $import->total_baris }}</h3><div class="sub">Total</div></div>
        <div class="dash-card"><h3>{{ $import->jumlah_valid }}</h3><div class="sub">Valid</div></div>
        <div class="dash-card"><h3>{{ $import->jumlah_warning }}</h3><div class="sub">Warning</div></div>
        <div class="dash-card"><h3>{{ $import->jumlah_error }}</h3><div class="sub">Error</div></div>
        <div class="dash-card"><h3>{{ $import->jumlah_duplikat }}</h3><div class="sub">Duplikat</div></div>
        <div class="dash-card"><h3>Rp {{ fmt_rupiah($import->total_nominal) }}</h3><div class="sub">Nominal Bruto</div></div>
        <div class="dash-card"><h3>Rp {{ fmt_rupiah($import->total_ppn) }}</h3><div class="sub">PPN</div></div>
        <div class="dash-card"><h3>Rp {{ fmt_rupiah($import->total_pph) }}</h3><div class="sub">Total PPh</div></div>
    </div>
    <div class="nav" style="margin-top:14px;">
        <a class="btn" href="{{ route('manajemen-data.import.npd-historis.report', [$import, 'validation']) }}">Unduh Laporan Validasi</a>
        @if($import->status === 'committed')<a class="btn" href="{{ route('manajemen-data.import.npd-historis.report', [$import, 'final']) }}">Unduh Laporan Final</a>@endif
        @if($import->status === 'staged' && !$import->kedaluwarsa())
        <form method="POST" action="{{ route('manajemen-data.import.npd-historis.confirm', $import) }}" onsubmit="return confirm('Impor {{ $import->jumlah_valid + $import->jumlah_warning }} dokumen dengan total Rp {{ fmt_rupiah($import->total_nominal) }}?');">@csrf<button class="btn prim">Konfirmasi Import</button></form>
        @endif
    </div>
</div>

<div class="dash-card" style="margin-top:16px;">
<form method="GET" class="nav">
    <select name="hasil"><option value="">Semua hasil</option>@foreach(['valid','warning','error','duplicate'] as $v)<option value="{{ $v }}" @selected(request('hasil')===$v)>{{ $v }}</option>@endforeach</select>
    <select name="jenis_kode"><option value="">Semua jenis</option>@foreach(\App\Models\Npd::JENIS_LABEL as $k=>$v)<option value="{{ $k }}" @selected(request('jenis_kode')===$k)>{{ $v }}</option>@endforeach</select>
    <input name="tahun" placeholder="Tahun" value="{{ request('tahun') }}"><select name="status_target"><option value="">Semua status</option><option value="Selesai">Selesai</option><option value="Dibatalkan">Batal</option></select>
    <label><input type="checkbox" name="manual" value="1" @checked(request('manual'))> Penerima manual</label><button class="btn">Filter</button>
</form>
</div>

<div class="dash-card" style="margin-top:16px;">
<div class="sp-table-wrap" style="overflow-x:auto;"><table class="realisasi"><thead><tr><th>Baris</th><th>Hasil</th><th>Pesan</th><th>Tanggal</th><th>Tahun/Bulan</th><th>Nomor</th><th>Jenis</th><th>Program/Kegiatan</th><th>Sub Kegiatan</th><th>Kode Rekening</th><th>Tagging</th><th>Penerima / Mapping</th><th>Bruto/Pajak</th><th>Pagu</th><th>RAK Bulan</th><th>Realisasi Sebelum</th><th>Proyeksi</th><th>Sisa</th><th>Mapping</th></tr></thead><tbody>
@forelse($baris as $row)<tr><td>{{ $row->nomor_baris }}</td><td>{{ $row->hasil }}</td><td>{{ implode(' | ', $row->pesan ?? []) }}</td><td>{{ $row->tanggal_npd?->format('Y-m-d') ?? '—' }}</td><td>{{ $row->tahun }}/{{ $row->bulan }}</td><td>{{ $row->nomor_npd }}</td><td>{{ $row->jenis_input }} → {{ $row->jenis_kode }}</td><td>{{ $row->program }}<br>{{ $row->kegiatan }}</td><td>{{ $row->sub_kegiatan }}</td><td>{{ $row->kode_rekening }}</td><td>{{ $row->tagging_nama ?? '—' }}</td><td>{{ $row->penerima }}<br>{{ $row->penerima_manual ? 'Snapshot manual' : 'Master exact' }} · {{ $row->rekening_penerima }}</td><td>Rp {{ fmt_rupiah($row->nominal_bruto) }}<br>PPN {{ fmt_rupiah($row->ppn) }} · PPh {{ fmt_rupiah((float)$row->pph1+(float)$row->pph2) }}</td><td>{{ $row->pagu!==null?'Rp '.fmt_rupiah($row->pagu):'—' }}</td><td>{{ $row->rak_bulan!==null?'Rp '.fmt_rupiah($row->rak_bulan):'—' }}</td><td>{{ $row->realisasi_sebelum!==null?'Rp '.fmt_rupiah($row->realisasi_sebelum):'—' }}</td><td>{{ $row->realisasi_proyeksi!==null?'Rp '.fmt_rupiah($row->realisasi_proyeksi):'—' }}</td><td>{{ $row->sisa_proyeksi!==null?'Rp '.fmt_rupiah($row->sisa_proyeksi):'—' }}</td><td>{{ $row->mapping_status }}</td></tr>
@empty<tr><td colspan="19">Tidak ada baris untuk filter ini.</td></tr>@endforelse
</tbody></table></div>{{ $baris->links() }}
</div>

<div class="dash-grid" style="margin-top:16px;"><div class="dash-card"><h3>Total per Jenis</h3>@foreach($totalsByType as $t)<div class="sub">{{ $t->jenis_kode }}: {{ $t->jumlah }} · Rp {{ fmt_rupiah($t->nominal) }}</div>@endforeach</div><div class="dash-card"><h3>Total per Tahun/Bulan/Status</h3>@foreach($totalsByPeriod as $t)<div class="sub">{{ $t->tahun }}/{{ $t->bulan }} {{ $t->status_target }}: {{ $t->jumlah }} · Rp {{ fmt_rupiah($t->nominal) }}</div>@endforeach</div></div>
@endsection
