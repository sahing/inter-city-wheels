<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingTypeCarRate;
use App\Models\CarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingTypeRateAdminController extends Controller
{
    /**
     * Return all Car Types along with per-booking-type bulk rates matrix.
     */
    public function index()
    {
        $carTypes = CarType::orderBy('order_index')->get();
        $rates = BookingTypeCarRate::all();

        $grouped = [];
        foreach (BookingTypeCarRate::BOOKING_TYPES as $type) {
            $grouped[$type] = [];
        }

        foreach ($rates as $rate) {
            $grouped[$rate->booking_type][$rate->car_type_id] = $rate;
        }

        return response()->json([
            'success' => true,
            'car_types' => $carTypes,
            'booking_types' => BookingTypeCarRate::BOOKING_TYPES,
            'rates_by_type' => $grouped,
            'rates' => $rates,
        ]);
    }

    /**
     * Bulk save rates for one booking type (or reset non-oneway overrides back to fallback defaults).
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'booking_type' => 'required|string|in:oneway,roundtrip,local,airport_pickup,airport_drop',
            'action' => 'nullable|string|in:save,reset',
            'rates' => 'nullable|array',
            'rates.*.car_type_id' => 'required_with:rates|integer|exists:car_types,id',
            'rates.*.base_fare' => 'required_with:rates|numeric|min:0',
            'rates.*.rate_per_km' => 'required_with:rates|numeric|min:0',
            'rates.*.extra_km_rate' => 'nullable|numeric|min:0',
            'rates.*.extra_hour_rate' => 'nullable|numeric|min:0',
            'rates.*.is_active' => 'nullable|boolean',
        ]);

        $bookingType = $validated['booking_type'];
        $action = $validated['action'] ?? 'save';

        if ($action === 'reset') {
            if ($bookingType === 'oneway') {
                return response()->json([
                    'success' => false,
                    'message' => 'One-Way is the primary Car Type base rate table and cannot be reset.',
                ], 422);
            }

            $deleted = BookingTypeCarRate::where('booking_type', $bookingType)->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$deleted} custom {$this->labelFor($bookingType)} rate(s); fallback defaults restored.",
                'booking_type' => $bookingType,
                'car_types' => CarType::orderBy('order_index')->get(),
                'rates' => BookingTypeCarRate::all(),
            ]);
        }

        $rows = $validated['rates'] ?? [];
        if ($rows === []) {
            return response()->json([
                'success' => false,
                'message' => 'Provide at least one car type rate row to save.',
            ], 422);
        }

        DB::transaction(function () use ($bookingType, $rows) {
            foreach ($rows as $row) {
                $carType = CarType::findOrFail($row['car_type_id']);
                $baseFare = (float) $row['base_fare'];
                $ratePerKm = (float) $row['rate_per_km'];
                $extraKmRate = array_key_exists('extra_km_rate', $row) && $row['extra_km_rate'] !== null
                    ? (float) $row['extra_km_rate']
                    : $ratePerKm;
                $extraHourRate = array_key_exists('extra_hour_rate', $row) && $row['extra_hour_rate'] !== null
                    ? (float) $row['extra_hour_rate']
                    : 0.0;
                $isActive = array_key_exists('is_active', $row)
                    ? (bool) $row['is_active']
                    : (bool) $carType->is_active;

                BookingTypeCarRate::updateOrCreate(
                    [
                        'booking_type' => $bookingType,
                        'car_type_id' => $carType->id,
                    ],
                    [
                        'base_fare' => $baseFare,
                        'rate_per_km' => $ratePerKm,
                        'extra_km_rate' => $extraKmRate,
                        'extra_hour_rate' => $extraHourRate,
                        'is_active' => $isActive,
                    ]
                );

                // Keep primary CarType table synchronized with One-Way rates.
                if ($bookingType === 'oneway') {
                    $carType->base_fare_min = $baseFare;
                    $carType->rate_per_km = max(1.0, $ratePerKm);
                    $carType->extra_km_rate = $extraKmRate;
                    if (array_key_exists('is_active', $row)) {
                        $carType->is_active = $isActive;
                    }
                    $carType->save();
                }
            }
        });

        $count = count($rows);

        return response()->json([
            'success' => true,
            'message' => "Saved {$this->labelFor($bookingType)} bulk rates for {$count} car type(s)!",
            'booking_type' => $bookingType,
            'car_types' => CarType::orderBy('order_index')->get(),
            'rates' => BookingTypeCarRate::all(),
        ]);
    }

    private function labelFor(string $bookingType): string
    {
        return match ($bookingType) {
            'oneway' => 'One-Way',
            'roundtrip' => 'Round-Trip',
            'local' => 'Local Hourly',
            'airport_pickup' => 'Airport Pickup',
            'airport_drop' => 'Airport Drop',
            default => ucfirst($bookingType),
        };
    }
}
