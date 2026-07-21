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
     */
    public function handle(Request $request, Closure $next, ?string $menuKey = null): Response
    {
        $akses = config('akses.menu')[GuestSession::role()] ?? [];
        $menuKey ??= $request->route('key');

        if (! in_array($menuKey, $akses, true)) {
            abort(403);
        }

        return $next($request);
    }
}
