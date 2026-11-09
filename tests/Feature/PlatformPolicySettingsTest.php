<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PlatformSetting;

class PlatformPolicySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->first();
        $this->customer = User::where('role', 'customer')->first();
    }

    public function test_login_popup_contains_small_terms_and_privacy_notice(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify the login modal contains the small notice
        $response->assertSee('By proceeding, you agree to', false);
        $response->assertSee('Privacy Policy', false);
        $response->assertSee('T&Cs', false);
        $response->assertSee('id="login-link-privacy"', false);
        $response->assertSee('id="login-link-terms"', false);
    }

    public function test_admin_can_update_policy_and_terms_urls(): void
    {
        $payload = [
            'privacy_policy_url' => 'https://policies.intercitywheels.com/privacy-v2',
            'terms_conditions_url' => 'https://policies.intercitywheels.com/terms-v2'
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/policy-urls', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'settings' => $payload
            ]);

        $this->assertEquals('https://policies.intercitywheels.com/privacy-v2', PlatformSetting::get('privacy_policy_url'));
        $this->assertEquals('https://policies.intercitywheels.com/terms-v2', PlatformSetting::get('terms_conditions_url'));

        // Verify the updated URLs are rendered in HTML
        $homeRes = $this->get('/');
        $homeRes->assertSee('href="https://policies.intercitywheels.com/privacy-v2"', false);
        $homeRes->assertSee('href="https://policies.intercitywheels.com/terms-v2"', false);
    }

    public function test_non_admin_cannot_update_policy_urls(): void
    {
        $payload = [
            'privacy_policy_url' => '/hacked-privacy',
            'terms_conditions_url' => '/hacked-terms'
        ];

        $response = $this->actingAs($this->customer)->postJson('/api/admin/settings/policy-urls', $payload);
        $response->assertStatus(403);
    }

    public function test_public_legal_routes_render_successfully(): void
    {
        $privRes = $this->get('/privacy-policy');
        $privRes->assertStatus(200)->assertSee('IntercityWheels Privacy Policy');

        $termsRes = $this->get('/terms-conditions');
        $termsRes->assertStatus(200)->assertSee('Terms of Service');
    }
}
