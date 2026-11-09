<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DriverVehicle;
use App\Models\Driver;

class VehicleAdminController extends Controller
{
    // Driver submits their car for approval
    public function driverStore(Request $request)
    {
        $validated = $request->validate([
            'vehicle_model' => 'required|string|max:120',
            'vehicle_plate' => 'required|string|max:30|unique:driver_vehicles,vehicle_plate',
            'fleet_category' => 'required|string|in:sedan,prime,suv,luxury,sleeper_van',
            'seating_capacity' => 'required|integer|min:2|max:60',
            'berth_capacity' => 'nullable|integer|min:0|max:40',
            'coach_code' => 'nullable|string|max:30',
            'fuel_type' => 'nullable|string|max:40',
            'model_year' => 'nullable|string|max:10',
            'rc_number' => 'nullable|string|max:60',
        ]);

        $currentUser = auth()->user();
        $driver = Driver::where('phone', $currentUser?->phone)->first();

        $vehicle = DriverVehicle::create([
            'driver_id' => $driver?->id,
            'user_id' => $currentUser?->id,
            'driver_name' => $currentUser?->name ?? 'Partner Driver',
            'driver_phone' => $currentUser?->phone ?? '+91 98220 11920',
            'vehicle_model' => $validated['vehicle_model'],
            'vehicle_plate' => strtoupper($validated['vehicle_plate']),
            'fleet_category' => $validated['fleet_category'],
            'seating_capacity' => $validated['seating_capacity'],
            'berth_capacity' => $validated['berth_capacity'] ?? 0,
            'coach_code' => $validated['coach_code'] ?? null,
            'fuel_type' => $validated['fuel_type'] ?? 'Diesel',
            'model_year' => $validated['model_year'] ?? date('Y'),
            'rc_number' => $validated['rc_number'] ?? null,
            'status' => 'Pending', // Pending admin approval!
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Vehicle '{$vehicle->vehicle_model}' ({$vehicle->vehicle_plate}) listed successfully! Status is Pending Admin Approval.",
            'vehicle' => $vehicle
        ]);
    }

    // Admin lists all vehicles (with optional status filter)
    public function index(Request $request)
    {
        $query = DriverVehicle::with('driver');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $vehicles = $query->latest()->get();

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles
        ]);
    }

    public function show($id)
    {
        $vehicle = DriverVehicle::with('driver')->findOrFail($id);
        return response()->json([
            'success' => true,
            'vehicle' => $vehicle
        ]);
    }

    // Admin updates/edits any vehicle
    public function update(Request $request, $id)
    {
        $vehicle = DriverVehicle::findOrFail($id);

        $validated = $request->validate([
            'driver_name' => 'required|string|max:100',
            'driver_phone' => 'required|string|max:30',
            'vehicle_model' => 'required|string|max:120',
            'vehicle_plate' => 'required|string|max:30|unique:driver_vehicles,vehicle_plate,' . $id,
            'fleet_category' => 'required|string|in:sedan,prime,suv,luxury,sleeper_van',
            'seating_capacity' => 'required|integer|min:2|max:60',
            'berth_capacity' => 'nullable|integer|min:0|max:40',
            'coach_code' => 'nullable|string|max:30',
            'fuel_type' => 'nullable|string|max:40',
            'model_year' => 'nullable|string|max:10',
            'rc_number' => 'nullable|string|max:60',
            'status' => 'required|string|in:Pending,Approved,Rejected',
            'admin_notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['vehicle_plate'] = strtoupper($validated['vehicle_plate']);
        $validated['is_active'] = $request->boolean('is_active', $vehicle->is_active);

        $vehicle->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Vehicle '{$vehicle->vehicle_plate}' updated successfully by Admin!",
            'vehicle' => $vehicle
        ]);
    }

    // Admin 1-click Approval
    public function approve(Request $request, $id)
    {
        $vehicle = DriverVehicle::findOrFail($id);
        $notes = $request->input('admin_notes', 'Approved by Platform Admin. Commercial documents verified.');

        $vehicle->update([
            'status' => 'Approved',
            'is_active' => true,
            'admin_notes' => $notes,
        ]);

        // If driver exists, ensure driver is marked verified
        if ($vehicle->driver_id) {
            Driver::where('id', $vehicle->driver_id)->update(['is_verified' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => "Vehicle '{$vehicle->vehicle_model}' ({$vehicle->vehicle_plate}) has been APPROVED and activated!",
            'vehicle' => $vehicle
        ]);
    }

    // Admin 1-click Rejection
    public function reject(Request $request, $id)
    {
        $vehicle = DriverVehicle::findOrFail($id);
        $notes = $request->input('admin_notes', 'Rejected by Platform Admin. Registration / Fitness certificate discrepancy.');

        $vehicle->update([
            'status' => 'Rejected',
            'is_active' => false,
            'admin_notes' => $notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Vehicle '{$vehicle->vehicle_plate}' has been rejected.",
            'vehicle' => $vehicle
        ]);
    }

    public function destroy($id)
    {
        $vehicle = DriverVehicle::findOrFail($id);
        $plate = $vehicle->vehicle_plate;
        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => "Vehicle '{$plate}' deleted successfully."
        ]);
    }
}

