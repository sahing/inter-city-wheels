<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidBookingTransition;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverBid;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverBiddingController extends Controller
{
    /** Platform commission withheld from the driver payout. */
    private const PLATFORM_FEE_RATE = 0.10;

    public function __construct(
        private readonly BookingWorkflow $workflow,
        private readonly BookingReferenceGenerator $references,
    ) {}

    public function getActiveBids()
    {
        $drivers = Driver::where('is_online', true)->get();

        return response()->json([
            'success' => true,
            'drivers' => $drivers,
        ]);
    }

    public function counterOffer(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'counter_amount' => 'required|numeric|min:500',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Counter offer dispatched to driver!',
            'driver_id' => $validated['driver_id'],
            'counter_amount' => $validated['counter_amount'],
        ]);
    }

    public function submitDriverBid(Request $request)
    {
        $validated = $request->validate([
            'trip_id' => 'required|string',
            'from_location' => 'required|string',
            'to_location' => 'required|string',
            'distance_km' => 'required|numeric',
            'bid_amount' => 'required|numeric|min:100',
            'driver_id' => 'nullable|exists:drivers,id',
            'notes' => 'nullable|string',
        ]);

        $currentUser = $request->user();
        $driver = $this->driverForUser($currentUser);
        $driverId = $driver?->id ?? ($validated['driver_id'] ?? null);

        $driverName = $driver?->name ?? ($currentUser?->name ?? 'Verified Chauffeur');
        $conclusiveStatement = "I will take and do this ride from {$validated['from_location']} to {$validated['to_location']} ({$validated['distance_km']} km) at this price: ₹".number_format($validated['bid_amount']).'. I accept this.';

        // P1-3: a bid is a record, not a message. Store it whenever the trip can
        // be tied to a real booking; driver_bids.booking_id is a foreign key, so
        // a free-text trip reference cannot be persisted and says so.
        $booking = $this->resolveBooking($validated['trip_id']);
        $bid = null;

        if ($booking && $driverId) {
            $bid = DriverBid::create([
                'booking_id' => $booking->id,
                'driver_id' => $driverId,
                'bid_amount' => $validated['bid_amount'],
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Binding bid of ₹'.number_format($validated['bid_amount'])." submitted! {$conclusiveStatement}",
            'bid' => [
                'id' => $bid?->id,
                'persisted' => $bid !== null,
                'trip_id' => $validated['trip_id'],
                'from_location' => $validated['from_location'],
                'to_location' => $validated['to_location'],
                'distance_km' => (float) $validated['distance_km'],
                'bid_amount' => (float) $validated['bid_amount'],
                'conclusive_statement' => $conclusiveStatement,
                'status' => 'Bid Placed & Accepted',
                'driver_name' => $driverName,
            ],
        ]);
    }

    public function acceptBid(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'amount' => 'required|numeric|min:500',
            'route' => 'required|string',
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
        ]);

        $driver = Driver::findOrFail($validated['driver_id']);

        try {
            $booking = DB::transaction(function () use ($validated, $driver) {
                // P0-5 + P0-7: recorded as Requested, then legally assigned.
                // References and ride OTPs come from the CSPRNG.
                return $this->workflow->create([
                    'booking_reference' => $this->references->generate(),
                    'customer_user_id' => auth()->id(),
                    'service_type' => 'cab',
                    'trip_type' => 'oneway',
                    'route_summary' => $validated['route'],
                    'pickup_location' => 'Doorstep Pickup',
                    'drop_location' => 'Destination City',
                    'customer_name' => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'],
                    'passengers' => [['name' => $validated['customer_name']]],
                    'selected_units' => [$driver->vehicle_plate],
                    'base_fare' => $validated['amount'],
                    'toll_fare' => 0,
                    'addon_fare' => 0,
                    'discount_fare' => 0,
                    'total_amount' => $validated['amount'],
                    'start_otp' => $this->references->startOtp(),
                ], BookingWorkflow::DRIVER_ASSIGNED, [
                    'assigned_driver_id' => $driver->id,
                ]);
            });
        } catch (InvalidBookingTransition $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'transition' => $e->toArray(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver assigned and ride locked!',
            'booking' => $booking,
        ]);
    }

    public function verifyStartOtp(Request $request, $id)
    {
        $validated = $request->validate([
            'otp' => 'required|string|size:4',
        ]);

        $booking = Booking::findOrFail($id);

        $authorization = $this->authorizeTrip($request->user(), $booking);

        if ($authorization !== null) {
            return $authorization;
        }

        if (! hash_equals((string) $booking->start_otp, (string) $validated['otp'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Ask the passenger for their 4-digit ride start OTP.',
            ], 422);
        }

        try {
            $started = DB::transaction(function () use ($booking) {
                $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

                $this->workflow->transition($locked, BookingWorkflow::STARTED, [], allowSameStatus: false);

                return $locked;
            });
        } catch (InvalidBookingTransition $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'transition' => $e->toArray(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully! Trip has commenced.',
            'booking' => $started->fresh(),
        ]);
    }

    public function completeTrip(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $authorization = $this->authorizeTrip($request->user(), $booking);

        if ($authorization !== null) {
            return $authorization;
        }

        try {
            $result = DB::transaction(function () use ($booking) {
                $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

                // Rejects a second completion, which would pay the driver twice.
                $this->workflow->transition($locked, BookingWorkflow::COMPLETED, [], allowSameStatus: false);

                $payout = 0.0;

                if ($locked->assigned_driver_id) {
                    $driver = Driver::whereKey($locked->assigned_driver_id)->lockForUpdate()->first();

                    if ($driver) {
                        // A trip won through the driver marketplace pays the
                        // winning bid; a manually allotted trip keeps the legacy
                        // commission split. The stored amount always wins.
                        $payout = $locked->driver_payout_amount !== null
                            ? round((float) $locked->driver_payout_amount, 2)
                            : round(((float) $locked->total_amount) * (1 - self::PLATFORM_FEE_RATE), 2);

                        $this->creditDriver(
                            $driver,
                            $payout,
                            WalletTransaction::REASON_TRIP_PAYOUT,
                            $locked,
                            'Trip payout for '.$locked->booking_reference
                        );

                        $driver->trips_count = (int) $driver->trips_count + 1;
                        $driver->save();
                    }
                }

                return [$locked, $payout];
            });
        } catch (InvalidBookingTransition $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'transition' => $e->toArray(),
            ], 422);
        }

        [$completed, $payout] = $result;

        return response()->json([
            'success' => true,
            'message' => 'Trip marked as completed! Payout credited to your driver wallet.',
            'payout' => $payout,
            'booking' => $completed->fresh(),
        ]);
    }

    public function withdrawPayout(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $driver = $this->driverForUser($request->user());

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver profile is linked to this account.',
            ], 403);
        }

        $amount = round((float) $validated['amount'], 2);

        try {
            $transaction = DB::transaction(function () use ($driver, $amount) {
                $locked = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();

                // Balance floor: a withdrawal can never overdraw the wallet.
                if ($amount > (float) $locked->wallet_balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Withdrawal exceeds wallet balance of ₹'
                            .number_format((float) $locked->wallet_balance, 2).'.',
                    ]);
                }

                return $this->debitDriver(
                    $locked,
                    $amount,
                    WalletTransaction::REASON_WITHDRAWAL,
                    null,
                    'IMPS payout request to registered bank account'
                );
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payout request for ₹'.number_format($amount).' initiated to your registered bank account (HDFC •••• 4912) via IMPS.',
            'balance_after' => (float) $transaction->balance_after,
            'transaction_id' => $transaction->id,
        ]);
    }

    /**
     * Get current driver profile details.
     */
    public function getProfile(Request $request)
    {
        $driver = $this->driverForUser($request->user());

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver profile is linked to this account. Complete onboarding first.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $driver,
            'driver' => $driver,
        ]);
    }

    /**
     * Update current driver profile credentials.
     */
    public function updateProfile(Request $request)
    {
        $driver = $this->driverForUser($request->user());

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver profile is linked to this account.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'vehicle_model' => 'required|string|max:100',
            'vehicle_plate' => 'required|string|max:20',
            'fleet_category' => 'required|string|in:sedan,prime,suv,luxury,sleeper_van',
            'avatar' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
        ]);

        $driver->name = trim($validated['name']);
        $driver->phone = trim($validated['phone']);
        $driver->vehicle_model = trim($validated['vehicle_model']);
        $driver->vehicle_plate = strtoupper(trim($validated['vehicle_plate']));
        $driver->fleet_category = strtolower(trim($validated['fleet_category']));
        if (! empty($validated['avatar'])) {
            $driver->avatar = $validated['avatar'];
        }
        $driver->save();

        $currentUser = $request->user();

        if ($currentUser) {
            $currentUser->name = $driver->name;
            $currentUser->phone = $driver->phone;
            if (! empty($validated['avatar'])) {
                $currentUser->avatar = $driver->avatar;
            }
            $currentUser->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver profile updated successfully.',
            'profile' => $driver,
            'driver' => $driver,
        ]);
    }

    /**
     * Toggle driver Online/Offline status.
     */
    public function toggleOnlineStatus(Request $request)
    {
        $driver = $this->driverForUser($request->user());

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver profile is linked to this account.',
            ], 403);
        }

        $driver->is_online = ! $driver->is_online;
        $driver->save();

        return response()->json([
            'success' => true,
            'is_online' => (bool) $driver->is_online,
            'message' => 'Driver duty status changed to '
                .($driver->is_online ? 'Online (Accepting Bids)' : 'Offline (Off Duty)'),
        ]);
    }

    /**
     * Map the signed-in account to its driver profile. Phone is the join key
     * because registrations create both rows with the same number.
     */
    private function driverForUser(?User $user): ?Driver
    {
        if (! $user) {
            return null;
        }

        $phone = trim((string) $user->phone);

        if ($phone !== '') {
            $driver = Driver::where('phone', $phone)->first();

            if ($driver) {
                return $driver;
            }

            $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
            $lastTen = substr($digits, -10);

            if (strlen($lastTen) === 10) {
                $driver = Driver::where('phone', 'like', '%'.$lastTen.'%')->first();

                if ($driver) {
                    return $driver;
                }
            }
        }

        return null;
    }

    /**
     * Only the assigned driver (or an admin) may move a trip along.
     */
    private function authorizeTrip(?User $user, Booking $booking)
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->isAdmin()) {
            return null;
        }

        if ($booking->assigned_driver_id === null) {
            return response()->json([
                'success' => false,
                'message' => 'No driver is assigned to this trip yet.',
            ], 422);
        }

        $driver = $this->driverForUser($user);

        if (! $driver || (int) $driver->id !== (int) $booking->assigned_driver_id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. This trip is assigned to another driver.',
            ], 403);
        }

        return null;
    }

    private function resolveBooking(string $tripReference): ?Booking
    {
        $reference = trim($tripReference);

        if ($reference === '') {
            return null;
        }

        $booking = Booking::where('booking_reference', strtoupper($reference))->first();

        if ($booking) {
            return $booking;
        }

        if (ctype_digit($reference)) {
            return Booking::find((int) $reference);
        }

        return null;
    }

    private function creditDriver(
        Driver $driver,
        float $amount,
        string $reason,
        ?Booking $booking,
        string $description
    ): WalletTransaction {
        $driver->wallet_balance = round((float) $driver->wallet_balance + $amount, 2);
        $driver->save();

        return WalletTransaction::create([
            'driver_id' => $driver->id,
            'booking_id' => $booking?->id,
            'type' => WalletTransaction::TYPE_CREDIT,
            'reason' => $reason,
            'amount' => $amount,
            'balance_after' => $driver->wallet_balance,
            'description' => $description,
        ]);
    }

    private function debitDriver(
        Driver $driver,
        float $amount,
        string $reason,
        ?Booking $booking,
        string $description
    ): WalletTransaction {
        $driver->wallet_balance = round((float) $driver->wallet_balance - $amount, 2);
        $driver->save();

        return WalletTransaction::create([
            'driver_id' => $driver->id,
            'booking_id' => $booking?->id,
            'type' => WalletTransaction::TYPE_DEBIT,
            'reason' => $reason,
            'amount' => $amount,
            'balance_after' => $driver->wallet_balance,
            'description' => $description,
        ]);
    }
}
