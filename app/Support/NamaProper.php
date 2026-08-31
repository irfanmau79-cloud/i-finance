<?php

namespace App\Support;

/**
 * Proper Case untuk nama & jabatan pada Surat Keterangan Penghasilan. Port
 * dari _gtProper() di CodeGajiTunjangan.gs (lihat perubahan 15 & 16 di
 * README_PERUBAHAN.txt).
 *
 * Dua aturan yang membuatnya bukan sekadar ucwords():
 *
 *  1. Bagian SESUDAH koma dibiarkan apa adanya, karena di situlah gelar
 *     berada: "IRFAN MAULANA, S.Ak." -> "Irfan Maulana, S.Ak." (bukan
 *     "S.ak." atau "S.AK.").
 *  2. Kata sambung tetap huruf kecil kecuali bila menjadi kata pertama:
 *     "PENGOLAH DATA DAN INFORMASI" -> "Pengolah Data dan Informasi".
 */
class NamaProper
{
    /** @var array<int, string> */
    private const KONJUNGSI = [
        'dan', 'di', 'ke', 'dari', 'yang', 'untuk', 'atau',
        'pada', 'dengan', 'atas', 'oleh', 'the', 'of',
    ];

    public static function format(?string $teks): string
    {
        $teks = (string) $teks;
        $koma = mb_strpos($teks, ',');

        $inti = $koma === false ? $teks : mb_substr($teks, 0, $koma);
        $sisa = $koma === false ? '' : mb_substr($teks, $koma);

        $kata = preg_split('/\s+/u', mb_strtolower($inti)) ?: [];

        $hasil = [];

        foreach ($kata as $index => $satu) {
            if ($satu === '') {
                $hasil[] = $satu;

                continue;
            }

            $hasil[] = $index > 0 && in_array($satu, self::KONJUNGSI, true)
                ? $satu
                : mb_strtoupper(mb_substr($satu, 0, 1)).mb_substr($satu, 1);
        }

        return implode(' ', $hasil).$sisa;
    }
}
