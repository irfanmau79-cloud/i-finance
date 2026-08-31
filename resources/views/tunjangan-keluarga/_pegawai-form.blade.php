@php
    use App\Models\Pegawai;

    $p = $pegawai ?? null;
@endphp

{{-- Satu grid yang mengisi lebar kartunya: jumlah kolomnya mengikuti ruang
     yang tersedia (lihat .form-grid-auto), jadi di layar lebar formulirnya
     tidak lagi menggantung di kiri, dan di layar sempit tetap menumpuk satu
     kolom. Nama dan Jabatan diberi dua kolom karena isinya panjang. --}}
<div class="form-grid-auto">
    <div class="fg span2">
        <label class="fl" for="nama">Nama Pegawai</label>
        <input id="nama" name="nama" value="{{ old('nama', $p->nama ?? '') }}" placeholder="Nama lengkap" required>
    </div>
    <div class="fg">
        <label class="fl" for="nip">NIP</label>
        <input id="nip" name="nip" value="{{ old('nip', $p->nip ?? '') }}" placeholder="Nomor Induk Pegawai" inputmode="numeric" required>
    </div>

    <div class="fg span2">
        <label class="fl" for="jabatan">Jabatan</label>
        <input id="jabatan" name="jabatan" value="{{ old('jabatan', $p->jabatan ?? '') }}" placeholder="Jabatan" required>
    </div>
    <div class="fg">
        <label class="fl" for="bidang">Unit Kerja</label>
        <input id="bidang" name="bidang" value="{{ old('bidang', $p->bidang ?? '') }}" placeholder="Contoh: Inspektur Pembantu I" required>
    </div>

    <div class="fg">
        <label class="fl" for="golongan">Golongan</label>
        <input id="golongan" name="golongan" value="{{ old('golongan', $p->golongan ?? '') }}" placeholder="Contoh: III/c">
    </div>
    <div class="fg">
        <label class="fl" for="pangkat">Pangkat</label>
        <input id="pangkat" name="pangkat" value="{{ old('pangkat', $p->pangkat ?? '') }}" placeholder="Contoh: Penata">
    </div>
    <div class="fg">
        <label class="fl" for="periode_kgb">Periode KGB</label>
        <input id="periode_kgb" name="periode_kgb" value="{{ old('periode_kgb', $p->periode_kgb ?? '') }}" placeholder="Contoh: April 2026">
        <div class="sub" style="margin-top:4px;">Ditulis bebas sesuai berkas kepegawaian.</div>
    </div>

    <div class="fg">
        <label class="fl" for="status_kepegawaian">Status Kepegawaian</label>
        <select id="status_kepegawaian" name="status_kepegawaian" required>
            @foreach (Pegawai::STATUS_KEPEGAWAIAN as $opsi)
                <option value="{{ $opsi }}" @selected(old('status_kepegawaian', $p->status_kepegawaian ?? Pegawai::STATUS_PNS) === $opsi)>{{ $opsi }}</option>
            @endforeach
        </select>
        <div class="sub" style="margin-top:4px;">PPPK Paruh Waktu tidak muncul di Data Tunjangan Keluarga.</div>
    </div>
    <div class="fg">
        <label class="fl" for="rekening">Rekening</label>
        <input id="rekening" name="rekening" value="{{ old('rekening', $p->rekening ?? '') }}" placeholder="Opsional" inputmode="numeric">
    </div>
    <div class="fg">
        <label class="fl" for="nomor_handphone">Nomor Handphone</label>
        <input id="nomor_handphone" name="nomor_handphone" value="{{ old('nomor_handphone', $p->nomor_handphone ?? '') }}" placeholder="Contoh: 081234567890" inputmode="tel">
        <div class="sub" style="margin-top:4px;">Dipakai fitur Kirim Notifikasi WhatsApp di Data NPD. Boleh ditulis 08&hellip; atau +62&hellip;</div>
    </div>
    <div class="fg">
        <label class="fl" for="aktif">Status Aktif</label>
        <select id="aktif" name="aktif">
            <option value="1" @selected(old('aktif', ($p->aktif ?? true) ? '1' : '0') == '1')>Aktif</option>
            <option value="0" @selected(old('aktif', ($p->aktif ?? true) ? '1' : '0') == '0')>Tidak Aktif</option>
        </select>
    </div>
</div>
