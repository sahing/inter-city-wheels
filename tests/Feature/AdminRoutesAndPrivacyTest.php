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

        // The auctions tab renders live, masked auctions only.
        $response->assertSee('Available Outstation Trips for Driver Bidding');
        $response->assertSee('id="bidding-cards-stream"', false);
        $response->assertSee('unlock only if you win the auction', false);

        // The old demo bid modal (fake trips, fake passenger phones) is gone.
        $response->assertDontSee('id="driver-bid-modal"', false);
        $response->assertDontSee('Chauffeur Ride Commitment', false);
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

    public function test_admin_can_calculate_cab_route_distance_via_api_or_override_manually()
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
        $customer = User::where('role', 'customer')->first() ?? User::factory()->create(['role' => 'customer']);

        // 1. Admin modal includes both API calculation and manual entry controls
        $page = $this->actingAs($admin)->get('/');
        $page->assertStatus(200);
        $page->assertSee('id="admin-cab-route-mode-api-btn"', false);
        $page->assertSee('id="admin-cab-route-mode-manual-btn"', false);
        $page->assertSee('Calculate via API', false);
        $page->assertSee('Manual Entry', false);

        // 2. Non-admin cannot call calculate-distance endpoint
        $this->actingAs($customer)
            ->postJson('/api/admin/cab-routes/calculate-distance', [
                'from_city' => 'Kolkata',
                'to_city' => 'Digha',
            ])
            ->assertStatus(403);

        // 3. Admin API calculation returns fresh live metrics + coordinates even if route already exists
        config([
            'services.osm.enabled' => true,
            'services.osm.throttle' => false,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'router.project-osrm.org/*' => \Illuminate\Support\Facades\Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 186400, 'duration' => 14400],
                ],
            ], 200),
            'nominatim.openstreetmap.org/*' => \Illuminate\Support\Facades\Http::response([], 200),
        ]);

        $calcRes = $this->actingAs($admin)->postJson('/api/admin/cab-routes/calculate-distance', [
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
        ]);
        $calcRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'distance_km' => 186,
                    'estimated_time' => '4h',
                    'source' => 'location_dataset+osrm_route',
                ],
            ]);
        $this->assertNotNull($calcRes->json('data.from_lat'));
        $this->assertNotNull($calcRes->json('data.to_lat'));

        // 4. Admin can still save a manual KM override alongside resolved coordinates
        $storeRes = $this->actingAs($admin)->postJson('/api/admin/cab-routes', [
            'from_city' => 'Kolkata',
            'to_city' => 'Digha Custom Bypass',
            'distance_km' => 195,
            'estimated_time' => '4h 20m',
            'base_toll' => 210,
            'from_lat' => $calcRes->json('data.from_lat'),
            'from_lng' => $calcRes->json('data.from_lng'),
            'to_lat' => $calcRes->json('data.to_lat'),
            'to_lng' => $calcRes->json('data.to_lng'),
            'is_active' => true,
        ]);
        $storeRes->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('route_fares', [
            'from_city' => 'Kolkata',
            'to_city' => 'Digha Custom Bypass',
            'distance_km' => 195,
            'estimated_time' => '4h 20m',
        ]);

        // 5. Unresolvable cities return 422 prompting manual entry
        $this->actingAs($admin)
            ->postJson('/api/admin/cab-routes/calculate-distance', [
                'from_city' => 'ZzzUnknownOrigin999',
                'to_city' => 'ZzzUnknownDest999',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
