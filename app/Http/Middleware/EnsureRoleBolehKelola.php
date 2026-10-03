<?php

namespace App\Http\Middleware;

use App\Helpers\GuestSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga rute yang MENGUBAH data pada menu yang pembacanya lebih luas
 * daripada pengelolanya (lihat config('akses.kelola')).
 *
 * Contoh: Data Realisasi SP2D boleh dibuka banyak role, tetapi menambah dan
 * menyunting SP2D hanya milik superadmin dan Bendahara Pengeluaran. Kunci
 * menu yang tidak terdaftar di config dianggap tidak punya pengelola, jadi
 * salah ketik berujung 403 - bukan terbuka untuk semua.
 *
 * Usage: ->middleware('kelola:spm')
 */
class EnsureRoleBolehKelola
{
    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        if (! in_array(GuestSession::role(), config('akses.kelola.'.$menuKey, []), true)) {
            abort(403);
        }

        return $next($request);
    }
}
