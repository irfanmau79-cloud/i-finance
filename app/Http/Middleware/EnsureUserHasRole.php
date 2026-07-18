<?php

namespace App\Http\Middleware;

use App\Helpers\GuestSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('role:pptk,bendahara')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array(GuestSession::role(), $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
