<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DistanceUnresolvable;
use App\Http\Controllers\Controller;
use App\Services\FareCalculatorService;
use Illuminate\Http\Request;

class RouteCalculationController extends Controller
{
    public function __construct(private readonly FareCalculatorService $fares) {}

    /**
     * Quote a trip.
     *
     * P1-2: this endpoint used to carry its own copy of the pricing formula
     * while the booking endpoint carried another and the browser a third. It is
     * now a thin projection of FareCalculatorService, which is also what the
     * booking endpoint charges, so a quote can no longer disagree with the
     * invoice.
     */
    public function calculate(Request $request)
    {
        $from = trim((string) $request->query('from', 'Kolkata'));
        $to = trim((string) $request->query('to', 'Digha'));
        $tripType = (string) $request->query('trip_type', 'oneway');

        if (! in_array($tripType, ['oneway', 'roundtrip', 'local', 'airport'], true)) {
            $tripType = 'oneway';
        }

        $options = [
            'include_tolls' => $request->boolean('include_tolls'),
            'pet_friendly' => $request->boolean('pet_friendly'),
            'night_allowance' => $request->boolean('night_allowance'),
            'luggage_carrier' => $request->boolean('luggage_carrier'),
            'coupon_code' => $request->query('coupon'),
            'airport_type' => (string) $request->query('airport_type', 'pickup'),
        ];

        try {
            $trip = $this->fares->resolveTrip([
                'from_city' => $from,
                'to_city' => $to,
                'trip_type' => $tripType,
                'local_package' => $request->query('local_package'),
                'airport_type' => $options['airport_type'],
                'from_lat' => $request->filled('from_lat') ? (float) $request->query('from_lat') : null,
                'from_lng' => $request->filled('from_lng') ? (float) $request->query('from_lng') : null,
                'to_lat' => $request->filled('to_lat') ? (float) $request->query('to_lat') : null,
                'to_lng' => $request->filled('to_lng') ? (float) $request->query('to_lng') : null,
            ]);
        } catch (DistanceUnresolvable $e) {
            return response()->json([
                'success' => false,
                'code' => 'distance_unresolvable',
                'message' => $e->getMessage(),
            ] + $e->toArray(), 422);
        }

        $fleets = $this->fares->fleetCatalogue();

        $fleetEstimates = [];
        $itemized = [];

        foreach ($fleets as $carType) {
            $key = $carType->category_key;
            $quote = $this->fares->quote($trip, $key, [
                'include_tolls' => $options['include_tolls'],
            ]);

            $fleetEstimates[$key] = $quote['total_amount'];

            $itemized[$key] = [
                'name' => $carType->name ?? ucfirst($key),
                'rate_per_km' => $quote['rate_per_km'],
                'distance_km' => $quote['distance_km'],
                'base_fare' => $quote['base_fare'],
                'toll_fare' => $quote['toll_fare'],
                'total' => $quote['total_amount'],
            ];
        }

        $payload = [
            'success' => true,
            'route' => [
                'from_city' => $trip['from_city'],
                'to_city' => $trip['to_city'],
                'distance_km' => $trip['distance_km'],
                'estimated_time' => $trip['estimated_time'],
                'base_toll' => $trip['base_toll'],
                'roundtrip_discount_pct' => $trip['roundtrip_discount_pct'],
                'distance_source' => $trip['source'],
                'cached' => (bool) ($trip['cached'] ?? false),
                'beyond_region' => (bool) ($trip['beyond_region'] ?? false),
                'availability_guaranteed' => (bool) ($trip['availability_guaranteed'] ?? true),
                'region_note' => $trip['region_note'] ?? null,
            ],
            'fleet_estimates' => $fleetEstimates,
            'itemized_breakdown' => $itemized,
            'addons' => $this->fares->addonRates(),
            'conditions' => $this->fares->pricingConditions(),
        ];

        // Optional full itemisation for one fleet, including add-ons and coupon.
        $selectedFleet = $request->query('fleet');

        if (is_string($selectedFleet) && $selectedFleet !== '') {
            $payload['quote'] = $this->fares->quote($trip, $selectedFleet, $options);
        }

        return response()->json($payload);
    }
}
