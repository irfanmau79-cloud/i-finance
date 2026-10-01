@php
    /**
     * Isian Uraian (Keterangan Lampiran) berikut pratinjaunya.
     *
     * Dua mode:
     *  - OTOMATIS (bawaan): kotaknya menampilkan Uraian yang akan tercetak,
     *    dirakit ulang tiap kali isian di atasnya berubah. Yang DIKIRIM ke
     *    server tetap kosong, jadi perilakunya persis seperti sebelum ada
     *    pratinjau ini - teksnya dirangkai lagi saat mencetak, mengikuti data
     *    terakhir. Ini penting supaya NPD yang tidak disentuh uraiannya tidak
     *    diam-diam membeku pada kalimat saat ia dibuat.
     *  - MANUAL: begitu petugas mengetik di kotaknya, teks itu yang disimpan
     *    dan tidak lagi ikut berubah walau data di atas diubah.
     *
     * $jenis  : 'pd' | 'tr' | 'kd'
     * $nilai  : keterangan_lampiran yang sudah tersimpan (null = mode otomatis)
     * $catatan: keterangan kecil di bawah kotak, beda tiap modul
     */
    $jenis = $jenis ?? 'pd';
    $nilai = old('keterangan_lampiran', $nilai ?? null);
    $mode = old('keterangan_mode', filled($nilai) ? 'manual' : 'otomatis');
@endphp

<div class="fg span2" data-ket-wrap data-ket-jenis="{{ $jenis }}" data-ket-url="{{ route('npd.keterangan-lampiran.pratinjau') }}">
    <label class="fl" for="keterangan_lampiran">Keterangan Lampiran (opsional)</label>
    <textarea id="keterangan_lampiran" name="keterangan_lampiran" rows="3" data-ket-teks
              placeholder="Terisi otomatis dari data di atas. Boleh diubah bila perlu.">{{ $nilai }}</textarea>
    <input type="hidden" name="keterangan_mode" value="{{ $mode }}" data-ket-mode>

    <div class="sub" style="margin-top:4px;display:flex;flex-wrap:wrap;gap:8px;align-items:baseline;justify-content:space-between;">
        <span data-ket-status></span>
        <button type="button" class="btn" style="padding:2px 10px;font-size:11px;" data-ket-reset hidden>
            Kembalikan ke otomatis
        </button>
    </div>
    @if (! empty($catatan))
        <div class="sub" style="margin-top:4px;">{{ $catatan }}</div>
    @endif
</div>

@once
<script>
(function () {
    const wrap = document.querySelector('[data-ket-wrap]');
    if (! wrap) return;

    const form = wrap.closest('form');
    const teks = wrap.querySelector('[data-ket-teks]');
    const mode = wrap.querySelector('[data-ket-mode]');
    const status = wrap.querySelector('[data-ket-status]');
    const tombolReset = wrap.querySelector('[data-ket-reset]');
    const jenis = wrap.dataset.ketJenis;
    const url = wrap.dataset.ketUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    // Isian yang ikut menentukan bunyi Uraian, per jenis NPD. Sisanya tidak
    // dikirim - termasuk berkas unggahan SPJ, yang kalau ikut akan terunggah
    // ulang tiap kali ada yang diketik.
    const DIPAKAI = {
        pd: /^(surat_perintah_id|uraian_sp|tanggal_berangkat|tanggal_pulang|penerima_index|tim\[)/,
        tr: /^(npd_induk_id|penerima_index|tim\[)/,
        kd: /^(mode|nama_pelatihan|tanggal_mulai|tanggal_selesai|penerima_index|peserta\[|penerima_transfer\[)/,
    }[jenis];

    let timer = null;
    let urutan = 0;

    function manual() {
        return mode.value === 'manual';
    }

    function perbaruiTampilan() {
        tombolReset.hidden = ! manual();
        status.textContent = manual()
            ? 'Mode manual - uraian ini tidak lagi mengikuti perubahan data di atas.'
            : 'Mengikuti data di atas secara otomatis. Mulai mengetik untuk menulis sendiri.';
    }

    function muatan() {
        const data = new FormData();
        data.append('jenis', jenis);

        for (const [nama, nilai] of new FormData(form).entries()) {
            // Lewati berkas: pratinjau hanya butuh teks.
            if (nilai instanceof File) continue;
            if (DIPAKAI.test(nama)) data.append(nama, nilai);
        }

        return data;
    }

    async function segarkan() {
        if (manual()) return;

        const giliran = ++urutan;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: muatan(),
            });

            if (! res.ok) return;

            const json = await res.json();

            // Balasan yang datang terlambat diabaikan supaya tidak menimpa
            // hasil permintaan yang lebih baru.
            if (giliran !== urutan || manual()) return;

            teks.value = json.teks ?? '';
        } catch (e) {
            // Pratinjau gagal bukan alasan menahan pengisian formulir -
            // kotaknya dibiarkan apa adanya dan server tetap merangkai
            // uraiannya sendiri saat mencetak.
        }
    }

    function jadwalkan() {
        clearTimeout(timer);
        timer = setTimeout(segarkan, 400);
    }

    // Ketikan petugas di kotak ini yang menandai peralihan ke mode manual.
    // Pengisian otomatis di atas memakai teks.value langsung, yang memang
    // tidak memicu event 'input'.
    teks.addEventListener('input', function () {
        if (manual()) return;
        mode.value = 'manual';
        perbaruiTampilan();
    });

    tombolReset.addEventListener('click', function () {
        mode.value = 'otomatis';
        perbaruiTampilan();
        segarkan();
    });

    form.addEventListener('input', function (e) {
        if (e.target === teks) return;
        jadwalkan();
    });
    form.addEventListener('change', function (e) {
        if (e.target === teks) return;
        jadwalkan();
    });

    // Baris tim/peserta dibangun oleh skrip formulir yang jalan SETELAH blok
    // ini, dan membangunnya tidak memicu event 'input'. Jadi pratinjaunya
    // disegarkan sekali lagi setelah halaman benar-benar siap - penting pada
    // mode sunting, yang memulihkan baris-baris itu dari data tersimpan.
    window.addEventListener('load', jadwalkan);

    perbaruiTampilan();
    segarkan();
})();
</script>
@endonce
