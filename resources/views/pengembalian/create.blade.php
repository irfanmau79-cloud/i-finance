@extends('layouts.app')

@section('activeNav', 'pengembalian-create')
@section('title', 'Input Data Pengembalian')

@section('content')
<div class="dash-card">
    <h3>Input Data Pengembalian</h3>
    <div class="sub">Pilih dokumen sumber (NPD Selesai atau SPM LS), lalu isi nominal yang dikembalikan per mata anggaran. Disimpan sebagai draft — belum memengaruhi realisasi sampai disetujui.</div>

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

    <form method="POST" action="{{ route('pengembalian.store') }}" enctype="multipart/form-data" id="pengembalian-form">
        @csrf

        <h3 style="margin-top:0;">Cari Dokumen Sumber</h3>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="f-bulan">Bulan</label>
                <select id="f-bulan"><option value="">— Semua Bulan —</option>
                    @foreach ($bulanList as $i => $label)
                        <option value="{{ $i + 1 }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="f-program">Program</label>
                <select id="f-program"><option value="">— Semua Program —</option></select>
            </div>
        </div>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="f-kegiatan">Kegiatan</label>
                <select id="f-kegiatan"><option value="">— Semua Kegiatan —</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="f-sub">Sub Kegiatan</label>
                <select id="f-sub"><option value="">— Semua Sub Kegiatan —</option></select>
            </div>
        </div>

        <div class="fg">
            <label class="fl">Jenis Dokumen</label>
            <div class="seg">
                <label><input type="radio" name="f_tipe" value="npd"><span>NPD (Selesai)</span></label>
                <label><input type="radio" name="f_tipe" value="spm_ls"><span>SPM LS</span></label>
            </div>
        </div>

        <div class="fg">
            <label class="fl" for="f-dokumen">Dokumen Sumber</label>
            <select id="f-dokumen"><option value="">— Semua Dokumen —</option></select>
        </div>
        <input type="hidden" id="dokumen_tipe" name="dokumen_tipe" value="{{ old('dokumen_tipe') }}">
        <input type="hidden" id="dokumen_id" name="dokumen_id" value="{{ old('dokumen_id') }}">

        <div id="breakdown-wrap" style="display:none;">
            <h3>Breakdown Mata Anggaran</h3>
            <div class="sub">Isi nominal pengembalian pada baris yang relevan. Kosongkan baris yang tidak dikembalikan.</div>
            <div id="baris-list"></div>

            <div class="sumbar" style="margin-top:16px;">
                <span>Total Nominal Pengembalian</span>
                <span class="v" id="total-nominal">Rp 0</span>
            </div>
        </div>

        <h3 style="margin-top:22px;">Detail Pengembalian</h3>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_pengembalian">Tanggal Pengembalian</label>
                <input type="date" id="tanggal_pengembalian" name="tanggal_pengembalian" value="{{ old('tanggal_pengembalian') }}">
            </div>
            <div class="fg">
                <label class="fl" for="dokumen_pendukung">Dokumen Pendukung (jpg/png/pdf, maks 5MB)</label>
                <input type="file" id="dokumen_pendukung" name="dokumen_pendukung" accept=".jpg,.jpeg,.png,.pdf">
                <div class="sub" style="margin-top:4px;">Boleh dikosongkan saat draft, tapi wajib diisi sebelum bendahara pengeluaran dapat menyetujui.</div>
            </div>
        </div>
        <div class="fg">
            <label class="fl" for="keterangan">Keterangan (opsional)</label>
            <textarea id="keterangan" name="keterangan" rows="3">{{ old('keterangan') }}</textarea>
        </div>

        <div class="nav">
            <a class="btn" href="{{ route('pengembalian.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan sebagai Draft</button>
        </div>
    </form>
</div>

<script>
(function () {
    const dokumenData = @json($dokumenJs);
    const oldTipe = @json(old('dokumen_tipe'));
    const oldId = @json(old('dokumen_id'));
    const oldBaris = @json(old('baris', []));

    function formatRupiah(n) {
        n = Number(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function uniq(arr) {
        const seen = new Set();
        const out = [];
        arr.forEach(v => { if (v !== '' && v != null && ! seen.has(v)) { seen.add(v); out.push(v); } });
        return out;
    }

    function fillOptions(sel, options, placeholder) {
        const current = sel.value;
        sel.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>'
            + options.map(o => '<option value="' + escapeHtml(o.value) + '">' + escapeHtml(o.label) + '</option>').join('');
        if (options.some(o => o.value === current)) sel.value = current;
    }

    const bulanSel = document.getElementById('f-bulan');
    const programSel = document.getElementById('f-program');
    const kegiatanSel = document.getElementById('f-kegiatan');
    const subSel = document.getElementById('f-sub');
    const tipeRadios = document.querySelectorAll('input[name="f_tipe"]');
    const dokumenSel = document.getElementById('f-dokumen');
    const dokumenTipeField = document.getElementById('dokumen_tipe');
    const dokumenIdField = document.getElementById('dokumen_id');
    const breakdownWrap = document.getElementById('breakdown-wrap');
    const barisList = document.getElementById('baris-list');

    function currentTipe() {
        const checked = document.querySelector('input[name="f_tipe"]:checked');
        return checked ? checked.value : '';
    }

    function docsByBulan() {
        return bulanSel.value === '' ? dokumenData : dokumenData.filter(d => String(d.bulan) === bulanSel.value);
    }

    function docsByProgram() {
        return docsByBulan().filter(d => ! programSel.value || d.program_list.includes(programSel.value));
    }

    function docsByKegiatan() {
        return docsByProgram().filter(d => ! kegiatanSel.value || d.kegiatan_list.includes(kegiatanSel.value));
    }

    function matchingDocs() {
        const tipe = currentTipe();
        return docsByKegiatan()
            .filter(d => ! subSel.value || d.sub_kegiatan_list.includes(subSel.value))
            .filter(d => ! tipe || d.tipe === tipe);
    }

    function onBulanChange() {
        fillOptions(programSel, uniq(docsByBulan().flatMap(d => d.program_list)).map(p => ({ value: p, label: p })), '— Semua Program —');
        onProgramChange();
    }

    function onProgramChange() {
        fillOptions(kegiatanSel, uniq(docsByProgram().flatMap(d => d.kegiatan_list)).map(k => ({ value: k, label: k })), '— Semua Kegiatan —');
        onKegiatanChange();
    }

    function onKegiatanChange() {
        fillOptions(subSel, uniq(docsByKegiatan().flatMap(d => d.sub_kegiatan_list)).map(s => ({ value: s, label: s })), '— Semua Sub Kegiatan —');
        onFilterChange();
    }

    function onFilterChange() {
        const docs = matchingDocs();
        fillOptions(
            dokumenSel,
            docs.map(d => ({ value: d.tipe + ':' + d.id, label: d.label + ' — ' + d.tanggal })),
            docs.length ? '— Pilih Dokumen —' : 'Tidak ada dokumen yang cocok dengan filter ini'
        );
        hideBreakdown();
    }

    function hideBreakdown() {
        dokumenTipeField.value = '';
        dokumenIdField.value = '';
        breakdownWrap.style.display = 'none';
        barisList.innerHTML = '';
        recalcTotal();
    }

    function renderBreakdown(doc) {
        barisList.innerHTML = '';
        doc.breakdown.forEach((b, idx) => {
            const row = document.createElement('div');
            row.className = 'pen';
            row.innerHTML =
                '<h4>' + escapeHtml(b.label) + '</h4>'
                + '<div class="auto">'
                + '<div class="ai"><span class="k">Nominal Asli</span><span class="v">' + formatRupiah(b.nominal_asli) + '</span></div>'
                + '<div class="ai"><span class="k">Sudah Dikembalikan</span><span class="v">' + formatRupiah(b.sudah_dikembalikan) + '</span></div>'
                + '<div class="ai"><span class="k">Sisa Bisa Dikembalikan</span><span class="v" style="color:var(--ok);font-weight:800;">' + formatRupiah(b.sisa) + '</span></div>'
                + '</div>'
                + '<input type="hidden" name="baris[' + idx + '][master_anggaran_id]" value="' + b.master_anggaran_id + '">'
                + '<div class="fg"><label class="fl">Nominal Pengembalian (Rp) — kosongkan bila tidak dikembalikan</label>'
                + '<input type="number" step="0.01" min="0" max="' + b.sisa + '" data-baris-nominal name="baris[' + idx + '][nominal]" value=""></div>';
            barisList.appendChild(row);
        });
        barisList.querySelectorAll('[data-baris-nominal]').forEach(inp => inp.addEventListener('input', recalcTotal));
    }

    function onDokumenChange() {
        const [tipe, id] = (dokumenSel.value || '').split(':');
        const doc = dokumenData.find(d => d.tipe === tipe && String(d.id) === id);
        if (! doc) { hideBreakdown(); return; }

        dokumenTipeField.value = doc.tipe;
        dokumenIdField.value = doc.id;
        renderBreakdown(doc);
        breakdownWrap.style.display = 'block';
        recalcTotal();
    }

    function recalcTotal() {
        let total = 0;
        barisList.querySelectorAll('[data-baris-nominal]').forEach(inp => { total += parseFloat(inp.value) || 0; });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    bulanSel.addEventListener('change', onBulanChange);
    programSel.addEventListener('change', onProgramChange);
    kegiatanSel.addEventListener('change', onKegiatanChange);
    subSel.addEventListener('change', onFilterChange);
    tipeRadios.forEach(r => r.addEventListener('change', onFilterChange));
    dokumenSel.addEventListener('change', onDokumenChange);

    onBulanChange();

    if (oldTipe && oldId) {
        const radio = document.querySelector('input[name="f_tipe"][value="' + oldTipe + '"]');
        if (radio) radio.checked = true;
        onFilterChange();
        dokumenSel.value = oldTipe + ':' + oldId;
        onDokumenChange();
        oldBaris.forEach((b, idx) => {
            const inputs = barisList.querySelectorAll('[data-baris-nominal]');
            if (inputs[idx] && b && b.nominal) inputs[idx].value = b.nominal;
        });
        recalcTotal();
    }
})();
</script>
@endsection
