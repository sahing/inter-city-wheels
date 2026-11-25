<?php

namespace App\Services;

use App\Models\GeoPlaceCache;
use App\Models\GeoRouteCache;
use Illuminate\Support\Facades\Log;

/**
 * Durable memory for OpenStreetMap answers.
 *
 * The HTTP cache inside OsmGeoService is fast but volatile: a cache flush, a
 * deployment or a different worker sends the next customer query back to
 * Nominatim/OSRM. This layer persists the measured route and the geocoded place
 * in the database, so the first query pays for the API call and every later
 * query is one indexed read.
 *
 * Every failure here is non-fatal: if the tables are missing or the database is
 * unavailable, the caller simply continues to the map API.
 */
class GeoCacheService
{
    /** null = not probed yet, false = unusable for the rest of this request. */
    private ?bool $available = null;

    public function enabled(): bool
    {
        return (bool) config('services.osm.db_cache', true) && $this->available !== false;
    }

    /**
     * A previously measured route for this pair of endpoints.
     *
     * @return array{distance_km: int, duration_min: int, engine: string, profile: string, cached: bool}|null
     */
    public function route(?float $fromLat, ?float $fromLng, ?float $toLat, ?float $toLng): ?array
    {
        if (! $this->enabled()
            || $fromLat === null || $fromLng === null
            || $toLat === null || $toLng === null) {
            return null;
        }

        try {
            $row = GeoRouteCache::query()
                ->where('route_key', self::routeKey($fromLat, $fromLng, $toLat, $toLng))
                ->first();
        } catch (\Throwable $e) {
            $this->fail($e);

            return null;
        }

        if ($row === null || self::expired($row->expires_at)) {
            return null;
        }

        $this->countHit($row);

        return [
            'distance_km' => (int) $row->distance_km,
            'duration_min' => (int) $row->duration_min,
            'engine' => (string) $row->engine,
            'profile' => 'driving',
            'cached' => true,
        ];
    }

    /**
     * Persist a measured route before the caller returns a quote.
     *
     * @param  array{distance_km: int, duration_min: int, base_toll?: float}  $metrics
     */
    public function rememberRoute(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        array $metrics,
        string $engine = 'osrm',
        ?string $fromName = null,
        ?string $toName = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $ttlDays = (int) config('services.osm.route_ttl_days', 30);

        $values = [
            'from_name' => self::trimmed($fromName),
            'to_name' => self::trimmed($toName),
            'from_lat' => round($fromLat, 5),
            'from_lng' => round($fromLng, 5),
            'to_lat' => round($toLat, 5),
            'to_lng' => round($toLng, 5),
            'distance_km' => max(1, (int) $metrics['distance_km']),
            'duration_min' => max(1, (int) $metrics['duration_min']),
            'base_toll' => round((float) ($metrics['base_toll'] ?? 0.0), 2),
            'engine' => $engine,
            'resolved_at' => now(),
            'expires_at' => $ttlDays > 0 ? now()->addDays($ttlDays) : null,
        ];

        $key = self::routeKey($fromLat, $fromLng, $toLat, $toLng);

        try {
            GeoRouteCache::updateOrCreate(['route_key' => $key], $values);
        } catch (\Throwable $e) {
            // A concurrent quote can win the unique key between read and write;
            // the row is then already there, so retry as a plain update.
            try {
                GeoRouteCache::query()->where('route_key', $key)->update($values);
            } catch (\Throwable $inner) {
                $this->fail($inner);
            }
        }
    }

    /**
     * A previously geocoded place for this exact query text.
     *
     * @return array{lat: float, lng: float, name: string, state: ?string, display_name: ?string, cached: bool}|null
     */
    public function place(string $query): ?array
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return null;
        }

        try {
            $row = GeoPlaceCache::query()
                ->where('query_key', self::placeKey($query))
                ->first();
        } catch (\Throwable $e) {
            $this->fail($e);

            return null;
        }

        if ($row === null || self::expired($row->expires_at)) {
            return null;
        }

        $this->countHit($row);

        return [
            'lat' => (float) $row->latitude,
            'lng' => (float) $row->longitude,
            'name' => (string) $row->name,
            'state' => $row->state,
            'display_name' => $row->display_name,
            'cached' => true,
        ];
    }

    /**
     * Persist a geocoded place before the caller uses it.
     *
     * @param  array{lat: float, lng: float, name: string, state?: ?string, display_name?: ?string}  $place
     */
    public function rememberPlace(string $query, array $place): void
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return;
        }

        $ttlDays = (int) config('services.osm.place_ttl_days', 90);

        $values = [
            'query' => mb_substr($query, 0, 255),
            'name' => mb_substr((string) $place['name'], 0, 255),
            'state' => self::trimmed($place['state'] ?? null),
            'latitude' => round((float) $place['lat'], 7),
            'longitude' => round((float) $place['lng'], 7),
            'display_name' => isset($place['display_name'])
                ? mb_substr((string) $place['display_name'], 0, 512)
                : null,
            'resolved_at' => now(),
            'expires_at' => $ttlDays > 0 ? now()->addDays($ttlDays) : null,
        ];

        $key = self::placeKey($query);

        try {
            GeoPlaceCache::updateOrCreate(['query_key' => $key], $values);
        } catch (\Throwable $e) {
            try {
                GeoPlaceCache::query()->where('query_key', $key)->update($values);
            } catch (\Throwable $inner) {
                $this->fail($inner);
            }
        }
    }

    /**
     * Delete expired rows.
     *
     * @return array{routes: int, places: int}
     */
    public function prune(): array
    {
        return [
            'routes' => GeoRouteCache::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete(),
            'places' => GeoPlaceCache::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete(),
        ];
    }

    /**
     * Key ignores direction: A→B and B→A share one measured route.
     */
    public static function routeKey(float $fromLat, float $fromLng, float $toLat, float $toLng): string
    {
        $ends = [
            sprintf('%.5F,%.5F', $fromLat, $fromLng),
            sprintf('%.5F,%.5F', $toLat, $toLng),
        ];

        sort($ends, SORT_STRING);

        return hash('sha256', 'route|'.$ends[0].'|'.$ends[1]);
    }

    public static function placeKey(string $query): string
    {
        return hash('sha256', 'place|'.mb_strtolower(trim($query)));
    }

    private static function expired(?\DateTimeInterface $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt->getTimestamp() <= now()->getTimestamp();
    }

    private function countHit(GeoRouteCache|GeoPlaceCache $row): void
    {
        try {
            $row->increment('hits');
        } catch (\Throwable) {
            // Hit counting is observability only; never fail a quote over it.
        }
    }

    private function fail(\Throwable $e): void
    {
        $this->available = false;

        Log::warning('Geo cache unavailable, continuing to the map API: '.$e->getMessage());
    }

    private static function trimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
