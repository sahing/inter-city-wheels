<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\RouteFare;
use App\Models\SeatLayout;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|in:cab,bus',
            'trip_type' => 'nullable|string',
            'from_city' => 'required|string',
            'to_city' => 'required|string',
            'pickup_location' => 'required|string',
            'drop_location' => 'required|string',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'required|string|max:20',
            'passengers' => 'nullable|array',
            'selected_units' => 'nullable|array',
            'fleet_category' => 'nullable|string',
            'include_tolls' => 'nullable|boolean',
            'pet_friendly' => 'nullable|boolean',
            'night_allowance' => 'nullable|boolean',
            'coupon_code' => 'nullable|string',
            'dynamic_fields' => 'nullable|array'
        ]);

        $route = RouteFare::where('from_city', $validated['from_city'])
            ->where('to_city', $validated['to_city'])
            ->first();

        $distance = $route ? $route->distance_km : 450;
        $baseToll = ($request->boolean('include_tolls') && $route) ? $route->base_toll : 0;

        $rates = [
            'sedan' => 12.5,
            'prime' => 15.0,
            'suv' => 18.0,
            'luxury' => 32.0
        ];

        $fleet = $validated['fleet_category'] ?? 'suv';
        $ratePerKm = $rates[$fleet] ?? 18.0;

        $baseFare = 0;
        if ($validated['service_type'] === 'cab') {
            $baseFare = round($distance * $ratePerKm);
            if ($validated['trip_type'] === 'roundtrip') {
                $baseFare *= 2;
                $baseToll *= 1.8;
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
            'trip_type' => $validated['trip_type'] ?? 'oneway',
            'route_summary' => "{$validated['from_city']} → {$validated['to_city']}",
            'pickup_location' => $validated['pickup_location'],
            'drop_location' => $validated['drop_location'],
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
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
        $booking = Booking::where('booking_reference', $reference)->firstOrFail();
        return response()->json([
            'success' => true,
            'booking' => $booking
        ]);
    }
}
