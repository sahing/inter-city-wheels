<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidBookingTransition;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverBid;
use App\Models\DriverDueSettlement;
use App\Models\DriverPayoutRequest;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingWorkflow;
use App\Services\DriverPaymentHistoryService;
use App\Services\NotificationService;
use App\Services\RazorpayService;
use App\Support\IndianPhone;
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
        private readonly NotificationService $notifications,
        private readonly DriverPaymentHistoryService $paymentHistory,
        private readonly RazorpayService $razorpay,
    ) {}

    public function myTrips(Request $request)
    {
        $driver = $this->driverForUser($request->user());
        if (! $driver) {
            return response()->json([
                'success' => true,
                'driver_id' => null,
                'trips' => [],
            ]);
        }

        $trips = Booking::with(['driver', 'assignedBid', 'walletTransactions'])
            ->where('assigned_driver_id', $driver->id)
            ->where('status', '!=', 'Cancelled')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'driver_id' => $driver->id,
            'trips' => $trips,
        ]);
    }

    public function paymentHistory(Request $request)
    {
        $driver = $this->driverForUser($request->user());
        if (! $driver) {
            return response()->json([
                'success' => true,
                'driver_id' => null,
                ...$this->paymentHistory->buildForDriver(null),
            ]);
        }

        return response()->json([
            'success' => true,
            'driver_id' => $driver->id,
            ...$this->paymentHistory->buildForDriver($driver),
        ]);
    }

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

        $booking->load('driver');
        $this->notifications->bookingCreated($booking, 'bid_accept');
        $this->notifications->driverAllotted($booking, $driver, (float) $validated['amount']);

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

        $startedFresh = $started->fresh(['driver', 'customer']);
        $this->notifications->tripStarted($startedFresh);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully! Trip has commenced.',
            'booking' => $startedFresh,
        ]);
    }

    public function completeTrip(Request $request, $id)
    {
        $validated = $request->validate([
            'collected_by_driver' => 'nullable|boolean',
            'collected_amount' => 'nullable|numeric|min:0',
            'actual_distance_km' => 'nullable|integer|min:0|max:50000',
        ]);

        $booking = Booking::findOrFail($id);

        $authorization = $this->authorizeTrip($request->user(), $booking);

        if ($authorization !== null) {
            return $authorization;
        }

        try {
            $result = DB::transaction(function () use ($booking, $validated, $request) {
                $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

                $completionAttrs = [];
                $fareDelta = 0.0;

                if ($request->filled('actual_distance_km') && $validated['actual_distance_km'] !== null) {
                    $actualKm = max(0, (int) $validated['actual_distance_km']);
                    $breakdown = is_array($locked->fare_breakdown) ? $locked->fare_breakdown : [];
                    $bookedKm = (int) ($locked->booked_distance_km ?: ($breakdown['booked_distance_km'] ?? 0));
                    $extraKmRate = (float) ($locked->extra_km_rate ?: ($breakdown['extra_km_rate'] ?? 0));

                    if ($extraKmRate <= 0) {
                        $fleetKey = $locked->cab_type ?: 'sedan';
                        $btKey = $locked->trip_type ?: 'oneway';
                        $extraKmRate = app(\App\Services\FareCalculatorService::class)
                            ->extraKmRateForFleet($fleetKey, $btKey);
                    }

                    $oldExtraFare = (float) ($locked->extra_km_fare ?? 0);
                    $extraKm = $bookedKm > 0 ? max(0, $actualKm - $bookedKm) : 0;
                    $newExtraFare = $extraKm > 0 ? (float) round($extraKm * $extraKmRate) : 0.0;
                    $fareDelta = round($newExtraFare - $oldExtraFare, 2);
                    $newTotal = max(0.0, round((float) $locked->total_amount + $fareDelta, 2));

                    $breakdown['actual_distance_km'] = $actualKm;
                    $breakdown['extra_km'] = $extraKm;
                    $breakdown['extra_km_rate'] = $extraKmRate;
                    $breakdown['extra_km_fare'] = $newExtraFare;
                    $breakdown['total_amount'] = $newTotal;

                    $completionAttrs['actual_distance_km'] = $actualKm;
                    $completionAttrs['extra_km'] = $extraKm;
                    $completionAttrs['extra_km_rate'] = $extraKmRate;
                    $completionAttrs['extra_km_fare'] = $newExtraFare;
                    $completionAttrs['total_amount'] = $newTotal;
                    $completionAttrs['fare_breakdown'] = $breakdown;

                    if ($locked->driver_payout_amount !== null && $fareDelta != 0.0) {
                        $completionAttrs['driver_payout_amount'] = max(
                            0.0,
                            round((float) $locked->driver_payout_amount + ($fareDelta * (1 - self::PLATFORM_FEE_RATE)), 2)
                        );
                    }
                }

                $collectDue = 0.0;
                if ($locked->driver_collected_at === null) {
                    if ($locked->payment_mode === 'part_pay' && (float) $locked->balance_amount > 0) {
                        $collectDue = round((float) $locked->balance_amount, 2);
                    } elseif (
                        $locked->payment_collection_by === 'driver'
                        || in_array($locked->payment_status, ['pay_on_pickup', 'driver_collect', 'upi_rejected', 'pending_upi_verification', 'awaiting_upi_utr'], true)
                    ) {
                        $collectDue = round((float) ($locked->balance_amount > 0 ? $locked->balance_amount : $locked->total_amount), 2);
                    }
                }

                if ($fareDelta != 0.0) {
                    $collectDue = max(0.0, round($collectDue + $fareDelta, 2));
                    if ((float) $locked->balance_amount > 0 || $fareDelta > 0) {
                        $completionAttrs['balance_amount'] = max(0.0, round((float) $locked->balance_amount + $fareDelta, 2));
                    }
                }

                if ($collectDue > 0 && $request->has('collected_by_driver') && ! $validated['collected_by_driver']) {
                    throw ValidationException::withMessages([
                        'collected_by_driver' => 'Please collect ₹'.number_format($collectDue, 2).' from the customer before completing this trip.',
                    ]);
                }

                if ($collectDue > 0) {
                    $existingNote = trim((string) ($locked->payment_notes ?? ''));
                    $collectionNote = 'Collected ₹'.number_format($collectDue, 2).' by driver on trip completion';
                    $completionAttrs = array_merge($completionAttrs, [
                        'payment_collection_by' => 'driver',
                        'driver_collected_amount' => $collectDue,
                        'driver_collected_at' => now(),
                        'payment_status' => 'paid',
                        'payment_notes' => $existingNote !== '' ? "{$existingNote} | {$collectionNote}" : $collectionNote,
                    ]);
                }

                // Rejects a second completion, which would pay the driver twice.
                $this->workflow->transition($locked, BookingWorkflow::COMPLETED, $completionAttrs, allowSameStatus: false);

                $payout = 0.0;
                $netWalletDelta = 0.0;
                $platformReceived = $collectDue > 0
                    ? round(max(0.0, ((float) $locked->total_amount) - $collectDue), 2)
                    : round((float) $locked->total_amount, 2);

                if ($locked->assigned_driver_id) {
                    $driver = Driver::whereKey($locked->assigned_driver_id)->lockForUpdate()->first();

                    if ($driver) {
                        // A trip won through the driver marketplace pays the
                        // winning bid; a manually allotted trip keeps the legacy
                        // commission split. The stored amount always wins.
                        $payout = $locked->driver_payout_amount !== null
                            ? round((float) $locked->driver_payout_amount, 2)
                            : round(((float) $locked->total_amount) * (1 - self::PLATFORM_FEE_RATE), 2);

                        $netWalletDelta = round($payout - $collectDue, 2);
                        $txDescription = $this->paymentHistory->formatCompletionDescription(
                            $locked,
                            $payout,
                            $collectDue,
                            $netWalletDelta,
                            $platformReceived
                        );

                        if ($netWalletDelta > 0) {
                            $this->creditDriver(
                                $driver,
                                $netWalletDelta,
                                WalletTransaction::REASON_TRIP_PAYOUT,
                                $locked,
                                $txDescription
                            );
                        } elseif ($netWalletDelta < 0) {
                            $this->debitDriver(
                                $driver,
                                abs($netWalletDelta),
                                WalletTransaction::REASON_ADJUSTMENT,
                                $locked,
                                $txDescription
                            );
                        } elseif ($collectDue > 0) {
                            WalletTransaction::create([
                                'driver_id' => $driver->id,
                                'booking_id' => $locked->id,
                                'type' => WalletTransaction::TYPE_CREDIT,
                                'reason' => WalletTransaction::REASON_TRIP_PAYOUT,
                                'amount' => 0.00,
                                'balance_after' => (float) $driver->wallet_balance,
                                'description' => $txDescription,
                            ]);
                        }

                        $driver->trips_count = (int) $driver->trips_count + 1;
                        $driver->save();
                    }
                }

                return [$locked, $payout, $collectDue, $netWalletDelta];
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (InvalidBookingTransition $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'transition' => $e->toArray(),
            ], 422);
        }

        [$completed, $payout, $collectDue, $netWalletDelta] = $result;
        $completedFresh = $completed->fresh(['driver', 'customer', 'assignedBid', 'walletTransactions']);
        if ($collectDue > 0) {
            PaymentAttempt::create([
                'booking_id' => $completedFresh->id,
                'user_id' => $completedFresh->customer_user_id,
                'payment_mode' => (string) ($completedFresh->payment_mode ?: 'cash'),
                'payment_channel' => 'driver_collect',
                'amount' => (float) $collectDue,
                'status' => 'collected_by_driver',
                'notes' => 'Collected by driver upon trip completion',
                'attempted_at' => now(),
                'resolved_at' => now(),
            ]);
        }
        $this->notifications->tripCompleted($completedFresh, (float) $payout);

        $breakdown = $this->paymentHistory->buildTripBreakdown($completedFresh, $completedFresh->assigned_driver_id);

        $msg = $collectDue > 0
            ? 'Trip completed! Recorded ₹'.number_format($collectDue).' collected from customer and settled driver wallet.'
            : 'Trip marked as completed! Payout credited to your driver wallet.';

        return response()->json([
            'success' => true,
            'message' => $msg,
            'payout' => $payout,
            'driver_collected_amount' => $collectDue,
            'net_wallet_delta' => $netWalletDelta,
            'wallet_balance' => (float) ($completedFresh->driver?->wallet_balance ?? 0),
            'trip_breakdown' => $breakdown,
            'booking' => $completedFresh,
        ]);
    }

    public function withdrawPayout(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'payout_method' => 'nullable|string|in:upi,bank',
        ]);

        $driver = $this->driverForUser($request->user());

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver profile is linked to this account.',
            ], 403);
        }

        $amount = round((float) $validated['amount'], 2);
        $explicitMethod = $validated['payout_method'] ?? null;
        $method = $explicitMethod ?? ($driver->preferred_payout_method ?: 'bank');
        $destination = $driver->payoutDestinationFor($method);

        if ($explicitMethod !== null && $destination === null) {
            $missingLabel = $method === 'upi' ? 'UPI ID' : 'Bank Account details (holder, bank, account no. & IFSC)';

            return response()->json([
                'success' => false,
                'message' => "Please save your {$missingLabel} in Driver Profile before requesting a ".strtoupper($method).' payout.',
                'errors' => ['payout_method' => ["{$missingLabel} not configured in Driver Profile."]],
            ], 422);
        }

        if ($destination === null) {
            // Fallback to whichever method is configured, or record profile placeholder for legacy API callers
            if ($driver->hasUpiPayoutConfigured()) {
                $method = 'upi';
                $destination = $driver->payoutDestinationFor('upi');
            } elseif ($driver->hasBankPayoutConfigured()) {
                $method = 'bank';
                $destination = $driver->payoutDestinationFor('bank');
            } else {
                $destination = ['note' => 'Registered driver payout profile'];
            }
        }

        try {
            [$transaction, $payoutRequest] = DB::transaction(function () use ($driver, $amount, $method, $destination, $request) {
                $locked = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();

                // Balance floor: a withdrawal can never overdraw the wallet.
                if ($amount > (float) $locked->wallet_balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Withdrawal exceeds wallet balance of ₹'
                            .number_format((float) $locked->wallet_balance, 2).'.',
                    ]);
                }

                $payoutRef = 'PAY-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
                $destPreview = $method === 'upi'
                    ? (string) ($destination['upi_id'] ?? 'UPI')
                    : ((isset($destination['bank_account_number']) && strlen((string) $destination['bank_account_number']) >= 4)
                        ? (($destination['bank_name'] ?? 'Bank').' •••• '.substr((string) $destination['bank_account_number'], -4))
                        : 'Registered Bank Account');

                $tx = $this->debitDriver(
                    $locked,
                    $amount,
                    WalletTransaction::REASON_WITHDRAWAL,
                    null,
                    "Payout request {$payoutRef} via ".strtoupper($method)." ({$destPreview}) — Pending Admin Approval"
                );

                $pr = DriverPayoutRequest::create([
                    'payout_reference' => $payoutRef,
                    'driver_id' => $locked->id,
                    'user_id' => $request->user()?->id,
                    'amount' => $amount,
                    'payout_method' => $method,
                    'payout_destination' => $destination,
                    'status' => DriverPayoutRequest::STATUS_PENDING,
                    'wallet_transaction_id' => $tx->id,
                    'requested_at' => now(),
                ]);

                return [$tx, $pr];
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $this->notifications->walletWithdrawal($driver, $amount, (float) $transaction->balance_after, $payoutRequest);

        return response()->json([
            'success' => true,
            'message' => "Payout request {$payoutRequest->payout_reference} for ₹".number_format($amount).' submitted for Admin approval. Your wallet balance has been updated.',
            'balance_after' => (float) $transaction->balance_after,
            'transaction_id' => $transaction->id,
            'payout_request' => [
                'id' => $payoutRequest->id,
                'payout_reference' => $payoutRequest->payout_reference,
                'amount' => round((float) $payoutRequest->amount, 2),
                'payout_method' => $payoutRequest->payout_method,
                'destination_summary' => $payoutRequest->destinationSummary(),
                'status' => $payoutRequest->status,
            ],
        ]);
    }

    /**
     * Submit a payment to settle a negative wallet balance (company due)
     * via Payment Gateway (Razorpay) or Manual UPI UTR.
     */
    public function submitDueSettlement(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_channel' => 'required|string|in:gateway,upi_manual',
            'upi_utr' => 'nullable|string|max:80',
            'driver_notes' => 'nullable|string|max:500',
        ]);

        $driver = $this->driverForUser($request->user());
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver profile is linked to this account.',
            ], 403);
        }

        $companyDue = $driver->companyDueAmount();
        if ($companyDue <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Your wallet has no pending company dues to settle.',
            ], 422);
        }

        $amount = round((float) $validated['amount'], 2);
        if ($amount > $companyDue + 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Settlement amount cannot exceed your current company due of ₹'.number_format($companyDue, 2).'.',
            ], 422);
        }

        $channel = $validated['payment_channel'];
        $utr = strtoupper(trim((string) ($validated['upi_utr'] ?? '')));

        if ($channel === DriverDueSettlement::CHANNEL_UPI_MANUAL && strlen($utr) < 6) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid UPI UTR / Transaction Reference ID (at least 6 characters).',
            ], 422);
        }

        if ($channel === DriverDueSettlement::CHANNEL_GATEWAY && ! $this->razorpay->isReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment Gateway is not active right now. Please use Manual UPI transfer.',
            ], 422);
        }

        $reference = 'DUE-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $settlement = DriverDueSettlement::create([
            'settlement_reference' => $reference,
            'driver_id' => $driver->id,
            'user_id' => $request->user()?->id,
            'amount' => $amount,
            'payment_channel' => $channel,
            'status' => DriverDueSettlement::STATUS_PENDING_VERIFICATION,
            'upi_utr' => $channel === DriverDueSettlement::CHANNEL_UPI_MANUAL ? $utr : null,
            'driver_notes' => trim((string) ($validated['driver_notes'] ?? '')) ?: null,
            'submitted_at' => now(),
        ]);

        if ($channel === DriverDueSettlement::CHANNEL_GATEWAY) {
            try {
                $order = $this->razorpay->createCustomOrder(
                    receipt: $settlement->settlement_reference,
                    amountInr: $amount,
                    notes: [
                        'settlement_reference' => $settlement->settlement_reference,
                        'driver_id' => (string) $driver->id,
                        'driver_name' => (string) $driver->name,
                        'purpose' => 'driver_company_due_settlement',
                    ]
                );
            } catch (\Throwable $e) {
                $settlement->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Could not initiate Payment Gateway order: '.$e->getMessage(),
                ], 422);
            }

            $settlement->forceFill(['razorpay_order_id' => $order['order_id']])->save();

            return response()->json([
                'success' => true,
                'message' => 'Complete the payment in Razorpay to settle your company due.',
                'settlement' => $settlement->fresh(),
                'razorpay_order' => $order,
            ], 201);
        }

        $this->notifications->dueSettlementSubmitted($settlement);

        return response()->json([
            'success' => true,
            'message' => "Company due payment {$settlement->settlement_reference} (UTR: {$utr}) submitted for Admin verification.",
            'settlement' => $settlement->fresh(),
        ], 201);
    }

    /**
     * Verify Razorpay signature for a driver's company due settlement and credit wallet immediately.
     */
    public function verifyDueSettlementGateway(Request $request, int $id)
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string|max:120',
            'razorpay_payment_id' => 'required|string|max:120',
            'razorpay_signature' => 'required|string|max:200',
        ]);

        $driver = $this->driverForUser($request->user());
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'No driver profile is linked to this account.'], 403);
        }

        $settlement = DriverDueSettlement::where('id', $id)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        if ($settlement->status === DriverDueSettlement::STATUS_PAID) {
            return response()->json([
                'success' => true,
                'message' => 'Company due settlement already verified.',
                'settlement' => $settlement,
                'wallet_balance' => round((float) $driver->wallet_balance, 2),
            ]);
        }

        if ($settlement->razorpay_order_id && $settlement->razorpay_order_id !== $validated['razorpay_order_id']) {
            return response()->json(['success' => false, 'message' => 'Order ID mismatch for due settlement.'], 422);
        }

        if (! $this->razorpay->verifySignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        )) {
            return response()->json(['success' => false, 'message' => 'Payment signature verification failed.'], 422);
        }

        $updated = DB::transaction(function () use ($settlement, $driver, $validated) {
            $locked = DriverDueSettlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === DriverDueSettlement::STATUS_PAID) {
                return $locked;
            }

            $lockedDriver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $lockedDriver->wallet_balance = round((float) $lockedDriver->wallet_balance + (float) $locked->amount, 2);
            $lockedDriver->save();

            $tx = WalletTransaction::create([
                'driver_id' => $lockedDriver->id,
                'booking_id' => null,
                'type' => WalletTransaction::TYPE_CREDIT,
                'reason' => WalletTransaction::REASON_ADJUSTMENT,
                'amount' => (float) $locked->amount,
                'balance_after' => $lockedDriver->wallet_balance,
                'description' => "Company due settled via Payment Gateway {$locked->settlement_reference} (Pay ID {$validated['razorpay_payment_id']})",
            ]);

            $locked->forceFill([
                'status' => DriverDueSettlement::STATUS_PAID,
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature' => $validated['razorpay_signature'],
                'wallet_transaction_id' => $tx->id,
                'verified_at' => now(),
            ])->save();

            return $locked->fresh('driver');
        });

        $this->notifications->dueSettlementVerified($updated, true);

        return response()->json([
            'success' => true,
            'message' => 'Payment verified! ₹'.number_format((float) $updated->amount, 2).' credited to your wallet.',
            'settlement' => $updated,
            'wallet_balance' => round((float) ($updated->driver?->wallet_balance ?? 0), 2),
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
     * Update current driver profile credentials (and optional payout settings).
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
            'preferred_payout_method' => 'nullable|string|in:upi,bank',
            'payout_upi_id' => ['nullable', 'string', 'max:120', 'regex:/^[\w.\-]{2,}@[A-Za-z0-9.\-]{2,}$/'],
            'payout_upi_name' => 'nullable|string|max:120',
            'bank_account_holder' => 'nullable|string|max:120',
            'bank_name' => 'nullable|string|max:120',
            'bank_account_number' => ['nullable', 'string', 'min:6', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/'],
            'bank_ifsc' => ['nullable', 'string', 'size:11', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
        ]);

        $driver->name = trim($validated['name']);
        $driver->phone = IndianPhone::format($validated['phone']);
        $driver->vehicle_model = trim($validated['vehicle_model']);
        $driver->vehicle_plate = strtoupper(trim($validated['vehicle_plate']));
        $driver->fleet_category = strtolower(trim($validated['fleet_category']));
        if (! empty($validated['avatar'])) {
            $driver->avatar = $validated['avatar'];
        }
        if (array_key_exists('city', $validated)) {
            $driver->city = trim((string) ($validated['city'] ?? '')) ?: $driver->city;
        }
        $this->applyPayoutFields($driver, $request, $validated);
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
            'profile' => $driver->fresh(),
            'driver' => $driver->fresh(),
        ]);
    }

    /**
     * Save driver's UPI and/or Bank payout collection details.
     */
    public function updatePayoutDetails(Request $request)
    {
        $driver = $this->driverForUser($request->user());

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver profile is linked to this account.',
            ], 403);
        }

        $validated = $request->validate([
            'preferred_payout_method' => 'required|string|in:upi,bank',
            'payout_upi_id' => ['nullable', 'string', 'max:120', 'regex:/^[\w.\-]{2,}@[A-Za-z0-9.\-]{2,}$/'],
            'payout_upi_name' => 'nullable|string|max:120',
            'bank_account_holder' => 'nullable|string|max:120',
            'bank_name' => 'nullable|string|max:120',
            'bank_account_number' => ['nullable', 'string', 'min:6', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/'],
            'bank_ifsc' => ['nullable', 'string', 'size:11', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
        ]);

        $pref = $validated['preferred_payout_method'];
        $upiId = trim((string) ($validated['payout_upi_id'] ?? ''));
        $bankHolder = trim((string) ($validated['bank_account_holder'] ?? ''));
        $bankName = trim((string) ($validated['bank_name'] ?? ''));
        $bankAcct = trim((string) ($validated['bank_account_number'] ?? ''));
        $bankIfsc = strtoupper(trim((string) ($validated['bank_ifsc'] ?? '')));

        if ($pref === 'upi' && $upiId === '') {
            throw ValidationException::withMessages([
                'payout_upi_id' => 'Enter a valid UPI ID (e.g. driver@okicici) when UPI is selected as preferred payout method.',
            ]);
        }

        if ($pref === 'bank' && ($bankHolder === '' || $bankName === '' || $bankAcct === '' || $bankIfsc === '')) {
            throw ValidationException::withMessages([
                'bank_account_number' => 'Enter Account Holder Name, Bank Name, Account Number, and IFSC Code for Bank payouts.',
            ]);
        }

        $this->applyPayoutFields($driver, $request, $validated);
        $driver->save();

        $fresh = $driver->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Driver payout details saved! Ready for '.strtoupper($fresh->preferred_payout_method).' withdrawals.',
            'profile' => $fresh,
            'payout_summary' => $fresh->payoutSummaryLabel(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyPayoutFields(Driver $driver, Request $request, array $validated): void
    {
        if (! empty($validated['preferred_payout_method'])) {
            $driver->preferred_payout_method = $validated['preferred_payout_method'];
        }
        if ($request->has('payout_upi_id')) {
            $driver->payout_upi_id = trim((string) ($validated['payout_upi_id'] ?? '')) ?: null;
        }
        if ($request->has('payout_upi_name')) {
            $driver->payout_upi_name = trim((string) ($validated['payout_upi_name'] ?? '')) ?: null;
        }
        if ($request->has('bank_account_holder')) {
            $driver->bank_account_holder = trim((string) ($validated['bank_account_holder'] ?? '')) ?: null;
        }
        if ($request->has('bank_name')) {
            $driver->bank_name = trim((string) ($validated['bank_name'] ?? '')) ?: null;
        }
        if ($request->has('bank_account_number')) {
            $driver->bank_account_number = trim((string) ($validated['bank_account_number'] ?? '')) ?: null;
        }
        if ($request->has('bank_ifsc')) {
            $ifsc = strtoupper(trim((string) ($validated['bank_ifsc'] ?? '')));
            $driver->bank_ifsc = $ifsc !== '' ? $ifsc : null;
        }
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

        $driver = $user->driverProfile();
        if ($driver) {
            return $driver;
        }

        if ($user->isAdmin()) {
            return Driver::where('is_verified', true)->first() ?? Driver::first();
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
