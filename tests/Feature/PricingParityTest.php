<?php

namespace Tests\Feature;

use App\Models\AirportHub;
use App\Models\CarType;
use App\Models\Coupon;
use App\Models\LocalPackage;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-2 — one pricing authority.
 *
 * Before: BookingController, RouteCalculationController, public/js/app.js and
 * the Blade view each had their own per-km table and their own coupon rules, so
 * the quoted price, the displayed price and the charged price disagreed. These
 * tests pin the quote and the charge to the same service.
 */
class PricingParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        PlatformSetting::set('enable_schedules_and_berths', '1');
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Berhampore',
            'to_city' => 'Digha',
            'pickup_location' => 'Berhampore Bus Stand',
            'drop_location' => 'New Digha Beach',
            'customer_name' => 'Parity Tester',
            'customer_phone' => '+91 97777 11111',
            'fleet_category' => 'sedan',
            'include_tolls' => true,
            'pet_friendly' => true,
            'coupon_code' => 'FIRSTWHEELS',
        ], $overrides);
    }

    public function test_quote_and_charge_are_identical(): void
    {
        $query = http_build_query([
            'from' => 'Berhampore',
            'to' => 'Digha',
            'trip_type' => 'oneway',
            'fleet' => 'sedan',
            'include_tolls' => 1,
            'pet_friendly' => 1,
            'coupon' => 'FIRSTWHEELS',
        ]);

        $quote = $this->getJson('/api/routes/calculate?'.$query)->assertStatus(200);

        $booking = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->bookingPayload())->assertStatus(200);

        $this->assertEquals($quote->json('quote.total_amount'), (float) $booking->json('booking.total_amount'));
        $this->assertEquals($quote->json('quote.base_fare'), (float) $booking->json('booking.base_fare'));
        $this->assertEquals($quote->json('quote.toll_fare'), (float) $booking->json('booking.toll_fare'));
        $this->assertEquals($quote->json('quote.addon_fare'), (float) $booking->json('booking.addon_fare'));
        $this->assertEquals($quote->json('quote.discount_fare'), (float) $booking->json('booking.discount_fare'));
    }

    public function test_route_fare_breakdown_matches_legacy_arithmetic(): void
    {
        // Seeded Berhampore -> Digha: 345 km, toll 320, sedan 12.5/km.
        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->bookingPayload([
            'pet_friendly' => false,
            'coupon_code' => null,
        ]))->assertStatus(200);

        $booking = $response->json('booking');

        // 345 * 12.5 + 320 = 4632.5 -> 4633 all-inclusive, with 320 itemised as toll.
        $this->assertEquals(4633.0, (float) $booking['total_amount']);
        $this->assertEquals(4313.0, (float) $booking['base_fare']);
        $this->assertEquals(320.0, (float) $booking['toll_fare']);
    }

    public function test_rates_come_from_the_car_types_table(): void
    {
        CarType::where('category_key', 'sedan')->update(['rate_per_km' => 20.0]);

        $response = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha&fleet=sedan');

        $response->assertStatus(200)->assertJsonPath('quote.rate_per_km', 20);
        $this->assertEquals(20.0, (float) $response->json('itemized_breakdown.sedan.rate_per_km'));
    }

    public function test_route_calculation_returns_every_fleet_itemised(): void
    {
        $response = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha');

        $response->assertStatus(200);
        $estimates = $response->json('fleet_estimates');
        $itemized = $response->json('itemized_breakdown');

        $this->assertNotEmpty($estimates);
        $this->assertSame(array_keys($estimates), array_keys($itemized));

        foreach ($itemized as $key => $row) {
            $this->assertArrayHasKey('rate_per_km', $row);
            $this->assertArrayHasKey('base_fare', $row);
            $this->assertGreaterThan(0, $row['total']);
        }
    }

    public function test_local_package_price_comes_from_the_local_packages_table(): void
    {
        LocalPackage::where('package_code', '8_80')->update([
            'sedan_fare' => 2200,
            'suv_fare' => 3000,
        ]);

        $response = $this->getJson('/api/routes/calculate?from=Kolkata&to=Kolkata&trip_type=local&local_package=8_80&fleet=sedan');

        $response->assertStatus(200);
        $this->assertEquals(2200.0, (float) $response->json('quote.base_fare'));
        $this->assertSame('local_package', $response->json('route.distance_source'));
    }

    public function test_airport_fare_comes_from_the_airport_hub(): void
    {
        AirportHub::where('iata_code', 'CCU')->update([
            'sedan_base_fare' => 1000,
            'drop_surcharge' => 200,
        ]);

        $response = $this->getJson('/api/routes/calculate?from=Kolkata&to=CCU&trip_type=airport&airport_type=drop&fleet=sedan');

        $response->assertStatus(200);
        $this->assertEquals(1200.0, (float) $response->json('quote.base_fare'));
        $this->assertSame('airport_hub', $response->json('route.distance_source'));
    }

    public function test_roundtrip_applies_the_configured_discount(): void
    {
        $single = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha&fleet=sedan');

        RouteFare::where('from_city', 'Berhampore')->where('to_city', 'Digha')
            ->update(['roundtrip_discount_pct' => 10]);

        $round = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha&trip_type=roundtrip&fleet=sedan');

        $round->assertStatus(200);
        $oneWay = (float) $single->json('fleet_estimates.sedan');
        $expected = round($oneWay * 2 * 0.9);

        $this->assertEquals($expected, (float) $round->json('fleet_estimates.sedan'));
    }

    public function test_addons_are_itemised_and_consistent_between_quote_and_charge(): void
    {
        $quote = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha&fleet=sedan&pet_friendly=1&night_allowance=1&luggage_carrier=1');

        $quote->assertStatus(200)->assertJsonPath('quote.addon_fare', 950);

        $charge = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->bookingPayload([
            'pet_friendly' => true,
            'night_allowance' => true,
            'luggage_carrier' => true,
            'coupon_code' => null,
        ]))->assertStatus(200);

        $this->assertEquals(950.0, (float) $charge->json('booking.addon_fare'));
    }

    public function test_coupon_discount_is_computed_only_on_the_server(): void
    {
        // A 25%-off coupon that the browser has no hardcoded knowledge of.
        Coupon::create([
            'code' => 'SERVERONLY',
            'type' => 'percentage',
            'value' => 25,
            'max_discount' => 400,
            'min_spend' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->bookingPayload([
            'coupon_code' => 'SERVERONLY',
            'pet_friendly' => false,
        ]))->assertStatus(200);

        $this->assertEquals(400.0, (float) $response->json('booking.discount_fare'));
    }

    public function test_unmeasurable_route_is_rejected_by_the_quote_endpoint_too(): void
    {
        $this->getJson('/api/routes/calculate?from=Atlantis&to=El%20Dorado')
            ->assertStatus(422)
            ->assertJsonPath('code', 'distance_unresolvable');
    }
}
