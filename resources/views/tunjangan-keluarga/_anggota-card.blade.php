{{--
    Satu anggota keluarga pada formulir Perubahan Data: pasangan atau anak.

    Kartunya punya dua rupa. Rupa LIHAT menampilkan data yang sekarang
    tersimpan beserta tombol "Ubah Data"; rupa ISI adalah isiannya. Isian
    selalu ikut terkirim walau kartunya tidak dibuka - yang diajukan adalah
    susunan keluarga SELENGKAPNYA sesudah perubahan, jadi anggota yang tidak
    diubah harus tetap terbawa apa adanya.

    $nama   awalan atribut name, mis. "pasangan" atau "anak[0]"
    $id     awalan atribut id, mis. "pasangan" atau "anak-0"
    $judul  judul kartu
    $ikon   isi lingkaran di kepala kartu
    $data   nilai awal isian (data tersimpan, atau isian lama saat validasi gagal)
    $anak   true untuk kartu anak (punya Perpanjangan Kuliah & Keterangan)
    $buka   true bila kartu langsung tampil dalam rupa ISI
--}}
@php
    $terisi = filled($data['nama'] ?? null);
    $buka = $buka ?? false;
    $lahir = filled($data['tanggal_lahir'] ?? null)
        ? \Illuminate\Support\Carbon::parse($data['tanggal_lahir'])->format('d-m-Y')
        : null;
@endphp
<div class="fam-card tkf-kartu{{ $buka ? ' diubah' : '' }}" data-ubah-kartu @if ($anak) data-anak-card @endif>
    <div class="fam-head">
        <span class="fam-ic">{!! $ikon !!}</span> {{ $judul }}
        <span class="tkf-tanda">Diubah</span>
    </div>

    <div class="tkf-lihat" data-ubah-lihat @if ($buka) hidden @endif>
        @if ($terisi)
            <div class="tkf-nama">{{ $data['nama'] }}</div>
            <div class="tkf-lahir">Lahir {{ $lahir ?? '-' }}</div>
            <div class="tkf-lencana">
                <span class="badge {{ ! empty($data['status_tunjangan']) ? 'st-aktif' : 'st-diterima' }}">{{ ! empty($data['status_tunjangan']) ? 'Tunjangan' : 'Tanpa Tunjangan' }}</span>
                @if ($anak && ! empty($data['perpanjangan_kuliah']))
                    <span class="badge st-verifikasi">Perpanjangan Kuliah</span>
                @endif
            </div>
            @if ($anak && filled($data['keterangan'] ?? null))
                <div class="tkf-cat">{{ $data['keterangan'] }}</div>
            @endif
        @else
            <div class="tkf-kosong">Belum ada data.</div>
        @endif
        <button type="button" class="btn tkf-ubah" data-ubah-buka>{{ $terisi ? 'Ubah Data' : 'Isi Data' }}</button>
    </div>

    <div class="tkf-isi" data-ubah-isi @if (! $buka) hidden @endif>
        <label class="fl" for="{{ $id }}-nama">Nama {{ $anak ? 'Anak' : 'Suami/Istri' }}</label>
        <input id="{{ $id }}-nama" name="{{ $nama }}[nama]" value="{{ $data['nama'] ?? '' }}" placeholder="Nama lengkap" maxlength="150">
        <div class="form-grid2">
            <div>
                <label class="fl" for="{{ $id }}-tanggal-lahir">Tanggal Lahir</label>
                <input id="{{ $id }}-tanggal-lahir" type="date" name="{{ $nama }}[tanggal_lahir]" value="{{ $data['tanggal_lahir'] ?? '' }}">
            </div>
            <div>
                <label class="fl" for="{{ $id }}-status">Dapat Tunjangan?</label>
                <select id="{{ $id }}-status" name="{{ $nama }}[status_tunjangan]">
                    <option value="0" @selected(empty($data['status_tunjangan']))>Tidak</option>
                    <option value="1" @selected(! empty($data['status_tunjangan']))>Ya</option>
                </select>
            </div>
        </div>
        @if ($anak)
            <div class="form-grid2">
                <div>
                    <label class="fl" for="{{ $id }}-kuliah">Perpanjangan Kuliah?</label>
                    <select id="{{ $id }}-kuliah" name="{{ $nama }}[perpanjangan_kuliah]">
                        <option value="0" @selected(empty($data['perpanjangan_kuliah']))>Tidak</option>
                        <option value="1" @selected(! empty($data['perpanjangan_kuliah']))>Ya</option>
                    </select>
                </div>
                <div>
                    <label class="fl" for="{{ $id }}-keterangan">Keterangan</label>
                    <input id="{{ $id }}-keterangan" name="{{ $nama }}[keterangan]" value="{{ $data['keterangan'] ?? '' }}" placeholder="Opsional, cth: perpanjangan usia 21–25" maxlength="500">
                </div>
            </div>
        @endif
        <div class="tkf-kaki">
            <span>Kosongkan nama untuk menghapus anggota ini dari data.</span>
            <button type="button" class="btn" data-ubah-batal>Batal Ubah</button>
        </div>
    </div>
</div>
