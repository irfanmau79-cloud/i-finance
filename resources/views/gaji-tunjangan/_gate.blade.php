{{--
    Gerbang privasi Data Gaji & Tunjangan - salinan panel #gt-gate di GAS.

    Ditampilkan untuk role di luar config('gaji_tunjangan.role_data_penuh').
    Penyaringan datanya sendiri dilakukan di server (GajiTunjanganService),
    panel ini hanya antarmukanya - jadi menyembunyikan atau melewati form ini
    di peramban tidak membuka data pegawai lain.
--}}
<form method="POST" action="{{ route('gaji-tunjangan.verifikasi') }}"
      style="flex:0 0 auto;margin:6px 0 4px;border:1px solid var(--line);background:#f8fafc;border-radius:12px;padding:16px 18px;max-width:640px;">
    @csrf

    <div style="font-weight:700;color:var(--navy);font-size:14px;margin-bottom:4px;">Verifikasi Identitas</div>
    <div class="sub" style="margin-bottom:12px;">
        Untuk menjaga privasi, data penghasilan hanya dapat dilihat oleh pegawai
        yang bersangkutan. Masukkan NIP dan 4 digit terakhir nomor rekening Anda.
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="gt-field" style="flex:1;min-width:220px;">
            <label for="gt-gate-nip">NIP</label>
            <input id="gt-gate-nip" class="gt-inp" type="text" name="nip" inputmode="numeric"
                   autocomplete="off" placeholder="Contoh: 199907022021021001" value="{{ old('nip') }}">
        </div>
        <div class="gt-field" style="width:180px;">
            <label for="gt-gate-rek">4 Digit Akhir Rekening</label>
            <input id="gt-gate-rek" class="gt-inp" type="text" name="rek4" inputmode="numeric"
                   maxlength="4" autocomplete="off" placeholder="Contoh: 1234" value="{{ old('rek4') }}">
        </div>
        <button class="gt-btn-tampil" type="submit">Tampilkan</button>
    </div>

    <div style="margin-top:10px;font-size:12.5px;color:#c0392b;">{{ $errors->first() }}</div>
</form>
