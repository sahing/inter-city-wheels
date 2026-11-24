<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlternateLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_alternate_config_endpoint_returns_json_structure(): void
    {
        $response = $this->getJson('/auth/alternate-config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'config' => [
                    'firebase' => [
                        'apiKey',
                        'authDomain',
                        'projectId',
                        'storageBucket',
                        'messagingSenderId',
                        'appId',
                        'is_configured',
                    ],
                    'clerk' => [
                        'publishableKey',
                        'is_configured',
                    ],
                ],
            ]);
    }

    public function test_firebase_login_provisions_new_customer(): void
    {
        $payload = [
            'provider' => 'firebase',
            'email' => 'new.google.customer@example.com',
            'name' => 'New Google Customer',
            'uid' => 'fb_cust_998877',
            'role' => 'customer',
            'photo_url' => 'https://example.com/avatar.jpg',
        ];

        $response = $this->postJson('/auth/firebase-login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'firebase',
                'target_tab' => 'cabs',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new.google.customer@example.com',
            'name' => 'New Google Customer',
            'role' => 'customer',
            'auth_provider' => 'firebase',
            'auth_provider_id' => 'fb_cust_998877',
        ]);

        $this->assertAuthenticated();
    }

    public function test_firebase_login_authenticates_existing_user_and_links_provider(): void
    {
        $existing = User::where('role', 'customer')->first();
        $this->assertNotNull($existing);

        $payload = [
            'provider' => 'firebase',
            'email' => $existing->email,
            'uid' => 'fb_uid_existing_123',
        ];

        $response = $this->postJson('/auth/firebase-login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'target_tab' => 'cabs',
            ]);

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertEquals('firebase', $existing->fresh()->auth_provider);
        $this->assertEquals('fb_uid_existing_123', $existing->fresh()->auth_provider_id);
    }

    public function test_firebase_login_provisions_driver_with_driver_record(): void
    {
        $payload = [
            'provider' => 'firebase',
            'email' => 'chauffeur.google@example.com',
            'name' => 'Google Chauffeur Partner',
            'uid' => 'fb_driver_554433',
            'role' => 'driver',
            'phone' => '+91 9888877777',
            'vehicle_model' => 'Maruti Suzuki Ertiga',
            'vehicle_plate' => 'WB 02 AB 9988',
        ];

        $response = $this->postJson('/auth/firebase-login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'firebase',
                'target_tab' => 'bidding',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'chauffeur.google@example.com',
            'role' => 'driver',
            'auth_provider' => 'firebase',
        ]);

        $this->assertDatabaseHas('drivers', [
            'phone' => '+91 9888877777',
            'name' => 'Google Chauffeur Partner',
        ]);

        $this->assertAuthenticated();
    }

    public function test_clerk_login_provisions_new_customer(): void
    {
        $payload = [
            'provider' => 'clerk',
            'email' => 'clerk.user@example.com',
            'name' => 'Clerk Passenger',
            'clerk_user_id' => 'user_clerk_987654',
            'role' => 'customer',
            'avatar' => '👤',
        ];

        $response = $this->postJson('/auth/clerk-login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'clerk',
                'target_tab' => 'cabs',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'clerk.user@example.com',
            'auth_provider' => 'clerk',
            'auth_provider_id' => 'user_clerk_987654',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
    }

    public function test_alternate_login_unified_route_dispatches_proper_provider(): void
    {
        // 1. Unified route with Clerk provider
        $clerkRes = $this->postJson('/auth/alternate-login', [
            'provider' => 'clerk',
            'email' => 'unified.clerk@example.com',
            'name' => 'Unified Clerk',
            'clerk_user_id' => 'user_unified_clerk',
            'role' => 'customer',
        ]);

        $clerkRes->assertStatus(200)->assertJson(['success' => true, 'provider' => 'clerk']);

        // 2. Unified route with Firebase provider
        $firebaseRes = $this->postJson('/auth/alternate-login', [
            'provider' => 'firebase',
            'email' => 'unified.firebase@example.com',
            'name' => 'Unified Firebase',
            'uid' => 'fb_unified_123',
            'role' => 'customer',
        ]);

        $firebaseRes->assertStatus(200)->assertJson(['success' => true, 'provider' => 'firebase']);
    }

    public function test_alternate_login_validation_fails_on_invalid_or_missing_email(): void
    {
        $response = $this->postJson('/auth/alternate-login', [
            'provider' => 'firebase',
            'email' => 'not-a-valid-email',
        ]);

        $response->assertStatus(422);
    }

    public function test_firebase_login_with_direct_firebase_id_authenticates_or_provisions_user(): void
    {
        $firebaseId = 'fb_custom_uid_884422';

        $payload = [
            'provider' => 'firebase',
            'firebase_id' => $firebaseId,
            'email' => 'firebase.id.user@example.com',
            'name' => 'Firebase ID User',
            'role' => 'customer',
        ];

        $response = $this->postJson('/auth/firebase-login', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'provider' => 'firebase',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'firebase.id.user@example.com',
            'firebase_id' => $firebaseId,
            'auth_provider' => 'firebase',
            'auth_provider_id' => $firebaseId,
        ]);

        // Finding by Firebase ID helper
        $foundUser = User::findByFirebaseId($firebaseId);
        $this->assertNotNull($foundUser);
        $this->assertEquals('firebase.id.user@example.com', $foundUser->email);

        // Subsequent login using solely Firebase ID
        $reLoginResponse = $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'firebase_id' => $firebaseId,
        ]);

        $reLoginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $foundUser->id,
                    'firebase_id' => $firebaseId,
                ],
            ]);
    }

    public function test_modal_renders_firebase_and_clerk_buttons_and_alternate_modal(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Assert alternate buttons in signin & signup containers
        $response->assertSee('id="btn-auth-firebase-google"', false);
        $response->assertSee('id="btn-auth-clerk"', false);
        $response->assertSee('id="btn-signup-firebase-google"', false);
        $response->assertSee('id="btn-signup-clerk"', false);
        $response->assertSee('Firebase ID / Google', false);
        $response->assertSee('Clerk Auth', false);

        // Assert Alternate Auth modal dialog
        $response->assertSee('id="alternate-auth-modal"', false);
        $response->assertSee('id="alt-auth-form"', false);
        $response->assertSee('id="alt-input-firebase-id"', false);
        $response->assertSee('id="alt-input-email"', false);
        $response->assertSee('id="alt-input-name"', false);

        // Assert Global Javascript state has alternate config
        $response->assertSee('window.ALTERNATE_AUTH_CONFIG =', false);
    }
}
