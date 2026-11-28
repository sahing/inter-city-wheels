<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeoPlaceCache;
use App\Models\ServiceLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationAdminController extends Controller
{
    public function index()
    {
        $locations = ServiceLocation::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80|unique:service_locations,name',
            'state' => 'required|string|max:80',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'airport_code' => 'nullable|string|max:10',
            'has_airport_service' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['has_airport_service'] = $request->boolean('has_airport_service', true);
        $validated['is_active'] = $request->boolean('is_active', true);

        $location = ServiceLocation::create($validated);
        $this->purgeGeoPlaceCacheForCity($location->name);

        return response()->json([
            'success' => true,
            'message' => "Service location '{$location->name}' added successfully!",
            'location' => $location,
        ]);
    }

    public function show($id)
    {
        $location = ServiceLocation::findOrFail($id);

        return response()->json([
            'success' => true,
            'location' => $location,
        ]);
    }

    public function update(Request $request, $id)
    {
        $location = ServiceLocation::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:80|unique:service_locations,name,'.$id,
            'state' => 'required|string|max:80',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'airport_code' => 'nullable|string|max:10',
            'has_airport_service' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['has_airport_service'] = $request->boolean('has_airport_service', $location->has_airport_service);
        $validated['is_active'] = $request->boolean('is_active', $location->is_active);

        $previousName = (string) $location->name;
        $location->update($validated);
        $this->purgeGeoPlaceCacheForCity($location->name, $location->id);
        if (strtolower($previousName) !== strtolower((string) $location->name)) {
            $this->purgeGeoPlaceCacheForCity($previousName);
        }

        return response()->json([
            'success' => true,
            'message' => "Location '{$location->name}' updated successfully!",
            'location' => $location,
        ]);
    }

    public function toggleStatus($id)
    {
        $location = ServiceLocation::findOrFail($id);
        $location->update(['is_active' => ! $location->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Location status updated to '.($location->is_active ? 'Active' : 'Inactive'),
            'location' => $location,
        ]);
    }

    public function destroy($id)
    {
        $location = ServiceLocation::findOrFail($id);
        $name = (string) $location->name;
        $locationId = (int) $location->id;
        $location->delete();

        $this->purgeGeoPlaceCacheForCity($name, $locationId);

        return response()->json([
            'success' => true,
            'message' => "Location '{$name}' deleted successfully!",
        ]);
    }

    /**
     * List OpenStreetMap-discovered / out-of-region cached cities awaiting or reviewed by admin.
     */
    public function discovered()
    {
        $items = GeoPlaceCache::query()
            ->orderByRaw("CASE approval_status WHEN 'pending' THEN 0 WHEN 'cached' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END")
            ->orderByDesc('is_beyond_region')
            ->orderByDesc('hits')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'discovered_locations' => $items,
        ]);
    }

    /**
     * Approve a cached/out-of-region OpenStreetMap city and promote it into ServiceLocation.
     */
    public function approveDiscovered(Request $request, $id)
    {
        $discovered = GeoPlaceCache::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:80',
            'state' => 'nullable|string|max:80',
            'airport_code' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        $cityName = trim((string) ($validated['name'] ?? $discovered->name));
        $stateName = trim((string) ($validated['state'] ?? $discovered->state ?? 'India')) ?: 'India';
        $isActive = $request->boolean('is_active', true);

        $location = ServiceLocation::whereRaw('LOWER(name) = ?', [strtolower($cityName)])->first();

        if ($location) {
            $location->update([
                'state' => $stateName,
                'latitude' => (float) $discovered->latitude,
                'longitude' => (float) $discovered->longitude,
                'airport_code' => $validated['airport_code'] ?? $location->airport_code,
                'is_active' => $isActive,
            ]);
        } else {
            $location = ServiceLocation::create([
                'name' => $cityName,
                'state' => $stateName,
                'latitude' => (float) $discovered->latitude,
                'longitude' => (float) $discovered->longitude,
                'airport_code' => $validated['airport_code'] ?? null,
                'has_airport_service' => ! empty($validated['airport_code']),
                'is_active' => $isActive,
            ]);
        }

        $originalName = (string) $discovered->name;
        $discoveredSnapshot = [
            'id' => $discovered->id,
            'name' => $cityName,
            'state' => $stateName,
            'approval_status' => 'approved',
            'service_location_id' => $location->id,
            'deleted' => true,
        ];

        $discovered->delete();
        $this->purgeGeoPlaceCacheForCity($cityName, $location->id);
        if (strtolower($originalName) !== strtolower($cityName)) {
            $this->purgeGeoPlaceCacheForCity($originalName);
        }

        return response()->json([
            'success' => true,
            'message' => "'{$location->name}' approved and added to the active city list!",
            'location' => $location,
            'discovered' => $discoveredSnapshot,
            'deleted_from_cache' => true,
        ]);
    }

    /**
     * Dismiss / remove a discovered city and delete it from the OSM cache table.
     */
    public function dismissDiscovered($id)
    {
        $discovered = GeoPlaceCache::findOrFail($id);
        $name = (string) $discovered->name;
        $snapshot = [
            'id' => $discovered->id,
            'name' => $name,
            'approval_status' => 'dismissed',
            'deleted' => true,
        ];

        $discovered->delete();
        $this->purgeGeoPlaceCacheForCity($name);

        return response()->json([
            'success' => true,
            'message' => "Discovered location '{$name}' removed from OSM cache.",
            'discovered' => $snapshot,
            'deleted_from_cache' => true,
        ]);
    }

    /**
     * Delete any GeoPlaceCache rows matching the given city name or ServiceLocation ID.
     */
    private function purgeGeoPlaceCacheForCity(string $cityName, ?int $serviceLocationId = null): int
    {
        $clean = strtolower(trim(explode(',', $cityName)[0]));
        $fullLower = strtolower(trim($cityName));

        if ($clean === '' && $serviceLocationId === null) {
            return 0;
        }

        return GeoPlaceCache::query()
            ->where(function ($query) use ($clean, $fullLower, $serviceLocationId) {
                $hasCondition = false;

                if ($serviceLocationId !== null) {
                    $query->where('service_location_id', $serviceLocationId);
                    $hasCondition = true;
                }

                foreach (array_unique(array_filter([$clean, $fullLower])) as $nameVariant) {
                    if ($hasCondition) {
                        $query->orWhereRaw('LOWER(name) = ?', [$nameVariant])
                            ->orWhereRaw('LOWER(query) = ?', [$nameVariant]);
                    } else {
                        $query->whereRaw('LOWER(name) = ?', [$nameVariant])
                            ->orWhereRaw('LOWER(query) = ?', [$nameVariant]);
                        $hasCondition = true;
                    }
                }
            })
            ->delete();
    }
}
