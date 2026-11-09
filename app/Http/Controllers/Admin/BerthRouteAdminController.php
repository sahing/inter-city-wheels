<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BerthRoute;

class BerthRouteAdminController extends Controller
{
    public function index()
    {
        $routes = BerthRoute::with('seatLayouts')->latest()->get();
        return response()->json([
            'success' => true,
            'routes' => $routes
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_city' => 'required|string|max:80',
            'to_city' => 'required|string|max:80',
            'operator_name' => 'required|string|max:120',
            'coach_code' => 'required|string|max:30',
            'vehicle_type' => 'required|string|max:100',
            'departure_time' => 'required|string|max:40',
            'arrival_time' => 'required|string|max:40',
            'base_price' => 'required|numeric|min:50',
            'total_berths' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $route = BerthRoute::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Berth route '{$route->from_city} → {$route->to_city}' ({$route->coach_code}) created successfully!",
            'route' => $route
        ]);
    }

    public function show($id)
    {
        $route = BerthRoute::with('seatLayouts')->findOrFail($id);
        return response()->json([
            'success' => true,
            'route' => $route
        ]);
    }

    public function update(Request $request, $id)
    {
        $route = BerthRoute::findOrFail($id);

        $validated = $request->validate([
            'from_city' => 'required|string|max:80',
            'to_city' => 'required|string|max:80',
            'operator_name' => 'required|string|max:120',
            'coach_code' => 'required|string|max:30',
            'vehicle_type' => 'required|string|max:100',
            'departure_time' => 'required|string|max:40',
            'arrival_time' => 'required|string|max:40',
            'base_price' => 'required|numeric|min:50',
            'total_berths' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', $route->is_active);

        $route->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Berth route updated successfully!",
            'route' => $route
        ]);
    }

    public function toggleStatus($id)
    {
        $route = BerthRoute::findOrFail($id);
        $route->update(['is_active' => !$route->is_active]);

        return response()->json([
            'success' => true,
            'message' => "Route status updated to " . ($route->is_active ? 'Active' : 'Inactive'),
            'route' => $route
        ]);
    }

    public function destroy($id)
    {
        $route = BerthRoute::findOrFail($id);
        $summary = "{$route->from_city} → {$route->to_city} ({$route->coach_code})";
        $route->delete();

        return response()->json([
            'success' => true,
            'message' => "Berth route '{$summary}' deleted successfully!"
        ]);
    }
}

