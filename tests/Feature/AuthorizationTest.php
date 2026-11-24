<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

/**
 * Proves the privilege boundary actually holds.
 *
 * Rule R5: every privileged endpoint needs a negative case. `actingAs()`
 * alone masks a missing middleware, which is precisely how this codebase
 * reported 0 failures while every admin route was public.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function userFor(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    /** @test */
    public function guest_cannot_reach_admin_api(): void
    {
        $this->getJson('/api/admin/bookings')->assertStatus(401);
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->postJson('/api/admin/users', [])->assertStatus(401);
        $this->getJson('/api/admin/settings')->assertStatus(401);
        $this->deleteJson('/api/admin/vehicles/1')->assertStatus(401);
    }

    /** @test */
    public function customer_cannot_reach_admin_api(): void
    {
        $customer = $this->userFor('customer');

        $this->actingAs($customer)->getJson('/api/admin/bookings')->assertStatus(403);
        $this->actingAs($customer)->getJson('/api/admin/users')->assertStatus(403);
        $this->actingAs($customer)
            ->postJson('/api/admin/settings/policy-urls', ['privacy_policy_url' => '/x'])
            ->assertStatus(403);
        $this->actingAs($customer)->postJson('/api/admin/dynamic-fields', [])->assertStatus(403);
    }

    /** @test */
    public function driver_cannot_reach_admin_api(): void
    {
        $driver = $this->userFor('driver');
        $this->actingAs($driver)->getJson('/api/admin/bookings')->assertStatus(403);
    }

    /** @test */
    public function admin_can_reach_admin_api(): void
    {
        $admin = $this->userFor('admin');
        $this->actingAs($admin)->getJson('/api/admin/bookings')->assertStatus(200);
    }

    /** @test */
    public function guest_cannot_reach_driver_console(): void
    {
        $this->getJson('/api/bids')->assertStatus(401);
        $this->postJson('/api/driver/toggle-online')->assertStatus(401);
        $this->getJson('/api/driver/profile')->assertStatus(401);
    }

    /** @test */
    public function customer_cannot_reach_driver_console(): void
    {
        $customer = $this->userFor('customer');

        $this->actingAs($customer)->getJson('/api/bids')->assertStatus(403);
        $this->actingAs($customer)->postJson('/api/driver/toggle-online')->assertStatus(403);
        $this->actingAs($customer)->getJson('/api/driver/profile')->assertStatus(403);
    }

    /** @test */
    public function driver_can_reach_driver_console(): void
    {
        $driver = $this->userFor('driver');
        $this->actingAs($driver)->getJson('/api/driver/profile')->assertStatus(200);
    }

    /** @test */
    public function guest_must_be_authenticated_for_current_user_endpoint(): void
    {
        $this->getJson('/api/auth/user')->assertStatus(401);
    }

    /** @test */
    public function public_catalogue_and_booking_intake_remain_open(): void
    {
        // Guests must still be able to browse and book.
        $this->getJson('/api/locations')->assertStatus(200);
        $this->getJson('/api/car-types')->assertStatus(200);
        $this->getJson('/api/local-packages')->assertStatus(200);
        $this->getJson('/api/airport-hubs')->assertStatus(200);
        $this->getJson('/api/routes/calculate?from=Kolkata&to=Digha')->assertStatus(200);
    }
}
