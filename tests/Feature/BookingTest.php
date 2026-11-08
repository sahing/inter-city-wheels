<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\RouteFare;
use App\Models\SeatLayout;
use App\Models\Coupon;
use App\Models\Driver;
use App\Models\Booking;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_home_page_loads_with_laravel_blade(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('INTERCITY WHEELS');
        $response->assertSee('Choose Berths', false);
        $response->assertSee('admin-sidebar', false);
        $response->assertSee('admin-kpi-grid', false);
        $response->assertSee('admin-sec-bookings', false);
        $response->assertSee('admin-table-compact', false);
        $response->assertSee('admin-allot-driver-modal', false);
        $response->assertSee('admin-view-booking-modal', false);
        $response->assertSee('admin-edit-booking-modal', false);
        $response->assertSee('admin-add-booking-modal', false);
        $response->assertSee('admin-invoice-modal', false);
        $response->assertSee('add-dynamic-field-modal', false);
    }

    public function test_admin_user_gets_desktop_full_admin_panel_by_default(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->first();
        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);
        $response->assertSee('role-admin-mode', false);
        $response->assertSee('admin-active-view', false);
        $response->assertSee('id="tab-admin" class="tab-pane active"', false);
        $response->assertSee('nav-tab-btn active" data-target="admin"', false);
    }

    public function test_health_check_endpoint(): void
    {
        $response = $this->get('/health');
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'framework', 'database', 'timestamp']);
    }

    public function test_route_calculation_api(): void
    {
        $response = $this->get('/api/routes/calculate?from=Pune&to=Goa');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'route' => [
                'from_city' => 'Pune',
                'to_city' => 'Goa',
                'distance_km' => 450
            ]
        ]);
    }

    public function test_store_cab_booking_with_coupon(): void
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => 'Amitabh Bachchan',
            'customer_phone' => '+91 99999 88888',
            'fleet_category' => 'suv',
            'include_tolls' => true,
            'pet_friendly' => true,
            'coupon_code' => 'FIRSTWHEELS'
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'service_type' => 'cab',
                'status' => 'Confirmed'
            ]
        ]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Amitabh Bachchan',
            'service_type' => 'cab'
        ]);
    }

    public function test_store_bus_sleeper_berth_booking(): void
    {
        $payload = [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate Bay 4',
            'drop_location' => 'Panjim KTC',
            'customer_name' => 'Pooja Hegde',
            'customer_phone' => '+91 91234 56789',
            'selected_units' => ['L1', 'L4'],
            'passengers' => [
                ['name' => 'Pooja Hegde', 'age' => 28, 'gender' => 'female'],
                ['name' => 'Friend', 'age' => 27, 'gender' => 'female']
            ]
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Pooja Hegde',
            'service_type' => 'bus'
        ]);
    }

    public function test_driver_bidding_counter_offer(): void
    {
        $driver = Driver::first();
        $response = $this->postJson('/api/bids/counter', [
            'driver_id' => $driver->id,
            'counter_amount' => 4600
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'counter_amount' => 4600
        ]);
    }

    public function test_admin_dynamic_form_field_creation(): void
    {
        $response = $this->postJson('/api/admin/dynamic-fields', [
            'label' => 'Child Booster Seat Needed',
            'field_type' => 'checkbox',
            'applies_to' => 'cab',
            'conditional_logic' => 'Family Trips'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('dynamic_form_fields', [
            'label' => 'Child Booster Seat Needed',
            'field_type' => 'checkbox'
        ]);
    }
}
