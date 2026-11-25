<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_gps_mode_reverse_geocodes_coordinates_via_nominatim(): void
    {
        config([
            'services.osm.enabled' => true,
            'services.osm.throttle' => false,
        ]);

        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'lat' => '22.8765856',
                'lon' => '87.7908631',
                'name' => 'Arambagh',
                'display_name' => 'Arambagh, Hooghly, West Bengal, India',
                'address' => [
                    'town' => 'Arambagh',
                    'state' => 'West Bengal',
                ],
            ], 200),
        ]);

        $this->getJson('/api/locations/detect?mode=gps&lat=22.8766&lng=87.7909')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'gps')
            ->assertJsonPath('location.name', 'Arambagh')
            ->assertJsonPath('location.state', 'West Bengal')
            ->assertJsonPath('location.accuracy', 'precise');
    }

    public function test_gps_mode_falls_back_to_nearest_catalog_city_when_osm_is_off(): void
    {
        config(['services.osm.enabled' => false]);

        $this->getJson('/api/locations/detect?mode=gps&lat=22.5730&lng=88.3640')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'gps')
            ->assertJsonPath('location.name', 'Kolkata')
            ->assertJsonPath('location.state', 'West Bengal');
    }

    public function test_gps_mode_rejects_missing_or_invalid_coordinates(): void
    {
        $this->getJson('/api/locations/detect?mode=gps')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'coordinates_required');

        $this->getJson('/api/locations/detect?mode=gps&lat=200&lng=88.36')
            ->assertStatus(422);
    }

    public function test_network_mode_detects_city_from_public_client_ip(): void
    {
        config(['services.geoip.enabled' => true]);

        Http::fake([
            'ipwho.is/103.21.244.0*' => Http::response([
                'success' => true,
                'city' => 'Kolkata',
                'region' => 'West Bengal',
                'latitude' => 22.5726,
                'longitude' => 88.3639,
            ], 200),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '103.21.244.0'])
            ->getJson('/api/locations/detect?mode=network')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'network')
            ->assertJsonPath('location.name', 'Kolkata')
            ->assertJsonPath('location.state', 'West Bengal')
            ->assertJsonPath('location.accuracy', 'approximate');
    }

    public function test_network_mode_queries_outbound_ip_for_loopback_and_degrades_when_disabled(): void
    {
        config(['services.geoip.enabled' => true]);

        Http::fake([
            'ipwho.is/' => Http::response([
                'success' => true,
                'city' => 'Daspur',
                'region' => 'West Bengal',
                'latitude' => 22.6076,
                'longitude' => 87.7228,
            ], 200),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/api/locations/detect?mode=network')
            ->assertOk()
            ->assertJsonPath('location.name', 'Daspur');

        config(['services.geoip.enabled' => false]);

        $this->getJson('/api/locations/detect?mode=network')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'location_undetected');
    }

    public function test_booking_page_exposes_gps_and_network_pickup_options(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="btn-detect-pickup-gps"', false)
            ->assertSee('id="btn-detect-pickup-network"', false)
            ->assertSee("detectActiveBookingLocation('gps')", false)
            ->assertSee("detectActiveBookingLocation('network')", false);
    }
}
