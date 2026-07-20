@php
    $namaVal = old("peserta.$i.nama", $p['nama'] ?? '');
    $pangkatVal = old("peserta.$i.pangkat", $p['pangkat'] ?? '');
    $nipVal = old("peserta.$i.nip", $p['nip'] ?? '');
    $rekeningVal = old("peserta.$i.rekening", $p['rekening'] ?? '');
    $pegawaiIdVal = old("peserta.$i.pegawai_id", $p['pegawai_id'] ?? '');
    $volKontribusiVal = old("peserta.$i.volume_kontribusi", $p['volume_kontribusi'] ?? '');
    $tarifKontribusiVal = old("peserta.$i.tarif_kontribusi", $p['tarif_kontribusi'] ?? '');
    $volMoocVal = old("peserta.$i.volume_mooc", $p['volume_mooc'] ?? '');
    $tarifMoocVal = old("peserta.$i.tarif_mooc", $p['tarif_mooc'] ?? '');
    $hariUhVal = old("peserta.$i.hari_uh", $p['hari_uh'] ?? '');
    $tarifUhVal = old("peserta.$i.tarif_uh", $p['tarif_uh'] ?? '');
    $volAkomodasiVal = old("peserta.$i.volume_akomodasi", $p['volume_akomodasi'] ?? '');
    $tarifAkomodasiVal = old("peserta.$i.tarif_akomodasi", $p['tarif_akomodasi'] ?? '');
    $hariSakuVal = old("peserta.$i.hari_saku", $p['hari_saku'] ?? '');
    $tarifSakuVal = old("peserta.$i.tarif_saku", $p['tarif_saku'] ?? '');
    $transportVal = old("peserta.$i.transport", $p['transport'] ?? '');
    $penerimaIndexVal = (string) old('penerima_index', $detailAwal['penerima_index'] ?? 0);
@endphp
<div class="pen" data-peserta-row>
    <button type="button" class="del" data-peserta-remove title="Hapus peserta">&times;</button>
    <h4>Peserta <span data-peserta-number>#{{ $i + 1 }}</span></h4>
    <div class="form-grid">
        <div class="fg span2">
            <label class="fl">Nama Peserta</label>
            <div class="nsearch" data-name-search>
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="ns-inp" data-name-input autocomplete="off"
                       placeholder="Cari pegawai, atau ketik nama manual..."
                       name="peserta[{{ $i }}][nama]" value="{{ $namaVal }}">
                <div class="ns-drop" data-name-drop></div>
            </div>
            <input type="hidden" data-pegawai-id name="peserta[{{ $i }}][pegawai_id]" value="{{ $pegawaiIdVal }}">
        </div>
        <div class="fg">
            <label class="fl">Pangkat/Golongan</label>
            <input type="text" data-pangkat name="peserta[{{ $i }}][pangkat]" value="{{ $pangkatVal }}">
        </div>
        <div class="fg">
            <label class="fl">NIP</label>
            <input type="text" data-nip name="peserta[{{ $i }}][nip]" value="{{ $nipVal }}">
        </div>
        <div class="fg">
            <label class="fl">No. Rekening</label>
            <input type="text" data-rekening name="peserta[{{ $i }}][rekening]" value="{{ $rekeningVal }}">
        </div>
        <div class="fg">
            <label class="fl">Penerima Dana</label>
            <label style="display:flex;align-items:center;gap:6px;margin-top:8px;">
                <input type="radio" name="penerima_index" value="{{ $i }}" data-penerima-radio @checked($penerimaIndexVal === (string) $i)>
                <span>Jadikan penerima transfer</span>
            </label>
        </div>

        <div class="fg span2 kd-sec kd-sec-kontribusi">
            <div class="form-grid" style="margin-top:0;">
                <div class="fg"><label class="fl">Volume Kontribusi</label><input type="number" step="1" min="0" data-vol-kontribusi name="peserta[{{ $i }}][volume_kontribusi]" value="{{ $volKontribusiVal }}"></div>
                <div class="fg"><label class="fl">Tarif Kontribusi (Rp)</label><input type="number" step="0.01" min="0" data-tarif-kontribusi name="peserta[{{ $i }}][tarif_kontribusi]" value="{{ $tarifKontribusiVal }}"></div>
                <div class="fg"><label class="fl">Jumlah Kontribusi (otomatis)</label><input type="text" data-jml-kontribusi readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>
                <div class="fg"><label class="fl">Volume MOOC</label><input type="number" step="1" min="0" data-vol-mooc name="peserta[{{ $i }}][volume_mooc]" value="{{ $volMoocVal }}"></div>
                <div class="fg"><label class="fl">Tarif MOOC (Rp)</label><input type="number" step="0.01" min="0" data-tarif-mooc name="peserta[{{ $i }}][tarif_mooc]" value="{{ $tarifMoocVal }}"></div>
                <div class="fg"><label class="fl">Jumlah MOOC (otomatis)</label><input type="text" data-jml-mooc readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>
                <div class="fg span2"><label class="fl">Subtotal Kontribusi (otomatis)</label><input type="text" data-sub-kontribusi readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>
            </div>
        </div>

        <div class="fg span2 kd-sec kd-sec-perjalanan">
            <div class="form-grid" style="margin-top:0;">
                <div class="fg"><label class="fl">Jumlah Hari (Uang Harian)</label><input type="number" step="1" min="0" data-hari-uh name="peserta[{{ $i }}][hari_uh]" value="{{ $hariUhVal }}"></div>
                <div class="fg"><label class="fl">Tarif Uang Harian (Rp)</label><input type="number" step="0.01" min="0" data-tarif-uh name="peserta[{{ $i }}][tarif_uh]" value="{{ $tarifUhVal }}"></div>
                <div class="fg"><label class="fl">Jumlah Uang Harian (otomatis)</label><input type="text" data-jml-harian readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>
                <div class="fg"><label class="fl">Volume Akomodasi (malam)</label><input type="number" step="1" min="0" data-vol-akomodasi name="peserta[{{ $i }}][volume_akomodasi]" value="{{ $volAkomodasiVal }}"></div>
                <div class="fg"><label class="fl">Tarif Akomodasi (Rp)</label><input type="number" step="0.01" min="0" data-tarif-akomodasi name="peserta[{{ $i }}][tarif_akomodasi]" value="{{ $tarifAkomodasiVal }}"></div>
                <div class="fg"><label class="fl">Jumlah Akomodasi (otomatis)</label><input type="text" data-jml-akomodasi readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>
                <div class="fg"><label class="fl">Jumlah Hari (Uang Saku)</label><input type="number" step="1" min="0" data-hari-saku name="peserta[{{ $i }}][hari_saku]" value="{{ $hariSakuVal }}"></div>
                <div class="fg"><label class="fl">Tarif Uang Saku (Rp)</label><input type="number" step="0.01" min="0" data-tarif-saku name="peserta[{{ $i }}][tarif_saku]" value="{{ $tarifSakuVal }}"></div>
                <div class="fg"><label class="fl">Jumlah Uang Saku (otomatis)</label><input type="text" data-jml-saku readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>
                <div class="fg"><label class="fl">Transport/BBM/Tiket (Rp, at-cost)</label><input type="number" step="0.01" min="0" data-transport name="peserta[{{ $i }}][transport]" value="{{ $transportVal }}"></div>
                <div class="fg span2"><label class="fl">Subtotal Perjalanan (otomatis)</label><input type="text" data-sub-perjalanan readonly value="Rp 0" style="background:#f8fafc;font-weight:700;"></div>
            </div>
        </div>
    </div>
</div>
