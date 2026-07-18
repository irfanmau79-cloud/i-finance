@php
    $namaVal = old("penerima.$i.nama", $p['nama'] ?? '');
    $rekeningVal = old("penerima.$i.rekening", $p['rekening'] ?? '');
    $brutoVal = old("penerima.$i.bruto", $p['bruto'] ?? '');
    $ppnVal = old("penerima.$i.ppn", $p['ppn'] ?? '');
    $biayaKuRtgsVal = old("penerima.$i.biaya_ku_rtgs", $p['biaya_ku_rtgs'] ?? '');
    $ketVal = old("penerima.$i.keterangan", $p['keterangan'] ?? '');
    $pegawaiIdVal = old("penerima.$i.pegawai_id", $p['pegawai_id'] ?? '');
    $vendorIdVal = old("penerima.$i.vendor_id", $p['vendor_id'] ?? '');
    $pphListVal = old("penerima.$i.pph_list", $p['pph_list'] ?? []);
@endphp
<div class="pen" data-pen-row>
    <button type="button" class="del" data-pen-remove title="Hapus penerima">&times;</button>
    <h4>Penerima <span data-pen-number>#{{ $i + 1 }}</span></h4>
    <div class="form-grid">
        <div class="fg span2">
            <label class="fl">Nama Penerima</label>
            <div class="nsearch" data-name-search>
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="ns-inp" data-name-input autocomplete="off"
                       placeholder="Cari pegawai/vendor, atau ketik nama manual..."
                       name="penerima[{{ $i }}][nama]" value="{{ $namaVal }}">
                <div class="ns-drop" data-name-drop></div>
            </div>
            <input type="hidden" data-pegawai-id name="penerima[{{ $i }}][pegawai_id]" value="{{ $pegawaiIdVal }}">
            <input type="hidden" data-vendor-id name="penerima[{{ $i }}][vendor_id]" value="{{ $vendorIdVal }}">
        </div>
        <div class="fg">
            <label class="fl">No. Rekening</label>
            <input type="text" name="penerima[{{ $i }}][rekening]" value="{{ $rekeningVal }}">
        </div>
        <div class="fg">
            <label class="fl">Bruto (Rp)</label>
            <input type="number" step="0.01" min="0" data-bruto name="penerima[{{ $i }}][bruto]" value="{{ $brutoVal }}">
        </div>
        <div class="fg">
            <label class="fl">PPN (Rp)</label>
            <input type="number" step="0.01" min="0" data-ppn name="penerima[{{ $i }}][ppn]" value="{{ $ppnVal }}">
        </div>
        <div class="fg">
            <label class="fl">Biaya KU/RTGS (Rp)</label>
            <input type="number" step="0.01" min="0" data-biaya-ku-rtgs name="penerima[{{ $i }}][biaya_ku_rtgs]" value="{{ $biayaKuRtgsVal }}">
        </div>
        <div class="fg span2">
            <label class="fl">PPh</label>
            <div data-pph-list>
                @foreach ($pphListVal as $j => $pp)
                    @include('npd.bj._pph-row', ['i' => $i, 'j' => $j, 'pp' => $pp])
                @endforeach
            </div>
            <button type="button" class="add" style="padding:6px;font-size:11.5px;margin-top:6px;" data-pph-add>+ Tambah PPh</button>
        </div>
        <div class="fg">
            <label class="fl">Netto (otomatis)</label>
            <input type="text" data-netto readonly value="Rp 0" style="background:#f8fafc;font-weight:700;">
        </div>
        <div class="fg span2">
            <label class="fl">Keterangan</label>
            <input type="text" required name="penerima[{{ $i }}][keterangan]" value="{{ $ketVal }}">
        </div>
    </div>
</div>
