<?php

namespace App\Helpers;

/**
 * Normalisasi nomor handphone Indonesia ke bentuk yang diterima tautan
 * wa.me: hanya angka, diawali kode negara 62, tanpa tanda plus.
 *
 * Nomor di Data Pegawai/Vendor diketik bebas oleh petugas - "0812-3456-7890",
 * "+62 812 3456 7890", dan "812.3456.7890" adalah nomor yang sama dan harus
 * menghasilkan tautan yang sama.
 */
class NomorWhatsapp
{
    /** Panjang wajar nomor Indonesia berikut kode negara (62 + 9..13 digit). */
    private const MIN_DIGIT = 10;

    private const MAKS_DIGIT = 15;

    /** Bentuk siap-pakai untuk wa.me, atau null bila nomornya kosong/tidak masuk akal. */
    public static function normalisasi(?string $nomor): ?string
    {
        $digit = preg_replace('/\D+/', '', (string) $nomor) ?? '';

        if ($digit === '') {
            return null;
        }

        // Buang dulu semua nol di depan: menyeragamkan "0812...", "0062 812...",
        // dan "62 0812..." sekaligus. Sisanya "62..." (sudah berkode negara)
        // atau "812..." - bentuk yang muncul saat Excel memakan nol di depan.
        $digit = ltrim($digit, '0');

        if (str_starts_with($digit, '620')) {
            $digit = '62'.ltrim(substr($digit, 2), '0');
        }

        if (! str_starts_with($digit, '62')) {
            $digit = '62'.$digit;
        }

        $panjang = strlen($digit);

        if ($panjang < self::MIN_DIGIT || $panjang > self::MAKS_DIGIT) {
            return null;
        }

        return $digit;
    }

    /** Bentuk enak dibaca untuk ditampilkan di layar: "+62 812-3456-7890". */
    public static function tampilan(?string $nomor): ?string
    {
        $wa = self::normalisasi($nomor);

        if ($wa === null) {
            return null;
        }

        $sisa = substr($wa, 2);
        $pecah = array_filter([substr($sisa, 0, 3), substr($sisa, 3, 4), substr($sisa, 7)]);

        return '+62 '.implode('-', $pecah);
    }
}
