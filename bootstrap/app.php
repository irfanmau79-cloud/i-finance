<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'menu-akses' => \App\Http\Middleware\EnsureUserHasMenuAccess::class,
            'auth.or.guest' => \App\Http\Middleware\EnsureAuthenticatedOrGuestLayanan::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Sesi/CSRF token kedaluwarsa (halaman dibiarkan terbuka lebih lama
        // dari SESSION_LIFETIME) sebelumnya menampilkan halaman kosong "419
        // Page Expired" - arahkan balik ke login dengan pesan yang jelas.
        // Handler::prepareException() sudah mengubah TokenMismatchException
        // jadi Symfony HttpException(419, ...) SEBELUM callback render()
        // custom sempat dicek, jadi type-hint di sini harus HttpException lalu
        // saring lewat status code - bukan TokenMismatchException langsung
        // (sudah dicoba, tidak pernah ke-trigger karena alasan itu).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            return redirect()->route('login')
                ->withErrors(['username' => 'Sesi Anda telah berakhir. Silakan login kembali.']);
        });
    })->create();
