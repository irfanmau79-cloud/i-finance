{{--
    INPUT RUPIAH — isian angka yang dipisah titik ribuan sambil diketik.

    Dipasang otomatis pada tiap <input data-rupiah>. Yang dilihat pengguna
    "1.500.000" (jelas jutaan, bukan ratusan ribu), sedangkan yang TERKIRIM ke
    server tetap angka polos "1500000": `name` isian dipindahkan ke sebuah
    <input type="hidden"> yang nilainya diperbarui tiap ketukan. Jadi tidak ada
    penyeragaman saat submit yang bisa terlewat, dan aturan validasi numerik di
    FormRequest tidak perlu diubah sama sekali.

    Kode halaman yang butuh angkanya memakai `window.InputRupiah.nilai(el)` —
    bukan `el.value` yang sudah berformat. Fungsi itu membaca ulang isiannya
    setiap dipanggil, jadi hasilnya benar walau pemanggilnya kebetulan
    mendengarkan event 'input' lebih dulu daripada komponen ini.

    Desimal memakai koma seperti kebiasaan menulis rupiah: "1.500.000,50"
    dikirim sebagai "1500000.50".
--}}
<script>
(function () {
    'use strict';

    var TERPASANG = 'rupiahSiap';

    /** "1.500.000,50" -> "1500000.50" ; "" bila tidak ada angka sama sekali. */
    function keAngka(teks) {
        var bersih = String(teks == null ? '' : teks).replace(/[^\d,.-]/g, '');

        // Titik dianggap pemisah ribuan, koma pemisah desimal. Nilai dari
        // server datang dengan titik desimal ("1500000.50"), jadi titik yang
        // diikuti 1-2 angka DI AKHIR diperlakukan sebagai desimal.
        if (! bersih.includes(',') && /\.\d{1,2}$/.test(bersih)) {
            bersih = bersih.replace(/\.(\d{1,2})$/, ',$1');
        }

        var bagian = bersih.replace(/\./g, '').split(',');
        var bulat = (bagian[0] || '').replace(/\D/g, '');
        var desimal = bagian.length > 1 ? (bagian[1] || '').replace(/\D/g, '').slice(0, 2) : null;

        if (bulat === '' && (desimal === null || desimal === '')) return '';

        return desimal === null ? bulat : (bulat || '0') + '.' + desimal;
    }

    /** "1500000.5" -> "1.500.000,5" (apa adanya, tanpa membulatkan ketikan). */
    function keTampilan(angka) {
        if (angka === '') return '';
        var bagian = String(angka).split('.');
        var bulat = bagian[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return bagian.length > 1 ? bulat + ',' + bagian[1] : bulat;
    }

    function pasangSatu(el) {
        if (! el || el.dataset[TERPASANG] === '1') return;
        el.dataset[TERPASANG] = '1';

        // type=number menolak titik/koma, jadi isiannya jadi teks biasa dengan
        // papan tik angka di ponsel. Batas nilainya tetap ditegakkan server.
        el.type = 'text';
        el.setAttribute('inputmode', 'decimal');
        el.autocomplete = 'off';
        el.removeAttribute('step');
        el.removeAttribute('min');
        el.removeAttribute('max');

        var nama = el.getAttribute('name');
        var tersembunyi = null;

        if (nama) {
            tersembunyi = document.createElement('input');
            tersembunyi.type = 'hidden';
            tersembunyi.name = nama;
            el.removeAttribute('name');
            el.parentNode.insertBefore(tersembunyi, el.nextSibling);
        }

        function simpan(angka) {
            el.dataset.nilai = angka;
            if (tersembunyi) tersembunyi.value = angka;
        }

        function segarkan(jagaKursor) {
            var angka = keAngka(el.value);
            var tampilan = keTampilan(angka);

            if (jagaKursor) {
                // Kursor dikembalikan berdasarkan JUMLAH ANGKA di kirinya,
                // bukan posisi karakter: titik ribuan yang muncul/hilang saat
                // mengetik tidak boleh menggeser tempat mengetik.
                var posisi = el.selectionStart || 0;
                var angkaKiri = (el.value.slice(0, posisi).match(/[\d,]/g) || []).length;
                el.value = tampilan;
                var lewat = 0;
                var baru = tampilan.length;
                for (var i = 0; i < tampilan.length; i++) {
                    if (/[\d,]/.test(tampilan[i])) lewat++;
                    if (lewat >= angkaKiri) { baru = i + 1; break; }
                }
                if (angkaKiri === 0) baru = 0;
                try { el.setSelectionRange(baru, baru); } catch (e) {}
            } else {
                // ",00" dari nilai peladen dibuang: yang ditulis orang di
                // dokumen keuangan kantor ini bilangan bulat rupiah.
                if (/\.0+$/.test(angka)) {
                    angka = angka.replace(/\.0+$/, '');
                    tampilan = keTampilan(angka);
                }
                el.value = tampilan;
            }

            simpan(angka);
        }

        el.addEventListener('input', function () { segarkan(true); });
        el.addEventListener('blur', function () { segarkan(false); });
        if (el.form) el.form.addEventListener('reset', function () { setTimeout(function () { segarkan(false); }, 0); });

        segarkan(false);
    }

    function pasang(akar) {
        var ruang = akar || document;
        if (ruang.matches && ruang.matches('input[data-rupiah]')) pasangSatu(ruang);
        if (ruang.querySelectorAll) {
            Array.prototype.forEach.call(ruang.querySelectorAll('input[data-rupiah]'), pasangSatu);
        }
    }

    function mulai() {
        pasang(document);
        // Baris yang ditumbuhkan lewat JavaScript ikut terpasang sendiri.
        new MutationObserver(function (daftar) {
            for (var i = 0; i < daftar.length; i++) {
                var tambahan = daftar[i].addedNodes;
                for (var j = 0; j < tambahan.length; j++) {
                    if (tambahan[j].nodeType === 1) pasang(tambahan[j]);
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    window.InputRupiah = {
        pasang: pasang,
        pasangSatu: pasangSatu,
        /** Angka dari sebuah isian rupiah; 0 bila kosong. Selalu dibaca ulang
            dari isiannya supaya tidak bergantung pada urutan pendengar event. */
        nilai: function (el) { return el ? (parseFloat(keAngka(el.value)) || 0) : 0; },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mulai);
    } else {
        mulai();
    }
})();
</script>
