{{--
    SELECT CARI — dropdown bawaan peramban yang bisa diketik untuk mencari.

    Dipasang otomatis pada tiap <select data-cari> di seluruh aplikasi, jadi
    sebuah halaman cukup menambahkan atribut `data-cari` pada <select>-nya —
    tidak perlu menyalin JavaScript per halaman lagi.

    <select> aslinya TIDAK diganti, hanya disembunyikan secara visual dan
    dipindahkan ke dalam pembungkus. Konsekuensinya semua yang sudah ada tetap
    jalan apa adanya: nilai tetap terkirim saat submit, `old()` tetap terpilih,
    validasi `required` bawaan peramban tetap berjalan, dan kode halaman yang
    membaca `select.value`, mengganti `select.innerHTML`, atau menyetel
    `select.disabled` / `select.hidden` tidak perlu diubah — perubahan itu
    diikuti lewat MutationObserver.

    Label baris kedua (mis. jabatan/bidang di bawah nama) diambil dari atribut
    `data-sub` pada <option>.
--}}
<script>
(function () {
    'use strict';

    var TERPASANG = 'scariSiap';

    function esc(teks) {
        var d = document.createElement('div');
        d.textContent = teks == null ? '' : teks;
        return d.innerHTML;
    }

    function pasangSatu(sel) {
        if (! sel || sel.dataset[TERPASANG] === '1') return;
        sel.dataset[TERPASANG] = '1';

        var wrap = document.createElement('div');
        wrap.className = 'scari';

        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'sc-inp';
        inp.autocomplete = 'off';
        inp.setAttribute('role', 'combobox');
        inp.setAttribute('aria-autocomplete', 'list');
        inp.setAttribute('aria-expanded', 'false');

        // Label yang tadinya menunjuk <select> dialihkan ke isian pencarian,
        // supaya klik pada label tetap memindahkan fokus ke tempat yang benar.
        if (sel.id) {
            inp.id = sel.id + '-cari';
            var label = document.querySelector('label[for="' + sel.id.replace(/"/g, '\\"') + '"]');
            if (label) label.setAttribute('for', inp.id);
        }

        var chev = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        chev.setAttribute('class', 'sc-chev');
        chev.setAttribute('viewBox', '0 0 24 24');
        chev.setAttribute('aria-hidden', 'true');
        var poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        poly.setAttribute('points', '6 9 12 15 18 9');
        chev.appendChild(poly);

        var drop = document.createElement('div');
        drop.className = 'sc-drop';
        drop.setAttribute('role', 'listbox');

        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(inp);
        wrap.appendChild(chev);
        wrap.appendChild(drop);
        wrap.appendChild(sel);

        var terbuka = false;
        var sorot = -1;
        var daftarKini = [];

        /** Teks pilihan yang sedang aktif; pilihan kosong dianggap "belum dipilih". */
        function labelTerpilih() {
            var o = sel.options[sel.selectedIndex];
            return (! o || o.value === '') ? '' : o.text;
        }

        function teksPlaceholder() {
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === '') return sel.options[i].text;
            }
            return sel.dataset.cariPlaceholder || 'Ketik untuk mencari…';
        }

        function saring() {
            var q = inp.value.trim().toLowerCase();
            var opsi = Array.prototype.slice.call(sel.options);
            if (! q || q === labelTerpilih().trim().toLowerCase()) return opsi;
            return opsi.filter(function (o) {
                return (o.text + ' ' + (o.dataset.sub || '')).toLowerCase().indexOf(q) >= 0;
            });
        }

        function gambar() {
            daftarKini = saring();
            if (! daftarKini.length) {
                drop.innerHTML = '<div class="sc-kosong">Tidak ditemukan</div>';
                return;
            }
            drop.innerHTML = daftarKini.map(function (o, i) {
                return '<div class="sc-item' + (o.selected ? ' terpilih' : '') + (i === sorot ? ' sorot' : '')
                    + (o.disabled ? ' mati' : '') + '" role="option" data-i="' + o.index + '">'
                    + esc(o.text)
                    + (o.dataset.sub ? '<span class="sc-sub">' + esc(o.dataset.sub) + '</span>' : '')
                    + '</div>';
            }).join('');
        }

        /* Daftar pilihan melayang di atas seluruh halaman (position:fixed),
           jadi letaknya harus dihitung sendiri. Kalau ruang di bawah isian
           tidak cukup, daftarnya dibalik ke atas. */
        function tempatkan() {
            var r = inp.getBoundingClientRect();
            // Lebarnya disetel lebih dulu: tinggi daftar bergantung padanya,
            // dan tinggi itulah yang menentukan daftarnya turun atau naik.
            drop.style.left = r.left + 'px';
            drop.style.width = r.width + 'px';
            var tinggi = Math.min(drop.scrollHeight + 2, 264);
            var ruangBawah = window.innerHeight - r.bottom;
            drop.style.top = (ruangBawah < tinggi + 8 && r.top > ruangBawah)
                ? Math.max(4, r.top - tinggi - 4) + 'px'
                : (r.bottom + 4) + 'px';
        }

        function buka() {
            if (sel.disabled) return;
            terbuka = true;
            sorot = -1;
            gambar();
            wrap.classList.add('buka');
            inp.setAttribute('aria-expanded', 'true');
            tempatkan();
        }

        function tutup() {
            terbuka = false;
            wrap.classList.remove('buka');
            inp.setAttribute('aria-expanded', 'false');
        }

        // capture:true supaya penggulingan di dalam pembungkus tabel atau
        // modal - bukan hanya jendela - ikut menggeser daftarnya.
        window.addEventListener('scroll', function () { if (terbuka) tempatkan(); }, true);
        window.addEventListener('resize', function () { if (terbuka) tempatkan(); });

        function pilih(index) {
            var o = sel.options[index];
            if (! o || o.disabled) return;
            sel.selectedIndex = index;
            tutup();
            inp.value = labelTerpilih();
            sel.dispatchEvent(new Event('input', { bubbles: true }));
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }

        /** Menyalin keadaan <select> ke tampilan: pilihan, placeholder, mati/hidup. */
        function segarkan() {
            inp.placeholder = teksPlaceholder();
            inp.disabled = sel.disabled;
            wrap.hidden = sel.hidden;
            if (document.activeElement !== inp) inp.value = labelTerpilih();
            if (terbuka) gambar();
        }

        /* Sebagian halaman memindahkan pilihan lewat `select.value = ...`
           tanpa membangkitkan event apa pun (mis. saat modal Inventarisasi SPJ
           dibuka). Penetapan itu tidak terlihat oleh MutationObserver, jadi
           kedua properti ini dibungkus di tingkat elemen - perilakunya tetap
           sama, hanya ditambah penyegaran tampilan sesudahnya. */
        ['value', 'selectedIndex'].forEach(function (nama) {
            var asal = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, nama);
            if (! asal || ! asal.set) return;
            Object.defineProperty(sel, nama, {
                configurable: true,
                enumerable: false,
                get: function () { return asal.get.call(sel); },
                set: function (nilai) { asal.set.call(sel, nilai); segarkan(); },
            });
        });

        inp.addEventListener('focus', function () { inp.value = labelTerpilih(); buka(); });
        inp.addEventListener('click', buka);
        inp.addEventListener('input', function () {
            sorot = -1;
            terbuka = true;
            gambar();
            wrap.classList.add('buka');
            tempatkan();
        });
        inp.addEventListener('blur', function () {
            setTimeout(function () { tutup(); inp.value = labelTerpilih(); }, 130);
        });
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (! terbuka) buka();
                sorot = Math.min(Math.max(sorot + (e.key === 'ArrowDown' ? 1 : -1), 0), daftarKini.length - 1);
                gambar();
                var el = drop.querySelector('.sc-item.sorot');
                if (el) el.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                if (! terbuka) return;
                e.preventDefault();
                if (daftarKini[sorot]) pilih(daftarKini[sorot].index);
            } else if (e.key === 'Escape') {
                tutup();
                inp.value = labelTerpilih();
            } else if (e.key === 'Tab') {
                tutup();
                inp.value = labelTerpilih();
            }
        });

        // mousedown, bukan click: blur pada isian tidak sempat menutup daftar
        // sebelum pilihannya terbaca.
        drop.addEventListener('mousedown', function (e) {
            var item = e.target.closest('.sc-item[data-i]');
            if (! item) return;
            e.preventDefault();
            pilih(parseInt(item.dataset.i, 10));
        });

        // Halaman lain kerap mengganti isi <select> (dropdown bertingkat
        // program → kegiatan → kode rekening) atau menyalakan/mematikannya
        // langsung lewat properti. Semua itu diikuti dari sini.
        new MutationObserver(segarkan).observe(sel, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['disabled', 'hidden', 'data-cari-placeholder'],
        });
        sel.addEventListener('change', function () {
            if (document.activeElement !== inp) segarkan();
        });
        if (sel.form) sel.form.addEventListener('reset', function () { setTimeout(segarkan, 0); });

        segarkan();
    }

    function pasang(akar) {
        var ruang = akar || document;
        if (ruang.matches && ruang.matches('select[data-cari]')) pasangSatu(ruang);
        if (ruang.querySelectorAll) {
            Array.prototype.forEach.call(ruang.querySelectorAll('select[data-cari]'), pasangSatu);
        }
    }

    // Banyak formulir menumbuhkan barisnya lewat JavaScript (anggota SP, tim
    // perjalanan dinas, baris SPM). Mengamati dokumen membuat baris baru ikut
    // terpasang tanpa halamannya perlu memanggil apa pun.
    function amatiDokumen() {
        new MutationObserver(function (daftar) {
            for (var i = 0; i < daftar.length; i++) {
                var tambahan = daftar[i].addedNodes;
                for (var j = 0; j < tambahan.length; j++) {
                    if (tambahan[j].nodeType === 1) pasang(tambahan[j]);
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    function mulai() {
        pasang(document);
        amatiDokumen();
    }

    window.SelectCari = { pasang: pasang, pasangSatu: pasangSatu };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mulai);
    } else {
        mulai();
    }
})();
</script>
