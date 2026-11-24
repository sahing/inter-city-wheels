<?php

use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);

        // P0-3: CSRF protection is back on for every state-changing route,
        // including /api/* and the login/OTP/logout endpoints. The browser
        // attaches the X-CSRF-TOKEN header from <meta name="csrf-token"> in
        // public/js/app.js, so the SPA and plain form posts both keep working.
        // Do not add browser-reachable paths back to this list.
        $middleware->validateCsrfTokens(except: []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
