<?php

namespace App\Http\Middleware;

use App\Helpers\GuestSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang halaman Pengguna Layanan (Input SP, Monitoring SP, Cetak SPJ,
 * Perubahan Tunjangan Keluarga).
 *
 * Halaman-halaman itu memang tanpa akun - tidak ada pendaftaran - tetapi
 * sejak aplikasi dihosting tidak boleh lagi terbuka bebas. Yang boleh lewat:
 * user yang benar-benar login, atau tamu yang sudah memasukkan kata sandi
 * bersama di halaman login (lihat AuthController::masukLayanan).
 *
 * Yang ditolak dikembalikan ke halaman login dengan penanda supaya jendela
 * "Masukkan Kata Sandi" langsung terbuka, bukan sekadar dilempar ke formulir
 * login yang tidak dia punya akunnya.
 */
class EnsureGerbangLayanan
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() || GuestSession::isActive()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Masukkan kata sandi Pengguna Layanan terlebih dahulu.');
        }

        return redirect()
            ->guest(route('login'))
            ->with('buka_gerbang_layanan', true);
    }
}
