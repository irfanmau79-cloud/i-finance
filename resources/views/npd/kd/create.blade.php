@extends('layouts.app')

@section('activeNav', 'npd')
@php($npdEdit = $npd ?? null)
@section('title', $npdEdit ? 'Edit NPD Kontribusi Diklat' : 'Buat NPD Kontribusi Diklat')

@section('content')
<div class="dash-card">
    <h3>{{ $npdEdit ? 'Edit' : 'Buat' }} NPD Kontribusi Diklat</h3>
    <div class="sub">Pilih mode, lengkapi data pelatihan, lalu tambahkan peserta.</div>

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

    <form method="POST" action="{{ $npdEdit ? route('npd.kd.update', $npdEdit) : route('npd.kd.store') }}" id="npd-kd-form">
        @csrf
        @if ($npdEdit) @method('PUT') @endif

        @php($modeVal = old('mode', $npdEdit?->mode_kd ?? 'kontribusi'))
        <div class="fg">
            <label class="fl">Mode NPD</label>
            <div class="seg">
                <label>
                    <input type="radio" name="mode" value="kontribusi" id="mode-kontribusi" @checked($modeVal === 'kontribusi')>
                    <span>Kontribusi (sebelum pelatihan)</span>
                </label>
                <label>
                    <input type="radio" name="mode" value="perjalanan" id="mode-perjalanan" @checked($modeVal === 'perjalanan')>
                    <span>Perjalanan Dinas (setelah pelatihan)</span>
                </label>
            </div>
        </div>

        <div class="fg" id="referensi-wrap" style="display:none;">
            <label class="fl" for="npd_referensi_id">Referensi NPD Kontribusi (opsional)</label>
            <select id="npd_referensi_id" name="npd_referensi_id">
                <option value="">— Input manual, tanpa referensi —</option>
                @foreach ($referensiList as $r)
                    <option value="{{ $r->id }}" @selected((string) old('npd_referensi_id', $npdEdit?->npd_referensi_id) === (string) $r->id)>
                        {{ $r->nomor_lengkap ?? '(Draft #'.$r->id.')' }} — {{ $r->detail_json['nama_pelatihan'] ?? '' }}
                    </option>
                @endforeach
            </select>
            <div class="sub" style="margin-top:4px;">Memilih referensi akan menyalin nama/pangkat/NIP/rekening peserta ke bawah — bisa diedit.</div>
        </div>

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

        @php($detail = $detailAwal ?? ($npdEdit?->detail_json ?? []))
        <div class="fg">
            <label class="fl" for="nama_pelatihan">Nama Pelatihan</label>
            <input type="text" id="nama_pelatihan" name="nama_pelatihan" placeholder="mis. Diklat Penjenjangan Auditor Ahli Muda"
                   value="{{ old('nama_pelatihan', $detail['nama_pelatihan'] ?? '') }}">
        </div>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_mulai">Tanggal Mulai Pelatihan</label>
                <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ old('tanggal_mulai', $detail['tanggal_mulai'] ?? '') }}">
            </div>
            <div class="fg">
                <label class="fl" for="tanggal_selesai">Tanggal Selesai Pelatihan</label>
                <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ old('tanggal_selesai', $detail['tanggal_selesai'] ?? '') }}">
            </div>
        </div>

        <h3 style="margin-top:22px;">Daftar Peserta</h3>
        <div id="peserta-list">
            <?php
                $oldPeserta = old('peserta', $pesertaAwal ?? [[]]);
            ?>
            @foreach ($oldPeserta as $i => $row)
                @include('npd.kd._peserta-row', ['i' => $i, 'p' => $row])
            @endforeach
        </div>
        <button type="button" class="add" id="peserta-add">+ Tambah Peserta</button>

        <h3 style="margin-top:22px;">Rincian Lampiran (opsional)</h3>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="ppn">PPN (Rp)</label>
                <input type="number" step="0.01" min="0" id="ppn" name="ppn" value="{{ old('ppn', $detail['ppn'] ?? '') }}">
            </div>
            <div class="fg">
                <label class="fl" for="pph_jenis">Jenis PPh</label>
                <input type="text" id="pph_jenis" name="pph_jenis" placeholder="mis. PPh Pasal 21" value="{{ old('pph_jenis', $detail['pph_jenis'] ?? '') }}">
            </div>
            <div class="fg">
                <label class="fl" for="pph_nilai">Nilai PPh (Rp)</label>
                <input type="number" step="0.01" min="0" id="pph_nilai" name="pph_nilai" value="{{ old('pph_nilai', $detail['pph_nilai'] ?? '') }}">
            </div>
            <div class="fg">
                <label class="fl" for="biaya_lain">Biaya KU/RTGS (Rp)</label>
                <input type="number" step="0.01" min="0" id="biaya_lain" name="biaya_lain" value="{{ old('biaya_lain', $detail['biaya_lain'] ?? '') }}">
            </div>
        </div>
        <div class="fg">
            <label class="fl" for="keterangan_lampiran">Keterangan Lampiran (opsional)</label>
            <input type="text" id="keterangan_lampiran" name="keterangan_lampiran" placeholder="Kosongkan untuk memakai keterangan otomatis"
                   value="{{ old('keterangan_lampiran', $detail['keterangan_lampiran'] ?? '') }}">
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
    $masterAnggaranJs = $masterAnggaran->map(fn ($m) => [
        'id' => $m->id,
        'program' => $m->program,
        'kegiatan' => $m->kegiatan,
        'sub_kegiatan' => $m->sub_kegiatan,
        'kode_rekening' => $m->kode_rekening,
        'uraian_rekening' => $m->uraian_rekening,
        'tagging_id' => $m->tagging_id,
        'tagging' => $m->tagging->nama ?? 'Tanpa Tagging',
        'pagu' => (float) $m->pagu,
        'sisa' => $m->sisaTersedia() + ($npdEdit && $npdEdit->master_anggaran_id === $m->id ? (float) $npdEdit->nominal : 0),
        'keu' => $m->tentukanKeu(),
    ]);

    $namaJs = $pegawai->map(fn ($p) => [
        'id' => $p->id,
        'nama' => $p->nama,
        'sub' => trim($p->jabatan.' — '.$p->bidang, ' —'),
        'pangkat' => $p->jabatan,
        'nip' => $p->nip,
        'rekening' => $p->rekening,
    ]);

    $referensiJs = $referensiList->map(fn ($r) => [
        'id' => $r->id,
        'nama_pelatihan' => $r->detail_json['nama_pelatihan'] ?? '',
        'tanggal_mulai' => $r->detail_json['tanggal_mulai'] ?? '',
        'tanggal_selesai' => $r->detail_json['tanggal_selesai'] ?? '',
        'peserta' => $r->peserta->map(fn ($p) => [
            'pegawai_id' => $p->pegawai_id,
            'nama' => $p->nama,
            'pangkat' => $p->pangkat,
            'nip' => $p->nip,
            'rekening' => $p->rekening,
        ])->all(),
    ]);
?>
<script>
(function () {
    const masterAnggaranData = @json($masterAnggaranJs);
    const namaData = @json($namaJs);
    const referensiData = @json($referensiJs);

    function formatRupiah(n) {
        n = Number(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // ---- Sumber Dana: dropdown bertingkat ----
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

    // ---- Tanggal NPD -> default bulan & tahun ----
    const tanggalInput = document.getElementById('tanggal_npd');
    const bulanSelect = document.getElementById('bulan');
    const tahunInput = document.getElementById('tahun');
    tanggalInput.addEventListener('change', () => {
        if (! tanggalInput.value) return;
        const d = new Date(tanggalInput.value + 'T00:00:00');
        bulanSelect.value = String(d.getMonth() + 1);
        tahunInput.value = d.getFullYear();
    });

    // ---- Mode: tampilkan bagian yang relevan & referensi hanya untuk mode perjalanan ----
    const modeKontribusi = document.getElementById('mode-kontribusi');
    const modePerjalanan = document.getElementById('mode-perjalanan');
    const referensiWrap = document.getElementById('referensi-wrap');

    function currentMode() {
        return modePerjalanan.checked ? 'perjalanan' : 'kontribusi';
    }

    function applyMode() {
        const mode = currentMode();
        document.querySelectorAll('.kd-sec-kontribusi').forEach(el => { el.style.display = mode === 'kontribusi' ? 'block' : 'none'; });
        document.querySelectorAll('.kd-sec-perjalanan').forEach(el => { el.style.display = mode === 'perjalanan' ? 'block' : 'none'; });
        referensiWrap.style.display = mode === 'perjalanan' ? 'block' : 'none';
        recalcTotal();
    }

    modeKontribusi.addEventListener('change', applyMode);
    modePerjalanan.addEventListener('change', applyMode);

    // ---- Referensi NPD Kontribusi: salin peserta + nama pelatihan (client-side, boleh diedit) ----
    const referensiSelect = document.getElementById('npd_referensi_id');
    const namaPelatihanInput = document.getElementById('nama_pelatihan');

    referensiSelect.addEventListener('change', () => {
        const ref = referensiData.find(r => String(r.id) === referensiSelect.value);
        if (! ref) return;

        if (! namaPelatihanInput.value) namaPelatihanInput.value = ref.nama_pelatihan;

        if (ref.peserta.length) {
            pesertaList.querySelectorAll('[data-peserta-row]').forEach(row => row.remove());
            ref.peserta.forEach((p, idx) => {
                addPesertaRow();
                const row = pesertaList.querySelectorAll('[data-peserta-row]')[idx];
                row.querySelector('[data-name-input]').value = p.nama || '';
                row.querySelector('[data-pegawai-id]').value = p.pegawai_id || '';
                row.querySelector('[data-pangkat]').value = p.pangkat || '';
                row.querySelector('[data-nip]').value = p.nip || '';
                row.querySelector('[data-rekening]').value = p.rekening || '';
            });
        }
    });

    // ---- Baris Peserta ----
    const pesertaList = document.getElementById('peserta-list');
    let pesertaIndex = pesertaList.querySelectorAll('[data-peserta-row]').length;

    function pesertaRowHtml(idx) {
        return '<div class="pen" data-peserta-row>'
            + '<button type="button" class="del" data-peserta-remove title="Hapus peserta">&times;</button>'
            + '<h4>Peserta <span data-peserta-number>#' + (idx + 1) + '</span></h4>'
            + '<div class="form-grid">'
            + '<div class="fg span2">'
            + '<label class="fl">Nama Peserta</label>'
            + '<div class="nsearch" data-name-search>'
            + '<svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
            + '<input type="text" class="ns-inp" data-name-input autocomplete="off" placeholder="Cari pegawai, atau ketik nama manual..." name="peserta[' + idx + '][nama]" value="">'
            + '<div class="ns-drop" data-name-drop></div>'
            + '</div>'
            + '<input type="hidden" data-pegawai-id name="peserta[' + idx + '][pegawai_id]" value="">'
            + '</div>'
            + '<div class="fg"><label class="fl">Pangkat/Golongan</label><input type="text" data-pangkat name="peserta[' + idx + '][pangkat]" value=""></div>'
            + '<div class="fg"><label class="fl">NIP</label><input type="text" data-nip name="peserta[' + idx + '][nip]" value=""></div>'
            + '<div class="fg"><label class="fl">No. Rekening</label><input type="text" data-rekening name="peserta[' + idx + '][rekening]" value=""></div>'
            + '<div class="fg"><label class="fl">Penerima Dana</label><label style="display:flex;align-items:center;gap:6px;margin-top:8px;"><input type="radio" name="penerima_index" value="' + idx + '" data-penerima-radio' + (idx === 0 ? ' checked' : '') + '><span>Jadikan penerima transfer</span></label></div>'
            + '<div class="fg span2 kd-sec kd-sec-kontribusi">'
            + '<div class="form-grid" style="margin-top:0;">'
            + '<div class="fg"><label class="fl">Volume Kontribusi</label><input type="number" step="1" min="0" data-vol-kontribusi name="peserta[' + idx + '][volume_kontribusi]" value=""></div>'
            + '<div class="fg"><label class="fl">Tarif Kontribusi (Rp)</label><input type="number" step="0.01" min="0" data-tarif-kontribusi name="peserta[' + idx + '][tarif_kontribusi]" value=""></div>'
            + '<div class="fg"><label class="fl">Jumlah Kontribusi (otomatis)</label><input type="text" data-jml-kontribusi readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg"><label class="fl">Volume MOOC</label><input type="number" step="1" min="0" data-vol-mooc name="peserta[' + idx + '][volume_mooc]" value=""></div>'
            + '<div class="fg"><label class="fl">Tarif MOOC (Rp)</label><input type="number" step="0.01" min="0" data-tarif-mooc name="peserta[' + idx + '][tarif_mooc]" value=""></div>'
            + '<div class="fg"><label class="fl">Jumlah MOOC (otomatis)</label><input type="text" data-jml-mooc readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg span2"><label class="fl">Subtotal Kontribusi (otomatis)</label><input type="text" data-sub-kontribusi readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '</div>'
            + '</div>'
            + '<div class="fg span2 kd-sec kd-sec-perjalanan">'
            + '<div class="form-grid" style="margin-top:0;">'
            + '<div class="fg"><label class="fl">Jumlah Hari (Uang Harian)</label><input type="number" step="1" min="0" data-hari-uh name="peserta[' + idx + '][hari_uh]" value=""></div>'
            + '<div class="fg"><label class="fl">Tarif Uang Harian (Rp)</label><input type="number" step="0.01" min="0" data-tarif-uh name="peserta[' + idx + '][tarif_uh]" value=""></div>'
            + '<div class="fg"><label class="fl">Jumlah Uang Harian (otomatis)</label><input type="text" data-jml-harian readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg"><label class="fl">Volume Akomodasi (malam)</label><input type="number" step="1" min="0" data-vol-akomodasi name="peserta[' + idx + '][volume_akomodasi]" value=""></div>'
            + '<div class="fg"><label class="fl">Tarif Akomodasi (Rp)</label><input type="number" step="0.01" min="0" data-tarif-akomodasi name="peserta[' + idx + '][tarif_akomodasi]" value=""></div>'
            + '<div class="fg"><label class="fl">Jumlah Akomodasi (otomatis)</label><input type="text" data-jml-akomodasi readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg"><label class="fl">Jumlah Hari (Uang Saku)</label><input type="number" step="1" min="0" data-hari-saku name="peserta[' + idx + '][hari_saku]" value=""></div>'
            + '<div class="fg"><label class="fl">Tarif Uang Saku (Rp)</label><input type="number" step="0.01" min="0" data-tarif-saku name="peserta[' + idx + '][tarif_saku]" value=""></div>'
            + '<div class="fg"><label class="fl">Jumlah Uang Saku (otomatis)</label><input type="text" data-jml-saku readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg"><label class="fl">Transport/BBM/Tiket (Rp, at-cost)</label><input type="number" step="0.01" min="0" data-transport name="peserta[' + idx + '][transport]" value=""></div>'
            + '<div class="fg span2"><label class="fl">Subtotal Perjalanan (otomatis)</label><input type="text" data-sub-perjalanan readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '</div>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function addPesertaRow() {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = pesertaRowHtml(pesertaIndex);
        const row = wrapper.firstElementChild;
        pesertaList.appendChild(row);
        attachRowEvents(row);
        pesertaIndex++;
        renumber();
        applyMode();
    }

    function renumber() {
        const rows = pesertaList.querySelectorAll('[data-peserta-row]');
        rows.forEach((row, i) => {
            row.querySelector('[data-peserta-number]').textContent = '#' + (i + 1);
            row.querySelector('[data-peserta-remove]').disabled = rows.length <= 1;
        });
    }

    function recalcRow(row) {
        const volKontribusi = parseFloat(row.querySelector('[data-vol-kontribusi]').value) || 0;
        const tarifKontribusi = parseFloat(row.querySelector('[data-tarif-kontribusi]').value) || 0;
        const volMooc = parseFloat(row.querySelector('[data-vol-mooc]').value) || 0;
        const tarifMooc = parseFloat(row.querySelector('[data-tarif-mooc]').value) || 0;
        const jmlKontribusi = volKontribusi * tarifKontribusi;
        const jmlMooc = volMooc * tarifMooc;
        row.querySelector('[data-jml-kontribusi]').value = formatRupiah(jmlKontribusi);
        row.querySelector('[data-jml-mooc]').value = formatRupiah(jmlMooc);
        row.querySelector('[data-sub-kontribusi]').value = formatRupiah(jmlKontribusi + jmlMooc);

        const hariUh = parseFloat(row.querySelector('[data-hari-uh]').value) || 0;
        const tarifUh = parseFloat(row.querySelector('[data-tarif-uh]').value) || 0;
        const volAkomodasi = parseFloat(row.querySelector('[data-vol-akomodasi]').value) || 0;
        const tarifAkomodasi = parseFloat(row.querySelector('[data-tarif-akomodasi]').value) || 0;
        const hariSaku = parseFloat(row.querySelector('[data-hari-saku]').value) || 0;
        const tarifSaku = parseFloat(row.querySelector('[data-tarif-saku]').value) || 0;
        const transport = parseFloat(row.querySelector('[data-transport]').value) || 0;
        const jmlHarian = hariUh * tarifUh;
        const jmlAkomodasi = volAkomodasi * tarifAkomodasi;
        const jmlSaku = hariSaku * tarifSaku;
        row.querySelector('[data-jml-harian]').value = formatRupiah(jmlHarian);
        row.querySelector('[data-jml-akomodasi]').value = formatRupiah(jmlAkomodasi);
        row.querySelector('[data-jml-saku]').value = formatRupiah(jmlSaku);
        row.querySelector('[data-sub-perjalanan]').value = formatRupiah(jmlHarian + jmlAkomodasi + jmlSaku + transport);

        recalcTotal();
    }

    function recalcTotal() {
        const mode = currentMode();
        let total = 0;
        pesertaList.querySelectorAll('[data-peserta-row]').forEach(row => {
            if (mode === 'kontribusi') {
                const vol = parseFloat(row.querySelector('[data-vol-kontribusi]').value) || 0;
                const tarif = parseFloat(row.querySelector('[data-tarif-kontribusi]').value) || 0;
                const volM = parseFloat(row.querySelector('[data-vol-mooc]').value) || 0;
                const tarifM = parseFloat(row.querySelector('[data-tarif-mooc]').value) || 0;
                total += (vol * tarif) + (volM * tarifM);
            } else {
                const hariUh = parseFloat(row.querySelector('[data-hari-uh]').value) || 0;
                const tarifUh = parseFloat(row.querySelector('[data-tarif-uh]').value) || 0;
                const volAkom = parseFloat(row.querySelector('[data-vol-akomodasi]').value) || 0;
                const tarifAkom = parseFloat(row.querySelector('[data-tarif-akomodasi]').value) || 0;
                const hariSaku = parseFloat(row.querySelector('[data-hari-saku]').value) || 0;
                const tarifSaku = parseFloat(row.querySelector('[data-tarif-saku]').value) || 0;
                const transport = parseFloat(row.querySelector('[data-transport]').value) || 0;
                total += (hariUh * tarifUh) + (volAkom * tarifAkom) + (hariSaku * tarifSaku) + transport;
            }
        });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    function attachRowEvents(row) {
        const nameInput = row.querySelector('[data-name-input]');
        const nameDrop = row.querySelector('[data-name-drop]');
        const pegawaiIdField = row.querySelector('[data-pegawai-id]');
        const pangkatInput = row.querySelector('[data-pangkat]');
        const nipInput = row.querySelector('[data-nip]');
        const rekeningInput = row.querySelector('[data-rekening]');
        const delBtn = row.querySelector('[data-peserta-remove]');

        function renderNameDrop(query) {
            const q = query.trim().toLowerCase();
            let items = q ? namaData.filter(n => n.nama.toLowerCase().includes(q)) : namaData;
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
                        pangkatInput.value = n.pangkat || pangkatInput.value;
                        nipInput.value = n.nip || '';
                        rekeningInput.value = n.rekening || '';
                        nameDrop.classList.remove('show');
                    });
                    nameDrop.appendChild(el);
                });
            }
            nameDrop.classList.add('show');
        }

        nameInput.addEventListener('input', () => {
            pegawaiIdField.value = '';
            renderNameDrop(nameInput.value);
        });
        nameInput.addEventListener('focus', () => renderNameDrop(nameInput.value));

        ['[data-vol-kontribusi]', '[data-tarif-kontribusi]', '[data-vol-mooc]', '[data-tarif-mooc]',
         '[data-hari-uh]', '[data-tarif-uh]', '[data-vol-akomodasi]', '[data-tarif-akomodasi]',
         '[data-hari-saku]', '[data-tarif-saku]', '[data-transport]'].forEach(sel => {
            row.querySelector(sel).addEventListener('input', () => recalcRow(row));
        });

        delBtn.addEventListener('click', () => {
            if (pesertaList.querySelectorAll('[data-peserta-row]').length <= 1) return;
            row.remove();
            renumber();
            recalcTotal();
        });

        recalcRow(row);
    }

    document.addEventListener('click', (e) => {
        document.querySelectorAll('[data-name-drop].show').forEach(drop => {
            if (! drop.closest('[data-name-search]').contains(e.target)) drop.classList.remove('show');
        });
    });

    document.getElementById('peserta-add').addEventListener('click', addPesertaRow);

    pesertaList.querySelectorAll('[data-peserta-row]').forEach(attachRowEvents);
    renumber();
    applyMode();
})();
</script>
@endsection
