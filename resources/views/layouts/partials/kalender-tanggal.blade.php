{{--
    KALENDER TANGGAL — pemilih banyak tanggal untuk satu isian teks.

    Dipasang otomatis pada tiap <input data-kalender> di seluruh aplikasi:
    isiannya dijadikan read-only (murni klik, tidak bisa diketik) dan klik
    padanya membuka kalender. Tanggal dipilih satu per satu (klik = pilih,
    klik lagi = batal), bisa lintas bulan dan lintas tahun.

    Yang tersimpan ke basis data TETAP string ringkas seperti sebelumnya —
    mis. "1-2, 4-7, 13 Juli 2026" atau "30 Juni - 2 Juli, 5 Juli 2026" — jadi
    kolomnya, validasinya, dan seluruh cetakan PDF tidak berubah. Daftar ISO
    hanya keadaan sementara di halaman (disimpan pada dataset isian), tidak
    ikut terkirim.

    Saat menyunting SP lama, string yang sudah ada diurai kembali menjadi
    tanggal supaya kalendernya terpilih otomatis. Bila stringnya tak beraturan
    dan gagal diurai, kalender mulai kosong dan string lamanya tetap utuh
    sampai pengguna memilih ulang — tidak ada data yang tertimpa diam-diam.

    Port dari pemilih kalender GAS (index.html: bukaKalenderRincian) dengan
    satu perbaikan: rentang yang melewati pergantian tahun ("30 Desember 2026
    - 2 Januari 2027") kini diurai ke tahun yang benar; di GAS kedua ujungnya
    jatuh ke tahun yang sama.
--}}
<script>
(function () {
    'use strict';

    var TERPASANG = 'kalSiap';
    var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var BULAN_LC = BULAN.map(function (b) { return b.toLowerCase(); });
    var DOW = ['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'];

    function esc(teks) {
        var d = document.createElement('div');
        d.textContent = teks == null ? '' : teks;
        return d.innerHTML;
    }

    function keIso(y, m, d) {
        return y + '-' + ('0' + (m + 1)).slice(-2) + '-' + ('0' + d).slice(-2);
    }

    /**
     * Daftar ISO (YYYY-MM-DD) -> string ringkas.
     *
     * Hari yang berurutan digabung jadi rentang, lalu nama bulan & tahunnya
     * ditarik ke belakang sejauh mungkin: satu bulan cukup ditulis sekali di
     * ujung ("1-2, 4-7, 13 Juli 2026"), beda bulan menuliskan bulan di tiap
     * bagian ("30 Juni - 2 Juli, 5 Juli 2026"), beda tahun menuliskan
     * tahunnya juga. Bentuknya sengaja sama persis dengan GAS — string inilah
     * yang tercetak di SPJ.
     */
    function rangkum(daftar) {
        if (! daftar || ! daftar.length) return '';

        var tgl = daftar.slice().sort().map(function (x) {
            var p = x.split('-');
            return { y: +p[0], m: +p[1] - 1, d: +p[2] };
        });

        function besok(o) {
            var dt = new Date(o.y, o.m, o.d);
            dt.setDate(dt.getDate() + 1);
            return { y: dt.getFullYear(), m: dt.getMonth(), d: dt.getDate() };
        }

        var kelompok = [];
        var kini = [tgl[0]];
        for (var i = 1; i < tgl.length; i++) {
            var lanjut = besok(kini[kini.length - 1]);
            if (tgl[i].y === lanjut.y && tgl[i].m === lanjut.m && tgl[i].d === lanjut.d) {
                kini.push(tgl[i]);
            } else {
                kelompok.push(kini);
                kini = [tgl[i]];
            }
        }
        kelompok.push(kini);

        var seBulan = tgl.every(function (o) { return o.m === tgl[0].m && o.y === tgl[0].y; });
        var seTahun = tgl.every(function (o) { return o.y === tgl[0].y; });

        function tulis(o, pakaiTahun) {
            return o.d + ' ' + BULAN[o.m] + (pakaiTahun ? (' ' + o.y) : '');
        }

        var bagian = kelompok.map(function (g) {
            var a = g[0];
            var b = g[g.length - 1];
            if (seBulan) return a.d === b.d ? ('' + a.d) : (a.d + '-' + b.d);
            if (a.m === b.m && a.y === b.y) {
                return a.d === b.d ? tulis(a, ! seTahun) : (a.d + '-' + tulis(b, ! seTahun));
            }
            return tulis(a, ! seTahun) + ' - ' + tulis(b, ! seTahun);
        });

        var ekor = seBulan
            ? (' ' + BULAN[tgl[0].m] + ' ' + tgl[0].y)
            : (seTahun ? (' ' + tgl[0].y) : '');

        return bagian.join(', ') + ekor;
    }

    /**
     * String ringkas -> daftar ISO. Sebaik-usaha: kalau bentuknya tak
     * dikenali, hasilnya daftar kosong — bukan tebakan yang salah.
     */
    function urai(teks) {
        if (! teks) return [];
        teks = String(teks).trim();

        function idxBulan(t) {
            t = t.toLowerCase();
            for (var i = 0; i < BULAN_LC.length; i++) {
                if (t.indexOf(BULAN_LC[i]) >= 0) return i;
            }
            return -1;
        }

        var hasil = [];
        try {
            var tahunSemua = teks.match(/(\d{4})\b/g);
            if (! tahunSemua) return [];
            var tahunBaku = +tahunSemua[tahunSemua.length - 1];
            var bulanUmum = idxBulan(teks);

            teks.split(',').forEach(function (bagian) {
                bagian = bagian.trim();

                var th = (bagian.match(/(\d{4})\b/) || [])[1];
                th = th ? +th : tahunBaku;

                var bl = idxBulan(bagian);
                if (bl < 0) bl = bulanUmum;
                if (bl < 0) return;

                var rentang = bagian.match(
                    /^(\d{1,2})(?:\s+([A-Za-z]+))?(?:\s+\d{4})?\s*[-–]\s*(\d{1,2})(?:\s+([A-Za-z]+))?/
                );

                if (rentang) {
                    var b1 = rentang[2] ? idxBulan(rentang[2]) : bl;
                    if (b1 < 0) b1 = bl;
                    var b2 = rentang[4] ? idxBulan(rentang[4]) : b1;
                    if (b2 < 0) b2 = b1;
                    // Bulan akhir yang lebih awal dari bulan mulai berarti
                    // rentangnya melompati pergantian tahun.
                    var th2 = b2 < b1 ? th + 1 : th;
                    var mulai = new Date(th, b1, +rentang[1]);
                    var akhir = new Date(th2, b2, +rentang[3]);
                    if (akhir < mulai) return;
                    for (var dt = new Date(mulai); dt <= akhir; dt.setDate(dt.getDate() + 1)) {
                        hasil.push(keIso(dt.getFullYear(), dt.getMonth(), dt.getDate()));
                    }
                    return;
                }

                var tunggal = bagian.match(/^(\d{1,2})(?:\s+([A-Za-z]+))?/);
                if (! tunggal) return;
                var b = tunggal[2] ? idxBulan(tunggal[2]) : bl;
                if (b < 0) b = bl;
                hasil.push(keIso(th, b, +tunggal[1]));
            });
        } catch (e) {
            return [];
        }

        return hasil.filter(function (v, i, a) { return a.indexOf(v) === i; });
    }

    /* ===== Kalender melayang: satu untuk seluruh halaman ===== */

    var lapis = null;
    var kotak = null;
    var sasaran = null;      // isian yang sedang disunting
    var pilihan = {};        // ISO -> true
    var lihatY = 0;
    var lihatM = 0;

    function siapkanLapis() {
        if (lapis) return;

        lapis = document.createElement('div');
        lapis.className = 'kal-lapis';
        lapis.hidden = true;

        kotak = document.createElement('div');
        kotak.className = 'kal-box';
        kotak.setAttribute('role', 'dialog');
        kotak.setAttribute('aria-modal', 'true');
        kotak.setAttribute('aria-label', 'Pilih tanggal penugasan');

        lapis.appendChild(kotak);
        document.body.appendChild(lapis);

        lapis.addEventListener('mousedown', function (e) { if (e.target === lapis) tutup(); });

        // Satu penangan untuk seluruh isi kalender: isinya digambar ulang tiap
        // kali sesuatu berubah, jadi menempelkan penangan per tombol berarti
        // memasang ulang puluhan penangan setiap klik.
        kotak.addEventListener('click', function (e) {
            var tombol = e.target.closest('[data-kal]');
            if (! tombol) return;
            var aksi = tombol.dataset.kal;
            if (aksi === 'maju') geser(1);
            else if (aksi === 'mundur') geser(-1);
            else if (aksi === 'bersih') { pilihan = {}; gambar(); }
            else if (aksi === 'batal') tutup();
            else if (aksi === 'selesai') simpan();
            else jungkit(aksi);
        });

        document.addEventListener('keydown', function (e) {
            if (lapis.hidden) return;
            if (e.key === 'Escape') { e.preventDefault(); tutup(); }
        });
    }

    function geser(langkah) {
        lihatM += langkah;
        if (lihatM < 0) { lihatM = 11; lihatY--; }
        if (lihatM > 11) { lihatM = 0; lihatY++; }
        gambar();
    }

    function jungkit(isoTgl) {
        if (pilihan[isoTgl]) delete pilihan[isoTgl];
        else pilihan[isoTgl] = true;
        gambar();
    }

    function terpilih() {
        return Object.keys(pilihan).sort();
    }

    function gambar() {
        var awal = new Date(lihatY, lihatM, 1);
        var mulaiDow = (awal.getDay() + 6) % 7;                    // Senin = 0
        var jumlahHari = new Date(lihatY, lihatM + 1, 0).getDate();
        var hariIni = new Date();
        var isoHariIni = keIso(hariIni.getFullYear(), hariIni.getMonth(), hariIni.getDate());

        var sel = '';
        for (var i = 0; i < mulaiDow; i++) sel += '<span class="kal-kosong"></span>';
        for (var d = 1; d <= jumlahHari; d++) {
            var isoTgl = keIso(lihatY, lihatM, d);
            var kelas = 'kal-hari'
                + (pilihan[isoTgl] ? ' aktif' : '')
                + (isoTgl === isoHariIni ? ' kini' : '');
            sel += '<button type="button" class="' + kelas + '" data-kal="' + isoTgl + '"'
                + ' aria-pressed="' + (pilihan[isoTgl] ? 'true' : 'false') + '">' + d + '</button>';
        }

        var daftar = terpilih();
        var ringkas = daftar.length
            ? esc(rangkum(daftar))
            : '<span class="kal-ring-kosong">Belum ada tanggal dipilih</span>';

        kotak.innerHTML =
            '<div class="kal-kepala">'
            + '<button type="button" class="kal-nav" data-kal="mundur" aria-label="Bulan sebelumnya">&#8249;</button>'
            + '<span class="kal-judul">' + BULAN[lihatM] + ' ' + lihatY + '</span>'
            + '<button type="button" class="kal-nav" data-kal="maju" aria-label="Bulan berikutnya">&#8250;</button>'
            + '</div>'
            + '<div class="kal-grid kal-dow">'
            + DOW.map(function (h) { return '<span>' + h + '</span>'; }).join('')
            + '</div>'
            + '<div class="kal-grid">' + sel + '</div>'
            + '<div class="kal-ring"><div class="kal-ring-lbl">Rangkuman</div>'
            + '<div class="kal-ring-isi">' + ringkas + '</div></div>'
            + '<div class="kal-kaki">'
            + '<button type="button" class="btn" data-kal="bersih">Bersihkan</button>'
            + '<span class="kal-kaki-sela"></span>'
            + '<button type="button" class="btn" data-kal="batal">Batal</button>'
            + '<button type="button" class="btn prim" data-kal="selesai">Selesai</button>'
            + '</div>';
    }

    function buka(el) {
        if (el.disabled) return;
        siapkanLapis();
        sasaran = el;

        // Sumber pilihan: daftar ISO dari suntingan sebelumnya di halaman ini,
        // kalau belum ada baru diurai dari teks yang tersimpan.
        var benih = el.dataset.kalIso
            ? el.dataset.kalIso.split(',').filter(Boolean)
            : urai(el.value);

        pilihan = {};
        benih.forEach(function (t) { pilihan[t] = true; });

        var pertama = benih.length ? benih.slice().sort()[0].split('-') : null;
        var kini = new Date();
        lihatY = pertama ? +pertama[0] : kini.getFullYear();
        lihatM = pertama ? (+pertama[1] - 1) : kini.getMonth();

        gambar();
        lapis.hidden = false;
    }

    function tutup() {
        if (lapis) lapis.hidden = true;
        var kembali = sasaran;
        sasaran = null;
        if (kembali) kembali.focus();
    }

    function simpan() {
        var daftar = terpilih();
        var el = sasaran;
        tutup();
        if (! el) return;
        el.value = rangkum(daftar);
        el.dataset.kalIso = daftar.join(',');
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function pasangSatu(el) {
        if (! el || el.dataset[TERPASANG] === '1') return;
        el.dataset[TERPASANG] = '1';

        el.readOnly = true;                  // murni klik: tidak bisa diketik manual
        el.classList.add('kal-inp');
        el.setAttribute('autocomplete', 'off');
        if (! el.placeholder) el.placeholder = 'Klik untuk pilih tanggal…';

        // mousedown, bukan click: klik pertama tidak perlu menaruh kursor teks
        // di isian read-only sebelum kalendernya terbuka.
        el.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            e.preventDefault();
            buka(el);
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                buka(el);
            }
        });
    }

    function pasang(akar) {
        var ruang = akar || document;
        if (ruang.matches && ruang.matches('input[data-kalender]')) pasangSatu(ruang);
        if (ruang.querySelectorAll) {
            Array.prototype.forEach.call(ruang.querySelectorAll('input[data-kalender]'), pasangSatu);
        }
    }

    // Sebagian formulir menumbuhkan barisnya lewat JavaScript. Mengamati
    // dokumen membuat isian baru ikut terpasang tanpa halamannya memanggil apa pun.
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

    // Diekspor supaya halaman lain bisa memasang isian barunya sendiri, dan
    // supaya perangkat uji bisa memanggil kedua fungsi ringkas/urai langsung.
    window.KalenderTanggal = { pasang: pasang, pasangSatu: pasangSatu, rangkum: rangkum, urai: urai };

    function mulai() {
        pasang(document);
        amatiDokumen();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mulai);
    } else {
        mulai();
    }
})();
</script>
