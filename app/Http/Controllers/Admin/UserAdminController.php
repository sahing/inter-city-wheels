<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
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
            $driver = $u->role === 'driver' ? $u->driverProfile() : null;
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
                'avatar' => $u->avatar ?? $this->defaultAvatarForRole($u->role),
                'created_at' => $u->created_at ? $u->created_at->format('d M Y') : 'N/A',
                'driver' => $driver ? [
                    'id' => $driver->id,
                    'vehicle_model' => $driver->vehicle_model,
                    'vehicle_plate' => $driver->vehicle_plate,
                    'fleet_category' => $driver->fleet_category,
                    'rating' => $driver->rating,
                    'wallet_balance' => $driver->wallet_balance,
                    'is_online' => $driver->is_online,
                    'is_verified' => (bool) $driver->is_verified,
                ] : null,
                'trips_count' => $tripsCount,
            ];
        });

        return response()->json([
            'success' => true,
            'users' => $users,
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
            'fleet_category' => 'nullable|string',
        ]);

        $avatar = $this->defaultAvatarForRole($validated['role']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'avatar' => $avatar,
            'password' => Hash::make($validated['password'] ?? 'password123'),
        ]);

        // Every driver account needs a verified Driver profile so bidding and
        // driver console actions work immediately.
        if ($validated['role'] === 'driver') {
            $this->ensureVerifiedDriverProfile($user, $validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registered new '.ucfirst($user->role).' account successfully!',
            'user' => $user,
        ], 201);
    }

    /**
     * Resolve customer profile and booking history from a booking ID.
     */
    public function customerProfileByBooking($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $user = null;

        if ($booking->customer_user_id) {
            $user = User::find($booking->customer_user_id);
        }
        if (! $user && $booking->customer_phone) {
            $user = User::where('phone', $booking->customer_phone)->first();
        }
        if (! $user && $booking->customer_email) {
            $user = User::where('email', $booking->customer_email)->first();
        }

        $bookingsQuery = Booking::query();
        if ($user) {
            $bookingsQuery->where('customer_user_id', $user->id)
                ->orWhere('customer_phone', $user->phone)
                ->orWhere('customer_email', $user->email);
        } else {
            $bookingsQuery->where('customer_phone', $booking->customer_phone);
        }

        $bookings = $bookingsQuery->latest()->take(20)->get();

        return response()->json([
            'success' => true,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'avatar' => $user->avatar ?? $this->defaultAvatarForRole($user->role),
            ] : [
                'id' => null,
                'name' => $booking->customer_name,
                'email' => $booking->customer_email ?: '—',
                'phone' => $booking->customer_phone,
                'role' => 'customer',
                'avatar' => '👤',
            ],
            'driver' => null,
            'bookings' => $bookings,
        ]);
    }

    /**
     * Resolve driver profile, linked user account, and assigned trips by Driver ID.
     */
    public function driverProfile($driverId)
    {
        $driver = Driver::findOrFail($driverId);
        $user = User::where('role', 'driver')->where('phone', $driver->phone)->first();
        $bookings = Booking::where('assigned_driver_id', $driver->id)->latest()->take(20)->get();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name ?? $driver->name,
                'email' => $user?->email ?? '—',
                'phone' => $driver->phone,
                'role' => 'driver',
                'avatar' => $user?->avatar ?? $driver->avatar ?? '👨🏽‍✈️',
            ],
            'driver' => $driver,
            'bookings' => $bookings,
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        $driver = $user->role === 'driver' ? $user->driverProfile() : null;

        $bookings = Booking::where('customer_phone', $user->phone)
            ->orWhere('customer_email', $user->email)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'user' => $user,
            'driver' => $driver,
            'bookings' => $bookings,
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
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            'phone' => 'sometimes|required|string|max:20',
            'role' => 'sometimes|required|in:customer,driver,admin',
            'password' => 'nullable|string|min:6',
        ]);

        if (
            isset($validated['role'])
            && (int) $user->id === (int) auth()->id()
            && $validated['role'] !== 'admin'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove admin permission from your own active administrator account.',
            ], 422);
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (isset($validated['role']) && $validated['role'] !== $user->role) {
            if (! $user->avatar || in_array($user->avatar, ['👤', '👨🏽‍✈️', '🛡️'], true)) {
                $validated['avatar'] = $this->defaultAvatarForRole($validated['role']);
            }
        }

        $user->update($validated);
        $user->refresh();

        $driver = $user->role === 'driver'
            ? $this->ensureVerifiedDriverProfile($user)
            : null;

        return response()->json([
            'success' => true,
            'message' => "User account {$user->name} updated successfully!",
            'user' => $user,
            'driver' => $driver,
        ]);
    }

    /**
     * Grant or change a user's access level (customer, driver, or admin).
     */
    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role' => 'required|in:customer,driver,admin',
        ]);

        if ((int) $user->id === (int) auth()->id() && $validated['role'] !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove admin permission from your own active administrator account.',
            ], 422);
        }

        $updates = ['role' => $validated['role']];
        if (! $user->avatar || in_array($user->avatar, ['👤', '👨🏽‍✈️', '🛡️'], true)) {
            $updates['avatar'] = $this->defaultAvatarForRole($validated['role']);
        }

        $user->update($updates);
        $user->refresh();

        $driver = $user->role === 'driver'
            ? $this->ensureVerifiedDriverProfile($user)
            : null;

        $roleLabel = match ($user->role) {
            'admin' => 'Platform Admin',
            'driver' => 'Driver Partner',
            default => 'Customer',
        };

        return response()->json([
            'success' => true,
            'message' => "{$user->name} now has {$roleLabel} access.",
            'user' => $user,
            'driver' => $driver,
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
                'message' => 'Cannot delete your own active administrator account.',
            ], 422);
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "User {$userName} has been removed.",
        ]);
    }

    private function defaultAvatarForRole(string $role): string
    {
        return match ($role) {
            'admin' => '🛡️',
            'driver' => '👨🏽‍✈️',
            default => '👤',
        };
    }

    /**
     * Ensure a user granted driver-level permission has a verified Driver row
     * linked by phone so bidding and driver console actions unlock immediately.
     */
    private function ensureVerifiedDriverProfile(User $user, array $input = []): Driver
    {
        $existing = $user->driverProfile();

        if ($existing) {
            $dirty = false;
            if (! $existing->is_verified) {
                $existing->is_verified = true;
                $dirty = true;
            }
            if (! empty($input['vehicle_model'])) {
                $existing->vehicle_model = trim((string) $input['vehicle_model']);
                $dirty = true;
            }
            if (! empty($input['vehicle_plate'])) {
                $existing->vehicle_plate = strtoupper(trim((string) $input['vehicle_plate']));
                $dirty = true;
            }
            if (! empty($input['fleet_category'])) {
                $existing->fleet_category = strtolower(trim((string) $input['fleet_category']));
                $dirty = true;
            }
            if ($dirty) {
                $existing->save();
            }

            return $existing;
        }

        $phone = trim((string) $user->phone);
        if ($phone === '') {
            $phone = '+91 90000 '.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
            $user->phone = $phone;
            $user->save();
        }

        $requestedPlate = strtoupper(trim((string) ($input['vehicle_plate'] ?? '')));
        $plate = $requestedPlate !== '' ? $requestedPlate : $this->uniquePlaceholderPlate();

        return Driver::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $user->name,
                'avatar' => '👨🏽‍✈️',
                'vehicle_model' => trim((string) ($input['vehicle_model'] ?? '')) ?: 'Maruti Suzuki Dzire',
                'vehicle_plate' => $plate,
                'fleet_category' => strtolower(trim((string) ($input['fleet_category'] ?? ''))) ?: 'sedan',
                'rating' => 4.90,
                'trips_count' => 0,
                'wallet_balance' => 0,
                'is_verified' => true,
                'is_online' => true,
            ]
        );
    }

    private function uniquePlaceholderPlate(): string
    {
        do {
            $plate = 'WB 02 '.random_int(10, 99).' '.random_int(1000, 9999);
        } while (Driver::where('vehicle_plate', $plate)->exists());

        return $plate;
    }
}
