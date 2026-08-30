@php
    use App\Models\SuratPerintah;

    $sp = $suratPerintah ?? null;
    $isEdit = $sp !== null;
    $jenisAwal = old('jenis_permintaan', $sp?->jenis_permintaan ?? SuratPerintah::JENIS_UANG_HARIAN);

    $anggotaAwal = old('anggota', $sp?->anggota?->map(fn ($item) => $item->sebagaiInput())->values()->all() ?? []);

    $pegawaiAnggotaJs = $pegawaiList->map(fn ($item) => [
        'id' => $item->id,
        'nama' => $item->nama,
        'nip' => (string) $item->nip,
        'golongan' => (string) $item->golongan,
        'pangkat' => (string) $item->pangkat,
        'jabatan' => (string) $item->jabatan,
        'rekening' => (string) $item->rekening,
        'detail' => trim(($item->jabatan ?? '').' — '.($item->bidang ?? ''), ' —'),
    ])->values()->all();

    $indukJs = ($indukList ?? collect())->map(fn ($item) => [
        'id' => $item->id,
        'nomor_sp' => $item->nomor_sp,
        'tanggal_sp' => $item->tanggal_sp?->format('Y-m-d'),
        'unit_kerja' => $item->unit_kerja,
        'lokasi' => $item->lokasi,
        'nama_pengirim' => $item->nama_pengirim,
        'tujuan_transfer' => $item->tujuan_transfer,
        'irban_dibayar' => $item->irban_dibayar ? '1' : '0',
        'rincian_tgl_bayar' => $item->rincian_tgl_bayar,
        'keterangan' => $item->keterangan,
        'jumlah_anggota' => $item->anggota->count(),
    ])->values()->all();

    $komponenAwal = old('komponen', $sp?->pengajuanArray() ?? []);
@endphp

<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
    <label for="website">Website</label>
    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
</div>

<div class="form-grid">
    @unless ($isEdit)
        <div class="fg span2">
            <label class="fl" for="jenis_permintaan">Jenis Permintaan Pembayaran</label>
            <select id="jenis_permintaan" name="jenis_permintaan">
                @foreach (SuratPerintah::JENIS_PERMINTAAN as $jenis)
                    <option value="{{ $jenis }}" @selected($jenisAwal === $jenis)>{{ $jenis }}</option>
                @endforeach
            </select>
        </div>

        <div class="fg span2" id="sp-induk-wrap" hidden>
            <label class="fl" for="sp_induk_id">Pilih SP Uang Harian/Akomodasi (induk)</label>
            <select id="sp_induk_id" name="sp_induk_id" data-cari>
                <option value="">&mdash; Pilih SP induk &mdash;</option>
                @foreach ($indukJs as $induk)
                    <option value="{{ $induk['id'] }}" @selected((string) old('sp_induk_id') === (string) $induk['id'])>
                        {{ $induk['nomor_sp'] }} &mdash; {{ $induk['lokasi'] }} ({{ $induk['jumlah_anggota'] }} anggota)
                    </option>
                @endforeach
            </select>
            <div class="sub" style="margin-top:4px;">
                Data SP dan anggotanya disalin dari SP induk lalu terkunci. Nomor SP mengikuti induk dengan tambahan
                &ldquo;{{ trim(SuratPerintah::SUFFIX_REIMBURSE) }}&rdquo;. Satu SP induk hanya bisa punya satu entri Reimburse.
            </div>
            @if ($indukJs === [])
                <div class="sub" style="color:#b45309;margin-top:4px;">
                    Belum ada SP Uang Harian/Akomodasi yang memenuhi syarat (punya anggota, Sumber NPD aktif, dan belum punya entri Reimburse).
                </div>
            @endif
        </div>
    @else
        <div class="fg span2">
            <label class="fl">Jenis Permintaan Pembayaran</label>
            <input type="text" value="{{ $sp->jenis_permintaan }}" readonly>
            <div class="sub" style="margin-top:4px;">
                Jenis permintaan tidak bisa diubah setelah SP dibuat.
                @if ($sp->isReimburse() && $sp->induk)
                    Entri ini menginduk pada SP <strong>{{ $sp->induk->nomor_sp }}</strong>.
                @endif
            </div>
        </div>
    @endunless

    <div class="fg" data-sp-identitas>
        <label class="fl" for="nomor_sp">Nomor Surat Perintah</label>
        <input type="text" id="nomor_sp" name="nomor_sp" placeholder="87/PW.02.01/Sekre" value="{{ old('nomor_sp', $sp->nomor_sp ?? '') }}">
    </div>

    <div class="fg">
        <label class="fl" for="status_sp">Status Surat Perintah</label>
        <select id="status_sp" name="status_sp">
            <option value="">-- Pilih Status SP --</option>
            <option value="Baru" @selected(old('status_sp', $sp->status_sp ?? 'Baru') === 'Baru')>Baru</option>
            <option value="Revisi" @selected(old('status_sp', $sp->status_sp ?? null) === 'Revisi')>Revisi</option>
        </select>
    </div>

    <div class="fg" data-sp-identitas>
        <label class="fl" for="tanggal_sp">Tanggal Surat Perintah</label>
        <input type="date" id="tanggal_sp" name="tanggal_sp" value="{{ old('tanggal_sp', $sp?->tanggal_sp?->format('Y-m-d')) }}">
    </div>

    <div class="fg" data-sp-identitas>
        <label class="fl" for="unit_kerja">Unit Kerja</label>
        <select id="unit_kerja" name="unit_kerja">
            <option value="" disabled @selected(! old('unit_kerja', $sp->unit_kerja ?? null))>&mdash; Pilih Unit Kerja &mdash;</option>
            @foreach (\App\Http\Requests\StoreSuratPerintahRequest::UNIT_KERJA as $unit)
                <option value="{{ $unit }}" @selected(old('unit_kerja', $sp->unit_kerja ?? null) === $unit)>{{ $unit }}</option>
            @endforeach
        </select>
    </div>

    <div class="fg" data-sp-identitas>
        <label class="fl" for="lokasi">Kabupaten/Kota Lokasi Penugasan</label>
        <input type="text" id="lokasi" name="lokasi" placeholder="Contoh: Kabupaten Bekasi" value="{{ old('lokasi', $sp->lokasi ?? '') }}">
    </div>

    <div class="fg" data-sp-identitas>
        <label class="fl" for="nama_pengirim">Nama Pengirim</label>
        <input type="text" id="nama_pengirim" name="nama_pengirim" value="{{ old('nama_pengirim', $sp->nama_pengirim ?? '') }}">
    </div>

    <div class="fg" data-sp-identitas>
        <label class="fl" for="tujuan_transfer">Tujuan Transfer (Nama Orang)</label>
        <input type="text" id="tujuan_transfer" name="tujuan_transfer" value="{{ old('tujuan_transfer', $sp->tujuan_transfer ?? '') }}">
    </div>

    <div class="fg" data-sp-identitas>
        <label class="fl" for="irban_dibayar">Apakah Irban dibayar di SP ini?</label>
        <select id="irban_dibayar" name="irban_dibayar">
            <option value="0" @selected(old('irban_dibayar', isset($sp) ? ($sp->irban_dibayar ? '1' : '0') : '0') === '0')>Tidak</option>
            <option value="1" @selected(old('irban_dibayar', isset($sp) ? ($sp->irban_dibayar ? '1' : '0') : '0') === '1')>Ya</option>
        </select>
    </div>

    <div class="fg" data-sp-identitas>
        <label class="fl" for="rincian_tgl_bayar">Rincian Tanggal Penugasan yang Dibayar</label>
        <input type="text" id="rincian_tgl_bayar" name="rincian_tgl_bayar" placeholder="Contoh: 1 - 2 Mei 2026" value="{{ old('rincian_tgl_bayar', $sp->rincian_tgl_bayar ?? '') }}">
    </div>

    <div class="fg span2" data-sp-komponen>
        <label class="fl">Komponen Pembayaran</label>
        <div class="komp-pilih">
            @foreach (SuratPerintah::PENGAJUAN_OPTIONS as $opsi)
                <label class="komp-chip">
                    <input type="checkbox" name="komponen[]" value="{{ $opsi }}" @checked(in_array($opsi, $komponenAwal, true))>
                    <span class="komp-box"><svg viewBox="0 0 16 16" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4.5"/></svg></span>
                    <span class="komp-txt">{{ $opsi === 'Transport' ? 'Transportasi' : $opsi }}</span>
                </label>
            @endforeach
        </div>
        <div class="sub" style="margin-top:4px;">Yang dicentang mengisi kolom Pengajuan di Monitoring SP.</div>
    </div>

    <div class="fg span2" data-sp-identitas>
        <label class="fl" for="keterangan">Keterangan</label>
        <textarea id="keterangan" name="keterangan" rows="2" placeholder="Contoh: Reviu LKPD&hellip;">{{ old('keterangan', $sp->keterangan ?? '') }}</textarea>
    </div>

    <div class="fg span2">
        <label class="fl" for="file_url">Upload PDF SP</label>
        @if ($sp)
            <p class="mini">
                File saat ini:
                @if ($sp->fileTersedia())
                    <a href="{{ route('surat-perintah.file', $sp) }}" target="_blank" rel="noopener">Lihat SP</a>
                @else
                    <span class="sub">belum ada</span>
                @endif
                &mdash; kosongkan jika tidak ingin mengganti.
            </p>
        @endif
        <input type="file" id="file_url" name="file_url" accept="application/pdf">
        <div class="sub" id="sp-file-note" style="margin-top:4px;" hidden>Untuk Reimburse Transportasi, unggahan PDF tidak wajib.</div>
    </div>
</div>

<section class="sp-anggota-card" id="sp-anggota-card">
    <div class="sp-anggota-head">
        <div class="sp-anggota-judul">
            <h3>Anggota Surat Perintah</h3>
            <span class="sp-anggota-hitung" id="sp-anggota-hitung" hidden>0 orang</span>
        </div>
        <button type="button" class="btn prim sp-anggota-btn" data-sp-add>
            <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah Anggota
        </button>
    </div>

    <div id="sp-anggota-list"></div>

    <div class="sp-anggota-empty" id="sp-anggota-empty">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        <div class="sp-anggota-empty-judul">Belum ada anggota</div>
        <div class="sp-anggota-empty-sub">Tambahkan minimal satu orang yang bertugas pada Surat Perintah ini.</div>
    </div>

    {{-- Tombol tambah kedua di bawah daftar: setelah beberapa kartu terisi,
         tombol di kepala kartu sudah jauh dari titik kerja pengguna. --}}
    <button type="button" class="sp-anggota-add-row" data-sp-add>
        <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Anggota
    </button>
</section>

<div class="sub" id="sp-anggota-reimburse" hidden style="margin-top:12px;padding:10px;border:1px solid #fde68a;background:#fffbeb;border-radius:8px;">
    Anggota akan <strong>disalin otomatis</strong> dari SP induk yang dipilih, jadi tidak perlu diisi di sini.
</div>

<script>
(function () {
    const REIMBURSE = @json(SuratPerintah::JENIS_REIMBURSE);
    const list = document.getElementById('sp-anggota-list');
    const empty = document.getElementById('sp-anggota-empty');
    const hitung = document.getElementById('sp-anggota-hitung');
    const pegawai = @json($pegawaiAnggotaJs);
    const jabatan = @json(SuratPerintah::JABATAN_ANGGOTA);
    const initial = @json($anggotaAwal);
    const jenisSelect = document.getElementById('jenis_permintaan');
    const indukWrap = document.getElementById('sp-induk-wrap');
    const indukSelect = document.getElementById('sp_induk_id');
    const anggotaCard = document.getElementById('sp-anggota-card');
    const anggotaNote = document.getElementById('sp-anggota-reimburse');
    const fileNote = document.getElementById('sp-file-note');
    let sequence = 0;

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function refresh() {
        const rows = Array.from(list.querySelectorAll('[data-sp-anggota]'));
        rows.forEach((row, i) => {
            row.querySelectorAll('[data-sp-number]').forEach(el => { el.textContent = i + 1; });
        });
        empty.hidden = rows.length > 0;
        if (hitung) {
            hitung.hidden = rows.length === 0;
            hitung.textContent = rows.length + ' orang';
        }
    }

    /** Gabungan "III/c / Penata" seperti di GAS: satu isian, dipecah saat submit. */
    function gabungGolPangkat(gol, pangkat) {
        return [gol, pangkat].map(v => String(v || '').trim()).filter(Boolean).join(' / ');
    }

    function pecahGolPangkat(nilai) {
        const s = String(nilai || '').trim();
        if (!s) return { golongan: '', pangkat: '' };
        const pos = s.indexOf('/');
        if (pos < 0) return { golongan: s, pangkat: '' };
        return { golongan: s.slice(0, pos).trim(), pangkat: s.slice(pos + 1).trim() };
    }

    function setManual(row, manual) {
        row.dataset.manual = manual ? '1' : '0';
        row.querySelector('[data-manual]').value = manual ? '1' : '0';
        row.querySelector('[data-manual-toggle]').checked = manual;
        row.querySelector('[data-nama-select]').hidden = manual;
        row.querySelector('[data-nama-select]').disabled = manual;
        row.querySelector('[data-nama-text]').hidden = !manual;
        row.querySelector('[data-nama-text]').disabled = !manual;
        row.querySelectorAll('[data-identitas]').forEach(el => { el.readOnly = !manual; });
    }

    function isiDariPegawai(row, id) {
        const p = pegawai.find(x => String(x.id) === String(id));
        row.querySelector('[data-pegawai-id]').value = p ? p.id : '';
        row.querySelector('[data-nama-hidden]').value = p ? p.nama : '';
        row.querySelector('[data-nip]').value = p ? p.nip : '';
        row.querySelector('[data-golpangkat]').value = p ? gabungGolPangkat(p.golongan, p.pangkat) : '';
        row.querySelector('[data-jabatan]').value = p ? p.jabatan : '';
        row.querySelector('[data-rekening]').value = p ? p.rekening : '';
    }

    function addAnggota(data = {}) {
        const i = sequence++;
        const manual = String(data.manual ?? '') === '1' || data.manual === true;
        const opsiPegawai = pegawai.map(p =>
            '<option value="' + p.id + '"' + (String(p.id) === String(data.pegawai_id ?? '') ? ' selected' : '')
            + (p.detail ? ' data-sub="' + esc(p.detail) + '"' : '') + '>'
            + esc(p.nama) + '</option>'
        ).join('');
        const opsiJabatan = jabatan.map(j =>
            '<option value="' + esc(j) + '"' + (j === (data.jabatan_sp ?? '') ? ' selected' : '') + '>' + esc(j) + '</option>'
        ).join('');

        const wrap = document.createElement('div');
        wrap.innerHTML = '<div class="sp-anggota-row" data-sp-anggota>'
            // Baris judul: nomor + label, aksi di kanan. Terpisah dari isian
            // supaya nomornya sejajar judul, bukan menggantung di atas kolom.
            + '<div class="sp-anggota-bar">'
            + '<span class="sp-anggota-no" data-sp-number></span>'
            + '<span class="sp-anggota-title">Anggota <span data-sp-number></span></span>'
            + '<label class="komp-chip sp-manual-chip" title="Isi identitas sendiri untuk orang di luar Data Pegawai">'
            + '<input type="checkbox" data-manual-toggle' + (manual ? ' checked' : '') + '>'
            + '<span class="komp-box"><svg viewBox="0 0 16 16" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4.5"/></svg></span>'
            + '<span class="komp-txt">Isi Manual</span></label>'
            + '<button type="button" class="ic-btn danger" data-sp-remove title="Hapus anggota" aria-label="Hapus anggota">&times;</button>'
            + '</div>'
            + '<div class="sp-anggota-fields">'
            + '<div class="fg sp-f-nama"><label class="fl">Nama</label>'
            + '<select data-cari data-nama-select><option value="">— Pilih dari Data Pegawai —</option>' + opsiPegawai + '</select>'
            + '<input type="text" data-nama-text placeholder="Ketik nama lengkap" value="' + esc(data.nama ?? '') + '" hidden disabled>'
            + '</div>'
            + '<div class="fg"><label class="fl">NIP</label>'
            + '<input type="text" data-identitas data-nip name="anggota[' + i + '][nip]" value="' + esc(data.nip ?? '') + '" readonly></div>'
            + '<div class="fg"><label class="fl">Golongan / Pangkat</label>'
            + '<input type="text" data-identitas data-golpangkat value="' + esc(gabungGolPangkat(data.golongan, data.pangkat)) + '" readonly></div>'
            + '<div class="fg sp-f-jabatan"><label class="fl">Jabatan</label>'
            + '<input type="text" data-identitas data-jabatan name="anggota[' + i + '][jabatan]" value="' + esc(data.jabatan ?? '') + '" readonly></div>'
            + '<div class="fg sp-f-tim"><label class="fl">Jabatan Dalam Tim <span class="sub">(opsional)</span></label>'
            + '<select name="anggota[' + i + '][jabatan_sp]"><option value="">— tidak ditentukan —</option>' + opsiJabatan + '</select></div>'
            + '</div>'
            + '<div class="sp-anggota-note">Identitas diketik sendiri. Nama tidak dicocokkan ke Data Pegawai.</div>'
            + '<input type="hidden" data-pegawai-id name="anggota[' + i + '][pegawai_id]" value="' + esc(data.pegawai_id ?? '') + '">'
            + '<input type="hidden" data-nama-hidden name="anggota[' + i + '][nama]" value="' + esc(data.nama ?? '') + '">'
            + '<input type="hidden" data-manual name="anggota[' + i + '][manual]" value="' + (manual ? '1' : '0') + '">'
            + '<input type="hidden" data-golongan name="anggota[' + i + '][golongan]" value="' + esc(data.golongan ?? '') + '">'
            + '<input type="hidden" data-pangkat name="anggota[' + i + '][pangkat]" value="' + esc(data.pangkat ?? '') + '">'
            + '<input type="hidden" data-rekening name="anggota[' + i + '][rekening]" value="' + esc(data.rekening ?? '') + '">'
            + '</div>';

        const row = wrap.firstElementChild;
        const namaSelect = row.querySelector('[data-nama-select]');
        const namaText = row.querySelector('[data-nama-text]');

        namaSelect.addEventListener('change', () => {
            isiDariPegawai(row, namaSelect.value);
        });
        namaText.addEventListener('input', () => {
            row.querySelector('[data-nama-hidden]').value = namaText.value;
            row.querySelector('[data-pegawai-id]').value = '';
        });
        row.querySelector('[data-manual-toggle]').addEventListener('change', (e) => {
            const jadiManual = e.target.checked;
            setManual(row, jadiManual);
            if (jadiManual) {
                namaText.value = row.querySelector('[data-nama-hidden]').value;
            } else {
                isiDariPegawai(row, namaSelect.value);
            }
        });
        row.querySelector('[data-sp-remove]').addEventListener('click', () => { row.remove(); refresh(); });

        list.appendChild(row);
        // Dipasang saat itu juga, bukan menunggu pengamat dokumen: baris ini
        // langsung difokuskan setelah dibuat, jadi isian pencariannya harus
        // sudah ada saat baris ini kembali ke pemanggil.
        if (window.SelectCari) window.SelectCari.pasang(row);
        setManual(row, manual);
        refresh();
        return row;
    }

    // Golongan & Pangkat dikirim terpisah; isian gabungannya dipecah saat submit.
    const form = list.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            list.querySelectorAll('[data-sp-anggota]').forEach(row => {
                const { golongan, pangkat } = pecahGolPangkat(row.querySelector('[data-golpangkat]').value);
                row.querySelector('[data-golongan]').value = golongan;
                row.querySelector('[data-pangkat]').value = pangkat;
            });
        });
    }

    function terapkanJenis() {
        const reimburse = jenisSelect && jenisSelect.value === REIMBURSE;
        if (indukWrap) indukWrap.hidden = !reimburse;
        if (indukSelect) indukSelect.disabled = !reimburse;
        if (fileNote) fileNote.hidden = !reimburse;
        if (anggotaCard) anggotaCard.hidden = reimburse;
        if (anggotaNote) anggotaNote.hidden = !reimburse;

        // Identitas & komponen dikunci: nilainya disalin dari SP induk di server.
        document.querySelectorAll('[data-sp-identitas] input, [data-sp-identitas] select, [data-sp-identitas] textarea')
            .forEach(el => { el.disabled = reimburse; });
        document.querySelectorAll('[data-sp-komponen] input').forEach(el => { el.disabled = reimburse; });
        document.querySelectorAll('[data-sp-komponen]').forEach(el => { el.hidden = reimburse; });
        document.querySelectorAll('[data-sp-identitas]').forEach(el => { el.hidden = reimburse; });

        if (reimburse) {
            list.querySelectorAll('[data-sp-anggota] input, [data-sp-anggota] select').forEach(el => { el.disabled = true; });
        } else {
            list.querySelectorAll('[data-sp-anggota]').forEach(row => {
                row.querySelectorAll('input, select').forEach(el => { el.disabled = false; });
                setManual(row, row.dataset.manual === '1');
            });
        }
    }

    document.querySelectorAll('[data-sp-add]').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = addAnggota();
            // Fokuskan langsung ke isian Nama kartu baru supaya bisa terus
            // mengetik tanpa memindahkan tangan ke tetikus.
            const namaSel = row.querySelector('[data-nama-select]');
            const fokus = namaSel && ! namaSel.hidden
                ? (namaSel.closest('.scari')?.querySelector('.sc-inp') ?? namaSel)
                : row.querySelector('[data-nama-text]');
            if (fokus) fokus.focus();
            row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    });
    initial.forEach(item => addAnggota(item));

    if (jenisSelect) {
        jenisSelect.addEventListener('change', terapkanJenis);
        terapkanJenis();
    }
})();
</script>
