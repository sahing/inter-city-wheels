<?php

namespace Tests\Feature;

use App\Models\CarType;
use App\Models\RouteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCarTypeAndRouteFareScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function getAdminUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@intercitywheels.com'],
            [
                'name' => 'Vikram Singhania (Ops Admin)',
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'phone' => '+91 99000 00001',
            ]
        );
    }

    protected function getCustomerUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'customer@intercitywheels.com'],
            [
                'name' => 'Rahul Verma',
                'password' => bcrypt('password123'),
                'role' => 'customer',
                'phone' => '+91 98765 43210',
            ]
        );
    }

    public function test_admin_panel_renders_car_types_section_and_modal(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);

        // Sidebar and UI checks
        $response->assertSee('Car Types & Fleet Setup', false);
        $response->assertSee('id="admin-sec-car-types"', false);
        $response->assertSee('id="admin-car-type-modal"', false);
        $response->assertSee('id="admin-car-types-table"', false);
    }

    public function test_public_car_types_api_returns_active_categories(): void
    {
        $response = $this->getJson('/api/car-types');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'car_types' => [
                '*' => ['id', 'name', 'category_key', 'rate_per_km', 'seats', 'is_active'],
            ],
        ]);
        $this->assertTrue($response->json('success'));
        $carTypes = $response->json('car_types');
        $this->assertGreaterThanOrEqual(4, count($carTypes));
    }

    public function test_admin_can_create_new_car_type(): void
    {
        $admin = $this->getAdminUser();

        $payload = [
            'name' => 'Executive EV Sedan',
            'category_key' => 'ev_executive',
            'badge' => 'Zero Emission',
            'models' => 'BYD Seal, Hyundai Ioniq 5',
            'seats' => '4 Seats',
            'luggage' => '3 Bags',
            'rate_per_km' => 20.00,
            'extra_km_rate' => 22.00,
            'base_fare_min' => 1200.00,
            'order_index' => 10,
            'is_active' => true,
        ];

        $response = $this->actingAs($admin)->postJson('/api/admin/car-types', $payload);
        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('car_types', [
            'category_key' => 'ev_executive',
            'name' => 'Executive EV Sedan',
            'seats' => '4 Seats',
        ]);
    }

    public function test_admin_can_update_existing_car_type(): void
    {
        $admin = $this->getAdminUser();
        $carType = CarType::first();

        $updateData = [
            'name' => $carType->name.' Prime Edition',
            'rate_per_km' => 16.50,
            'seats' => $carType->seats,
            'luggage' => $carType->luggage,
            'is_active' => true,
        ];

        $response = $this->actingAs($admin)->putJson("/api/admin/car-types/{$carType->id}", $updateData);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('car_types', [
            'id' => $carType->id,
            'name' => $carType->name.' Prime Edition',
            'rate_per_km' => 16.50,
        ]);
    }

    public function test_admin_can_toggle_car_type_active_status(): void
    {
        $admin = $this->getAdminUser();
        $carType = CarType::first();
        $originalStatus = (bool) $carType->is_active;

        $response = $this->actingAs($admin)->postJson("/api/admin/car-types/{$carType->id}/toggle");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $carType->refresh();
        $this->assertEquals(! $originalStatus, (bool) $carType->is_active);
    }

    public function test_admin_can_create_route_with_custom_car_fare_schedule_and_departure_timings(): void
    {
        $admin = $this->getAdminUser();

        $payload = [
            'from_city' => 'Pune',
            'to_city' => 'Mahabaleshwar',
            'distance_km' => 125,
            'estimated_time' => '3.5 hrs',
            'base_toll' => 180,
            'hatchback_fare' => 2200,
            'sedan_fare' => 2600,
            'suv_fare' => 3400,
            'innova_fare' => 4200,
            'tempo_fare' => 6500,
            'luxury_fare' => 8500,
            'departure_schedule' => 'Daily departures every 2 hours (06:00 AM - 10:00 PM)',
            'schedule_frequency' => 'Every 2 Hours',
        ];

        $response = $this->actingAs($admin)->postJson('/api/admin/cab-routes', $payload);
        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('route_fares', [
            'from_city' => 'Pune',
            'to_city' => 'Mahabaleshwar',
            'hatchback_fare' => 2200,
            'sedan_fare' => 2600,
            'innova_fare' => 4200,
            'departure_schedule' => 'Daily departures every 2 hours (06:00 AM - 10:00 PM)',
            'schedule_frequency' => 'Every 2 Hours',
        ]);
    }

    public function test_booking_uses_route_car_fare_schedule_override(): void
    {
        $customer = $this->getCustomerUser();

        // Create or update a specific route with fixed car-type fares
        RouteFare::updateOrCreate(
            ['from_city' => 'Mumbai', 'to_city' => 'Pune'],
            [
                'distance_km' => 150,
                'estimated_time' => '3.0 hrs',
                'base_toll' => 320,
                'sedan_fare' => 2499.00,
                'innova_fare' => 3999.00,
                'hatchback_fare' => 1999.00,
            ]
        );

        // Test booking with sedan
        $bookingPayload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Mumbai',
            'to_city' => 'Pune',
            'pickup_location' => 'Dadar, Mumbai',
            'drop_location' => 'Shivajinagar, Pune',
            'customer_name' => 'Rahul Verma',
            'customer_phone' => '+91 98765 43210',
            'fleet_category' => 'sedan',
        ];

        $response = $this->actingAs($customer)->postJson('/api/bookings', $bookingPayload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $booking = $response->json('booking');
        // Sedan fare should match route sedan_fare override (2499)
        $this->assertEquals(2499.00, (float) $booking['total_amount']);

        // Now test booking with innova on the same route
        $bookingPayload['fleet_category'] = 'innova';
        $responseInnova = $this->actingAs($customer)->postJson('/api/bookings', $bookingPayload);
        $responseInnova->assertStatus(200);
        $bookingInnova = $responseInnova->json('booking');
        $this->assertEquals(3999.00, (float) $bookingInnova['total_amount']);
    }

    public function test_custom_car_type_populates_cab_booking_and_manual_booking(): void
    {
        $admin = $this->getAdminUser();
        $customer = $this->getCustomerUser();

        $this->actingAs($admin)->postJson('/api/admin/car-types', [
            'name' => 'Executive EV Sedan',
            'category_key' => 'ev_executive',
            'badge' => 'Zero Emission',
            'models' => 'BYD Seal, Hyundai Ioniq 5',
            'seats' => '4 Seats',
            'luggage' => '3 Bags',
            'rate_per_km' => 20.00,
            'extra_km_rate' => 22.00,
            'base_fare_min' => 1200.00,
            'order_index' => 10,
            'is_active' => true,
        ])->assertStatus(201);

        // Customer cab booking page gets INITIAL_CAR_TYPES and filter pill
        $customerPage = $this->actingAs($customer)->get('/');
        $customerPage->assertStatus(200);
        $customerPage->assertSee('window.INITIAL_CAR_TYPES', false);
        $customerPage->assertSee('ev_executive', false);
        $customerPage->assertSee('Executive EV Sedan', false);

        // Admin manual booking & edit booking dropdowns include the new car type
        $adminPage = $this->actingAs($admin)->get('/');
        $adminPage->assertStatus(200);
        $adminPage->assertSee('<option value="ev_executive">Executive EV Sedan</option>', false);

        // Customer booking with custom car type preserves cab_type instead of falling back to SUV
        $bookingRes = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Mumbai',
            'to_city' => 'Pune',
            'pickup_location' => 'Dadar, Mumbai',
            'drop_location' => 'Shivajinagar, Pune',
            'customer_name' => 'EV Passenger',
            'customer_phone' => '+91 98765 43210',
            'fleet_category' => 'ev_executive',
        ]);
        $bookingRes->assertStatus(200);
        $bookingRes->assertJsonPath('booking.cab_type', 'EV_EXECUTIVE');
    }

    public function test_edit_cab_route_modal_and_api_expose_exact_customer_calculated_fares(): void
    {
        $admin = $this->getAdminUser();

        $route = RouteFare::updateOrCreate(
            ['from_city' => 'Murshidabad', 'to_city' => 'Kolkata'],
            [
                'distance_km' => 220,
                'estimated_time' => '5h 30m',
                'base_toll' => 240,
                'additional_amount' => 150,
                'additional_amount_label' => 'Bridge Cess',
                'roundtrip_discount_pct' => 10,
                'sedan_fare' => 3100,
                'default_car_type' => 'sedan',
                'is_active' => true,
            ]
        );

        // Customer quotes (oneway & roundtrip) as a public/customer user sees them
        $owCustomer = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=oneway')
            ->assertOk()
            ->json('fleet_estimates');
        $rtCustomer = $this->getJson('/api/routes/calculate?from=Murshidabad&to=Kolkata&trip_type=roundtrip')
            ->assertOk()
            ->json('fleet_estimates');

        // 1. Opening Edit Cab Route returns customer_fare_preview matching customer quote identically
        $showRes = $this->actingAs($admin)->getJson("/api/admin/cab-routes/{$route->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer_fare_preview.distance_km', 220)
            ->assertJsonPath('customer_fare_preview.roundtrip_distance_km', 440)
            ->assertJsonPath('customer_fare_preview.additional_amount', 150);

        $fleets = collect($showRes->json('customer_fare_preview.fleets'))->keyBy('category_key');
        $this->assertTrue($fleets->has('sedan'));
        $this->assertTrue($fleets['sedan']['is_preferred']);
        $this->assertTrue($fleets['sedan']['has_custom_route_fare']);
        $this->assertEquals((float) $owCustomer['sedan'], (float) $fleets['sedan']['oneway']['total_amount']);
        $this->assertEquals((float) $rtCustomer['sedan'], (float) $fleets['sedan']['roundtrip']['total_amount']);
        $this->assertEquals((float) $owCustomer['suv'], (float) $fleets['suv']['oneway']['total_amount']);
        $this->assertEquals((float) $rtCustomer['suv'], (float) $fleets['suv']['roundtrip']['total_amount']);

        // 2. Live modal edits preview recalculated customer fares via POST /api/admin/cab-routes/preview-fares
        $previewRes = $this->actingAs($admin)->postJson('/api/admin/cab-routes/preview-fares', [
            'route_id' => $route->id,
            'from_city' => 'Murshidabad',
            'to_city' => 'Kolkata',
            'distance_km' => 240,
            'base_toll' => 300,
            'additional_amount' => 200,
            'additional_amount_label' => 'Bridge Cess',
            'roundtrip_discount_pct' => 10,
            'sedan_fare' => 3500,
            'default_car_type' => 'sedan',
        ])->assertOk()->assertJsonPath('success', true);

        $updatedFleets = collect($previewRes->json('customer_fare_preview.fleets'))->keyBy('category_key');
        $this->assertGreaterThan((float) $owCustomer['sedan'], (float) $updatedFleets['sedan']['oneway']['total_amount']);
        $this->assertEquals(3700.0, (float) $updatedFleets['sedan']['oneway']['total_amount']); // 3500 override + 200 addl

        // 3. Admin modal HTML & JS ship the preview container and live handlers
        $adminHtml = $this->actingAs($admin)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="admin-cab-route-fare-preview-box"', $adminHtml);
        $this->assertStringContainsString('Calculated Customer Fare (What the Customer Sees)', $adminHtml);

        $jsContent = file_get_contents(public_path('js/app.js')) ?: '';
        $this->assertStringContainsString('renderAdminCabRouteFarePreview', $jsContent);
        $this->assertStringContainsString('scheduleAdminCabRouteFarePreview', $jsContent);
    }
}
