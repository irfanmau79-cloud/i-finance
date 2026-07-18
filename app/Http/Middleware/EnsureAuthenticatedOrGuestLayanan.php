<?php

namespace App\Http\Middleware;

use App\Helpers\GuestSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedOrGuestLayanan
{
    /**
     * Pengganti middleware 'auth' bawaan untuk route yang juga boleh
     * diakses tamu layanan (sesi tamu — lihat App\Helpers\GuestSession),
     * bukan cuma user yang benar-benar login. Middleware role:/menu-akses
     * di dalam grup tetap membatasi akses per menu seperti biasa; tamu
     * hanya lolos ke situ, tidak otomatis dapat akses tambahan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() || GuestSession::isActive()) {
            return $next($request);
        }

        return redirect()->guest(route('login'));
    }
}
