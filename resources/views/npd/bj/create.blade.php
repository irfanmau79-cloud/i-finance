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
            <label class="fl" for="ma-input">Sumber Dana</label>
            <div class="nsearch" id="ma-search">
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="ns-inp" id="ma-input" autocomplete="off"
                       placeholder="Cari sub kegiatan, kode rekening, atau tagging...">
                <div class="ns-drop" id="ma-drop"></div>
            </div>
            <input type="hidden" name="master_anggaran_id" id="master_anggaran_id" value="{{ old('master_anggaran_id') }}">
        </div>

        <div class="auto" id="ma-detail" style="display:none;">
            <div class="ai"><span class="k">Sub Kegiatan</span><span class="v" id="ma-sub"></span></div>
            <div class="ai"><span class="k">Kode Rekening</span><span class="v" id="ma-kode"></span></div>
            <div class="ai"><span class="k">Tagging</span><span class="v" id="ma-tagging"></span></div>
            <div class="ai"><span class="k">Pagu</span><span class="v" id="ma-pagu"></span></div>
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
        'sub_kegiatan' => $m->sub_kegiatan,
        'kode_rekening' => $m->kode_rekening,
        'tagging' => $m->tagging->nama ?? '-',
        'pagu' => (float) $m->pagu,
        'keu' => $m->tentukanKeu(),
    ]);

    $namaJs = $pegawai->map(fn ($p) => [
        'id' => $p->id,
        'tipe' => 'pegawai',
        'nama' => $p->nama,
        'sub' => trim($p->jabatan.' — '.$p->bidang, ' —'),
    ])->concat($vendor->map(fn ($v) => [
        'id' => $v->id,
        'tipe' => 'vendor',
        'nama' => $v->nama,
        'sub' => 'Vendor',
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

    // ---- Pencarian Sumber Dana (master anggaran) ----
    const maInput = document.getElementById('ma-input');
    const maDrop = document.getElementById('ma-drop');
    const maIdField = document.getElementById('master_anggaran_id');
    const maDetail = document.getElementById('ma-detail');

    function renderMaDrop(query) {
        const q = query.trim().toLowerCase();
        let items = masterAnggaranData;
        if (q) {
            items = items.filter(m =>
                m.sub_kegiatan.toLowerCase().includes(q) ||
                m.kode_rekening.toLowerCase().includes(q) ||
                m.tagging.toLowerCase().includes(q)
            );
        }
        items = items.slice(0, 50);

        maDrop.innerHTML = '';
        if (! items.length) {
            maDrop.innerHTML = '<div class="ns-empty">Tidak ada sumber dana yang cocok.</div>';
        } else {
            items.forEach(m => {
                const el = document.createElement('div');
                el.className = 'ns-item';
                el.innerHTML = '<div><div>' + escapeHtml(m.kode_rekening) + ' — ' + escapeHtml(m.sub_kegiatan) + '</div>'
                    + '<div class="sub">' + escapeHtml(m.tagging) + ' · Pagu ' + formatRupiah(m.pagu) + '</div></div>';
                el.addEventListener('click', () => selectMasterAnggaran(m));
                maDrop.appendChild(el);
            });
        }
        maDrop.classList.add('show');
    }

    function selectMasterAnggaran(m) {
        maIdField.value = m.id;
        maInput.value = m.kode_rekening + ' — ' + m.sub_kegiatan;
        maDrop.classList.remove('show');

        document.getElementById('ma-sub').textContent = m.sub_kegiatan;
        document.getElementById('ma-kode').textContent = m.kode_rekening;
        document.getElementById('ma-tagging').textContent = m.tagging;
        document.getElementById('ma-pagu').textContent = formatRupiah(m.pagu);
        document.getElementById('ma-keu').textContent = m.keu ? ('KEU ' + m.keu) : 'Tidak dapat ditentukan';
        maDetail.style.display = 'block';
    }

    maInput.addEventListener('input', () => {
        maIdField.value = '';
        renderMaDrop(maInput.value);
    });
    maInput.addEventListener('focus', () => renderMaDrop(maInput.value));

    if (maIdField.value) {
        const found = masterAnggaranData.find(m => String(m.id) === String(maIdField.value));
        if (found) selectMasterAnggaran(found);
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
            + '<div class="fg"><label class="fl">No. Rekening</label><input type="text" name="penerima[' + idx + '][rekening]" value=""></div>'
            + '<div class="fg"><label class="fl">Bruto (Rp)</label><input type="number" step="0.01" min="0" data-bruto name="penerima[' + idx + '][bruto]" value=""></div>'
            + '<div class="fg"><label class="fl">PPN (Rp)</label><input type="number" step="0.01" min="0" data-ppn name="penerima[' + idx + '][ppn]" value=""></div>'
            + '<div class="fg"><label class="fl">Biaya KU/RTGS (Rp)</label><input type="number" step="0.01" min="0" data-biaya-ku-rtgs name="penerima[' + idx + '][biaya_ku_rtgs]" value=""></div>'
            + '<div class="fg span2">'
            + '<label class="fl">PPh</label>'
            + '<div data-pph-list></div>'
            + '<button type="button" class="add" style="padding:6px;font-size:11.5px;margin-top:6px;" data-pph-add>+ Tambah PPh</button>'
            + '</div>'
            + '<div class="fg"><label class="fl">Netto (otomatis)</label><input type="text" data-netto readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>'
            + '<div class="fg span2"><label class="fl">Keterangan (opsional)</label><input type="text" name="penerima[' + idx + '][keterangan]" value=""></div>'
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
        if (! e.target.closest('#ma-search')) maDrop.classList.remove('show');
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
