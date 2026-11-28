<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RouteFare;
use App\Services\FareCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CabRouteAdminController extends Controller
{
    /**
     * Calculate highway distance, travel time, and base toll between two cities using
     * the live routing/location API pipeline so admin routes can be auto-filled or adjusted manually.
     */
    public function calculateDistance(Request $request, FareCalculatorService $fares)
    {
        $validated = $request->validate([
            'from_city' => 'required|string|max:100',
            'to_city' => 'required|string|max:100',
            'from_lat' => 'nullable|numeric',
            'from_lng' => 'nullable|numeric',
            'to_lat' => 'nullable|numeric',
            'to_lng' => 'nullable|numeric',
        ]);

        $metrics = $fares->resolveDistanceViaApi(
            $validated,
            $validated['from_city'],
            $validated['to_city']
        );

        if ($metrics === null) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resolve highway distance via API for these locations. Please enter KM manually.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }
    /**
     * List all outstation cab routes.
     */
    public function index(Request $request)
    {
        $query = RouteFare::latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('from_city', 'like', "%{$s}%")
                    ->orWhere('to_city', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        return response()->json([
            'success' => true,
            'routes' => $query->get(),
        ]);
    }

    /**
     * Store a new outstation cab route.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_city' => 'required|string|max:100',
            'to_city' => 'required|string|max:100',
            'distance_km' => 'required|numeric|min:1',
            'estimated_time' => 'required|string|max:50',
            'base_toll' => 'nullable|numeric|min:0',
            'additional_amount' => 'nullable|numeric|min:0',
            'additional_amount_label' => 'nullable|string|max:120',
            'hatchback_fare' => 'nullable|numeric|min:0',
            'sedan_fare' => 'nullable|numeric|min:0',
            'suv_fare' => 'nullable|numeric|min:0',
            'innova_fare' => 'nullable|numeric|min:0',
            'tempo_fare' => 'nullable|numeric|min:0',
            'luxury_fare' => 'nullable|numeric|min:0',
            'car_fares' => 'nullable|array',
            'departure_schedule' => 'nullable|string|max:255',
            'schedule_frequency' => 'nullable|string|max:100',
            'roundtrip_discount_pct' => 'nullable|numeric|min:0|max:100',
            'default_car_type' => 'nullable|string|max:60',
            'from_lat' => 'nullable|numeric',
            'from_lng' => 'nullable|numeric',
            'to_lat' => 'nullable|numeric',
            'to_lng' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        $route = RouteFare::create([
            'from_city' => trim($validated['from_city']),
            'to_city' => trim($validated['to_city']),
            'from_lat' => $validated['from_lat'] ?? 18.5204,
            'from_lng' => $validated['from_lng'] ?? 73.8567,
            'to_lat' => $validated['to_lat'] ?? 15.2993,
            'to_lng' => $validated['to_lng'] ?? 74.1240,
            'distance_km' => round($validated['distance_km']),
            'estimated_time' => trim($validated['estimated_time']),
            'base_toll' => (float) ($validated['base_toll'] ?? 0),
            'additional_amount' => (float) ($validated['additional_amount'] ?? 0),
            'additional_amount_label' => ! empty($validated['additional_amount_label']) ? trim((string) $validated['additional_amount_label']) : null,
            'hatchback_fare' => isset($validated['hatchback_fare']) && $validated['hatchback_fare'] !== '' ? (float) $validated['hatchback_fare'] : null,
            'sedan_fare' => isset($validated['sedan_fare']) && $validated['sedan_fare'] !== '' ? (float) $validated['sedan_fare'] : null,
            'suv_fare' => isset($validated['suv_fare']) && $validated['suv_fare'] !== '' ? (float) $validated['suv_fare'] : null,
            'innova_fare' => isset($validated['innova_fare']) && $validated['innova_fare'] !== '' ? (float) $validated['innova_fare'] : null,
            'tempo_fare' => isset($validated['tempo_fare']) && $validated['tempo_fare'] !== '' ? (float) $validated['tempo_fare'] : null,
            'luxury_fare' => isset($validated['luxury_fare']) && $validated['luxury_fare'] !== '' ? (float) $validated['luxury_fare'] : null,
            'car_fares' => $validated['car_fares'] ?? null,
            'departure_schedule' => ! empty($validated['departure_schedule']) ? trim($validated['departure_schedule']) : null,
            'schedule_frequency' => ! empty($validated['schedule_frequency']) ? trim($validated['schedule_frequency']) : 'Daily Express',
            'roundtrip_discount_pct' => (float) ($validated['roundtrip_discount_pct'] ?? 0),
            'default_car_type' => ! empty($validated['default_car_type']) ? strtolower(trim($validated['default_car_type'])) : null,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Cab Route {$route->from_city} → {$route->to_city} created successfully!",
            'route' => $route,
        ], 201);
    }

    /**
     * Show single cab route details along with live customer-visible fare preview.
     */
    public function show($id, FareCalculatorService $fares)
    {
        $route = RouteFare::findOrFail($id);

        return response()->json([
            'success' => true,
            'route' => $route,
            'customer_fare_preview' => $this->buildCustomerFarePreview($route, $fares),
        ]);
    }

    /**
     * Live-calculate the exact fare a customer will see for current modal inputs
     * (including One-Way & Round-Trip, KM formulas, custom overrides, tolls,
     * route additional charges, driver allowance, auto price fields, and auto promos).
     */
    public function previewFares(Request $request, FareCalculatorService $fares)
    {
        $validated = $request->validate([
            'route_id' => 'nullable|integer',
            'from_city' => 'nullable|string|max:100',
            'to_city' => 'nullable|string|max:100',
            'distance_km' => 'required|numeric|min:1',
            'estimated_time' => 'nullable|string|max:50',
            'base_toll' => 'nullable|numeric|min:0',
            'additional_amount' => 'nullable|numeric|min:0',
            'additional_amount_label' => 'nullable|string|max:120',
            'hatchback_fare' => 'nullable|numeric|min:0',
            'sedan_fare' => 'nullable|numeric|min:0',
            'suv_fare' => 'nullable|numeric|min:0',
            'innova_fare' => 'nullable|numeric|min:0',
            'tempo_fare' => 'nullable|numeric|min:0',
            'luxury_fare' => 'nullable|numeric|min:0',
            'car_fares' => 'nullable|array',
            'roundtrip_discount_pct' => 'nullable|numeric|min:0|max:100',
            'default_car_type' => 'nullable|string|max:60',
        ]);

        $route = new RouteFare([
            'from_city' => trim((string) ($validated['from_city'] ?? 'Origin')) ?: 'Origin',
            'to_city' => trim((string) ($validated['to_city'] ?? 'Destination')) ?: 'Destination',
            'distance_km' => (int) round((float) $validated['distance_km']),
            'estimated_time' => trim((string) ($validated['estimated_time'] ?? '—')) ?: '—',
            'base_toll' => (float) ($validated['base_toll'] ?? 0),
            'additional_amount' => (float) ($validated['additional_amount'] ?? 0),
            'additional_amount_label' => ! empty($validated['additional_amount_label']) ? trim((string) $validated['additional_amount_label']) : null,
            'hatchback_fare' => isset($validated['hatchback_fare']) && $validated['hatchback_fare'] !== '' ? (float) $validated['hatchback_fare'] : null,
            'sedan_fare' => isset($validated['sedan_fare']) && $validated['sedan_fare'] !== '' ? (float) $validated['sedan_fare'] : null,
            'suv_fare' => isset($validated['suv_fare']) && $validated['suv_fare'] !== '' ? (float) $validated['suv_fare'] : null,
            'innova_fare' => isset($validated['innova_fare']) && $validated['innova_fare'] !== '' ? (float) $validated['innova_fare'] : null,
            'tempo_fare' => isset($validated['tempo_fare']) && $validated['tempo_fare'] !== '' ? (float) $validated['tempo_fare'] : null,
            'luxury_fare' => isset($validated['luxury_fare']) && $validated['luxury_fare'] !== '' ? (float) $validated['luxury_fare'] : null,
            'car_fares' => $validated['car_fares'] ?? null,
            'roundtrip_discount_pct' => (float) ($validated['roundtrip_discount_pct'] ?? 0),
            'default_car_type' => ! empty($validated['default_car_type']) ? strtolower(trim((string) $validated['default_car_type'])) : null,
            'is_active' => true,
        ]);

        if (! empty($validated['route_id'])) {
            $route->id = (int) $validated['route_id'];
        }

        return response()->json([
            'success' => true,
            'customer_fare_preview' => $this->buildCustomerFarePreview($route, $fares),
        ]);
    }

    /**
     * Compute One-Way and Round-Trip customer quotes across all active fleets for a RouteFare.
     *
     * @return array<string, mixed>
     */
    private function buildCustomerFarePreview(RouteFare $route, FareCalculatorService $fares): array
    {
        $oneWayTrip = [
            'trip_type' => 'oneway',
            'from_city' => $route->from_city ?: 'Origin',
            'to_city' => $route->to_city ?: 'Destination',
            'distance_km' => max(0, (int) $route->distance_km),
            'estimated_time' => $route->estimated_time ?: '—',
            'base_toll' => (float) ($route->base_toll ?? 0),
            'roundtrip_discount_pct' => (float) ($route->roundtrip_discount_pct ?? 0),
            'source' => 'route_table',
            'local_package' => null,
            'route' => $route,
            'airport_hub' => null,
            'beyond_region' => false,
            'availability_guaranteed' => true,
            'region_note' => null,
        ];
        $roundTrip = array_merge($oneWayTrip, ['trip_type' => 'roundtrip']);
        $quoteOptions = [
            'include_tolls' => false,
            'user_id' => null,
        ];

        $preferredKey = strtolower(trim((string) ($route->default_car_type ?? '')));
        $conditions = $fares->pricingConditions();
        $fleets = [];

        foreach ($fares->fleetCatalogue() as $carType) {
            $key = strtolower((string) $carType->category_key);
            $ow = $fares->quote($oneWayTrip, $key, $quoteOptions);
            $rt = $fares->quote($roundTrip, $key, $quoteOptions);

            $fleets[] = [
                'category_key' => $key,
                'name' => $carType->name ?? ucfirst($key),
                'seats' => (int) ($carType->seats ?? 4),
                'is_preferred' => $preferredKey !== '' && $preferredKey === $key,
                'has_custom_route_fare' => $route->hasCustomFareForCarType($key),
                'oneway' => [
                    'total_amount' => $ow['total_amount'],
                    'subtotal' => $ow['subtotal'],
                    'base_fare' => $ow['base_fare'],
                    'rate_per_km' => $ow['rate_per_km'],
                    'fixed_base_fare' => $ow['fixed_base_fare'],
                    'extra_km_rate' => $ow['extra_km_rate'],
                    'route_additional_fare' => $ow['route_additional_fare'],
                    'addon_fare' => $ow['addon_fare'],
                    'custom_price_fields_fare' => $ow['custom_price_fields_fare'],
                    'discount_fare' => $ow['discount_fare'],
                    'coupon' => $ow['coupon'],
                ],
                'roundtrip' => [
                    'total_amount' => $rt['total_amount'],
                    'subtotal' => $rt['subtotal'],
                    'base_fare' => $rt['base_fare'],
                    'rate_per_km' => $rt['rate_per_km'],
                    'fixed_base_fare' => $rt['fixed_base_fare'],
                    'extra_km_rate' => $rt['extra_km_rate'],
                    'route_additional_fare' => $rt['route_additional_fare'],
                    'addon_fare' => $rt['addon_fare'],
                    'custom_price_fields_fare' => $rt['custom_price_fields_fare'],
                    'discount_fare' => $rt['discount_fare'],
                    'coupon' => $rt['coupon'],
                ],
            ];
        }

        return [
            'from_city' => $oneWayTrip['from_city'],
            'to_city' => $oneWayTrip['to_city'],
            'distance_km' => $oneWayTrip['distance_km'],
            'roundtrip_distance_km' => $oneWayTrip['distance_km'] * 2,
            'base_toll' => (float) ($route->base_toll ?? 0),
            'additional_amount' => (float) ($route->additional_amount ?? 0),
            'additional_amount_label' => $route->additional_amount_label,
            'roundtrip_discount_pct' => (float) ($route->roundtrip_discount_pct ?? 0),
            'driver_day_allowance' => (float) ($conditions['driver_day_allowance'] ?? 0),
            'default_car_type' => $preferredKey !== '' ? $preferredKey : null,
            'fleets' => $fleets,
        ];
    }

    /**
     * Update an existing cab route.
     */
    public function update(Request $request, $id)
    {
        $route = RouteFare::findOrFail($id);

        $validated = $request->validate([
            'from_city' => 'sometimes|required|string|max:100',
            'to_city' => 'sometimes|required|string|max:100',
            'distance_km' => 'sometimes|required|numeric|min:1',
            'estimated_time' => 'sometimes|required|string|max:50',
            'base_toll' => 'nullable|numeric|min:0',
            'additional_amount' => 'nullable|numeric|min:0',
            'additional_amount_label' => 'nullable|string|max:120',
            'hatchback_fare' => 'nullable|numeric|min:0',
            'sedan_fare' => 'nullable|numeric|min:0',
            'suv_fare' => 'nullable|numeric|min:0',
            'innova_fare' => 'nullable|numeric|min:0',
            'tempo_fare' => 'nullable|numeric|min:0',
            'luxury_fare' => 'nullable|numeric|min:0',
            'car_fares' => 'nullable|array',
            'departure_schedule' => 'nullable|string|max:255',
            'schedule_frequency' => 'nullable|string|max:100',
            'roundtrip_discount_pct' => 'nullable|numeric|min:0|max:100',
            'default_car_type' => 'nullable|string|max:60',
            'from_lat' => 'nullable|numeric',
            'from_lng' => 'nullable|numeric',
            'to_lat' => 'nullable|numeric',
            'to_lng' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['distance_km'])) {
            $validated['distance_km'] = round($validated['distance_km']);
        }

        foreach (['hatchback_fare', 'sedan_fare', 'suv_fare', 'innova_fare', 'tempo_fare', 'luxury_fare'] as $fareKey) {
            if (array_key_exists($fareKey, $validated) && $validated[$fareKey] === '') {
                $validated[$fareKey] = null;
            }
        }

        if (array_key_exists('default_car_type', $validated)) {
            $validated['default_car_type'] = ! empty($validated['default_car_type'])
                ? strtolower(trim((string) $validated['default_car_type']))
                : null;
        }
        if (array_key_exists('additional_amount', $validated)) {
            $validated['additional_amount'] = (float) ($validated['additional_amount'] ?? 0);
        }
        if (array_key_exists('additional_amount_label', $validated)) {
            $validated['additional_amount_label'] = ! empty($validated['additional_amount_label'])
                ? trim((string) $validated['additional_amount_label'])
                : null;
        }

        $route->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Cab Route {$route->from_city} → {$route->to_city} updated successfully!",
            'route' => $route,
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus($id)
    {
        $route = RouteFare::findOrFail($id);
        $route->is_active = ! $route->is_active;
        $route->save();

        return response()->json([
            'success' => true,
            'message' => 'Route status updated to '.($route->is_active ? 'Active' : 'Inactive'),
            'route' => $route,
        ]);
    }

    /**
     * Bulk update outstation cab route tolls, roundtrip discounts, or fixed car fare overrides.
     */
    public function bulkAdjust(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:use_car_type_rates,set_roundtrip_discount,percent_adjust,inline_save',
            'roundtrip_discount_pct' => 'nullable|numeric|min:0|max:100',
            'percent' => 'nullable|numeric|min:-90|max:500',
            'routes' => 'nullable|array',
            'routes.*.id' => 'required_with:routes|integer|exists:route_fares,id',
            'routes.*.base_toll' => 'nullable|numeric|min:0',
            'routes.*.additional_amount' => 'nullable|numeric|min:0',
            'routes.*.additional_amount_label' => 'nullable|string|max:120',
            'routes.*.roundtrip_discount_pct' => 'nullable|numeric|min:0|max:100',
            'routes.*.hatchback_fare' => 'nullable|numeric|min:0',
            'routes.*.sedan_fare' => 'nullable|numeric|min:0',
            'routes.*.suv_fare' => 'nullable|numeric|min:0',
            'routes.*.innova_fare' => 'nullable|numeric|min:0',
            'routes.*.tempo_fare' => 'nullable|numeric|min:0',
            'routes.*.luxury_fare' => 'nullable|numeric|min:0',
        ]);

        $fareCols = ['hatchback_fare', 'sedan_fare', 'suv_fare', 'innova_fare', 'tempo_fare', 'luxury_fare'];
        $action = $validated['action'];

        $message = DB::transaction(function () use ($action, $validated, $fareCols) {
            if ($action === 'use_car_type_rates') {
                $nulls = array_fill_keys($fareCols, null);
                $nulls['car_fares'] = null;
                $affected = RouteFare::query()->update($nulls);

                return "Cleared custom route fare overrides across {$affected} route(s); live Car Type per-km rates now apply.";
            }

            if ($action === 'set_roundtrip_discount') {
                $pct = (float) ($validated['roundtrip_discount_pct'] ?? 0);
                $affected = RouteFare::query()->update(['roundtrip_discount_pct' => $pct]);

                return "Set round-trip return discount to {$pct}% across {$affected} route(s).";
            }

            if ($action === 'percent_adjust') {
                $pct = (float) ($validated['percent'] ?? 0);
                $multiplier = 1 + ($pct / 100);
                $routes = RouteFare::all();
                $updated = 0;

                foreach ($routes as $route) {
                    $dirty = false;
                    foreach ($fareCols as $col) {
                        if ($route->{$col} !== null && (float) $route->{$col} > 0) {
                            $route->{$col} = round((float) $route->{$col} * $multiplier);
                            $dirty = true;
                        }
                    }
                    if ($dirty) {
                        $route->save();
                        $updated++;
                    }
                }

                return "Adjusted custom fixed fares by {$pct}% on {$updated} route(s).";
            }

            // inline_save
            $rows = $validated['routes'] ?? [];
            foreach ($rows as $row) {
                $route = RouteFare::findOrFail($row['id']);
                if (array_key_exists('base_toll', $row) && $row['base_toll'] !== null) {
                    $route->base_toll = (float) $row['base_toll'];
                }
                if (array_key_exists('additional_amount', $row)) {
                    $route->additional_amount = ($row['additional_amount'] === null || $row['additional_amount'] === '') ? 0.0 : (float) $row['additional_amount'];
                }
                if (array_key_exists('additional_amount_label', $row)) {
                    $route->additional_amount_label = ! empty($row['additional_amount_label']) ? trim((string) $row['additional_amount_label']) : null;
                }
                if (array_key_exists('roundtrip_discount_pct', $row) && $row['roundtrip_discount_pct'] !== null) {
                    $route->roundtrip_discount_pct = (float) $row['roundtrip_discount_pct'];
                }
                foreach ($fareCols as $col) {
                    if (array_key_exists($col, $row)) {
                        $route->{$col} = ($row[$col] === null || $row[$col] === '') ? null : (float) $row[$col];
                    }
                }
                $route->save();
            }

            return 'Saved inline changes for '.count($rows).' route(s).';
        });

        return response()->json([
            'success' => true,
            'message' => $message,
            'routes' => RouteFare::latest()->get(),
        ]);
    }

    /**
     * Delete a cab route.
     */
    public function destroy($id)
    {
        $route = RouteFare::findOrFail($id);
        $summary = "{$route->from_city} → {$route->to_city}";
        $route->delete();

        return response()->json([
            'success' => true,
            'message' => "Cab Route {$summary} deleted successfully!",
        ]);
    }
}
