<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\GeoPlaceCache;
use App\Models\PlatformSetting;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Services\BookingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutOfRegionBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        config([
            'services.osm.enabled' => true,
            'services.osm.throttle' => false,
        ]);

        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_beyond_region_route_falls_back_from_cache_to_api_for_km_and_fare(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => function ($request) {
                $q = strtolower((string) ($request->data()['q'] ?? ''));
                if (str_contains($q, 'kochi')) {
                    return Http::response([[
                        'lat' => '9.9312',
                        'lon' => '76.2673',
                        'name' => 'Kochi',
                        'display_name' => 'Kochi, Kerala, India',
                        'address' => ['city' => 'Kochi', 'state' => 'Kerala'],
                    ]], 200);
                }

                if (str_contains($q, 'munnar')) {
                    return Http::response([[
                        'lat' => '10.0889',
                        'lon' => '77.0595',
                        'name' => 'Munnar',
                        'display_name' => 'Munnar, Kerala, India',
                        'address' => ['town' => 'Munnar', 'state' => 'Kerala'],
                    ]], 200);
                }

                return Http::response([], 200);
            },
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 128400.0, 'duration' => 13200.0]],
            ], 200),
        ]);

        $response = $this->getJson('/api/routes/calculate?'.http_build_query([
            'from' => 'Kochi',
            'to' => 'Munnar',
            'trip_type' => 'oneway',
            'fleet' => 'sedan',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('route.distance_km', 128)
            ->assertJsonPath('route.estimated_time', '3h 40m')
            ->assertJsonPath('route.distance_source', 'osm_geocode+osrm_route')
            ->assertJsonPath('route.beyond_region', true)
            ->assertJsonPath('route.availability_guaranteed', false);

        $this->assertGreaterThan(0, $response->json('quote.total_amount'));
        $this->assertNotEmpty($response->json('route.region_note'));
    }

    public function test_transient_osm_outage_does_not_poison_search_cache(): void
    {
        $attempt = 0;

        Http::fake([
            'nominatim.openstreetmap.org/*' => function () use (&$attempt) {
                $attempt += 1;
                if ($attempt === 1) {
                    return Http::response('rate limited', 429);
                }

                return Http::response([[
                    'lat' => '9.9312',
                    'lon' => '76.2673',
                    'name' => 'Kochi',
                    'display_name' => 'Kochi, Kerala, India',
                    'address' => ['city' => 'Kochi', 'state' => 'Kerala'],
                ]], 200);
            },
        ]);

        $first = $this->getJson('/api/locations/search?q=Kochi')->assertOk();
        $this->assertSame([], collect($first->json('locations'))->where('badge', 'OpenStreetMap')->values()->all());

        // Second call recovers via live API instead of returning cached empty result
        $second = $this->getJson('/api/locations/search?q=Kochi')->assertOk();
        $osm = collect($second->json('locations'))->firstWhere('badge', 'OpenStreetMap');
        $this->assertNotNull($osm);
        $this->assertSame('Kochi', $osm['name']);
    }

    public function test_beyond_region_booking_is_accepted_but_not_confirmed_or_guaranteed(): void
    {
        $customer = User::where('role', 'customer')->first() ?? User::factory()->create(['role' => 'customer']);
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
        $driver = Driver::first();

        // Enable Razorpay settings to ensure out-of-region bookings still do NOT pre-confirm/charge
        PlatformSetting::set('payment_gateway', 'razorpay');
        PlatformSetting::set('razorpay_key_id', 'rzp_test_outregion123');
        PlatformSetting::set('razorpay_key_secret', 'secret_outregion123');

        Http::fake([
            'nominatim.openstreetmap.org/*' => function ($request) {
                $q = strtolower((string) ($request->data()['q'] ?? ''));
                if (str_contains($q, 'kochi')) {
                    return Http::response([[
                        'lat' => '9.9312',
                        'lon' => '76.2673',
                        'name' => 'Kochi',
                        'display_name' => 'Kochi, Kerala, India',
                        'address' => ['city' => 'Kochi', 'state' => 'Kerala'],
                    ]], 200);
                }

                return Http::response([[
                    'lat' => '10.0889',
                    'lon' => '77.0595',
                    'name' => 'Munnar',
                    'display_name' => 'Munnar, Kerala, India',
                    'address' => ['town' => 'Munnar', 'state' => 'Kerala'],
                ]], 200);
            },
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 130000.0, 'duration' => 13800.0]],
            ], 200),
        ]);

        $res = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kochi',
            'to_city' => 'Munnar',
            'pickup_location' => 'Ernakulam South Railway Station',
            'drop_location' => 'Munnar Tea Gardens',
            'customer_name' => 'Ananya Menon',
            'customer_phone' => '+91 98470 11223',
            'customer_email' => 'ananya@example.com',
            'fleet_category' => 'sedan',
            'payment_mode' => 'full_online',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_beyond_region', true)
            ->assertJsonPath('availability_guaranteed', false)
            ->assertJsonPath('requires_razorpay', false)
            ->assertJsonPath('booking.status', BookingWorkflow::REQUESTED)
            ->assertJsonPath('booking.is_beyond_region', true)
            ->assertJsonPath('booking.availability_status', 'unconfirmed')
            ->assertJsonPath('booking.payment_status', 'pending_availability');

        $bookingId = $res->json('booking.id');
        $booking = Booking::findOrFail($bookingId);
        $this->assertTrue($booking->is_beyond_region);
        $this->assertSame('unconfirmed', $booking->availability_status);
        $this->assertGreaterThan(0, (float) $booking->total_amount);

        // Customer notification must NOT say "Booking Confirmed"
        $customerNotif = $customer->notifications()->latest()->first();
        $this->assertNotNull($customerNotif);
        $this->assertStringNotContainsString('Booking Confirmed', (string) ($customerNotif->data['title'] ?? ''));
        $this->assertStringContainsString('Beyond Region', (string) ($customerNotif->data['title'] ?? ''));

        // Customer My Trips view marks it Unconfirmed / Not Guaranteed
        $page = $this->actingAs($customer)->get('/');
        $page->assertOk();
        $page->assertSee('Out of Region · Unconfirmed', false);
        $page->assertSee('Availability Not Guaranteed', false);
        $page->assertSee('id="step2-beyond-region-banner"', false);
        $page->assertSee('id="step3-beyond-region-banner"', false);

        // Admin allotment confirms availability once a chauffeur is assigned
        if ($driver) {
            $allotRes = $this->actingAs($admin)->postJson("/api/admin/bookings/{$bookingId}/allot-driver", [
                'driver_id' => $driver->id,
            ]);
            $allotRes->assertOk();
            $this->assertDatabaseHas('bookings', [
                'id' => $bookingId,
                'status' => BookingWorkflow::DRIVER_ASSIGNED,
                'availability_status' => 'confirmed',
            ]);
        }
    }

    public function test_unlisted_city_search_caches_places_and_beyond_region_booking_notifies_admin_for_approval(): void
    {
        $customer = User::where('role', 'customer')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();

        PlatformSetting::updateOrCreate(['key' => 'allow_out_of_region_bookings'], ['value' => '1']);

        $nominatimCalls = 0;

        Http::fake([
            'nominatim.openstreetmap.org/*' => function ($request) use (&$nominatimCalls) {
                $nominatimCalls++;
                $q = strtolower((string) ($request->data()['q'] ?? ''));

                if (str_contains($q, 'munnar')) {
                    return Http::response([[
                        'lat' => '10.0889',
                        'lon' => '77.0595',
                        'name' => 'Munnar',
                        'display_name' => 'Munnar, Idukki, Kerala, India',
                        'address' => ['town' => 'Munnar', 'state' => 'Kerala'],
                    ]], 200);
                }

                if (str_contains($q, 'kochi')) {
                    return Http::response([[
                        'lat' => '9.9312',
                        'lon' => '76.2673',
                        'name' => 'Kochi',
                        'display_name' => 'Kochi, Ernakulam, Kerala, India',
                        'address' => ['city' => 'Kochi', 'state' => 'Kerala'],
                    ]], 200);
                }

                return Http::response([], 200);
            },
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 130000.0, 'duration' => 13800.0]],
            ], 200),
        ]);

        // 1. First search hits Nominatim once and persists Munnar into geo_place_cache
        $firstSearch = $this->getJson('/api/locations/search?q=Munnar');
        $firstSearch->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('locations.0.name', 'Munnar')
            ->assertJsonPath('locations.0.badge', 'OpenStreetMap');
        $this->assertSame(1, $nominatimCalls);
        $this->assertDatabaseHas('geo_place_cache', [
            'name' => 'Munnar',
            'state' => 'Kerala',
            'is_beyond_region' => true,
            'approval_status' => 'cached',
        ]);

        // 2. Even after volatile Laravel cache flush, searching Munnar again hits durable DB cache with 0 extra Nominatim calls
        Cache::flush();
        $secondSearch = $this->getJson('/api/locations/search?q=Munnar');
        $secondSearch->assertOk()
            ->assertJsonPath('locations.0.name', 'Munnar')
            ->assertJsonPath('locations.0.cached', true);
        $this->assertSame(1, $nominatimCalls);

        // 3. Calculating route & creating booking reuses cached Munnar and notifies admin for location approval
        $bookingRes = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kochi',
            'to_city' => 'Munnar',
            'pickup_location' => 'Kochi Harbour',
            'drop_location' => 'Munnar Town Center',
            'customer_name' => 'Ananya Menon',
            'customer_phone' => '+91 98470 11223',
            'customer_email' => 'ananya@example.com',
            'fleet_category' => 'sedan',
            'payment_mode' => 'full_online',
        ]);

        $bookingRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_beyond_region', true);
        // Only Kochi had not been searched yet (1 extra call)
        $this->assertSame(2, $nominatimCalls);

        $bookingRef = $bookingRes->json('booking.booking_reference');

        $munnarCache = GeoPlaceCache::where('name', 'Munnar')->firstOrFail();
        $this->assertSame('pending', $munnarCache->approval_status);
        $this->assertTrue($munnarCache->is_beyond_region);
        $this->assertSame($bookingRef, $munnarCache->last_booking_reference);

        $adminNotif = $admin->notifications()->latest()->first();
        $this->assertNotNull($adminNotif);
        $this->assertSame('locations', $adminNotif->data['meta']['section'] ?? null);
        $this->assertStringContainsString('Munnar', (string) ($adminNotif->data['message'] ?? ''));
        $this->assertStringContainsString('#admin-discovered-cities-card', (string) ($adminNotif->data['action_url'] ?? ''));

        // 4. Customer cannot access admin discovered-locations endpoints
        $this->actingAs($customer)
            ->getJson('/api/admin/discovered-locations')
            ->assertStatus(403);

        // 5. Admin sees Munnar in discovered queue and UI, then approves it into ServiceLocations
        $this->actingAs($admin)
            ->getJson('/api/admin/discovered-locations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $adminPage = $this->actingAs($admin)->get('/?tab=admin&section=locations');
        $adminPage->assertOk();
        $adminPage->assertSee('id="admin-discovered-cities-card"', false);
        $adminPage->assertSee('Munnar', false);
        $adminPage->assertSee('Beyond Region', false);

        $approveRes = $this->actingAs($admin)->postJson("/api/admin/discovered-locations/{$munnarCache->id}/approve", [
            'is_active' => true,
        ]);
        $approveRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('location.name', 'Munnar')
            ->assertJsonPath('discovered.approval_status', 'approved');

        $this->assertDatabaseHas('service_locations', [
            'name' => 'Munnar',
            'state' => 'Kerala',
            'is_active' => true,
        ]);

        // 6. Now Munnar appears as a Registered Hub in city search
        Cache::flush();
        $afterApprovalSearch = $this->getJson('/api/locations/search?q=Munnar');
        $afterApprovalSearch->assertOk()
            ->assertJsonPath('locations.0.name', 'Munnar')
            ->assertJsonPath('locations.0.is_registered', true)
            ->assertJsonPath('locations.0.badge', 'Registered Hub');

        // 7. Admin can dismiss a pending cached city (e.g., Kochi)
        $kochiCache = GeoPlaceCache::where('name', 'Kochi')->firstOrFail();
        $this->actingAs($admin)
            ->postJson("/api/admin/discovered-locations/{$kochiCache->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('discovered.approval_status', 'dismissed');

        $this->assertNull(ServiceLocation::where('name', 'Kochi')->first());
    }
}
