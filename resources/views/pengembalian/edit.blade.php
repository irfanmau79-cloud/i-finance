@extends('layouts.app')

@section('activeNav', 'pengembalian-create')
@section('title', 'Edit Draft Pengembalian')

@section('content')
<div class="dash-card">
    <h3>Edit Draft Pengembalian</h3>
    <div class="sub">Dokumen sumber: {{ $pengembalian->dokumen_tipe === \App\Models\Pengembalian::TIPE_NPD ? 'NPD' : 'SPM LS' }} &mdash; {{ $labelDokumen }} (tidak dapat diganti — hapus draft ini dan buat baru bila dokumen sumbernya salah).</div>

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

    <form method="POST" action="{{ route('pengembalian.update', $pengembalian) }}" enctype="multipart/form-data" id="pengembalian-edit-form">
        @csrf
        @method('PUT')
        <input type="hidden" name="dokumen_tipe" value="{{ $pengembalian->dokumen_tipe }}">
        <input type="hidden" name="dokumen_id" value="{{ $pengembalian->dokumen_id }}">

        <h3 style="margin-top:0;">Breakdown Mata Anggaran</h3>
        <div class="sub">Isi nominal pengembalian pada baris yang relevan. Kosongkan baris yang tidak dikembalikan.</div>
        <div id="baris-list">
            @foreach ($breakdown as $idx => $b)
                <div class="pen">
                    <h4>{{ $b['label'] }}</h4>
                    <div class="auto">
                        <div class="ai"><span class="k">Nominal Realisasi</span><span class="v">Rp {{ number_format($b['nominal_asli'], 2, ',', '.') }}</span></div>
                        <div class="ai"><span class="k">Sudah Dikembalikan</span><span class="v">Rp {{ number_format($b['sudah_dikembalikan'], 2, ',', '.') }}</span></div>
                    </div>
                    <input type="hidden" name="baris[{{ $idx }}][master_anggaran_id]" value="{{ $b['master_anggaran_id'] }}">
                    <div class="fg">
                        <label class="fl">Nominal Pengembalian (Rp)</label>
                        <input type="number" step="0.01" min="0" max="{{ $b['sisa'] }}" data-baris-nominal
                               name="baris[{{ $idx }}][nominal]" value="{{ old('baris.'.$idx.'.nominal', $b['nominal_lama']) }}">
                    </div>
                </div>
            @endforeach
        </div>

        <div class="sumbar" style="margin-top:16px;">
            <span>Total Nominal Pengembalian</span>
            <span class="v" id="total-nominal">Rp 0</span>
        </div>

        <h3 style="margin-top:22px;">Detail Pengembalian</h3>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_pengembalian">Tanggal Pengembalian</label>
                <input type="date" id="tanggal_pengembalian" name="tanggal_pengembalian" value="{{ old('tanggal_pengembalian', $pengembalian->tanggal_pengembalian->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="dokumen_pendukung">Dokumen Pendukung (jpg/png/pdf, maks 5MB)</label>
                <input type="file" id="dokumen_pendukung" name="dokumen_pendukung" accept=".jpg,.jpeg,.png,.pdf">
                <div class="sub" style="margin-top:4px;">
                    @if ($pengembalian->dokumen_pendukung)
                        Sudah ada dokumen pendukung tersimpan — kosongkan untuk tetap memakainya, atau unggah file baru untuk menggantinya.
                    @else
                        Boleh dikosongkan saat draft, tapi wajib diisi sebelum bendahara pengeluaran dapat menyetujui.
                    @endif
                </div>
            </div>
        </div>
        <div class="fg">
            <label class="fl" for="keterangan">Keterangan (opsional)</label>
            <textarea id="keterangan" name="keterangan" rows="3">{{ old('keterangan', $pengembalian->keterangan) }}</textarea>
        </div>

        <div class="nav">
            <a class="btn" href="{{ route('pengembalian.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan Perubahan</button>
        </div>
    </form>
</div>

<script>
(function () {
    function formatRupiah(n) {
        n = Number(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const barisList = document.getElementById('baris-list');

    function recalcTotal() {
        let total = 0;
        barisList.querySelectorAll('[data-baris-nominal]').forEach(inp => { total += parseFloat(inp.value) || 0; });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    barisList.querySelectorAll('[data-baris-nominal]').forEach(inp => inp.addEventListener('input', recalcTotal));
    recalcTotal();
})();
</script>
@endsection
