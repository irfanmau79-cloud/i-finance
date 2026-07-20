@extends('layouts.app')

@section('activeNav', 'spm-ls')
@php($spmEdit = $spm ?? null)
@section('title', $spmEdit ? 'Edit SPM LS' : 'Buat SPM LS')

@section('content')
<div class="dash-card">
    <h3>{{ $spmEdit ? 'Edit' : 'Buat' }} SPM LS</h3>
    <div class="sub">Pilih sumber dana, lengkapi nominal dan potongan, lalu simpan.</div>

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

    <form method="POST" action="{{ $spmEdit ? route('spm.ls.update', $spmEdit) : route('spm.ls.store') }}">
        @csrf
        @if ($spmEdit) @method('PUT') @endif

        <div class="fg">
            <label class="fl" for="maf-program">Program</label>
            <select id="maf-program"><option value="">Memuat data…</option></select>
        </div>
        <div class="fg">
            <label class="fl" for="maf-kegiatan">Kegiatan</label>
            <select id="maf-kegiatan" disabled><option value="">Pilih program dulu</option></select>
        </div>
        <div class="fg">
            <label class="fl" for="maf-sub">Sub Kegiatan</label>
            <select id="maf-sub" disabled><option value="">Pilih kegiatan dulu</option></select>
        </div>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="maf-kode">Kode Rekening</label>
                <select id="maf-kode" disabled><option value="">Pilih sub kegiatan dulu</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="maf-tagging">Tagging</label>
                <select id="maf-tagging" disabled><option value="">Pilih kode rekening dulu</option></select>
            </div>
        </div>
        <input type="hidden" name="master_anggaran_id" id="master_anggaran_id" value="{{ old('master_anggaran_id', $spmEdit?->master_anggaran_id) }}">

        <div class="auto" id="ma-detail" style="display:none;">
            <div class="ai"><span class="k">Program</span><span class="v" id="ma-program"></span></div>
            <div class="ai"><span class="k">Kegiatan</span><span class="v" id="ma-kegiatan"></span></div>
            <div class="ai"><span class="k">Sub Kegiatan</span><span class="v" id="ma-sub"></span></div>
            <div class="ai"><span class="k">Kode Rekening</span><span class="v" id="ma-kode"></span></div>
            <div class="ai"><span class="k">Tagging</span><span class="v" id="ma-tagging"></span></div>
            <div class="ai"><span class="k">Pagu Anggaran</span><span class="v" id="ma-pagu"></span></div>
            <div class="ai"><span class="k">Dana Terikat NPD</span><span class="v" id="ma-terikat"></span></div>
            <div class="ai"><span class="k">Realisasi Aktual</span><span class="v" id="ma-realisasi"></span></div>
            <div class="ai"><span class="k">Sisa Tersedia</span><span class="v" id="ma-sisa" style="color:var(--ok);font-weight:800;"></span></div>
        </div>

        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_dokumen">Tanggal SPM</label>
                <input type="date" id="tanggal_dokumen" name="tanggal_dokumen" value="{{ old('tanggal_dokumen', $spmEdit?->tanggal_dokumen?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="nomor_dokumen">Nomor SPM</label>
                <input type="text" id="nomor_dokumen" name="nomor_dokumen" value="{{ old('nomor_dokumen', $spmEdit?->nomor_dokumen) }}">
            </div>
        </div>

        <div class="fg">
            <label class="fl" for="nominal">Nominal (Rp)</label>
            <input type="number" step="0.01" min="0.01" id="nominal" name="nominal" value="{{ old('nominal', $spmEdit?->nominal) }}">
        </div>

        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="ppn">PPN (Rp)</label>
                <input type="number" step="0.01" min="0" id="ppn" name="ppn" value="{{ old('ppn', $spmEdit?->ppn) }}">
            </div>
            <div class="fg"></div>
            <div class="fg">
                <label class="fl" for="jenis_pph1">Jenis PPh 1</label>
                <input type="text" id="jenis_pph1" name="jenis_pph1" placeholder="mis. PPh Pasal 22" value="{{ old('jenis_pph1', $spmEdit?->jenis_pph1) }}">
            </div>
            <div class="fg">
                <label class="fl" for="pph1">Nilai PPh 1 (Rp)</label>
                <input type="number" step="0.01" min="0" id="pph1" name="pph1" value="{{ old('pph1', $spmEdit?->pph1) }}">
            </div>
            <div class="fg">
                <label class="fl" for="jenis_pph2">Jenis PPh 2 (opsional)</label>
                <input type="text" id="jenis_pph2" name="jenis_pph2" placeholder="mis. PPh Pasal 23" value="{{ old('jenis_pph2', $spmEdit?->jenis_pph2) }}">
            </div>
            <div class="fg">
                <label class="fl" for="pph2">Nilai PPh 2 (Rp)</label>
                <input type="number" step="0.01" min="0" id="pph2" name="pph2" value="{{ old('pph2', $spmEdit?->pph2) }}">
            </div>
        </div>

        <div class="fg">
            <label class="fl" for="penerima">Penerima (opsional)</label>
            <input type="text" id="penerima" name="penerima" value="{{ old('penerima', $spmEdit?->penerima) }}">
        </div>
        <div class="fg">
            <label class="fl" for="uraian">Uraian</label>
            <input type="text" id="uraian" name="uraian" value="{{ old('uraian', $spmEdit?->uraian) }}">
        </div>

        <div class="nav">
            <a class="btn" href="{{ route('spm.ls.index') }}">Batal</a>
            <button type="submit" class="btn prim">{{ $spmEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
    </form>
</div>

<?php
    $masterAnggaranJs = $masterAnggaran->map(function ($m) use ($spmEdit) {
        $sisaTambahan = $spmEdit && $spmEdit->master_anggaran_id === $m->id ? (float) $spmEdit->nominal : 0;

        return [
            'id' => $m->id,
            'program' => $m->program,
            'kegiatan' => $m->kegiatan,
            'sub_kegiatan' => $m->sub_kegiatan,
            'kode_rekening' => $m->kode_rekening,
            'uraian_rekening' => $m->uraian_rekening,
            'tagging_id' => $m->tagging_id,
            'tagging' => $m->tagging->nama ?? 'Tanpa Tagging',
            'pagu' => (float) $m->pagu,
            'dana_terikat' => $m->danaTerikatNpd(),
            'realisasi_aktual' => $m->realisasiAktual(),
            'sisa' => $m->sisaTersedia() + $sisaTambahan,
        ];
    });
?>
<script>
(function () {
    const masterAnggaranData = @json($masterAnggaranJs);

    function formatRupiah(n) {
        n = Number(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const NONE_TAG = '__none__';

    function uniq(arr) {
        const seen = new Set();
        const out = [];
        arr.forEach(v => { if (v !== '' && v != null && ! seen.has(v)) { seen.add(v); out.push(v); } });
        return out;
    }

    function taggingValue(m) {
        return m.tagging_id === null || m.tagging_id === undefined ? NONE_TAG : String(m.tagging_id);
    }

    const maSel = {
        program: document.getElementById('maf-program'),
        kegiatan: document.getElementById('maf-kegiatan'),
        sub: document.getElementById('maf-sub'),
        kode: document.getElementById('maf-kode'),
        tagging: document.getElementById('maf-tagging'),
    };
    const maIdField = document.getElementById('master_anggaran_id');
    const maDetail = document.getElementById('ma-detail');

    const MSEL = { program: '', kegiatan: '', sub: '', kode: '', tagging: '' };

    function fillOptions(sel, options, placeholder) {
        sel.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>'
            + options.map(o => '<option value="' + escapeHtml(o.value) + '">' + escapeHtml(o.label) + '</option>').join('');
        sel.disabled = options.length === 0;
    }

    function hideMaDetail() {
        maIdField.value = '';
        maDetail.style.display = 'none';
    }

    function loadPrograms() {
        const progs = uniq(masterAnggaranData.map(m => m.program));
        fillOptions(maSel.program, progs.map(p => ({ value: p, label: p })), '— Pilih Program —');
    }

    function onProgramChange() {
        MSEL.program = maSel.program.value;
        MSEL.kegiatan = MSEL.sub = MSEL.kode = MSEL.tagging = '';
        const k = uniq(masterAnggaranData.filter(m => m.program === MSEL.program).map(m => m.kegiatan));
        fillOptions(maSel.kegiatan, k.map(v => ({ value: v, label: v })), k.length ? '— Pilih Kegiatan —' : 'Pilih program dulu');
        fillOptions(maSel.sub, [], 'Pilih kegiatan dulu');
        fillOptions(maSel.kode, [], 'Pilih sub kegiatan dulu');
        fillOptions(maSel.tagging, [], 'Pilih kode rekening dulu');
        hideMaDetail();
    }

    function onKegiatanChange() {
        MSEL.kegiatan = maSel.kegiatan.value;
        MSEL.sub = MSEL.kode = MSEL.tagging = '';
        const s = uniq(masterAnggaranData.filter(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan).map(m => m.sub_kegiatan));
        fillOptions(maSel.sub, s.map(v => ({ value: v, label: v })), s.length ? '— Pilih Sub Kegiatan —' : 'Pilih kegiatan dulu');
        fillOptions(maSel.kode, [], 'Pilih sub kegiatan dulu');
        fillOptions(maSel.tagging, [], 'Pilih kode rekening dulu');
        hideMaDetail();
    }

    function kodeLabel(m) {
        return m.uraian_rekening ? (m.kode_rekening + ' — ' + m.uraian_rekening) : m.kode_rekening;
    }

    function onSubChange() {
        MSEL.sub = maSel.sub.value;
        MSEL.kode = MSEL.tagging = '';
        const rows = masterAnggaranData.filter(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan && m.sub_kegiatan === MSEL.sub);
        const seen = new Set();
        const opts = [];
        rows.forEach(m => {
            if (! seen.has(m.kode_rekening)) { seen.add(m.kode_rekening); opts.push({ value: m.kode_rekening, label: kodeLabel(m) }); }
        });
        fillOptions(maSel.kode, opts, opts.length ? '— Pilih Kode Rekening —' : 'Pilih sub kegiatan dulu');
        fillOptions(maSel.tagging, [], 'Pilih kode rekening dulu');
        hideMaDetail();
    }

    function onKodeChange() {
        MSEL.kode = maSel.kode.value;
        MSEL.tagging = '';
        const rows = masterAnggaranData.filter(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan && m.sub_kegiatan === MSEL.sub && m.kode_rekening === MSEL.kode);
        const seen = new Set();
        const opts = [];
        rows.forEach(m => {
            const val = taggingValue(m);
            if (! seen.has(val)) { seen.add(val); opts.push({ value: val, label: m.tagging }); }
        });
        fillOptions(maSel.tagging, opts, opts.length ? '— Pilih Tagging —' : 'Pilih kode rekening dulu');
        hideMaDetail();
    }

    function onTaggingChange() {
        MSEL.tagging = maSel.tagging.value;
        const row = masterAnggaranData.find(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan && m.sub_kegiatan === MSEL.sub && m.kode_rekening === MSEL.kode && taggingValue(m) === MSEL.tagging);
        if (row) {
            selectMasterAnggaran(row);
        } else {
            hideMaDetail();
        }
    }

    function selectMasterAnggaran(m) {
        maIdField.value = m.id;
        document.getElementById('ma-program').textContent = m.program;
        document.getElementById('ma-kegiatan').textContent = m.kegiatan;
        document.getElementById('ma-sub').textContent = m.sub_kegiatan;
        document.getElementById('ma-kode').textContent = kodeLabel(m);
        document.getElementById('ma-tagging').textContent = m.tagging;
        document.getElementById('ma-pagu').textContent = formatRupiah(m.pagu);
        document.getElementById('ma-terikat').textContent = formatRupiah(m.dana_terikat);
        document.getElementById('ma-realisasi').textContent = formatRupiah(m.realisasi_aktual);
        document.getElementById('ma-sisa').textContent = formatRupiah(m.sisa);
        maDetail.style.display = 'block';
    }

    maSel.program.addEventListener('change', onProgramChange);
    maSel.kegiatan.addEventListener('change', onKegiatanChange);
    maSel.sub.addEventListener('change', onSubChange);
    maSel.kode.addEventListener('change', onKodeChange);
    maSel.tagging.addEventListener('change', onTaggingChange);

    loadPrograms();

    if (maIdField.value) {
        const found = masterAnggaranData.find(m => String(m.id) === String(maIdField.value));
        if (found) {
            maSel.program.value = found.program;
            onProgramChange();
            maSel.kegiatan.value = found.kegiatan;
            onKegiatanChange();
            maSel.sub.value = found.sub_kegiatan;
            onSubChange();
            maSel.kode.value = found.kode_rekening;
            onKodeChange();
            maSel.tagging.value = taggingValue(found);
            selectMasterAnggaran(found);
        }
    }
})();
</script>
@endsection
