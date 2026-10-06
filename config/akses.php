<?php

/*
|--------------------------------------------------------------------------
| Konstanta akses menu per role
|--------------------------------------------------------------------------
|
| Dipakai oleh sidebar (resources/views/layouts/app.blade.php) untuk
| menentukan menu apa saja yang tampil, dan oleh middleware 'menu-akses'
| untuk menjaga rutenya.
|
| Sumbernya MATRIKS di bawah: satu baris per kunci menu, berisi role yang
| boleh membukanya - susunan yang sama dengan ringkasan role yang disepakati
| kantor, supaya keduanya bisa dicocokkan baris demi baris. Daftar per role
| ('menu') diturunkan dari matriks itu, tidak ditulis tangan.
*/

// "Pimpinan" pada ringkasan role: seluruh pejabat struktural.
$irban = ['irban1', 'irban2', 'irban3', 'irban4', 'irban_inv'];
$pimpinan = ['inspektur', 'sekretaris', 'kasubbag', 'inspektur_pembantu', ...$irban];

// Lima role yang mengerjakan atau memantau alur keuangan sehari-hari.
$keuangan = ['superadmin', 'bendahara_pengeluaran', 'bpp', 'pptk', 'verifikator'];

// "All role": semua role yang login, ditambah Pengguna Layanan (tanpa akun).
$login = [...$keuangan, ...$pimpinan, 'perencanaan', 'kepegawaian', 'pengawas', 'pengelola_spj'];
$semua = [...$login, 'layanan'];

// Pengawas hanya membaca (lihat 'role_baca_saja'), jadi dua formulir isian
// yang "All role" ini tidak ikut dipegangnya - rutenya pun menolaknya.
$semuaPengisi = array_values(array_diff($semua, ['pengawas']));

$pemantau = [...$keuangan, ...$pimpinan, 'pengawas'];

$matriks = [
    // Dashboard
    'dashboard' => $semua,
    'dashpd' => [...$keuangan, ...$pimpinan, 'perencanaan', 'pengawas'],
    'dash-tk' => [...$keuangan, ...$pimpinan, 'kepegawaian', 'pengawas'],
    'dashspj' => $pemantau,
    // Dashboard Nota Pencairan Dana: role keuangan dan Pengawas, tetapi dari
    // Pimpinan HANYA Inspektur Daerah, Sekretaris, dan Kasubbag TU - para
    // Inspektur Pembantu tidak memegangnya (keputusan Irfan, Oktober 2026).
    // Semuanya juga memegang 'npd-data', kunci yang menjaga dokumen cetak
    // di balik tombol "Lihat NPD".
    'dashnpd' => [...$keuangan, 'inspektur', 'sekretaris', 'kasubbag', 'pengawas'],

    // Rincian Realisasi (Tahunan & Periodik berbagi satu kunci)
    'rincian' => [...$keuangan, ...$pimpinan, 'perencanaan', 'pengawas'],

    // Analisis dan Tren. 'analisis' = Tren Realisasi + dua Simulasi.
    'analisis' => [...$keuangan, ...$pimpinan, 'perencanaan', 'pengawas'],
    /*
     * Monitoring PKPT & Data Kebutuhan: Superadmin, Perencanaan, Pengawas -
     * DITAMBAH para Inspektur Pembantu. Irban per unit mengisi Estimasi
     * Kebutuhan dari daftar PKPT unitnya dan harus bisa melihat hasil
     * isiannya sendiri; ikatan unitnya ditegakkan di KebutuhanController
     * (lihat App\Support\BidangOrganisasi::unitRole()).
     */
    'pkpt' => ['superadmin', 'perencanaan', 'pengawas', 'inspektur_pembantu', ...$irban],
    'keb-data' => ['superadmin', 'perencanaan', 'pengawas', 'inspektur_pembantu', ...$irban],
    'keb-input' => $irban,

    // Nota Pencairan Dana (NPD)
    'npd-data' => $pemantau,
    'npd' => ['superadmin', 'pptk'],
    // Bendahara Pengeluaran membuka antrean ini untuk MEMANTAU; aksi
    // persetujuannya tetap milik BPP (lihat Npd::TRANSISI).
    'persetujuan' => ['superadmin', 'bendahara_pengeluaran', 'bpp'],
    'verifikasi' => ['superadmin', 'verifikator'],
    'npd-rekanan' => $keuangan,

    // Pengembalian (Input Data & Daftar)
    'pengembalian-create' => ['superadmin', 'bendahara_pengeluaran', 'bpp', 'verifikator'],
    'pengembalian' => ['superadmin', 'bendahara_pengeluaran', 'bpp', 'verifikator'],

    'invspj' => ['superadmin', 'pptk', 'bpp', 'bendahara_pengeluaran', 'sekretaris', 'kasubbag', 'pengawas', 'pengelola_spj'],

    // Data Realisasi SP2D (UP/GU/TU & LS berbagi satu kunci)
    'spm' => $pemantau,

    // Surat Perintah
    'sp-input' => $semuaPengisi,
    'sp-data' => $pemantau,
    'sp-monitor' => $semua,
    'sp-cetakspj' => $semua,
    'sp-cetaksppd' => $semua,

    // Data Kepegawaian
    'tk-pegawai' => [...$keuangan, ...$pimpinan, 'kepegawaian', 'perencanaan', 'pengawas'],
    'tk-data' => ['superadmin', 'bendahara_pengeluaran', 'pptk', 'kepegawaian', 'pengawas'],
    'tk-form' => $semuaPengisi,
    'tk-monitor' => $semua,

    /*
     * Gaji dan Tunjangan. Empat kunci pertama adalah empat penyajian sub
     * menu "Rincian Penghasilan". Semua role boleh membukanya, tetapi di
     * luar config('gaji_tunjangan.role_data_penuh') wajib memasukkan NIP +
     * 4 digit akhir rekening dan hanya menerima barisnya sendiri.
     */
    'gt-gaji' => $semua,
    'gt-beban' => $semua,
    'gt-kondisi' => $semua,
    'gt-total' => $semua,
    'gt-cetak' => $semua,
    'gt-daftar' => ['superadmin', 'bendahara_pengeluaran'],
    'gt-rekon' => ['superadmin', 'bendahara_pengeluaran'],
    'gt-potensi' => ['superadmin', 'bendahara_pengeluaran', 'kasubbag', 'sekretaris', 'inspektur'],

    'audit-log' => ['superadmin'],

    // Setting
    'pelimpahan' => ['superadmin'],
    'manajemen-data' => ['superadmin'],
    'users' => ['superadmin'],

    // Pengguna Layanan tidak punya akun, jadi tidak punya profil.
    'profil' => $login,
];

$menu = array_fill_keys($semua, []);

foreach ($matriks as $kunci => $roles) {
    foreach ($roles as $role) {
        $menu[$role][] = $kunci;
    }
}

return [

    /*
     * Kata sandi bersama untuk Pengguna Layanan. Bukan akun: tidak ada
     * pendaftaran, tidak ada username - satu kata sandi yang dibagikan ke
     * pegawai supaya halaman layanan tidak terbuka bebas begitu aplikasi
     * dihosting. Bisa diganti di server lewat SANDI_LAYANAN pada .env.
     */
    'sandi_layanan' => env('SANDI_LAYANAN', 'itprovjabar'),

    // role => daftar kunci menu, diturunkan dari $matriks di atas.
    'menu' => $menu,

    /*
     * Role yang boleh MENGUBAH data pada menu yang pembacanya lebih luas
     * daripada pengelolanya. Memegang kunci menunya hanya berarti boleh
     * membuka halamannya; tombol dan rute pengubahnya dijaga daftar ini,
     * lewat middleware 'kelola' (App\Http\Middleware\EnsureRoleBolehKelola)
     * dan helper boleh_kelola() di tampilan.
     */
    'kelola' => [
        'spm' => ['superadmin', 'bendahara_pengeluaran'],
        'invspj' => ['superadmin', 'pengelola_spj'],
        'tk-pegawai' => ['superadmin', 'kepegawaian'],
        'tk-data' => ['superadmin', 'kepegawaian'],
    ],

    /*
     * Role yang HANYA boleh membaca. Mereka melihat luas tetapi tidak boleh
     * mengubah apa pun.
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
        'pengelola_spj' => 'Pengelola SPJ',
        'layanan' => 'Pengguna Layanan',
    ],

    /**
     * Menu yang SEMENTARA disembunyikan dari sidebar: kunci menu => role
     * yang masih melihatnya.
     *
     * Hanya tautan menunya yang hilang. Hak aksesnya ('menu' di atas) tidak
     * berubah, jadi halamannya tetap terbuka bagi role yang berhak bila
     * alamatnya dibuka langsung - ini saklar tampilan, bukan pengaman. Untuk
     * menampilkannya lagi, hapus barisnya.
     */
    'menu_disembunyikan' => [
        'keb-data' => ['superadmin'],
    ],

];
