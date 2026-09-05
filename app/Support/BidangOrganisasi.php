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

    /** 7 bidang di Inspektorat (dipakai Tabel Detail SPJ - Inventarisasi SPJ) - PERJALANAN tanpa 'Struktural'. */
    public const SPJ = [
        'Sekretariat',
        'Subbagian Tata Usaha',
        'Inspektur Pembantu I',
        'Inspektur Pembantu II',
        'Inspektur Pembantu III',
        'Inspektur Pembantu IV',
        'Inspektur Pembantu Investigasi',
    ];

    /**
     * Lima unit pelaksana pengawasan, dalam urutan tetap yang dipakai
     * Monitoring PKPT & Estimasi Kebutuhan: Irban I s.d IV lalu Investigasi.
     * Sekretariat sengaja di luar - unit itu tidak punya PKPT.
     */
    public const PKPT = [
        'Inspektur Pembantu I',
        'Inspektur Pembantu II',
        'Inspektur Pembantu III',
        'Inspektur Pembantu IV',
        'Inspektur Pembantu Investigasi',
    ];

    /** Role Irban -> unit kerja yang dipegangnya. Role lain: null. */
    public static function unitRole(?string $role): ?string
    {
        return match ($role) {
            'irban1' => 'Inspektur Pembantu I',
            'irban2' => 'Inspektur Pembantu II',
            'irban3' => 'Inspektur Pembantu III',
            'irban4' => 'Inspektur Pembantu IV',
            'irban_inv' => 'Inspektur Pembantu Investigasi',
            default => null,
        };
    }

    /**
     * "Inspektur Pembantu III" -> "Irban III". Dipakai di kolom tabel & label
     * chart yang sempit; unit non-Irban dikembalikan apa adanya.
     */
    public static function singkat(?string $unit): string
    {
        $unit = trim((string) $unit);

        return $unit === 'Inspektur Pembantu Investigasi'
            ? 'Investigasi'
            : preg_replace('/^Inspektur Pembantu\s+/u', 'Irban ', $unit);
    }

    /** Urutan tetap unit PKPT; unit di luar daftar ditaruh di belakang. */
    public static function urutanPkpt(?string $unit): int
    {
        $posisi = array_search(trim((string) $unit), self::PKPT, true);

        return $posisi === false ? count(self::PKPT) : $posisi;
    }

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
