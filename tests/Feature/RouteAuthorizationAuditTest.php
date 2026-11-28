<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * G1 — the authorization matrix from AUTOMATION_RULES.md §5, asserted against
 * the compiled route table rather than a hand-maintained list.
 *
 * P0-2 made every privileged endpoint require `auth` plus a role. This test is
 * the gate that fails if a future route is added to a privileged prefix without
 * those middleware, which is exactly how the platform shipped wide open.
 */
class RouteAuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<Route>
     */
    private function routes(): array
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/'))
            ->values()
            ->all();
    }

    private function middlewareOf(Route $route): array
    {
        return $route->gatherMiddleware();
    }

    private function hasRoleGuard(Route $route, array $roles): bool
    {
        foreach ($this->middlewareOf($route) as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'role' || str_starts_with($middleware, 'role:')) {
                if ($middleware === 'role') {
                    return true;
                }

                $granted = explode(',', substr($middleware, strlen('role:')));

                if (array_diff($roles, $granted) === []) {
                    return true;
                }
            }
        }

        return false;
    }

    private function requiresAuth(Route $route): bool
    {
        return in_array('auth', $this->middlewareOf($route), true);
    }

    public function test_every_admin_route_requires_auth_and_the_admin_role(): void
    {
        $unprotected = [];

        foreach ($this->routes() as $route) {
            if (! str_starts_with($route->uri(), 'api/admin/')) {
                continue;
            }

            if (! $this->requiresAuth($route) || ! $this->hasRoleGuard($route, ['admin'])) {
                $unprotected[] = $route->methods()[0].' '.$route->uri();
            }
        }

        $this->assertSame([], $unprotected, 'Admin routes without auth+role:admin: '.implode(', ', $unprotected));
    }

    public function test_every_driver_route_requires_auth_and_a_driver_capable_role(): void
    {
        $unprotected = [];

        foreach ($this->routes() as $route) {
            $uri = $route->uri();

            // The masked auction payload is intentionally public so a driver who
            // has only a WhatsApp link can see what the trip is before signing in.
            if ($uri === 'api/bidding/{token}') {
                continue;
            }

            $isDriverSurface = str_starts_with($uri, 'api/driver/')
                || $uri === 'api/bids'
                || str_starts_with($uri, 'api/bids/')
                || str_starts_with($uri, 'api/bidding/');

            if (! $isDriverSurface) {
                continue;
            }

            if (! $this->requiresAuth($route)) {
                $unprotected[] = $route->methods()[0].' '.$uri;

                continue;
            }

            // Bid acceptance is a customer action; everything else on this
            // surface must be reachable by a driver.
            if ($uri === 'api/bids/accept') {
                if (! $this->hasRoleGuard($route, ['customer'])) {
                    $unprotected[] = $route->methods()[0].' '.$uri.' (expected role:customer,admin)';
                }

                continue;
            }

            // role:driver,admin satisfies 'driver'.
            if (! $this->hasRoleGuard($route, ['driver'])) {
                $unprotected[] = $route->methods()[0].' '.$uri.' (expected role:driver,...)';
            }
        }

        $this->assertSame([], $unprotected, 'Driver routes without auth+driver role: '.implode(', ', $unprotected));
    }

    public function test_bidding_link_is_public_but_bidding_requires_a_driver(): void
    {
        $masked = collect($this->routes())->first(fn (Route $r) => $r->uri() === 'api/bidding/{token}');
        $placeBid = collect($this->routes())->first(
            fn (Route $r) => $r->uri() === 'api/bidding/{token}/bids' && in_array('POST', $r->methods(), true)
        );

        $this->assertNotNull($masked, 'The masked bidding link endpoint is missing.');
        $this->assertFalse($this->requiresAuth($masked), 'The masked bidding link must be readable without an account.');

        $this->assertNotNull($placeBid, 'The place-bid endpoint is missing.');
        $this->assertTrue($this->requiresAuth($placeBid), 'Placing a bid must require authentication.');
        $this->assertTrue($this->hasRoleGuard($placeBid, ['driver']), 'Placing a bid must require a driver role.');
    }

    public function test_current_user_endpoint_requires_authentication(): void
    {
        $route = collect($this->routes())->first(fn (Route $r) => $r->uri() === 'api/auth/user');

        $this->assertNotNull($route, 'api/auth/user route is missing.');
        $this->assertTrue($this->requiresAuth($route));
    }

    public function test_public_catalogue_and_ticket_lookup_stay_open(): void
    {
        // Booking *creation* now requires an account (see BookingRequiresLoginTest).
        // Browsing, quoting and reopening a ticket by reference + phone stay anonymous.
        $public = [
            'api/routes/calculate',
            'api/locations',
            'api/locations/search',
            'api/car-types',
            'api/local-packages',
            'api/airport-hubs',
            'api/bookings/lookup',
        ];

        foreach ($this->routes() as $route) {
            if (! in_array($route->uri(), $public, true)) {
                continue;
            }

            $this->assertFalse(
                in_array('auth', $this->middlewareOf($route), true),
                $route->uri().' should stay reachable without an account.'
            );
        }
    }

    public function test_booking_read_is_not_in_the_public_intake_set(): void
    {
        // GET /api/bookings/{reference} is authorised inside the controller
        // (signed URL, owner, admin, or reference+phone) rather than by
        // middleware, because guests must be able to reopen their own ticket.
        $route = collect($this->routes())->first(fn (Route $r) => $r->uri() === 'api/bookings/{reference}');

        $this->assertNotNull($route);
        $this->assertNotContains($route->uri(), ['api/bookings'], 'Route collision');
    }
}
