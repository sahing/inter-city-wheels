<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CarType;
use App\Models\RouteFare;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
                'phone' => '+91 99000 00001'
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
                'phone' => '+91 98765 43210'
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
                '*' => ['id', 'name', 'category_key', 'rate_per_km', 'seats', 'is_active']
            ]
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
            'name' => $carType->name . ' Prime Edition',
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
            'name' => $carType->name . ' Prime Edition',
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
        $this->assertEquals(!$originalStatus, (bool) $carType->is_active);
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
}
