@php
    $namaVal = old("narasumber.$i.nama", $n['nama'] ?? '');
    $jabatanVal = old("narasumber.$i.jabatan", $n['jabatan'] ?? '');
    $rekeningVal = old("narasumber.$i.rekening", $n['rekening'] ?? '');
    $jpVal = old("narasumber.$i.jumlah_jp", $n['jumlah_jp'] ?? '');
    $tarifVal = old("narasumber.$i.tarif_jp", $n['tarif_jp'] ?? '');
    $transportVal = old("narasumber.$i.transport", $n['transport'] ?? '');
    $pph21Val = old("narasumber.$i.pph21", $n['pph21'] ?? '');
    $uraianVal = old("narasumber.$i.uraian", $n['uraian'] ?? '');
    $pegawaiIdVal = old("narasumber.$i.pegawai_id", $n['pegawai_id'] ?? '');
    $vendorIdVal = old("narasumber.$i.vendor_id", $n['vendor_id'] ?? '');
@endphp
<div class="pen" data-nara-row>
    <button type="button" class="del" data-nara-remove title="Hapus narasumber">&times;</button>
    <h4>Narasumber <span data-nara-number>#{{ $i + 1 }}</span></h4>
    <div class="form-grid">
        <div class="fg span2">
            <label class="fl">Nama Narasumber</label>
            <div class="nsearch" data-name-search>
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="ns-inp" data-name-input autocomplete="off"
                       placeholder="Cari pegawai/vendor, atau ketik nama manual..."
                       name="narasumber[{{ $i }}][nama]" value="{{ $namaVal }}">
                <div class="ns-drop" data-name-drop></div>
            </div>
            <input type="hidden" data-pegawai-id name="narasumber[{{ $i }}][pegawai_id]" value="{{ $pegawaiIdVal }}">
            <input type="hidden" data-vendor-id name="narasumber[{{ $i }}][vendor_id]" value="{{ $vendorIdVal }}">
        </div>
        <div class="fg">
            <label class="fl">Jabatan</label>
            <input type="text" data-jabatan name="narasumber[{{ $i }}][jabatan]" value="{{ $jabatanVal }}">
        </div>
        <div class="fg">
            <label class="fl">No. Rekening</label>
            <input type="text" data-rekening name="narasumber[{{ $i }}][rekening]" value="{{ $rekeningVal }}">
        </div>
        <div class="fg">
            <label class="fl">Jumlah JP</label>
            <input type="number" step="1" min="0" data-jp name="narasumber[{{ $i }}][jumlah_jp]" value="{{ $jpVal }}">
        </div>
        <div class="fg">
            <label class="fl">Tarif per JP (Rp)</label>
            <input type="number" step="0.01" min="0" data-tarif name="narasumber[{{ $i }}][tarif_jp]" value="{{ $tarifVal }}">
        </div>
        <div class="fg">
            <label class="fl">Honor (otomatis)</label>
            <input type="text" data-honor readonly value="Rp 0" style="background:#f8fafc;font-weight:700;">
        </div>
        <div class="fg">
            <label class="fl">Pengganti Transport (Rp)</label>
            <input type="number" step="0.01" min="0" data-transport name="narasumber[{{ $i }}][transport]" value="{{ $transportVal }}">
        </div>
        <div class="fg">
            <label class="fl">Bruto (otomatis)</label>
            <input type="text" data-bruto readonly value="Rp 0" style="background:#f8fafc;font-weight:700;">
        </div>
        <div class="fg">
            <label class="fl">PPh Pasal 21 (Rp)</label>
            <input type="number" step="0.01" min="0" data-pph21 name="narasumber[{{ $i }}][pph21]" value="{{ $pph21Val }}">
        </div>
        <div class="fg">
            <label class="fl">Diterima / Netto (otomatis)</label>
            <input type="text" data-netto readonly value="Rp 0" style="background:#f8fafc;font-weight:700;">
        </div>
        <div class="fg span2">
            <label class="fl">Keterangan Lampiran (opsional)</label>
            <input type="text" name="narasumber[{{ $i }}][uraian]" value="{{ $uraianVal }}" placeholder="Kosongkan untuk memakai uraian kegiatan">
        </div>
    </div>
</div>
