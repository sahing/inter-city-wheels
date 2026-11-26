<?php

namespace Tests\Feature;

use App\Models\CarType;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentOptionsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarType::create([
            'category_key' => 'sedan',
            'name' => 'Sedan',
            'models' => 'Dzire, Etios',
            'rate_per_km' => 13,
            'extra_km_rate' => 13,
            'is_active' => true,
            'order_index' => 1,
        ]);

        CarType::create([
            'category_key' => 'suv',
            'name' => 'SUV',
            'models' => 'Innova, Ertiga',
            'rate_per_km' => 18,
            'extra_km_rate' => 18,
            'is_active' => true,
            'order_index' => 2,
        ]);

        RouteFare::create([
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'from_lat' => 22.5726,
            'from_lng' => 88.3639,
            'to_lat' => 21.6266,
            'to_lng' => 87.5074,
            'distance_km' => 185,
            'estimated_time' => '4 hrs 30 mins',
            'base_toll' => 250,
            'sedan_fare' => 3000,
            'suv_fare' => 4000,
            'roundtrip_discount_pct' => 10,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_configure_advance_percentage_and_visible_payment_options(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Non-admin cannot change payment settings
        $this->actingAs($customer)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 35,
            'payment_show_full_online' => true,
            'payment_show_part_pay' => true,
            'payment_show_full_upi' => false,
            'payment_show_cash' => false,
        ])->assertStatus(403);

        // Admin cannot disable all payment options
        $this->actingAs($admin)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 35,
            'payment_show_full_online' => false,
            'payment_show_part_pay' => false,
            'payment_show_full_upi' => false,
            'payment_show_cash' => false,
        ])->assertStatus(422);

        // Admin configures 35% advance, enables part_pay & full_upi only, default part_pay
        $res = $this->actingAs($admin)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 35,
            'payment_show_full_online' => false,
            'payment_show_part_pay' => true,
            'payment_show_full_upi' => true,
            'payment_show_cash' => false,
            'payment_default_option' => 'part_pay',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.payment_advance_pct', '35')
            ->assertJsonPath('settings.payment_show_full_online', '0')
            ->assertJsonPath('settings.payment_show_part_pay', '1')
            ->assertJsonPath('settings.payment_show_full_upi', '1')
            ->assertJsonPath('settings.payment_show_cash', '0')
            ->assertJsonPath('settings.payment_default_option', 'part_pay');

        // Customer checkout renders only enabled options and updated 35% copy
        $home = $this->actingAs($customer)->get('/');
        $home->assertOk();
        $home->assertSee('Part Pay (35% Advance, Balance to Driver)', false);
        $home->assertSee('Full UPI (Instant Zero-Fee UPI)', false);
        $home->assertDontSee('data-mode="full_online"', false);
        $home->assertDontSee('data-mode="cash"', false);
    }

    public function test_booking_enforces_enabled_payment_options_and_calculates_advance_and_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'phone' => '9876543210']);

        PlatformSetting::set('payment_advance_pct', '25');
        PlatformSetting::set('payment_show_full_online', '1');
        PlatformSetting::set('payment_show_part_pay', '1');
        PlatformSetting::set('payment_show_full_upi', '1');
        PlatformSetting::set('payment_show_cash', '0');
        PlatformSetting::set('payment_default_option', 'part_pay');

        $basePayload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Park Street',
            'drop_location' => 'Digha Beach',
            'customer_name' => 'Rohan Das',
            'customer_phone' => '9876543210',
            'fleet_category' => 'suv',
            'include_tolls' => true,
        ];

        // Disabled option ('cash') is rejected
        $this->actingAs($customer)
            ->postJson('/api/bookings', $basePayload + ['payment_mode' => 'cash'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_mode']);

        // Part Pay calculates 25% advance & 75% balance
        $partRes = $this->actingAs($customer)
            ->postJson('/api/bookings', $basePayload + ['payment_mode' => 'part_pay']);

        $partRes->assertOk()->assertJsonPath('success', true);
        $total = (float) $partRes->json('booking.total_amount');
        $expectedAdvance = round($total * 0.25, 2);
        $expectedBalance = round($total - $expectedAdvance, 2);

        $this->assertSame('part_pay', $partRes->json('booking.payment_mode'));
        $this->assertEquals($expectedAdvance, (float) $partRes->json('booking.advance_amount'));
        $this->assertEquals($expectedBalance, (float) $partRes->json('booking.balance_amount'));
        $this->assertEquals(25.0, (float) $partRes->json('booking.advance_percentage'));

        // Admin invoice reflects part_pay details
        $bookingId = $partRes->json('booking.id');
        $invoiceRes = $this->actingAs($admin)->getJson("/api/admin/bookings/{$bookingId}/invoice")
            ->assertOk()
            ->assertJsonPath('invoice.payment.payment_mode', 'part_pay');

        $this->assertEquals($expectedAdvance, (float) $invoiceRes->json('invoice.payment.advance_amount'));
        $this->assertEquals($expectedBalance, (float) $invoiceRes->json('invoice.payment.balance_amount'));

        // Full UPI charges 100% upfront
        $upiRes = $this->actingAs($customer)
            ->postJson('/api/bookings', $basePayload + ['payment_mode' => 'full_upi']);
        $upiRes->assertOk();
        $this->assertSame('full_upi', $upiRes->json('booking.payment_mode'));
        $this->assertEquals((float) $upiRes->json('booking.total_amount'), (float) $upiRes->json('booking.advance_amount'));
        $this->assertEquals(0.0, (float) $upiRes->json('booking.balance_amount'));
    }

    public function test_admin_can_enable_razorpay_without_exposing_secret_and_checkout_verifies_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'phone' => '9876543210']);
        $otherUser = User::factory()->create(['role' => 'customer', 'phone' => '9111122222']);

        // Cannot enable Razorpay without Key ID & Secret
        $this->actingAs($admin)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 25,
            'payment_show_full_online' => true,
            'payment_show_part_pay' => true,
            'payment_show_full_upi' => true,
            'payment_show_cash' => true,
            'payment_gateway' => 'razorpay',
        ])->assertStatus(422);

        // Configure Razorpay Test credentials
        $saveRes = $this->actingAs($admin)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 25,
            'payment_show_full_online' => true,
            'payment_show_part_pay' => true,
            'payment_show_full_upi' => true,
            'payment_show_cash' => true,
            'payment_default_option' => 'part_pay',
            'payment_gateway' => 'razorpay',
            'razorpay_mode' => 'test',
            'razorpay_key_id' => 'rzp_test_ABC12345',
            'razorpay_key_secret' => 'rzp_secret_topsecret_999',
            'razorpay_display_name' => 'Intercity Wheels Demo',
        ]);

        $saveRes->assertOk()
            ->assertJsonPath('settings.payment_gateway', 'razorpay')
            ->assertJsonPath('settings.razorpay_mode', 'test')
            ->assertJsonPath('settings.razorpay_key_id', 'rzp_test_ABC12345')
            ->assertJsonPath('settings.razorpay_secret_configured', '1')
            ->assertJsonMissing(['razorpay_key_secret' => 'rzp_secret_topsecret_999']);

        // Secret never appears in public HTML payload
        $html = $this->actingAs($customer)->get('/')->getContent();
        $this->assertStringNotContainsString('rzp_secret_topsecret_999', $html);

        // Subsequent save without secret preserves existing secret
        $this->actingAs($admin)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 30,
            'payment_show_full_online' => true,
            'payment_show_part_pay' => true,
            'payment_show_full_upi' => true,
            'payment_show_cash' => true,
            'payment_gateway' => 'razorpay',
            'razorpay_key_id' => 'rzp_test_ABC12345',
        ])->assertOk()->assertJsonPath('settings.razorpay_secret_configured', '1');

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_RzpTest001',
                'amount' => 127500,
                'currency' => 'INR',
            ], 200),
        ]);

        // Online part_pay booking initiates Razorpay order
        $bookRes = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Salt Lake',
            'drop_location' => 'New Digha',
            'customer_name' => 'Rohan Das',
            'customer_phone' => '9876543210',
            'fleet_category' => 'suv',
            'include_tolls' => true,
            'payment_mode' => 'part_pay',
        ]);

        $bookRes->assertOk()
            ->assertJsonPath('requires_razorpay', true)
            ->assertJsonPath('razorpay.order_id', 'order_RzpTest001')
            ->assertJsonPath('razorpay.key', 'rzp_test_ABC12345')
            ->assertJsonPath('booking.payment_gateway', 'razorpay')
            ->assertJsonPath('booking.payment_status', 'pending');

        $ref = $bookRes->json('booking.booking_reference');
        $expectedPaise = (int) round(((float) $bookRes->json('booking.advance_amount')) * 100);
        $this->assertSame($expectedPaise, (int) $bookRes->json('razorpay.amount'));

        // Another user cannot verify
        $this->actingAs($otherUser)->postJson("/api/bookings/{$ref}/razorpay/verify", [
            'razorpay_order_id' => 'order_RzpTest001',
            'razorpay_payment_id' => 'pay_Test999',
            'razorpay_signature' => 'bad',
        ])->assertStatus(403);

        // Invalid signature rejected
        $this->actingAs($customer)->postJson("/api/bookings/{$ref}/razorpay/verify", [
            'razorpay_order_id' => 'order_RzpTest001',
            'razorpay_payment_id' => 'pay_Test999',
            'razorpay_signature' => 'invalid_sig',
        ])->assertStatus(422);

        // Valid HMAC-SHA256 signature confirms booking & marks partial_paid
        $validSig = hash_hmac('sha256', 'order_RzpTest001|pay_Test999', 'rzp_secret_topsecret_999');
        $verifyRes = $this->actingAs($customer)->postJson("/api/bookings/{$ref}/razorpay/verify", [
            'razorpay_order_id' => 'order_RzpTest001',
            'razorpay_payment_id' => 'pay_Test999',
            'razorpay_signature' => $validSig,
        ]);

        $verifyRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('booking.status', 'Confirmed')
            ->assertJsonPath('booking.payment_status', 'partial_paid')
            ->assertJsonPath('booking.razorpay_payment_id', 'pay_Test999');

        // Cash booking skips Razorpay even when gateway is active
        $cashRes = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Salt Lake',
            'drop_location' => 'New Digha',
            'customer_name' => 'Rohan Das',
            'customer_phone' => '9876543210',
            'fleet_category' => 'suv',
            'payment_mode' => 'cash',
        ]);

        $cashRes->assertOk()
            ->assertJsonPath('requires_razorpay', false)
            ->assertJsonPath('booking.payment_status', 'pay_on_pickup');
    }
}
