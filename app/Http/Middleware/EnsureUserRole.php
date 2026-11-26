<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces authenticated role access on API routes.
 *
 * Usage in routes: ->middleware(['auth', 'role:admin']) or 'role:driver,admin'.
 * Guests receive 401/redirect; authenticated-but-wrong-role receives 403.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('login'));
        }

        if ($roles !== [] && ! $user->isAdmin() && ! in_array($user->role, $roles, true)) {
            return $request->expectsJson()
                ? response()->json([
                    'success' => false,
                    'message' => 'Forbidden. Required role: '.implode('|', $roles),
                ], 403)
                : abort(403);
        }

        return $next($request);
    }
}
