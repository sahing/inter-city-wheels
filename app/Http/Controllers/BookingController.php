<?php

namespace App\Http\Controllers;

use App\Exceptions\BerthUnavailable;
use App\Exceptions\DistanceUnresolvable;
use App\Models\Booking;
use App\Models\PlatformSetting;
use App\Models\SeatLayout;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingWorkflow;
use App\Services\FareCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /** Maximum berths one booking may hold. */
    private const MAX_BERTHS_PER_BOOKING = 6;

    public function __construct(
        private readonly FareCalculatorService $fares,
        private readonly BookingReferenceGenerator $references,
        private readonly BookingWorkflow $workflow,
    ) {}

    /**
     * Create a booking.
     *
     * Guest booking stays open, but the guest still gets a signed lookup URL so
     * their own ticket is reachable without exposing anyone else's (P0-8).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|in:cab,bus',
            'trip_type' => 'nullable|string|in:oneway,roundtrip,local,airport',
            'from_city' => 'required|string|max:120',
            'to_city' => 'nullable|string|max:120',
            'pickup_location' => 'required|string|max:255',
            'drop_location' => 'nullable|string|max:255',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email',
            'departure_time' => 'nullable|string',
            'return_time' => 'nullable|string',
            'local_package' => 'nullable|string|max:20',
            'airport_type' => 'nullable|string|in:pickup,drop',
            'flight_number' => 'nullable|string|max:30',
            'passengers' => 'nullable|array',
            'selected_units' => 'nullable|array|max:'.self::MAX_BERTHS_PER_BOOKING,
            'selected_units.*' => 'string|max:20',
            'coach_code' => 'nullable|string|max:30',
            'fleet_category' => 'nullable|string|max:30',
            'include_tolls' => 'nullable|boolean',
            'pet_friendly' => 'nullable|boolean',
            'night_allowance' => 'nullable|boolean',
            'luggage_carrier' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|max:40',
            'dynamic_fields' => 'nullable|array',
            'from_lat' => 'nullable|numeric|between:-90,90',
            'from_lng' => 'nullable|numeric|between:-180,180',
            'to_lat' => 'nullable|numeric|between:-90,90',
            'to_lng' => 'nullable|numeric|between:-180,180',
        ]);

        $tripType = $validated['trip_type'] ?? 'oneway';
        $toCity = $validated['to_city'] ?? ($tripType === 'local' ? ($validated['from_city'].' Local') : 'Destination');
        $dropLocation = $validated['drop_location']
            ?? ($tripType === 'local' ? 'Local City Sightseeing & Transit' : 'Drop Address');

        $fleet = $this->fares->normalizeFleet((string) ($validated['fleet_category'] ?? 'suv'));

        $options = [
            'include_tolls' => $request->boolean('include_tolls'),
            'pet_friendly' => $request->boolean('pet_friendly'),
            'night_allowance' => $request->boolean('night_allowance'),
            'luggage_carrier' => $request->boolean('luggage_carrier'),
            'coupon_code' => $validated['coupon_code'] ?? null,
            'airport_type' => $validated['airport_type'] ?? 'pickup',
        ];

        // P1-1: no distance, no quote. resolveTrip() throws rather than
        // inventing a distance for a city it cannot ground.
        try {
            $trip = $this->fares->resolveTrip([
                'from_city' => $validated['from_city'],
                'to_city' => $toCity,
                'trip_type' => $tripType,
                'local_package' => $validated['local_package'] ?? null,
                'airport_type' => $validated['airport_type'] ?? 'pickup',
                'from_lat' => $validated['from_lat'] ?? null,
                'from_lng' => $validated['from_lng'] ?? null,
                'to_lat' => $validated['to_lat'] ?? null,
                'to_lng' => $validated['to_lng'] ?? null,
            ]);
        } catch (DistanceUnresolvable $e) {
            return response()->json([
                'success' => false,
                'code' => 'distance_unresolvable',
                'message' => $e->getMessage(),
            ] + $e->toArray(), 422);
        }

        $isBus = $validated['service_type'] === 'bus';

        if ($isBus) {
            $schedulesEnabled = PlatformSetting::get('enable_schedules_and_berths', '0');
            if ($schedulesEnabled !== '1' && (! auth()->check() || auth()->user()->role !== 'admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedules and berth bookings are currently unavailable.',
                ], 403);
            }
        }

        $selectedUnits = array_values(array_unique(array_map(
            static fn ($unit) => trim((string) $unit),
            $validated['selected_units'] ?? []
        )));

        if ($isBus && $selectedUnits === []) {
            throw ValidationException::withMessages([
                'selected_units' => 'Select at least one berth to book.',
            ]);
        }

        try {
            [$booking, $quote] = DB::transaction(function () use (
                $validated,
                $trip,
                $tripType,
                $toCity,
                $dropLocation,
                $fleet,
                $options,
                $isBus,
                $selectedUnits
            ) {
                $berths = new Collection;

                if ($isBus) {
                    // P0-6: lock the berth rows, then verify availability
                    // inside the same transaction that writes the booking.
                    $berths = $this->lockBerths($selectedUnits, $validated['coach_code'] ?? null);
                    $quote = $this->fares->quoteBerths($berths, $options);
                } else {
                    $quote = $this->fares->quote($trip, $fleet, $options);
                }

                // P0-4: burn one redemption inside the same transaction, and
                // only when the coupon was actually applied.
                $this->fares->redeemCoupon($quote['coupon']);

                // P0-5: every booking starts Requested; nothing may be created
                // already Confirmed or Completed.
                $booking = $this->workflow->create([
                    'booking_reference' => $this->references->generate(),
                    'customer_user_id' => auth()->id(),
                    'service_type' => $validated['service_type'],
                    'trip_type' => $tripType,
                    'route_summary' => "{$validated['from_city']} → {$toCity}",
                    'pickup_location' => $validated['pickup_location'],
                    'drop_location' => $dropLocation,
                    'departure_time' => ! empty($validated['departure_time'])
                        ? date('Y-m-d H:i:s', strtotime($validated['departure_time']))
                        : null,
                    'customer_name' => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'],
                    'customer_email' => $validated['customer_email'] ?? null,
                    'passengers' => $validated['passengers'] ?? [],
                    'selected_units' => $isBus ? $selectedUnits : [$fleet],
                    'base_fare' => $quote['base_fare'],
                    'toll_fare' => $quote['toll_fare'],
                    'addon_fare' => $quote['addon_fare'],
                    'discount_fare' => $quote['discount_fare'],
                    'total_amount' => $quote['total_amount'],
                    'start_otp' => $this->references->startOtp(),
                    'dynamic_fields_data' => $validated['dynamic_fields'] ?? [],
                ]);

                if ($isBus) {
                    SeatLayout::whereIn('id', $berths->pluck('id')->all())
                        ->update(['is_booked' => true]);
                }

                return [$booking, $quote];
            });
        } catch (BerthUnavailable $e) {
            return response()->json([
                'success' => false,
                'code' => 'berth_unavailable',
                'message' => $e->getMessage(),
            ] + $e->toArray(), 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trip booked successfully!',
            'booking' => $booking->fresh(),
            'quote' => $quote,
            'lookup_url' => URL::temporarySignedRoute(
                'api.bookings.show',
                now()->addDays(7),
                ['reference' => $booking->booking_reference]
            ),
        ]);
    }

    /**
     * Read a booking.
     *
     * P0-8: a reference alone is not proof of ownership. Access requires one
     * of: a valid signature (the link handed out at booking time), an
     * authenticated owner or admin, or the mobile number used to book.
     */
    public function show(Request $request, string $reference)
    {
        $booking = Booking::with('driver')->where('booking_reference', $reference)->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        $hasSignature = $request->hasValidSignature();
        $isOwner = $booking->isOwnedBy($request->user());
        $phoneMatches = $request->filled('phone')
            && $booking->matchesPhone((string) $request->query('phone'));

        if (! $hasSignature && ! $isOwner && ! $phoneMatches) {
            return response()->json([
                'success' => false,
                'code' => 'booking_forbidden',
                'message' => 'This booking is private. Sign in, reopen the link from your ticket, or verify the mobile number used to book.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'booking' => $booking,
        ]);
    }

    /**
     * Anonymous lookup for a guest who kept neither the account nor the link:
     * requires the booking reference AND the mobile number on the booking.
     */
    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string|max:40',
            'phone' => 'required|string|max:20',
        ]);

        $booking = Booking::with('driver')
            ->where('booking_reference', strtoupper(trim($validated['reference'])))
            ->first();

        // Same response for "no such booking" and "wrong phone" so the endpoint
        // cannot be used to enumerate references.
        if (! $booking || ! $booking->matchesPhone($validated['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'No booking matches that reference and mobile number.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'booking' => $booking,
        ]);
    }

    /**
     * Lock and validate the berth rows for a bus booking.
     *
     * Berth numbers repeat across coaches ("L1" exists on several vehicles), so
     * the selection is pinned to one coach before availability is judged.
     *
     * @param  list<string>  $units
     * @return Collection<int, SeatLayout>
     *
     * @throws ValidationException
     * @throws BerthUnavailable
     */
    private function lockBerths(array $units, ?string $coachCode): Collection
    {
        if (count($units) > self::MAX_BERTHS_PER_BOOKING) {
            throw ValidationException::withMessages([
                'selected_units' => 'A single booking may hold at most '
                    .self::MAX_BERTHS_PER_BOOKING.' berths.',
            ]);
        }

        $query = SeatLayout::whereIn('berth_number', $units)->orderBy('id')->lockForUpdate();

        if ($coachCode !== null && $coachCode !== '') {
            $query->where('coach_code', $coachCode);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'selected_units' => 'None of the selected berths exist on this vehicle.',
            ]);
        }

        $coach = $rows->first()->coach_code;
        $inCoach = $rows->where('coach_code', $coach);

        $missing = array_values(array_diff($units, $inCoach->pluck('berth_number')->all()));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'selected_units' => 'Berths not available on vehicle '.$coach.': '.implode(', ', $missing).'.',
            ]);
        }

        $taken = $inCoach->where('is_booked', true);

        if ($taken->isNotEmpty()) {
            throw BerthUnavailable::fromRows($taken->values());
        }

        return $inCoach->values();
    }
}
