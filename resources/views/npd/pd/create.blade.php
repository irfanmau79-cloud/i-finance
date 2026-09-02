@extends('layouts.app')

@section('activeNav', 'npd')
@php($npdEdit = $npd ?? null)
@section('title', $npdEdit ? 'Edit Nota Pencairan Dana Perjalanan Dinas' : 'Buat Nota Pencairan Dana Perjalanan Dinas')

@section('content')
<div class="dash-card">
    <h3>{{ $npdEdit ? 'Edit' : 'Buat' }} Nota Pencairan Dana Perjalanan Dinas</h3>
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
            @include('npd._anggaran-kosong')

            <div class="fg">
                <label class="fl" for="maf-program">Program</label>
                <select id="maf-program" data-cari><option value="">Memuat data…</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="maf-kegiatan">Kegiatan</label>
                <select id="maf-kegiatan" data-cari disabled><option value="">Pilih program terlebih dahulu</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="maf-sub">Sub Kegiatan</label>
                <select id="maf-sub" data-cari disabled><option value="">Pilih kegiatan terlebih dahulu</option></select>
            </div>
            <div class="form-grid">
                <div class="fg">
                    <label class="fl" for="maf-kode">Kode Rekening</label>
                    <select id="maf-kode" data-cari disabled><option value="">Pilih sub kegiatan terlebih dahulu</option></select>
                </div>
                <div class="fg">
                    <label class="fl" for="maf-tagging">Tagging</label>
                    <select id="maf-tagging" data-cari disabled><option value="">Pilih kode rekening terlebih dahulu</option></select>
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

            @include('npd._sisa-manual')

            <div class="err-box" id="err-1"></div>
            <div class="nav">
                <a class="btn" href="{{ route('npd.index') }}">Batal</a>
                <button type="button" class="btn prim" id="wiz-n1">Lanjut &rarr;</button>
            </div>
        </div>

        <div class="pane" data-pane="2">
            <h3 style="margin-top:0;">Surat Perintah</h3>
            @php($spTerpilih = $suratPerintahList->firstWhere('id', (int) old('surat_perintah_id', $npdEdit?->surat_perintah_id)))
            <div class="fg">
                <label class="fl" for="sp-taut-inp">Pilih Data Surat Perintah <span style="color:var(--err);">*</span></label>
                <div class="kombo" id="sp-taut-wrap" data-semua="&mdash; belum dipilih &mdash;">
                    <input type="text" class="kb-inp" id="sp-taut-inp" autocomplete="off" role="combobox" aria-expanded="false"
                           placeholder="Ketik Nomor SP, unit kerja, atau lokasi&hellip;"
                           value="{{ $spTerpilih ? $spTerpilih->nomor_sp.' — '.$spTerpilih->unit_kerja.($spTerpilih->lokasi ? ' ('.$spTerpilih->lokasi.')' : '') : '' }}">
                    <svg class="kb-chev" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    <div class="kb-drop" id="sp-taut-drop" role="listbox"></div>
                </div>
                <p class="mini">Nota Pencairan Dana Perjalanan Dinas selalu berangkat dari Surat Perintah. Memilih SP akan mengisi keterangan di bawah dan memunculkan anggota timnya.</p>
                @if ($npdEdit && ! $npdEdit->surat_perintah_id)
                    {{-- NPD lama dibuat sebelum aturan ini berlaku, jadi belum
                         punya tautan SP. Diberi tahu di sini supaya pengguna
                         tidak menabrak galat validasi tanpa tahu sebabnya. --}}
                    <div class="sumbar" style="margin-top:8px;background:var(--warn-bg);color:var(--warn);">
                        <span>Nota Pencairan Dana ini dibuat sebelum Surat Perintah diwajibkan, sehingga belum tertaut. Silakan pilih Surat Perintah yang sesuai sebelum menyimpan.</span>
                    </div>
                @endif
                @if ($suratPerintahList->isEmpty())
                    <p class="mini" style="color:var(--err);">Belum ada Surat Perintah yang siap dipakai. Pastikan SP sudah dicatat di Data Surat Perintah, berjenis Uang Harian/Akomodasi, dan penanda Sumber NPD-nya menyala.</p>
                @endif
            </div>
            <input type="hidden" name="surat_perintah_id" id="surat_perintah_id" value="{{ old('surat_perintah_id', $npdEdit?->surat_perintah_id) }}">

            <div class="form-grid">
                <div class="fg">
                    <label class="fl" for="nomor_sp">Nomor SP</label>
                    <input type="text" id="nomor_sp" value="{{ $spTerpilih?->nomor_sp ?? ($npdEdit?->detail_json['nomor_sp'] ?? '') }}" readonly placeholder="Terisi dari Surat Perintah">
                </div>
                <div class="fg">
                    <label class="fl" for="tanggal_sp">Tanggal SP</label>
                    <input type="date" id="tanggal_sp" value="{{ $spTerpilih?->tanggal_sp?->format('Y-m-d') ?? ($npdEdit?->detail_json['tanggal_sp'] ?? '') }}" readonly>
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
                {{-- Tahun tidak lagi dipilih: NPD selalu dibuat pada tahun
                     anggaran berjalan. Tetap dikirim agar aturan validasi dan
                     penomoran tidak perlu diubah. --}}
                <input type="hidden" name="tahun" id="tahun" value="{{ old('tahun', $npdEdit?->tahun ?? config('anggaran.tahun_aktif')) }}">
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
        'program' => $m->program_lengkap,
        'kegiatan' => $m->kegiatan_lengkap,
        'sub_kegiatan' => $m->sub_kegiatan_lengkap,
        'kode_rekening' => $m->rekening_lengkap,
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
        'unit_kerja' => $sp->unit_kerja,
        'jumlah_anggota' => $sp->anggota->count(),
        // Identitas diambil dari SNAPSHOT anggota SP, bukan join ke master
        // Pegawai: anggota mode manual memang tidak punya baris master, dan
        // SP lama harus tetap membawa identitas sebagaimana saat ditandatangani.
        'anggota' => $sp->anggota->map(fn ($item) => [
            'pegawai_id' => $item->pegawai_id,
            'nama' => (string) $item->nama,
            // Jabatan struktural yang tercetak di Daftar Pembayaran; kalau
            // kosong, jabatan dalam tim dipakai sebagai gantinya.
            'jabatan' => (string) ($item->jabatan ?: $item->jabatan_sp),
            'nip' => (string) $item->nip,
            'rekening' => (string) $item->rekening,
        ])->values()->all(),
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
        // Tahun sengaja TIDAK ikut diubah - selalu tahun anggaran berjalan.
    });

    // ==================== Pilih Surat Perintah ====================
    // Wajib: NPD Perjalanan Dinas selalu berangkat dari SP. Memilih SP mengisi
    // keterangannya (baca-saja) dan menyalin anggota tim SP ke daftar anggota,
    // yang setelah itu masih bisa disunting per orang.
    (function () {
        const wrap = document.getElementById('sp-taut-wrap');
        const inp = document.getElementById('sp-taut-inp');
        const drop = document.getElementById('sp-taut-drop');
        const nilai = document.getElementById('surat_perintah_id');
        const escHtml = t => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };

        let label = inp.value;
        let sorot = -1;

        const cocok = () => {
            const q = inp.value.trim().toLowerCase();

            return spData.filter(sp => !q || q === label.toLowerCase()
                || [sp.nomor_sp, sp.unit_kerja, sp.tujuan, sp.uraian_sp].join(' ').toLowerCase().includes(q));
        };

        function gambar() {
            const daftar = cocok();
            drop.innerHTML = daftar.length
                ? daftar.map((sp, i) => '<div class="kb-item' + (String(sp.id) === nilai.value ? ' terpilih' : '')
                    + (i === sorot ? ' sorot' : '') + '" role="option" data-id="' + sp.id + '">'
                    + '<strong>' + escHtml(sp.nomor_sp) + '</strong><br><span style="color:var(--mut);font-size:11.5px;">'
                    + escHtml(sp.unit_kerja || '') + (sp.tujuan ? ' &middot; ' + escHtml(sp.tujuan) : '')
                    + (sp.jumlah_anggota ? ' &middot; ' + sp.jumlah_anggota + ' anggota' : '') + '</span></div>').join('')
                : '<div class="kb-kosong">Tidak ada Surat Perintah yang cocok</div>';
        }

        function buka() { sorot = -1; gambar(); wrap.classList.add('buka'); inp.setAttribute('aria-expanded', 'true'); }
        function tutup() { wrap.classList.remove('buka'); inp.setAttribute('aria-expanded', 'false'); inp.value = label; }

        function pilih(id) {
            const sp = spData.find(s => String(s.id) === String(id));
            if (!sp) return;

            nilai.value = sp.id;
            label = sp.nomor_sp + ' — ' + (sp.unit_kerja || '') + (sp.tujuan ? ' (' + sp.tujuan + ')' : '');
            inp.value = label;
            tutup();

            document.getElementById('nomor_sp').value = sp.nomor_sp;
            document.getElementById('tanggal_sp').value = sp.tanggal_sp;
            document.getElementById('uraian_sp').value = sp.uraian_sp || '';
            document.getElementById('tujuan').value = sp.tujuan || '';
            importSpAnggota(sp.anggota || []);
        }

        inp.addEventListener('focus', buka);
        inp.addEventListener('click', buka);
        inp.addEventListener('input', function () { sorot = -1; gambar(); wrap.classList.add('buka'); });
        inp.addEventListener('blur', () => setTimeout(tutup, 130));
        inp.addEventListener('keydown', function (e) {
            const daftar = cocok();
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!wrap.classList.contains('buka')) buka();
                sorot = Math.min(Math.max(sorot + (e.key === 'ArrowDown' ? 1 : -1), 0), daftar.length - 1);
                gambar();
                const el = drop.querySelector('.kb-item.sorot');
                if (el) el.scrollIntoView({block: 'nearest'});
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (daftar[sorot]) pilih(daftar[sorot].id);
            } else if (e.key === 'Escape') { tutup(); inp.blur(); }
        });
        drop.addEventListener('mousedown', function (e) {
            const item = e.target.closest('.kb-item[data-id]');
            if (!item) return;
            e.preventDefault();
            pilih(item.dataset.id);
        });
    })();

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
            + '<div style="font-size:11px;color:var(--tegas);font-weight:600;margin-bottom:4px;">Paket Tujuan</div>'
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
            + '<div class="mini" style="text-align:right;">Subtotal paket: <span data-p-sub style="font-weight:600;color:var(--tegas);">Rp 0</span></div>'
            + '</div>';
    }

    function timRowHtml(idx) {
        return '<div class="anggota" data-tim-row data-idx="' + idx + '">'
            + '<button type="button" class="del" data-tim-remove title="Hapus anggota">&times;</button>'
            + '<div class="tim-member-head">'
            + '<span class="tim-member-number" data-tim-number>' + (idx + 1) + '</span>'
            + '<div><span class="tim-member-eyebrow">Anggota Tim</span>'
            + '<strong data-tim-name-label>Belum dipilih</strong></div>'
            + '</div>'
            + '<div class="tim-copy" data-copy-panel hidden>'
            + '<div class="tim-copy-icon" aria-hidden="true">&#8646;</div>'
            + '<div class="tim-copy-field"><label class="fl">Data perjalanan sama dengan</label>'
            + '<select data-copy-source><option value="">Pilih anggota yang sudah diinput</option></select>'
            + '<span class="mini" data-copy-feedback>Pilih sumber untuk menyalin paket tujuan dan biaya perjalanan.</span></div>'
            + '<button type="button" class="btn tim-copy-btn" data-copy-apply disabled>Salin Data Perjalanan</button>'
            + '</div>'
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
            row.querySelector('[data-tim-number]').textContent = i + 1;
            row.querySelector('[data-tim-name-label]').textContent =
                row.querySelector('[data-name-input]').value.trim() || 'Belum dipilih';
            row.querySelector('[data-tim-remove]').disabled = rows.length <= 1;
        });
        refreshCopySources();
    }

    function refreshCopySources() {
        const rows = Array.from(timList.querySelectorAll('[data-tim-row]'));
        rows.forEach((row, rowIndex) => {
            const panel = row.querySelector('[data-copy-panel]');
            const select = row.querySelector('[data-copy-source]');
            const applyButton = row.querySelector('[data-copy-apply]');
            const selected = select.value;
            const precedingRows = rows.slice(0, rowIndex);

            panel.hidden = precedingRows.length === 0;
            select.innerHTML = '<option value="">Pilih anggota yang sudah diinput</option>'
                + precedingRows.map((sourceRow, sourceIndex) => {
                    const sourceName = sourceRow.querySelector('[data-name-input]').value.trim();
                    const label = 'Anggota #' + (sourceIndex + 1) + (sourceName ? ' — ' + sourceName : '');
                    return '<option value="' + sourceRow.dataset.idx + '">' + escapeHtml(label) + '</option>';
                }).join('');

            if (precedingRows.some(sourceRow => sourceRow.dataset.idx === selected)) select.value = selected;
            applyButton.disabled = ! select.value;
        });
    }

    function perjalananData(timRow) {
        return {
            paket: Array.from(timRow.querySelectorAll('[data-paket-row]')).map(paketRow => ({
                cluster: paketRow.querySelector('[data-p-cluster]').value,
                wilayah: paketRow.querySelector('[data-p-wilayah]')?.value || '',
                lama_hari: paketRow.querySelector('[data-p-hari]').value,
                tarif_uh: paketRow.querySelector('[data-p-tarifuh]').value,
                malam: paketRow.querySelector('[data-p-malam]').value,
                tarif_akom: paketRow.querySelector('[data-p-tarifak]').value,
            })),
            bbm_liter: timRow.querySelector('[data-bbm-liter]').value,
            bbm_tarif: timRow.querySelector('[data-bbm-tarif]').value,
            tol: timRow.querySelector('[data-tol]').value,
            tiket: timRow.querySelector('[data-tiket]').value,
            representatif: timRow.querySelector('[data-representatif]').value,
        };
    }

    function copyPerjalanan(sourceRow, targetRow) {
        const data = perjalananData(sourceRow);
        targetRow.querySelector('[data-paket-list]').innerHTML = '';

        (data.paket.length ? data.paket : [{}]).forEach(item => {
            addPaket(targetRow);
            isiPaket(targetRow.querySelector('[data-paket-row]:last-child'), item, targetRow);
        });

        targetRow.querySelector('[data-bbm-liter]').value = data.bbm_liter;
        targetRow.querySelector('[data-bbm-tarif]').value = data.bbm_tarif;
        targetRow.querySelector('[data-tol]').value = data.tol;
        targetRow.querySelector('[data-tiket]').value = data.tiket;
        targetRow.querySelector('[data-representatif]').value = data.representatif;
        recalcTim(targetRow);
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
                        refreshCopySources();
                    });
                    nameDrop.appendChild(el);
                });
            }
            nameDrop.classList.add('show');
        }

        nameInput.addEventListener('input', () => {
            pegawaiIdField.value = '';
            renderNameDrop(nameInput.value);
            refreshCopySources();
        });
        nameInput.addEventListener('focus', () => renderNameDrop(nameInput.value));

        const copySource = timRow.querySelector('[data-copy-source]');
        const copyButton = timRow.querySelector('[data-copy-apply]');
        const copyFeedback = timRow.querySelector('[data-copy-feedback]');
        copySource.addEventListener('change', () => {
            copyButton.disabled = ! copySource.value;
            copyFeedback.textContent = copySource.value
                ? 'Siap menyalin paket tujuan dan seluruh biaya perjalanan.'
                : 'Pilih sumber untuk menyalin paket tujuan dan biaya perjalanan.';
        });
        copyButton.addEventListener('click', () => {
            const rows = Array.from(timList.querySelectorAll('[data-tim-row]'));
            const targetPosition = rows.indexOf(timRow);
            const sourceRow = rows.slice(0, targetPosition).find(row => row.dataset.idx === copySource.value);
            if (! sourceRow) {
                refreshCopySources();
                copyFeedback.textContent = 'Anggota sumber sudah tidak tersedia.';
                return;
            }

            copyPerjalanan(sourceRow, timRow);
            const sourceName = sourceRow.querySelector('[data-name-input]').value.trim();
            const sourceNumber = sourceRow.querySelector('[data-tim-number]').textContent;
            copyFeedback.textContent = 'Data perjalanan berhasil disalin dari '
                + (sourceName || 'Anggota #' + sourceNumber) + '.';
        });

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
        return row;
    }

    function importSpAnggota(anggota) {
        if (! anggota.length) return;

        timList.innerHTML = '';
        const importedRows = anggota.map(item => tambahTim({
            pegawai_id: item.pegawai_id,
            nama: item.nama,
            jabatan: item.jabatan,
            nip: item.nip,
            rekening: item.rekening,
            bbm_liter: '',
            bbm_tarif: '',
            tol: '',
            tiket: '',
            representatif: '',
            paket: [],
        }));
        importedRows[0]?.querySelector('[data-penerima-radio]')?.click();
        renumber();
        recalcTotal();
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
