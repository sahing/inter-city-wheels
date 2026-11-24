<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_can_login_via_json_request(): void
    {
        $response = $this->postJson('/login', [
            'email' => 'customer@intercitywheels.com',
            'password' => 'password123',
            'role' => 'customer',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user' => [
                'email' => 'customer@intercitywheels.com',
                'role' => 'customer',
            ],
        ]);
        $response->assertJsonStructure(['success', 'message', 'redirect_url', 'user', 'target_tab']);
        $this->assertAuthenticated();
    }

    public function test_customer_can_login_via_standard_form_post(): void
    {
        $response = $this->post('/login', [
            'email' => 'customer@intercitywheels.com',
            'password' => 'password123',
            'role' => 'customer',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_invalid_login_credentials_returns_error(): void
    {
        $response = $this->postJson('/login', [
            'email' => 'customer@intercitywheels.com',
            'password' => 'wrongpassword',
            'role' => 'customer',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid email or password. Please verify your credentials.',
        ]);
        $this->assertGuest();
    }

    public function test_quick_demo_login_authenticates_requested_role(): void
    {
        $response = $this->postJson('/login/quick-demo', [
            'role' => 'admin',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user' => [
                'role' => 'admin',
            ],
            'target_tab' => 'admin',
        ]);
        $this->assertAuthenticated();
        $this->assertEquals('admin', Auth::user()->role);
    }

    public function test_user_can_logout_via_post_and_session_cleared(): void
    {
        $user = User::where('role', 'customer')->first();
        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post('/logout');
        $response->assertStatus(302);
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_user_can_logout_via_json_request(): void
    {
        $user = User::where('role', 'customer')->first();
        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->postJson('/logout');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
        $this->assertGuest();
    }

    public function test_user_can_logout_via_get_request(): void
    {
        $user = User::where('role', 'customer')->first();
        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->get('/logout');
        $response->assertStatus(302);
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
