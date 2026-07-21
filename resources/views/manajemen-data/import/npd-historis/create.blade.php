@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Import Data NPD')

@section('content')
<div class="dash-card">
    <h3>Import Data NPD Historis</h3>
    <div class="sub" style="font-weight:700;">Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</div>
    <div class="sub">Upload, preview, validasi, lalu konfirmasi. Preview hanya menulis staging dan tidak membuat NPD, SP, SPM, workflow, atau nomor baru.</div>
    <div class="sub">{{ config('anggaran.catatan_scope') }}</div>
    @if ($errors->any())<div class="err-box" style="display:block;"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="nav" style="margin:14px 0;">
        <a class="btn" href="{{ route('manajemen-data.import.npd-historis.template') }}">Unduh Template Import NPD</a>
    </div>
    <form method="POST" action="{{ route('manajemen-data.import.npd-historis.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="fg"><label class="fl" for="file">File Excel</label><input id="file" type="file" name="file" accept=".xlsx,.xls" required></div>
        <div class="sub">Maksimum 10 MB dan 5.000 baris. File sumber tidak disimpan; hanya hash, metadata batch, dan hasil validasi per baris yang dipertahankan.</div>
        <div class="nav" style="margin-top:14px;"><a class="btn" href="{{ route('manajemen-data.index') }}">Kembali</a><button class="btn prim">Upload &amp; Preview</button></div>
    </form>
</div>

<div class="dash-card" style="margin-top:16px;">
    <h3>Petunjuk Kolom</h3>
    <div class="sp-table-wrap"><table class="realisasi"><thead><tr><th>Kolom</th><th>Aturan</th></tr></thead><tbody>
        <tr><td>Tanggal NPD</td><td>Tanggal asli wajib berada pada tahun {{ config('anggaran.tahun_aktif') }}; Tahun Anggaran dan Bulan Realisasi diturunkan otomatis. Tahun lain ditolak tanpa mapping ke master anggaran.</td></tr>
        <tr><td>Nomor NPD</td><td>Dipertahankan persis; identitas juga memakai tahun dan jenis. Tidak dibuatkan nomor baru.</td></tr>
        <tr><td>Jenis NPD</td><td>Barang/Jasa, Perjalanan Dinas, Transport, Narasumber, atau Kontribusi Diklat.</td></tr>
        <tr><td>Sub Kegiatan, Kode Rekening</td><td>Harus exact pada master aktif dan memiliki RAK untuk tahun/bulan tanggal NPD.</td></tr>
        <tr><td>Tagging</td><td>Klasifikasi transaksi NPD; tidak pernah menjadi identitas atau saldo RAK.</td></tr>
        <tr><td>Penerima, Rekening Penerima</td><td>Exact ke Pegawai/Vendor atau disimpan sebagai snapshot manual tanpa membuat master.</td></tr>
        <tr><td>Nominal Bruto</td><td>Wajib positif; menjadi nominal realisasi NPD.</td></tr>
        <tr><td>Uraian</td><td>Snapshot uraian historis; tidak dipakai untuk menebak jenis.</td></tr>
        <tr><td>PPN</td><td>Termasuk bruto; mengikuti Lampiran saat ini sebagai pengurang transfer, bukan pengurang realisasi bruto.</td></tr>
        <tr><td>PPh1/Jenis PPh1, PPh2/Jenis PPh2</td><td>Nilai non-negatif; jenis wajib jika nilainya positif.</td></tr>
        <tr><td>Status NPD</td><td>Opsional. Kosong menjadi Selesai; nilai lain hanya Batal.</td></tr>
    </tbody></table></div>
</div>

<div class="dash-card" style="margin-top:16px;">
    <h3>Riwayat Batch</h3>
    <div class="sp-table-wrap"><table class="realisasi"><thead><tr><th>ID</th><th>File</th><th>User</th><th>Upload</th><th>Eksekusi</th><th>Status</th><th>Total</th><th>Berhasil</th><th>Aksi</th></tr></thead><tbody>
    @forelse($riwayat as $batch)<tr><td>{{ $batch->id }}</td><td>{{ $batch->nama_file }}</td><td>{{ $batch->user?->username ?? '—' }}</td><td>{{ $batch->created_at?->format('d-m-Y H:i') }}</td><td>{{ $batch->executed_at?->format('d-m-Y H:i') ?? '—' }}</td><td>{{ $batch->status }}</td><td>{{ $batch->total_baris }}</td><td>{{ $batch->jumlah_berhasil }}</td><td><a class="btn" href="{{ route('manajemen-data.import.npd-historis.preview', $batch) }}">Detail</a></td></tr>
    @empty<tr><td colspan="9">Belum ada batch.</td></tr>@endforelse
    </tbody></table></div>{{ $riwayat->links() }}
</div>
@endsection
