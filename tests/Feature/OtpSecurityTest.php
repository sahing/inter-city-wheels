<?php

namespace Tests\Feature;

use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P1-5 — OTP lifecycle.
 *
 * Before: the code lived in the session with no expiry, unlimited guesses were
 * allowed, and the string "1234" authenticated any phone number.
 */
class OtpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_otp_is_four_digits_and_short_lived(): void
    {
        $response = $this->postJson('/auth/send-otp', ['phone' => '9876543210']);

        $response->assertStatus(200)
            ->assertJsonStructure(['otp', 'display_otp', 'expires_in'])
            ->assertJsonPath('expires_in', OtpService::TTL_SECONDS);

        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $response->json('otp'));
    }

    public function test_expired_otp_is_rejected(): void
    {
        $otp = $this->postJson('/auth/send-otp', ['phone' => '9876543211'])->json('otp');

        $this->travel(OtpService::TTL_SECONDS + 1)->seconds();

        $this->postJson('/auth/verify-otp', ['phone' => '9876543211', 'otp' => $otp])
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_expired');
    }

    public function test_otp_can_only_be_used_once(): void
    {
        $otp = $this->postJson('/auth/send-otp', ['phone' => '9876543212'])->json('otp');

        $this->postJson('/auth/verify-otp', ['phone' => '9876543212', 'otp' => $otp])->assertStatus(200);

        $this->postJson('/auth/verify-otp', ['phone' => '9876543212', 'otp' => $otp])
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_missing');
    }

    public function test_repeated_wrong_guesses_burn_the_code(): void
    {
        $otp = $this->postJson('/auth/send-otp', ['phone' => '9876543213'])->json('otp');
        $wrong = $otp === '0000' ? '0001' : '0000';

        for ($attempt = 1; $attempt <= OtpService::MAX_ATTEMPTS; $attempt++) {
            $response = $this->postJson('/auth/verify-otp', ['phone' => '9876543213', 'otp' => $wrong]);

            if ($attempt < OtpService::MAX_ATTEMPTS) {
                $response->assertStatus(422)->assertJsonPath('code', 'otp_invalid');
            } else {
                $response->assertStatus(429)->assertJsonPath('code', 'otp_locked');
            }
        }

        // The correct code no longer works: the code was burned.
        $this->postJson('/auth/verify-otp', ['phone' => '9876543213', 'otp' => $otp])
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_missing');
    }

    public function test_hardcoded_master_code_is_gone(): void
    {
        $service = app(OtpService::class);
        $phone = '9876543214';

        // Find a code that is not the old master code, then prove "1234" fails.
        $issued = null;

        for ($i = 0; $i < 25; $i++) {
            Cache::flush();
            $candidate = $service->issue($phone);

            if ($candidate['otp'] !== '1234') {
                $issued = $candidate['otp'];
                break;
            }
        }

        $this->assertNotNull($issued, 'Could not obtain a non-1234 code to test against.');

        $this->assertFalse($service->verify($phone, '1234')['ok']);

        // The attempt above consumed one guess; the real code still works.
        $this->assertTrue($service->verify($phone, $issued)['ok']);
    }

    public function test_sending_is_throttled_per_phone(): void
    {
        $phone = '9876543215';

        for ($i = 0; $i < OtpService::MAX_ISSUES; $i++) {
            $this->postJson('/auth/send-otp', ['phone' => $phone])->assertStatus(200);
        }

        $this->postJson('/auth/send-otp', ['phone' => $phone])
            ->assertStatus(429)
            ->assertJsonPath('code', 'otp_throttled');
    }

    public function test_otp_is_not_echoed_when_a_real_gateway_delivers_it(): void
    {
        config(['services.fast2sms.api_key' => 'live_key_for_test']);

        Http::fake([
            'https://www.fast2sms.com/dev/bulkV2' => Http::response([
                'return' => true,
                'request_id' => 'req_1',
                'message' => ['SMS sent successfully.'],
            ], 200),
        ]);

        $response = $this->postJson('/auth/send-otp', ['phone' => '9876543216']);

        $response->assertStatus(200)
            ->assertJsonPath('sms_sent', true)
            ->assertJsonPath('otp', null)
            ->assertJsonPath('display_otp', null);
    }
}
