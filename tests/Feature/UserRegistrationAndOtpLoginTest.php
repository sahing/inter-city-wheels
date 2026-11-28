<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserRegistrationAndOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_can_register_new_account()
    {
        $payload = [
            'name' => 'Aarav Ganguly',
            'email' => 'aarav.ganguly@example.com',
            'phone' => '9831122334',
            'password' => 'securepass123',
            'role' => 'customer',
        ];

        $response = $this->postJson('/register', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'target_tab' => 'cabs',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'aarav.ganguly@example.com',
            'name' => 'Aarav Ganguly',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
    }

    public function test_driver_can_register_new_account_with_vehicle()
    {
        $payload = [
            'name' => 'Subhash Chauffeur',
            'email' => 'subhash.driver@example.com',
            'phone' => '9832233445',
            'password' => 'driverpass123',
            'vehicle_model' => 'Toyota Innova Crysta',
            'vehicle_plate' => 'WB 06 QX 7788',
        ];

        $response = $this->postJson('/driver/register', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'target_tab' => 'bidding',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'subhash.driver@example.com',
            'role' => 'driver',
        ]);

        $this->assertDatabaseHas('drivers', [
            'phone' => '+91 98322 33445',
            'vehicle_model' => 'Toyota Innova Crysta',
            'vehicle_plate' => 'WB 06 QX 7788',
        ]);

        $this->assertAuthenticated();
    }

    public function test_customer_register_endpoint_refuses_driver_role()
    {
        $this->postJson('/register', [
            'name' => 'Wrong Endpoint',
            'email' => 'wrong.endpoint@example.com',
            'phone' => '9832233499',
            'password' => 'driverpass123',
            'role' => 'driver',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'wrong.endpoint@example.com']);
    }

    public function test_dedicated_login_portals_and_clean_customer_popup_render()
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Customer Sign In')
            ->assertDontSee('onclick="selectLoginRole(\'driver\')"', false)
            ->assertDontSee('onclick="selectLoginRole(\'admin\')"', false)
            ->assertDontSee('id="signup-driver-fields"', false);

        $this->get('/login')->assertOk()->assertSee('Customer Sign In');
        $this->get('/driver')->assertOk()->assertSee('Driver Partner Portal')->assertSee('/driver/register', false);
        $this->get('/admin')->assertOk()->assertSee('Platform Admin Sign In')->assertDontSee('portal-signup-form', false);
    }

    public function test_driver_and_admin_login_endpoints_enforce_their_roles()
    {
        $this->postJson('/driver/login', [
            'email' => 'customer@intercitywheels.com',
            'password' => 'password123',
        ])->assertStatus(422);

        $this->postJson('/driver/login', [
            'email' => 'driver@intercitywheels.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('target_tab', 'bidding');

        $this->postJson('/logout')->assertOk();

        $this->postJson('/admin/login', [
            'email' => 'driver@intercitywheels.com',
            'password' => 'password123',
        ])->assertStatus(422);

        $this->postJson('/admin/login', [
            'email' => 'admin@intercitywheels.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('target_tab', 'admin');
    }

    public function test_mobile_otp_can_be_dispatched_and_shows_otp_in_demo_mode()
    {
        $phone = '9876543210';

        $response = $this->postJson('/auth/send-otp', [
            'phone' => $phone,
            'role' => 'customer',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'phone',
                'otp',
                'display_otp',
                'sms_sent',
                'is_demo',
            ]);

        $otp = $response->json('otp');
        $this->assertNotEmpty($otp);
        $this->assertEquals(4, strlen($otp));
    }

    public function test_fast2sms_api_is_called_when_key_is_provided()
    {
        config(['services.fast2sms.api_key' => 'test_fast2sms_key_123']);

        Http::fake([
            'https://www.fast2sms.com/dev/bulkV2' => Http::response([
                'return' => true,
                'request_id' => 'v2_req_9988',
                'message' => ['SMS sent successfully.'],
            ], 200),
        ]);

        $response = $this->postJson('/auth/send-otp', [
            'phone' => '9876543210',
            'role' => 'customer',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sms_sent' => true,
                'is_demo' => false,
            ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.fast2sms.com/dev/bulkV2' &&
                $request->hasHeader('authorization', 'test_fast2sms_key_123');
        });
    }

    public function test_user_can_verify_otp_and_login()
    {
        $phone = '9839988776';

        // 1. Send OTP
        $sendResponse = $this->postJson('/auth/send-otp', ['phone' => $phone]);
        $sendResponse->assertStatus(200);
        $otp = $sendResponse->json('otp');

        // 2. Verify with correct OTP
        $verifyResponse = $this->postJson('/auth/verify-otp', [
            'phone' => $phone,
            'otp' => $otp,
            'role' => 'customer',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'phone' => '+91 98399 88776',
        ]);
    }

    public function test_invalid_otp_fails_with_proper_error()
    {
        $phone = '9839988776';

        $this->postJson('/auth/send-otp', ['phone' => $phone]);

        $verifyResponse = $this->postJson('/auth/verify-otp', [
            'phone' => $phone,
            'otp' => '999999', // wrong OTP
        ]);

        $verifyResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'otp_invalid',
            ])
            ->assertJsonPath('attempts_left', 4);

        $this->assertGuest();
    }

    public function test_modal_renders_sign_up_and_mobile_otp_options()
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Assert Sign In & Sign Up toggle buttons
        $response->assertSee('tab-btn-signin', false);
        $response->assertSee('tab-btn-signup', false);
        $response->assertSee('Create Account', false);

        // Assert Mobile OTP Sign In View
        $response->assertSee('method-btn-otp', false);
        $response->assertSee('Mobile OTP Login', false);
        $response->assertSee('otp-request-form', false);
        $response->assertSee('Fast2SMS', false);
        $response->assertSee('otp-live-preview-box', false);

        // Assert Registration Form
        $response->assertSee('auth-signup-form', false);
        $response->assertSee('signup-name', false);
        $response->assertSee('signup-phone', false);
        $response->assertSee('signup-email', false);
        $response->assertSee('signup-password', false);
    }
}
