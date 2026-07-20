@extends('layouts.app')

@section('activeNav', 'npd')
@section('title', 'Buat NPD Barang/Jasa')

@section('content')
<div class="dash-card">
    <h3>Buat NPD Barang/Jasa</h3>
    <div class="sub">Pilih sumber dana, lengkapi data NPD, lalu tambahkan penerima.</div>

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

    <form method="POST" action="{{ route('npd.bj.store') }}" id="npd-bj-form">
        @csrf

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
        <input type="hidden" name="master_anggaran_id" id="master_anggaran_id" value="{{ old('master_anggaran_id') }}">

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
                        <input type="radio" name="jenis_panjar" value="{{ $opt }}" @checked(old('jenis_panjar', 'Panjar') === $opt)>
                        <span>{{ $opt }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_npd">Tanggal NPD</label>
                <input type="date" id="tanggal_npd" name="tanggal_npd" value="{{ old('tanggal_npd') }}">
            </div>
            <div class="fg">
                <label class="fl" for="bulan">Bulan</label>
                <select id="bulan" name="bulan">
                    <option value="">-- Pilih Bulan --</option>
                    @foreach ($bulanList as $num => $label)
                        <option value="{{ $num }}" @selected((string) old('bulan') === (string) $num)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="tahun">Tahun</label>
                <input type="number" id="tahun" name="tahun" min="2000" max="2100" value="{{ old('tahun', now()->year) }}">
            </div>
        </div>

        <h3 style="margin-top:22px;">Daftar Penerima</h3>
        <div id="pen-list">
            @php $oldPenerima = old('penerima', [[]]); @endphp
            @foreach ($oldPenerima as $i => $row)
                @include('npd.bj._penerima-row', ['i' => $i, 'p' => $row])
            @endforeach
        </div>
        <button type="button" class="add" id="pen-add">+ Tambah Penerima</button>

        <div class="sumbar" style="margin-top:16px;">
            <span>Nominal Total NPD</span>
            <span class="v" id="total-nominal">Rp 0</span>
        </div>

        <div class="nav">
            <a class="btn" href="{{ route('npd.index') }}">Batal</a>
            <button type="submit" class="btn prim">Simpan sebagai Draft</button>
        </div>
    </form>
</div>

@php
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
        'sisa' => $m->sisaTersedia(),
        'keu' => $m->tentukanKeu(),
    ]);

    $namaJs = $pegawai->map(fn ($p) => [
        'id' => $p->id,
        'tipe' => 'pegawai',
        'nama' => $p->nama,
        'sub' => trim($p->jabatan.' — '.$p->bidang, ' —'),
        'rekening' => $p->rekening,
    ])->concat($vendor->map(fn ($v) => [
        'id' => $v->id,
        'tipe' => 'vendor',
        'nama' => $v->nama,
        'sub' => 'Vendor',
        'rekening' => $v->rekening,
    ]));
@endphp
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

    // ---- Baris Penerima ----
    const penList = document.getElementById('pen-list');
    let penIndex = penList.querySelectorAll('[data-pen-row]').length;

    const PPH_JENIS_OPTIONS = ['PPh Pasal 21', 'PPh Pasal 22', 'PPh Pasal 23', 'PPh Pasal 4(2)'];
    const PPH_MAX_PER_PENERIMA = 2;
    let pphSeq = 0;

    function pphOptionsHtml(selected) {
        return '<option value="">— jenis —</option>' + PPH_JENIS_OPTIONS.map(o =>
            '<option value="' + escapeHtml(o) + '"' + (o === selected ? ' selected' : '') + '>' + escapeHtml(o) + '</option>'
        ).join('');
    }

    function pphRowHtml(penIdx, pphIdx) {
        return '<div class="pph-row" data-pph-row style="display:flex;gap:8px;align-items:flex-end;margin-top:6px;">'
            + '<div style="flex:1.3;"><select name="penerima[' + penIdx + '][pph_list][' + pphIdx + '][jenis]" data-pph-jenis>' + pphOptionsHtml('') + '</select></div>'
            + '<div style="flex:1;"><input type="number" step="0.01" min="0" placeholder="Rp" data-pph-nilai name="penerima[' + penIdx + '][pph_list][' + pphIdx + '][nilai]" value=""></div>'
            + '<button type="button" class="del" style="position:static;width:30px;height:34px;flex:0 0 30px;" data-pph-remove>&times;</button>'
            + '</div>';
    }

    function penRowHtml(idx) {
        return '<div class="pen" data-pen-row>'
            + '<button type="button" class="del" data-pen-remove title="Hapus penerima">&times;</button>'
            + '<h4>Penerima <span data-pen-number>#' + (idx + 1) + '</span></h4>'
            + '<div class="form-grid">'
            + '<div class="fg span2">'
            + '<label class="fl">Nama Penerima</label>'
            + '<div class="nsearch" data-name-search>'
            + '<svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
            + '<input type="text" class="ns-inp" data-name-input autocomplete="off" placeholder="Cari pegawai/vendor, atau ketik nama manual..." name="penerima[' + idx + '][nama]" value="">'
            + '<div class="ns-drop" data-name-drop></div>'
            + '</div>'
            + '<input type="hidden" data-pegawai-id name="penerima[' + idx + '][pegawai_id]" value="">'
            + '<input type="hidden" data-vendor-id name="penerima[' + idx + '][vendor_id]" value="">'
            + '</div>'
            + '<div class="fg"><label class="fl">No. Rekening</label><input type="text" data-rekening name="penerima[' + idx + '][rekening]" value=""></div>'
            + '<div class="fg"><label class="fl">Bruto (Rp)</label><input type="number" step="0.01" min="0" data-bruto name="penerima[' + idx + '][bruto]" value=""></div>'
            + '<div class="fg"><label class="fl">PPN (Rp)</label><input type="number" step="0.01" min="0" data-ppn name="penerima[' + idx + '][ppn]" value=""></div>'
            + '<div class="fg"><label class="fl">Biaya KU/RTGS (Rp)</label><input type="number" step="0.01" min="0" data-biaya-ku-rtgs name="penerima[' + idx + '][biaya_ku_rtgs]" value=""></div>'
            + '<div class="fg span2">'
            + '<label class="fl">PPh</label>'
            + '<div data-pph-list></div>'
            + '<button type="button" class="add" style="padding:6px;font-size:11.5px;margin-top:6px;" data-pph-add>+ Tambah PPh</button>'
            + '</div>'
            + '<div class="fg"><label class="fl">Netto (otomatis)</label><input type="text" data-netto readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg span2"><label class="fl">Keterangan</label><input type="text" required name="penerima[' + idx + '][keterangan]" value=""></div>'
            + '</div>'
            + '</div>';
    }

    function renumber() {
        const rows = penList.querySelectorAll('[data-pen-row]');
        rows.forEach((row, i) => {
            row.querySelector('[data-pen-number]').textContent = '#' + (i + 1);
            row.querySelector('[data-pen-remove]').disabled = rows.length <= 1;
        });
    }

    function recalcTotal() {
        // Nominal total NPD = TOTAL BRUTO seluruh penerima (bukan netto) — persis logika GAS.
        let total = 0;
        penList.querySelectorAll('[data-pen-row]').forEach(row => {
            total += parseFloat(row.querySelector('[data-bruto]').value) || 0;
        });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    function attachPphRowEvents(pphRow, penRow) {
        pphRow.querySelectorAll('[data-pph-jenis],[data-pph-nilai]').forEach(el => {
            el.addEventListener('input', () => recalcNetto(penRow));
            el.addEventListener('change', () => recalcNetto(penRow));
        });
        pphRow.querySelector('[data-pph-remove]').addEventListener('click', () => {
            pphRow.remove();
            updatePphAddButton(penRow);
            recalcNetto(penRow);
        });
    }

    function updatePphAddButton(penRow) {
        const addBtn = penRow.querySelector('[data-pph-add]');
        const count = penRow.querySelectorAll('[data-pph-row]').length;
        addBtn.disabled = count >= PPH_MAX_PER_PENERIMA;
    }

    function recalcNetto(penRow) {
        const bruto = parseFloat(penRow.querySelector('[data-bruto]').value) || 0;
        const ppn = parseFloat(penRow.querySelector('[data-ppn]').value) || 0;
        const biaya = parseFloat(penRow.querySelector('[data-biaya-ku-rtgs]').value) || 0;
        let totalPph = 0;
        penRow.querySelectorAll('[data-pph-nilai]').forEach(inp => { totalPph += parseFloat(inp.value) || 0; });
        penRow.querySelector('[data-netto]').value = formatRupiah(bruto - ppn - totalPph - biaya);
        recalcTotal();
    }

    function attachRowEvents(row) {
        const nameInput = row.querySelector('[data-name-input]');
        const nameDrop = row.querySelector('[data-name-drop]');
        const pegawaiIdField = row.querySelector('[data-pegawai-id]');
        const vendorIdField = row.querySelector('[data-vendor-id]');
        const rekeningInput = row.querySelector('[data-rekening]');
        const brutoInput = row.querySelector('[data-bruto]');
        const ppnInput = row.querySelector('[data-ppn]');
        const biayaInput = row.querySelector('[data-biaya-ku-rtgs]');
        const pphList = row.querySelector('[data-pph-list]');
        const pphAddBtn = row.querySelector('[data-pph-add]');
        const delBtn = row.querySelector('[data-pen-remove]');
        const penIdx = row.querySelector('[data-name-input]').getAttribute('name').match(/penerima\[(\d+)\]/)[1];

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

        brutoInput.addEventListener('input', () => recalcNetto(row));
        ppnInput.addEventListener('input', () => recalcNetto(row));
        biayaInput.addEventListener('input', () => recalcNetto(row));

        pphAddBtn.addEventListener('click', () => {
            if (pphList.querySelectorAll('[data-pph-row]').length >= PPH_MAX_PER_PENERIMA) return;
            pphSeq++;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = pphRowHtml(penIdx, pphSeq);
            const pphRow = wrapper.firstElementChild;
            pphList.appendChild(pphRow);
            attachPphRowEvents(pphRow, row);
            updatePphAddButton(row);
        });

        // Baris PPh yang sudah ada (mis. dari old-input setelah validasi gagal).
        pphList.querySelectorAll('[data-pph-row]').forEach(pphRow => attachPphRowEvents(pphRow, row));
        updatePphAddButton(row);

        delBtn.addEventListener('click', () => {
            if (penList.querySelectorAll('[data-pen-row]').length <= 1) return;
            row.remove();
            renumber();
            recalcTotal();
        });

        recalcNetto(row);
    }

    document.addEventListener('click', (e) => {
        document.querySelectorAll('[data-name-drop].show').forEach(drop => {
            if (! drop.closest('[data-name-search]').contains(e.target)) drop.classList.remove('show');
        });
    });

    document.getElementById('pen-add').addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = penRowHtml(penIndex);
        const row = wrapper.firstElementChild;
        penList.appendChild(row);
        attachRowEvents(row);
        penIndex++;
        renumber();
    });

    penList.querySelectorAll('[data-pen-row]').forEach(attachRowEvents);
    renumber();
    recalcTotal();
})();
</script>
@endsection
