<?php

namespace Tests\Feature;

use App\Models\AirportHub;
use App\Models\Booking;
use App\Models\CarType;
use App\Models\Driver;
use App\Models\DynamicFormField;
use App\Models\LocalPackage;
use App\Models\RouteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkBookingRatesOverrunAndPriceFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private CarType $sedan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->customer = User::where('role', 'customer')->firstOrFail();
        $this->sedan = CarType::where('category_key', 'sedan')->firstOrFail();

        // Configure Sedan to match the exact example: base=800, rate=10/km, extra_km=12/km.
        $this->actingAs($this->admin)->postJson('/api/admin/car-types/bulk-rates', [
            'rates' => [
                [
                    'id' => $this->sedan->id,
                    'base_fare_min' => 800,
                    'rate_per_km' => 10,
                    'extra_km_rate' => 12,
                    'is_active' => true,
                ],
            ],
        ])->assertOk();

        // Configure Murshidabad -> Kolkata as exact 220 km with 0 toll for crisp arithmetic.
        RouteFare::updateOrCreate(
            ['from_city' => 'Murshidabad', 'to_city' => 'Kolkata'],
            [
                'distance_km' => 220,
                'estimated_time' => '5h 30m',
                'base_toll' => 0,
                'is_active' => true,
            ]
        );
    }

    public function test_oneway_base_plus_per_km_and_extra_km_overrun_formula(): void
    {
        // Standard 220 km trip: 800 + (220 * 10) = 3000
        $standard = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=oneway&fleet=sedan')
            ->assertOk();

        $this->assertEquals(800.0, (float) $standard->json('quote.fixed_base_fare'));
        $this->assertEquals(10.0, (float) $standard->json('quote.rate_per_km'));
        $this->assertEquals(12.0, (float) $standard->json('quote.extra_km_rate'));
        $this->assertEquals(220, (int) $standard->json('quote.booked_distance_km'));
        $this->assertEquals(220, (int) $standard->json('quote.actual_distance_km'));
        $this->assertEquals(0, (int) $standard->json('quote.extra_km'));
        $this->assertEquals(3000.0, (float) $standard->json('quote.base_fare'));
        $this->assertEquals(0.0, (float) $standard->json('quote.extra_km_fare'));
        $this->assertEquals(3000.0, (float) $standard->json('quote.total_amount'));

        // Overrun to 230 km: 800 + (220 * 10) + ((230 - 220) * 12) = 3120
        $overrun = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=oneway&fleet=sedan&actual_km=230')
            ->assertOk();

        $this->assertEquals(220, (int) $overrun->json('quote.booked_distance_km'));
        $this->assertEquals(230, (int) $overrun->json('quote.actual_distance_km'));
        $this->assertEquals(10, (int) $overrun->json('quote.extra_km'));
        $this->assertEquals(3000.0, (float) $overrun->json('quote.base_fare'));
        $this->assertEquals(120.0, (float) $overrun->json('quote.extra_km_fare'));
        $this->assertEquals(3120.0, (float) $overrun->json('quote.total_amount'));
        $this->assertEquals(3120.0, (float) $overrun->json('fleet_estimates.sedan'));
    }

    public function test_driver_trip_completion_with_actual_km_reconciles_extra_km_wallet_and_invoice(): void
    {
        $driverUser = User::where('role', 'driver')->firstOrFail();
        $driver = $driverUser->driverProfile() ?? Driver::firstOrFail();
        $initialWallet = (float) $driver->wallet_balance;

        // Customer books 220 km trip at ₹3,000 in part_pay (20% advance = 600, balance = 2400).
        $bookRes = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Murshidabad',
            'to_city' => 'Kolkata',
            'pickup_location' => 'Hazarduari Gate, Murshidabad',
            'drop_location' => 'Salt Lake Sector V, Kolkata',
            'customer_name' => 'Arup Sen',
            'customer_phone' => '+91 98300 12345',
            'fleet_category' => 'sedan',
            'include_tolls' => true,
            'payment_mode' => 'part_pay',
            'payment_channel' => 'manual_upi',
            'upi_utr' => 'UTR220KM6001',
            'part_pay_agreed' => true,
        ])->assertOk();

        $bookingId = (int) $bookRes->json('booking.id');
        $startOtp = (string) $bookRes->json('booking.start_otp');

        $this->assertEquals(3000.0, (float) $bookRes->json('booking.total_amount'));
        $this->assertEquals(220, (int) $bookRes->json('booking.booked_distance_km'));
        $this->assertEquals(600.0, (float) $bookRes->json('booking.advance_amount'));
        $this->assertEquals(2400.0, (float) $bookRes->json('booking.balance_amount'));

        // Admin allots driver, then driver starts trip with OTP.
        $this->actingAs($this->admin)->postJson("/api/admin/bookings/{$bookingId}/allot-driver", [
            'driver_id' => $driver->id,
        ])->assertOk();

        $this->actingAs($driverUser)->postJson("/api/driver/trips/{$bookingId}/verify-otp", [
            'otp' => $startOtp,
        ])->assertOk();

        // Car runs 230 km actual on completion -> adds 10 km * ₹12 = ₹120!
        $completeRes = $this->actingAs($driverUser)->postJson("/api/driver/trips/{$bookingId}/complete", [
            'actual_distance_km' => 230,
            'collected_by_driver' => true,
        ])->assertOk();

        $completedBooking = $completeRes->json('booking');
        $this->assertEquals('Completed', $completedBooking['status']);
        $this->assertEquals(230, (int) $completedBooking['actual_distance_km']);
        $this->assertEquals(10, (int) $completedBooking['extra_km']);
        $this->assertEquals(120.0, (float) $completedBooking['extra_km_fare']);
        $this->assertEquals(3120.0, (float) $completedBooking['total_amount']);

        // Driver collected balance (2400) + extra KM charge (120) = 2520.
        // Driver 90% payout on 3120 = 2808. Net wallet credit = 2808 - 2520 = 288.
        $this->assertEquals(2520.0, (float) $completeRes->json('driver_collected_amount'));
        $this->assertEquals(round($initialWallet + 288.0, 2), round((float) $completeRes->json('wallet_balance'), 2));

        // Tax invoice includes extra KM charge in taxable_amount and charges breakdown.
        $inv = $this->actingAs($this->admin)->getJson("/api/admin/bookings/{$bookingId}/invoice")
            ->assertOk()
            ->json('invoice.charges');

        $this->assertEquals(3000.0, (float) $inv['base_fare']);
        $this->assertEquals(120.0, (float) $inv['extra_km_fare']);
        $this->assertEquals(10, (int) $inv['extra_km']);
        $this->assertEquals(3120.0, (float) $inv['taxable_amount']);
        $this->assertEquals(3120.0, (float) $inv['total_amount']);
    }

    public function test_bulk_rate_management_across_all_booking_types(): void
    {
        // Non-admin blocked from new endpoints
        $this->actingAs($this->customer)->getJson('/api/admin/booking-type-rates')->assertStatus(403);
        $this->actingAs($this->customer)->postJson('/api/admin/booking-type-rates/bulk', [
            'booking_type' => 'roundtrip',
            'rates' => [],
        ])->assertStatus(403);

        // 1. Round-Trip bulk rates: base=1500, rate=11/km, extra=13/km
        $this->actingAs($this->admin)->postJson('/api/admin/booking-type-rates/bulk', [
            'booking_type' => 'roundtrip',
            'rates' => [
                [
                    'car_type_id' => $this->sedan->id,
                    'base_fare' => 1500,
                    'rate_per_km' => 11,
                    'extra_km_rate' => 13,
                    'is_active' => true,
                ],
            ],
        ])->assertOk();

        // 220 km one-way => 440 km roundtrip => 1500 + (440 * 11) = 6340; actual 450 km => +10*13 = 6470
        $rtQuote = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=roundtrip&fleet=sedan&actual_km=450')
            ->assertOk();
        $this->assertEquals(440, (int) $rtQuote->json('quote.booked_distance_km'));
        $this->assertEquals(6340.0, (float) $rtQuote->json('quote.base_fare'));
        $this->assertEquals(130.0, (float) $rtQuote->json('quote.extra_km_fare'));
        $this->assertEquals(6470.0, (float) $rtQuote->json('quote.total_amount'));

        // 2. Local Hourly bulk rates: base=500, rate=15/km, extra_km=18/km, extra_hour=150/hr
        $this->actingAs($this->admin)->postJson('/api/admin/booking-type-rates/bulk', [
            'booking_type' => 'local',
            'rates' => [
                [
                    'car_type_id' => $this->sedan->id,
                    'base_fare' => 500,
                    'rate_per_km' => 15,
                    'extra_km_rate' => 18,
                    'extra_hour_rate' => 150,
                    'is_active' => true,
                ],
            ],
        ])->assertOk();

        LocalPackage::where('package_code', '8_80')->update(['included_km' => 80]);
        $localQuote = $this->getJson('/api/routes/calculate?from=Kolkata&to=Kolkata&trip_type=local&local_package=8_80&fleet=sedan&actual_km=95')
            ->assertOk();
        // 500 + (80 * 15) = 1700 base + (15 * 18) = 270 extra => 1970
        $this->assertEquals(1700.0, (float) $localQuote->json('quote.base_fare'));
        $this->assertEquals(270.0, (float) $localQuote->json('quote.extra_km_fare'));
        $this->assertEquals(150.0, (float) $localQuote->json('quote.extra_hour_rate'));
        $this->assertEquals(1970.0, (float) $localQuote->json('quote.total_amount'));

        // 3. Airport Pickup & Drop distinct bulk rates
        AirportHub::where('iata_code', 'CCU')->update([
            'pickup_surcharge' => 100,
            'drop_surcharge' => 200,
        ]);

        $this->actingAs($this->admin)->postJson('/api/admin/booking-type-rates/bulk', [
            'booking_type' => 'airport_pickup',
            'rates' => [
                [
                    'car_type_id' => $this->sedan->id,
                    'base_fare' => 600,
                    'rate_per_km' => 14,
                    'extra_km_rate' => 16,
                ],
            ],
        ])->assertOk();

        $this->actingAs($this->admin)->postJson('/api/admin/booking-type-rates/bulk', [
            'booking_type' => 'airport_drop',
            'rates' => [
                [
                    'car_type_id' => $this->sedan->id,
                    'base_fare' => 700,
                    'rate_per_km' => 16,
                    'extra_km_rate' => 18,
                ],
            ],
        ])->assertOk();

        $aptPickup = $this->getJson('/api/routes/calculate?from=Kolkata&to=CCU&trip_type=airport&airport_type=pickup&fleet=sedan')
            ->assertOk();
        $aptDrop = $this->getJson('/api/routes/calculate?from=Kolkata&to=CCU&trip_type=airport&airport_type=drop&fleet=sedan')
            ->assertOk();

        $this->assertEquals(700.0, (float) $aptPickup->json('quote.base_fare')); // 600 + 100 pickup surcharge
        $this->assertEquals(900.0, (float) $aptDrop->json('quote.base_fare'));   // 700 + 200 drop surcharge

        // Reset roundtrip override restores fallback 2 * oneway pricing
        $this->actingAs($this->admin)->postJson('/api/admin/booking-type-rates/bulk', [
            'booking_type' => 'roundtrip',
            'action' => 'reset',
        ])->assertOk();

        $rtResetQuote = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=roundtrip&fleet=sedan')
            ->assertOk();
        $this->assertEquals(6000.0, (float) $rtResetQuote->json('quote.base_fare'));
    }

    public function test_route_additional_amount_and_scoped_dynamic_price_fields_apply_to_quotes_bookings_and_invoices(): void
    {
        $route = RouteFare::where('from_city', 'Murshidabad')->where('to_city', 'Kolkata')->firstOrFail();

        // Add route-specific additional amount ₹250 via inline bulk adjust
        $this->actingAs($this->admin)->postJson('/api/admin/cab-routes/bulk-adjust', [
            'action' => 'inline_save',
            'routes' => [
                [
                    'id' => $route->id,
                    'base_toll' => 0,
                    'roundtrip_discount_pct' => 0,
                    'additional_amount' => 250,
                    'additional_amount_label' => 'Ferry & Permit Surcharge',
                    'sedan_fare' => null,
                    'suv_fare' => null,
                ],
            ],
        ])->assertOk();

        // Add an auto flat price field scoped ONLY to roundtrip + route_table (₹150)
        $rtFieldRes = $this->actingAs($this->admin)->postJson('/api/admin/dynamic-fields', [
            'label' => 'Roundtrip Driver Food Allowance',
            'field_type' => 'price',
            'is_price_field' => true,
            'price_amount' => 150,
            'price_mode' => 'flat',
            'charge_behavior' => 'auto',
            'booking_type_scope' => 'roundtrip',
            'route_type_scope' => 'route_table',
        ])->assertOk();

        // Add an optional per_km price field scoped ONLY to oneway + specific Murshidabad->Kolkata route (₹2/km)
        $optFieldRes = $this->actingAs($this->admin)->postJson('/api/admin/dynamic-fields', [
            'label' => 'Expressway Fast-Track Fee',
            'field_type' => 'price',
            'is_price_field' => true,
            'price_amount' => 2,
            'price_mode' => 'per_km',
            'charge_behavior' => 'optional',
            'booking_type_scope' => 'oneway',
            'route_type_scope' => 'specific_route',
            'route_fare_id' => $route->id,
        ])->assertOk();

        $optFieldKey = (string) $optFieldRes->json('field.field_key');
        $rtFieldId = (int) $rtFieldRes->json('field.id');

        // 1. One-Way without selecting optional field: base=3000 + route_additional=250 = 3250 (roundtrip-only field excluded)
        $onewayBase = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=oneway&fleet=sedan')
            ->assertOk();
        $this->assertEquals(3000.0, (float) $onewayBase->json('quote.base_fare'));
        $this->assertEquals(250.0, (float) $onewayBase->json('quote.route_additional_fare'));
        $this->assertEquals('Ferry & Permit Surcharge', $onewayBase->json('quote.route_additional_label'));
        $this->assertEquals(0.0, (float) $onewayBase->json('quote.custom_price_fields_fare'));
        $this->assertEquals(3250.0, (float) $onewayBase->json('quote.total_amount'));
        $this->assertCount(1, $onewayBase->json('available_price_fields'));
        $this->assertSame($optFieldKey, $onewayBase->json('available_price_fields.0.field_key'));

        // 2. Round-Trip gets base=6000 + route_additional=250 + auto field=150 = 6400
        $rtQuote = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=roundtrip&fleet=sedan')
            ->assertOk();
        $this->assertEquals(6000.0, (float) $rtQuote->json('quote.base_fare'));
        $this->assertEquals(250.0, (float) $rtQuote->json('quote.route_additional_fare'));
        $this->assertEquals(150.0, (float) $rtQuote->json('quote.custom_price_fields_fare'));
        $this->assertEquals(6400.0, (float) $rtQuote->json('quote.total_amount'));

        // 3. Different route (Berhampore -> Digha oneway) must NOT receive Murshidabad-only optional price field
        $otherRouteQuote = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha&trip_type=oneway&fleet=sedan')
            ->assertOk();
        $this->assertCount(0, $otherRouteQuote->json('available_price_fields'));

        // 4. Book One-Way Murshidabad->Kolkata with 230 km overrun AND optional price field selected:
        // base = 3000, extra_km = 120, route_additional = 250, optional per_km price field (230 * 2) = 460 -> total = 3830
        $bookingRes = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Murshidabad',
            'to_city' => 'Kolkata',
            'pickup_location' => 'Lalbagh Court Road',
            'drop_location' => 'Howrah Station',
            'customer_name' => 'Suman Das',
            'customer_phone' => '+91 98301 54321',
            'fleet_category' => 'sedan',
            'include_tolls' => true,
            'actual_distance_km' => 230,
            'selected_price_fields' => [$optFieldKey],
        ])->assertOk();

        $booking = $bookingRes->json('booking');
        $this->assertEquals(3000.0, (float) $booking['base_fare']);
        $this->assertEquals(120.0, (float) $booking['extra_km_fare']);
        $this->assertEquals(250.0, (float) $booking['route_additional_fare']);
        $this->assertEquals(460.0, (float) $booking['custom_price_fields_fare']);
        $this->assertEquals(3830.0, (float) $booking['total_amount']);

        // Invoice reflects all new itemized charge columns
        $invCharges = $this->actingAs($this->admin)->getJson("/api/admin/bookings/{$booking['id']}/invoice")
            ->assertOk()
            ->json('invoice.charges');
        $this->assertEquals(3000.0, (float) $invCharges['base_fare']);
        $this->assertEquals(120.0, (float) $invCharges['extra_km_fare']);
        $this->assertEquals(250.0, (float) $invCharges['route_additional_fare']);
        $this->assertEquals(460.0, (float) $invCharges['custom_price_fields_fare']);
        $this->assertEquals(3830.0, (float) $invCharges['taxable_amount']);
        $this->assertEquals(3830.0, (float) $invCharges['total_amount']);

        // Toggle & update price field endpoints work
        $this->actingAs($this->admin)->postJson("/api/admin/dynamic-fields/{$rtFieldId}/toggle")
            ->assertOk()
            ->assertJsonPath('field.is_active', false);

        $rtAfterToggle = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=roundtrip&fleet=sedan')
            ->assertOk();
        $this->assertEquals(0.0, (float) $rtAfterToggle->json('quote.custom_price_fields_fare'));

        // Percentage auto price field (10% of fare on oneway) + update endpoint
        $pctRes = $this->actingAs($this->admin)->postJson('/api/admin/dynamic-fields', [
            'label' => 'Peak Season Surcharge',
            'field_type' => 'price',
            'is_price_field' => true,
            'price_amount' => 10,
            'price_mode' => 'percentage',
            'charge_behavior' => 'auto',
            'booking_type_scope' => 'oneway',
            'route_type_scope' => 'all',
        ])->assertOk();

        $pctId = (int) $pctRes->json('field.id');
        // 10% of (base 3000 + route_additional 250 = 3250) = 325 -> total 3575
        $pctQuote = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=oneway&fleet=sedan')
            ->assertOk();
        $this->assertEquals(325.0, (float) $pctQuote->json('quote.custom_price_fields_fare'));
        $this->assertEquals(3575.0, (float) $pctQuote->json('quote.total_amount'));

        $this->actingAs($this->admin)->putJson("/api/admin/dynamic-fields/{$pctId}", [
            'label' => 'Peak Season Surcharge Updated',
            'price_amount' => 5,
            'price_mode' => 'percentage',
            'booking_type_scope' => 'oneway',
            'route_type_scope' => 'all',
        ])->assertOk()->assertJsonPath('field.price_amount', 5);

        // Verify updated UI controls render on dashboard & booking screens
        $html = $this->actingAs($this->admin)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="pricing-booking-type-pills"', $html);
        $this->assertStringContainsString('id="cab-actual-km-input"', $html);
        $this->assertStringContainsString('id="dispatch-actual-km-input"', $html);
        $this->assertStringContainsString('id="new-field-price-amount"', $html);
    }
}
