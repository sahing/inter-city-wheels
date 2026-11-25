<?php

namespace Tests\Feature;

use App\Services\OsmGeoService;
use App\Services\RegionalLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OpenStreetMap integration: Nominatim place search and OSRM driving distance.
 *
 * The suite disables OSM by default (phpunit.xml) so the other tests never touch
 * the public instances; each test here opts back in and stubs HTTP.
 */
class OsmGeoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.osm.enabled' => true,
            'services.osm.throttle' => false,
            'services.osm.country_codes' => 'in',
        ]);

        Cache::flush();
        Http::preventStrayRequests();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arambaghNominatim(): array
    {
        return [[
            'lat' => '22.8744',
            'lon' => '87.8561',
            'name' => 'Arambagh',
            'display_name' => 'Arambagh, Hooghly, West Bengal, India',
            'osm_type' => 'node',
            'osm_id' => 123456,
            'type' => 'town',
            'address' => ['town' => 'Arambagh', 'state' => 'West Bengal'],
        ]];
    }

    private function fakeNominatim(array $rows = []): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($rows, 200),
        ]);
    }

    private function fakeOsrm(float $meters, float $seconds): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => $meters, 'duration' => $seconds]],
            ], 200),
        ]);
    }

    public function test_location_search_returns_openstreetmap_place_with_coordinates(): void
    {
        $this->fakeNominatim($this->arambaghNominatim());

        $response = $this->getJson('/api/locations/search?q=Arambagh');

        $response->assertOk()->assertJsonPath('success', true);

        $osm = collect($response->json('locations'))
            ->first(fn (array $place) => ($place['badge'] ?? null) === 'OpenStreetMap');

        $this->assertNotNull($osm, 'Expected a Nominatim suggestion in the results.');
        $this->assertSame('Arambagh', $osm['name']);
        $this->assertEqualsWithDelta(22.8744, $osm['latitude'], 0.0001);
        $this->assertEqualsWithDelta(87.8561, $osm['longitude'], 0.0001);
        $this->assertSame('geocoded', $osm['type']);
    }

    public function test_unresolvable_typed_location_is_returned_without_fabricated_coordinates(): void
    {
        $this->fakeNominatim([]);

        $response = $this->getJson('/api/locations/search?q=Nowhereville');

        $response->assertOk();

        $custom = collect($response->json('locations'))->firstWhere('is_custom', true);

        $this->assertNotNull($custom);
        $this->assertNull($custom['latitude'], 'An unknown place must not be pinned to a default city.');
        $this->assertNull($custom['longitude']);
        $this->assertTrue($custom['needs_geocode']);
    }

    public function test_search_survives_nominatim_outage(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response('rate limited', 429),
        ]);

        $response = $this->getJson('/api/locations/search?q=Arambagh');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(
            [],
            collect($response->json('locations'))
                ->where('badge', 'OpenStreetMap')
                ->values()
                ->all()
        );
    }

    public function test_route_quote_uses_osrm_driving_distance(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 123456.0, 'duration' => 9000.0]],
            ], 200),
        ]);

        $response = $this->getJson('/api/routes/calculate?'.http_build_query([
            'from' => 'Arambagh',
            'to' => 'Digha',
            'from_lat' => 22.8744,
            'from_lng' => 87.8561,
            'to_lat' => 21.6266,
            'to_lng' => 87.5074,
            'trip_type' => 'oneway',
            'fleet' => 'sedan',
        ]));

        $response->assertOk()
            ->assertJsonPath('route.distance_km', 123)
            ->assertJsonPath('route.estimated_time', '2h 30m')
            ->assertJsonPath('route.distance_source', 'coordinates+osrm_route');

        $this->assertSame(123, $response->json('itemized_breakdown.sedan.distance_km'));
    }

    public function test_route_quote_falls_back_to_straight_line_when_osrm_is_down(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
            'router.project-osrm.org/*' => Http::response('upstream unavailable', 503),
        ]);

        $response = $this->getJson('/api/routes/calculate?'.http_build_query([
            'from' => 'Arambagh',
            'to' => 'Digha',
            'from_lat' => 22.8744,
            'from_lng' => 87.8561,
            'to_lat' => 21.6266,
            'to_lng' => 87.5074,
        ]));

        $expected = RegionalLocationService::computeRouteMetrics(22.8744, 87.8561, 21.6266, 87.5074);

        $response->assertOk()
            ->assertJsonPath('route.distance_km', (int) $expected['distance_km'])
            ->assertJsonPath('route.distance_source', 'coordinates');
    }

    public function test_unknown_place_name_is_geocoded_then_routed(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($this->arambaghNominatim(), 200),
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 150000.0, 'duration' => 7200.0]],
            ], 200),
        ]);

        // "Digha" is in the curated dataset; "Arambagh" is not, so it must be
        // geocoded through OSM before OSRM can price the route.
        $response = $this->getJson('/api/routes/calculate?from=Arambagh&to=Digha');

        $response->assertOk()
            ->assertJsonPath('route.distance_km', 150)
            ->assertJsonPath('route.estimated_time', '2h')
            ->assertJsonPath('route.distance_source', 'osm_geocode+osrm_route');
    }

    public function test_quote_is_rejected_when_no_source_can_ground_the_place(): void
    {
        $this->fakeNominatim([]);

        $response = $this->getJson('/api/routes/calculate?from=Nowhereville&to=Digha');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'distance_unresolvable');
    }

    public function test_repeated_quotes_reuse_the_cached_osrm_route(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 123456.0, 'duration' => 9000.0]],
            ], 200),
        ]);

        $query = http_build_query([
            'from' => 'Arambagh',
            'to' => 'Digha',
            'from_lat' => 22.8744,
            'from_lng' => 87.8561,
            'to_lat' => 21.6266,
            'to_lng' => 87.5074,
        ]);

        $this->getJson('/api/routes/calculate?'.$query)->assertOk();
        $this->getJson('/api/routes/calculate?'.$query)->assertOk();

        Http::assertSentCount(1);
    }

    public function test_reverse_geocoding_names_a_coordinate_pair(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'lat' => '22.5726',
                'lon' => '88.3639',
                'name' => 'Kolkata',
                'display_name' => 'Kolkata, West Bengal, India',
                'address' => ['city' => 'Kolkata', 'state' => 'West Bengal'],
            ], 200),
        ]);

        $place = app(OsmGeoService::class)->reverse(22.5726, 88.3639);

        $this->assertNotNull($place);
        $this->assertSame('Kolkata', $place['name']);
        $this->assertEqualsWithDelta(22.5726, $place['lat'], 0.0001);
    }

    public function test_service_reports_disabled_when_switched_off(): void
    {
        config(['services.osm.enabled' => false]);

        $service = app(OsmGeoService::class);

        $this->assertFalse($service->enabled());
        $this->assertNull($service->geocode('Arambagh'));
        $this->assertNull($service->route(22.8744, 87.8561, 21.6266, 87.5074));
    }
}
