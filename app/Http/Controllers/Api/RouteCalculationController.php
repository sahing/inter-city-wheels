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

        $selectedPriceFields = $request->query('selected_price_fields', []);
        if (is_string($selectedPriceFields)) {
            $selectedPriceFields = array_values(array_filter(array_map('trim', explode(',', $selectedPriceFields))));
        } elseif (! is_array($selectedPriceFields)) {
            $selectedPriceFields = [];
        }

        $options = [
            'include_tolls' => $request->boolean('include_tolls'),
            'pet_friendly' => $request->boolean('pet_friendly'),
            'night_allowance' => $request->boolean('night_allowance'),
            'luggage_carrier' => $request->boolean('luggage_carrier'),
            'coupon_code' => $request->query('coupon'),
            'airport_type' => (string) $request->query('airport_type', 'pickup'),
            'actual_km' => $request->filled('actual_km') ? (int) $request->query('actual_km') : null,
            'extra_km' => $request->filled('extra_km') ? (int) $request->query('extra_km') : null,
            'selected_price_fields' => $selectedPriceFields,
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
        $availablePriceFields = [];
        $routeAdditionalFare = 0.0;
        $routeAdditionalLabel = null;

        foreach ($fleets as $carType) {
            $key = $carType->category_key;
            $quote = $this->fares->quote($trip, $key, [
                'include_tolls' => $options['include_tolls'],
                'actual_km' => $options['actual_km'],
                'extra_km' => $options['extra_km'],
                'selected_price_fields' => $options['selected_price_fields'],
            ]);

            if ($availablePriceFields === []) {
                $availablePriceFields = $quote['available_price_fields'] ?? [];
                $routeAdditionalFare = (float) ($quote['route_additional_fare'] ?? 0.0);
                $routeAdditionalLabel = $quote['route_additional_label'] ?? null;
            }

            $fleetEstimates[$key] = $quote['total_amount'];

            $itemized[$key] = [
                'name' => $carType->name ?? ucfirst($key),
                'rate_per_km' => $quote['rate_per_km'],
                'fixed_base_fare' => $quote['fixed_base_fare'],
                'extra_km_rate' => $quote['extra_km_rate'],
                'extra_hour_rate' => $quote['extra_hour_rate'],
                'distance_km' => $quote['distance_km'],
                'booked_distance_km' => $quote['booked_distance_km'],
                'actual_distance_km' => $quote['actual_distance_km'],
                'extra_km' => $quote['extra_km'],
                'base_fare' => $quote['base_fare'],
                'toll_fare' => $quote['toll_fare'],
                'extra_km_fare' => $quote['extra_km_fare'],
                'route_additional_fare' => $quote['route_additional_fare'],
                'custom_price_fields_fare' => $quote['custom_price_fields_fare'],
                'total' => $quote['total_amount'],
            ];
        }

        $routeModel = $trip['route'] ?? null;

        $payload = [
            'success' => true,
            'route' => [
                'id' => $routeModel?->id,
                'from_city' => $trip['from_city'],
                'to_city' => $trip['to_city'],
                'distance_km' => $trip['distance_km'],
                'estimated_time' => $trip['estimated_time'],
                'base_toll' => $trip['base_toll'],
                'additional_amount' => $routeAdditionalFare,
                'additional_amount_label' => $routeAdditionalLabel,
                'roundtrip_discount_pct' => $trip['roundtrip_discount_pct'],
                'distance_source' => $trip['source'],
                'cached' => (bool) ($trip['cached'] ?? false),
                'beyond_region' => (bool) ($trip['beyond_region'] ?? false),
                'availability_guaranteed' => (bool) ($trip['availability_guaranteed'] ?? true),
                'region_note' => $trip['region_note'] ?? null,
            ],
            'fleet_estimates' => $fleetEstimates,
            'itemized_breakdown' => $itemized,
            'available_price_fields' => $availablePriceFields,
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
