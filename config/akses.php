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
        'bendahara' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'tk-monitor', 'dashspj', 'npd', 'npd-selesai', 'persetujuan', 'persetujuan-selesai', 'verifikasi', 'verifikasi-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'audit-log', 'users', 'pelimpahan', 'profil'],
        'pptk' => ['dashboard', 'rincian', 'analisis', 'invspj', 'tk-monitor', 'dashspj', 'npd', 'npd-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'bpp' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'tk-monitor', 'dashspj', 'persetujuan', 'persetujuan-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'verifikator' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'tk-monitor', 'dashspj', 'verifikasi', 'verifikasi-selesai', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'perencanaan' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'tk-monitor', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'inspektur' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'audit-log', 'profil'],
        'sekretaris' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'kasubbag' => ['dashboard', 'rincian', 'analisis', 'invspj', 'dashpd', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'inspektur_pembantu' => ['dashboard', 'rincian', 'analisis', 'dashpd', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'tk-form', 'profil'],
        'layanan' => ['dashboard', 'rincian', 'sp-input', 'sp-monitor', 'tk-form'],
    ],

    'role_label' => [
        'bendahara' => 'Bendahara Pengeluaran',
        'pptk' => 'PPTK',
        'bpp' => 'BPP',
        'verifikator' => 'Verifikator',
        'inspektur' => 'Inspektur Daerah',
        'sekretaris' => 'Sekretaris',
        'kasubbag' => 'Kepala Subbagian Tata Usaha',
        'inspektur_pembantu' => 'Inspektur Pembantu',
        'perencanaan' => 'Perencanaan',
        'layanan' => 'Pengguna Layanan',
    ],

];
