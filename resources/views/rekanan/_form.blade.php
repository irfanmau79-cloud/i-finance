@php($r = $rekanan ?? null)

{{-- Susunan dan kelasnya mengikuti formulir Data Pegawai supaya kedua
     daftar induk itu terasa sama saat diisi. --}}
<div class="form-grid-auto">
    <div class="fg span2">
        <label class="fl" for="nama">Nama Rekanan</label>
        <input id="nama" name="nama" value="{{ old('nama', $r->nama ?? '') }}" placeholder="Contoh: CV Sumber Rejeki" required>
        <div class="sub" style="margin-top:4px;">Menjadi identitas rekanan - tulis sama persis dengan yang dipakai di dokumen, karena nama inilah yang dicocokkan saat import dan saat menautkan penerima SPM LS.</div>
    </div>
    <div class="fg">
        <label class="fl" for="rekening">Rekening</label>
        <input id="rekening" name="rekening" value="{{ old('rekening', $r->rekening ?? '') }}" placeholder="Opsional" inputmode="numeric">
        <div class="sub" style="margin-top:4px;">Terisi otomatis sebagai tujuan transfer saat rekanan ini dipilih di NPD.</div>
    </div>

    <div class="fg">
        <label class="fl" for="npwp">NPWP</label>
        <input id="npwp" name="npwp" value="{{ old('npwp', $r->npwp ?? '') }}" placeholder="Contoh: 01.234.567.8-901.000">
    </div>
    <div class="fg">
        <label class="fl" for="jenis_usaha">Jenis Usaha</label>
        <input id="jenis_usaha" name="jenis_usaha" value="{{ old('jenis_usaha', $r->jenis_usaha ?? '') }}" placeholder="Contoh: Perdagangan alat tulis">
    </div>
    <div class="fg">
        <label class="fl" for="nomor_handphone">Nomor Handphone</label>
        <input id="nomor_handphone" name="nomor_handphone" value="{{ old('nomor_handphone', $r->nomor_handphone ?? '') }}" placeholder="Contoh: 081234567890" inputmode="tel">
        <div class="sub" style="margin-top:4px;">Dipakai fitur Kirim Notifikasi WhatsApp di Data NPD. Boleh ditulis 08&hellip; atau +62&hellip;</div>
    </div>

    <div class="fg">
        <label class="fl" for="pkp">Status PKP</label>
        <select id="pkp" name="pkp">
            <option value="0" @selected(old('pkp', ($r->pkp ?? false) ? '1' : '0') == '0')>Non-PKP</option>
            <option value="1" @selected(old('pkp', ($r->pkp ?? false) ? '1' : '0') == '1')>PKP</option>
        </select>
        <div class="sub" style="margin-top:4px;">Pengusaha Kena Pajak - menentukan perlakuan PPN pada dokumen.</div>
    </div>
    <div class="fg">
        <label class="fl" for="aktif">Status Aktif</label>
        <select id="aktif" name="aktif">
            <option value="1" @selected(old('aktif', ($r->aktif ?? true) ? '1' : '0') == '1')>Aktif</option>
            <option value="0" @selected(old('aktif', ($r->aktif ?? true) ? '1' : '0') == '0')>Tidak Aktif</option>
        </select>
        <div class="sub" style="margin-top:4px;">Rekanan non-aktif tidak muncul di pilihan penerima pada Pembuatan NPD.</div>
    </div>
</div>
