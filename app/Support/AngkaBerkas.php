<?php

namespace App\Support;

/**
 * Pembaca angka dari sel berkas import.
 *
 * "1.250.000,50", "250.000", "Rp 1.250.000", maupun angka asli dari Excel
 * sama-sama diterima; sel kosong dihitung 0.
 *
 * Titik TIDAK bisa langsung dianggap pemisah desimal: is_numeric() menerima
 * "250.000" dan membacanya 250 - selisih seribu kali lipat pada angka
 * rupiah. Jadi titik baru dibuang bila polanya memang pemisah ribuan
 * (kelompok tepat tiga digit), dan "250.5" tetap dibaca desimal.
 */
class AngkaBerkas
{
    public static function dari(mixed $nilai): float
    {
        // Sel bertipe angka dari Excel dipakai apa adanya - tidak ada
        // pemisah yang perlu ditafsirkan.
        if (is_int($nilai) || is_float($nilai)) {
            return round((float) $nilai, 2);
        }

        $bersih = preg_replace('/[^\d,.\-]/', '', trim((string) $nilai));

        if ($bersih === '' || $bersih === null) {
            return 0.0;
        }

        if (str_contains($bersih, ',')) {
            // Format Indonesia: titik ribuan, koma desimal.
            $bersih = str_replace(['.', ','], ['', '.'], $bersih);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $bersih) === 1) {
            $bersih = str_replace('.', '', $bersih);
        }

        return is_numeric($bersih) ? round((float) $bersih, 2) : 0.0;
    }
}
