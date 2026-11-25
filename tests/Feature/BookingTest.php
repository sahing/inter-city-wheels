<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\PlatformSetting;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $response->assertSee('Outstation Cabs', false);
        $response->assertSee('admin-invoice-modal', false);
    }

    public function test_admin_user_gets_desktop_full_admin_panel_by_default(): void
    {
        $admin = User::where('role', 'admin')->first();
        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);
        $response->assertSee('role-admin-mode', false);
        $response->assertSee('admin-active-view', false);
        $response->assertSee('id="tab-admin" class="tab-pane active"', false);
        $response->assertSee('nav-tab-btn active" data-target="admin"', false);
        $response->assertSee('admin-sidebar', false);
        $response->assertSee('admin-kpi-grid', false);
        $response->assertSee('admin-sec-bookings', false);
        $response->assertSee('admin-table-compact', false);
        $response->assertSee('admin-allot-driver-modal', false);
        $response->assertSee('admin-view-booking-modal', false);
        $response->assertSee('admin-edit-booking-modal', false);
        $response->assertSee('admin-add-booking-modal', false);
        $response->assertSee('add-dynamic-field-modal', false);
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
                'distance_km' => 450,
            ],
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
            'coupon_code' => 'FIRSTWHEELS',
        ];

        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'service_type' => 'cab',
                'status' => 'Requested',
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Amitabh Bachchan',
            'service_type' => 'cab',
        ]);
    }

    public function test_outstation_cab_simplified_screen_flow_renders(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Assert 3-step progressive stepper flow exists
        $response->assertSee('cab-stepper-header', false);
        $response->assertSee('pill-step-1', false);
        $response->assertSee('pill-step-2', false);
        $response->assertSee('pill-step-3', false);

        // Step Panels
        $response->assertSee('cab-step-1', false);
        $response->assertSee('cab-step-2', false);
        $response->assertSee('cab-step-3', false);

        // Route, Vehicle & Confirmation CTAs
        $response->assertSee('cab-route-insight-strip', false);
        $response->assertSee('trust-inclusions-strip', false);
        $response->assertSee('book-cab-confirm-btn', false);
    }

    public function test_store_bus_sleeper_berth_booking(): void
    {
        PlatformSetting::set('enable_schedules_and_berths', '1');

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
                ['name' => 'Friend', 'age' => 27, 'gender' => 'female'],
            ],
        ];

        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Pooja Hegde',
            'service_type' => 'bus',
        ]);
    }

    public function test_driver_bidding_counter_offer(): void
    {
        $driver = Driver::first();
        $this->actingAs(User::where('role', 'driver')->firstOrFail());
        $response = $this->postJson('/api/bids/counter', [
            'driver_id' => $driver->id,
            'counter_amount' => 4600,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'counter_amount' => 4600,
        ]);
    }

    public function test_driver_conclusive_ride_bid_submission(): void
    {
        $driver = Driver::first();
        $this->actingAs(User::where('role', 'driver')->firstOrFail());
        $response = $this->postJson('/api/bids/submit', [
            'trip_id' => 'trip-wb-101',
            'from_location' => 'Baharampur (Mohona Central Bus Stand)',
            'to_location' => 'Kolkata (Netaji Subhash Chandra Bose Intl Airport, CCU)',
            'distance_km' => 205,
            'bid_amount' => 3800,
            'driver_id' => $driver->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'bid' => [
                'trip_id' => 'trip-wb-101',
                'from_location' => 'Baharampur (Mohona Central Bus Stand)',
                'to_location' => 'Kolkata (Netaji Subhash Chandra Bose Intl Airport, CCU)',
                'distance_km' => 205,
                'bid_amount' => 3800,
                'conclusive_statement' => 'I will take and do this ride from Baharampur (Mohona Central Bus Stand) to Kolkata (Netaji Subhash Chandra Bose Intl Airport, CCU) (205 km) at this price: ₹3,800. I accept this.',
            ],
        ]);
    }

    public function test_admin_dynamic_form_field_creation(): void
    {
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $response = $this->postJson('/api/admin/dynamic-fields', [
            'label' => 'Child Booster Seat Needed',
            'field_type' => 'checkbox',
            'applies_to' => 'cab',
            'conditional_logic' => 'Family Trips',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('dynamic_form_fields', [
            'label' => 'Child Booster Seat Needed',
            'field_type' => 'checkbox',
        ]);
    }

    public function test_car_rental_homepage_hero_and_booking_widget_renders(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Assert Hero section & main heading
        $response->assertSee('SERVICES ACROSS 2000+ CITIES');
        $response->assertSee("India's Trusted Car Rental & Outstation Travel Partner.", false);

        // Assert 4 Booking Type Tabs
        $response->assertSee('ONE WAY');
        $response->assertSee('ROUND TRIP');
        $response->assertSee('LOCAL');
        $response->assertSee('AIRPORT');

        // Assert Orange CTA Explore Cabs button
        $response->assertSee('EXPLORE CABS');

        // Assert Laurel wreath & Floating Trust Pill with Ratings
        $response->assertSee("India's Top Rated Car Rental Service", false);
        $response->assertSee('App Store');
        $response->assertSee('(4.2K+ Reviews)');
        $response->assertSee('Google');
        $response->assertSee('(6.1K+ Reviews)');
        $response->assertSee('Play Store');
        $response->assertSee('(15.5K+ Reviews)');

        // Assert 3 Promotional Cards
        $response->assertSee('Book Now at zero cost');
        $response->assertSee('EXCLUSIVE OUTSTATION OFFERS');
        $response->assertSee('Free Cancellations');
    }

    public function test_store_local_hourly_rental_package_booking(): void
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'local',
            'from_city' => 'Kolkata',
            'to_city' => 'Kolkata',
            'pickup_location' => 'Park Street, Kolkata',
            'drop_location' => 'Local City Tour',
            'customer_name' => 'Sourav Ganguly',
            'customer_phone' => '+91 98300 12345',
            'customer_email' => 'sourav@cricket.in',
            'fleet_category' => 'innova',
            'local_package' => '8_80',
            'coupon_code' => 'FIRSTWHEELS',
        ];

        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'service_type' => 'cab',
                'trip_type' => 'local',
                'customer_name' => 'Sourav Ganguly',
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Sourav Ganguly',
            'trip_type' => 'local',
        ]);
    }

    public function test_store_airport_transfer_booking(): void
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'airport',
            'from_city' => 'Kolkata',
            'to_city' => 'Kolkata Airport',
            'pickup_location' => 'Salt Lake Sector V, Kolkata',
            'drop_location' => 'Netaji Subhash Chandra Bose Intl (CCU)',
            'customer_name' => 'Jhulan Goswami',
            'customer_phone' => '+91 98311 54321',
            'customer_email' => 'jhulan@sports.in',
            'fleet_category' => 'sedan',
            'airport_type' => 'drop',
            'flight_number' => '6E-204',
        ];

        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'service_type' => 'cab',
                'trip_type' => 'airport',
                'customer_name' => 'Jhulan Goswami',
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Jhulan Goswami',
            'trip_type' => 'airport',
        ]);
    }

    public function test_booking_persists_departure_time_and_addons(): void
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Koregaon Park, Pune',
            'drop_location' => 'Candolim Beach, Goa',
            'departure_time' => '2026-12-01 07:30:00',
            'customer_name' => 'Rohit Sharma',
            'customer_phone' => '+91 98888 77777',
            'fleet_category' => 'suv',
            'include_tolls' => true,
            'pet_friendly' => true,
            'night_allowance' => true,
            'luggage_carrier' => true,
            'dynamic_fields' => [
                'booster_seat' => 'Yes, 1 needed',
                'luggage_count' => '4 suitcases',
            ],
        ];

        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'customer_name' => 'Rohit Sharma',
                'addon_fare' => 950, // 350 (pet) + 400 (night) + 200 (luggage)
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Rohit Sharma',
            'addon_fare' => 950,
        ]);

        $booking = Booking::where('customer_name', 'Rohit Sharma')->first();
        $this->assertNotNull($booking->departure_time);
        $this->assertEquals('Yes, 1 needed', $booking->dynamic_fields_data['booster_seat']);
    }

    public function test_booking_validation_fails_on_missing_required_fields(): void
    {
        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', [
            'service_type' => 'cab',
            // Missing customer_name, customer_phone, from_city, pickup_location
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_city', 'pickup_location', 'customer_name', 'customer_phone']);
    }

    public function test_public_locations_api_returns_active_locations(): void
    {
        $response = $this->getJson('/api/locations');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'locations']);
    }

    public function test_portal_renders_admin_updated_locations_in_dropdown(): void
    {
        ServiceLocation::create([
            'name' => 'Ahmedabad',
            'state' => 'Gujarat',
            'latitude' => 23.0225,
            'longitude' => 72.5714,
            'is_active' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Ahmedabad, Gujarat', false);
        $response->assertSee('Ahmedabad', false);
    }

    public function test_admin_updated_city_is_reflected_in_portal_and_api(): void
    {
        $loc = ServiceLocation::create([
            'name' => 'Chandigarh',
            'state' => 'Punjab',
            'latitude' => 30.7333,
            'longitude' => 76.7794,
            'is_active' => true,
        ]);

        // Admin updates the location
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $updateResponse = $this->putJson("/api/admin/locations/{$loc->id}", [
            'name' => 'Chandigarh City',
            'state' => 'Punjab & Haryana',
            'latitude' => 30.7333,
            'longitude' => 76.7794,
            'is_active' => true,
        ]);
        $updateResponse->assertStatus(200);
        $updateResponse->assertJson(['success' => true]);

        // Portal reflects the updated location
        $portalResponse = $this->get('/');
        $portalResponse->assertStatus(200);
        $portalResponse->assertSee('Chandigarh City', false);
        $portalResponse->assertSee('Punjab &amp; Haryana', false);

        // API reflects updated location
        $apiResponse = $this->getJson('/api/locations');
        $apiResponse->assertStatus(200);
        $apiResponse->assertJsonFragment(['name' => 'Chandigarh City']);
    }

    public function test_admin_deactivated_city_is_excluded_from_active_dropdown(): void
    {
        $loc = ServiceLocation::create([
            'name' => 'Bhopal',
            'state' => 'Madhya Pradesh',
            'latitude' => 23.2599,
            'longitude' => 77.4126,
            'is_active' => true,
        ]);

        // Toggle to inactive
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $toggleResponse = $this->postJson("/api/admin/locations/{$loc->id}/toggle");
        $toggleResponse->assertStatus(200);
        $this->assertFalse(ServiceLocation::find($loc->id)->is_active);

        // API should exclude inactive location
        $apiResponse = $this->getJson('/api/locations');
        $apiResponse->assertStatus(200);
        $apiResponse->assertJsonMissing(['name' => 'Bhopal']);
    }
}
