<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notifikasi WhatsApp Pencairan NPD
    |--------------------------------------------------------------------------
    |
    | Kanal pengiriman saat ini: DEEP LINK (wa.me). Aplikasi hanya menyiapkan
    | teks pesan dan membuka WhatsApp Web/Desktop milik petugas - pengiriman
    | tetap ditekan manual oleh petugas, tidak ada kredensial gateway dan
    | tidak ada risiko nomor kantor diblokir Meta. Bila kelak pindah ke
    | gateway/Cloud API, yang berubah cukup lapisan pengirimnya; template,
    | pencatatan riwayat, dan otorisasi di bawah ini tetap sama.
    |
    */

    /** Tautan aplikasi yang disebut di akhir pesan. */
    'tautan_aplikasi' => env('WA_TAUTAN_APLIKASI', 'i-finance.web.id'),

    /**
     * Penanda yang tersedia: :penerima, :nomor_npd, :frasa_sp, :nominal,
     * :rincian, :aplikasi.
     *
     * :nominal adalah TOTAL seluruh penerima, bukan jatah satu orang -
     * nilainya sama di semua pesan untuk NPD yang sama, sedangkan rinciannya
     * menyebut jatah masing-masing.
     *
     * :frasa_sp otomatis kosong bila NPD tidak tertaut Surat Perintah, dan
     * :rincian kosong bila penerimanya cuma satu (menyebut rincian satu
     * baris hanya mengulang totalnya).
     *
     * Tautan unduh ditulis pada BARIS SENDIRI: kalau menempel pada kata
     * sebelumnya, WhatsApp salah mendeteksi batas tautannya (GAS #72).
     */
    'template_npd_selesai' => "Yth. Bapak/Ibu :penerima, NPD Nomor :nomor_npd:frasa_sp telah selesai ditransaksikan sebesar Rp:nominal.:rincian

"
        ."Dokumen Daftar Pembayaran dapat diunduh melalui:
:aplikasi

"
        ."Cara:
"
        ."1. Masuk sebagai Pengguna Layanan (Tanpa Login)
"
        ."2. Buka menu Surat Perintah
"
        ."3. Pilih Cetak SPJ Perjalanan Dinas
"
        ."4. Masukkan Nomor SP

"
        .'Hatur nuhun Bapak/Ibu.',

    /** Disisipkan ke :frasa_sp hanya bila NPD punya Surat Perintah. */
    'frasa_sp' => ' atas SP Nomor :nomor_sp',

    /** Pembuka daftar rincian; tiap penerima ditulis bernomor di bawahnya. */
    'judul_rincian' => ' Rincian Penerima adalah sebagai berikut:',

];
