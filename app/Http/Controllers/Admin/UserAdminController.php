<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Driver;
use App\Models\Booking;
use Illuminate\Support\Facades\Hash;

class UserAdminController extends Controller
{
    /**
     * Display a listing of registered users.
     */
    public function index(Request $request)
    {
        $query = User::latest();

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        $users = $query->get()->map(function ($u) {
            $driver = null;
            if ($u->role === 'driver') {
                $driver = Driver::where('phone', $u->phone)->orWhere('name', 'like', '%' . explode(' ', $u->name)[0] . '%')->first();
            }
            $tripsCount = 0;
            if ($u->role === 'customer') {
                $tripsCount = Booking::where('customer_phone', $u->phone)->orWhere('customer_email', $u->email)->count();
            } elseif ($driver) {
                $tripsCount = $driver->trips_count;
            }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'phone' => $u->phone,
                'avatar' => $u->avatar ?? ($u->role === 'admin' ? '🛡️' : ($u->role === 'driver' ? '👨🏽‍✈️' : '👤')),
                'created_at' => $u->created_at ? $u->created_at->format('d M Y') : 'N/A',
                'driver' => $driver ? [
                    'id' => $driver->id,
                    'vehicle_model' => $driver->vehicle_model,
                    'vehicle_plate' => $driver->vehicle_plate,
                    'fleet_category' => $driver->fleet_category,
                    'rating' => $driver->rating,
                    'wallet_balance' => $driver->wallet_balance,
                    'is_online' => $driver->is_online
                ] : null,
                'trips_count' => $tripsCount
            ];
        });

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Store a newly created user / driver in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'role' => 'required|in:customer,driver,admin',
            'password' => 'nullable|string|min:6',
            'vehicle_model' => 'nullable|string',
            'vehicle_plate' => 'nullable|string',
            'fleet_category' => 'nullable|string'
        ]);

        $avatar = match ($validated['role']) {
            'admin' => '🛡️',
            'driver' => '👨🏽‍✈️',
            default => '👤'
        };

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'avatar' => $avatar,
            'password' => Hash::make($validated['password'] ?? 'password123')
        ]);

        // If registered as driver, create corresponding driver record if plate given
        if ($validated['role'] === 'driver' && !empty($validated['vehicle_plate'])) {
            Driver::firstOrCreate(
                ['vehicle_plate' => strtoupper(trim($validated['vehicle_plate']))],
                [
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'avatar' => '👨🏽‍✈️',
                    'vehicle_model' => $validated['vehicle_model'] ?? 'Maruti Suzuki Dzire',
                    'fleet_category' => $validated['fleet_category'] ?? 'sedan',
                    'rating' => 4.90,
                    'trips_count' => 0,
                    'wallet_balance' => 0,
                    'is_verified' => true,
                    'is_online' => true
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Registered new " . ucfirst($user->role) . " account successfully!",
            'user' => $user
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        $driver = null;
        if ($user->role === 'driver') {
            $driver = Driver::where('phone', $user->phone)->orWhere('name', 'like', '%' . explode(' ', $user->name)[0] . '%')->first();
        }

        $bookings = Booking::where('customer_phone', $user->phone)
            ->orWhere('customer_email', $user->email)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'user' => $user,
            'driver' => $driver,
            'bookings' => $bookings
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|required|string|max:20',
            'role' => 'sometimes|required|in:customer,driver,admin',
            'password' => 'nullable|string|min:6'
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => "User account {$user->name} updated successfully!",
            'user' => $user
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent self-deletion of super admin
        if ($user->id === auth()->id() && $user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own active administrator account.'
            ], 422);
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "User {$userName} has been removed."
        ]);
    }
}

