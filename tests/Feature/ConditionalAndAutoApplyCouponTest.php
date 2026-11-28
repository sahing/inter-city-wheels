<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Driver;
use App\Models\RouteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConditionalAndAutoApplyCouponTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private User $otherCustomer;

    private Driver $driver;

    private RouteFare $puneGoa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->customer = User::where('role', 'customer')->firstOrFail();
        $this->otherCustomer = User::factory()->create(['role' => 'customer', 'phone' => '+91 90000 11122']);
        $this->driver = Driver::firstOrFail();
        $this->puneGoa = RouteFare::where('from_city', 'Pune')->where('to_city', 'Goa')->firstOrFail();
    }

    public function test_conditional_coupon_enforces_date_range_booking_type_car_type_route_and_user(): void
    {
        // Future start date should be rejected until starts_at arrives
        $this->actingAs($this->admin)->postJson('/api/admin/coupons', [
            'code' => 'FUTURE10',
            'title' => 'Future Promo',
            'type' => 'flat',
            'value' => 300,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'expires_at' => now()->addDays(5)->toDateTimeString(),
            'is_active' => true,
        ])->assertStatus(201);

        $this->actingAs($this->customer)
            ->getJson('/api/routes/calculate?from=Pune&to=Goa&fleet=sedan&coupon=FUTURE10')
            ->assertOk()
            ->assertJsonPath('quote.coupon.applied', false);

        // Conditional coupon restricted to: roundtrip, suv, Pune->Goa, this customer, valid date range
        $this->actingAs($this->admin)->postJson('/api/admin/coupons', [
            'code' => 'VIPRT500',
            'title' => 'VIP Roundtrip SUV',
            'description' => 'Flat ₹500 off for designated VIP customer on Pune-Goa SUV round-trips',
            'type' => 'flat',
            'value' => 500,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'booking_types' => ['roundtrip'],
            'car_types' => ['suv'],
            'route_fare_ids' => [$this->puneGoa->id],
            'target_user_ids' => [$this->customer->id],
            'per_user_limit' => 1,
            'usage_limit' => 5,
            'auto_apply' => false,
            'show_on_checkout' => true,
            'is_active' => true,
        ])->assertStatus(201);

        // Wrong booking type (oneway) -> rejected
        $this->actingAs($this->customer)
            ->getJson('/api/routes/calculate?from=Pune&to=Goa&trip_type=oneway&fleet=suv&coupon=VIPRT500')
            ->assertOk()
            ->assertJsonPath('quote.coupon.applied', false);

        // Wrong car type (sedan) -> rejected
        $this->actingAs($this->customer)
            ->getJson('/api/routes/calculate?from=Pune&to=Goa&trip_type=roundtrip&fleet=sedan&coupon=VIPRT500')
            ->assertOk()
            ->assertJsonPath('quote.coupon.applied', false);

        // Wrong route (Berhampore -> Digha) -> rejected
        $this->actingAs($this->customer)
            ->getJson('/api/routes/calculate?from=Berhampore&to=Digha&trip_type=roundtrip&fleet=suv&coupon=VIPRT500')
            ->assertOk()
            ->assertJsonPath('quote.coupon.applied', false);

        // Wrong customer -> rejected
        $this->actingAs($this->otherCustomer)
            ->getJson('/api/routes/calculate?from=Pune&to=Goa&trip_type=roundtrip&fleet=suv&coupon=VIPRT500')
            ->assertOk()
            ->assertJsonPath('quote.coupon.applied', false);

        // All conditions met -> surfaced in available_promotions and applied on booking once (per_user_limit = 1)
        $matchingQuote = $this->actingAs($this->customer)
            ->getJson('/api/routes/calculate?from=Pune&to=Goa&trip_type=roundtrip&fleet=suv&coupon=VIPRT500')
            ->assertOk();

        $matchingQuote->assertJsonPath('quote.coupon.applied', true);
        $this->assertEquals(500.0, (float) $matchingQuote->json('quote.discount_fare'));
        $promoCodes = array_column($matchingQuote->json('available_promotions'), 'code');
        $this->assertContains('VIPRT500', $promoCodes);

        $bookRes = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'roundtrip',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Pune Station',
            'drop_location' => 'Panaji',
            'customer_name' => 'VIP User',
            'customer_phone' => '+91 98111 22333',
            'fleet_category' => 'suv',
            'coupon_code' => 'VIPRT500',
        ])->assertOk();

        $this->assertEquals(500.0, (float) $bookRes->json('booking.discount_fare'));
        $this->assertSame('VIPRT500', $bookRes->json('booking.coupon_code'));

        // Second redemption by the same customer is blocked by per_user_limit = 1
        $secondAttempt = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'roundtrip',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Pune Station',
            'drop_location' => 'Panaji',
            'customer_name' => 'VIP User',
            'customer_phone' => '+91 98111 22333',
            'fleet_category' => 'suv',
            'coupon_code' => 'VIPRT500',
        ])->assertOk();

        $secondAttempt->assertJsonPath('quote.coupon.applied', false);
        $this->assertStringContainsString('maximum usage limit', (string) $secondAttempt->json('quote.coupon.message'));
        $this->assertEquals(0.0, (float) $secondAttempt->json('booking.discount_fare'));
    }

    public function test_auto_apply_coupon_applies_automatically_at_quote_and_booking_and_respects_driver_specificity(): void
    {
        // Route + Car auto-applied discount (₹350 flat for Sedan on Pune->Goa One-Way, no code typed)
        $this->actingAs($this->admin)->postJson('/api/admin/coupons', [
            'code' => 'AUTOPUNE350',
            'title' => 'Pune-Goa Sedan Auto Offer',
            'type' => 'flat',
            'value' => 350,
            'booking_types' => ['oneway'],
            'car_types' => ['sedan'],
            'route_fare_ids' => [$this->puneGoa->id],
            'auto_apply' => true,
            'show_on_checkout' => true,
            'is_active' => true,
        ])->assertStatus(201);

        // Without passing coupon parameter, Sedan on Pune->Goa One-Way automatically applies AUTOPUNE350
        $autoQuote = $this->actingAs($this->customer)
            ->getJson('/api/routes/calculate?from=Pune&to=Goa&trip_type=oneway&fleet=sedan')
            ->assertOk();

        $autoQuote->assertJsonPath('quote.coupon.applied', true);
        $autoQuote->assertJsonPath('quote.coupon.auto_applied', true);
        $autoQuote->assertJsonPath('quote.coupon.code', 'AUTOPUNE350');
        $this->assertEquals(350.0, (float) $autoQuote->json('quote.discount_fare'));

        // SUV on the same route must NOT get Sedan-only auto coupon
        $suvQuote = $this->actingAs($this->customer)
            ->getJson('/api/routes/calculate?from=Pune&to=Goa&trip_type=oneway&fleet=suv')
            ->assertOk();

        $suvQuote->assertJsonPath('quote.coupon.applied', false);
        $this->assertEquals(0.0, (float) $suvQuote->json('quote.discount_fare'));

        // Booking without coupon_code auto-applies AUTOPUNE350 and increments used_count
        $autoBook = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate, Pune',
            'drop_location' => 'Calangute, Goa',
            'customer_name' => 'Auto Promo Rider',
            'customer_phone' => '+91 98222 33445',
            'fleet_category' => 'sedan',
        ])->assertOk();

        $autoBook->assertJsonPath('quote.coupon.auto_applied', true);
        $this->assertSame('AUTOPUNE350', $autoBook->json('booking.coupon_code'));
        $this->assertEquals(350.0, (float) $autoBook->json('booking.discount_fare'));
        $this->assertSame(1, (int) Coupon::where('code', 'AUTOPUNE350')->value('used_count'));

        // Driver-specific auto-apply coupon: ₹400 off on trips allotted to $this->driver
        $this->actingAs($this->admin)->postJson('/api/admin/coupons', [
            'code' => 'DRVBONUS400',
            'title' => 'Preferred Chauffeur Bonus',
            'type' => 'flat',
            'value' => 400,
            'target_driver_ids' => [$this->driver->id],
            'auto_apply' => true,
            'show_on_checkout' => true,
            'is_active' => true,
        ])->assertStatus(201);

        // Customer books SUV (so AUTOPUNE350 doesn't match) with no discount initially
        $suvBook = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Berhampore',
            'to_city' => 'Digha',
            'pickup_location' => 'Berhampore',
            'drop_location' => 'Digha',
            'customer_name' => 'Driver Promo Rider',
            'customer_phone' => '+91 98333 44556',
            'fleet_category' => 'suv',
        ])->assertOk();

        $bookingId = (int) $suvBook->json('booking.id');
        $initialTotal = (float) $suvBook->json('booking.total_amount');
        $this->assertEquals(0.0, (float) $suvBook->json('booking.discount_fare'));

        // Admin allots $this->driver -> driver-specific auto coupon DRVBONUS400 automatically applies!
        $allotRes = $this->actingAs($this->admin)->postJson("/api/admin/bookings/{$bookingId}/allot-driver", [
            'driver_id' => $this->driver->id,
        ])->assertOk();

        $this->assertSame('DRVBONUS400', $allotRes->json('booking.coupon_code'));
        $this->assertEquals(400.0, (float) $allotRes->json('booking.discount_fare'));
        $this->assertEquals($initialTotal - 400.0, (float) $allotRes->json('booking.total_amount'));
        $this->assertSame(1, (int) Coupon::where('code', 'DRVBONUS400')->value('used_count'));
    }

    public function test_admin_coupons_panel_modal_and_checkout_offers_render_in_ui(): void
    {
        $html = $this->actingAs($this->admin)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="admin-sec-coupons"', $html);
        $this->assertStringContainsString('id="admin-coupon-modal"', $html);
        $this->assertStringContainsString('id="admin-coupon-starts-at"', $html);
        $this->assertStringContainsString('id="admin-coupon-per-user-limit"', $html);
        $this->assertStringContainsString('id="admin-coupon-booking-types"', $html);
        $this->assertStringContainsString('id="admin-coupon-car-types"', $html);
        $this->assertStringContainsString('id="admin-coupon-routes"', $html);
        $this->assertStringContainsString('id="admin-coupon-users"', $html);
        $this->assertStringContainsString('id="admin-coupon-drivers"', $html);
        $this->assertStringContainsString('id="admin-coupon-auto-apply"', $html);
        $this->assertStringContainsString('id="cab-checkout-offers-box"', $html);
    }
}
