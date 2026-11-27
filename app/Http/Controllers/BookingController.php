<?php

namespace App\Http\Controllers;

use App\Exceptions\BerthUnavailable;
use App\Exceptions\DistanceUnresolvable;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\PlatformSetting;
use App\Models\SeatLayout;
use App\Services\BiddingService;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingWorkflow;
use App\Services\FareCalculatorService;
use App\Services\GeoCacheService;
use App\Services\NotificationService;
use App\Services\RazorpayService;
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
        private readonly BiddingService $bidding,
        private readonly NotificationService $notifications,
        private readonly RazorpayService $razorpay,
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
            'payment_mode' => 'nullable|string|in:full_online,part_pay,full_upi,cash,full,advance',
            'payment_channel' => 'nullable|string|in:razorpay,manual_upi,driver_collect',
            'upi_utr' => 'nullable|string|min:6|max:40|regex:/^[A-Za-z0-9\-_]+$/',
            'payment_collection_by' => 'nullable|string|in:platform,driver',
            'part_pay_agreed' => 'nullable|boolean',
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

        $paymentConfig = PlatformSetting::paymentSettings();
        $enabledModes = [];
        if (($paymentConfig['payment_show_full_online'] ?? '1') === '1') {
            $enabledModes[] = 'full_online';
        }
        if (($paymentConfig['payment_show_part_pay'] ?? '1') === '1') {
            $enabledModes[] = 'part_pay';
        }
        if (($paymentConfig['payment_show_full_upi'] ?? '1') === '1') {
            $enabledModes[] = 'full_upi';
        }
        if (($paymentConfig['payment_show_cash'] ?? '1') === '1') {
            $enabledModes[] = 'cash';
        }
        if ($enabledModes === []) {
            $enabledModes = ['full_online'];
        }

        $requestedMode = $validated['payment_mode'] ?? null;
        if ($requestedMode === 'full') {
            $requestedMode = 'full_online';
        } elseif ($requestedMode === 'advance') {
            $requestedMode = 'part_pay';
        }

        if ($requestedMode !== null && ! in_array($requestedMode, $enabledModes, true)) {
            throw ValidationException::withMessages([
                'payment_mode' => 'The selected payment option is currently disabled by platform settings.',
            ]);
        }

        $paymentMode = $requestedMode ?? (
            in_array($paymentConfig['payment_default_option'] ?? '', $enabledModes, true)
                ? $paymentConfig['payment_default_option']
                : $enabledModes[0]
        );
        $configuredAdvancePct = max(1.0, min(99.0, (float) ($paymentConfig['payment_advance_pct'] ?? 20)));
        $isBeyondRegion = ! $isBus && (bool) ($trip['beyond_region'] ?? false);
        $upiUtr = ! empty($validated['upi_utr']) ? strtoupper(trim((string) $validated['upi_utr'])) : null;
        $paymentChannel = $validated['payment_channel'] ?? null;
        $requestedCollectionBy = $validated['payment_collection_by'] ?? null;
        $wantsDriverCollect = $paymentMode === 'cash'
            || $paymentChannel === 'driver_collect'
            || $requestedCollectionBy === 'driver';

        if (($paymentChannel === 'driver_collect' || $requestedCollectionBy === 'driver') && ($paymentConfig['payment_show_cash'] ?? '1') !== '1') {
            throw ValidationException::withMessages([
                'payment_collection_by' => 'Driver collection on completion is currently disabled by platform settings.',
            ]);
        }

        if ($paymentChannel === 'razorpay' && ! $this->razorpay->isReady()) {
            throw ValidationException::withMessages([
                'payment_channel' => 'Razorpay gateway is currently inactive. Please use Manual UPI.',
            ]);
        }

        // Customer may choose Razorpay Gateway OR Manual UPI when both are available.
        // Never capture upfront online payment or pre-confirm a ride whose availability
        // outside our regional area is not guaranteed.
        $useRazorpay = ! $wantsDriverCollect
            && $paymentChannel !== 'manual_upi'
            && $upiUtr === null
            && ! $isBeyondRegion
            && $this->razorpay->isReady();

        try {
            [$booking, $quote] = DB::transaction(function () use (
                $request,
                $validated,
                $trip,
                $tripType,
                $toCity,
                $dropLocation,
                $fleet,
                $options,
                $isBus,
                $isBeyondRegion,
                $selectedUnits,
                $paymentMode,
                $paymentChannel,
                $configuredAdvancePct,
                $useRazorpay,
                $upiUtr,
                $wantsDriverCollect
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
                $totalAmount = (float) $quote['total_amount'];
                $isManualUpiActive = ! $useRazorpay && ! $wantsDriverCollect && (
                    $paymentChannel === 'manual_upi'
                    || $paymentMode === 'full_upi'
                    || $upiUtr !== null
                );
                $isDriverCollectOverride = ! $useRazorpay && $paymentMode !== 'cash' && $wantsDriverCollect;

                [$advancePct, $advanceAmount, $balanceAmount] = match (true) {
                    $paymentMode === 'cash' || $isDriverCollectOverride => [0.0, 0.0, $totalAmount],
                    $paymentMode === 'part_pay' => [
                        $configuredAdvancePct,
                        $adv = round($totalAmount * ($configuredAdvancePct / 100), 2),
                        round(max(0.0, $totalAmount - $adv), 2),
                    ],
                    default => [100.0, $totalAmount, 0.0],
                };

                $collectionBy = match (true) {
                    $paymentMode === 'cash' || $isDriverCollectOverride || $paymentMode === 'part_pay' => 'driver',
                    default => 'platform',
                };

                $paymentGateway = match (true) {
                    $paymentMode === 'cash' => 'manual',
                    $useRazorpay => 'razorpay',
                    $isManualUpiActive => 'upi_manual',
                    default => 'manual',
                };

                if ($isBeyondRegion) {
                    $initialPaymentStatus = 'pending_availability';
                } elseif ($paymentMode === 'cash') {
                    $initialPaymentStatus = 'pay_on_pickup';
                } elseif ($isDriverCollectOverride) {
                    $initialPaymentStatus = 'driver_collect';
                } elseif ($isManualUpiActive) {
                    $initialPaymentStatus = $upiUtr !== null ? 'pending_upi_verification' : 'awaiting_upi_utr';
                } elseif ($useRazorpay) {
                    $initialPaymentStatus = 'pending';
                } elseif ($paymentMode === 'part_pay') {
                    $initialPaymentStatus = 'partial_paid';
                } else {
                    $initialPaymentStatus = 'paid';
                }

                $partPayAgreementNote = ($paymentMode === 'part_pay' && $request->boolean('part_pay_agreed'))
                    ? 'Customer agreed to pay remaining balance & additional charges (if any) on trip completion'
                    : null;

                $paymentNotes = match (true) {
                    $isDriverCollectOverride => 'Customer chose driver collection on trip completion',
                    $isManualUpiActive && $upiUtr !== null => 'UPI UTR submitted — awaiting Admin confirmation',
                    $isManualUpiActive => 'Awaiting UPI UTR submission or driver collection',
                    default => null,
                };

                if ($partPayAgreementNote !== null) {
                    $paymentNotes = $paymentNotes ? "{$paymentNotes} | {$partPayAgreementNote}" : $partPayAgreementNote;
                }

                $booking = $this->workflow->create([
                    'booking_reference' => $this->references->generate(),
                    'customer_user_id' => auth()->id(),
                    'service_type' => $validated['service_type'],
                    'trip_type' => $tripType,
                    'is_beyond_region' => $isBeyondRegion,
                    'availability_status' => $isBeyondRegion ? 'unconfirmed' : 'guaranteed',
                    'region_note' => $trip['region_note'] ?? null,
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
                    'total_amount' => $totalAmount,
                    'payment_mode' => $paymentMode,
                    'advance_percentage' => $advancePct,
                    'advance_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount,
                    'payment_gateway' => $paymentGateway,
                    'payment_status' => $initialPaymentStatus,
                    'upi_utr' => $upiUtr,
                    'payment_collection_by' => $collectionBy,
                    'payment_notes' => $paymentNotes,
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

        if ($isBeyondRegion) {
            $geoCache = app(GeoCacheService::class);
            $beyondCities = [];

            $endpoints = [
                [
                    'in_region' => (bool) ($trip['from_in_region'] ?? true),
                    'name' => trim(explode(',', (string) $validated['from_city'])[0]),
                    'raw' => (string) $validated['from_city'],
                    'lat' => $trip['from_lat'] ?? ($validated['from_lat'] ?? null),
                    'lng' => $trip['from_lng'] ?? ($validated['from_lng'] ?? null),
                ],
                [
                    'in_region' => (bool) ($trip['to_in_region'] ?? true),
                    'name' => trim(explode(',', (string) $toCity)[0]),
                    'raw' => (string) $toCity,
                    'lat' => $trip['to_lat'] ?? ($validated['to_lat'] ?? null),
                    'lng' => $trip['to_lng'] ?? ($validated['to_lng'] ?? null),
                ],
            ];

            foreach ($endpoints as $ep) {
                if ($ep['in_region'] || $ep['name'] === '' || $ep['lat'] === null || $ep['lng'] === null) {
                    continue;
                }
                $statePart = str_contains($ep['raw'], ',')
                    ? trim(explode(',', $ep['raw'], 2)[1])
                    : null;

                $geoCache->recordBeyondRegionCity(
                    $ep['name'],
                    (float) $ep['lat'],
                    (float) $ep['lng'],
                    $statePart ?: null,
                    $ep['raw'],
                    $booking->id,
                    $booking->booking_reference
                );
                $beyondCities[] = $ep['name'];
            }
        }

        $razorpayOrder = null;
        if ($useRazorpay) {
            try {
                $razorpayOrder = $this->razorpay->createOrder($booking, (float) $booking->advance_amount);
                $booking->forceFill(['razorpay_order_id' => $razorpayOrder['order_id']])->save();
            } catch (\Throwable $e) {
                $booking->delete();

                return response()->json([
                    'success' => false,
                    'code' => 'razorpay_order_failed',
                    'message' => 'Razorpay gateway error: '.$e->getMessage(),
                ], 502);
            }
        } else {
            if (! $isBus) {
                $this->bidding->openForNewBooking($booking);
            }

            $this->notifications->bookingCreated($booking, 'customer', $beyondCities ?? []);
        }

        // Track payment attempt under the authenticated user
        $initialAttemptChannel = $useRazorpay
            ? 'razorpay'
            : ($booking->payment_gateway === 'upi_manual' ? 'manual_upi' : 'driver_collect');
        $initialAttemptStatus = match (true) {
            $useRazorpay => 'initiated',
            $booking->payment_status === 'pending_upi_verification' => 'utr_submitted',
            $booking->payment_status === 'driver_collect' || $booking->payment_status === 'pay_on_pickup' => 'driver_collect',
            $booking->payment_status === 'paid' || $booking->payment_status === 'partial_paid' => 'verified',
            default => 'initiated',
        };
        PaymentAttempt::create([
            'booking_id' => $booking->id,
            'user_id' => auth()->id(),
            'payment_mode' => (string) $booking->payment_mode,
            'payment_channel' => $initialAttemptChannel,
            'amount' => (float) ($booking->advance_amount > 0 ? $booking->advance_amount : $booking->total_amount),
            'status' => $initialAttemptStatus,
            'gateway_order_id' => $razorpayOrder['order_id'] ?? null,
            'upi_utr' => $booking->upi_utr,
            'notes' => $booking->payment_notes,
            'attempted_at' => now(),
            'resolved_at' => $initialAttemptStatus === 'verified' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $useRazorpay
                ? 'Complete Razorpay payment to confirm your trip.'
                : ($isBeyondRegion
                    ? 'Booking request recorded (Beyond Service Region). Ride is NOT confirmed and vehicle availability is NOT guaranteed until dispatch confirms.'
                    : ($booking->payment_gateway === 'upi_manual'
                        ? ($booking->payment_collection_by === 'driver' && $booking->payment_status === 'driver_collect'
                            ? 'Booking created! Driver will collect ₹'.number_format((float) $booking->total_amount).' on trip completion.'
                            : ($booking->upi_utr
                                ? 'Booking created! Your UPI UTR ('.$booking->upi_utr.') has been submitted for Admin confirmation.'
                                : 'Booking created! Pay to the UPI ID and submit your UTR in My Trips, or pay the driver on completion.'))
                        : 'Trip booked successfully!')),
            'is_beyond_region' => $isBeyondRegion,
            'availability_guaranteed' => ! $isBeyondRegion,
            'region_note' => $booking->region_note,
            'requires_razorpay' => $useRazorpay,
            'upi_details' => $booking->payment_gateway === 'upi_manual' ? [
                'upi_id' => $paymentConfig['upi_id'] ?? 'intercitywheels@upi',
                'upi_payee_name' => $paymentConfig['upi_payee_name'] ?? 'Intercity Wheels',
                'upi_instructions' => $paymentConfig['upi_instructions'] ?? '',
                'upi_qr_url' => $paymentConfig['upi_qr_url'] ?? '',
                'amount' => (float) ($booking->advance_amount > 0 ? $booking->advance_amount : $booking->total_amount),
                'upi_utr' => $booking->upi_utr,
                'payment_status' => $booking->payment_status,
                'payment_collection_by' => $booking->payment_collection_by,
            ] : null,
            'razorpay' => $razorpayOrder ? [
                'key' => $razorpayOrder['key_id'],
                'order_id' => $razorpayOrder['order_id'],
                'amount' => $razorpayOrder['amount_paise'],
                'currency' => $razorpayOrder['currency'],
                'name' => $razorpayOrder['display_name'],
                'description' => "Booking {$booking->booking_reference} ({$booking->route_summary})",
                'payment_mode' => $booking->payment_mode,
                'prefill' => [
                    'name' => $booking->customer_name,
                    'email' => $booking->customer_email ?: (auth()->user()?->email ?? ''),
                    'contact' => $booking->customer_phone,
                ],
            ] : null,
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
     * Submit or update a UPI UTR for Admin verification, or switch to driver collection on completion.
     */
    public function submitUpiUtr(Request $request, string $reference)
    {
        $validated = $request->validate([
            'collection_choice' => 'nullable|string|in:utr,driver_collect',
            'upi_utr' => 'nullable|string|min:6|max:40|regex:/^[A-Za-z0-9\-_]+$/',
        ]);

        $booking = Booking::where('booking_reference', strtoupper(trim($reference)))->firstOrFail();

        if (! $booking->isOwnedBy($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update payment details for this booking.',
            ], 403);
        }

        if ($booking->payment_status === 'paid' && $booking->upi_verified_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This UPI payment has already been verified by Admin.',
            ], 422);
        }

        $choice = $validated['collection_choice'] ?? 'utr';

        if ($choice === 'driver_collect') {
            if (PlatformSetting::get('payment_show_cash', '1') !== '1') {
                throw ValidationException::withMessages([
                    'collection_choice' => 'Driver collection on completion is currently disabled by platform settings.',
                ]);
            }

            $booking->forceFill([
                'payment_collection_by' => 'driver',
                'payment_status' => 'driver_collect',
                'advance_amount' => 0.0,
                'balance_amount' => (float) $booking->total_amount,
                'upi_verified_at' => null,
                'upi_verified_by' => null,
                'payment_notes' => 'Customer selected driver collection on trip completion',
            ])->save();

            $fresh = $booking->fresh(['driver']);
            PaymentAttempt::create([
                'booking_id' => $fresh->id,
                'user_id' => $request->user()?->id,
                'payment_mode' => (string) $fresh->payment_mode,
                'payment_channel' => 'driver_collect',
                'amount' => (float) $fresh->total_amount,
                'status' => 'driver_collect',
                'notes' => 'Customer switched to driver collection on trip completion',
                'attempted_at' => now(),
            ]);
            $this->notifications->upiUtrSubmitted($fresh);

            return response()->json([
                'success' => true,
                'message' => 'Updated! Your assigned driver will collect ₹'.number_format((float) $fresh->total_amount).' when the drive is completed.',
                'booking' => $fresh,
            ]);
        }

        $utr = strtoupper(trim((string) ($validated['upi_utr'] ?? '')));
        if ($utr === '') {
            throw ValidationException::withMessages([
                'upi_utr' => 'Please enter your UPI UTR / Transaction Reference number.',
            ]);
        }

        $isPartPay = $booking->payment_mode === 'part_pay';
        $advAmt = $isPartPay && (float) $booking->advance_amount > 0
            ? (float) $booking->advance_amount
            : (float) $booking->total_amount;
        $balAmt = $isPartPay
            ? round(max(0.0, ((float) $booking->total_amount) - $advAmt), 2)
            : 0.0;

        $booking->forceFill([
            'payment_mode' => $isPartPay ? 'part_pay' : ($booking->payment_mode ?: 'full_upi'),
            'payment_gateway' => 'upi_manual',
            'upi_utr' => $utr,
            'payment_collection_by' => ($isPartPay && $balAmt > 0) ? 'driver' : 'platform',
            'payment_status' => 'pending_upi_verification',
            'advance_amount' => $advAmt,
            'balance_amount' => $balAmt,
            'upi_verified_at' => null,
            'upi_verified_by' => null,
            'payment_notes' => 'UTR submitted by customer — pending Admin verification',
        ])->save();

        $fresh = $booking->fresh(['driver']);
        PaymentAttempt::create([
            'booking_id' => $fresh->id,
            'user_id' => $request->user()?->id,
            'payment_mode' => (string) $fresh->payment_mode,
            'payment_channel' => 'manual_upi',
            'amount' => $advAmt,
            'status' => 'utr_submitted',
            'upi_utr' => $utr,
            'notes' => 'Customer submitted UTR for Admin verification',
            'attempted_at' => now(),
        ]);
        $this->notifications->upiUtrSubmitted($fresh);

        return response()->json([
            'success' => true,
            'message' => "UTR {$utr} submitted! Admin will confirm your UPI payment shortly.",
            'booking' => $fresh,
        ]);
    }

    /**
     * Create (or retrieve) a Razorpay payment order for an unpaid customer booking.
     */
    public function createRazorpayOrder(Request $request, string $reference)
    {
        $booking = Booking::where('booking_reference', strtoupper(trim($reference)))->firstOrFail();

        if (! $booking->isOwnedBy($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to pay for this booking.',
            ], 403);
        }

        if (in_array($booking->payment_status, ['paid', 'partial_paid'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This booking is already paid.',
            ], 422);
        }

        $payable = (float) ($booking->advance_amount ?? $booking->total_amount);

        try {
            $order = $this->razorpay->createOrder($booking, $payable);
            $booking->forceFill([
                'payment_gateway' => 'razorpay',
                'payment_status' => 'pending',
                'razorpay_order_id' => $order['order_id'],
            ])->save();

            PaymentAttempt::create([
                'booking_id' => $booking->id,
                'user_id' => $request->user()?->id,
                'payment_mode' => (string) ($booking->payment_mode ?: 'full_online'),
                'payment_channel' => 'razorpay',
                'amount' => $payable,
                'status' => 'initiated',
                'gateway_order_id' => $order['order_id'],
                'notes' => 'Razorpay order created / retry attempt',
                'attempted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Razorpay gateway error: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'razorpay' => [
                'key' => $order['key_id'],
                'order_id' => $order['order_id'],
                'amount' => $order['amount_paise'],
                'currency' => $order['currency'],
                'name' => $order['display_name'],
                'description' => "Booking {$booking->booking_reference} ({$booking->route_summary})",
                'payment_mode' => $booking->payment_mode,
                'prefill' => [
                    'name' => $booking->customer_name,
                    'email' => $booking->customer_email ?: (auth()->user()?->email ?? ''),
                    'contact' => $booking->customer_phone,
                ],
            ],
            'booking' => $booking->fresh(),
        ]);
    }

    /**
     * Verify Razorpay Checkout signature and confirm the booking.
     */
    public function verifyRazorpayPayment(Request $request, string $reference)
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string|max:120',
            'razorpay_payment_id' => 'required|string|max:120',
            'razorpay_signature' => 'required|string|max:255',
        ]);

        $booking = Booking::where('booking_reference', strtoupper(trim($reference)))->firstOrFail();

        if (! $booking->isOwnedBy($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to verify payment for this booking.',
            ], 403);
        }

        if ($booking->razorpay_order_id !== $validated['razorpay_order_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Razorpay order ID does not match this booking.',
            ], 422);
        }

        if (! $this->razorpay->verifySignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Razorpay payment signature.',
            ], 422);
        }

        if (in_array($booking->payment_status, ['paid', 'partial_paid'], true)
            && $booking->razorpay_payment_id === $validated['razorpay_payment_id']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment already verified.',
                'booking' => $booking->fresh(),
            ]);
        }

        $confirmedBooking = DB::transaction(function () use ($booking, $validated) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $targetStatus = ($locked->status === BookingWorkflow::REQUESTED && ! $locked->is_beyond_region)
                ? BookingWorkflow::CONFIRMED
                : (string) $locked->status;

            $paidStatus = $locked->payment_mode === 'part_pay' ? 'partial_paid' : 'paid';

            return $this->workflow->transition($locked, $targetStatus, [
                'payment_gateway' => 'razorpay',
                'payment_status' => $paidStatus,
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature' => $validated['razorpay_signature'],
            ]);
        });

        $attempt = PaymentAttempt::where('booking_id', $confirmedBooking->id)
            ->where('gateway_order_id', $validated['razorpay_order_id'])
            ->latest('id')
            ->first();
        if ($attempt) {
            $attempt->forceFill([
                'status' => 'verified',
                'gateway_payment_id' => $validated['razorpay_payment_id'],
                'resolved_at' => now(),
            ])->save();
        } else {
            PaymentAttempt::create([
                'booking_id' => $confirmedBooking->id,
                'user_id' => $request->user()?->id,
                'payment_mode' => (string) ($confirmedBooking->payment_mode ?: 'full_online'),
                'payment_channel' => 'razorpay',
                'amount' => (float) ($confirmedBooking->advance_amount ?? $confirmedBooking->total_amount),
                'status' => 'verified',
                'gateway_order_id' => $validated['razorpay_order_id'],
                'gateway_payment_id' => $validated['razorpay_payment_id'],
                'attempted_at' => now(),
                'resolved_at' => now(),
            ]);
        }

        if ($confirmedBooking->service_type !== 'bus') {
            $this->bidding->openForNewBooking($confirmedBooking);
        }

        $this->notifications->bookingCreated($confirmedBooking, 'razorpay');

        return response()->json([
            'success' => true,
            'message' => 'Payment verified via Razorpay! Trip confirmed.',
            'booking' => $confirmedBooking->fresh(),
        ]);
    }

    /**
     * List all bookings belonging to the signed-in customer (with allotted driver details).
     */
    public function myTrips(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'bookings' => []], 401);
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $user->phone) ?? '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        $email = strtolower(trim((string) $user->email));

        $bookings = Booking::with('driver')
            ->where(function ($query) use ($user, $last10, $email) {
                $query->where('customer_user_id', $user->id);

                if ($email !== '') {
                    $query->orWhereRaw('LOWER(customer_email) = ?', [$email]);
                }

                if (strlen($last10) === 10) {
                    $query->orWhere('customer_phone', 'like', '%'.$last10.'%')
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?",
                            ['%'.$last10]
                        );
                }
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
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
