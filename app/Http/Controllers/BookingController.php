<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\RouteFare;
use App\Models\SeatLayout;
use App\Services\RegionalLocationService;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|in:cab,bus',
            'trip_type' => 'nullable|string',
            'from_city' => 'required|string',
            'to_city' => 'nullable|string',
            'pickup_location' => 'required|string',
            'drop_location' => 'nullable|string',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email',
            'departure_time' => 'nullable|string',
            'return_time' => 'nullable|string',
            'local_package' => 'nullable|string',
            'airport_type' => 'nullable|string',
            'flight_number' => 'nullable|string',
            'passengers' => 'nullable|array',
            'selected_units' => 'nullable|array',
            'fleet_category' => 'nullable|string',
            'include_tolls' => 'nullable|boolean',
            'pet_friendly' => 'nullable|boolean',
            'night_allowance' => 'nullable|boolean',
            'luggage_carrier' => 'nullable|boolean',
            'coupon_code' => 'nullable|string',
            'dynamic_fields' => 'nullable|array'
        ]);

        $tripType = $validated['trip_type'] ?? 'oneway';
        $toCity = $validated['to_city'] ?? ($tripType === 'local' ? ($validated['from_city'] . ' Local') : 'Destination');
        $dropLocation = $validated['drop_location'] ?? ($tripType === 'local' ? 'Local City Sightseeing & Transit' : 'Drop Address');

        $cleanFrom = trim(explode(',', $validated['from_city'])[0]);
        $cleanTo = trim(explode(',', $toCity)[0]);

        $fromAliases = RegionalLocationService::normalizeAliases($cleanFrom);
        $toAliases = RegionalLocationService::normalizeAliases($cleanTo);

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

        if ($route) {
            $distance = (int)$route->distance_km;
            $baseToll = ($request->boolean('include_tolls') && $route) ? (float)$route->base_toll : 0;
        } else {
            $c1 = RegionalLocationService::findCoordinates($cleanFrom);
            $c2 = RegionalLocationService::findCoordinates($cleanTo);
            if ($c1 && $c2) {
                $metrics = RegionalLocationService::computeRouteMetrics($c1['lat'], $c1['lng'], $c2['lat'], $c2['lng']);
                $distance = $metrics['distance_km'];
                $baseToll = $request->boolean('include_tolls') ? $metrics['base_toll'] : 0;
            } else {
                $distance = 345;
                $baseToll = $request->boolean('include_tolls') ? 320 : 0;
            }
        }

        $rates = [
            'hatchback' => 11.0,
            'sedan' => 12.5,
            'prime' => 15.0,
            'suv' => 18.0,
            'innova' => 22.0,
            'tempo' => 28.0,
            'luxury' => 32.0
        ];

        $fleet = strtolower($validated['fleet_category'] ?? 'suv');
        $carTypeModel = \App\Models\CarType::where('category_key', $fleet)->first();
        $ratePerKm = $carTypeModel ? (float)$carTypeModel->rate_per_km : ($rates[$fleet] ?? 18.0);

        $baseFare = 0;
        if ($validated['service_type'] === 'cab') {
            if ($tripType === 'local') {
                $pkg = $validated['local_package'] ?? '8_80';
                $pkgMultipliers = [
                    '4_40' => 40 * 1.8,
                    '8_80' => 80 * 1.7,
                    '10_100' => 100 * 1.65,
                    '12_120' => 120 * 1.6,
                ];
                $kmEquivalent = $pkgMultipliers[$pkg] ?? (80 * 1.7);
                $baseFare = round($kmEquivalent * $ratePerKm);
                $baseToll = 0; // Local trips typically do not include highway tolls
            } elseif ($tripType === 'airport') {
                $airportDist = $route ? $route->distance_km : 45;
                $baseFare = round($airportDist * $ratePerKm);
                $baseToll = $request->boolean('include_tolls') ? 120 : 0; // Airport toll & parking
            } else {
                if ($route) {
                    $routeCarFare = $route->getFareForCarType($fleet, $ratePerKm);
                    $baseFare = max(0, round($routeCarFare - ($request->boolean('include_tolls') ? $route->base_toll : 0)));
                } else {
                    $baseFare = round($distance * $ratePerKm);
                }
                if ($tripType === 'roundtrip') {
                    $baseFare *= 2;
                    $baseToll *= 1.8;
                    if ($route && $route->roundtrip_discount_pct > 0) {
                        $baseFare = round($baseFare * (1 - ($route->roundtrip_discount_pct / 100)));
                    }
                }
            }
        } else {
            // Bus calculation
            $selectedUnits = $validated['selected_units'] ?? ['L4', 'L5'];
            $berths = SeatLayout::whereIn('berth_number', $selectedUnits)->get();
            $baseFare = $berths->sum('price') ?: 1700;
        }

        $addonFare = 0;
        if ($request->boolean('pet_friendly')) $addonFare += 350;
        if ($request->boolean('night_allowance')) $addonFare += 400;
        if ($request->boolean('luggage_carrier')) $addonFare += 200;

        // Coupon calculation
        $discountFare = 0;
        if (!empty($validated['coupon_code'])) {
            $coupon = Coupon::where('code', strtoupper($validated['coupon_code']))->where('is_active', true)->first();
            if ($coupon) {
                if ($coupon->type === 'percentage') {
                    $calc = ($baseFare + $baseToll + $addonFare) * ($coupon->value / 100);
                    $discountFare = min($calc, $coupon->max_discount ?? $calc);
                } else {
                    $discountFare = $coupon->value;
                }
            }
        }

        $totalAmount = max(0, round($baseFare + $baseToll + $addonFare - $discountFare));
        $otp = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $bookingRef = 'ICW-' . mt_rand(10000, 99999);

        $booking = Booking::create([
            'booking_reference' => $bookingRef,
            'service_type' => $validated['service_type'],
            'trip_type' => $tripType,
            'route_summary' => "{$validated['from_city']} → {$toCity}",
            'pickup_location' => $validated['pickup_location'],
            'drop_location' => $dropLocation,
            'departure_time' => !empty($validated['departure_time']) ? date('Y-m-d H:i:s', strtotime($validated['departure_time'])) : null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'passengers' => $validated['passengers'] ?? [],
            'selected_units' => $validated['selected_units'] ?? [$fleet],
            'base_fare' => $baseFare,
            'toll_fare' => $baseToll,
            'addon_fare' => $addonFare,
            'discount_fare' => $discountFare,
            'total_amount' => $totalAmount,
            'status' => 'Confirmed',
            'start_otp' => $otp,
            'dynamic_fields_data' => $validated['dynamic_fields'] ?? []
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trip booked successfully!',
            'booking' => $booking
        ]);
    }

    public function show($reference)
    {
        $booking = Booking::with('driver')->where('booking_reference', $reference)->firstOrFail();
        return response()->json([
            'success' => true,
            'booking' => $booking
        ]);
    }
}
