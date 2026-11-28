<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
            'terms_conditions_url' => 'https://policies.intercitywheels.com/terms-v2',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/policy-urls', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'settings' => $payload,
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
            'terms_conditions_url' => '/hacked-terms',
        ];

        $response = $this->actingAs($this->customer)->postJson('/api/admin/settings/policy-urls', $payload);
        $response->assertStatus(403);
    }

    public function test_public_legal_routes_render_successfully(): void
    {
        $privRes = $this->get('/privacy-policy');
        $privRes->assertStatus(200)
            ->assertSee('IntercityWheels Privacy Policy')
            ->assertDontSee('Edit Content');

        $termsRes = $this->get('/terms-conditions');
        $termsRes->assertStatus(200)->assertSee('Terms of Service');

        // Admin sees direct "Edit Content" shortcut on legal pages
        $this->actingAs($this->admin)->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Edit Content');
    }

    public function test_admin_can_edit_privacy_policy_and_terms_page_content(): void
    {
        $payload = [
            'privacy_policy_title' => 'Updated Privacy Charter',
            'privacy_policy_updated_at' => 'December 2026',
            'privacy_policy_body' => "We respect rider privacy.\n\n1. GPS Usage\nGPS coordinates are used only during active trips.\n<script>alert('xss')</script>",
            'terms_conditions_title' => 'Updated Rider Terms',
            'terms_conditions_updated_at' => 'December 2026',
            'terms_conditions_body' => 'All bookings are governed by updated terms.',
            'legal_contact_helpline' => '1800-999-0000',
            'legal_contact_email' => 'legal@intercitywheels.com',
        ];

        $this->actingAs($this->customer)
            ->postJson('/api/admin/settings/legal-pages', $payload)
            ->assertStatus(403);

        $res = $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/legal-pages', $payload);

        $res->assertOk()->assertJsonPath('success', true);

        $privPage = $this->get('/privacy-policy');
        $privPage->assertOk()
            ->assertSee('Updated Privacy Charter')
            ->assertSee('Last Updated: December 2026')
            ->assertSee('We respect rider privacy.')
            ->assertSee('GPS Usage')
            ->assertSee('1800-999-0000')
            ->assertSee('legal@intercitywheels.com')
            ->assertDontSee("<script>alert('xss')</script>", false);

        $this->get('/terms-conditions')
            ->assertOk()
            ->assertSee('Updated Rider Terms')
            ->assertSee('All bookings are governed by updated terms.');

        // Admin settings screen renders the editable form card
        $this->actingAs($this->admin)->get('/')
            ->assertOk()
            ->assertSee('admin-legal-pages-card', false)
            ->assertSee('Updated Privacy Charter');
    }
}
