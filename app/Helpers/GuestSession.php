<?php

namespace App\Helpers;

/**
 * "Sesi tamu" murni tampilan untuk role layanan (Pengguna Layanan) — publik,
 * tanpa username/password. BUKAN otentikasi Laravel sungguhan: cuma flag di
 * session, supaya middleware & sidebar yang sudah ada bisa memperlakukan
 * tamu seolah "masuk" dengan role 'layanan'. Dibersihkan begitu saja lewat
 * session()->invalidate() di AuthController::logout().
 */
class GuestSession
{
    private const SESSION_KEY = 'guest_layanan';

    /** Kunci session-nya, dipakai test untuk melewati gerbang kata sandi. */
    public static function kunciSesi(): string
    {
        return self::SESSION_KEY;
    }

    public static function login(): void
    {
        if (! auth()->check()) {
            session([self::SESSION_KEY => true]);
        }
    }

    public static function isActive(): bool
    {
        return ! auth()->check() && (bool) session(self::SESSION_KEY);
    }

    /** Role user yang login sungguhan, atau 'layanan' kalau sesi tamu aktif. */
    public static function role(): ?string
    {
        return auth()->user()?->role ?? (self::isActive() ? 'layanan' : null);
    }
}
