<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_panel_renders_cab_routes_and_booking_types_sections()
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);

        // Check sidebar navigation links
        $response->assertSee('Outstation Cab Routes');
        $response->assertSee('data-section="cab-routes"', false);
        $response->assertSee('Booking Types', false);
        $response->assertSee('data-section="booking-types"', false);

        // Check content sections
        $response->assertSee('id="admin-sec-cab-routes"', false);
        $response->assertSee('id="admin-sec-booking-types"', false);
        $response->assertSee('Outstation Cab Routes', false);
        $response->assertSee('Booking Types', false);
        $response->assertSee('One Way Outstation');
        $response->assertSee('Round Trip Outstation');
        $response->assertSee('Local Hourly Rentals');
        $response->assertSee('Airport Transit');
    }

    public function test_admin_can_manage_outstation_cab_routes_lifecycle()
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        // 1. List cab routes
        $listRes = $this->actingAs($admin)->getJson('/api/admin/cab-routes');
        $listRes->assertStatus(200);
        $listRes->assertJson(['success' => true]);

        // 2. Create new cab route
        $createRes = $this->actingAs($admin)->postJson('/api/admin/cab-routes', [
            'from_city' => 'Kolkata',
            'to_city' => 'Murshidabad',
            'distance_km' => 220,
            'estimated_time' => '5h 15m',
            'base_toll' => 240,
            'is_active' => true,
        ]);
        $createRes->assertStatus(201);
        $createRes->assertJson(['success' => true]);
        $routeId = $createRes->json('route.id');

        $this->assertDatabaseHas('route_fares', [
            'id' => $routeId,
            'from_city' => 'Kolkata',
            'to_city' => 'Murshidabad',
            'distance_km' => 220,
            'is_active' => true,
        ]);

        // 3. Show route
        $showRes = $this->actingAs($admin)->getJson("/api/admin/cab-routes/{$routeId}");
        $showRes->assertStatus(200);
        $showRes->assertJson([
            'success' => true,
            'route' => ['from_city' => 'Kolkata', 'to_city' => 'Murshidabad'],
        ]);

        // 4. Update route
        $updateRes = $this->actingAs($admin)->putJson("/api/admin/cab-routes/{$routeId}", [
            'distance_km' => 225,
            'estimated_time' => '5h 30m',
            'base_toll' => 260,
        ]);
        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('route_fares', [
            'id' => $routeId,
            'distance_km' => 225,
            'base_toll' => 260,
        ]);

        // 5. Toggle status
        $toggleRes = $this->actingAs($admin)->postJson("/api/admin/cab-routes/{$routeId}/toggle");
        $toggleRes->assertStatus(200);
        $this->assertDatabaseHas('route_fares', [
            'id' => $routeId,
            'is_active' => false,
        ]);

        // 6. Delete route
        $delRes = $this->actingAs($admin)->deleteJson("/api/admin/cab-routes/{$routeId}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('route_fares', ['id' => $routeId]);
    }

    public function test_driver_bidding_masks_passenger_contact_details_until_allotted()
    {
        $driverUser = User::where('role', 'driver')->first() ?? User::factory()->create(['role' => 'driver']);

        $response = $this->actingAs($driverUser)->get('/');
        $response->assertStatus(200);

        // Ensure passenger privacy notice is rendered in the driver view
        $response->assertSee('Passenger phone number and exact doorstep pickup address remain masked until your bid is accepted', false);
        $response->assertSee('Chauffeur Ride Commitment', false);
        $response->assertSee('Conclusive Ride Commitment Statement', false);
    }

    public function test_driver_conclusive_bid_submission_statement()
    {
        $driverUser = User::where('role', 'driver')->first() ?? User::factory()->create(['role' => 'driver']);
        $driver = Driver::first() ?? Driver::create([
            'name' => 'Ramesh Patil',
            'phone' => '9820012345',
            'vehicle_model' => 'Innova Crysta',
            'vehicle_plate' => 'MH 12 QX 4920',
            'fleet_category' => 'suv',
        ]);

        $res = $this->actingAs($driverUser)->postJson('/api/bids/submit', [
            'trip_id' => 'TRIP-DEMO-99',
            'from_location' => 'Kolkata Airport',
            'to_location' => 'Murshidabad Heritage Gate',
            'distance_km' => 220,
            'bid_amount' => 3800,
            'driver_id' => $driver->id,
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);
        $this->assertStringContainsString('I will take and do this ride from Kolkata Airport to Murshidabad Heritage Gate (220 km) at this price: ₹3,800. I accept this.', $res->json('bid.conclusive_statement'));
    }

    public function test_admin_sees_all_booking_details_without_restriction()
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        // Create a booking with sensitive contact info
        $booking = Booking::create([
            'booking_reference' => 'ICW-PRIVATE-999',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Kolkata → Murshidabad',
            'pickup_location' => '12 Park Street, Flat 4B, Kolkata',
            'drop_location' => 'Hazarduari Palace Hotel, Murshidabad',
            'customer_name' => 'Sourav Mukherjee',
            'customer_phone' => '+91 98300 99999',
            'customer_email' => 'sourav@example.com',
            'base_fare' => 3500,
            'total_amount' => 3740,
            'status' => 'Confirmed',
            'start_otp' => '4321',
        ]);

        // Admin API call returns full unmasked customer details
        $res = $this->actingAs($admin)->getJson("/api/admin/bookings/{$booking->id}");
        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'booking' => [
                'customer_name' => 'Sourav Mukherjee',
                'customer_phone' => '+91 98300 99999',
                'customer_email' => 'sourav@example.com',
                'pickup_location' => '12 Park Street, Flat 4B, Kolkata',
                'drop_location' => 'Hazarduari Palace Hotel, Murshidabad',
                'start_otp' => '4321',
            ],
        ]);

        // Admin page rendering includes full customer phone and route
        $pageRes = $this->actingAs($admin)->get('/');
        $pageRes->assertStatus(200);
        $pageRes->assertSee('ICW-PRIVATE-999');
        $pageRes->assertSee('Sourav Mukherjee');
        $pageRes->assertSee('+91 98300 99999');
        $pageRes->assertSee('12 Park Street, Flat 4B, Kolkata');
    }
}
