{{--
    Dialog i-Finance: pengganti confirm() dan alert() bawaan peramban.

    Dialog bawaan peramban tidak bisa diberi gaya, berbeda-beda tiap peramban,
    dan menampilkan alamat situs di judulnya. Di sini semuanya memakai satu
    dialog berwajah aplikasi (kelas .mdl-ov/.mdl yang sama dengan modal lain).

    TIGA CARA PAKAI

    1. Formulir - cukup beri atribut, tanpa skrip:
         <form method="POST" ... data-konfirmasi="Hapus data ini?">
       Pengirimannya ditahan sampai pengguna menekan tombol setuju. Baris baru
       ditulis &#10;. Pilihan tambahan (semuanya opsional):
         data-konfirmasi-judul="..."   judul dialog
         data-konfirmasi-ya="..."      tulisan tombol setuju
         data-konfirmasi-jenis="bahaya|biasa"   paksa warnanya
       Atribut yang sama boleh dipasang di tombol submit-nya, bila satu
       formulir punya beberapa tombol dengan pertanyaan berbeda.

    2. Tautan/tombol biasa:
         <a href="..." data-konfirmasi="...">   pindah halaman setelah setuju
         <button type="button" data-beritahu="File tidak tersedia.">

    3. Dari skrip - keduanya mengembalikan Promise:
         if (! await iFinance.konfirmasi('Hapus berkas ini?')) return;
         iFinance.beritahu('Gagal menyimpan. Silakan coba lagi.');

    Warna "bahaya" (merah) dipilih otomatis bila pesannya menyebut penghapusan
    atau tindakan yang tidak bisa dibatalkan; selain itu navy.
--}}
<script>
(function () {
    if (window.iFinance && window.iFinance.konfirmasi) return;

    var IKON = {
        biasa: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        bahaya: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };

    // Pesan yang menyebut penghapusan / tak bisa dibatalkan -> tampil merah.
    var POLA_BAHAYA = /\b(hapus|menghapus|dihapus|permanen|kosongkan|tidak (bisa|dapat) di(batalkan|kembalikan)|hilang)\b/i;

    var ov = null, elIkon, elJudul, elPesan, elBatal, elYa, tutupAktif = null, fokusSebelum = null;

    function bangun() {
        if (ov) return;

        ov = document.createElement('div');
        ov.className = 'mdl-ov ifd-ov';
        ov.innerHTML =
            '<div class="mdl ifd" role="alertdialog" aria-modal="true" aria-labelledby="ifd-judul" aria-describedby="ifd-pesan">'
            + '<div class="ifd-pita" aria-hidden="true"></div>'
            + '<div class="ifd-isi">'
            + '<div class="ifd-ikon"></div>'
            + '<div class="ifd-judul" id="ifd-judul"></div>'
            + '<div class="ifd-pesan" id="ifd-pesan"></div>'
            + '</div>'
            + '<div class="ifd-aksi">'
            + '<button type="button" class="btn" data-ifd-batal>Batal</button>'
            + '<button type="button" class="btn prim" data-ifd-ya>Ya, Lanjutkan</button>'
            + '</div>'
            + '<div class="ifd-merek" aria-hidden="true">i-Finance &middot; Inspektorat Daerah Provinsi Jawa Barat</div>'
            + '</div>';
        document.body.appendChild(ov);

        elIkon = ov.querySelector('.ifd-ikon');
        elJudul = ov.querySelector('.ifd-judul');
        elPesan = ov.querySelector('.ifd-pesan');
        elBatal = ov.querySelector('[data-ifd-batal]');
        elYa = ov.querySelector('[data-ifd-ya]');

        elBatal.addEventListener('click', function () { selesai(false); });
        elYa.addEventListener('click', function () { selesai(true); });
        // Klik di luar kotak = batal, sama seperti menekan Esc.
        ov.addEventListener('mousedown', function (e) { if (e.target === ov) selesai(false); });

        document.addEventListener('keydown', function (e) {
            if (! tutupAktif) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                selesai(false);
            } else if (e.key === 'Tab') {
                // Fokus dikurung di antara tombol-tombol dialog.
                var tombol = [elBatal, elYa].filter(function (t) { return ! t.hidden; });
                var i = tombol.indexOf(document.activeElement);
                e.preventDefault();
                tombol[(i + (e.shiftKey ? tombol.length - 1 : 1)) % tombol.length].focus();
            }
        }, true);
    }

    function selesai(hasil) {
        if (! tutupAktif) return;

        var tutup = tutupAktif;
        tutupAktif = null;
        ov.classList.remove('show');
        if (fokusSebelum && document.contains(fokusSebelum)) fokusSebelum.focus();
        tutup(hasil);
    }

    function buka(pesan, opsi) {
        bangun();
        opsi = opsi || {};

        // Dialog sebelumnya yang masih terbuka dianggap dibatalkan.
        if (tutupAktif) selesai(false);

        var hanyaInfo = opsi.hanyaInfo === true;
        var jenis = opsi.jenis || (hanyaInfo ? 'info' : (POLA_BAHAYA.test(pesan) ? 'bahaya' : 'biasa'));

        ov.querySelector('.ifd').setAttribute('data-jenis', jenis);
        elIkon.innerHTML = IKON[jenis] || IKON.biasa;
        elJudul.textContent = opsi.judul || (hanyaInfo ? 'Pemberitahuan' : 'Konfirmasi');
        // textContent, bukan innerHTML: pesannya sering memuat nama/nomor
        // dari basis data. Baris barunya ditampilkan lewat white-space:pre-line.
        elPesan.textContent = String(pesan == null ? '' : pesan);

        elBatal.hidden = hanyaInfo;
        elBatal.textContent = opsi.batal || 'Batal';
        elYa.textContent = opsi.ya || (hanyaInfo ? 'Mengerti' : 'Ya, Lanjutkan');
        elYa.className = 'btn ' + (jenis === 'bahaya' ? 'danger' : 'prim');

        fokusSebelum = document.activeElement;
        ov.classList.add('show');

        // Tindakan berbahaya: fokus awal di Batal, supaya Enter yang tak
        // sengaja tidak langsung menghapus.
        (jenis === 'bahaya' && ! hanyaInfo ? elBatal : elYa).focus();

        return new Promise(function (resolve) { tutupAktif = resolve; });
    }

    window.iFinance = window.iFinance || {};

    /** Tanya ya/tidak. Promise<boolean>. */
    window.iFinance.konfirmasi = function (pesan, opsi) {
        return buka(pesan, opsi);
    };

    /** Pemberitahuan satu tombol. Promise yang selesai saat ditutup. */
    window.iFinance.beritahu = function (pesan, opsi) {
        opsi = opsi || {};
        opsi.hanyaInfo = true;

        return buka(pesan, opsi).then(function () {});
    };

    function opsiDari(el) {
        return {
            judul: el.getAttribute('data-konfirmasi-judul') || undefined,
            ya: el.getAttribute('data-konfirmasi-ya') || undefined,
            jenis: el.getAttribute('data-konfirmasi-jenis') || undefined,
        };
    }

    // ---- Formulir ber-data-konfirmasi ----
    // Dipasang di fase TANGKAP supaya berjalan sebelum penangan submit lain
    // (mis. yang menonaktifkan tombol atau menyusun isian tersembunyi).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (! form || ! form.getAttribute) return;

        var pengirim = e.submitter && e.submitter.hasAttribute && e.submitter.hasAttribute('data-konfirmasi') ? e.submitter : null;
        var sumber = pengirim || (form.hasAttribute('data-konfirmasi') ? form : null);
        if (! sumber) return;

        // Putaran kedua (sesudah disetujui) diloloskan apa adanya.
        if (form.__ifdSetuju) {
            form.__ifdSetuju = false;
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        var tombol = e.submitter || null;

        buka(sumber.getAttribute('data-konfirmasi'), opsiDari(sumber)).then(function (setuju) {
            if (! setuju) return;

            form.__ifdSetuju = true;

            if (typeof form.requestSubmit === 'function') {
                // requestSubmit memicu lagi peristiwa submit, jadi penangan
                // lain tetap berjalan dan nama/nilai tombolnya ikut terkirim.
                form.requestSubmit(tombol && tombol.form === form ? tombol : undefined);
            } else {
                form.__ifdSetuju = false;
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    }, true);

    // ---- Tautan / tombol biasa ----
    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-beritahu], a[data-konfirmasi]') : null;
        if (! el) return;

        if (el.hasAttribute('data-beritahu')) {
            e.preventDefault();
            window.iFinance.beritahu(el.getAttribute('data-beritahu'));

            return;
        }

        if (el.__ifdSetuju) {
            el.__ifdSetuju = false;
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        buka(el.getAttribute('data-konfirmasi'), opsiDari(el)).then(function (setuju) {
            if (! setuju) return;

            el.__ifdSetuju = true;
            el.click();
        });
    }, true);
})();
</script>
