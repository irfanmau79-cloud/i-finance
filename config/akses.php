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
        'superadmin' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd', 'npd-selesai', 'persetujuan', 'persetujuan-selesai', 'verifikasi', 'verifikasi-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'spm', 'manajemen-data', 'audit-log', 'users', 'pelimpahan', 'profil'],
        'bendahara_pengeluaran' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd', 'npd-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'spm', 'manajemen-data', 'profil'],
        'pptk' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dash-tk', 'tk-monitor', 'dashspj', 'npd', 'npd-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'bpp' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'persetujuan', 'persetujuan-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'verifikator' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'verifikasi', 'verifikasi-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'perencanaan' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'inspektur' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'audit-log', 'profil'],
        'sekretaris' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'kasubbag' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'inspektur_pembantu' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'layanan' => ['dashboard', 'rincian', 'sp-input', 'sp-monitor', 'tk-form'],
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
