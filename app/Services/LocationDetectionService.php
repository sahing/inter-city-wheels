<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationDetectionService
{
    public function __construct(private readonly OsmGeoService $osm) {}

    /**
     * Resolve an initial pickup location from browser GPS coordinates.
     *
     * @return array{name: string, state: ?string, display_name: string, latitude: float, longitude: float, source: string, accuracy: string}|null
     */
    public function detectFromCoordinates(float $lat, float $lng): ?array
    {
        if (! $this->validCoordinates($lat, $lng)) {
            return null;
        }

        $lat = round($lat, 6);
        $lng = round($lng, 6);

        $reversed = $this->osm->reverse($lat, $lng);
        if ($reversed !== null && trim($reversed['name']) !== '') {
            $name = trim($reversed['name']);
            $state = isset($reversed['state']) && trim((string) $reversed['state']) !== ''
                ? trim((string) $reversed['state'])
                : null;
            $displayName = trim((string) ($reversed['display_name'] ?? ''));

            return [
                'name' => $name,
                'state' => $state,
                'display_name' => $displayName !== '' ? $displayName : ($state ? "{$name}, {$state}" : $name),
                'latitude' => (float) $reversed['lat'],
                'longitude' => (float) $reversed['lng'],
                'source' => 'gps',
                'accuracy' => 'precise',
            ];
        }

        // Offline / OSM-disabled fallback: snap to the closest registered hub or curated city.
        $nearest = RegionalLocationService::nearestTo($lat, $lng);
        if ($nearest !== null) {
            return [
                'name' => $nearest['name'],
                'state' => $nearest['state'],
                'display_name' => $nearest['state'] ? "{$nearest['name']}, {$nearest['state']}" : $nearest['name'],
                'latitude' => $lat,
                'longitude' => $lng,
                'source' => 'gps',
                'accuracy' => 'precise',
            ];
        }

        return null;
    }

    /**
     * Resolve an approximate initial pickup city from the caller's network IP.
     *
     * @return array{name: string, state: ?string, display_name: string, latitude: float, longitude: float, source: string, accuracy: string}|null
     */
    public function detectFromNetwork(?string $clientIp): ?array
    {
        if (! (bool) config('services.geoip.enabled', true)) {
            return null;
        }

        $baseUrl = trim((string) config('services.geoip.url', 'https://ipwho.is'));
        if ($baseUrl === '') {
            return null;
        }

        $ip = trim((string) $clientIp);
        $isPublicIp = $ip !== ''
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        $endpoint = rtrim($baseUrl, '/').($isPublicIp ? '/'.urlencode($ip) : '/');
        $cacheKey = 'geoip:network:'.md5($isPublicIp ? $ip : 'self');

        if (! app()->environment('testing')) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached['name'])) {
                return $cached;
            }
        }

        $timeout = max(0.5, (float) config('services.geoip.timeout', 3.0));

        try {
            $response = Http::timeout($timeout)->acceptJson()->get($endpoint);
        } catch (\Throwable $e) {
            Log::warning('Network IP geolocation lookup failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::info('Network IP geolocation returned HTTP '.$response->status());

            return null;
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['success'] ?? true) === false) {
            return null;
        }

        $city = trim((string) ($payload['city'] ?? $payload['name'] ?? ''));
        $state = trim((string) ($payload['region'] ?? $payload['regionName'] ?? $payload['state'] ?? ''));
        $lat = $payload['latitude'] ?? $payload['lat'] ?? null;
        $lng = $payload['longitude'] ?? $payload['lon'] ?? $payload['lng'] ?? null;

        if ($city === '' || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        if (! $this->validCoordinates($latF, $lngF)) {
            return null;
        }

        $result = [
            'name' => $city,
            'state' => $state !== '' ? $state : null,
            'display_name' => $state !== '' ? "{$city}, {$state}" : $city,
            'latitude' => round($latF, 6),
            'longitude' => round($lngF, 6),
            'source' => 'network',
            'accuracy' => 'approximate',
        ];

        if (! app()->environment('testing')) {
            Cache::put($cacheKey, $result, 900);
        }

        return $result;
    }

    private function validCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90.0 && $lat <= 90.0
            && $lng >= -180.0 && $lng <= 180.0
            && ! ($lat === 0.0 && $lng === 0.0);
    }
}
