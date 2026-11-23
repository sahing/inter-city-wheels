<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RouteFare;
use App\Models\ServiceLocation;
use App\Models\CarType;
use App\Services\RegionalLocationService;

class RouteCalculationController extends Controller
{
    /**
     * Compute route distance, drive time, highway tolls, and itemized fleet estimates.
     */
    public function calculate(Request $request)
    {
        $from = trim($request->query('from', 'Kolkata'));
        $to = trim($request->query('to', 'Digha'));
        $tripType = $request->query('trip_type', 'oneway');
        $couponCode = $request->query('coupon');

        // Clean city names (extract city if state/airport appended, e.g. "Berhampore, West Bengal")
        $cleanFrom = trim(explode(',', $from)[0]);
        $cleanTo = trim(explode(',', $to)[0]);

        $fromAliases = RegionalLocationService::normalizeAliases($cleanFrom);
        $toAliases = RegionalLocationService::normalizeAliases($cleanTo);

        // 1. Look up static RouteFare with alias and bidirectional support
        $route = RouteFare::where(function ($q) use ($fromAliases, $toAliases) {
            foreach ($fromAliases as $fa) {
                foreach ($toAliases as $ta) {
                    $q->orWhere(function ($sub) use ($fa, $ta) {
                        $sub->whereRaw('LOWER(from_city) = ?', [strtolower($fa)])
                            ->whereRaw('LOWER(to_city) = ?', [strtolower($ta)]);
                    })->orWhere(function ($sub) use ($fa, $ta) {
                        $sub->whereRaw('LOWER(from_city) = ?', [strtolower($ta)])
                            ->whereRaw('LOWER(to_city) = ?', [strtolower($fa)]);
                    });
                }
            }
        })->first();

        $distanceKm = 450;
        $estimatedTime = '9h 15m';
        $baseToll = 480.00;
        $roundtripDiscount = 0.0;

        if ($route) {
            $distanceKm = (int)$route->distance_km;
            $estimatedTime = $route->estimated_time;
            $baseToll = (float)$route->base_toll;
            $roundtripDiscount = (float)($route->roundtrip_discount_pct ?? 0);
        } else {
            // 2. Dynamic GPS calculation from ServiceLocation, Regional Catalog, or Request Lat/Lng
            $fromLat = $request->filled('from_lat') ? (float)$request->query('from_lat') : null;
            $fromLng = $request->filled('from_lng') ? (float)$request->query('from_lng') : null;
            $toLat = $request->filled('to_lat') ? (float)$request->query('to_lat') : null;
            $toLng = $request->filled('to_lng') ? (float)$request->query('to_lng') : null;

            if ($fromLat === null || $fromLng === null) {
                $c1 = RegionalLocationService::findCoordinates($cleanFrom);
                if ($c1) {
                    $fromLat = $c1['lat'];
                    $fromLng = $c1['lng'];
                }
            }

            if ($toLat === null || $toLng === null) {
                $c2 = RegionalLocationService::findCoordinates($cleanTo);
                if ($c2) {
                    $toLat = $c2['lat'];
                    $toLng = $c2['lng'];
                }
            }

            if ($fromLat !== null && $fromLng !== null && $toLat !== null && $toLng !== null) {
                $metrics = RegionalLocationService::computeRouteMetrics($fromLat, $fromLng, $toLat, $toLng);
                $distanceKm = $metrics['distance_km'];
                $estimatedTime = $metrics['estimated_time'];
                $baseToll = $metrics['base_toll'];
            }
        }

        // 3. Fleet Pricing
        $carTypes = CarType::where('is_active', true)->orderBy('order_index')->get();
        if ($carTypes->isEmpty()) {
            $carTypes = collect([
                (object)['category_key' => 'hatchback', 'name' => 'Compact Hatchback', 'rate_per_km' => 11.0, 'base_fare_min' => 750],
                (object)['category_key' => 'sedan', 'name' => 'Comfort Sedan', 'rate_per_km' => 12.5, 'base_fare_min' => 950],
                (object)['category_key' => 'prime', 'name' => 'Prime Sedan', 'rate_per_km' => 15.0, 'base_fare_min' => 1100],
                (object)['category_key' => 'suv', 'name' => 'Family SUV', 'rate_per_km' => 18.0, 'base_fare_min' => 1400],
                (object)['category_key' => 'innova', 'name' => 'Innova Crysta', 'rate_per_km' => 22.0, 'base_fare_min' => 1800],
                (object)['category_key' => 'tempo', 'name' => 'Tempo Traveller', 'rate_per_km' => 28.0, 'base_fare_min' => 2500],
                (object)['category_key' => 'luxury', 'name' => 'Luxury Limousine', 'rate_per_km' => 32.0, 'base_fare_min' => 3500],
            ]);
        }

        $fleetEstimates = [];
        $itemizedBreakdown = [];

        foreach ($carTypes as $ct) {
            $rate = (float)$ct->rate_per_km;
            $minBase = (float)($ct->base_fare_min ?? 800);
            $catKey = $ct->category_key;

            if ($route) {
                $oneWayFare = $route->getFareForCarType($catKey, $rate);
            } else {
                $oneWayFare = max($minBase, round($distanceKm * $rate + $baseToll));
            }

            $effectiveFare = $oneWayFare;
            if ($tripType === 'roundtrip') {
                $effectiveFare = round($oneWayFare * 2);
                if ($roundtripDiscount > 0) {
                    $effectiveFare = round($effectiveFare * (1 - ($roundtripDiscount / 100)));
                }
            }

            $fleetEstimates[$catKey] = $effectiveFare;
            $itemizedBreakdown[$catKey] = [
                'name' => $ct->name ?? ucfirst($catKey),
                'rate_per_km' => $rate,
                'distance_km' => $distanceKm,
                'base_fare' => max($minBase, round($distanceKm * $rate)),
                'toll_fare' => $baseToll,
                'total' => $effectiveFare,
            ];
        }

        return response()->json([
            'success' => true,
            'route' => [
                'from_city' => $cleanFrom,
                'to_city' => $cleanTo,
                'distance_km' => $distanceKm,
                'estimated_time' => $estimatedTime,
                'base_toll' => $baseToll,
                'roundtrip_discount_pct' => $roundtripDiscount
            ],
            'fleet_estimates' => $fleetEstimates,
            'itemized_breakdown' => $itemizedBreakdown,
            'addons' => [
                'pet_friendly' => 350.00,
                'night_allowance' => 400.00,
                'luggage_carrier' => 200.00
            ]
        ]);
    }
}
