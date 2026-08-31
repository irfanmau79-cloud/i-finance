{{--
    Gerbang privasi Data Gaji & Tunjangan.

    Ditampilkan untuk role di luar config('gaji_tunjangan.role_data_penuh').
    Penyaringan datanya sendiri dilakukan di server (GajiTunjanganService),
    panel ini hanya antarmukanya - jadi menyembunyikan atau melewati form ini
    di peramban tidak membuka data pegawai lain.
--}}
<div class="dash-card" style="max-width:520px;margin:0 auto;">
    <h3>Verifikasi Identitas</h3>
    <div class="sub">
        Data penghasilan bersifat pribadi. Masukkan NIP dan 4 digit terakhir nomor
        rekening Anda untuk melihat data Anda sendiri.
    </div>

    @if ($errors->any())
        <div class="err-box" style="display:block;margin-top:12px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('gaji-tunjangan.verifikasi') }}" style="margin-top:6px;">
        @csrf

        <label class="fl" for="gt-nip">NIP</label>
        <input type="text" id="gt-nip" name="nip" inputmode="numeric" autocomplete="off"
               placeholder="Contoh: 196611041990032003" value="{{ old('nip') }}" required>

        <label class="fl" for="gt-rek4">4 Digit Terakhir Nomor Rekening</label>
        <input type="text" id="gt-rek4" name="rek4" inputmode="numeric" maxlength="4"
               autocomplete="off" placeholder="Contoh: 2100" value="{{ old('rek4') }}" required>

        <button class="btn prim" style="margin-top:16px;">Tampilkan Data Saya</button>
    </form>

    <div class="sub" style="margin-top:14px;margin-bottom:0;">
        Verifikasi berlaku selama sesi ini, jadi berpindah antar sub-menu tidak
        perlu mengetik ulang.
    </div>
</div>
