<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AirportHub;
use Illuminate\Http\Request;

class AirportHubAdminController extends Controller
{
    /**
     * List all airport transit hubs.
     */
    public function index(Request $request)
    {
        $query = AirportHub::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('airport_name', 'like', "%{$s}%")
                    ->orWhere('iata_code', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        return response()->json([
            'success' => true,
            'airports' => $query->orderBy('city')->get(),
        ]);
    }

    /**
     * Store a new airport transit hub.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'airport_name' => 'required|string|max:150',
            'iata_code' => 'required|string|max:10',
            'city' => 'required|string|max:100',
            'terminals' => 'nullable|string|max:200',
            'pickup_surcharge' => 'nullable|numeric|min:0',
            'drop_surcharge' => 'nullable|numeric|min:0',
            'sedan_base_fare' => 'nullable|numeric|min:0',
            'suv_base_fare' => 'nullable|numeric|min:0',
            'car_fares' => 'nullable|array',
            'default_car_type' => 'nullable|string|max:60',
            'is_active' => 'nullable|boolean',
        ]);

        $airport = AirportHub::create([
            'airport_name' => trim($validated['airport_name']),
            'iata_code' => strtoupper(trim($validated['iata_code'])),
            'city' => trim($validated['city']),
            'terminals' => trim($validated['terminals'] ?? 'Main Terminal') ?: 'Main Terminal',
            'pickup_surcharge' => (float) ($validated['pickup_surcharge'] ?? 250.00),
            'drop_surcharge' => (float) ($validated['drop_surcharge'] ?? 150.00),
            'sedan_base_fare' => (float) ($validated['sedan_base_fare'] ?? 950.00),
            'suv_base_fare' => (float) ($validated['suv_base_fare'] ?? 1450.00),
            'car_fares' => $validated['car_fares'] ?? null,
            'default_car_type' => ! empty($validated['default_car_type']) ? strtolower(trim($validated['default_car_type'])) : null,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Airport Hub '{$airport->airport_name} ({$airport->iata_code})' created successfully!",
            'airport' => $airport,
        ], 201);
    }

    /**
     * Show single airport hub details.
     */
    public function show($id)
    {
        $airport = AirportHub::findOrFail($id);

        return response()->json([
            'success' => true,
            'airport' => $airport,
        ]);
    }

    /**
     * Update an existing airport hub.
     */
    public function update(Request $request, $id)
    {
        $airport = AirportHub::findOrFail($id);

        $validated = $request->validate([
            'airport_name' => 'sometimes|required|string|max:150',
            'iata_code' => 'sometimes|required|string|max:10',
            'city' => 'sometimes|required|string|max:100',
            'terminals' => 'nullable|string|max:200',
            'pickup_surcharge' => 'nullable|numeric|min:0',
            'drop_surcharge' => 'nullable|numeric|min:0',
            'sedan_base_fare' => 'nullable|numeric|min:0',
            'suv_base_fare' => 'nullable|numeric|min:0',
            'car_fares' => 'nullable|array',
            'default_car_type' => 'nullable|string|max:60',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['airport_name'])) {
            $airport->airport_name = trim($validated['airport_name']);
        }
        if (isset($validated['iata_code'])) {
            $airport->iata_code = strtoupper(trim($validated['iata_code']));
        }
        if (isset($validated['city'])) {
            $airport->city = trim($validated['city']);
        }
        if (isset($validated['terminals'])) {
            $airport->terminals = trim($validated['terminals']);
        }
        if (isset($validated['pickup_surcharge'])) {
            $airport->pickup_surcharge = (float) $validated['pickup_surcharge'];
        }
        if (isset($validated['drop_surcharge'])) {
            $airport->drop_surcharge = (float) $validated['drop_surcharge'];
        }
        if (isset($validated['sedan_base_fare'])) {
            $airport->sedan_base_fare = (float) $validated['sedan_base_fare'];
        }
        if (isset($validated['suv_base_fare'])) {
            $airport->suv_base_fare = (float) $validated['suv_base_fare'];
        }
        if (array_key_exists('car_fares', $validated)) {
            $airport->car_fares = $validated['car_fares'];
        }
        if (array_key_exists('default_car_type', $validated)) {
            $airport->default_car_type = ! empty($validated['default_car_type'])
                ? strtolower(trim((string) $validated['default_car_type']))
                : null;
        }
        if ($request->has('is_active')) {
            $airport->is_active = (bool) $request->is_active;
        }

        $airport->save();

        return response()->json([
            'success' => true,
            'message' => "Airport Hub '{$airport->airport_name} ({$airport->iata_code})' updated successfully!",
            'airport' => $airport,
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus($id)
    {
        $airport = AirportHub::findOrFail($id);
        $airport->is_active = ! $airport->is_active;
        $airport->save();

        return response()->json([
            'success' => true,
            'message' => 'Airport status changed to '.($airport->is_active ? 'Active' : 'Inactive'),
            'is_active' => $airport->is_active,
        ]);
    }

    /**
     * Delete an airport hub.
     */
    public function destroy($id)
    {
        $airport = AirportHub::findOrFail($id);
        $name = $airport->airport_name;
        $airport->delete();

        return response()->json([
            'success' => true,
            'message' => "Airport Hub '{$name}' deleted successfully.",
        ]);
    }
}
