<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarType;
use Illuminate\Http\Request;

class CarTypeAdminController extends Controller
{
    /**
     * List all car types / fleet categories.
     */
    public function index(Request $request)
    {
        $query = CarType::orderBy('order_index')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('category_key', 'like', "%{$s}%")
                    ->orWhere('models', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        return response()->json([
            'success' => true,
            'car_types' => $query->get(),
        ]);
    }

    /**
     * Store a new car type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'category_key' => 'required|string|max:60|unique:car_types,category_key',
            'badge' => 'nullable|string|max:60',
            'models' => 'required|string|max:255',
            'seats' => 'required|max:40',
            'luggage' => 'nullable|max:40',
            'rate_per_km' => 'required|numeric|min:1',
            'extra_km_rate' => 'nullable|numeric|min:0',
            'base_fare_min' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
            'order_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $categoryKey = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['category_key']));

        $carType = CarType::create([
            'name' => trim($validated['name']),
            'category_key' => $categoryKey,
            'badge' => $validated['badge'] ? trim($validated['badge']) : 'Popular',
            'models' => trim($validated['models']),
            'seats' => trim($validated['seats']),
            'luggage' => trim($validated['luggage']),
            'rate_per_km' => (float) $validated['rate_per_km'],
            'extra_km_rate' => isset($validated['extra_km_rate']) ? (float) $validated['extra_km_rate'] : (float) $validated['rate_per_km'],
            'base_fare_min' => (float) ($validated['base_fare_min'] ?? 800),
            'image_url' => $validated['image_url'] ?? 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80',
            'order_index' => (int) ($validated['order_index'] ?? 0),
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$carType->name}' added successfully!",
            'car_type' => $carType,
        ], 201);
    }

    /**
     * Show single car type.
     */
    public function show($id)
    {
        $carType = CarType::findOrFail($id);

        return response()->json([
            'success' => true,
            'car_type' => $carType,
        ]);
    }

    /**
     * Update an existing car type.
     */
    public function update(Request $request, $id)
    {
        $carType = CarType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'category_key' => 'sometimes|required|string|max:60|unique:car_types,category_key,'.$id,
            'badge' => 'nullable|string|max:60',
            'models' => 'sometimes|required|string|max:255',
            'seats' => 'sometimes|required|max:40',
            'luggage' => 'nullable|max:40',
            'rate_per_km' => 'sometimes|required|numeric|min:1',
            'extra_km_rate' => 'nullable|numeric|min:0',
            'base_fare_min' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
            'order_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['category_key'])) {
            $validated['category_key'] = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['category_key']));
        }

        $carType->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$carType->name}' updated successfully!",
            'car_type' => $carType,
        ]);
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus($id)
    {
        $carType = CarType::findOrFail($id);
        $carType->is_active = ! $carType->is_active;
        $carType->save();

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$carType->name}' is now ".($carType->is_active ? 'Active' : 'Inactive'),
            'car_type' => $carType,
        ]);
    }

    /**
     * Delete car type.
     */
    public function destroy($id)
    {
        $carType = CarType::findOrFail($id);
        $name = $carType->name;
        $carType->delete();

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$name}' deleted successfully!",
        ]);
    }
}
