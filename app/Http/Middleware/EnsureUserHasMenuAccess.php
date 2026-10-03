<?php

namespace App\Http\Middleware;

use App\Helpers\GuestSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasMenuAccess
{
    /**
     * Menjaga route menu supaya hanya bisa diakses jika key dari parameter
     * middleware (atau {key} pada placeholder generik) ada di config('akses.menu') milik role
     * user yang login (atau tamu layanan) — mengikuti aturan visibilitas
     * menu yang sama dengan yang dipakai sidebar (layouts/app.blade.php).
     *
     * Boleh menyebut beberapa kunci: 'menu-akses:npd-data,invspj' lolos bila
     * role memegang SALAH SATUNYA. Dipakai untuk rute yang dibuka dari lebih
     * dari satu menu, mis. berkas SPJ (dari detail NPD maupun Inventarisasi
     * SPJ).
     */
    public function handle(Request $request, Closure $next, string ...$menuKeys): Response
    {
        $akses = config('akses.menu')[GuestSession::role()] ?? [];
        $menuKeys = $menuKeys ?: [(string) $request->route('key')];

        if (array_intersect($menuKeys, $akses) === []) {
            abort(403);
        }

        return $next($request);
    }
}
