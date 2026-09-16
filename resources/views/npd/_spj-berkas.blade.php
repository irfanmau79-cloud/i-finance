{{--
    Daftar berkas SPJ satu NPD, plus unggah & hapus. Dipakai halaman detail
    NPD; Inventarisasi SPJ memakai daftar yang sama lewat JSON rincian.

    $npd            Npd yang sedang dilihat
    $bolehKelola    true bila pengguna boleh mengunggah/menghapus (Pengawas tidak)
--}}
<h3 style="margin-top:22px;">Berkas SPJ <span class="spj-ops">opsional</span></h3>
<div class="dash-card" style="box-shadow:none;border:1px solid var(--line);">
    <div class="sub" style="margin-bottom:12px;">
        Hasil pindaian SPJ untuk NPD ini. Berkas di sini <strong>ikut tercetak</strong> pada
        &ldquo;Cetak Semua (1 Berkas)&rdquo;, di urutan paling belakang. Menyimpan SPJ belum
        diwajibkan &mdash; NPD tanpa berkas tetap berjalan seperti biasa.
    </div>

    @if ($bolehKelola)
        <form method="POST" action="{{ route('npd.spj-berkas.store', $npd) }}"
              enctype="multipart/form-data" class="spj-unggah" style="margin-bottom:14px;">
            @csrf
            <label class="spj-pilih" for="spj-berkas-input">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <span>Upload SPJ</span>
            </label>
            <input type="file" id="spj-berkas-input" name="spj[]" accept="application/pdf,image/jpeg,.pdf,.jpg,.jpeg" multiple hidden>
            <span class="spj-pilihan" id="spj-berkas-pilihan">Belum ada berkas dipilih</span>
            <button type="submit" class="btn prim" id="spj-berkas-kirim" disabled>Simpan Berkas</button>
        </form>
        @error('spj')<div class="err-box" style="display:block;">{{ $message }}</div>@enderror
        @error('spj.*')<div class="err-box" style="display:block;">{{ $message }}</div>@enderror
    @endif

    @forelse ($npd->spjBerkas as $nomor => $berkas)
        <div class="spj-baris">
            <span class="spj-no">SPJ {{ $nomor + 1 }}</span>
            <a class="spj-tautan" href="{{ route('npd.spj-berkas.show', [$npd, $berkas]) }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <span class="spj-nama">{{ $berkas->nama_asli }}</span>
            </a>
            <span class="spj-meta">{{ $berkas->pdf() ? 'PDF' : 'JPG' }} &middot; {{ $berkas->ukuranTerbaca() }}</span>
            <span class="spj-meta">{{ $berkas->created_at?->format('d-m-Y H:i') }}</span>
            @if ($bolehKelola)
                {{-- Hapus lewat <details>, pola yang sama dengan hapus NPD di
                     tabel: konfirmasinya muncul di halaman, bukan lewat
                     confirm() bawaan peramban. --}}
                <details class="spj-hapus">
                    <summary class="ic-btn danger" title="Hapus berkas ini">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </summary>
                    <form method="POST" action="{{ route('npd.spj-berkas.destroy', [$npd, $berkas]) }}" class="spj-hapus-form">
                        @csrf
                        @method('DELETE')
                        <div class="sub" style="margin:0 0 8px;">Hapus berkas ini? Berkasnya hilang permanen.</div>
                        <button class="btn danger" type="submit">Ya, hapus</button>
                    </form>
                </details>
            @endif
        </div>
    @empty
        <div class="sub" style="color:var(--mut);">Belum ada berkas SPJ yang diunggah.</div>
    @endforelse
</div>

@if ($bolehKelola)
<script>
(function () {
    var input = document.getElementById('spj-berkas-input');
    var label = document.getElementById('spj-berkas-pilihan');
    var kirim = document.getElementById('spj-berkas-kirim');
    if (! input || ! label || ! kirim) return;

    input.addEventListener('change', function () {
        var n = input.files ? input.files.length : 0;
        // Tombol simpan sengaja mati sampai ada berkas dipilih: kiriman
        // kosong hanya akan kembali dengan pesan galat tanpa alasan jelas.
        kirim.disabled = n === 0;
        label.textContent = n === 0
            ? 'Belum ada berkas dipilih'
            : (n === 1 ? input.files[0].name : n + ' berkas dipilih');
    });
})();
</script>
@endif
