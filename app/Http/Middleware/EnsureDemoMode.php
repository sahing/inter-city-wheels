<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks demonstration-only endpoints unless demo mode is enabled.
 *
 * These endpoints (one-tap role login, simulated Firebase/Clerk providers)
 * authenticate a caller without any credential, so in production they must
 * behave as though they never existed.
 */
class EnsureDemoMode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('demo.enabled'), 404);

        return $next($request);
    }
}
