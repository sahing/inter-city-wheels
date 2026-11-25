<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production must expose no demonstration-only authentication surface.
 *
 * Demo mode lets a caller assume any role — including admin — without a
 * credential, so with `demo.enabled = false` those endpoints must 404 and the
 * generated OTP must never be echoed back.
 */
class DemoModeDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Simulate the production configuration.
        config(['demo.enabled' => false]);
    }

    public function test_one_tap_role_login_does_not_exist(): void
    {
        $this->postJson('/login/quick-demo', ['role' => 'admin'])->assertStatus(404);
        $this->postJson('/login/quick-demo', ['role' => 'customer'])->assertStatus(404);

        $this->assertGuest();
    }

    public function test_simulated_identity_providers_do_not_exist(): void
    {
        $this->getJson('/auth/alternate-config')->assertStatus(404);
        $this->postJson('/auth/alternate-login', ['provider' => 'firebase', 'email' => 'x@example.com'])->assertStatus(404);
        $this->postJson('/auth/firebase-login', ['email' => 'x@example.com'])->assertStatus(404);
        $this->postJson('/auth/clerk-login', ['email' => 'x@example.com'])->assertStatus(404);

        $this->assertGuest();
    }

    public function test_generated_otp_is_never_echoed_back(): void
    {
        $response = $this->postJson('/auth/send-otp', ['phone' => '9876543210']);
        $response->assertStatus(200);

        // No SMS gateway is configured in testing, so this takes the fallback
        // path — and the code still must not be returned to the caller.
        $this->assertNull($response->json('otp'));
        $this->assertNull($response->json('display_otp'));
    }

    public function test_ui_has_no_demo_affordances(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);

        $response->assertDontSee('Continue with one tap');
        $response->assertDontSee('Instant Demo Role Switch');
        $response->assertDontSee('Role Perspective Preview');
        $response->assertDontSee('Demo Accounts');
        $response->assertDontSee('Simulated Accounts');
        $response->assertDontSee('id="alternate-auth-modal"', false);

        // The body carries the class that hides any residual demo widget.
        $response->assertSee('no-demo', false);
    }

    public function test_demo_login_still_works_when_explicitly_enabled(): void
    {
        config(['demo.enabled' => true]);

        $this->postJson('/login/quick-demo', ['role' => 'customer'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
