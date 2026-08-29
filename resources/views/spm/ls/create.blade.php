@extends('layouts.app')

@section('activeNav', 'spm-ls')
@php($spmEdit = $spm ?? null)
@section('title', $spmEdit ? 'Edit Realisasi SP2D LS' : 'Buat Realisasi SP2D LS')

@section('content')
<div class="dash-card">
    <h3>{{ $spmEdit ? 'Edit' : 'Buat' }} Realisasi SP2D LS</h3>
    <div class="sub">Lengkapi data dokumen, lalu tambahkan satu baris per mata anggaran yang dicakup dokumen ini. PPN/PPh/penerima berlaku untuk seluruh dokumen, bukan per baris.</div>

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

    <form method="POST" action="{{ $spmEdit ? route('spm.ls.update', $spmEdit) : route('spm.ls.store') }}" id="spm-ls-form">
        @csrf
        @if ($spmEdit) @method('PUT') @endif

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
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_sp2d">Tanggal SP2D (opsional)</label>
                <input type="date" id="tanggal_sp2d" name="tanggal_sp2d" value="{{ old('tanggal_sp2d', $spmEdit?->tanggal_sp2d?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="nomor_sp2d">Nomor SP2D (opsional)</label>
                <input type="text" id="nomor_sp2d" name="nomor_sp2d" value="{{ old('nomor_sp2d', $spmEdit?->nomor_sp2d) }}">
            </div>
        </div>

        <h3 style="margin-top:22px;">Daftar Mata Anggaran</h3>
        <div id="baris-list">
            <?php $barisAwal = old('baris', $spmEdit ? $spmEdit->detail->map(fn ($d) => ['master_anggaran_id' => $d->master_anggaran_id, 'nominal' => (float) $d->nominal])->all() : [[]]); ?>
            @foreach ($barisAwal as $i => $b)
                @include('spm.ls._baris-row', ['i' => $i, 'b' => $b])
            @endforeach
        </div>
        <button type="button" class="add" id="baris-add">+ Tambah Mata Anggaran</button>

        <div class="sumbar" style="margin-top:16px;">
            <span>Total Nominal Dokumen</span>
            <span class="v" id="total-nominal">Rp 0</span>
        </div>

        <h3 style="margin-top:22px;">Pajak/Biaya Dokumen (satu angka untuk seluruh SPM)</h3>
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
    $detailLama = $spmEdit ? $spmEdit->detail->pluck('nominal', 'master_anggaran_id')->map(fn ($v) => (float) $v) : collect();
    $masterAnggaranJs = $masterAnggaran->map(function ($m) use ($detailLama) {
        $sisaTambahan = (float) ($detailLama[$m->id] ?? 0);

        return [
            'id' => $m->id,
            'program' => $m->program_lengkap,
            'kegiatan' => $m->kegiatan_lengkap,
            'sub_kegiatan' => $m->sub_kegiatan_lengkap,
            'kode_rekening' => $m->rekening_lengkap,
            'kode_rekening_bersih' => $m->kode_rekening_bersih,
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

    function kodeLabel(m) {
        return m.kode_rekening;
    }

    function fillOptions(sel, options, placeholder) {
        sel.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>'
            + options.map(o => '<option value="' + escapeHtml(o.value) + '">' + escapeHtml(o.label) + '</option>').join('');
        sel.disabled = options.length === 0;
    }

    // ---- Cascade Program>Kegiatan>Sub Kegiatan>Kode Rekening>Tagging, dipasang per baris ----
    function wireCascade(row) {
        const sel = {
            program: row.querySelector('[data-ma-program]'),
            kegiatan: row.querySelector('[data-ma-kegiatan]'),
            sub: row.querySelector('[data-ma-sub]'),
            kode: row.querySelector('[data-ma-kode]'),
            tagging: row.querySelector('[data-ma-tagging]'),
        };
        const idField = row.querySelector('[data-ma-id]');
        const detailBox = row.querySelector('[data-ma-detail]');
        const M = { program: '', kegiatan: '', sub: '', kode: '', tagging: '' };

        function hideDetail() {
            idField.value = '';
            detailBox.style.display = 'none';
            recalcTotal();
        }

        function loadPrograms() {
            const progs = uniq(masterAnggaranData.map(m => m.program));
            fillOptions(sel.program, progs.map(p => ({ value: p, label: p })), '— Pilih Program —');
        }

        function onProgramChange() {
            M.program = sel.program.value;
            M.kegiatan = M.sub = M.kode = M.tagging = '';
            const k = uniq(masterAnggaranData.filter(m => m.program === M.program).map(m => m.kegiatan));
            fillOptions(sel.kegiatan, k.map(v => ({ value: v, label: v })), k.length ? '— Pilih Kegiatan —' : 'Pilih program dulu');
            fillOptions(sel.sub, [], 'Pilih kegiatan dulu');
            fillOptions(sel.kode, [], 'Pilih sub kegiatan dulu');
            fillOptions(sel.tagging, [], 'Pilih kode rekening dulu');
            hideDetail();
        }

        function onKegiatanChange() {
            M.kegiatan = sel.kegiatan.value;
            M.sub = M.kode = M.tagging = '';
            const s = uniq(masterAnggaranData.filter(m => m.program === M.program && m.kegiatan === M.kegiatan).map(m => m.sub_kegiatan));
            fillOptions(sel.sub, s.map(v => ({ value: v, label: v })), s.length ? '— Pilih Sub Kegiatan —' : 'Pilih kegiatan dulu');
            fillOptions(sel.kode, [], 'Pilih sub kegiatan dulu');
            fillOptions(sel.tagging, [], 'Pilih kode rekening dulu');
            hideDetail();
        }

        function onSubChange() {
            M.sub = sel.sub.value;
            M.kode = M.tagging = '';
            const rows = masterAnggaranData.filter(m => m.program === M.program && m.kegiatan === M.kegiatan && m.sub_kegiatan === M.sub);
            const seen = new Set();
            const opts = [];
            rows.forEach(m => {
                if (! seen.has(m.kode_rekening_bersih)) { seen.add(m.kode_rekening_bersih); opts.push({ value: m.kode_rekening_bersih, label: kodeLabel(m) }); }
            });
            fillOptions(sel.kode, opts, opts.length ? '— Pilih Kode Rekening —' : 'Pilih sub kegiatan dulu');
            fillOptions(sel.tagging, [], 'Pilih kode rekening dulu');
            hideDetail();
        }

        function onKodeChange() {
            M.kode = sel.kode.value;
            M.tagging = '';
            const rows = masterAnggaranData.filter(m => m.program === M.program && m.kegiatan === M.kegiatan && m.sub_kegiatan === M.sub && m.kode_rekening_bersih === M.kode);
            const seen = new Set();
            const opts = [];
            rows.forEach(m => {
                const val = taggingValue(m);
                if (! seen.has(val)) { seen.add(val); opts.push({ value: val, label: m.tagging }); }
            });
            fillOptions(sel.tagging, opts, opts.length ? '— Pilih Tagging —' : 'Pilih kode rekening dulu');
            hideDetail();
        }

        function onTaggingChange() {
            M.tagging = sel.tagging.value;
            const found = masterAnggaranData.find(m => m.program === M.program && m.kegiatan === M.kegiatan && m.sub_kegiatan === M.sub && m.kode_rekening_bersih === M.kode && taggingValue(m) === M.tagging);
            if (found) {
                selectMasterAnggaran(found);
            } else {
                hideDetail();
            }
        }

        function selectMasterAnggaran(m) {
            idField.value = m.id;
            row.querySelector('[data-ma-pagu]').textContent = formatRupiah(m.pagu);
            row.querySelector('[data-ma-terikat]').textContent = formatRupiah(m.dana_terikat);
            row.querySelector('[data-ma-realisasi]').textContent = formatRupiah(m.realisasi_aktual);
            row.querySelector('[data-ma-sisa]').textContent = formatRupiah(m.sisa);
            detailBox.style.display = 'block';
            recalcTotal();
        }

        sel.program.addEventListener('change', onProgramChange);
        sel.kegiatan.addEventListener('change', onKegiatanChange);
        sel.sub.addEventListener('change', onSubChange);
        sel.kode.addEventListener('change', onKodeChange);
        sel.tagging.addEventListener('change', onTaggingChange);

        loadPrograms();

        if (idField.value) {
            const found = masterAnggaranData.find(m => String(m.id) === String(idField.value));
            if (found) {
                sel.program.value = found.program;
                onProgramChange();
                sel.kegiatan.value = found.kegiatan;
                onKegiatanChange();
                sel.sub.value = found.sub_kegiatan;
                onSubChange();
                sel.kode.value = found.kode_rekening_bersih;
                onKodeChange();
                sel.tagging.value = taggingValue(found);
                selectMasterAnggaran(found);
            }
        }

        row.querySelector('[data-ma-nominal]').addEventListener('input', recalcTotal);
    }

    // ---- Baris mata anggaran: tambah/hapus, mengikuti pola npd/kd (peserta) ----
    const barisList = document.getElementById('baris-list');
    let barisIndex = barisList.querySelectorAll('[data-baris-row]').length;

    function barisRowHtml(idx) {
        return '<div class="pen" data-baris-row>'
            + '<button type="button" class="del" data-baris-remove title="Hapus baris">&times;</button>'
            + '<h4>Mata Anggaran <span data-baris-number>#' + (idx + 1) + '</span></h4>'
            + '<div class="fg"><label class="fl">Program</label><select data-ma-program><option value="">Memuat data…</option></select></div>'
            + '<div class="fg"><label class="fl">Kegiatan</label><select data-ma-kegiatan disabled><option value="">Pilih program dulu</option></select></div>'
            + '<div class="fg"><label class="fl">Sub Kegiatan</label><select data-ma-sub disabled><option value="">Pilih kegiatan dulu</option></select></div>'
            + '<div class="form-grid">'
            + '<div class="fg"><label class="fl">Kode Rekening</label><select data-ma-kode disabled><option value="">Pilih sub kegiatan dulu</option></select></div>'
            + '<div class="fg"><label class="fl">Tagging</label><select data-ma-tagging disabled><option value="">Pilih kode rekening dulu</option></select></div>'
            + '</div>'
            + '<input type="hidden" data-ma-id name="baris[' + idx + '][master_anggaran_id]" value="">'
            + '<div class="auto" data-ma-detail style="display:none;">'
            + '<div class="ai"><span class="k">Pagu Anggaran</span><span class="v" data-ma-pagu></span></div>'
            + '<div class="ai"><span class="k">Dana Terikat NPD</span><span class="v" data-ma-terikat></span></div>'
            + '<div class="ai"><span class="k">Realisasi Aktual</span><span class="v" data-ma-realisasi></span></div>'
            + '<div class="ai"><span class="k">Sisa Tersedia</span><span class="v" data-ma-sisa style="color:var(--ok);font-weight:800;"></span></div>'
            + '</div>'
            + '<div class="fg"><label class="fl">Nominal Bruto Baris Ini (Rp)</label><input type="number" step="0.01" min="0.01" data-ma-nominal name="baris[' + idx + '][nominal]" value=""></div>'
            + '</div>';
    }

    function addBarisRow() {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = barisRowHtml(barisIndex);
        const row = wrapper.firstElementChild;
        barisList.appendChild(row);
        wireCascade(row);
        attachDelete(row);
        barisIndex++;
        renumber();
    }

    function renumber() {
        const rows = barisList.querySelectorAll('[data-baris-row]');
        rows.forEach((row, i) => {
            row.querySelector('[data-baris-number]').textContent = '#' + (i + 1);
            row.querySelector('[data-baris-remove]').disabled = rows.length <= 1;
        });
    }

    function recalcTotal() {
        let total = 0;
        barisList.querySelectorAll('[data-baris-row]').forEach(row => {
            total += parseFloat(row.querySelector('[data-ma-nominal]').value) || 0;
        });
        document.getElementById('total-nominal').textContent = formatRupiah(total);
    }

    function attachDelete(row) {
        row.querySelector('[data-baris-remove]').addEventListener('click', () => {
            if (barisList.querySelectorAll('[data-baris-row]').length <= 1) return;
            row.remove();
            renumber();
            recalcTotal();
        });
    }

    document.getElementById('baris-add').addEventListener('click', addBarisRow);

    barisList.querySelectorAll('[data-baris-row]').forEach(row => {
        wireCascade(row);
        attachDelete(row);
    });
    renumber();
    recalcTotal();
})();
</script>
@endsection
