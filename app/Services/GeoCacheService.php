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
     * @param  array{lat: float, lng: float, name: string, state?: ?string, display_name?: ?string, is_beyond_region?: bool}  $place
     */
    public function rememberPlace(string $query, array $place): void
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return;
        }

        $ttlDays = (int) config('services.osm.place_ttl_days', 90);
        $key = self::placeKey($query);

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

        if (array_key_exists('is_beyond_region', $place)) {
            $values['is_beyond_region'] = (bool) $place['is_beyond_region'];
        }

        try {
            $existing = GeoPlaceCache::query()->where('query_key', $key)->first();
            if ($existing) {
                // Keep pending/approved review status intact when refreshing cache metadata.
                if (in_array($existing->approval_status, ['pending', 'approved'], true)) {
                    unset($values['expires_at']);
                }
                $existing->update($values);
            } else {
                GeoPlaceCache::create(['query_key' => $key] + $values);
            }
        } catch (\Throwable $e) {
            try {
                GeoPlaceCache::query()->where('query_key', $key)->update($values);
            } catch (\Throwable $inner) {
                $this->fail($inner);
            }
        }
    }

    /**
     * Search durable cached places by name, query, or state to avoid repeat Nominatim calls.
     *
     * @return array<int, array{lat: float, lng: float, name: string, state: ?string, display_name: ?string, is_beyond_region: bool, approval_status: string, cached: bool}>
     */
    public function searchPlaces(string $query, int $limit = 5, bool $countHits = false): array
    {
        $clean = trim(explode(',', $query)[0]);

        if (! $this->enabled() || mb_strlen($clean) < 2) {
            return [];
        }

        $lower = mb_strtolower($clean);
        $limit = max(1, min(15, $limit));

        try {
            $rows = GeoPlaceCache::query()
                ->where(function ($q) use ($lower) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                        ->orWhereRaw('LOWER(query) LIKE ?', ["%{$lower}%"]);
                })
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('hits')
                ->orderBy('name')
                ->limit($limit * 2)
                ->get();
        } catch (\Throwable $e) {
            $this->fail($e);

            return [];
        }

        $seen = [];
        $results = [];

        foreach ($rows as $row) {
            if (($row->approval_status ?? 'cached') === 'dismissed') {
                continue;
            }

            $dedupeKey = mb_strtolower(trim((string) $row->name));
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            if ($countHits) {
                $this->countHit($row);
            }

            $results[] = [
                'lat' => (float) $row->latitude,
                'lng' => (float) $row->longitude,
                'name' => (string) $row->name,
                'state' => $row->state,
                'display_name' => $row->display_name,
                'is_beyond_region' => (bool) ($row->is_beyond_region ?? false),
                'approval_status' => (string) ($row->approval_status ?? 'cached'),
                'cached' => true,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Find an exact cached city match by name or original query.
     *
     * @return array{lat: float, lng: float, name: string, state: ?string, display_name: ?string, cached: bool}|null
     */
    public function findByName(string $cityName): ?array
    {
        $clean = trim(explode(',', $cityName)[0]);

        if (! $this->enabled() || $clean === '') {
            return null;
        }

        $lower = mb_strtolower($clean);

        try {
            $row = GeoPlaceCache::query()
                ->where(function ($q) use ($lower) {
                    $q->whereRaw('LOWER(name) = ?', [$lower])
                        ->orWhereRaw('LOWER(query) = ?', [$lower]);
                })
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('hits')
                ->first();
        } catch (\Throwable $e) {
            $this->fail($e);

            return null;
        }

        if ($row === null || ($row->approval_status ?? 'cached') === 'dismissed') {
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
     * Record an out-of-region city encountered during booking so an admin can review
     * and approve it into the active ServiceLocation list later.
     */
    public function recordBeyondRegionCity(
        string $cityName,
        float $lat,
        float $lng,
        ?string $state = null,
        ?string $displayName = null,
        ?int $bookingId = null,
        ?string $bookingRef = null,
    ): ?GeoPlaceCache {
        $clean = trim(explode(',', $cityName)[0]);

        if (! $this->enabled() || $clean === '') {
            return null;
        }

        $key = self::placeKey($clean);
        $lower = mb_strtolower($clean);

        try {
            $row = GeoPlaceCache::query()
                ->where('query_key', $key)
                ->orWhereRaw('LOWER(name) = ?', [$lower])
                ->first();

            if ($row) {
                $updates = [
                    'is_beyond_region' => true,
                    'last_booking_id' => $bookingId ?? $row->last_booking_id,
                    'last_booking_reference' => $bookingRef ?? $row->last_booking_reference,
                ];
                if (! in_array($row->approval_status, ['approved'], true)) {
                    $updates['approval_status'] = 'pending';
                    $updates['expires_at'] = null;
                }
                if (! $row->state && $state) {
                    $updates['state'] = self::trimmed($state);
                }
                $row->update($updates);

                return $row->fresh();
            }

            return GeoPlaceCache::create([
                'query_key' => $key,
                'query' => mb_substr($clean, 0, 255),
                'name' => mb_substr($clean, 0, 255),
                'state' => self::trimmed($state) ?? 'Outside Regional Map',
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
                'display_name' => $displayName ? mb_substr($displayName, 0, 512) : null,
                'is_beyond_region' => true,
                'approval_status' => 'pending',
                'last_booking_id' => $bookingId,
                'last_booking_reference' => $bookingRef,
                'resolved_at' => now(),
                'expires_at' => null,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);

            return null;
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
