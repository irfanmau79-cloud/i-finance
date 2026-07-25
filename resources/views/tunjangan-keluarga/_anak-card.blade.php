@php($nomor = $i + 1)
<div class="fam-card" data-anak-card>
    <div class="fam-head"><span class="fam-ic">{{ $nomor }}</span> Anak Ke-{{ $nomor }}</div>
    <label class="fl" for="anak-{{ $i }}-nama">Nama Anak Ke-{{ $nomor }}</label>
    <input id="anak-{{ $i }}-nama" name="anak[{{ $i }}][nama]" value="{{ $anak['nama'] ?? '' }}" placeholder="Nama anak ke-{{ $nomor }}">
    <div class="form-grid2">
        <div>
            <label class="fl" for="anak-{{ $i }}-tanggal-lahir">Tanggal Lahir</label>
            <input id="anak-{{ $i }}-tanggal-lahir" type="date" name="anak[{{ $i }}][tanggal_lahir]" value="{{ $anak['tanggal_lahir'] ?? '' }}">
        </div>
        <div>
            <label class="fl" for="anak-{{ $i }}-status">Dapat Tunjangan?</label>
            <select id="anak-{{ $i }}-status" name="anak[{{ $i }}][status_tunjangan]">
                <option value="0" @selected(empty($anak['status_tunjangan']))>Tidak</option>
                <option value="1" @selected(! empty($anak['status_tunjangan']))>Ya</option>
            </select>
        </div>
    </div>
    <div class="form-grid2">
        <div>
            <label class="fl" for="anak-{{ $i }}-kuliah">Perpanjangan Kuliah?</label>
            <select id="anak-{{ $i }}-kuliah" name="anak[{{ $i }}][perpanjangan_kuliah]">
                <option value="0" @selected(empty($anak['perpanjangan_kuliah']))>Tidak</option>
                <option value="1" @selected(! empty($anak['perpanjangan_kuliah']))>Ya</option>
            </select>
        </div>
        <div>
            <label class="fl" for="anak-{{ $i }}-keterangan">Keterangan</label>
            <input id="anak-{{ $i }}-keterangan" name="anak[{{ $i }}][keterangan]" value="{{ $anak['keterangan'] ?? '' }}" placeholder="Opsional, cth: perpanjangan usia 21–25">
        </div>
    </div>
</div>
