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
     * Penanda yang tersedia: :nomor_npd, :frasa_sp, :nominal, :aplikasi.
     * :frasa_sp otomatis kosong bila NPD tidak tertaut Surat Perintah.
     */
    'template_npd_selesai' => 'Izin menginformasikan Bapak/Ibu, Pencairan NPD Nomor :nomor_npd:frasa_sp sebesar Rp:nominal telah selesai ditransaksikan. Untuk informasi dan fitur cetak SPJ, mohon kunjungi aplikasi kami :aplikasi. Hatur nuhun 🙏',

    /** Disisipkan ke :frasa_sp hanya bila NPD punya Surat Perintah. */
    'frasa_sp' => ' atas SP Nomor :nomor_sp',

];
