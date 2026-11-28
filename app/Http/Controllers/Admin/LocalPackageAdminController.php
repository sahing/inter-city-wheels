<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocalPackageAdminController extends Controller
{
    /**
     * List all local rental packages.
     */
    public function index(Request $request)
    {
        $query = LocalPackage::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('package_code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        return response()->json([
            'success' => true,
            'packages' => $query->orderBy('duration_hours')->get(),
        ]);
    }

    /**
     * Store a new local rental package.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_code' => 'required|string|max:50|unique:local_packages,package_code',
            'city' => 'nullable|string|max:100',
            'name' => 'required|string|max:150',
            'duration_hours' => 'required|integer|min:1',
            'included_km' => 'required|integer|min:1',
            'sedan_fare' => 'required|numeric|min:0',
            'suv_fare' => 'required|numeric|min:0',
            'car_fares' => 'nullable|array',
            'extra_km_rate' => 'nullable|numeric|min:0',
            'extra_hour_rate' => 'nullable|numeric|min:0',
            'default_car_type' => 'nullable|string|max:60',
            'is_active' => 'nullable|boolean',
        ]);

        $package = LocalPackage::create([
            'package_code' => trim($validated['package_code']),
            'city' => trim($validated['city'] ?? 'All Cities') ?: 'All Cities',
            'name' => trim($validated['name']),
            'duration_hours' => (int) $validated['duration_hours'],
            'included_km' => (int) $validated['included_km'],
            'sedan_fare' => (float) $validated['sedan_fare'],
            'suv_fare' => (float) $validated['suv_fare'],
            'car_fares' => $validated['car_fares'] ?? null,
            'extra_km_rate' => (float) ($validated['extra_km_rate'] ?? 14.00),
            'extra_hour_rate' => (float) ($validated['extra_hour_rate'] ?? 150.00),
            'default_car_type' => ! empty($validated['default_car_type']) ? strtolower(trim($validated['default_car_type'])) : null,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Local Package '{$package->name}' created successfully!",
            'package' => $package,
        ], 201);
    }

    /**
     * Show single package details.
     */
    public function show($id)
    {
        $package = LocalPackage::findOrFail($id);

        return response()->json([
            'success' => true,
            'package' => $package,
        ]);
    }

    /**
     * Update an existing package.
     */
    public function update(Request $request, $id)
    {
        $package = LocalPackage::findOrFail($id);

        $validated = $request->validate([
            'package_code' => "sometimes|required|string|max:50|unique:local_packages,package_code,{$id}",
            'city' => 'nullable|string|max:100',
            'name' => 'sometimes|required|string|max:150',
            'duration_hours' => 'sometimes|required|integer|min:1',
            'included_km' => 'sometimes|required|integer|min:1',
            'sedan_fare' => 'sometimes|required|numeric|min:0',
            'suv_fare' => 'sometimes|required|numeric|min:0',
            'car_fares' => 'nullable|array',
            'extra_km_rate' => 'nullable|numeric|min:0',
            'extra_hour_rate' => 'nullable|numeric|min:0',
            'default_car_type' => 'nullable|string|max:60',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['package_code'])) {
            $package->package_code = trim($validated['package_code']);
        }
        if (isset($validated['city'])) {
            $package->city = trim($validated['city']) ?: 'All Cities';
        }
        if (isset($validated['name'])) {
            $package->name = trim($validated['name']);
        }
        if (isset($validated['duration_hours'])) {
            $package->duration_hours = (int) $validated['duration_hours'];
        }
        if (isset($validated['included_km'])) {
            $package->included_km = (int) $validated['included_km'];
        }
        if (isset($validated['sedan_fare'])) {
            $package->sedan_fare = (float) $validated['sedan_fare'];
        }
        if (isset($validated['suv_fare'])) {
            $package->suv_fare = (float) $validated['suv_fare'];
        }
        if (array_key_exists('car_fares', $validated)) {
            $package->car_fares = $validated['car_fares'];
        }
        if (isset($validated['extra_km_rate'])) {
            $package->extra_km_rate = (float) $validated['extra_km_rate'];
        }
        if (isset($validated['extra_hour_rate'])) {
            $package->extra_hour_rate = (float) $validated['extra_hour_rate'];
        }
        if (array_key_exists('default_car_type', $validated)) {
            $package->default_car_type = ! empty($validated['default_car_type'])
                ? strtolower(trim((string) $validated['default_car_type']))
                : null;
        }
        if ($request->has('is_active')) {
            $package->is_active = (bool) $request->is_active;
        }

        $package->save();

        return response()->json([
            'success' => true,
            'message' => "Local Package '{$package->name}' updated successfully!",
            'package' => $package,
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus($id)
    {
        $package = LocalPackage::findOrFail($id);
        $package->is_active = ! $package->is_active;
        $package->save();

        return response()->json([
            'success' => true,
            'message' => 'Local Package status changed to '.($package->is_active ? 'Active' : 'Inactive'),
            'is_active' => $package->is_active,
        ]);
    }

    /**
     * Bulk update local rental package hours, included km, fares, and overrun rates.
     */
    public function bulkUpdateRates(Request $request)
    {
        $validated = $request->validate([
            'packages' => 'required|array|min:1',
            'packages.*.id' => 'required|integer|exists:local_packages,id',
            'packages.*.duration_hours' => 'required|integer|min:1',
            'packages.*.included_km' => 'required|integer|min:1',
            'packages.*.sedan_fare' => 'required|numeric|min:0',
            'packages.*.suv_fare' => 'required|numeric|min:0',
            'packages.*.extra_km_rate' => 'nullable|numeric|min:0',
            'packages.*.extra_hour_rate' => 'nullable|numeric|min:0',
            'packages.*.is_active' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['packages'] as $row) {
                $package = LocalPackage::findOrFail($row['id']);
                $package->duration_hours = (int) $row['duration_hours'];
                $package->included_km = (int) $row['included_km'];
                $package->sedan_fare = (float) $row['sedan_fare'];
                $package->suv_fare = (float) $row['suv_fare'];
                if (array_key_exists('extra_km_rate', $row) && $row['extra_km_rate'] !== null) {
                    $package->extra_km_rate = (float) $row['extra_km_rate'];
                }
                if (array_key_exists('extra_hour_rate', $row) && $row['extra_hour_rate'] !== null) {
                    $package->extra_hour_rate = (float) $row['extra_hour_rate'];
                }
                if (array_key_exists('is_active', $row)) {
                    $package->is_active = (bool) $row['is_active'];
                }
                $package->save();
            }
        });

        $updatedCount = count($validated['packages']);

        return response()->json([
            'success' => true,
            'message' => "Updated {$updatedCount} local package(s) successfully!",
            'packages' => LocalPackage::orderBy('duration_hours')->get(),
        ]);
    }

    /**
     * Delete a package.
     */
    public function destroy($id)
    {
        $package = LocalPackage::findOrFail($id);
        $code = $package->package_code;
        $package->delete();

        return response()->json([
            'success' => true,
            'message' => "Local Package '{$code}' deleted successfully.",
        ]);
    }
}
