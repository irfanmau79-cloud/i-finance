<?php

namespace App\Helpers;

/**
 * Konversi angka ke terbilang Bahasa Indonesia.
 *
 * Logika bagi() adalah port 1:1 dari fungsi terbilang() di gas-lama/Code.gs,
 * termasuk kasus khusus "seratus", "seribu", dan "sebelas".
 */
class Terbilang
{
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    /**
     * Format "... Rupiah" (+ "... Sen" hanya kalau sen-nya tidak nol).
     */
    public static function rupiah(float|int|string $angka): string
    {
        $totalSen = (int) round(abs((float) $angka) * 100);

        $rupiah = intdiv($totalSen, 100);
        $sen = $totalSen % 100;

        $hasilRupiah = $rupiah === 0 ? 'Nol' : self::kapital(self::bagi($rupiah));
        $hasil = "{$hasilRupiah} Rupiah";

        if ($sen !== 0) {
            $hasil .= ' '.self::kapital(self::bagi($sen)).' Sen';
        }

        return $hasil;
    }

    private static function kapital(string $kalimat): string
    {
        return implode(' ', array_map(
            fn (string $kata) => $kata === '' ? $kata : ucfirst($kata),
            explode(' ', $kalimat)
        ));
    }

    private static function bagi(int $n): string
    {
        if ($n < 12) {
            $h = self::SATUAN[$n];
        } elseif ($n < 20) {
            $h = self::SATUAN[$n - 10].' belas';
        } elseif ($n < 100) {
            $h = self::SATUAN[intdiv($n, 10)].' puluh'.
                ($n % 10 ? ' '.self::SATUAN[$n % 10] : '');
        } elseif ($n < 200) {
            $h = 'seratus'.($n - 100 ? ' '.self::bagi($n - 100) : '');
        } elseif ($n < 1000) {
            $h = self::SATUAN[intdiv($n, 100)].' ratus'.
                ($n % 100 ? ' '.self::bagi($n % 100) : '');
        } elseif ($n < 2000) {
            $h = 'seribu'.($n - 1000 ? ' '.self::bagi($n - 1000) : '');
        } elseif ($n < 1_000_000) {
            $h = self::bagi(intdiv($n, 1000)).' ribu'.
                ($n % 1000 ? ' '.self::bagi($n % 1000) : '');
        } elseif ($n < 1_000_000_000) {
            $h = self::bagi(intdiv($n, 1_000_000)).' juta'.
                ($n % 1_000_000 ? ' '.self::bagi($n % 1_000_000) : '');
        } elseif ($n < 1_000_000_000_000) {
            $h = self::bagi(intdiv($n, 1_000_000_000)).' miliar'.
                ($n % 1_000_000_000 ? ' '.self::bagi($n % 1_000_000_000) : '');
        } else {
            $h = self::bagi(intdiv($n, 1_000_000_000_000)).' triliun'.
                ($n % 1_000_000_000_000 ? ' '.self::bagi($n % 1_000_000_000_000) : '');
        }

        return trim($h);
    }
}
