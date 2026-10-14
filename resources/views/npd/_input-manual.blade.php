{{--
    "Input Manual" pada isian nama detail pembayaran (anggota tim, peserta,
    narasumber, penerima) - dipakai bersama oleh keempat formulir Buat/Edit NPD.

    Butir paling atas dropdown nama. Memilihnya berarti nama diketik sendiri
    dan baris itu TIDAK ditautkan ke Data Pegawai/Rekanan: tautannya
    dikosongkan dan dropdown tidak lagi menawarkan nama dari master sampai
    "Pilih dari daftar" ditekan.

    Baris yang punya isian tersembunyi [data-input-manual] (NPD Perjalanan
    Dinas dan Kontribusi Diklat) ikut MENYIMPAN pilihan ini; server memakainya
    untuk tidak menghitung baris itu di Dashboard Perjalanan Dinas maupun
    Riwayat Perjalanan Dinas pegawai. Pada jenis lain pilihan ini hanya
    melepas tautan.
--}}
<script>
window.npdInputManual = function (row, nameInput, nameDrop, kosongkanTautan, namaMaster) {
    const isian = row.querySelector('[data-input-manual]');
    const catatan = document.createElement('div');
    catatan.className = 'mini';
    catatan.hidden = true;
    catatan.innerHTML = '&#9998; Input manual &mdash; tidak ditautkan ke ' + namaMaster
        + (isian ? ' dan tidak masuk dashboard' : '')
        + '. <a href="#" data-manual-batal>Pilih dari daftar</a>';
    nameInput.closest('[data-name-search]').after(catatan);

    const api = {
        aktif: () => row.dataset.manual === '1',
        set(nyala) {
            row.dataset.manual = nyala ? '1' : '';
            catatan.hidden = ! nyala;
            if (isian) isian.value = nyala ? '1' : '0';
            if (nyala) kosongkanTautan();
        },
        // Butir "Input Manual" untuk ditaruh paling atas dropdown.
        butir() {
            const el = document.createElement('div');
            el.className = 'ns-item manual';
            el.innerHTML = '&#9998; Input Manual';
            el.addEventListener('click', () => {
                api.set(true);
                nameDrop.classList.remove('show');
                nameInput.focus();
            });

            return el;
        },
    };

    catatan.querySelector('[data-manual-batal]').addEventListener('click', (e) => {
        e.preventDefault();
        api.set(false);
        nameInput.focus();
    });

    if (isian && isian.value === '1') api.set(true);
    row.npdManual = api;

    return api;
};
</script>
