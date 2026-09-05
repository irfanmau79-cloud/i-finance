<?php

/*
|--------------------------------------------------------------------------
| Tarif & pilihan modul Estimasi Kebutuhan Kegiatan Pengawasan
|--------------------------------------------------------------------------
|
| Dipindahkan apa adanya dari KEB_TARIF_* dan KEB_JENIS_ANGGOTA di
| gas/index.html. Nilainya standar biaya yang berlaku, jadi ditaruh di config
| supaya bisa diganti saat standar biayanya diperbarui tanpa menyentuh kode.
|
| Tarif akomodasi masih menerima isian manual di formulir (menginap di luar
| daftar ini memang terjadi), sedangkan tarif uang harian TIDAK - besarannya
| ditetapkan dan tidak boleh diketik bebas.
*/

return [

    'tarif_uh_dalam' => [100_000, 170_000],

    'tarif_uh_luar' => [200_000, 275_000, 350_000, 430_000],

    'tarif_akomodasi' => [570_000, 1_006_000, 2_755_000],

    'jenis_anggota' => [
        'Eselon II dan setara',
        'Eselon III / Golongan IV',
        'Eselon IV / Golongan III, II, dan I',
    ],

];
