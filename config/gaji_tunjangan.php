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
     * Penandatangan Surat Keterangan Penghasilan. Di GAS ini konstanta
     * GT_PENANDATANGAN yang di-hardcode.
     *
     * Mengganti isi daftar ini TIDAK mengubah dokumen yang sudah pernah
     * dibuat: identitas penandatangan ikut dibekukan sebagai snapshot di
     * tabel rincian_penghasilan saat dokumen dibuat, jadi cetak ulang
     * dokumen lama tetap memakai pejabat yang dulu menandatanganinya.
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
     * Format nomor Surat Keterangan Penghasilan. :urut diisi nomor urut
     * GLOBAL (tidak reset), :bulan & :tahun adalah bulan dan tahun saat
     * dokumen DIBUAT - bukan periode penghasilannya.
     */
    'format_nomor' => ':urut/KET.PENGHASILAN/INSPEKTORAT/:bulan/:tahun',

];
