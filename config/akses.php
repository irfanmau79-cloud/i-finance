<?php

/*
|--------------------------------------------------------------------------
| Konstanta akses menu per role
|--------------------------------------------------------------------------
|
| Dipindahkan apa adanya dari variabel AKSES & ROLE_LABEL pada sistem lama
| (gas-lama/index.html). Dipakai oleh sidebar (resources/views/layouts/app.blade.php)
| untuk menentukan menu apa saja yang tampil untuk role yang sedang login.
*/

return [

    /*
     * Kata sandi bersama untuk Pengguna Layanan. Bukan akun: tidak ada
     * pendaftaran, tidak ada username - satu kata sandi yang dibagikan ke
     * pegawai supaya halaman layanan tidak terbuka bebas begitu aplikasi
     * dihosting. Bisa diganti di server lewat SANDI_LAYANAN pada .env.
     */
    'sandi_layanan' => env('SANDI_LAYANAN', 'itprovjabar'),

    'menu' => [
        'superadmin' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'npd', 'persetujuan', 'verifikasi', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-pegawai', 'tk-data', 'tk-form', 'spm', 'pengembalian-create', 'pengembalian', 'manajemen-data', 'audit-log', 'users', 'pelimpahan', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'gt-daftar', 'gt-rekon', 'profil'],
        'bendahara_pengeluaran' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'npd', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'spm', 'pengembalian-create', 'pengembalian', 'manajemen-data', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'gt-daftar', 'gt-rekon', 'profil'],
        'pptk' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'npd', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'bpp' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'persetujuan', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'pengembalian-create', 'pengembalian', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'verifikator' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'verifikasi', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'perencanaan' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'inspektur' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'audit-log', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'sekretaris' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'kasubbag' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'inspektur_pembantu' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'kepegawaian' => ['dashboard', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-pegawai', 'tk-data', 'tk-form', 'tk-monitor', 'profil'],
        'layanan' => ['dashboard', 'rincian', 'sp-input', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'tk-form'],
    ],

    'role_label' => [
        'superadmin' => 'Superadmin',
        'bendahara_pengeluaran' => 'Bendahara Pengeluaran',
        'pptk' => 'PPTK',
        'bpp' => 'Bendahara Pengeluaran Pembantu (BPP)',
        'verifikator' => 'Verifikator',
        'inspektur' => 'Inspektur Daerah',
        'sekretaris' => 'Sekretaris',
        'kasubbag' => 'Kepala Subbagian Tata Usaha',
        'inspektur_pembantu' => 'Inspektur Pembantu',
        'perencanaan' => 'Perencanaan',
        'kepegawaian' => 'Kepegawaian',
        'layanan' => 'Pengguna Layanan',
    ],

];
