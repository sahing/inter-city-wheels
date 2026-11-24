<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use Illuminate\Http\Request;

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

        $location->update($validated);

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
        $name = $location->name;
        $location->delete();

        return response()->json([
            'success' => true,
            'message' => "Location '{$name}' deleted successfully!",
        ]);
    }
}
