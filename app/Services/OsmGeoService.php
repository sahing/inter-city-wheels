<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenStreetMap integrations.
 *
 * Two separate public services are involved:
 *
 *  - Nominatim (nominatim.openstreetmap.org) turns a typed place name into
 *    coordinates and coordinates back into a place name.
 *  - OSRM (router.project-osrm.org) turns two coordinates into a real driving
 *    distance and duration, instead of the straight-line estimate multiplied by
 *    a guessed road factor.
 *
 * Both are free public instances with usage policies, so every call is cached
 * and Nominatim requests are throttled to at most one per second. Deployments
 * that need real volume should point the URLs at a self-hosted instance.
 */
class OsmGeoService
{
    private const CACHE_PREFIX = 'osm:';

    /**
     * Nominatim requests are limited to one per second by its usage policy.
     * A cache-backed timestamp keeps a burst of keystrokes from tripping it.
     */
    private const MIN_REQUEST_INTERVAL = 1.0;

    /** Durable store for places and routes, checked before the network. */
    private readonly GeoCacheService $geoCache;

    public function __construct(?GeoCacheService $geoCache = null)
    {
        $this->geoCache = $geoCache ?? new GeoCacheService;
    }

    public function enabled(): bool
    {
        return (bool) config('services.osm.enabled', true)
            && $this->config('nominatim_url') !== '';
    }

    public function routingEnabled(): bool
    {
        return (bool) config('services.osm.enabled', true)
            && $this->config('osrm_url') !== '';
    }

    /**
     * Search places by free text.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if (! $this->enabled() || mb_strlen($query) < 3) {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $cacheKey = self::CACHE_PREFIX.'search:'.md5(mb_strtolower($query).'|'.$limit.'|'.$this->countryCodes());

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        } catch (\Throwable $e) {
            Log::warning('OpenStreetMap cache unavailable: '.$e->getMessage());
        }

        $fresh = $this->fetchSearch($query, $limit);

        if ($fresh !== []) {
            try {
                Cache::put($cacheKey, $fresh, $this->ttl('cache_ttl', 604800));
            } catch (\Throwable $e) {
                Log::warning('OpenStreetMap cache write failed: '.$e->getMessage());
            }
        }

        return $fresh;
    }

    /**
     * First usable match for a free-text place name, used when the registered
     * location table and the curated catalogue cannot ground a name.
     *
     * The database entry is written before the result is returned, so the next
     * quote for the same place never reaches Nominatim.
     *
     * @return array{lat: float, lng: float, name: string, state: ?string, display_name: ?string}|null
     */
    public function geocode(string $query): ?array
    {
        $stored = $this->geoCache->place($query);

        if ($stored !== null) {
            return $stored;
        }

        foreach ($this->search($query, 1) as $result) {
            $place = [
                'lat' => (float) $result['latitude'],
                'lng' => (float) $result['longitude'],
                'name' => (string) $result['name'],
                'state' => $result['state'] ?? null,
                'display_name' => $result['display_name'] ?? null,
            ];

            $this->geoCache->rememberPlace($query, $place);

            return $place;
        }

        return null;
    }

    /**
     * Place name for a coordinate pair.
     *
     * @return array{lat: float, lng: float, name: string, state: ?string, display_name: ?string}|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        if (! $this->enabled() || ! $this->validCoordinates($lat, $lng)) {
            return null;
        }

        $lat = round($lat, 6);
        $lng = round($lng, 6);

        $payload = $this->request($this->config('nominatim_url'), '/reverse', [
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lng,
            'zoom' => 10,
            'addressdetails' => 1,
            'accept-language' => 'en',
        ]);

        if ($payload === null || isset($payload['error'])) {
            return null;
        }

        $place = $this->normalize($payload);

        if ($place === null) {
            return null;
        }

        return [
            'lat' => (float) $place['latitude'],
            'lng' => (float) $place['longitude'],
            'name' => (string) $place['name'],
            'state' => $place['state'] ?? null,
            'display_name' => $place['display_name'] ?? null,
        ];
    }

    /**
     * Real driving distance between two points, from OSRM.
     *
     * Order: HTTP cache → database cache (geo_route_cache) → OSRM. The measured
     * route is persisted before it is returned, so a cache flush or a new worker
     * does not send the next customer query back to the map API.
     *
     * Returns null when routing is unavailable, so callers fall back to the
     * straight-line estimate rather than inventing a number.
     *
     * @return array{distance_km: int, duration_min: int, engine: string, profile: string, cached?: bool}|null
     */
    public function route(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        ?string $fromName = null,
        ?string $toName = null,
    ): ?array {
        // Note: routingEnabled() is deliberately *not* checked here. A route
        // already stored in the database stays usable when the map API is
        // switched off (OSM_ENABLED=false); only the network call is skipped.
        if (! $this->validCoordinates($fromLat, $fromLng)
            || ! $this->validCoordinates($toLat, $toLng)) {
            return null;
        }

        // ~1 m precision: enough for a road route, and it makes cache hits
        // reliable for the same city pair typed with different decimals.
        $fromLat = round($fromLat, 5);
        $fromLng = round($fromLng, 5);
        $toLat = round($toLat, 5);
        $toLng = round($toLng, 5);

        $resolve = function () use ($fromLat, $fromLng, $toLat, $toLng, $fromName, $toName): ?array {
            $stored = $this->geoCache->route($fromLat, $fromLng, $toLat, $toLng);

            if ($stored !== null) {
                return $stored;
            }

            $measured = $this->fetchRoute($fromLat, $fromLng, $toLat, $toLng);

            if ($measured !== null) {
                $this->geoCache->rememberRoute(
                    $fromLat,
                    $fromLng,
                    $toLat,
                    $toLng,
                    $measured,
                    'osrm',
                    $fromName,
                    $toName
                );
            }

            return $measured;
        };

        $cacheKey = self::CACHE_PREFIX.'route:'.md5($fromLat.','.$fromLng.'|'.$toLat.','.$toLng);

        try {
            return Cache::remember(
                $cacheKey,
                $this->ttl('route_cache_ttl', 43200),
                $resolve
            );
        } catch (\Throwable $e) {
            // A broken cache store must not break pricing.
            Log::warning('OpenStreetMap cache unavailable: '.$e->getMessage());

            return $resolve();
        }
    }

    /**
     * @return array{distance_km: int, duration_min: int, engine: string, profile: string}|null
     */
    private function fetchRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        if (! $this->routingEnabled()) {
            return null;
        }

        // OSRM takes coordinates in longitude,latitude order.
        $path = sprintf('/route/v1/driving/%F,%F;%F,%F', $fromLng, $fromLat, $toLng, $toLat);

        $payload = $this->request($this->config('osrm_url'), $path, [
            'overview' => 'false',
            'alternatives' => 'false',
            'steps' => 'false',
        ]);

        if ($payload === null || ($payload['code'] ?? null) !== 'Ok') {
            return null;
        }

        $route = $payload['routes'][0] ?? null;

        if (! is_array($route) || ! isset($route['distance'], $route['duration'])) {
            return null;
        }

        $meters = (float) $route['distance'];
        $seconds = (float) $route['duration'];

        if ($meters <= 0) {
            return null;
        }

        return [
            'distance_km' => max(1, (int) round($meters / 1000)),
            'duration_min' => max(1, (int) round($seconds / 60)),
            'engine' => 'osrm',
            'profile' => 'driving',
        ];
    }

    /**
     * Duration string in the format the booking screens already use.
     */
    public function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }

        return "{$mins}m";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSearch(string $query, int $limit): array
    {
        $payload = $this->request($this->config('nominatim_url'), '/search', [
            'format' => 'jsonv2',
            'q' => $query,
            'limit' => $limit,
            'addressdetails' => 1,
            'countrycodes' => $this->countryCodes(),
            'accept-language' => 'en',
        ]);

        if ($payload === null) {
            return [];
        }

        $results = [];

        foreach ($payload as $row) {
            $place = is_array($row) ? $this->normalize($row) : null;

            if ($place !== null) {
                $results[] = $place;
            }
        }

        return $results;
    }

    /**
     * One HTTP GET against an OSM service, with timeout, identifying header and
     * throttle. Returns null on every failure path so callers can degrade.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    private function request(string $baseUrl, string $path, array $query): ?array
    {
        try {
            $this->throttle();

            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => $this->userAgent(),
                    'Referer' => (string) config('app.url'),
                ])
                ->get(rtrim($baseUrl, '/').$path, $query);
        } catch (\Throwable $e) {
            Log::warning('OpenStreetMap request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('OpenStreetMap request returned HTTP '.$response->status().' for '.$path);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Normalise a Nominatim row into the shape the location picker consumes.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalize(array $row): ?array
    {
        if (! isset($row['lat'], $row['lon']) || ! is_numeric($row['lat']) || ! is_numeric($row['lon'])) {
            return null;
        }

        $address = is_array($row['address'] ?? null) ? $row['address'] : [];

        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            foreach (['city', 'town', 'village', 'hamlet', 'suburb', 'county', 'state'] as $field) {
                if (! empty($address[$field]) && is_string($address[$field])) {
                    $name = trim($address[$field]);
                    break;
                }
            }
        }

        if ($name === '') {
            $name = trim(explode(',', (string) ($row['display_name'] ?? ''))[0]);
        }

        if ($name === '') {
            return null;
        }

        $state = $address['state'] ?? $address['state_district'] ?? $address['county'] ?? null;
        $state = is_string($state) && trim($state) !== '' ? trim($state) : null;

        return [
            'name' => $name,
            'state' => $state,
            'latitude' => (float) $row['lat'],
            'longitude' => (float) $row['lon'],
            'display_name' => isset($row['display_name']) ? (string) $row['display_name'] : null,
            'osm_type' => $row['osm_type'] ?? null,
            'osm_id' => $row['osm_id'] ?? null,
            'place_type' => $row['type'] ?? null,
            'type' => 'geocoded',
            'badge' => 'OpenStreetMap',
            'is_registered' => false,
            'source' => 'osm',
        ];
    }

    /**
     * Keep Nominatim traffic inside its one-request-per-second policy.
     */
    private function throttle(): void
    {
        if (! (bool) config('services.osm.throttle', true)) {
            return;
        }

        $last = (float) Cache::get(self::CACHE_PREFIX.'last_request', 0.0);

        if ($last > 0.0) {
            $wait = self::MIN_REQUEST_INTERVAL - (microtime(true) - $last);

            if ($wait > 0.0) {
                usleep((int) (min($wait, self::MIN_REQUEST_INTERVAL) * 1_000_000));
            }
        }

        Cache::put(self::CACHE_PREFIX.'last_request', microtime(true), 60);
    }

    private function validCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90.0 && $lat <= 90.0
            && $lng >= -180.0 && $lng <= 180.0
            && ! ($lat === 0.0 && $lng === 0.0);
    }

    private function config(string $key): string
    {
        return trim((string) config('services.osm.'.$key, ''));
    }

    private function ttl(string $key, int $default): int
    {
        return max(0, (int) config('services.osm.'.$key, $default));
    }

    private function timeout(): float
    {
        return max(0.5, (float) config('services.osm.timeout', 3.0));
    }

    private function userAgent(): string
    {
        $agent = $this->config('user_agent');

        return $agent !== '' ? $agent : 'IntercityWheels/2.0 ('.config('app.url').')';
    }

    private function countryCodes(): string
    {
        $codes = $this->config('country_codes');

        return $codes !== '' ? $codes : 'in';
    }
}
