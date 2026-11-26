<?php

namespace App\Services;

use App\Exceptions\DistanceUnresolvable;
use App\Models\AirportHub;
use App\Models\CarType;
use App\Models\Coupon;
use App\Models\LocalPackage;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\SeatLayout;
use Illuminate\Support\Collection;

/**
 * The only place a fare is calculated.
 *
 * Previously the same arithmetic existed in BookingController (what the
 * customer was charged), RouteCalculationController (what the customer was
 * quoted), public/js/app.js (what the customer was shown) with three different
 * per-km tables, and in the Blade view. The numbers disagreed. Everything now
 * goes through this service, and the browser only displays its output.
 *
 * All money values are rounded to whole rupees, matching the previous
 * behaviour of the booking endpoint.
 */
class FareCalculatorService
{
    /** Fixed optional extras. */
    public const ADDONS = [
        'pet_friendly' => 350.0,
        'night_allowance' => 400.0,
        'luggage_carrier' => 200.0,
    ];

    /** Fallback per-km rates when the car_types table is empty. */
    public const DEFAULT_RATES = [
        'hatchback' => 11.0,
        'sedan' => 12.5,
        'prime' => 15.0,
        'suv' => 18.0,
        'innova' => 22.0,
        'tempo' => 28.0,
        'luxury' => 32.0,
    ];

    /** Fallback minimum base fares (mirrors the car_types seed). */
    public const DEFAULT_MIN_BASE = [
        'hatchback' => 750.0,
        'sedan' => 950.0,
        'prime' => 1100.0,
        'suv' => 1400.0,
        'innova' => 1800.0,
        'tempo' => 2500.0,
        'luxury' => 3500.0,
    ];

    /** Airport trips that cannot be measured fall back to this toll. */
    public const AIRPORT_TOLL = 120.0;

    /** OpenStreetMap access (Nominatim geocoding, OSRM distances). */
    private readonly OsmGeoService $osm;

    public function __construct(?OsmGeoService $osm = null)
    {
        $this->osm = $osm ?? new OsmGeoService;
    }

    /**
     * Resolve the trip geometry (distance, drive time, toll) from a real
     * source: the route_fares table, the curated location dataset, an explicit
     * airport hub/local package, or caller-supplied coordinates.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws DistanceUnresolvable
     */
    public function resolveTrip(array $input): array
    {
        $from = (string) ($input['from_city'] ?? '');
        $to = (string) ($input['to_city'] ?? '');
        $tripType = (string) ($input['trip_type'] ?? 'oneway');

        $cleanFrom = trim(explode(',', $from)[0]);
        $cleanTo = trim(explode(',', $to)[0]);

        if ($tripType === 'local') {
            $package = $this->resolveLocalPackage($input['local_package'] ?? null);

            return [
                'trip_type' => 'local',
                'from_city' => $cleanFrom,
                'to_city' => $cleanTo,
                'distance_km' => (int) $package->included_km,
                'estimated_time' => $package->duration_hours.' hrs',
                'base_toll' => 0.0,
                'roundtrip_discount_pct' => 0.0,
                'source' => 'local_package',
                'local_package' => $package,
                'route' => null,
                'airport_hub' => null,
            ];
        }

        if ($tripType === 'airport') {
            $hub = $this->resolveAirportHub($cleanFrom, $cleanTo);

            return [
                'trip_type' => 'airport',
                'from_city' => $cleanFrom,
                'to_city' => $cleanTo,
                'distance_km' => 0,
                'estimated_time' => 'Transfer',
                'base_toll' => self::AIRPORT_TOLL,
                'roundtrip_discount_pct' => 0.0,
                'source' => $hub ? 'airport_hub' : 'unmeasured',
                'local_package' => null,
                'route' => null,
                'airport_hub' => $hub,
                'airport_type' => (string) ($input['airport_type'] ?? 'pickup'),
            ];
        }

        $route = $this->resolveRoute($cleanFrom, $cleanTo);

        if ($route) {
            return [
                'trip_type' => $tripType,
                'from_city' => $cleanFrom,
                'to_city' => $cleanTo,
                'distance_km' => (int) $route->distance_km,
                'estimated_time' => (string) $route->estimated_time,
                'base_toll' => (float) $route->base_toll,
                'roundtrip_discount_pct' => (float) ($route->roundtrip_discount_pct ?? 0),
                'source' => 'route_table',
                'local_package' => null,
                'route' => $route,
                'airport_hub' => null,
            ];
        }

        $metrics = $this->measureFromCoordinates($input, $cleanFrom, $cleanTo);

        if ($metrics === null) {
            throw new DistanceUnresolvable($cleanFrom, $cleanTo);
        }

        return [
            'trip_type' => $tripType,
            'from_city' => $cleanFrom,
            'to_city' => $cleanTo,
            'distance_km' => (int) $metrics['distance_km'],
            'estimated_time' => (string) $metrics['estimated_time'],
            'base_toll' => (float) $metrics['base_toll'],
            'roundtrip_discount_pct' => 0.0,
            'source' => $metrics['source'],
            'cached' => (bool) ($metrics['cached'] ?? false),
            'local_package' => null,
            'route' => null,
            'airport_hub' => null,
        ];
    }

    /**
     * Price one fleet against an already-resolved trip.
     *
     * @param  array<string, mixed>  $trip  Output of resolveTrip().
     * @param  array<string, mixed>  $options  include_tolls, pet_friendly, night_allowance, luggage_carrier, coupon_code, airport_type
     * @return array<string, mixed>
     */
    public function quote(array $trip, string $fleetCategory, array $options = []): array
    {
        $fleet = $this->normalizeFleet($fleetCategory);
        $rate = $this->rateForFleet($fleet);
        $includeTolls = (bool) ($options['include_tolls'] ?? false);

        $lineItems = [];

        switch ($trip['trip_type']) {
            case 'local':
                $baseFare = $this->localPackageFare($trip['local_package'], $fleet, $rate);
                $tollFare = 0.0;
                $lineItems[] = [
                    'label' => $trip['local_package']->name.' ('.$trip['local_package']->included_km.' km)',
                    'amount' => $baseFare,
                ];
                break;

            case 'airport':
                $hub = $trip['airport_hub'];
                $baseFare = $this->airportFare($trip, $fleet, $rate);
                $tollFare = $includeTolls ? self::AIRPORT_TOLL : 0.0;
                $lineItems[] = [
                    'label' => $hub ? $hub->airport_name.' transfer' : 'Airport transfer',
                    'amount' => $baseFare,
                ];
                if ($tollFare > 0) {
                    $lineItems[] = ['label' => 'Airport toll & parking', 'amount' => $tollFare];
                }
                break;

            default: // oneway, roundtrip
                [$basePortion, $tollPortion] = $this->distanceComponents($trip, $fleet, $rate, $includeTolls);

                if ($trip['trip_type'] === 'roundtrip') {
                    $baseFare = round($basePortion * 2);
                    if ($trip['roundtrip_discount_pct'] > 0) {
                        $baseFare = round($baseFare * (1 - ($trip['roundtrip_discount_pct'] / 100)));
                    }
                    $tollFare = round($tollPortion * 1.8);
                    $lineItems[] = ['label' => 'Round trip fare', 'amount' => $baseFare];
                } else {
                    $baseFare = $basePortion;
                    $tollFare = $tollPortion;
                    $lineItems[] = ['label' => 'Base distance fare', 'amount' => $baseFare];
                }

                if ($tollFare > 0) {
                    $lineItems[] = ['label' => 'FASTag highway tolls', 'amount' => $tollFare];
                }
        }

        [$addonFare, $appliedAddons] = $this->computeAddons($options, $lineItems);

        if (in_array($trip['trip_type'], ['oneway', 'roundtrip'], true)) {
            $driverAllowance = (float) ($this->pricingConditions()['driver_day_allowance'] ?? 0);
            if ($driverAllowance > 0) {
                $addonFare += $driverAllowance;
                $appliedAddons['driver_day_allowance'] = $driverAllowance;
                $lineItems[] = ['label' => 'Driver day allowance', 'amount' => $driverAllowance];
            }
        }

        $subtotal = $baseFare + $tollFare + $addonFare;
        $coupon = $this->resolveCoupon($options['coupon_code'] ?? null, $subtotal);

        if ($coupon['applied']) {
            $lineItems[] = ['label' => 'Coupon '.$coupon['code'], 'amount' => -$coupon['discount']];
        }

        $total = max(0, round($subtotal - $coupon['discount']));

        return [
            'fleet_category' => $fleet,
            'rate_per_km' => $rate,
            'distance_km' => (int) $trip['distance_km'],
            'estimated_time' => $trip['estimated_time'],
            'base_fare' => (float) round($baseFare),
            'toll_fare' => (float) round($tollFare),
            'addon_fare' => (float) round($addonFare),
            'applied_addons' => $appliedAddons,
            'discount_fare' => (float) round($coupon['discount']),
            'total_amount' => (float) $total,
            'coupon' => $coupon,
            'line_items' => $lineItems,
            'distance_source' => $trip['source'] ?? 'unknown',
            'trip_type' => $trip['trip_type'],
        ];
    }

    /**
     * Itemised cabin estimate for every active fleet, sharing one trip
     * resolution so nine cards cost one distance lookup.
     *
     * @param  array<string, mixed>  $trip
     * @param  array<string, mixed>  $options
     * @return array<string, array<string, mixed>>
     */
    public function quoteAllFleets(array $trip, array $options = []): array
    {
        $estimates = [];

        foreach ($this->fleetCatalogue() as $fleet) {
            $quote = $this->quote($trip, $fleet->category_key, $options);
            $estimates[$fleet->category_key] = [
                'name' => $fleet->name ?? ucfirst($fleet->category_key),
                'rate_per_km' => $quote['rate_per_km'],
                'distance_km' => $quote['distance_km'],
                'base_fare' => $quote['base_fare'],
                'toll_fare' => $quote['toll_fare'],
                'total' => $quote['total_amount'],
            ];
        }

        return $estimates;
    }

    /**
     * Price a berth selection. Berth prices come from seat_layouts, never from
     * a constant.
     *
     * @param  Collection<int, SeatLayout>  $berths
     * @return array<string, mixed>
     */
    public function quoteBerths(Collection $berths, array $options = []): array
    {
        $baseFare = (float) $berths->sum('price');

        $lineItems = [];
        [$addonFare, $appliedAddons] = $this->computeAddons($options, $lineItems);

        $subtotal = $baseFare + $addonFare;
        $coupon = $this->resolveCoupon($options['coupon_code'] ?? null, $subtotal);
        $total = max(0, round($subtotal - $coupon['discount']));

        return [
            'base_fare' => $baseFare,
            'toll_fare' => 0.0,
            'addon_fare' => $addonFare,
            'applied_addons' => $appliedAddons,
            'discount_fare' => (float) round($coupon['discount']),
            'total_amount' => (float) $total,
            'coupon' => $coupon,
        ];
    }

    /**
     * Validate a coupon against a subtotal without writing anything.
     *
     * P0-4: expiry, usage ceiling and minimum spend are all enforced here, so
     * the quote and the charge cannot disagree.
     *
     * @return array{code: ?string, applied: bool, discount: float, message: string, coupon_id: ?int}
     */
    public function resolveCoupon(?string $rawCode, float $subtotal): array
    {
        $code = strtoupper(trim((string) $rawCode));

        if ($code === '') {
            return $this->couponResult(null, false, 0.0, '', null);
        }

        $coupon = Coupon::where('code', $code)->where('is_active', true)->first();

        if (! $coupon) {
            return $this->couponResult($code, false, 0.0, 'Coupon code is invalid or inactive.', null);
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            return $this->couponResult(
                $code,
                false,
                0.0,
                'Coupon expired on '.$coupon->expires_at->format('d M Y').'.',
                $coupon->id
            );
        }

        if ($coupon->usage_limit !== null && (int) $coupon->used_count >= (int) $coupon->usage_limit) {
            return $this->couponResult(
                $code,
                false,
                0.0,
                'Coupon has reached its usage limit.',
                $coupon->id
            );
        }

        $minSpend = (float) ($coupon->min_spend ?? 0);

        if ($subtotal < $minSpend) {
            return $this->couponResult(
                $code,
                false,
                0.0,
                'Coupon needs a minimum spend of ₹'.number_format($minSpend, 0).'.',
                $coupon->id
            );
        }

        if ($coupon->type === 'percentage') {
            $discount = $subtotal * ((float) $coupon->value / 100);
            if ($coupon->max_discount !== null) {
                $discount = min($discount, (float) $coupon->max_discount);
            }
        } else {
            $discount = (float) $coupon->value;
        }

        $discount = round(max(0.0, min($discount, $subtotal)), 2);

        return $this->couponResult($code, $discount > 0, $discount, 'Coupon applied.', $coupon->id);
    }

    /**
     * Record a redemption. Must be called inside the booking transaction, with
     * the row locked, so two simultaneous bookings cannot both take the last
     * remaining use of a limited coupon.
     *
     * Only an applied coupon counts: a rejected code (expired, exhausted, below
     * minimum spend) must not consume inventory.
     *
     * @param  array{applied?: bool, coupon_id?: ?int}  $coupon
     */
    public function redeemCoupon(array $coupon): void
    {
        if (empty($coupon['applied']) || empty($coupon['coupon_id'])) {
            return;
        }

        $model = Coupon::whereKey($coupon['coupon_id'])->lockForUpdate()->first();

        if ($model) {
            $model->increment('used_count');
        }
    }

    public function rateForFleet(string $fleet): float
    {
        $fleet = $this->normalizeFleet($fleet);
        $carType = CarType::where('category_key', $fleet)->first();

        if ($carType && (float) $carType->rate_per_km > 0) {
            return (float) $carType->rate_per_km;
        }

        return self::DEFAULT_RATES[$fleet] ?? self::DEFAULT_RATES['suv'];
    }

    public function minBaseForFleet(string $fleet): float
    {
        $fleet = $this->normalizeFleet($fleet);
        $carType = CarType::where('category_key', $fleet)->first();

        if ($carType && $carType->base_fare_min !== null) {
            return (float) $carType->base_fare_min;
        }

        return self::DEFAULT_MIN_BASE[$fleet] ?? 0.0;
    }

    public function normalizeFleet(string $fleet): string
    {
        $fleet = strtolower(trim($fleet));

        if (array_key_exists($fleet, self::DEFAULT_RATES)) {
            return $fleet;
        }

        if ($fleet !== '' && CarType::whereRaw('LOWER(category_key) = ?', [$fleet])->exists()) {
            return $fleet;
        }

        return 'suv';
    }

    /**
     * @return Collection<int, CarType>
     */
    public function fleetCatalogue(): Collection
    {
        $active = CarType::where('is_active', true)->orderBy('order_index')->get();
        $configured = CarType::pluck('category_key')
            ->map(fn ($key) => strtolower((string) $key))
            ->all();

        $defaults = collect(self::DEFAULT_RATES)
            ->reject(fn ($rate, $key) => in_array($key, $configured, true))
            ->map(function ($rate, $key) {
                $carType = new CarType;
                $carType->category_key = $key;
                $carType->name = ucfirst($key);
                $carType->rate_per_km = $rate;
                $carType->base_fare_min = self::DEFAULT_MIN_BASE[$key] ?? 0;

                return $carType;
            })
            ->values();

        // Always return active categories plus any built-in defaults not yet
        // configured in `car_types`, while keeping explicitly deactivated rows off.
        return $active->concat($defaults)->values();
    }

    /**
     * @return array<string, float>
     */
    public function addonRates(): array
    {
        $settings = PlatformSetting::pricingSettings();

        return [
            'pet_friendly' => (float) $settings['addon_pet_friendly_rate'],
            'night_allowance' => (float) $settings['addon_night_allowance_rate'],
            'luggage_carrier' => (float) $settings['addon_luggage_carrier_rate'],
        ];
    }

    /**
     * Pricing conditions (night stay hours, roundtrip min km/day, waiting & cancellation rules).
     *
     * @return array<string, mixed>
     */
    public function pricingConditions(): array
    {
        $settings = PlatformSetting::pricingSettings();

        return [
            'night_stay_rate' => (float) $settings['addon_night_allowance_rate'],
            'night_stay_start_hour' => $settings['night_stay_start_hour'],
            'night_stay_end_hour' => $settings['night_stay_end_hour'],
            'pet_friendly_rate' => (float) $settings['addon_pet_friendly_rate'],
            'luggage_carrier_rate' => (float) $settings['addon_luggage_carrier_rate'],
            'roundtrip_min_km_per_day' => (int) $settings['roundtrip_min_km_per_day'],
            'driver_day_allowance' => (float) $settings['driver_day_allowance'],
            'free_waiting_minutes' => (int) $settings['free_waiting_minutes'],
            'waiting_charge_per_hour' => (float) $settings['waiting_charge_per_hour'],
            'free_cancellation_hours' => (int) $settings['free_cancellation_hours'],
            'other_booking_conditions' => $settings['other_booking_conditions'],
        ];
    }

    /**
     * Compute active optional add-on charges using persisted rates.
     *
     * @param  array<string, mixed>  $options
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array{0: float, 1: array<string, float>}
     */
    private function computeAddons(array $options, array &$lineItems): array
    {
        $addonFare = 0.0;
        $appliedAddons = [];

        foreach ($this->addonRates() as $key => $amount) {
            if (! empty($options[$key])) {
                $addonFare += $amount;
                $appliedAddons[$key] = $amount;
                $lineItems[] = ['label' => $this->addonLabel($key), 'amount' => $amount];
            }
        }

        return [$addonFare, $appliedAddons];
    }

    public function resolveRoute(string $from, string $to): ?RouteFare
    {
        $fromAliases = RegionalLocationService::normalizeAliases($from);
        $toAliases = RegionalLocationService::normalizeAliases($to);

        return RouteFare::where('is_active', true)
            ->where(function ($query) use ($fromAliases, $toAliases) {
                foreach ($fromAliases as $fa) {
                    foreach ($toAliases as $ta) {
                        $query->orWhere(function ($sub) use ($fa, $ta) {
                            $sub->whereRaw('LOWER(from_city) = ?', [strtolower($fa)])
                                ->whereRaw('LOWER(to_city) = ?', [strtolower($ta)]);
                        })->orWhere(function ($sub) use ($fa, $ta) {
                            $sub->whereRaw('LOWER(from_city) = ?', [strtolower($ta)])
                                ->whereRaw('LOWER(to_city) = ?', [strtolower($fa)]);
                        });
                    }
                }
            })
            ->first();
    }

    /**
     * Derive metrics from coordinates.
     *
     * Order of preference for each endpoint: caller-supplied coordinates, the
     * registered/curated dataset, then Nominatim. Distance comes from OSRM's
     * driving route when it is reachable, and falls back to the straight-line
     * road estimate only when routing is unavailable.
     *
     * @param  array<string, mixed>  $input
     * @return array{distance_km: int, estimated_time: string, base_toll: float, source: string}|null
     */
    private function measureFromCoordinates(array $input, string $from, string $to): ?array
    {
        [$fromLat, $fromLng, $fromSource] = $this->coordinatesFor($input, 'from', $from);
        [$toLat, $toLng, $toSource] = $this->coordinatesFor($input, 'to', $to);

        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
            return null;
        }

        if ($fromLat === $toLat && $fromLng === $toLng) {
            return null;
        }

        $source = match (true) {
            $fromSource === 'osm_geocode' || $toSource === 'osm_geocode' => 'osm_geocode',
            $fromSource === 'coordinates' && $toSource === 'coordinates' => 'coordinates',
            default => 'location_dataset',
        };

        $driving = $this->osm->route($fromLat, $fromLng, $toLat, $toLng, $from, $to);

        if ($driving !== null) {
            return [
                'distance_km' => (int) $driving['distance_km'],
                'estimated_time' => $this->osm->formatDuration((int) $driving['duration_min']),
                'base_toll' => (float) round($driving['distance_km'] * 0.92),
                'source' => $source.'+osrm_route',
                'cached' => (bool) ($driving['cached'] ?? false),
            ];
        }

        $metrics = RegionalLocationService::computeRouteMetrics($fromLat, $fromLng, $toLat, $toLng);

        return [
            'distance_km' => (int) $metrics['distance_km'],
            'estimated_time' => (string) $metrics['estimated_time'],
            'base_toll' => (float) $metrics['base_toll'],
            'source' => $source,
            'cached' => false,
        ];
    }

    /**
     * Coordinates for one endpoint and how they were obtained.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: ?float, 1: ?float, 2: string}
     */
    private function coordinatesFor(array $input, string $prefix, string $name): array
    {
        $lat = $input[$prefix.'_lat'] ?? null;
        $lng = $input[$prefix.'_lng'] ?? null;

        if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
            return [(float) $lat, (float) $lng, 'coordinates'];
        }

        $resolved = RegionalLocationService::resolveCoordinatesStrict($name);

        if ($resolved !== null) {
            return [(float) $resolved['lat'], (float) $resolved['lng'], 'location_dataset'];
        }

        $geocoded = $this->osm->geocode($name);

        if ($geocoded !== null) {
            return [(float) $geocoded['lat'], (float) $geocoded['lng'], 'osm_geocode'];
        }

        return [null, null, 'unresolved'];
    }

    /**
     * Split the distance-based price into its taxable base and toll portion.
     *
     * Route-table fares are all-inclusive (distance x rate + toll), so when the
     * customer asks for tolls to be itemised the toll is moved out of the base
     * and shown as its own line: the total is unchanged either way.
     *
     * @param  array<string, mixed>  $trip
     * @return array{0: float, 1: float}
     */
    private function distanceComponents(array $trip, string $fleet, float $rate, bool $includeTolls): array
    {
        /** @var RouteFare|null $route */
        $route = $trip['route'] ?? null;

        if ($route) {
            $allInclusive = (float) round($route->getFareForCarType($fleet, $rate));
            $toll = (float) $route->base_toll;

            if ($includeTolls && $toll > 0) {
                return [max(0.0, $allInclusive - $toll), $toll];
            }

            return [$allInclusive, 0.0];
        }

        $byDistance = (float) round($trip['distance_km'] * $rate);
        $base = max($this->minBaseForFleet($fleet), $byDistance);
        $toll = $includeTolls ? (float) $trip['base_toll'] : 0.0;

        return [$base, $toll];
    }

    private function localPackageFare(LocalPackage $package, string $fleet, float $rate): float
    {
        $sedan = (float) $package->sedan_fare;
        $suv = (float) $package->suv_fare;

        if ($fleet === 'sedan') {
            return round($sedan);
        }

        if ($fleet === 'suv') {
            return round($suv);
        }

        // Scale the SUV package price by the fleet's relative per-km rate.
        $suvRefRate = max(1.0, $this->rateForFleet('suv'));
        $scaled = $suv * ($rate / $suvRefRate);

        return round(max(0.0, $scaled));
    }

    private function airportFare(array $trip, string $fleet, float $rate): float
    {
        /** @var AirportHub|null $hub */
        $hub = $trip['airport_hub'];

        $direction = (string) ($trip['airport_type'] ?? 'pickup');
        $surcharge = 0.0;

        if ($hub) {
            $surcharge = $direction === 'drop'
                ? (float) $hub->drop_surcharge
                : (float) $hub->pickup_surcharge;

            $suvRefRate = max(1.0, $this->rateForFleet('suv'));

            if ($fleet === 'sedan') {
                $base = (float) $hub->sedan_base_fare;
            } elseif ($fleet === 'suv') {
                $base = (float) $hub->suv_base_fare;
            } elseif ($fleet === 'prime') {
                $primeRow = CarType::where('category_key', 'prime')->first();
                if ($primeRow && (float) $primeRow->rate_per_km > 0) {
                    $sedanRef = max(1.0, $this->rateForFleet('sedan'));
                    $base = (float) $hub->sedan_base_fare * ((float) $primeRow->rate_per_km / $sedanRef);
                } else {
                    $base = (float) $hub->sedan_base_fare * 1.15;
                }
            } else {
                $base = (float) $hub->suv_base_fare * ($rate / $suvRefRate);
            }

            return round($base + $surcharge);
        }

        // No hub configured: price the measured transfer, never a flat guess.
        $base = round($trip['distance_km'] * $rate);
        $base = max($base, $this->minBaseForFleet($fleet));

        return (float) $base;
    }

    private function resolveLocalPackage(?string $code): LocalPackage
    {
        $code = trim((string) $code);

        if ($code !== '') {
            $package = LocalPackage::where('package_code', $code)->where('is_active', true)->first();

            if ($package) {
                return $package;
            }
        }

        return LocalPackage::where('is_active', true)->orderBy('duration_hours')->first()
            ?? new LocalPackage([
                'package_code' => '8_80',
                'name' => '8 Hours / 80 km',
                'duration_hours' => 8,
                'included_km' => 80,
                'sedan_fare' => 2000,
                'suv_fare' => 2448,
            ]);
    }

    private function resolveAirportHub(string $from, string $to): ?AirportHub
    {
        $candidates = [trim(explode(',', $from)[0]), trim(explode(',', $to)[0])];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $hub = AirportHub::where('is_active', true)
                ->where(function ($query) use ($candidate) {
                    $query->whereRaw('LOWER(city) = ?', [strtolower($candidate)])
                        ->orWhereRaw('LOWER(iata_code) = ?', [strtoupper($candidate)])
                        ->orWhereRaw('LOWER(city) like ?', [strtolower($candidate).'%'])
                        ->orWhereRaw('LOWER(airport_name) like ?', ['%'.strtolower($candidate).'%']);
                })
                ->first();

            if ($hub) {
                return $hub;
            }
        }

        return null;
    }

    private function addonLabel(string $key): string
    {
        return match ($key) {
            'pet_friendly' => 'Pet-friendly allowance',
            'night_allowance' => 'Night driving allowance',
            'luggage_carrier' => 'Luggage carrier',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /**
     * @return array{code: ?string, applied: bool, discount: float, message: string, coupon_id: ?int}
     */
    private function couponResult(?string $code, bool $applied, float $discount, string $message, ?int $couponId): array
    {
        return [
            'code' => $code,
            'applied' => $applied,
            'discount' => (float) $discount,
            'message' => $message,
            'coupon_id' => $couponId,
        ];
    }
}
