<?php

/*
|--------------------------------------------------------------------------
| Modul Data Gaji & Tunjangan
|--------------------------------------------------------------------------
|
| Port dari CodeGajiTunjangan.gs. Nilai-nilai yang di GAS ditulis langsung di
| dalam kode dipindahkan ke sini supaya bisa diganti tanpa menyunting kode.
|
*/

return [

    /*
     * ISI AWAL daftar penandatangan Surat Keterangan Penghasilan. Di GAS ini
     * konstanta GT_PENANDATANGAN yang di-hardcode.
     *
     * PERHATIAN: sejak migrasi 2026_08_31_110000 daftar yang dipakai aplikasi
     * ada di tabel `penandatangan_rincian` dan dikelola superadmin lewat menu
     * Cetak Rincian Penghasilan. Nilai di bawah hanya dipakai SEKALI oleh
     * migrasi itu untuk mengisi tabelnya; menyuntingnya sekarang tidak
     * mengubah apa pun pada instalasi yang migrasinya sudah jalan.
     *
     * Mengganti isi daftar TIDAK mengubah dokumen yang sudah pernah dibuat:
     * identitas penandatangan ikut dibekukan sebagai snapshot di tabel
     * rincian_penghasilan saat dokumen dibuat, jadi cetak ulang dokumen lama
     * tetap memakai pejabat yang dulu menandatanganinya.
     *
     * 'nama' ditulis apa adanya (tidak dikapitalkan ulang) supaya gelar
     * seperti "S.Ak." dan "M.S.P." tidak berubah bentuk - lihat perubahan 14
     * di README_PERUBAHAN.txt.
     */
    'penandatangan' => [
        'irfan' => [
            'nama' => 'IRFAN MAULANA, S.Ak.',
            'jabatan' => 'Penelaah Teknis Kebijakan',
            'pangkat' => 'Penata Muda',
        ],
        'verri' => [
            'nama' => 'VERRI RIYANTI, M.S.P.',
            'jabatan' => 'Kepala Subbagian Tata Usaha',
            'pangkat' => 'Penata',
        ],
    ],

    /*
     * Role yang melihat tabel penuh (semua pegawai) tanpa verifikasi.
     *
     * Role lain - termasuk Pengguna Layanan yang masuk tanpa akun - wajib
     * memasukkan NIP + 4 digit akhir rekening lebih dulu, dan hanya menerima
     * baris miliknya sendiri. Penyaringannya di server, bukan di browser:
     * data pegawai lain tidak pernah dikirim. Lihat perubahan 22 di
     * README_PERUBAHAN.txt.
     */
    'role_data_penuh' => [
        'superadmin',
        'bendahara_pengeluaran',
        'kasubbag',
        'sekretaris',
        'inspektur',
    ],

    /*
     * Role yang boleh mengimpor berkas gaji/TPP dan menghapus dokumen pada
     * Daftar Rincian Penghasilan.
     */
    'role_kelola' => [
        'superadmin',
        'bendahara_pengeluaran',
    ],

    /*
     * Rekonsiliasi Gaji Induk: membandingkan Status Tunjangan Keluarga yang
     * DIKUNCI di awal bulan dengan status yang tersirat dari nominal gaji
     * bulan itu, lalu menaksir potensi kelebihan pembayarannya.
     *
     * Tunjangan pasangan = 10% x gaji pokok, tunjangan anak = 2% x gaji
     * pokok per anak (maksimal dua anak). Tiap jiwa juga membawa tunjangan
     * beras sebesar 'beras_per_jiwa'.
     *
     * Potensi kelebihan hanya dihitung untuk selisih yang MERUGIKAN negara
     * (penggajian membayar lebih banyak jiwa daripada yang berhak). Bila
     * sebaliknya, angkanya nol - kolomnya memang bernama "Potensi Kelebihan
     * Pembayaran" - tetapi selisihnya tetap terlihat pada kedua kolom status.
     */
    'rekonsiliasi' => [
        'persen_pasangan' => 0.10,
        'persen_anak' => 0.02,
        'beras_per_jiwa' => 72420,
        'maks_anak' => 2,
    ],

    /*
     * Hari libur nasional, dipakai menentukan tanggal penggajian: tanggal 1
     * tiap bulan, digeser ke hari berikutnya bila jatuh pada Sabtu, Minggu,
     * atau salah satu tanggal di bawah.
     *
     * PENTING: yang tercantum di sini hanya libur bertanggal TETAP. Libur
     * yang bergerak tiap tahun - Idulfitri, Iduladha, Tahun Baru Islam,
     * Maulid, Isra Mikraj, Nyepi, Wafat Isa Almasih, Kenaikan Isa Almasih,
     * Waisak, Imlek - beserta cuti bersamanya WAJIB ditambahkan sendiri tiap
     * tahun, kalau tidak tanggal penggajiannya bisa meleset.
     *
     * Format: 'Y-m-d' => 'Keterangan'.
     */
    'hari_libur' => [
        '2026-01-01' => 'Tahun Baru Masehi',
        '2026-05-01' => 'Hari Buruh Internasional',
        '2026-06-01' => 'Hari Lahir Pancasila',
        '2026-08-17' => 'Hari Kemerdekaan RI',
        '2026-12-25' => 'Hari Raya Natal',
    ],

    /*
     * Format nomor Surat Keterangan Penghasilan. :urut diisi nomor urut
     * GLOBAL (tidak reset), :bulan & :tahun adalah bulan dan tahun saat
     * dokumen DIBUAT - bukan periode penghasilannya.
     */
    'format_nomor' => ':urut/KET.PENGHASILAN/INSPEKTORAT/:bulan/:tahun',

];
