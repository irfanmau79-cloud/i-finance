<?php

namespace App\Support;

final class BidangOrganisasi
{
    public const PERJALANAN = [
        'Struktural',
        'Inspektur Pembantu I',
        'Inspektur Pembantu II',
        'Inspektur Pembantu III',
        'Inspektur Pembantu IV',
        'Inspektur Pembantu Investigasi',
        'Sekretariat',
        'Subbagian Tata Usaha',
    ];

    public const PENGAWASAN = [
        'Inspektur Pembantu I',
        'Inspektur Pembantu II',
        'Inspektur Pembantu III',
        'Inspektur Pembantu IV',
        'Inspektur Pembantu Investigasi',
        'Sekretariat',
    ];

    public static function petakan(?string $nilai, bool $khususPengawasan = false): ?string
    {
        $teks = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $nilai)));
        if ($teks === '') {
            return null;
        }

        $hasil = match (true) {
            str_contains($teks, 'investigas') => 'Inspektur Pembantu Investigasi',
            str_contains($teks, 'sekret') => 'Sekretariat',
            str_contains($teks, 'subbag') || str_contains($teks, 'tata usaha') => 'Subbagian Tata Usaha',
            str_contains($teks, 'struktur') => 'Struktural',
            self::irban($teks, 'iv') => 'Inspektur Pembantu IV',
            self::irban($teks, 'iii') => 'Inspektur Pembantu III',
            self::irban($teks, 'ii') => 'Inspektur Pembantu II',
            self::irban($teks, 'i') => 'Inspektur Pembantu I',
            default => null,
        };

        return $khususPengawasan && ! in_array($hasil, self::PENGAWASAN, true) ? null : $hasil;
    }

    private static function irban(string $teks, string $romawi): bool
    {
        if (! str_contains($teks, 'irban') && ! str_contains($teks, 'pembantu') && ! preg_match('/\birb/u', $teks)) {
            return false;
        }

        return (bool) preg_match('/(^|[^a-z])'.preg_quote($romawi, '/').'([^a-z]|$)/u', $teks);
    }
}
