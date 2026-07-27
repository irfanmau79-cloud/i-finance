@php
    $sp = $suratPerintah ?? null;
    $anggotaAwal = old('anggota', $sp?->anggota?->map(fn ($item) => [
        'pegawai_id' => $item->pegawai_id,
        'jabatan_sp' => $item->jabatan_sp,
    ])->values()->all() ?? []);
    $pegawaiAnggotaJs = $pegawaiList->map(fn ($item) => [
        'id' => $item->id,
        'nama' => $item->nama,
        'detail' => trim($item->jabatan.' — '.$item->bidang, ' —'),
    ])->values()->all();
    $jabatanSpJs = \App\Models\SuratPerintah::JABATAN_ANGGOTA;
@endphp

<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
    <label for="website">Website</label>
    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
</div>

<div class="form-grid">
    <div class="fg">
        <label class="fl" for="nomor_sp">Nomor Surat Perintah</label>
        <input type="text" id="nomor_sp" name="nomor_sp" placeholder="87/PW.02.01/Sekre" value="{{ old('nomor_sp', $sp->nomor_sp ?? '') }}">
    </div>

    <div class="fg">
        <label class="fl" for="status_sp">Status Surat Perintah</label>
        <select id="status_sp" name="status_sp">
            <option value="">-- Pilih Status SP --</option>
            <option value="Baru" @selected(old('status_sp', $sp->status_sp ?? null) === 'Baru')>Baru</option>
            <option value="Revisi" @selected(old('status_sp', $sp->status_sp ?? null) === 'Revisi')>Revisi</option>
        </select>
    </div>

    <div class="fg">
        <label class="fl" for="tanggal_sp">Tanggal Surat Perintah</label>
        <input type="date" id="tanggal_sp" name="tanggal_sp" value="{{ old('tanggal_sp', $sp?->tanggal_sp?->format('Y-m-d')) }}">
    </div>

    <div class="fg">
        <label class="fl" for="unit_kerja">Unit Kerja</label>
        <select id="unit_kerja" name="unit_kerja">
            <option value="" disabled @selected(! old('unit_kerja', $sp->unit_kerja ?? null))>&mdash; Pilih Unit Kerja &mdash;</option>
            @foreach ([
                'Inspektur Pembantu I',
                'Inspektur Pembantu II',
                'Inspektur Pembantu III',
                'Inspektur Pembantu IV',
                'Inspektur Pembantu Investigasi',
                'Sekretariat',
                'Subbagian Tata Usaha',
            ] as $unit)
                <option value="{{ $unit }}" @selected(old('unit_kerja', $sp->unit_kerja ?? null) === $unit)>{{ $unit }}</option>
            @endforeach
        </select>
    </div>

    <div class="fg">
        <label class="fl" for="lokasi">Kabupaten/Kota Lokasi Penugasan</label>
        <input type="text" id="lokasi" name="lokasi" placeholder="Contoh: Kabupaten Bekasi" value="{{ old('lokasi', $sp->lokasi ?? '') }}">
    </div>

    <div class="fg">
        <label class="fl" for="nama_pengirim">Nama Pengirim</label>
        <input type="text" id="nama_pengirim" name="nama_pengirim" value="{{ old('nama_pengirim', $sp->nama_pengirim ?? '') }}">
    </div>

    <div class="fg">
        <label class="fl" for="tujuan_transfer">Tujuan Transfer (Nama Orang)</label>
        <input type="text" id="tujuan_transfer" name="tujuan_transfer" value="{{ old('tujuan_transfer', $sp->tujuan_transfer ?? '') }}">
    </div>

    <div class="fg">
        <label class="fl" for="irban_dibayar">Apakah Irban dibayar di SP ini?</label>
        <select id="irban_dibayar" name="irban_dibayar">
            <option value="0" @selected(old('irban_dibayar', isset($sp) ? ($sp->irban_dibayar ? '1' : '0') : '0') === '0')>Tidak</option>
            <option value="1" @selected(old('irban_dibayar', isset($sp) ? ($sp->irban_dibayar ? '1' : '0') : '0') === '1')>Ya</option>
        </select>
    </div>

    <div class="fg">
        <label class="fl" for="rincian_tgl_bayar">Rincian Tanggal Penugasan yang Dibayar</label>
        <input type="text" id="rincian_tgl_bayar" name="rincian_tgl_bayar" placeholder="Contoh: 1 - 2 Mei 2026" value="{{ old('rincian_tgl_bayar', $sp->rincian_tgl_bayar ?? '') }}">
    </div>

    <div class="fg span2">
        <label class="fl" for="keterangan">Keterangan</label>
        <textarea id="keterangan" name="keterangan" rows="2" placeholder="Contoh: Reviu LKPD&hellip;">{{ old('keterangan', $sp->keterangan ?? '') }}</textarea>
    </div>

    <div class="fg span2">
        <label class="fl" for="file_url">Upload PDF SP</label>
        @if ($sp)
            @php
                $fileTersedia = $sp->fileTersedia();
            @endphp
            <p class="mini">
                File saat ini:
                @if ($fileTersedia)
                    <a href="{{ route('surat-perintah.file', $sp) }}" target="_blank" rel="noopener">Lihat SP</a>
                @else
                    <a href="#" onclick="alert('File tidak tersedia.'); return false;">Lihat SP</a>
                @endif
                &mdash; kosongkan jika tidak ingin mengganti.
            </p>
        @endif
        <input type="file" id="file_url" name="file_url" accept="application/pdf">
    </div>
</div>

<section class="sp-anggota-card">
    <div class="sp-anggota-head">
        <div>
            <h3>Anggota Surat Perintah</h3>
            <p>Pilih pegawai dan tentukan perannya dalam Surat Perintah. Data identitasnya akan otomatis dibawa saat SP ditautkan ke NPD Perjalanan Dinas.</p>
        </div>
        <button type="button" class="btn prim" id="sp-anggota-add">+ Tambah Anggota</button>
    </div>
    <div id="sp-anggota-list"></div>
    <div class="sp-anggota-empty" id="sp-anggota-empty">Belum ada anggota yang ditambahkan.</div>
</section>

<script>
(function () {
    const list = document.getElementById('sp-anggota-list');
    const empty = document.getElementById('sp-anggota-empty');
    const pegawai = @json($pegawaiAnggotaJs);
    const jabatan = @json($jabatanSpJs);
    const initial = @json($anggotaAwal);
    let sequence = 0;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[char]));
    }

    function refresh() {
        const rows = Array.from(list.querySelectorAll('[data-sp-anggota]'));
        rows.forEach((row, index) => {
            row.querySelector('[data-sp-number]').textContent = index + 1;
        });
        empty.hidden = rows.length > 0;
    }

    function addAnggota(data = {}) {
        const index = sequence++;
        const pegawaiOptions = pegawai.map(item =>
            '<option value="' + item.id + '"' + (String(item.id) === String(data.pegawai_id ?? '') ? ' selected' : '') + '>'
            + escapeHtml(item.nama + (item.detail ? ' — ' + item.detail : '')) + '</option>'
        ).join('');
        const jabatanOptions = jabatan.map(item =>
            '<option value="' + escapeHtml(item) + '"' + (item === (data.jabatan_sp ?? '') ? ' selected' : '') + '>'
            + escapeHtml(item) + '</option>'
        ).join('');

        const wrapper = document.createElement('div');
        wrapper.innerHTML = '<div class="sp-anggota-row" data-sp-anggota>'
            + '<div class="sp-anggota-no" data-sp-number></div>'
            + '<div class="fg"><label class="fl">Nama Pegawai</label><select name="anggota[' + index + '][pegawai_id]" required>'
            + '<option value="">— Pilih dari Data Pegawai —</option>' + pegawaiOptions + '</select></div>'
            + '<div class="fg"><label class="fl">Jabatan dalam SP</label><select name="anggota[' + index + '][jabatan_sp]" required>'
            + '<option value="">— Pilih Jabatan —</option>' + jabatanOptions + '</select></div>'
            + '<button type="button" class="ic-btn danger" data-sp-remove title="Hapus anggota" aria-label="Hapus anggota">&times;</button>'
            + '</div>';
        const row = wrapper.firstElementChild;
        row.querySelector('[data-sp-remove]').addEventListener('click', () => {
            row.remove();
            refresh();
        });
        list.appendChild(row);
        refresh();
    }

    document.getElementById('sp-anggota-add').addEventListener('click', () => addAnggota());
    initial.forEach(item => addAnggota(item));
})();
</script>
