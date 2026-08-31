<?php

namespace App\Support;

/**
 * Pengurutan daftar pegawai pada modul Data Gaji & Tunjangan. Port dari
 * _skorGolPNS / _skorGradePPPK / _gtCmp / _gtSort di CodeGajiTunjangan.gs
 * (lihat perubahan 7 di README_PERUBAHAN.txt).
 *
 * Aturannya:
 *   1. PNS lebih dulu, PPPK selalu di bagian akhir.
 *   2. PNS  : golongan tertinggi dulu (IV/e > IV/d > ... > I/a).
 *   3. PPPK : grade tertinggi dulu (romawi murni, mis. IX sebelum VII).
 *   4. Bila golongan/grade sama: NIP terlama dulu (NIP ascending - NIP PNS
 *      diawali tanggal lahir YYYYMMDD, jadi kelahiran 1967 mendahului 1968).
 *
 * Berlaku sama untuk mode Bulanan maupun Kumulatif, di keempat sub-menu.
 */
class UrutanPegawaiGaji
{
    private const TINGKAT = ['IV' => 4, 'III' => 3, 'II' => 2, 'I' => 1];

    private const HURUF = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5];

    private const ROMAWI = [
        'I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6,
        'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10, 'XI' => 11,
        'XII' => 12, 'XIII' => 13, 'XIV' => 14, 'XV' => 15, 'XVI' => 16,
        'XVII' => 17,
    ];

    /**
     * Urutkan baris. Tiap baris minimal punya kunci: status, gol, nip.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function urutkan(array $rows): array
    {
        usort($rows, self::pembanding(...));

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function pembanding(array $a, array $b): int
    {
        $pa = self::isPppk($a['status'] ?? '') ? 1 : 0;
        $pb = self::isPppk($b['status'] ?? '') ? 1 : 0;

        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        $sa = $pa === 0 ? self::skorGolPns($a['gol'] ?? '') : self::skorGradePppk($a['gol'] ?? '');
        $sb = $pb === 0 ? self::skorGolPns($b['gol'] ?? '') : self::skorGradePppk($b['gol'] ?? '');

        if ($sa !== $sb) {
            return $sb <=> $sa;
        }

        $na = (string) ($a['nip'] ?? '');
        $nb = (string) ($b['nip'] ?? '');

        // Perbandingan panjang lebih dulu, seperti di GAS: NIP yang lebih
        // pendek (format lama / tidak baku) tidak boleh unggul hanya karena
        // perbandingan string leksikografis.
        if (mb_strlen($na) !== mb_strlen($nb)) {
            return mb_strlen($na) <=> mb_strlen($nb);
        }

        return strcmp($na, $nb);
    }

    public static function isPppk(mixed $status): bool
    {
        return str_contains(mb_strtoupper(trim((string) $status)), 'PPPK');
    }

    /** Skor golongan PNS, mis. IV/e = 45 (tertinggi) ... I/a = 11. 0 bila tak dikenal. */
    public static function skorGolPns(mixed $golongan): int
    {
        $gol = preg_replace('/\s+/', '', mb_strtoupper(trim((string) $golongan))) ?? '';

        if (! preg_match('#^(IV|III|II|I)/?([A-E])?$#', $gol, $m)) {
            return 0;
        }

        $tingkat = self::TINGKAT[$m[1]] ?? 0;
        $huruf = self::HURUF[$m[2] ?? 'A'] ?? 0;

        return $tingkat * 10 + $huruf;
    }

    /** Skor grade PPPK (romawi murni, mis. IX = 9). 0 bila tak dikenal. */
    public static function skorGradePppk(mixed $golongan): int
    {
        $gol = preg_replace('/\s+/', '', mb_strtoupper(trim((string) $golongan))) ?? '';

        return self::ROMAWI[$gol] ?? 0;
    }
}
