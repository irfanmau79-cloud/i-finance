@extends('layouts.app')

@section('activeNav', 'npd')
@php($npdEdit = $npd ?? null)
@section('title', $npdEdit ? 'Edit NPD Perjalanan Dinas' : 'Buat NPD Perjalanan Dinas')

@section('content')
<div class="dash-card">
    <h3>{{ $npdEdit ? 'Edit' : 'Buat' }} NPD Perjalanan Dinas</h3>
    <div class="sub">Pilih sumber dana, lengkapi data SP &amp; perjalanan, lalu tambahkan anggota tim.</div>

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

    @php($wizStartStep = $errors->any() ? ($errors->has('master_anggaran_id') ? 1 : 2) : 1)
    <div class="steps" id="wiz-steps">
        <div class="step active" data-step="1"><span class="n">1</span><span class="lb">Pilih Anggaran</span></div>
        <div class="step" data-step="2"><span class="n">2</span><span class="lb">Detail SP &amp; Anggota Tim</span></div>
        <div class="step" data-step="3"><span class="n">3</span><span class="lb">Review</span></div>
    </div>

    <form method="POST" action="{{ $npdEdit ? route('npd.pd.update', $npdEdit) : route('npd.pd.store') }}" id="npd-pd-form" data-start-step="{{ $wizStartStep }}">
        @csrf
        @if ($npdEdit) @method('PUT') @endif

        <div class="pane show" data-pane="1">
            <div class="fg">
                <label class="fl" for="maf-program">Program</label>
                <select id="maf-program"><option value="">Memuat data…</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="maf-kegiatan">Kegiatan</label>
                <select id="maf-kegiatan" disabled><option value="">Pilih program terlebih dahulu</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="maf-sub">Sub Kegiatan</label>
                <select id="maf-sub" disabled><option value="">Pilih kegiatan terlebih dahulu</option></select>
            </div>
            <div class="form-grid">
                <div class="fg">
                    <label class="fl" for="maf-kode">Kode Rekening</label>
                    <select id="maf-kode" disabled><option value="">Pilih sub kegiatan terlebih dahulu</option></select>
                </div>
                <div class="fg">
                    <label class="fl" for="maf-tagging">Tagging</label>
                    <select id="maf-tagging" disabled><option value="">Pilih kode rekening terlebih dahulu</option></select>
                </div>
            </div>
            <input type="hidden" name="master_anggaran_id" id="master_anggaran_id" value="{{ old('master_anggaran_id', $npdEdit?->master_anggaran_id) }}">

            <div class="auto" id="ma-detail" style="display:none;">
                <div class="ai"><span class="k">Program</span><span class="v" id="ma-program"></span></div>
                <div class="ai"><span class="k">Kegiatan</span><span class="v" id="ma-kegiatan"></span></div>
                <div class="ai"><span class="k">Sub Kegiatan</span><span class="v" id="ma-sub"></span></div>
                <div class="ai"><span class="k">Kode Rekening</span><span class="v" id="ma-kode"></span></div>
                <div class="ai"><span class="k">Tagging</span><span class="v" id="ma-tagging"></span></div>
                <div class="ai"><span class="k">Pagu Anggaran</span><span class="v" id="ma-pagu"></span></div>
                <div class="ai"><span class="k">Sisa Anggaran</span><span class="v" id="ma-sisa" style="color:var(--ok);font-weight:800;"></span></div>
                <div class="ai"><span class="k">KEU</span><span class="v" id="ma-keu"></span></div>
            </div>

            <div class="err-box" id="err-1"></div>
            <div class="nav">
                <a class="btn" href="{{ route('npd.index') }}">Batal</a>
                <button type="button" class="btn prim" id="wiz-n1">Lanjut &rarr;</button>
            </div>
        </div>

        <div class="pane" data-pane="2">
            <h3 style="margin-top:0;">Surat Perintah</h3>
            <div class="fg">
                <label class="fl" for="sp-taut">Tautkan ke Data SP (opsional)</label>
                <select id="sp-taut">
                    <option value="">— Input manual, tidak ditautkan —</option>
                    @foreach ($suratPerintahList as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->nomor_sp }} — {{ $sp->unit_kerja }} ({{ $sp->tanggal_sp->format('d-m-Y') }})</option>
                    @endforeach
                </select>
                <p class="mini">Kalau ditautkan, Nomor SP/Tanggal SP/Uraian/Tujuan otomatis terisi (tetap bisa diedit) dan status NPD ini akan ikut termonitor di Monitoring SP.</p>
            </div>
            <input type="hidden" name="surat_perintah_id" id="surat_perintah_id" value="{{ old('surat_perintah_id', $npdEdit?->surat_perintah_id) }}">

            <div class="form-grid">
                <div class="fg">
                    <label class="fl" for="nomor_sp">Nomor SP</label>
                    <input type="text" id="nomor_sp" name="nomor_sp" value="{{ old('nomor_sp', $npdEdit?->detail_json['nomor_sp'] ?? null) }}" placeholder="mis. 294/KPG.03.01.01/Sekre">
                </div>
                <div class="fg">
                    <label class="fl" for="tanggal_sp">Tanggal SP</label>
                    <input type="date" id="tanggal_sp" name="tanggal_sp" value="{{ old('tanggal_sp', $npdEdit?->detail_json['tanggal_sp'] ?? null) }}">
                </div>
            </div>
            <div class="fg">
                <label class="fl" for="uraian_sp">Uraian / Maksud Perjalanan</label>
                <textarea id="uraian_sp" name="uraian_sp" placeholder="mis. Mengikuti kegiatan Rapat Persiapan ...">{{ old('uraian_sp', $npdEdit?->detail_json['uraian_sp'] ?? null) }}</textarea>
            </div>
            <div class="form-grid">
                <div class="fg">
                    <label class="fl" for="berangkat_dari">Berangkat Dari</label>
                    <input type="text" id="berangkat_dari" name="berangkat_dari" value="{{ old('berangkat_dari', $npdEdit?->detail_json['berangkat_dari'] ?? \App\Models\ClusterUh::ASAL_PERJALANAN) }}">
                </div>
                <div class="fg">
                    <label class="fl" for="tujuan">Tujuan (umum)</label>
                    <input type="text" id="tujuan" name="tujuan" value="{{ old('tujuan', $npdEdit?->detail_json['tujuan'] ?? null) }}" placeholder="mis. Jakarta">
                </div>
            </div>
            <div class="form-grid">
                <div class="fg">
                    <label class="fl" for="tanggal_berangkat">Tanggal Berangkat</label>
                    <input type="date" id="tanggal_berangkat" name="tanggal_berangkat" value="{{ old('tanggal_berangkat', $npdEdit?->detail_json['tanggal_berangkat'] ?? null) }}">
                </div>
                <div class="fg">
                    <label class="fl" for="tanggal_pulang">Tanggal Pulang</label>
                    <input type="date" id="tanggal_pulang" name="tanggal_pulang" value="{{ old('tanggal_pulang', $npdEdit?->detail_json['tanggal_pulang'] ?? null) }}">
                </div>
            </div>

            <div class="fg">
                <label class="fl">Jenis NPD</label>
                <div class="seg">
                    @foreach (\App\Models\Npd::JENIS_PANJAR_LIST as $opt)
                        <label>
                            <input type="radio" name="jenis_panjar" value="{{ $opt }}" @checked(old('jenis_panjar', $npdEdit?->jenis_panjar ?? 'Panjar') === $opt)>
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

            <h3 style="margin-top:22px;">Anggota Tim</h3>
            <div id="tim-list"></div>
            <button type="button" class="add" id="tim-add">+ Tambah Anggota</button>

            <div class="fg" style="margin-top:16px;">
                <label class="fl" for="keterangan_lampiran">Keterangan Lampiran (opsional)</label>
                <textarea id="keterangan_lampiran" name="keterangan_lampiran" placeholder="Kosongkan untuk memakai keterangan otomatis (rentang tanggal berangkat s.d pulang).">{{ old('keterangan_lampiran', $npdEdit?->detail_json['keterangan_lampiran'] ?? null) }}</textarea>
            </div>

            <div class="badge-tot" style="margin-top:16px;">
                <span>Total Seluruh Tim</span>
                <span class="v" id="total-nominal">Rp 0</span>
            </div>

            <div class="err-box" id="err-2"></div>
            <div class="nav">
                <button type="button" class="btn" id="wiz-b2">&larr; Sebelumnya</button>
                <button type="button" class="btn prim" id="wiz-n2">Lanjut &rarr;</button>
            </div>
        </div>

        <div class="pane" data-pane="3">
            <div class="rev" id="rev-box"></div>
            <div class="err-box" id="err-3"></div>
            <div class="nav">
                <button type="button" class="btn" id="wiz-b3">&larr; Sebelumnya</button>
                <button type="submit" class="btn prim">{{ $npdEdit ? 'Simpan Perubahan' : 'Simpan sebagai Draft' }}</button>
            </div>
        </div>
    </form>
</div>

<?php
    $masterAnggaranJs = $masterAnggaran->map(fn ($m) => [
        'id' => $m->id,
        'program' => $m->program,
        'kegiatan' => $m->kegiatan,
        'sub_kegiatan' => $m->sub_kegiatan,
        'kode_rekening' => $m->kode_rekening,
        'kode_rekening_bersih' => $m->kode_rekening_bersih,
        'tagging_id' => $m->tagging_id,
        'tagging' => $m->tagging->nama ?? 'Tanpa Tagging',
        'pagu' => (float) $m->pagu,
        'sisa' => $m->sisaTersedia() + ($npdEdit && $npdEdit->master_anggaran_id === $m->id ? (float) $npdEdit->nominal : 0),
        'keu' => $m->tentukanKeu(),
    ]);

    $pegawaiJs = $pegawai->map(fn ($p) => [
        'id' => $p->id,
        'nama' => $p->nama,
        'sub' => trim($p->jabatan.' — '.$p->bidang, ' —'),
        'jabatan' => $p->jabatan,
        'nip' => $p->nip,
        'rekening' => $p->rekening,
    ]);

    $clusterJs = $clusterList->map(fn ($c) => [
        'kode' => $c->kode,
        'tarif' => (float) $c->tarif,
        'jarak' => $c->jarak,
        'wilayah' => $c->wilayah->pluck('nama_wilayah')->all(),
    ]);

    $spJs = $suratPerintahList->map(fn ($sp) => [
        'id' => $sp->id,
        'nomor_sp' => $sp->nomor_sp,
        'tanggal_sp' => $sp->tanggal_sp->format('Y-m-d'),
        'uraian_sp' => $sp->keterangan,
        'tujuan' => $sp->lokasi,
    ]);

    $timTersimpan = $npdEdit?->tim->map(fn ($tim) => [
        'pegawai_id' => $tim->pegawai_id,
        'nama' => $tim->nama,
        'jabatan' => $tim->jabatan,
        'nip' => $tim->nip,
        'rekening' => $tim->rekening,
        'bbm_liter' => $tim->bbm_liter,
        'bbm_tarif' => $tim->bbm_tarif,
        'tol' => $tim->tol,
        'tiket' => $tim->tiket,
        'representatif' => $tim->representatif,
        'paket' => $tim->paket->map(fn ($paket) => [
            'cluster' => $paket->cluster,
            'wilayah' => $paket->wilayah,
            'lama_hari' => $paket->lama_hari,
            'tarif_uh' => $paket->tarif_uh,
            'malam' => $paket->malam,
            'tarif_akom' => $paket->tarif_akom,
        ])->all(),
    ])->all() ?? [];
    $timAwal = old('tim', $timTersimpan);
    $penerimaAwal = (int) old('penerima_index', $npdEdit?->tim->search(fn ($tim) => $tim->is_penerima) ?? 0);
?>
<script>
(function () {
    const masterAnggaranData = @json($masterAnggaranJs);
    const pegawaiData = @json($pegawaiJs);
    const clusterData = @json($clusterJs);
    const spData = @json($spJs);
    const initialTim = @json($timAwal);
    const initialPenerima = @json($penerimaAwal);

    function formatRupiah(n) {
        n = Number(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // ==================== Sumber Dana: cascade (identik modul BJ) ====================
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
        fillOptions(maSel.kegiatan, k.map(v => ({ value: v, label: v })), k.length ? '— Pilih Kegiatan —' : 'Pilih program terlebih dahulu');
        fillOptions(maSel.sub, [], 'Pilih kegiatan terlebih dahulu');
        fillOptions(maSel.kode, [], 'Pilih sub kegiatan terlebih dahulu');
        fillOptions(maSel.tagging, [], 'Pilih kode rekening terlebih dahulu');
        hideMaDetail();
    }

    function onKegiatanChange() {
        MSEL.kegiatan = maSel.kegiatan.value;
        MSEL.sub = MSEL.kode = MSEL.tagging = '';
        const s = uniq(masterAnggaranData.filter(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan).map(m => m.sub_kegiatan));
        fillOptions(maSel.sub, s.map(v => ({ value: v, label: v })), s.length ? '— Pilih Sub Kegiatan —' : 'Pilih kegiatan terlebih dahulu');
        fillOptions(maSel.kode, [], 'Pilih sub kegiatan terlebih dahulu');
        fillOptions(maSel.tagging, [], 'Pilih kode rekening terlebih dahulu');
        hideMaDetail();
    }

    function kodeLabel(m) {
        return m.kode_rekening;
    }

    function onSubChange() {
        MSEL.sub = maSel.sub.value;
        MSEL.kode = MSEL.tagging = '';
        const rows = masterAnggaranData.filter(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan && m.sub_kegiatan === MSEL.sub);
        const seen = new Set();
        const opts = [];
        rows.forEach(m => {
            if (! seen.has(m.kode_rekening_bersih)) { seen.add(m.kode_rekening_bersih); opts.push({ value: m.kode_rekening_bersih, label: kodeLabel(m) }); }
        });
        fillOptions(maSel.kode, opts, opts.length ? '— Pilih Kode Rekening —' : 'Pilih sub kegiatan terlebih dahulu');
        fillOptions(maSel.tagging, [], 'Pilih kode rekening terlebih dahulu');
        hideMaDetail();
    }

    function onKodeChange() {
        MSEL.kode = maSel.kode.value;
        MSEL.tagging = '';
        const rows = masterAnggaranData.filter(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan && m.sub_kegiatan === MSEL.sub && m.kode_rekening_bersih === MSEL.kode);
        const seen = new Set();
        const opts = [];
        rows.forEach(m => {
            const val = taggingValue(m);
            if (! seen.has(val)) { seen.add(val); opts.push({ value: val, label: m.tagging }); }
        });
        fillOptions(maSel.tagging, opts, opts.length ? '— Pilih Tagging —' : 'Pilih kode rekening terlebih dahulu');
        hideMaDetail();
    }

    function onTaggingChange() {
        MSEL.tagging = maSel.tagging.value;
        const row = masterAnggaranData.find(m => m.program === MSEL.program && m.kegiatan === MSEL.kegiatan && m.sub_kegiatan === MSEL.sub && m.kode_rekening_bersih === MSEL.kode && taggingValue(m) === MSEL.tagging);
        if (row) selectMasterAnggaran(row); else hideMaDetail();
    }

    function selectMasterAnggaran(m) {
        maIdField.value = m.id;
        document.getElementById('ma-program').textContent = m.program;
        document.getElementById('ma-kegiatan').textContent = m.kegiatan;
        document.getElementById('ma-sub').textContent = m.sub_kegiatan;
        document.getElementById('ma-kode').textContent = kodeLabel(m);
        document.getElementById('ma-tagging').textContent = m.tagging;
        document.getElementById('ma-pagu').textContent = formatRupiah(m.pagu);
        document.getElementById('ma-sisa').textContent = formatRupiah(m.sisa);
        document.getElementById('ma-keu').textContent = m.keu ? ('KEU ' + m.keu) : 'Tidak dapat ditentukan';
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
            maSel.program.value = found.program; onProgramChange();
            maSel.kegiatan.value = found.kegiatan; onKegiatanChange();
            maSel.sub.value = found.sub_kegiatan; onSubChange();
            maSel.kode.value = found.kode_rekening_bersih; onKodeChange();
            maSel.tagging.value = taggingValue(found);
            selectMasterAnggaran(found);
        }
    }

    // ==================== Tanggal NPD -> default bulan/tahun ====================
    const tanggalInput = document.getElementById('tanggal_npd');
    const bulanSelect = document.getElementById('bulan');
    const tahunInput = document.getElementById('tahun');
    tanggalInput.addEventListener('change', () => {
        if (! tanggalInput.value) return;
        const d = new Date(tanggalInput.value + 'T00:00:00');
        bulanSelect.value = String(d.getMonth() + 1);
        tahunInput.value = d.getFullYear();
    });

    // ==================== Tautkan SP ====================
    document.getElementById('sp-taut').addEventListener('change', function () {
        const sp = spData.find(s => String(s.id) === this.value);
        document.getElementById('surat_perintah_id').value = sp ? sp.id : '';
        if (sp) {
            document.getElementById('nomor_sp').value = sp.nomor_sp;
            document.getElementById('tanggal_sp').value = sp.tanggal_sp;
            document.getElementById('uraian_sp').value = sp.uraian_sp || '';
            document.getElementById('tujuan').value = sp.tujuan || '';
        }
    });
    document.getElementById('sp-taut').value = document.getElementById('surat_perintah_id').value;

    // ==================== Anggota Tim ====================
    const timList = document.getElementById('tim-list');
    let timIndex = 0;
    let paketSeq = 0;

    function clusterOptionsHtml(selected) {
        return '<option value="">— cluster —</option>'
            + clusterData.filter(c => c.kode !== 'LP').map(c => '<option value="' + c.kode + '"' + (c.kode === selected ? ' selected' : '') + '>' + c.kode + ' (' + c.jarak + ')</option>').join('')
            + '<option value="LP"' + (selected === 'LP' ? ' selected' : '') + '>Luar Provinsi</option>';
    }

    function paketRowHtml(timIdx, pid) {
        return '<div class="paket" data-paket-row data-pid="' + pid + '" style="border:1px solid var(--line);border-radius:8px;padding:10px;margin-top:8px;position:relative;">'
            + '<button type="button" class="del" data-paket-remove style="top:6px;right:6px;width:22px;height:22px;font-size:13px;" title="Hapus paket">&times;</button>'
            + '<div style="font-size:11px;color:var(--navy);font-weight:600;margin-bottom:4px;">Paket Tujuan</div>'
            + '<div class="row3">'
            + '<div><label class="fl">Cluster</label><select data-p-cluster name="tim[' + timIdx + '][paket][' + pid + '][cluster]">' + clusterOptionsHtml('') + '</select></div>'
            + '<div><label class="fl">Kab/Kota Tujuan</label><div data-p-wilayah-wrap><select data-p-wilayah name="tim[' + timIdx + '][paket][' + pid + '][wilayah]" disabled><option value="">—</option></select></div></div>'
            + '<div><label class="fl">Tarif/hari (Rp)</label><input type="number" step="0.01" min="0" data-p-tarifuh name="tim[' + timIdx + '][paket][' + pid + '][tarif_uh]" placeholder="otomatis"></div>'
            + '</div>'
            + '<div class="row3">'
            + '<div><label class="fl">Lama (hari)</label><input type="number" min="0" data-p-hari name="tim[' + timIdx + '][paket][' + pid + '][lama_hari]" value="0"></div>'
            + '<div><label class="fl">Malam</label><input type="number" min="0" data-p-malam name="tim[' + timIdx + '][paket][' + pid + '][malam]" value="0"></div>'
            + '<div><label class="fl">Tarif/malam (Rp)</label><input type="number" step="0.01" min="0" data-p-tarifak name="tim[' + timIdx + '][paket][' + pid + '][tarif_akom]" value="0"></div>'
            + '</div>'
            + '<div class="mini" style="text-align:right;">Subtotal paket: <span data-p-sub style="font-weight:600;color:var(--navy);">Rp 0</span></div>'
            + '</div>';
    }

    function timRowHtml(idx) {
        return '<div class="anggota" data-tim-row data-idx="' + idx + '">'
            + '<button type="button" class="del" data-tim-remove title="Hapus anggota">&times;</button>'
            + '<h4>Anggota <span data-tim-number>#' + (idx + 1) + '</span></h4>'
            + '<div class="form-grid">'
            + '<div class="fg span2">'
            + '<label class="fl">Nama</label>'
            + '<div class="nsearch" data-name-search>'
            + '<svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
            + '<input type="text" class="ns-inp" data-name-input autocomplete="off" placeholder="Cari pegawai, atau ketik nama manual..." name="tim[' + idx + '][nama]" value="">'
            + '<div class="ns-drop" data-name-drop></div>'
            + '</div>'
            + '<input type="hidden" data-pegawai-id name="tim[' + idx + '][pegawai_id]" value="">'
            + '</div>'
            + '<div class="fg"><label class="fl">Jabatan</label><input type="text" data-jabatan name="tim[' + idx + '][jabatan]" value=""></div>'
            + '<div class="fg"><label class="fl">NIP</label><input type="text" data-nip name="tim[' + idx + '][nip]" value=""></div>'
            + '<div class="fg"><label class="fl">No. Rekening</label><input type="text" data-rekening name="tim[' + idx + '][rekening]" value=""></div>'
            + '<div class="fg"><label class="fl" style="display:flex;align-items:center;gap:7px;cursor:pointer;"><input type="radio" name="penerima_index" data-penerima-radio value="' + idx + '" style="width:auto;"> Penerima Dana</label></div>'
            + '</div>'
            + '<div class="sub">Paket Tujuan (Uang Harian &amp; Akomodasi)</div>'
            + '<div data-paket-list></div>'
            + '<button type="button" class="add" style="padding:7px;font-size:12px;margin-top:8px;" data-paket-add>+ Tambah Tujuan</button>'
            + '<div class="sub">Transport</div>'
            + '<div class="row">'
            + '<div><label class="fl">Jumlah Liter BBM</label><input type="number" step="0.01" min="0" data-bbm-liter name="tim[' + idx + '][bbm_liter]" value=""></div>'
            + '<div><label class="fl">Tarif BBM/liter (Rp)</label><input type="number" step="0.01" min="0" data-bbm-tarif name="tim[' + idx + '][bbm_tarif]" value=""></div>'
            + '</div>'
            + '<div class="badge-tot" style="margin:6px 0 0;"><span>Total BBM</span><span class="v" data-bbm-total>Rp 0</span></div>'
            + '<div class="row" style="margin-top:8px;">'
            + '<div><label class="fl">Tol (Rp)</label><input type="number" step="0.01" min="0" data-tol name="tim[' + idx + '][tol]" value=""></div>'
            + '<div><label class="fl">Tiket (Rp)</label><input type="number" step="0.01" min="0" data-tiket name="tim[' + idx + '][tiket]" value=""></div>'
            + '</div>'
            + '<div class="fg">'
            + '<label class="fl">Uang Representatif (Rp)</label>'
            + '<input type="number" step="0.01" min="0" data-representatif name="tim[' + idx + '][representatif]" placeholder="kosongkan jika tidak ada" value="">'
            + '</div>'
            + '<div class="badge-tot"><span>Jumlah diterima anggota</span><span class="v" data-jumlah-anggota>Rp 0</span></div>'
            + '</div>';
    }

    function renumber() {
        const rows = timList.querySelectorAll('[data-tim-row]');
        rows.forEach((row, i) => {
            row.querySelector('[data-tim-number]').textContent = '#' + (i + 1);
            row.querySelector('[data-tim-remove]').disabled = rows.length <= 1;
        });
    }

    function recalcPaket(paketRow) {
        const hari = parseFloat(paketRow.querySelector('[data-p-hari]').value) || 0;
        const tuh = parseFloat(paketRow.querySelector('[data-p-tarifuh]').value) || 0;
        const malam = parseFloat(paketRow.querySelector('[data-p-malam]').value) || 0;
        const tak = parseFloat(paketRow.querySelector('[data-p-tarifak]').value) || 0;
        const sub = (hari * tuh) + (malam * tak);
        paketRow.querySelector('[data-p-sub]').textContent = formatRupiah(sub);
        return sub;
    }

    function recalcTim(timRow) {
        let jmlHarianAkom = 0;
        timRow.querySelectorAll('[data-paket-row]').forEach(pk => { jmlHarianAkom += recalcPaket(pk); });

        const liter = parseFloat(timRow.querySelector('[data-bbm-liter]').value) || 0;
        const tarifLiter = parseFloat(timRow.querySelector('[data-bbm-tarif]').value) || 0;
        const bbm = (liter > 0 && tarifLiter > 0) ? Math.round(liter * tarifLiter) : 0;
        timRow.querySelector('[data-bbm-total]').textContent = formatRupiah(bbm);

        const tol = parseFloat(timRow.querySelector('[data-tol]').value) || 0;
        const tiket = parseFloat(timRow.querySelector('[data-tiket]').value) || 0;
        const repr = parseFloat(timRow.querySelector('[data-representatif]').value) || 0;
        const jumlah = jmlHarianAkom + bbm + tol + tiket + repr;
        timRow.querySelector('[data-jumlah-anggota]').textContent = formatRupiah(jumlah);
        recalcTotal();
    }

    function recalcTotal() {
        let total = 0;
        timList.querySelectorAll('[data-tim-row]').forEach(row => {
            const text = row.querySelector('[data-jumlah-anggota]').textContent;
            total += parseFloat(text.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
        });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    function attachPaketEvents(paketRow, timRow) {
        paketRow.querySelectorAll('[data-p-hari],[data-p-tarifuh],[data-p-malam],[data-p-tarifak]').forEach(el => {
            el.addEventListener('input', () => recalcTim(timRow));
        });
        paketRow.querySelector('[data-p-cluster]').addEventListener('change', function () {
            const wilWrap = paketRow.querySelector('[data-p-wilayah-wrap]');
            const fieldName = wilWrap.querySelector('[data-p-wilayah]').getAttribute('name');
            const tarifInput = paketRow.querySelector('[data-p-tarifuh]');
            const c = clusterData.find(x => x.kode === this.value);
            if (this.value === 'LP') {
                // LP: wilayah/kota diisi manual (bukan dropdown) — pakai input teks biasa
                // supaya nilainya tetap terkirim (select disabled tidak ikut ter-submit).
                wilWrap.innerHTML = '<input type="text" data-p-wilayah name="' + fieldName + '" placeholder="Ketik kota/kabupaten tujuan...">';
                tarifInput.value = '';
                tarifInput.readOnly = false;
            } else if (c) {
                wilWrap.innerHTML = '<select data-p-wilayah name="' + fieldName + '">'
                    + '<option value="">— pilih kota —</option>' + c.wilayah.map(w => '<option>' + escapeHtml(w) + '</option>').join('') + '</select>';
                tarifInput.value = c.tarif;
                tarifInput.readOnly = true;
            } else {
                wilWrap.innerHTML = '<select data-p-wilayah name="' + fieldName + '" disabled><option value="">—</option></select>';
                tarifInput.value = '';
                tarifInput.readOnly = false;
            }
            recalcTim(timRow);
        });
        paketRow.querySelector('[data-paket-remove]').addEventListener('click', () => {
            const list = timRow.querySelector('[data-paket-list]');
            if (list.querySelectorAll('[data-paket-row]').length <= 1) return;
            paketRow.remove();
            recalcTim(timRow);
        });
    }

    function addPaket(timRow) {
        paketSeq++;
        const idx = timRow.getAttribute('data-idx');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = paketRowHtml(idx, paketSeq);
        const paketRow = wrapper.firstElementChild;
        timRow.querySelector('[data-paket-list]').appendChild(paketRow);
        attachPaketEvents(paketRow, timRow);
        recalcTim(timRow);
    }

    function attachTimEvents(timRow) {
        const nameInput = timRow.querySelector('[data-name-input]');
        const nameDrop = timRow.querySelector('[data-name-drop]');
        const pegawaiIdField = timRow.querySelector('[data-pegawai-id]');
        const jabatanInput = timRow.querySelector('[data-jabatan]');
        const nipInput = timRow.querySelector('[data-nip]');
        const rekeningInput = timRow.querySelector('[data-rekening]');

        function renderNameDrop(query) {
            const q = query.trim().toLowerCase();
            let items = q ? pegawaiData.filter(n => n.nama.toLowerCase().includes(q)) : pegawaiData;
            items = items.slice(0, 30);
            nameDrop.innerHTML = '';
            if (q && ! items.length) {
                nameDrop.innerHTML = '<div class="ns-empty">Tidak ditemukan di master — akan disimpan sebagai nama manual.</div>';
            } else {
                items.forEach(n => {
                    const el = document.createElement('div');
                    el.className = 'ns-item';
                    el.innerHTML = '<div><div>' + escapeHtml(n.nama) + '</div><div class="sub">' + escapeHtml(n.sub) + '</div></div>';
                    el.addEventListener('click', () => {
                        nameInput.value = n.nama;
                        pegawaiIdField.value = n.id;
                        jabatanInput.value = n.jabatan || '';
                        nipInput.value = n.nip || '';
                        rekeningInput.value = n.rekening || '';
                        nameDrop.classList.remove('show');
                    });
                    nameDrop.appendChild(el);
                });
            }
            nameDrop.classList.add('show');
        }

        nameInput.addEventListener('input', () => { pegawaiIdField.value = ''; renderNameDrop(nameInput.value); });
        nameInput.addEventListener('focus', () => renderNameDrop(nameInput.value));

        timRow.querySelectorAll('[data-bbm-liter],[data-bbm-tarif],[data-tol],[data-tiket],[data-representatif]').forEach(el => {
            el.addEventListener('input', () => recalcTim(timRow));
        });

        timRow.querySelector('[data-paket-add]').addEventListener('click', () => addPaket(timRow));

        timRow.querySelector('[data-tim-remove]').addEventListener('click', () => {
            if (timList.querySelectorAll('[data-tim-row]').length <= 1) return;
            timRow.remove();
            renumber();
            recalcTotal();
        });

        addPaket(timRow); // minimal 1 paket
    }

    function isiPaket(paketRow, data, timRow) {
        const cluster = paketRow.querySelector('[data-p-cluster]');
        cluster.value = data.cluster || '';
        cluster.dispatchEvent(new Event('change'));
        const wilayah = paketRow.querySelector('[data-p-wilayah]');
        if (wilayah) wilayah.value = data.wilayah || '';
        paketRow.querySelector('[data-p-hari]').value = data.lama_hari ?? 0;
        paketRow.querySelector('[data-p-tarifuh]').value = data.tarif_uh ?? 0;
        paketRow.querySelector('[data-p-malam]').value = data.malam ?? 0;
        paketRow.querySelector('[data-p-tarifak]').value = data.tarif_akom ?? 0;
        recalcTim(timRow);
    }

    function tambahTim(data = null) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = timRowHtml(timIndex);
        const row = wrapper.firstElementChild;
        timList.appendChild(row);
        attachTimEvents(row);

        if (data) {
            row.querySelector('[data-name-input]').value = data.nama || '';
            row.querySelector('[data-pegawai-id]').value = data.pegawai_id || '';
            row.querySelector('[data-jabatan]').value = data.jabatan || '';
            row.querySelector('[data-nip]').value = data.nip || '';
            row.querySelector('[data-rekening]').value = data.rekening || '';
            row.querySelector('[data-bbm-liter]').value = data.bbm_liter ?? 0;
            row.querySelector('[data-bbm-tarif]').value = data.bbm_tarif ?? 0;
            row.querySelector('[data-tol]').value = data.tol ?? 0;
            row.querySelector('[data-tiket]').value = data.tiket ?? 0;
            row.querySelector('[data-representatif]').value = data.representatif ?? 0;

            const paket = data.paket || [];
            paket.forEach((item, index) => {
                if (index > 0) addPaket(row);
                isiPaket(row.querySelectorAll('[data-paket-row]')[index], item, row);
            });
        }

        if (timIndex === initialPenerima || (! initialTim.length && timIndex === 0)) {
            row.querySelector('[data-penerima-radio]').checked = true;
        }
        timIndex++;
        renumber();
        recalcTim(row);
    }

    document.addEventListener('click', (e) => {
        document.querySelectorAll('[data-name-drop].show').forEach(drop => {
            if (! drop.closest('[data-name-search]').contains(e.target)) drop.classList.remove('show');
        });
    });

    document.getElementById('tim-add').addEventListener('click', () => tambahTim());

    if (initialTim.length) initialTim.forEach(data => tambahTim(data));
    else tambahTim();

    // ---- Wizard: stepper + review ----
    const wizForm = document.getElementById('npd-pd-form');
    const wizPanes = wizForm.querySelectorAll('[data-pane]');
    const wizSteps = document.querySelectorAll('#wiz-steps .step');

    function goStep(n) {
        wizPanes.forEach(p => p.classList.toggle('show', Number(p.dataset.pane) === n));
        wizSteps.forEach(s => {
            const sn = Number(s.dataset.step);
            s.classList.toggle('active', sn === n);
            s.classList.toggle('done', sn < n);
        });
        if (n === 3) renderReview();
        document.querySelector('.dash-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showStepErr(id, msg) {
        const el = document.getElementById(id);
        el.textContent = msg;
        el.style.display = msg ? 'block' : 'none';
    }

    function liRow(k, v) {
        return '<div class="li"><span class="k">' + escapeHtml(k) + '</span><span class="v">' + v + '</span></div>';
    }

    function renderReview() {
        const m = masterAnggaranData.find(x => String(x.id) === String(maIdField.value));
        const jenis = (wizForm.querySelector('input[name="jenis_panjar"]:checked') || {}).value || '—';

        let html = '<div class="grp"><div class="gt">Anggaran</div>';
        html += m
            ? liRow('Program', m.program) + liRow('Kegiatan', m.kegiatan) + liRow('Sub Kegiatan', m.sub_kegiatan)
                + liRow('Kode Rekening', kodeLabel(m)) + liRow('Tagging', m.tagging)
                + liRow('Pagu Anggaran', formatRupiah(m.pagu)) + liRow('Sisa Anggaran', formatRupiah(m.sisa))
            : liRow('Sumber dana', 'Belum dipilih');
        html += '</div>';

        html += '<div class="grp"><div class="gt">Surat Perintah</div>'
            + liRow('Nomor SP', document.getElementById('nomor_sp').value || '—')
            + liRow('Tanggal SP', document.getElementById('tanggal_sp').value || '—')
            + liRow('Tujuan', document.getElementById('tujuan').value || '—')
            + liRow('Tanggal Berangkat', document.getElementById('tanggal_berangkat').value || '—')
            + liRow('Tanggal Pulang', document.getElementById('tanggal_pulang').value || '—')
            + '</div>';

        html += '<div class="grp"><div class="gt">Detail NPD</div>'
            + liRow('Jenis', jenis) + liRow('Tanggal NPD', tanggalInput.value || '—')
            + liRow('Nominal Total', document.getElementById('total-nominal').textContent) + '</div>';

        const timRows = timList.querySelectorAll('[data-tim-row]');
        html += '<div class="grp"><div class="gt">Anggota Tim (' + timRows.length + ')</div>';
        timRows.forEach(row => {
            const nama = row.querySelector('[data-name-input]').value || '(belum diisi)';
            const jumlah = row.querySelector('[data-jumlah-anggota]').textContent;
            html += liRow(nama, jumlah);
        });
        html += '</div>';

        document.getElementById('rev-box').innerHTML = html;
    }

    document.getElementById('wiz-n1').addEventListener('click', () => {
        if (! maIdField.value) { showStepErr('err-1', 'Pilih sumber dana (Program s.d Tagging) terlebih dahulu.'); return; }
        showStepErr('err-1', '');
        goStep(2);
    });
    document.getElementById('wiz-b2').addEventListener('click', () => goStep(1));
    document.getElementById('wiz-n2').addEventListener('click', () => {
        if (! document.getElementById('nomor_sp').value.trim() || ! tanggalInput.value) {
            showStepErr('err-2', 'Lengkapi Nomor SP dan Tanggal NPD.');
            return;
        }
        const namaKosong = Array.from(timList.querySelectorAll('[data-name-input]')).some(inp => ! inp.value.trim());
        if (namaKosong) { showStepErr('err-2', 'Lengkapi nama semua anggota tim.'); return; }
        showStepErr('err-2', '');
        goStep(3);
    });
    document.getElementById('wiz-b3').addEventListener('click', () => goStep(2));

    // Jaring pengaman: field wajib yang lolos dari pengecekan manual di atas
    // (mis. field lain yang required) tapi masih kosong akan membuat submit
    // browser gagal DIAM-DIAM karena field itu ada di pane tersembunyi
    // (display:none), dan karena validasi native gagal, event 'submit' bahkan
    // tidak pernah terpicu (listener di form tidak berguna) - makanya
    // pengecekan dipasang di 'click' tombol submit, sebelum validasi native
    // sempat jalan: kalau tidak valid, batalkan submit, pindah ke pane yang
    // memuat field bermasalah supaya terlihat, baru minta browser tampilkan
    // pesan validasinya di sana.
    wizForm.querySelector('button[type="submit"]').addEventListener('click', (e) => {
        if (wizForm.checkValidity()) return;
        e.preventDefault();
        const invalid = wizForm.querySelector(':invalid');
        if (!invalid) return;
        const pane = invalid.closest('[data-pane]');
        if (pane) goStep(Number(pane.dataset.pane));
        setTimeout(() => wizForm.reportValidity(), 50);
    });

    const wizStartStep = Number(wizForm.dataset.startStep || 1);
    if (wizStartStep > 1) goStep(wizStartStep);
})();
</script>
@endsection
