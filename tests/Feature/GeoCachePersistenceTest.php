<?php

namespace Tests\Feature;

use App\Models\GeoPlaceCache;
use App\Models\GeoRouteCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The durable OpenStreetMap cache.
 *
 * The point of these tests is that the second identical customer query costs no
 * map API call at all — even after the HTTP cache is flushed — because the first
 * one wrote the answer to the database before returning the quote.
 */
class GeoCachePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const FROM_LAT = 22.8744;

    private const FROM_LNG = 87.8561;

    private const TO_LAT = 21.6266;

    private const TO_LNG = 87.5074;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.osm.enabled' => true,
            'services.osm.db_cache' => true,
            'services.osm.throttle' => false,
            'services.osm.route_ttl_days' => 30,
            'services.osm.place_ttl_days' => 90,
        ]);

        Cache::flush();
        Http::preventStrayRequests();
    }

    private function fakeOsrm(float $meters = 123456.0, float $seconds = 9000.0): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => $meters, 'duration' => $seconds]],
            ], 200),
        ]);
    }

    private function fakeNominatimAndOsrm(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => '22.8765',
                'lon' => '87.7908',
                'name' => 'Arambagh',
                'display_name' => 'Arambagh, Hooghly, West Bengal, India',
                'address' => ['town' => 'Arambagh', 'state' => 'West Bengal'],
            ]], 200),
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 150000.0, 'duration' => 7200.0]],
            ], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function quoteQuery(array $overrides = []): string
    {
        return http_build_query(array_merge([
            'from' => 'Arambagh',
            'to' => 'Digha',
            'from_lat' => self::FROM_LAT,
            'from_lng' => self::FROM_LNG,
            'to_lat' => self::TO_LAT,
            'to_lng' => self::TO_LNG,
            'trip_type' => 'oneway',
            'fleet' => 'sedan',
        ], $overrides));
    }

    private function nominatimCalls(): int
    {
        return Http::recorded()
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'nominatim'))
            ->count();
    }

    public function test_first_quote_persists_the_route_and_the_next_reads_the_database(): void
    {
        $this->fakeOsrm();

        $query = $this->quoteQuery();

        $first = $this->getJson('/api/routes/calculate?'.$query);
        $first->assertOk()
            ->assertJsonPath('route.distance_km', 123)
            ->assertJsonPath('route.cached', false);

        $this->assertDatabaseHas('geo_route_cache', [
            'distance_km' => 123,
            'duration_min' => 150,
            'engine' => 'osrm',
        ]);

        // Drop the HTTP cache: only the database can answer this now.
        Cache::flush();

        $second = $this->getJson('/api/routes/calculate?'.$query);
        $second->assertOk()
            ->assertJsonPath('route.distance_km', 123)
            ->assertJsonPath('route.estimated_time', '2h 30m')
            ->assertJsonPath('route.cached', true);

        Http::assertSentCount(1);
        $this->assertSame(1, (int) GeoRouteCache::query()->value('hits'));
    }

    public function test_the_reverse_trip_reuses_the_same_cached_route(): void
    {
        $this->fakeOsrm();

        $this->getJson('/api/routes/calculate?'.$this->quoteQuery())->assertOk();

        Cache::flush();

        $back = $this->quoteQuery([
            'from_lat' => self::TO_LAT,
            'from_lng' => self::TO_LNG,
            'to_lat' => self::FROM_LAT,
            'to_lng' => self::FROM_LNG,
            'from' => 'Digha',
            'to' => 'Arambagh',
        ]);

        $this->getJson('/api/routes/calculate?'.$back)
            ->assertOk()
            ->assertJsonPath('route.distance_km', 123)
            ->assertJsonPath('route.cached', true);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('geo_route_cache', 1);
    }

    public function test_expired_route_is_measured_again_and_refreshed(): void
    {
        $this->fakeOsrm();

        $query = $this->quoteQuery();
        $this->getJson('/api/routes/calculate?'.$query)->assertOk();

        GeoRouteCache::query()->update([
            'expires_at' => now()->subMinute(),
            'hits' => 0,
        ]);

        Cache::flush();

        $this->getJson('/api/routes/calculate?'.$query)
            ->assertOk()
            ->assertJsonPath('route.distance_km', 123)
            ->assertJsonPath('route.cached', false);

        Http::assertSentCount(2);

        $row = GeoRouteCache::query()->firstOrFail();
        $this->assertTrue($row->expires_at->isFuture());
    }

    public function test_geocoded_place_is_persisted_and_reused_without_nominatim(): void
    {
        $this->fakeNominatimAndOsrm();

        $this->getJson('/api/routes/calculate?from=Arambagh&to=Digha')
            ->assertOk()
            ->assertJsonPath('route.distance_km', 150);

        $this->assertDatabaseHas('geo_place_cache', [
            'name' => 'Arambagh',
            'state' => 'West Bengal',
        ]);

        Cache::flush();

        $this->getJson('/api/routes/calculate?from=Arambagh&to=Digha')
            ->assertOk()
            ->assertJsonPath('route.distance_km', 150)
            ->assertJsonPath('route.cached', true);

        $this->assertSame(1, $this->nominatimCalls(), 'The second quote must not call Nominatim again.');
        $this->assertSame(1, (int) GeoPlaceCache::query()->value('hits'));
    }

    public function test_stored_route_still_answers_when_the_map_api_is_switched_off(): void
    {
        $this->fakeOsrm();

        $query = $this->quoteQuery();
        $this->getJson('/api/routes/calculate?'.$query)->assertOk();

        Cache::flush();

        // No OSM at all: no HTTP client, no timeout, just the stored numbers.
        config(['services.osm.enabled' => false]);

        $this->getJson('/api/routes/calculate?'.$query)
            ->assertOk()
            ->assertJsonPath('route.distance_km', 123)
            ->assertJsonPath('route.cached', true);

        Http::assertSentCount(1);
    }

    public function test_pricing_still_works_when_the_cache_tables_are_missing(): void
    {
        Schema::drop('geo_route_cache');
        Schema::drop('geo_place_cache');

        $this->fakeOsrm();

        $this->getJson('/api/routes/calculate?'.$this->quoteQuery())
            ->assertOk()
            ->assertJsonPath('route.distance_km', 123)
            ->assertJsonPath('route.cached', false);
    }

    public function test_prune_command_removes_only_expired_rows(): void
    {
        GeoRouteCache::create([
            'route_key' => 'expired',
            'distance_km' => 10,
            'duration_min' => 10,
            'engine' => 'osrm',
            'resolved_at' => now()->subDays(40),
            'expires_at' => now()->subDay(),
        ]);

        GeoRouteCache::create([
            'route_key' => 'fresh',
            'distance_km' => 20,
            'duration_min' => 20,
            'engine' => 'osrm',
            'resolved_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('geo:prune-cache')
            ->expectsOutputToContain('Pruned 1 expired route(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('geo_route_cache', 1);
        $this->assertDatabaseMissing('geo_route_cache', ['route_key' => 'expired']);
    }

    public function test_stats_command_reports_both_stores(): void
    {
        $this->artisan('geo:cache-stats')
            ->expectsOutputToContain('routes')
            ->expectsOutputToContain('places')
            ->assertSuccessful();
    }
}
