<?php

namespace App\Http\Middleware;

use App\Helpers\GuestSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak role baca-saja (lihat config('akses.role_baca_saja')) pada rute
 * yang MENGUBAH data.
 *
 * Kebanyakan aksi ubah di aplikasi ini sudah dijaga daftar-izin role yang
 * ditulis eksplisit, sehingga role baru otomatis tertutup di sana. Yang tidak
 * tertutup adalah rute pengubah data yang hanya dijaga menu-akses: di situ
 * siapa pun yang memegang kunci menunya ikut boleh mengubah. Middleware ini
 * dipasang pada rute-rute tersebut supaya role pemantau bisa membuka
 * halamannya tanpa ikut mendapat hak ubahnya.
 *
 * Dipakai berdampingan dengan menu-akses, bukan menggantikannya.
 *
 * Usage: ->middleware(['menu-akses:analisis', 'baca-saja'])
 */
class EnsureRoleBukanBacaSaja
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(GuestSession::role(), config('akses.role_baca_saja', []), true)) {
            abort(403);
        }

        return $next($request);
    }
}
