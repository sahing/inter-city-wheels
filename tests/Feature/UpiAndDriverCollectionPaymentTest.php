<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CarType;
use App\Models\Driver;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpiAndDriverCollectionPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private User $otherCustomer;

    private User $driverUser;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Aarav Sen',
            'phone' => '9876543210',
        ]);
        $this->otherCustomer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Stranger',
            'phone' => '9111122222',
        ]);
        $this->driverUser = User::factory()->create([
            'role' => 'driver',
            'name' => 'Vikram Chauffeur',
            'phone' => '9800011223',
        ]);
        $this->driver = Driver::create([
            'name' => 'Vikram Chauffeur',
            'phone' => '9800011223',
            'vehicle_model' => 'Toyota Innova Crysta',
            'vehicle_plate' => 'WB02AB1234',
            'fleet_category' => 'suv',
            'rating' => 4.9,
            'wallet_balance' => 5000,
            'trips_count' => 10,
            'is_online' => true,
            'is_verified' => true,
        ]);

        CarType::create([
            'category_key' => 'suv',
            'name' => 'SUV',
            'models' => 'Innova, Ertiga',
            'rate_per_km' => 18,
            'extra_km_rate' => 18,
            'is_active' => true,
            'order_index' => 1,
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

    private function baseBookingPayload(array $overrides = []): array
    {
        return array_merge([
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Park Street, Kolkata',
            'drop_location' => 'Digha Beach',
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'fleet_category' => 'suv',
            'include_tolls' => true,
        ], $overrides);
    }

    public function test_admin_can_configure_upi_id_and_checkout_displays_upi_details_and_utr_controls(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 20,
            'payment_show_full_online' => true,
            'payment_show_part_pay' => true,
            'payment_show_full_upi' => true,
            'payment_show_cash' => true,
            'payment_default_option' => 'full_upi',
            'upi_id' => 'pay.intercity@okaxis',
            'upi_payee_name' => 'Intercity Wheels Ops',
            'upi_instructions' => 'Send exact fare via GPay/PhonePe and submit your 12-digit UTR.',
        ]);

        $res->assertOk()
            ->assertJsonPath('settings.upi_id', 'pay.intercity@okaxis')
            ->assertJsonPath('settings.upi_payee_name', 'Intercity Wheels Ops')
            ->assertJsonPath('settings.payment_default_option', 'full_upi');

        $html = $this->actingAs($this->customer)->get('/')->getContent();
        $this->assertStringContainsString('pay.intercity@okaxis', $html);
        $this->assertStringContainsString('Intercity Wheels Ops', $html);
        $this->assertStringContainsString('Send exact fare via GPay/PhonePe and submit your 12-digit UTR.', $html);
        $this->assertStringContainsString('id="checkout-upi-details-box"', $html);
        $this->assertStringContainsString('id="checkout-upi-utr"', $html);
        $this->assertStringContainsString('Driver collects on completion', $html);
    }

    public function test_when_razorpay_is_active_full_upi_uses_razorpay_and_when_inactive_manual_upi_acts(): void
    {
        PlatformSetting::set('upi_id', 'icw.desk@icici');
        PlatformSetting::set('upi_payee_name', 'Intercity Wheels Desk');

        // 1. When Razorpay IS active, full_upi routes through Razorpay Checkout (no manual UPI box).
        PlatformSetting::set('payment_gateway', 'razorpay');
        PlatformSetting::set('razorpay_key_id', 'rzp_test_123');
        PlatformSetting::set('razorpay_key_secret', 'rzp_secret_123');

        \Illuminate\Support\Facades\Http::fake([
            'https://api.razorpay.com/v1/orders' => \Illuminate\Support\Facades\Http::response([
                'id' => 'order_RzpUpi001',
                'amount' => 428000,
                'currency' => 'INR',
            ], 200),
        ]);

        $rzpHtml = $this->actingAs($this->customer)->get('/')->getContent();
        $this->assertStringContainsString('"razorpay_active":"1"', $rzpHtml);
        $this->assertStringContainsString('id="checkout-upi-details-box" style="display:none;', $rzpHtml);

        $rzpRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'full_upi',
        ]));
        $rzpRes->assertOk()
            ->assertJsonPath('requires_razorpay', true)
            ->assertJsonPath('booking.payment_gateway', 'razorpay')
            ->assertJsonPath('booking.payment_status', 'pending');

        // 2. When Razorpay is NOT active, Manual UPI method acts (shows UPI ID + UTR Admin confirmation).
        PlatformSetting::set('payment_gateway', 'manual');

        $manualHtml = $this->actingAs($this->customer)->get('/')->getContent();
        $this->assertStringContainsString('"razorpay_active":"0"', $manualHtml);
        $this->assertStringContainsString('id="checkout-upi-details-box" style="display:block;', $manualHtml);

        $bookRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'full_upi',
            'upi_utr' => 'utr432198765432',
            'payment_collection_by' => 'platform',
        ]));

        $bookRes->assertOk()
            ->assertJsonPath('requires_razorpay', false)
            ->assertJsonPath('upi_details.upi_id', 'icw.desk@icici')
            ->assertJsonPath('upi_details.upi_payee_name', 'Intercity Wheels Desk')
            ->assertJsonPath('booking.payment_mode', 'full_upi')
            ->assertJsonPath('booking.payment_gateway', 'upi_manual')
            ->assertJsonPath('booking.payment_status', 'pending_upi_verification')
            ->assertJsonPath('booking.upi_utr', 'UTR432198765432')
            ->assertJsonPath('booking.payment_collection_by', 'platform');

        $bookingId = $bookRes->json('booking.id');

        // Non-admin cannot verify payment
        $this->actingAs($this->customer)
            ->postJson("/api/admin/bookings/{$bookingId}/verify-payment", ['action' => 'confirm_utr'])
            ->assertStatus(403);

        // Admin confirms UTR -> marks paid & confirms booking
        $verifyRes = $this->actingAs($this->admin)->postJson("/api/admin/bookings/{$bookingId}/verify-payment", [
            'action' => 'confirm_utr',
            'payment_notes' => 'Verified in ICICI business statement',
        ]);

        $verifyRes->assertOk()
            ->assertJsonPath('booking.payment_status', 'paid')
            ->assertJsonPath('booking.payment_collection_by', 'platform')
            ->assertJsonPath('booking.status', 'Confirmed');

        $booking = Booking::findOrFail($bookingId);
        $this->assertNotNull($booking->upi_verified_at);
        $this->assertSame($this->admin->id, (int) $booking->upi_verified_by);

        // Invoice displays UTR reference and PAID IN FULL
        $this->actingAs($this->admin)->getJson("/api/admin/bookings/{$bookingId}/invoice")
            ->assertOk()
            ->assertJsonPath('invoice.payment.upi_utr', 'UTR432198765432')
            ->assertJsonPath('invoice.payment.transaction_ref', 'UTR-UTR432198765432')
            ->assertJsonPath('invoice.payment.status', 'PAID IN FULL');
    }

    public function test_customer_can_submit_utr_or_switch_to_driver_collection_and_admin_can_assign_driver_collection(): void
    {
        $bookRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'full_upi',
        ]));

        $bookRes->assertOk()
            ->assertJsonPath('booking.payment_status', 'awaiting_upi_utr');

        $ref = $bookRes->json('booking.booking_reference');
        $bookingId = $bookRes->json('booking.id');

        // Another customer cannot submit UTR for someone else's booking
        $this->actingAs($this->otherCustomer)
            ->postJson("/api/bookings/{$ref}/upi-utr", [
                'collection_choice' => 'utr',
                'upi_utr' => '998877665544',
            ])
            ->assertStatus(403);

        // Owner submits UTR
        $this->actingAs($this->customer)
            ->postJson("/api/bookings/{$ref}/upi-utr", [
                'collection_choice' => 'utr',
                'upi_utr' => '998877665544',
            ])
            ->assertOk()
            ->assertJsonPath('booking.upi_utr', '998877665544')
            ->assertJsonPath('booking.payment_status', 'pending_upi_verification');

        // Admin rejects UTR & assigns collection responsibility to the driver
        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$bookingId}/verify-payment", [
                'action' => 'reject_assign_driver',
                'payment_notes' => 'UTR not found — driver will collect on completion',
            ])
            ->assertOk()
            ->assertJsonPath('booking.payment_status', 'driver_collect')
            ->assertJsonPath('booking.payment_collection_by', 'driver');

        $booking = Booking::findOrFail($bookingId);
        $this->assertEquals((float) $booking->total_amount, (float) $booking->balance_amount);
        $this->assertEquals(0.0, (float) $booking->advance_amount);
    }

    public function test_driver_collects_money_on_trip_completion_and_settles_wallet_commission(): void
    {
        $bookRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'full_upi',
            'payment_collection_by' => 'driver',
        ]));

        $bookRes->assertOk()
            ->assertJsonPath('booking.payment_status', 'driver_collect')
            ->assertJsonPath('booking.payment_collection_by', 'driver');

        $booking = Booking::findOrFail($bookRes->json('booking.id'));
        $total = (float) $booking->total_amount;

        // Assign driver and start trip
        $booking->forceFill([
            'assigned_driver_id' => $this->driver->id,
            'status' => 'Started',
        ])->save();

        // Driver cannot complete if explicitly claiming not collected
        $this->actingAs($this->driverUser)
            ->postJson("/api/driver/trips/{$booking->id}/complete", [
                'collected_by_driver' => false,
            ])
            ->assertStatus(422);

        $beforeWallet = (float) $this->driver->wallet_balance;

        // Driver confirms collection upon completing drive
        $completeRes = $this->actingAs($this->driverUser)
            ->postJson("/api/driver/trips/{$booking->id}/complete", [
                'collected_by_driver' => true,
            ]);

        $completeRes->assertOk()
            ->assertJsonPath('booking.status', 'Completed')
            ->assertJsonPath('booking.payment_status', 'paid');

        $this->assertEquals($total, (float) $completeRes->json('driver_collected_amount'));

        $expectedPayout = round($total * 0.9, 2);
        $expectedCommission = round($total - $expectedPayout, 2);
        $this->assertEquals($beforeWallet - $expectedCommission, (float) $this->driver->fresh()->wallet_balance);

        $tx = WalletTransaction::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(WalletTransaction::TYPE_DEBIT, $tx->type);
        $this->assertEquals($expectedCommission, (float) $tx->amount);
    }

    public function test_part_pay_driver_collects_balance_on_completion_and_receives_net_wallet_delta(): void
    {
        PlatformSetting::set('payment_advance_pct', '20');

        $bookRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'part_pay',
        ]));
        $bookRes->assertOk();

        $booking = Booking::findOrFail($bookRes->json('booking.id'));
        $total = (float) $booking->total_amount;
        $balance = (float) $booking->balance_amount; // 80% collected by driver

        $booking->forceFill([
            'assigned_driver_id' => $this->driver->id,
            'status' => 'Started',
        ])->save();

        $beforeWallet = (float) $this->driver->wallet_balance;

        // Driver payout is 90% of total; driver collects 80% directly -> net wallet credit is +10% of total.
        $completeRes = $this->actingAs($this->driverUser)
            ->postJson("/api/driver/trips/{$booking->id}/complete", [
                'collected_by_driver' => true,
            ]);

        $completeRes->assertOk()
            ->assertJsonPath('booking.payment_status', 'paid');

        $expectedPayout = round($total * 0.9, 2);
        $expectedNetCredit = round($expectedPayout - $balance, 2);

        $this->assertEquals($balance, (float) $completeRes->json('driver_collected_amount'));
        $this->assertEquals($expectedNetCredit, (float) $completeRes->json('net_wallet_delta'));
        $this->assertEquals($beforeWallet + $expectedNetCredit, (float) $this->driver->fresh()->wallet_balance);
    }

    public function test_when_razorpay_is_inactive_full_online_and_part_pay_work_via_manual_upi_with_admin_utr_confirmation(): void
    {
        PlatformSetting::set('payment_gateway', 'manual');
        PlatformSetting::set('payment_advance_pct', '20');
        PlatformSetting::set('upi_id', 'manual.desk@sbi');

        // Full Online falls back to Manual UPI when UTR is provided
        $fullRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'full_online',
            'upi_utr' => 'SBI1122334455',
        ]));

        $fullRes->assertOk()
            ->assertJsonPath('requires_razorpay', false)
            ->assertJsonPath('upi_details.upi_id', 'manual.desk@sbi')
            ->assertJsonPath('booking.payment_gateway', 'upi_manual')
            ->assertJsonPath('booking.payment_status', 'pending_upi_verification')
            ->assertJsonPath('booking.upi_utr', 'SBI1122334455');

        // Part Pay advance via Manual UPI awaits Admin confirmation, then sets partial_paid with driver collecting balance
        $partRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'part_pay',
            'part_pay_agreed' => true,
            'upi_utr' => 'SBI9988776655',
        ]));

        $partRes->assertOk()
            ->assertJsonPath('requires_razorpay', false)
            ->assertJsonPath('booking.payment_gateway', 'upi_manual')
            ->assertJsonPath('booking.payment_status', 'pending_upi_verification');

        $this->assertStringContainsString(
            'remaining balance & additional charges',
            (string) $partRes->json('booking.payment_notes')
        );

        $partId = $partRes->json('booking.id');
        $this->actingAs($this->admin)->postJson("/api/admin/bookings/{$partId}/verify-payment", [
            'action' => 'confirm_utr',
        ])->assertOk()
            ->assertJsonPath('booking.payment_status', 'partial_paid')
            ->assertJsonPath('booking.payment_collection_by', 'driver');

        // Checkout page ships the step-by-step Part Pay Agreement -> UTR Entry modal
        $checkoutHtml = $this->actingAs($this->customer)->get('/')->getContent();
        $this->assertStringContainsString('id="checkout-payment-step-modal"', $checkoutHtml);
        $this->assertStringContainsString('id="step-part-pay-agree-checkbox"', $checkoutHtml);
        $this->assertStringContainsString('id="step-modal-upi-utr"', $checkoutHtml);
    }

    public function test_driver_collect_on_completion_is_hidden_and_rejected_when_admin_disables_cash_option(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/settings/payment-options', [
            'payment_advance_pct' => 20,
            'payment_show_full_online' => true,
            'payment_show_part_pay' => true,
            'payment_show_full_upi' => true,
            'payment_show_cash' => false,
            'payment_default_option' => 'full_upi',
        ])->assertOk();

        $html = $this->actingAs($this->customer)->get('/')->getContent();
        $this->assertStringContainsString('id="checkout-driver-collect-opt" style="display:none;', $html);
        $this->assertStringContainsString('id="step-modal-driver-collect-opt" style="display:none;', $html);

        // Backend rejects driver collection on new booking when cash/driver-collect is disabled
        $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'full_upi',
            'payment_collection_by' => 'driver',
        ]))->assertStatus(422)->assertJsonValidationErrors(['payment_collection_by']);

        // Create valid full_upi booking, then verify switching to driver_collect via /upi-utr is also rejected
        $bookRes = $this->actingAs($this->customer)->postJson('/api/bookings', $this->baseBookingPayload([
            'payment_mode' => 'full_upi',
        ]))->assertOk();

        $ref = $bookRes->json('booking.booking_reference');
        $this->actingAs($this->customer)->postJson("/api/bookings/{$ref}/upi-utr", [
            'collection_choice' => 'driver_collect',
        ])->assertStatus(422)->assertJsonValidationErrors(['collection_choice']);
    }
}
