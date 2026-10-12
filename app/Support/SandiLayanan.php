<?php

namespace App\Support;

use App\Models\Pengaturan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Kata sandi bersama Pengguna Layanan (lihat AuthController::masukLayanan).
 *
 * Pengguna Layanan bukan akun - tidak ada baris di tabel users - tetapi
 * kata sandinya kini bisa diganti superadmin dari Manajemen Users. Yang
 * diganti dari sana disimpan sebagai HASH di tabel pengaturan, jadi tidak
 * bisa dibaca kembali oleh siapa pun.
 *
 * Selama belum pernah diganti dari aplikasi, yang berlaku tetap nilai
 * SANDI_LAYANAN pada .env (config akses.sandi_layanan). Begitu diganti,
 * nilai .env itu tidak dipakai lagi.
 */
class SandiLayanan
{
    private const KUNCI = 'sandi_layanan_hash';

    public const MIN_PANJANG = 6;

    public static function cocok(string $sandi): bool
    {
        $hash = Pengaturan::ambil(self::KUNCI)?->nilai;

        if (filled($hash)) {
            return Hash::check($sandi, $hash);
        }

        return hash_equals((string) config('akses.sandi_layanan'), $sandi);
    }

    public static function ubah(string $sandiBaru): void
    {
        Pengaturan::simpan(self::KUNCI, Hash::make($sandiBaru));
    }

    /** Kapan terakhir diganti dari aplikasi; null = masih memakai nilai .env. */
    public static function terakhirDiubah(): ?Carbon
    {
        $baris = Pengaturan::ambil(self::KUNCI);

        return filled($baris?->nilai) ? $baris->updated_at : null;
    }
}
