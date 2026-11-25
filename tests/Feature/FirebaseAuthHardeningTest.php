<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Firebase sign-in endpoint must trust a verified ID token, not the payload.
 *
 * Before this, `POST /auth/firebase-login` accepted any `firebase_id` and any
 * role, so a caller could sign in as an existing user (or mint an admin account)
 * without ever touching Google.
 */
class FirebaseAuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT = '627768077846';

    private const CLIENT = '627768077846-abc.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.firebase.project_id' => self::PROJECT,
            'services.firebase.client_id' => '',
            'services.firebase.allow_id_only' => false,
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://securetoken.google.com/'.self::PROJECT,
            'aud' => self::CLIENT,
            'sub' => 'firebase-uid-123',
            'email' => 'google.user@example.com',
            'email_verified' => 'true',
            'exp' => (string) (time() + 3600),
            'name' => 'Google User',
            'picture' => 'https://example.com/p.png',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function fakeTokenInfo(array $claims, int $status = 200): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response($claims, $status),
        ]);
    }

    public function test_a_verified_token_signs_the_user_in_with_token_claims(): void
    {
        $this->fakeTokenInfo($this->claims());

        $response = $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'valid-token',
            // These claims must be ignored in favour of the token's.
            'email' => 'spoofed@example.com',
            'uid' => 'spoofed-uid',
            'role' => 'customer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.email', 'google.user@example.com')
            ->assertJsonPath('user.firebase_id', 'firebase-uid-123');

        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'firebase_id' => 'firebase-uid-123',
            'auth_provider' => 'firebase',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'spoofed@example.com']);
        $this->assertAuthenticated();
    }

    public function test_a_token_from_another_project_is_rejected(): void
    {
        $this->fakeTokenInfo($this->claims([
            'aud' => '999999999999-xyz.apps.googleusercontent.com',
        ]));

        $response = $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'foreign-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'That Google sign-in belongs to a different Firebase project.');

        $this->assertGuest();
    }

    public function test_a_pinned_client_id_must_match(): void
    {
        config(['services.firebase.client_id' => self::CLIENT]);
        $this->fakeTokenInfo($this->claims());

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'valid-token',
        ])->assertOk();

        config(['services.firebase.client_id' => 'another-client.apps.googleusercontent.com']);

        $this->post('/logout');

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'valid-token',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'That Google sign-in belongs to a different app configuration.');
    }

    public function test_a_token_with_a_foreign_issuer_is_rejected(): void
    {
        $this->fakeTokenInfo($this->claims(['iss' => 'https://evil.example.com']));

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'bad-issuer',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'That sign-in token was not issued by Google for this app.');
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $this->fakeTokenInfo($this->claims(['exp' => (string) (time() - 3600)]));

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'expired-token',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Your Google sign-in has expired. Please sign in again.');
    }

    public function test_an_unverified_email_is_rejected(): void
    {
        $this->fakeTokenInfo($this->claims(['email_verified' => 'false']));

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'unverified-email',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Your Google email address is not verified.');
    }

    public function test_google_rejecting_the_token_is_reported_cleanly(): void
    {
        $this->fakeTokenInfo(['error' => 'invalid_token'], 400);

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'garbage',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Your Google sign-in could not be verified. Please sign in again.');
    }

    public function test_an_id_without_a_token_is_refused_in_production_mode(): void
    {
        User::factory()->create(['email' => 'victim@example.com']);

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'firebase_id' => 'fb_anything',
            'email' => 'victim@example.com',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'A verified Firebase sign-in is required. Please sign in with Google again.');

        $this->assertGuest();
    }

    public function test_the_local_demo_still_accepts_a_bare_firebase_id(): void
    {
        config(['services.firebase.allow_id_only' => true]);

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'firebase_id' => 'fb_demo_123',
            'email' => 'demo@example.com',
            'role' => 'customer',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_a_verified_identity_cannot_create_an_admin_account(): void
    {
        $this->fakeTokenInfo($this->claims());

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'id_token' => 'valid-token',
            'role' => 'admin',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_the_demo_mode_keeps_its_admin_role_for_local_testing(): void
    {
        config(['services.firebase.allow_id_only' => true]);

        $this->postJson('/auth/firebase-login', [
            'provider' => 'firebase',
            'firebase_id' => 'fb_admin_demo',
            'email' => 'admin.demo@example.com',
            'role' => 'admin',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'admin.demo@example.com',
            'role' => 'admin',
        ]);
    }
}
