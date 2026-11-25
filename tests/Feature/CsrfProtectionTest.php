<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0-3 — CSRF is enforced on browser-reachable state-changing routes.
 *
 * Laravel's CSRF middleware short-circuits when the app reports itself as
 * running unit tests, so these tests switch the app environment for the request
 * to observe the real behaviour instead of the test shortcut.
 */
class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function actingAsRealBrowserApp(): void
    {
        // Disables the "running unit tests" bypass inside ValidateCsrfToken.
        $this->app['env'] = 'production';
    }

    public function test_api_post_without_a_token_is_rejected(): void
    {
        $this->actingAsRealBrowserApp();

        $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', [
            'service_type' => 'cab',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Baner',
            'customer_name' => 'No Token',
            'customer_phone' => '+91 90000 12345',
        ])->assertStatus(419);
    }

    public function test_login_post_without_a_token_is_rejected(): void
    {
        $this->actingAsRealBrowserApp();

        $this->postJson('/login', [
            'email' => 'customer@intercitywheels.com',
            'password' => 'password123',
        ])->assertStatus(419);
    }

    public function test_admin_write_without_a_token_is_rejected(): void
    {
        $this->actingAsRealBrowserApp();

        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/admin/settings/toggle-feature', ['enabled' => true])
            ->assertStatus(419);
    }

    public function test_request_with_a_valid_token_is_accepted(): void
    {
        $this->actingAsRealBrowserApp();

        $customer = User::where('role', 'customer')->firstOrFail();

        $this->actingAs($customer)
            ->withSession(['_token' => 'known-token'])
            ->withHeader('X-CSRF-TOKEN', 'known-token')
            ->postJson('/api/bookings', [
                'service_type' => 'cab',
                'trip_type' => 'oneway',
                'from_city' => 'Pune',
                'to_city' => 'Goa',
                'pickup_location' => 'Baner',
                'drop_location' => 'Panjim',
                'customer_name' => 'With Token',
                'customer_phone' => '+91 90000 12346',
            ])->assertStatus(200);
    }

    public function test_home_page_ships_a_csrf_meta_tag_for_the_spa(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee('name="csrf-token"', false);
    }
}
