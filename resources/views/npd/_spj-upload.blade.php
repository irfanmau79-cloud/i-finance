{{--
    Unggah SPJ pada formulir NPD - dipakai kelima jenis NPD, disisipkan di
    bagian bawah langkah "Detail NPD".

    OPSIONAL dan sengaja tidak ikut validasi wajib apa pun: NPD tanpa SPJ
    tetap tersimpan, tetap bisa diajukan, dan tetap bisa dicetak. Ini alat
    bantu penyimpanan berkas, bukan syarat baru di alur kerja.

    Pada mode EDIT, berkas yang sudah ada ditampilkan di bawah - menambah
    berkas baru TIDAK menimpa yang lama (SPJ sering dipindai per lembar).
    Menghapus lembar dilakukan dari halaman detail NPD atau Inventarisasi SPJ,
    bukan dari sini: tombol hapus di dalam formulir yang belum disimpan
    membuat pemakai tidak tahu apakah hapusnya sudah berlaku atau menunggu
    tombol Simpan.
--}}
@php($spjTerunggah = $npdEdit?->spjBerkas ?? collect())

<h3 style="margin-top:22px;">Berkas SPJ <span class="spj-ops">opsional</span></h3>
<div class="sub">
    Simpan hasil pindaian SPJ di sini (PDF atau JPG, maksimal
    {{ \App\Services\SpjBerkasService::MAKS_UKURAN_KB / 1024 }} MB per berkas,
    {{ \App\Services\SpjBerkasService::MAKS_BERKAS }} berkas per NPD). Boleh dikosongkan
    &mdash; NPD tetap tersimpan tanpa berkas SPJ. Berkas yang diunggah bisa dilihat di
    halaman detail NPD dan di modul Inventarisasi SPJ, dan ikut tercetak pada
    &ldquo;Cetak Semua (1 Berkas)&rdquo;.
</div>

<div class="spj-unggah">
    <label class="spj-pilih" for="spj-input">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <span>Upload SPJ</span>
    </label>
    <input type="file" id="spj-input" name="spj[]" accept="application/pdf,image/jpeg,.pdf,.jpg,.jpeg" multiple hidden>
    <span class="spj-pilihan" id="spj-pilihan">Belum ada berkas dipilih</span>
</div>

@error('spj')<div class="err-box" style="display:block;">{{ $message }}</div>@enderror
@error('spj.*')<div class="err-box" style="display:block;">{{ $message }}</div>@enderror

@if ($spjTerunggah->isNotEmpty())
    <div class="spj-daftar">
        <div class="spj-daftar-judul">{{ $spjTerunggah->count() }} berkas sudah tersimpan</div>
        @foreach ($spjTerunggah as $berkas)
            <a class="spj-item" href="{{ route('npd.spj-berkas.show', [$npdEdit, $berkas]) }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <span class="spj-nama">{{ $berkas->nama_asli }}</span>
                <span class="spj-meta">{{ $berkas->ukuranTerbaca() }}</span>
            </a>
        @endforeach
    </div>
@endif

<script>
(function () {
    var input = document.getElementById('spj-input');
    var label = document.getElementById('spj-pilihan');
    if (! input || ! label) return;

    input.addEventListener('change', function () {
        var n = input.files ? input.files.length : 0;
        if (! n) {
            label.textContent = 'Belum ada berkas dipilih';
            return;
        }
        label.textContent = n === 1
            ? input.files[0].name
            : n + ' berkas dipilih';
    });
})();
</script>
