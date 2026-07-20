@extends('layouts.app')

@section('activeNav', 'npd')
@php($npdEdit = $npd ?? null)
@section('title', $npdEdit ? 'Edit NPD Transport' : 'Buat NPD Transport')

@section('content')
<div class="dash-card">
    <h3>{{ $npdEdit ? 'Edit' : 'Buat' }} NPD Transport</h3>
    <div class="sub">Turunan dari NPD Perjalanan Dinas yang sudah Selesai — anggota disalin otomatis, isi komponen transport saja.</div>

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

    <form method="POST" action="{{ $npdEdit ? route('npd.tr.update', $npdEdit) : route('npd.tr.store') }}" id="npd-tr-form">
        @csrf
        @if ($npdEdit) @method('PUT') @endif

        <div class="fg">
            <label class="fl" for="npd_induk_id">NPD Perjalanan Dinas Induk</label>
            @if ($npdEdit)
                <input type="text" value="{{ $indukList->first()?->nomor_lengkap ?? '#'.$npdEdit->npd_induk_id }}" disabled style="background:#f8fafc;">
                <input type="hidden" name="npd_induk_id" value="{{ $npdEdit->npd_induk_id }}">
                <div class="sub" style="margin-top:4px;">Induk tidak dapat diganti setelah NPD Transport dibuat.</div>
            @else
                <select id="npd_induk_id" name="npd_induk_id">
                    <option value="">— Pilih NPD Perjalanan Dinas —</option>
                    @foreach ($indukList as $induk)
                        <option value="{{ $induk->id }}" @selected((string) old('npd_induk_id') === (string) $induk->id)>
                            {{ $induk->nomor_lengkap ?? '(Draft #'.$induk->id.')' }} — {{ $induk->tanggal_npd->format('d-m-Y') }} — {{ $induk->tim->count() }} anggota
                        </option>
                    @endforeach
                </select>
                @if ($indukList->isEmpty())
                    <div class="sub" style="margin-top:4px;color:var(--err);">Belum ada NPD Perjalanan Dinas berstatus Selesai yang tersedia sebagai induk (atau semuanya sudah punya NPD Transport aktif).</div>
                @endif
            @endif
        </div>

        <div class="fg">
            <label class="fl">Jenis NPD</label>
            <div class="seg">
                @foreach (\App\Models\Npd::JENIS_PANJAR_LIST as $opt)
                    <label>
                        <input type="radio" name="jenis_panjar" value="{{ $opt }}" @checked(old('jenis_panjar', $npdEdit?->jenis_panjar ?? 'Tanpa Panjar') === $opt)>
                        <span>{{ $opt }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_npd">Tanggal NPD</label>
                <input type="date" id="tanggal_npd" name="tanggal_npd" value="{{ old('tanggal_npd', $npdEdit?->tanggal_npd?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="bulan">Bulan</label>
                <select id="bulan" name="bulan">
                    <option value="">-- Pilih Bulan --</option>
                    @foreach ($bulanList as $num => $label)
                        <option value="{{ $num }}" @selected((string) old('bulan', $npdEdit?->bulan) === (string) $num)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="tahun">Tahun</label>
                <input type="number" id="tahun" name="tahun" min="2000" max="2100" value="{{ old('tahun', $npdEdit?->tahun ?? now()->year) }}">
            </div>
        </div>

        <h3 style="margin-top:22px;">Anggota &amp; Komponen Transport</h3>
        <div id="tim-list">
            @if ($npdEdit)
                <?php $indukTimAwal = $indukList->first()?->tim ?? collect(); ?>
                @foreach ($indukTimAwal as $i => $t)
                    @include('npd.tr._tim-row', ['i' => $i, 't' => $t])
                @endforeach
            @else
                <div class="sub">Pilih NPD Perjalanan Dinas induk terlebih dahulu untuk menampilkan anggota.</div>
            @endif
        </div>

        <div class="fg">
            <label class="fl" for="keterangan_lampiran">Keterangan Lampiran (opsional)</label>
            <input type="text" id="keterangan_lampiran" name="keterangan_lampiran" placeholder="Kosongkan untuk memakai keterangan induk"
                   value="{{ old('keterangan_lampiran', $npdEdit?->detail_json['keterangan_lampiran'] ?? '') }}">
        </div>

        <div class="sumbar" style="margin-top:16px;">
            <span>Nominal Total NPD</span>
            <span class="v" id="total-nominal">Rp 0</span>
        </div>

        <div class="nav">
            <a class="btn" href="{{ route('npd.index') }}">Batal</a>
            <button type="submit" class="btn prim">{{ $npdEdit ? 'Simpan Perubahan' : 'Simpan sebagai Draft' }}</button>
        </div>
    </form>
</div>

<?php
    $indukJs = $indukList->map(fn ($i) => [
        'id' => $i->id,
        'tim' => $i->tim->map(fn ($t) => ['nama' => $t->nama, 'jabatan' => $t->jabatan])->values()->all(),
    ]);
?>
<script>
(function () {
    const indukData = @json($indukJs);
    const isEdit = {{ $npdEdit ? 'true' : 'false' }};

    function formatRupiah(n) {
        n = Number(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const timList = document.getElementById('tim-list');
    const tanggalInput = document.getElementById('tanggal_npd');
    const bulanSelect = document.getElementById('bulan');
    const tahunInput = document.getElementById('tahun');

    tanggalInput.addEventListener('change', () => {
        if (! tanggalInput.value) return;
        const d = new Date(tanggalInput.value + 'T00:00:00');
        bulanSelect.value = String(d.getMonth() + 1);
        tahunInput.value = d.getFullYear();
    });

    function timRowHtml(idx, anggota) {
        return '<div class="pen" data-tim-row>'
            + '<h4>' + escapeHtml(anggota.nama) + ' <span style="font-weight:400;color:var(--mut);">' + escapeHtml(anggota.jabatan || '') + '</span></h4>'
            + '<div class="form-grid">'
            + '<div class="fg"><label class="fl">Penerima Dana</label><label style="display:flex;align-items:center;gap:6px;margin-top:8px;"><input type="radio" name="penerima_index" value="' + idx + '"' + (idx === 0 ? ' checked' : '') + '><span>Jadikan penerima transfer</span></label></div>'
            + '<div class="fg"><label class="fl">BBM (liter)</label><input type="number" step="0.01" min="0" data-bbm-liter name="tim[' + idx + '][bbm_liter]" value=""></div>'
            + '<div class="fg"><label class="fl">Tarif BBM (Rp/liter)</label><input type="number" step="0.01" min="0" data-bbm-tarif name="tim[' + idx + '][bbm_tarif]" value=""></div>'
            + '<div class="fg"><label class="fl">Tol (Rp)</label><input type="number" step="0.01" min="0" data-tol name="tim[' + idx + '][tol]" value=""></div>'
            + '<div class="fg"><label class="fl">Tiket (Rp)</label><input type="number" step="0.01" min="0" data-tiket name="tim[' + idx + '][tiket]" value=""></div>'
            + '<div class="fg"><label class="fl">Representatif (Rp)</label><input type="number" step="0.01" min="0" data-representatif name="tim[' + idx + '][representatif]" value=""></div>'
            + '<div class="fg"><label class="fl">Subtotal (otomatis)</label><input type="text" data-subtotal readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '</div>'
            + '</div>';
    }

    function recalcRow(row) {
        const bbmLiter = parseFloat(row.querySelector('[data-bbm-liter]').value) || 0;
        const bbmTarif = parseFloat(row.querySelector('[data-bbm-tarif]').value) || 0;
        const tol = parseFloat(row.querySelector('[data-tol]').value) || 0;
        const tiket = parseFloat(row.querySelector('[data-tiket]').value) || 0;
        const representatif = parseFloat(row.querySelector('[data-representatif]').value) || 0;
        const bbm = (bbmLiter > 0 && bbmTarif > 0) ? Math.round(bbmLiter * bbmTarif) : 0;
        const subtotal = bbm + tol + tiket + representatif;
        row.querySelector('[data-subtotal]').value = formatRupiah(subtotal);
        recalcTotal();
    }

    function recalcTotal() {
        let total = 0;
        timList.querySelectorAll('[data-tim-row]').forEach(row => {
            const bbmLiter = parseFloat(row.querySelector('[data-bbm-liter]').value) || 0;
            const bbmTarif = parseFloat(row.querySelector('[data-bbm-tarif]').value) || 0;
            const tol = parseFloat(row.querySelector('[data-tol]').value) || 0;
            const tiket = parseFloat(row.querySelector('[data-tiket]').value) || 0;
            const representatif = parseFloat(row.querySelector('[data-representatif]').value) || 0;
            const bbm = (bbmLiter > 0 && bbmTarif > 0) ? Math.round(bbmLiter * bbmTarif) : 0;
            total += bbm + tol + tiket + representatif;
        });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    function attachRowEvents(row) {
        ['[data-bbm-liter]', '[data-bbm-tarif]', '[data-tol]', '[data-tiket]', '[data-representatif]'].forEach(sel => {
            row.querySelector(sel).addEventListener('input', () => recalcRow(row));
        });
        recalcRow(row);
    }

    function renderAnggota(indukId) {
        const induk = indukData.find(i => String(i.id) === String(indukId));
        timList.innerHTML = '';
        if (! induk) {
            timList.innerHTML = '<div class="sub">Pilih NPD Perjalanan Dinas induk terlebih dahulu untuk menampilkan anggota.</div>';
            recalcTotal();
            return;
        }
        induk.tim.forEach((anggota, idx) => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = timRowHtml(idx, anggota);
            const row = wrapper.firstElementChild;
            timList.appendChild(row);
            attachRowEvents(row);
        });
    }

    if (! isEdit) {
        const indukSelect = document.getElementById('npd_induk_id');
        indukSelect.addEventListener('change', () => renderAnggota(indukSelect.value));
        if (indukSelect.value) renderAnggota(indukSelect.value);
    } else {
        timList.querySelectorAll('[data-tim-row]').forEach(attachRowEvents);
    }
})();
</script>
@endsection
