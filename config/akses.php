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
        'superadmin' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'npd', 'persetujuan', 'verifikasi', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-pegawai', 'tk-data', 'tk-form', 'spm', 'pengembalian-create', 'pengembalian', 'manajemen-data', 'audit-log', 'users', 'pelimpahan', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'gt-daftar', 'gt-rekon', 'profil'],
        'bendahara_pengeluaran' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'npd', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'spm', 'pengembalian-create', 'pengembalian', 'manajemen-data', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'gt-daftar', 'gt-rekon', 'profil'],
        'pptk' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'invspj', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'npd', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'bpp' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'persetujuan', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'pengembalian-create', 'pengembalian', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'verifikator' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'verifikasi', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'perencanaan' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'dashpd', 'dash-tk', 'tk-monitor', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'inspektur' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'audit-log', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'sekretaris' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'kasubbag' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'inspektur_pembantu' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],

        /*
         * Lima role Irban per unit kerja. Beda dengan 'inspektur_pembantu'
         * yang generik: role ini MENGIKAT satu unit kerja, dan modul Estimasi
         * Kebutuhan memakai ikatan itu untuk mengunci input serta menyaring
         * data - lihat App\Support\BidangOrganisasi::unitRole(). Menunya sama
         * dengan inspektur_pembantu, ditambah form kebutuhan (keb-input).
         */
        'irban1' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'keb-input', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'irban2' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'keb-input', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'irban3' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'keb-input', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'irban4' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'keb-input', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'irban_inv' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'keb-input', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-form', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'profil'],
        'pengawas' => ['dashboard', 'rincian', 'analisis', 'pkpt', 'keb-data', 'invspj', 'dashpd', 'dash-tk', 'tk-monitor', 'dashspj', 'npd-data', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'spm', 'pengembalian', 'audit-log', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'gt-rekon', 'profil'],
        'kepegawaian' => ['dashboard', 'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'tk-pegawai', 'tk-data', 'tk-form', 'tk-monitor', 'profil'],
        'layanan' => ['dashboard', 'rincian', 'sp-input', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd', 'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'tk-form'],
    ],

    /*
     * Role yang HANYA boleh membaca. Mereka melihat luas - hampir seluas
     * superadmin - tetapi tidak boleh mengubah apa pun.
     *
     * Sebagian besar aksi ubah sudah dijaga daftar-izin role eksplisit,
     * sehingga role baru otomatis tertutup di sana. Daftar ini menutup celah
     * yang tersisa: rute pengubah data yang hanya dijaga menu-akses, sehingga
     * siapa pun yang punya kunci menunya ikut boleh mengubah. Ditegakkan oleh
     * middleware 'baca-saja' (App\Http\Middleware\EnsureRoleBukanBacaSaja).
     */
    'role_baca_saja' => [
        'pengawas',
    ],

    /*
     * Dashboard Tunjangan Keluarga menampilkan nama & tanggal lahir anak
     * seluruh pegawai. Role di luar daftar ini wajib melewati gerbang NIP +
     * 4 digit akhir rekening lebih dulu (gerbang yang sama dengan Data Gaji &
     * Tunjangan) dan hanya menerima barisnya sendiri; kartu agregatnya tetap
     * tampil karena tidak membuka data siapa pun.
     *
     * Kepegawaian ikut di sini walau tidak ada di daftar Data Gaji: merekalah
     * yang memelihara data ini lewat menu Data Tunjangan Keluarga.
     */
    'role_tk_data_penuh' => [
        'superadmin',
        'bendahara_pengeluaran',
        'kasubbag',
        'sekretaris',
        'inspektur',
        'kepegawaian',
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
        'irban1' => 'Inspektur Pembantu I',
        'irban2' => 'Inspektur Pembantu II',
        'irban3' => 'Inspektur Pembantu III',
        'irban4' => 'Inspektur Pembantu IV',
        'irban_inv' => 'Inspektur Pembantu Investigasi',
        'perencanaan' => 'Perencanaan',
        'pengawas' => 'Pengawas',
        'kepegawaian' => 'Kepegawaian',
        'layanan' => 'Pengguna Layanan',
    ],

];
