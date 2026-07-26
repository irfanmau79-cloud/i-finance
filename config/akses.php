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

    'menu' => [
        'superadmin' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd', 'persetujuan', 'verifikasi', 'sp-input', 'sp-data', 'sp-monitor', 'tk-data', 'tk-form', 'spm', 'pengembalian-create', 'pengembalian', 'manajemen-data', 'audit-log', 'users', 'pelimpahan', 'data-pegawai', 'profil'],
        'bendahara_pengeluaran' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'spm', 'pengembalian-create', 'pengembalian', 'manajemen-data', 'data-pegawai', 'profil'],
        'pptk' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dash-tk', 'tk-monitor', 'dashspj', 'npd', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'data-pegawai', 'profil'],
        'bpp' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'persetujuan', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'pengembalian-create', 'pengembalian', 'data-pegawai', 'profil'],
        'verifikator' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'verifikasi', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'data-pegawai', 'profil'],
        'perencanaan' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'data-pegawai', 'profil'],
        'inspektur' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'audit-log', 'data-pegawai', 'profil'],
        'sekretaris' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'data-pegawai', 'profil'],
        'kasubbag' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'data-pegawai', 'profil'],
        'inspektur_pembantu' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'data-pegawai', 'profil'],
        'layanan' => ['dashboard', 'rincian', 'sp-input', 'sp-monitor', 'tk-form', 'data-pegawai'],
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
        'layanan' => 'Pengguna Layanan',
    ],

];
