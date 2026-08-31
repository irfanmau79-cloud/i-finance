<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tahun Anggaran Operasional
    |--------------------------------------------------------------------------
    |
    | Satu sumber nilai sementara untuk seluruh guard import Prompt 12A.
    | Ganti mekanisme ini hanya melalui migrasi dan workflow rollover
    | multi-tahun yang khusus, bukan dengan menambah hardcode di controller.
    |
    */
    'tahun_aktif' => 2026,

    /*
    |--------------------------------------------------------------------------
    | Input Manual Sisa Anggaran pada NPD
    |--------------------------------------------------------------------------
    |
    | Saat true, pembuat NPD boleh mengetik sendiri angka Sisa Anggaran yang
    | TERCETAK DI PDF NPD. Angka itu tidak pernah dipakai perhitungan apa pun
    | di sistem - sisa tersedia, dana terikat, dan realisasi tetap dihitung
    | dari transaksi (NPD + SP2D LS) lewat MasterAnggaran.
    |
    | Setel false untuk mengunci kembali isiannya pada tahun anggaran
    | berikutnya. Mengunci HANYA menutup isian untuk NPD baru; NPD yang sudah
    | menyimpan angka manual tetap dicetak dengan angka itu supaya dokumen
    | yang sudah ditandatangani tetap sama saat dicetak ulang.
    |
    */
    'sisa_manual_npd' => true,

    /*
    |--------------------------------------------------------------------------
    | Masa Berlaku Staging Import (menit)
    |--------------------------------------------------------------------------
    |
    | Semua import memakai pola preview/dry-run: berkas di-parse ke tabel
    | staging (*_imports + *_import_rows), ditampilkan sebagai preview, lalu
    | baru ditulis ke data sebenarnya saat user menekan Konfirmasi. Nilai ini
    | membatasi berapa lama batch staging boleh menunggu di antara kedua
    | tahap itu sebelum berkasnya harus diunggah ulang.
    |
    | Dihitung dari created_at batch, BUKAN dari kolom expires_at - lihat
    | App\Models\Concerns\StagingKedaluwarsa untuk alasannya (MariaDB bisa
    | menimpa kolom expires_at sendiri).
    |
    | Default 120 menit disamakan dengan SESSION_LIFETIME supaya staging tidak
    | pernah mati lebih dulu daripada sesi login yang sedang memeriksanya.
    |
    */
    'menit_staging_import' => (int) env('IMPORT_STAGING_MENIT', 120),

];
