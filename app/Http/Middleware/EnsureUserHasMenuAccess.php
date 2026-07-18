<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasMenuAccess
{
    /**
     * Menjaga route placeholder generik (/menu/{key}) supaya hanya
     * bisa diakses jika {key} ada di config('akses.menu') milik role
     * user yang login — mengikuti aturan visibilitas menu yang sama
     * dengan yang dipakai sidebar (layouts/app.blade.php).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $akses = config('akses.menu')[$request->user()?->role] ?? [];

        if (! in_array($request->route('key'), $akses, true)) {
            abort(403);
        }

        return $next($request);
    }
}
