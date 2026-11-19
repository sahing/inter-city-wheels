<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RouteFare;
use App\Models\ServiceLocation;
use App\Models\CarType;

class RouteCalculationController extends Controller
{
    /**
     * Compute route distance, drive time, highway tolls, and itemized fleet estimates.
     */
    public function calculate(Request $request)
    {
        $from = trim($request->query('from', 'Pune'));
        $to = trim($request->query('to', 'Goa'));
        $tripType = $request->query('trip_type', 'oneway');
        $couponCode = $request->query('coupon');

        // Clean city names (extract city if state/airport appended, e.g. "Pune, Maharashtra")
        $cleanFrom = trim(explode(',', $from)[0]);
        $cleanTo = trim(explode(',', $to)[0]);

        // 1. Look up static RouteFare
        $route = RouteFare::where(function ($q) use ($cleanFrom, $cleanTo) {
            $q->whereRaw('LOWER(from_city) = ?', [strtolower($cleanFrom)])
              ->whereRaw('LOWER(to_city) = ?', [strtolower($cleanTo)]);
        })->orWhere(function ($q) use ($cleanFrom, $cleanTo) {
            $q->whereRaw('LOWER(from_city) = ?', [strtolower($cleanTo)])
              ->whereRaw('LOWER(to_city) = ?', [strtolower($cleanFrom)]);
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
            // 2. Haversine GPS calculation from ServiceLocation coordinates
            $locFrom = ServiceLocation::whereRaw('LOWER(name) = ?', [strtolower($cleanFrom)])->first();
            $locTo = ServiceLocation::whereRaw('LOWER(name) = ?', [strtolower($cleanTo)])->first();

            if ($locFrom && $locTo) {
                $lat1 = deg2rad((float)$locFrom->latitude);
                $lng1 = deg2rad((float)$locFrom->longitude);
                $lat2 = deg2rad((float)$locTo->latitude);
                $lng2 = deg2rad((float)$locTo->longitude);

                $dLat = $lat2 - $lat1;
                $dLng = $lng2 - $lng1;
                $a = sin($dLat / 2) * sin($dLat / 2) + cos($lat1) * cos($lat2) * sin($dLng / 2) * sin($dLng / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $straightKm = 6371 * $c;
                $roadKm = max(25, round($straightKm * 1.25));

                $hours = floor($roadKm / 45);
                $mins = round(($roadKm % 45) * 1.3);

                $distanceKm = (int)$roadKm;
                $estimatedTime = "{$hours}h {$mins}m";
                $baseToll = round($roadKm * 0.9);
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
