<?php

namespace Tests\Feature;

use App\Models\CarType;
use App\Models\LocalPackage;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBulkRateAndPricingConditionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->customer = User::where('role', 'customer')->firstOrFail();
    }

    public function test_admin_panel_renders_bulk_rate_and_conditions_manager(): void
    {
        $response = $this->actingAs($this->admin)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Bulk Rates, Night Stay & Fare Conditions Manager', false);
        $response->assertSee('id="admin-bulk-car-rates-table"', false);
        $response->assertSee('id="rule-night-start"', false);
        $response->assertSee('id="rule-night-end"', false);
        $response->assertSee('id="rule-rt-min-km"', false);
        $response->assertSee('id="admin-bulk-local-packages-table"', false);
        $response->assertSee('id="admin-bulk-routes-table"', false);
    }

    public function test_admin_can_bulk_update_car_type_rates_and_bookings_reflect_changes(): void
    {
        $hatch = CarType::where('category_key', 'hatchback')->firstOrFail();
        $sedan = CarType::where('category_key', 'sedan')->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson('/api/admin/car-types/bulk-rates', [
            'rates' => [
                [
                    'id' => $hatch->id,
                    'rate_per_km' => 13.0,
                    'extra_km_rate' => 13.5,
                    'base_fare_min' => 900.0,
                    'is_active' => true,
                ],
                [
                    'id' => $sedan->id,
                    'rate_per_km' => 16.0,
                    'extra_km_rate' => 16.5,
                    'base_fare_min' => 1200.0,
                    'is_active' => true,
                ],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('car_types', [
            'id' => $hatch->id,
            'rate_per_km' => 13.0,
            'extra_km_rate' => 13.5,
            'base_fare_min' => 900.0,
        ]);
        $this->assertDatabaseHas('car_types', [
            'id' => $sedan->id,
            'rate_per_km' => 16.0,
            'extra_km_rate' => 16.5,
            'base_fare_min' => 1200.0,
        ]);

        $quote = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha&fleet=sedan')
            ->assertStatus(200);
        $this->assertEquals(16.0, (float) $quote->json('quote.rate_per_km'));
    }

    public function test_admin_can_persist_night_stay_hours_and_conditions_used_by_quotes_and_bookings(): void
    {
        $payload = [
            'addon_pet_friendly_rate' => 500,
            'addon_night_allowance_rate' => 650,
            'addon_luggage_carrier_rate' => 300,
            'night_stay_start_hour' => '22:00',
            'night_stay_end_hour' => '05:30',
            'roundtrip_min_km_per_day' => 300,
            'driver_day_allowance' => 250,
            'free_waiting_minutes' => 30,
            'waiting_charge_per_hour' => 200,
            'free_cancellation_hours' => 2,
            'other_booking_conditions' => 'State border tax extra as per actuals.',
        ];

        $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/pricing-conditions', $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame('650', PlatformSetting::get('addon_night_allowance_rate'));
        $this->assertSame('22:00', PlatformSetting::get('night_stay_start_hour'));
        $this->assertSame('05:30', PlatformSetting::get('night_stay_end_hour'));
        $this->assertSame('300', PlatformSetting::get('roundtrip_min_km_per_day'));

        $quote = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha&fleet=sedan&night_allowance=1')
            ->assertStatus(200);

        $this->assertEquals(650.0, (float) $quote->json('addons.night_allowance'));
        $this->assertSame('22:00', $quote->json('conditions.night_stay_start_hour'));
        $this->assertEquals(300, $quote->json('conditions.roundtrip_min_km_per_day'));
        // Night allowance (650) + driver day allowance (250) = 900
        $this->assertEquals(900.0, (float) $quote->json('quote.addon_fare'));

        $booking = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Berhampore',
            'to_city' => 'Digha',
            'pickup_location' => 'Berhampore Bus Stand',
            'drop_location' => 'New Digha Beach',
            'customer_name' => 'Condition Tester',
            'customer_phone' => '+91 97777 22222',
            'fleet_category' => 'sedan',
            'night_allowance' => true,
        ])->assertStatus(200);

        $this->assertEquals(
            (float) $quote->json('quote.total_amount'),
            (float) $booking->json('booking.total_amount')
        );
    }

    public function test_admin_can_bulk_update_local_packages_hours_and_rates(): void
    {
        $pkg4 = LocalPackage::where('package_code', '4_40')->firstOrFail();
        $pkg8 = LocalPackage::where('package_code', '8_80')->firstOrFail();

        $this->actingAs($this->admin)->postJson('/api/admin/local-packages/bulk-rates', [
            'packages' => [
                [
                    'id' => $pkg4->id,
                    'duration_hours' => 5,
                    'included_km' => 50,
                    'sedan_fare' => 1650,
                    'suv_fare' => 2250,
                    'extra_km_rate' => 16,
                    'extra_hour_rate' => 180,
                    'is_active' => true,
                ],
                [
                    'id' => $pkg8->id,
                    'duration_hours' => 8,
                    'included_km' => 80,
                    'sedan_fare' => 2600,
                    'suv_fare' => 3500,
                    'extra_km_rate' => 16,
                    'extra_hour_rate' => 180,
                    'is_active' => true,
                ],
            ],
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('local_packages', [
            'id' => $pkg4->id,
            'duration_hours' => 5,
            'included_km' => 50,
            'sedan_fare' => 1650,
            'extra_hour_rate' => 180,
        ]);

        $quote = $this->getJson('/api/routes/calculate?from=Kolkata&to=Kolkata&trip_type=local&local_package=4_40&fleet=sedan')
            ->assertStatus(200);

        $this->assertEquals(1650.0, (float) $quote->json('quote.base_fare'));
        $this->assertEquals(50, $quote->json('quote.distance_km'));
    }

    public function test_admin_can_bulk_adjust_outstation_cab_routes(): void
    {
        $route = RouteFare::where('from_city', 'Pune')->where('to_city', 'Goa')->firstOrFail();
        $route->update(['sedan_fare' => 6000]);

        $this->actingAs($this->admin)->postJson('/api/admin/cab-routes/bulk-adjust', [
            'action' => 'set_roundtrip_discount',
            'roundtrip_discount_pct' => 8,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->assertEquals(8.0, (float) $route->fresh()->roundtrip_discount_pct);

        $this->actingAs($this->admin)->postJson('/api/admin/cab-routes/bulk-adjust', [
            'action' => 'percent_adjust',
            'percent' => 10,
        ])->assertStatus(200);

        $this->assertEquals(6600.0, (float) $route->fresh()->sedan_fare);

        $this->actingAs($this->admin)->postJson('/api/admin/cab-routes/bulk-adjust', [
            'action' => 'use_car_type_rates',
        ])->assertStatus(200);

        $this->assertNull($route->fresh()->sedan_fare);
    }

    public function test_non_admin_cannot_access_bulk_rate_or_condition_endpoints(): void
    {
        $this->actingAs($this->customer)->postJson('/api/admin/car-types/bulk-rates', ['rates' => []])->assertStatus(403);
        $this->actingAs($this->customer)->postJson('/api/admin/local-packages/bulk-rates', ['packages' => []])->assertStatus(403);
        $this->actingAs($this->customer)->postJson('/api/admin/cab-routes/bulk-adjust', ['action' => 'use_car_type_rates'])->assertStatus(403);
        $this->actingAs($this->customer)->postJson('/api/admin/settings/pricing-conditions', [])->assertStatus(403);
    }

    public function test_booking_types_and_bookings_reflect_active_car_type_per_km_rates(): void
    {
        // Deactivate all except 2 car types (sedan & suv) and change their per-km rates.
        CarType::whereNotIn('category_key', ['sedan', 'suv'])->update(['is_active' => false]);
        CarType::where('category_key', 'sedan')->update(['rate_per_km' => 20.0]);
        CarType::where('category_key', 'suv')->update(['rate_per_km' => 30.0]);

        // Seeded Pune -> Goa: 450 km, toll 480.
        // Sedan: 450 * 20 + 480 = 9480; SUV: 450 * 30 + 480 = 13980.
        $page = $this->actingAs($this->admin)->get('/');
        $page->assertStatus(200);
        $page->assertSee('Comfort Sedan: <strong>₹9,480</strong>', false);
        $page->assertSee('Family SUV: <strong>₹13,980</strong>', false);
        $page->assertDontSee('Compact Hatchback: <strong>', false);

        // Route calculation excludes deactivated car types and prices active ones from rate_per_km.
        $oneway = $this->getJson('/api/routes/calculate?from=Pune&to=Goa&fleet=sedan')
            ->assertStatus(200);
        $this->assertEquals(9480.0, (float) $oneway->json('fleet_estimates.sedan'));
        $this->assertEquals(13980.0, (float) $oneway->json('fleet_estimates.suv'));
        $this->assertArrayNotHasKey('hatchback', $oneway->json('fleet_estimates'));

        // Actual One-Way booking charges the updated per-km rate.
        $booking = $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => 'Rate Verifier',
            'customer_phone' => '+91 98765 43210',
            'fleet_category' => 'sedan',
            'include_tolls' => true,
        ])->assertStatus(200);
        $this->assertEquals(9480.0, (float) $booking->json('booking.total_amount'));

        // Local package: if Hatchback is active at ₹9/km (below Sedan ₹20/km), it scales proportionally without clamping up to Sedan.
        CarType::where('category_key', 'hatchback')->update(['is_active' => true, 'rate_per_km' => 9.0]);
        $localHatch = $this->getJson('/api/routes/calculate?from=Kolkata&to=Kolkata&trip_type=local&local_package=8_80&fleet=hatchback')
            ->assertStatus(200);
        // Seeded 8_80 SUV fare = 3200, SUV rate = 30.0, Hatch rate = 9.0 -> round(3200 * (9 / 30)) = 960
        $this->assertEquals(960.0, (float) $localHatch->json('quote.base_fare'));
    }
}
