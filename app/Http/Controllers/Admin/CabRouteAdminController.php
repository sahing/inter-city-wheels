<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RouteFare;
use Illuminate\Http\Request;

class CabRouteAdminController extends Controller
{
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
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Cab Route {$route->from_city} → {$route->to_city} created successfully!",
            'route' => $route,
        ], 201);
    }

    /**
     * Show single cab route details.
     */
    public function show($id)
    {
        $route = RouteFare::findOrFail($id);

        return response()->json([
            'success' => true,
            'route' => $route,
        ]);
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
