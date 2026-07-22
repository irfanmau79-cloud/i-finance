@php
    $maIdVal = old("baris.$i.master_anggaran_id", $b['master_anggaran_id'] ?? '');
    $nominalVal = old("baris.$i.nominal", $b['nominal'] ?? '');
@endphp
<div class="pen" data-baris-row>
    <button type="button" class="del" data-baris-remove title="Hapus baris">&times;</button>
    <h4>Mata Anggaran <span data-baris-number>#{{ $i + 1 }}</span></h4>
    <div class="fg">
        <label class="fl">Program</label>
        <select data-ma-program><option value="">Memuat data…</option></select>
    </div>
    <div class="fg">
        <label class="fl">Kegiatan</label>
        <select data-ma-kegiatan disabled><option value="">Pilih program dulu</option></select>
    </div>
    <div class="fg">
        <label class="fl">Sub Kegiatan</label>
        <select data-ma-sub disabled><option value="">Pilih kegiatan dulu</option></select>
    </div>
    <div class="form-grid">
        <div class="fg">
            <label class="fl">Kode Rekening</label>
            <select data-ma-kode disabled><option value="">Pilih sub kegiatan dulu</option></select>
        </div>
        <div class="fg">
            <label class="fl">Tagging</label>
            <select data-ma-tagging disabled><option value="">Pilih kode rekening dulu</option></select>
        </div>
    </div>
    <input type="hidden" data-ma-id name="baris[{{ $i }}][master_anggaran_id]" value="{{ $maIdVal }}">

    <div class="auto" data-ma-detail style="display:none;">
        <div class="ai"><span class="k">Pagu Anggaran</span><span class="v" data-ma-pagu></span></div>
        <div class="ai"><span class="k">Dana Terikat NPD</span><span class="v" data-ma-terikat></span></div>
        <div class="ai"><span class="k">Realisasi Aktual</span><span class="v" data-ma-realisasi></span></div>
        <div class="ai"><span class="k">Sisa Tersedia</span><span class="v" data-ma-sisa style="color:var(--ok);font-weight:800;"></span></div>
    </div>

    <div class="fg">
        <label class="fl">Nominal Bruto Baris Ini (Rp)</label>
        <input type="number" step="0.01" min="0.01" data-ma-nominal name="baris[{{ $i }}][nominal]" value="{{ $nominalVal }}">
    </div>
</div>
