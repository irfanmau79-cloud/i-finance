@php
    /**
     * Kotak centang "PPTK Sebagai Penerima".
     *
     * $aktif    : tersimpan menyala?
     * $rekening : rekening manual tersimpan (dipakai bila PPTK belum punya
     *             rekening di Data Pegawai)
     * $catatan  : penjelasan khas modulnya
     */
@endphp
<div class="fg span2" data-pptk-wrap style="margin-top:12px;">
    <label class="komp-chip" style="display:inline-flex;">
        <input type="checkbox" name="pptk_penerima" value="1" data-pptk-centang @checked($aktif)>
        <span class="komp-box"><svg viewBox="0 0 16 16" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4.5"/></svg></span>
        <span class="komp-txt">PPTK Sebagai Penerima</span>
    </label>
    <div class="sub" style="margin-top:4px;">{{ $catatan }}</div>

    <div data-pptk-rekening style="margin-top:8px;" @unless ($aktif) hidden @endunless>
        <label class="fl" for="pptk_rekening">No. Rekening PPTK</label>
        <input type="text" id="pptk_rekening" name="pptk_rekening" inputmode="numeric"
               placeholder="Kosongkan bila rekening PPTK sudah ada di Data Pegawai"
               value="{{ old('pptk_rekening', $rekening) }}">
        <div class="sub" style="margin-top:4px;">
            Nama PPTK diambil dari pelimpahan sub kegiatan - sumber yang sama dengan tanda tangan PPTK di dokumen,
            jadi tidak perlu (dan tidak bisa) diketik di sini. Rekeningnya dibaca dari Data Pegawai; isi di sini
            hanya bila di sana masih kosong.
        </div>
    </div>
</div>

@once
<script>
(function () {
    const wrap = document.querySelector('[data-pptk-wrap]');
    if (! wrap) return;

    const centang = wrap.querySelector('[data-pptk-centang]');
    const kotakRekening = wrap.querySelector('[data-pptk-rekening]');

    function terapkan() {
        kotakRekening.hidden = ! centang.checked;
    }

    centang.addEventListener('change', terapkan);
    terapkan();
})();
</script>
@endonce
