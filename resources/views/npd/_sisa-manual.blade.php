{{--
    Isian Sisa Anggaran manual — mengikuti pola GAS (index.html:
    toggleManualSisa/onManualSisa): sebuah centang "Isi sisa anggaran manual"
    yang, saat dicentang, memunculkan isian angka dan LANGSUNG mengganti angka
    Sisa Anggaran yang tampil di kotak mata anggaran. Melepas centangnya
    mengembalikan angka sistem.

    SATU BEDA yang disengaja dari GAS: di GAS angka ketikan itu ikut menjadi
    batas nominal NPD (payload.sisaAnggaran dipakai buatNPD untuk menolak
    pencairan yang melebihi). Di sini TIDAK - angka sistem tetap satu-satunya
    batas, dan angka ketikan hanya dicetak di PDF (lihat Npd::sisaAnggaranCetak
    dan NpdSisaAnggaranManualTest). Karena itu angka sistem tetap ditampilkan
    berdampingan, tidak ditimpa diam-diam.

    Dipakai bersama oleh kelima jenis NPD. Dibuka untuk tahun anggaran ini
    lewat config('anggaran.sisa_manual_npd'); saat dikunci kembali, isiannya
    hilang dari formulir dan nilai NPD lama tetap dipertahankan (lihat
    Npd::sisaManualDariInput).
--}}
@if (\App\Models\Npd::bolehInputSisaManual())
    @php($sisaManualAwal = old('sisa_anggaran_manual', $npdEdit?->sisa_anggaran_manual))
    <div class="sisa-manual" data-sisa-manual @if (! ($paneSisaManualSelalu ?? false)) hidden @endif>
        <label class="sisa-manual-chk">
            <input type="checkbox" data-sisa-toggle @checked($sisaManualAwal !== null)>
            <span class="komp-box"><svg viewBox="0 0 16 16" aria-hidden="true"><polyline points="3,8.5 6.5,12 13,4.5"/></svg></span>
            <span>Isi sisa anggaran manual</span>
        </label>

        <div class="sisa-manual-isi" data-sisa-wrap @if ($sisaManualAwal === null) hidden @endif>
            <input type="text" data-rupiah data-sisa-input id="sisa_anggaran_manual" name="sisa_anggaran_manual"
                   placeholder="ketik sisa anggaran" value="{{ $sisaManualAwal }}">
            <div class="sisa-manual-nota">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>
                    Angka ini <strong>hanya</strong> dicetak pada kolom &ldquo;SISA ANGGARAN&rdquo; di PDF NPD.
                    Batas nominal NPD dan seluruh laporan tetap memakai angka sistem.
                </span>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const wrap = document.querySelector('[data-sisa-manual]');
        if (! wrap) return;

        const chk = wrap.querySelector('[data-sisa-toggle]');
        const isiWrap = wrap.querySelector('[data-sisa-wrap]');
        const isi = wrap.querySelector('[data-sisa-input]');
        const sisaEl = document.getElementById('ma-sisa');
        const detail = document.getElementById('ma-detail');

        // Angka sistem terakhir yang ditulis halaman ke kotak mata anggaran.
        // Direkam lewat pengamat, bukan dihitung ulang di sini, supaya kelima
        // formulir NPD tidak perlu tahu apa-apa tentang komponen ini.
        let teksSistem = sisaEl ? sisaEl.textContent : '';

        function nilai() {
            return window.InputRupiah ? window.InputRupiah.nilai(isi) : (parseFloat(isi.value) || 0);
        }

        function manualAktif() {
            return chk.checked && isi.value.trim() !== '';
        }

        function gambarSisa() {
            if (! sisaEl) return;

            if (manualAktif()) {
                // Seperti GAS: angkanya langsung terlihat menggantikan yang
                // tampil. Bedanya angka sistem tetap ditulis di bawahnya,
                // karena di sini dialah yang masih menjadi batas nominal.
                sisaEl.innerHTML = 'Rp ' + nilai().toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    + '<span class="sisa-manual-tanda">manual &middot; dicetak di PDF</span>'
                    + '<span class="sisa-manual-sistem">Sistem: ' + teksSistem + '</span>';
            } else if (sisaEl.textContent !== teksSistem) {
                sisaEl.textContent = teksSistem;
            }
        }

        /**
         * Rekam angka sistem yang baru ditulis halaman. Tulisan sendiri
         * dikenali dari penandanya - BUKAN dari bendera sinkron, karena
         * pengamat mutasi berjalan belakangan - dan perubahan yang tidak
         * berarti diabaikan supaya keduanya tidak saling memicu.
         */
        function rekamSistem() {
            if (! sisaEl) return;
            if (sisaEl.querySelector('.sisa-manual-tanda')) return;
            if (sisaEl.textContent === teksSistem) return;
            teksSistem = sisaEl.textContent;
            gambarSisa();
        }

        const ikutDetail = () => {
            if (detail) wrap.hidden = detail.style.display === 'none';
        };

        if (sisaEl) {
            new MutationObserver(rekamSistem).observe(sisaEl, { childList: true, characterData: true, subtree: true });
        }

        // Centangnya baru berguna setelah mata anggarannya dipilih - sama
        // seperti GAS yang memunculkannya bersama kotak Pagu/Sisa.
        if (detail) {
            new MutationObserver(ikutDetail).observe(detail, { attributes: true, attributeFilter: ['style'] });
            ikutDetail();
        }

        // Pengamat mutasi baru berjalan seusai tugas yang sedang berlangsung.
        // Event change dari dropdown mata anggaran menggelembung ke sini
        // SETELAH penanganan halaman selesai, jadi keduanya ikut seketika -
        // pengamat di atas tinggal jadi jaring pengaman untuk perubahan yang
        // tidak lewat event.
        const form = wrap.closest('form');
        if (form) {
            form.addEventListener('change', function () {
                ikutDetail();
                rekamSistem();
            });
        }

        chk.addEventListener('change', function () {
            isiWrap.hidden = ! chk.checked;
            if (chk.checked) {
                isi.focus();
            } else {
                isi.value = '';
                isi.dispatchEvent(new Event('input', { bubbles: true }));
            }
            gambarSisa();
        });

        isi.addEventListener('input', gambarSisa);
        gambarSisa();
    })();
    </script>
@endif
