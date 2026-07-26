@extends('layouts.app')

@section('activeNav', 'npd')
@php($npdEdit = $npd ?? null)
@section('title', $npdEdit ? 'Edit NPD Narasumber' : 'Buat NPD Narasumber')

@section('content')
<div class="dash-card">
    <h3>{{ $npdEdit ? 'Edit' : 'Buat' }} NPD Narasumber</h3>
    <div class="sub">Pilih sumber dana, lengkapi data kegiatan, lalu tambahkan narasumber.</div>

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
        <div class="step" data-step="2"><span class="n">2</span><span class="lb">Detail &amp; Narasumber</span></div>
        <div class="step" data-step="3"><span class="n">3</span><span class="lb">Review</span></div>
    </div>

    <form method="POST" action="{{ $npdEdit ? route('npd.ns.update', $npdEdit) : route('npd.ns.store') }}" id="npd-ns-form" data-start-step="{{ $wizStartStep }}">
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

            @php($detail = $npdEdit?->detail_json ?? [])
            <div class="fg">
                <label class="fl" for="uraian_kegiatan">Uraian Kegiatan</label>
                <input type="text" id="uraian_kegiatan" name="uraian_kegiatan" placeholder="mis. Rapat Koordinasi Pengawasan Internal"
                       value="{{ old('uraian_kegiatan', $detail['uraian_kegiatan'] ?? '') }}">
            </div>
            <div class="form-grid">
                <div class="fg">
                    <label class="fl" for="tanggal_mulai">Tanggal Mulai Kegiatan (opsional)</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ old('tanggal_mulai', $detail['tanggal_mulai'] ?? '') }}">
                </div>
                <div class="fg">
                    <label class="fl" for="tanggal_selesai">Tanggal Selesai Kegiatan (opsional)</label>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ old('tanggal_selesai', $detail['tanggal_selesai'] ?? '') }}">
                </div>
            </div>

            <h3 style="margin-top:22px;">Daftar Narasumber</h3>
            <div id="nara-list">
                <?php
                    $oldNarasumber = old('narasumber', $narasumberAwal ?? [[]]);
                ?>
                @foreach ($oldNarasumber as $i => $row)
                    @include('npd.ns._narasumber-row', ['i' => $i, 'n' => $row])
                @endforeach
            </div>
            <button type="button" class="add" id="nara-add">+ Tambah Narasumber</button>

            <div class="sumbar" style="margin-top:16px;">
                <span>Nominal Total NPD</span>
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
        'uraian_rekening' => $m->uraian_rekening,
        'tagging_id' => $m->tagging_id,
        'tagging' => $m->tagging->nama ?? 'Tanpa Tagging',
        'pagu' => (float) $m->pagu,
        'sisa' => $m->sisaTersedia() + ($npdEdit && $npdEdit->master_anggaran_id === $m->id ? (float) $npdEdit->nominal : 0),
        'keu' => $m->tentukanKeu(),
    ]);

    $namaJs = $pegawai->map(fn ($p) => [
        'id' => $p->id,
        'tipe' => 'pegawai',
        'nama' => $p->nama,
        'sub' => trim($p->jabatan.' — '.$p->bidang, ' —'),
        'jabatan' => $p->jabatan,
        'rekening' => $p->rekening,
    ])->concat($vendor->map(fn ($v) => [
        'id' => $v->id,
        'tipe' => 'vendor',
        'nama' => $v->nama,
        'sub' => 'Vendor',
        'jabatan' => '',
        'rekening' => $v->rekening,
    ]));
?>
<script>
(function () {
    const masterAnggaranData = @json($masterAnggaranJs);

    const namaData = @json($namaJs);

    function formatRupiah(n) {
        n = Number(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // ---- Sumber Dana: dropdown bertingkat (Program -> Kegiatan -> Sub Kegiatan -> Kode Rekening -> Tagging) ----
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
        fillOptions(maSel.kode, opts, opts.length ? '— Pilih Kode Rekening —' : 'Pilih sub kegiatan terlebih dahulu');
        fillOptions(maSel.tagging, [], 'Pilih kode rekening terlebih dahulu');
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
        fillOptions(maSel.tagging, opts, opts.length ? '— Pilih Tagging —' : 'Pilih kode rekening terlebih dahulu');
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

    // Pulihkan pilihan cascade jika form gagal validasi (old input).
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

    // ---- Baris Narasumber ----
    const naraList = document.getElementById('nara-list');
    let naraIndex = naraList.querySelectorAll('[data-nara-row]').length;

    function naraRowHtml(idx) {
        return '<div class="pen" data-nara-row>'
            + '<button type="button" class="del" data-nara-remove title="Hapus narasumber">&times;</button>'
            + '<h4>Narasumber <span data-nara-number>#' + (idx + 1) + '</span></h4>'
            + '<div class="form-grid">'
            + '<div class="fg span2">'
            + '<label class="fl">Nama Narasumber</label>'
            + '<div class="nsearch" data-name-search>'
            + '<svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
            + '<input type="text" class="ns-inp" data-name-input autocomplete="off" placeholder="Cari pegawai/vendor, atau ketik nama manual..." name="narasumber[' + idx + '][nama]" value="">'
            + '<div class="ns-drop" data-name-drop></div>'
            + '</div>'
            + '<input type="hidden" data-pegawai-id name="narasumber[' + idx + '][pegawai_id]" value="">'
            + '<input type="hidden" data-vendor-id name="narasumber[' + idx + '][vendor_id]" value="">'
            + '</div>'
            + '<div class="fg"><label class="fl">Jabatan</label><input type="text" data-jabatan name="narasumber[' + idx + '][jabatan]" value=""></div>'
            + '<div class="fg"><label class="fl">No. Rekening</label><input type="text" data-rekening name="narasumber[' + idx + '][rekening]" value=""></div>'
            + '<div class="fg"><label class="fl">Jumlah JP</label><input type="number" step="1" min="0" data-jp name="narasumber[' + idx + '][jumlah_jp]" value=""></div>'
            + '<div class="fg"><label class="fl">Tarif per JP (Rp)</label><input type="number" step="0.01" min="0" data-tarif name="narasumber[' + idx + '][tarif_jp]" value=""></div>'
            + '<div class="fg"><label class="fl">Honor (otomatis)</label><input type="text" data-honor readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg"><label class="fl">Pengganti Transport (Rp)</label><input type="number" step="0.01" min="0" data-transport name="narasumber[' + idx + '][transport]" value=""></div>'
            + '<div class="fg"><label class="fl">Bruto (otomatis)</label><input type="text" data-bruto readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg"><label class="fl">PPh Pasal 21 (Rp)</label><input type="number" step="0.01" min="0" data-pph21 name="narasumber[' + idx + '][pph21]" value=""></div>'
            + '<div class="fg"><label class="fl">Diterima / Netto (otomatis)</label><input type="text" data-netto readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg span2"><label class="fl">Keterangan Lampiran (opsional)</label><input type="text" name="narasumber[' + idx + '][uraian]" value="" placeholder="Kosongkan untuk memakai uraian kegiatan"></div>'
            + '</div>'
            + '</div>';
    }

    function renumber() {
        const rows = naraList.querySelectorAll('[data-nara-row]');
        rows.forEach((row, i) => {
            row.querySelector('[data-nara-number]').textContent = '#' + (i + 1);
            row.querySelector('[data-nara-remove]').disabled = rows.length <= 1;
        });
    }

    function recalcRow(row) {
        const jp = parseFloat(row.querySelector('[data-jp]').value) || 0;
        const tarif = parseFloat(row.querySelector('[data-tarif]').value) || 0;
        const transport = parseFloat(row.querySelector('[data-transport]').value) || 0;
        const pph21 = parseFloat(row.querySelector('[data-pph21]').value) || 0;

        const honor = jp * tarif;
        const bruto = honor + transport;
        const netto = bruto - pph21;

        row.querySelector('[data-honor]').value = formatRupiah(honor);
        row.querySelector('[data-bruto]').value = formatRupiah(bruto);
        row.querySelector('[data-netto]').value = formatRupiah(netto);

        recalcTotal();
    }

    function recalcTotal() {
        // Nominal total NPD = TOTAL BRUTO seluruh narasumber (honor + transport), bukan netto.
        let total = 0;
        naraList.querySelectorAll('[data-nara-row]').forEach(row => {
            const jp = parseFloat(row.querySelector('[data-jp]').value) || 0;
            const tarif = parseFloat(row.querySelector('[data-tarif]').value) || 0;
            const transport = parseFloat(row.querySelector('[data-transport]').value) || 0;
            total += (jp * tarif) + transport;
        });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    function attachRowEvents(row) {
        const nameInput = row.querySelector('[data-name-input]');
        const nameDrop = row.querySelector('[data-name-drop]');
        const pegawaiIdField = row.querySelector('[data-pegawai-id]');
        const vendorIdField = row.querySelector('[data-vendor-id]');
        const jabatanInput = row.querySelector('[data-jabatan]');
        const rekeningInput = row.querySelector('[data-rekening]');
        const delBtn = row.querySelector('[data-nara-remove]');

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
                        pegawaiIdField.value = n.tipe === 'pegawai' ? n.id : '';
                        vendorIdField.value = n.tipe === 'vendor' ? n.id : '';
                        jabatanInput.value = n.jabatan || jabatanInput.value;
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
            vendorIdField.value = '';
            renderNameDrop(nameInput.value);
        });
        nameInput.addEventListener('focus', () => renderNameDrop(nameInput.value));

        ['[data-jp]', '[data-tarif]', '[data-transport]', '[data-pph21]'].forEach(sel => {
            row.querySelector(sel).addEventListener('input', () => recalcRow(row));
        });

        delBtn.addEventListener('click', () => {
            if (naraList.querySelectorAll('[data-nara-row]').length <= 1) return;
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

    document.getElementById('nara-add').addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = naraRowHtml(naraIndex);
        const row = wrapper.firstElementChild;
        naraList.appendChild(row);
        attachRowEvents(row);
        naraIndex++;
        renumber();
    });

    naraList.querySelectorAll('[data-nara-row]').forEach(attachRowEvents);
    renumber();
    recalcTotal();

    // ---- Wizard: stepper + review ----
    const wizForm = document.getElementById('npd-ns-form');
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

        html += '<div class="grp"><div class="gt">Detail Kegiatan</div>'
            + liRow('Jenis', jenis) + liRow('Tanggal NPD', tanggalInput.value || '—')
            + liRow('Uraian Kegiatan', document.getElementById('uraian_kegiatan').value || '—')
            + liRow('Nominal Total', document.getElementById('total-nominal').textContent) + '</div>';

        const naraRows = naraList.querySelectorAll('[data-nara-row]');
        html += '<div class="grp"><div class="gt">Narasumber (' + naraRows.length + ')</div>';
        naraRows.forEach(row => {
            const nama = row.querySelector('[data-name-input]').value || '(belum diisi)';
            const netto = row.querySelector('[data-netto]').value;
            html += liRow(nama, netto);
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
        if (! document.getElementById('uraian_kegiatan').value.trim() || ! tanggalInput.value) {
            showStepErr('err-2', 'Lengkapi uraian kegiatan dan tanggal NPD.');
            return;
        }
        const namaKosong = Array.from(naraList.querySelectorAll('[data-name-input]')).some(inp => ! inp.value.trim());
        if (namaKosong) { showStepErr('err-2', 'Lengkapi nama semua narasumber.'); return; }
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
