<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransition;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\PaymentAttempt;
use App\Models\PlatformSetting;
use App\Services\BiddingService;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingWorkflow;
use App\Services\NotificationService;
use App\Support\IndianPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingAdminController extends Controller
{
    public function __construct(
        private readonly BookingWorkflow $workflow,
        private readonly BookingReferenceGenerator $references,
        private readonly BiddingService $bidding,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Return list of bookings with drivers.
     */
    public function index(Request $request)
    {
        $query = Booking::with('driver');

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->whereNotIn('status', ['Completed', 'Cancelled']);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('service_type') && $request->service_type !== 'all') {
            $query->where('service_type', $request->service_type);
        }

        if ($request->filled('trip_type') && $request->trip_type !== 'all') {
            $query->where('trip_type', $request->trip_type);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('booking_reference', 'like', "%{$s}%")
                    ->orWhere('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_phone', 'like', "%{$s}%")
                    ->orWhere('route_summary', 'like', "%{$s}%")
                    ->orWhere('pickup_location', 'like', "%{$s}%")
                    ->orWhere('drop_location', 'like', "%{$s}%");
            });
        }

        $sortBy = strtolower(trim((string) $request->query('sort_by', 'dispatch_default')));
        $sortDir = strtolower(trim((string) $request->query('sort_dir', 'asc'))) === 'desc' ? 'desc' : 'asc';

        match ($sortBy) {
            'oldest', 'date_asc', 'created_asc' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'newest', 'date_desc', 'created_desc' => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
            'amount', 'fare', 'total_amount' => $query->orderBy('total_amount', $sortDir)->orderBy('id', 'asc'),
            'amount_asc' => $query->orderBy('total_amount', 'asc')->orderBy('id', 'asc'),
            'amount_desc' => $query->orderBy('total_amount', 'desc')->orderBy('id', 'desc'),
            'customer' => $query->orderBy('customer_name', $sortDir)->orderBy('id', 'asc'),
            'reference' => $query->orderBy('booking_reference', $sortDir)->orderBy('id', 'asc'),
            'status' => $query->orderByRaw("
                CASE status
                    WHEN 'Requested' THEN 0
                    WHEN 'Bidding' THEN 1
                    WHEN 'Confirmed' THEN 2
                    WHEN 'Driver Assigned' THEN 3
                    WHEN 'Started' THEN 4
                    WHEN 'Cancelled' THEN 5
                    WHEN 'Completed' THEN 6
                    ELSE 7
                END ".($sortDir === 'desc' ? 'DESC' : 'ASC')
            )->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            default => $query->dispatchOrder(),
        };

        return response()->json([
            'success' => true,
            'bookings' => $query->get(),
        ]);
    }

    /**
     * Store a new booking created manually by admin.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|in:cab,bus',
            'cab_type' => 'nullable|string|max:30',
            'trip_type' => 'required|string',
            'from_city' => 'required|string',
            'to_city' => 'required|string',
            'pickup_location' => 'required|string',
            'drop_location' => 'required|string',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email',
            'departure_time' => 'nullable|string',
            'base_fare' => 'required|numeric|min:0',
            'toll_fare' => 'nullable|numeric|min:0',
            'addon_fare' => 'nullable|numeric|min:0',
            'discount_fare' => 'nullable|numeric|min:0',
            'booked_distance_km' => 'nullable|integer|min:0|max:50000',
            'actual_distance_km' => 'nullable|integer|min:0|max:50000',
            'extra_km' => 'nullable|integer|min:0|max:50000',
            'extra_km_rate' => 'nullable|numeric|min:0',
            'extra_km_fare' => 'nullable|numeric|min:0',
            'route_additional_fare' => 'nullable|numeric|min:0',
            'custom_price_fields_fare' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'assigned_driver_id' => 'nullable|exists:drivers,id',
        ]);

        $baseFare = (float) $validated['base_fare'];
        $tollFare = (float) ($validated['toll_fare'] ?? 0);
        $addonFare = (float) ($validated['addon_fare'] ?? 0);
        $discountFare = (float) ($validated['discount_fare'] ?? 0);
        $bookedKm = (int) ($validated['booked_distance_km'] ?? 0);
        $actualKm = (int) ($validated['actual_distance_km'] ?? $bookedKm);
        $extraKmRate = (float) ($validated['extra_km_rate'] ?? 0);
        $extraKm = isset($validated['extra_km'])
            ? (int) $validated['extra_km']
            : ($bookedKm > 0 ? max(0, $actualKm - $bookedKm) : 0);
        $extraKmFare = isset($validated['extra_km_fare'])
            ? (float) $validated['extra_km_fare']
            : (float) round($extraKm * $extraKmRate);
        $routeAdditionalFare = (float) ($validated['route_additional_fare'] ?? 0);
        $customPriceFieldsFare = (float) ($validated['custom_price_fields_fare'] ?? 0);
        $totalAmount = max(0, round($baseFare + $tollFare + $extraKmFare + $routeAdditionalFare + $addonFare + $customPriceFieldsFare - $discountFare));

        $bookingRef = $this->references->generate();
        $otp = $this->references->startOtp();

        $requestedStatus = $validated['status']
            ?? ($request->filled('assigned_driver_id') ? BookingWorkflow::DRIVER_ASSIGNED : BookingWorkflow::CONFIRMED);

        if (! BookingWorkflow::isKnownStatus($requestedStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown booking status "'.$requestedStatus.'".',
                'allowed' => BookingWorkflow::STATUSES,
            ], 422);
        }

        $attributes = [
            'booking_reference' => $bookingRef,
            'service_type' => $validated['service_type'],
            'trip_type' => $validated['trip_type'],
            'route_summary' => "{$validated['from_city']} → {$validated['to_city']}",
            'pickup_location' => $validated['pickup_location'],
            'drop_location' => $validated['drop_location'],
            'departure_time' => ! empty($validated['departure_time'] ?? null) ? date('Y-m-d H:i:s', strtotime($validated['departure_time'])) : now()->addHours(2),
            'customer_name' => $validated['customer_name'],
            'customer_phone' => IndianPhone::format($validated['customer_phone']),
            'customer_email' => $validated['customer_email'] ?? null,
            'base_fare' => $baseFare,
            'toll_fare' => $tollFare,
            'addon_fare' => $addonFare,
            'discount_fare' => $discountFare,
            'booked_distance_km' => $bookedKm,
            'actual_distance_km' => $actualKm,
            'extra_km' => $extraKm,
            'extra_km_rate' => $extraKmRate,
            'extra_km_fare' => $extraKmFare,
            'route_additional_fare' => $routeAdditionalFare,
            'custom_price_fields_fare' => $customPriceFieldsFare,
            'total_amount' => $totalAmount,
            'start_otp' => $otp,
            'assigned_driver_id' => $validated['assigned_driver_id'] ?? null,
            'selected_units' => [
                $validated['service_type'] === 'cab'
                    ? (! empty($validated['cab_type']) ? strtolower(trim($validated['cab_type'])) : 'Admin Assigned')
                    : 'Sleeper Seat',
            ],
        ];

        try {
            // Created as Requested, then advanced along a legal path only (P0-5).
            $booking = $this->workflow->create($attributes, $requestedStatus, [
                'assigned_driver_id' => $attributes['assigned_driver_id'],
            ]);
        } catch (InvalidBookingTransition $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'transition' => $e->toArray(),
            ], 422);
        }

        $this->bidding->openForNewBooking($booking);

        $booking->load('driver');
        $this->notifications->bookingCreated($booking, 'admin');
        if ($booking->driver) {
            $this->notifications->driverAllotted($booking, $booking->driver);
        }

        return response()->json([
            'success' => true,
            'message' => 'New booking created successfully!',
            'booking' => $booking,
        ], 201);
    }

    /**
     * Show single booking details with driver.
     */
    public function show($id)
    {
        $booking = Booking::with([
            'driver',
            'customer',
            'upiVerifier',
            'assignedBid',
            'biddingSessions',
            'paymentAttempts.user',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'booking' => $booking,
        ]);
    }

    /**
     * Update an existing booking.
     */
    public function update(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'customer_name' => 'sometimes|required|string|max:120',
            'customer_phone' => 'sometimes|required|string|max:20',
            'customer_email' => 'nullable|email',
            'pickup_location' => 'sometimes|required|string',
            'drop_location' => 'sometimes|required|string',
            'route_summary' => 'sometimes|nullable|string',
            'service_type' => 'sometimes|required|in:cab,bus',
            'cab_type' => 'sometimes|nullable|string|max:30',
            'trip_type' => 'sometimes|nullable|string',
            'departure_time' => 'nullable|string',
            'base_fare' => 'sometimes|numeric|min:0',
            'toll_fare' => 'sometimes|numeric|min:0',
            'addon_fare' => 'sometimes|numeric|min:0',
            'discount_fare' => 'sometimes|numeric|min:0',
            'booked_distance_km' => 'sometimes|nullable|integer|min:0|max:50000',
            'actual_distance_km' => 'sometimes|nullable|integer|min:0|max:50000',
            'extra_km' => 'sometimes|nullable|integer|min:0|max:50000',
            'extra_km_rate' => 'sometimes|nullable|numeric|min:0',
            'extra_km_fare' => 'sometimes|nullable|numeric|min:0',
            'route_additional_fare' => 'sometimes|nullable|numeric|min:0',
            'custom_price_fields_fare' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|required|string',
            'start_otp' => 'sometimes|nullable|string|max:6',
            'assigned_driver_id' => 'nullable|exists:drivers,id',
        ]);

        if (
            array_key_exists('actual_distance_km', $validated)
            && ! array_key_exists('extra_km_fare', $validated)
        ) {
            $bookedKm = (int) ($validated['booked_distance_km'] ?? $booking->booked_distance_km ?? 0);
            $actualKm = (int) ($validated['actual_distance_km'] ?? $bookedKm);
            $extraKmRate = (float) ($validated['extra_km_rate'] ?? $booking->extra_km_rate ?? 0);
            if ($extraKmRate <= 0) {
                $extraKmRate = app(\App\Services\FareCalculatorService::class)->extraKmRateForFleet(
                    $validated['cab_type'] ?? $booking->cab_type ?? 'sedan',
                    $validated['trip_type'] ?? $booking->trip_type ?? 'oneway'
                );
            }
            $extraKm = $bookedKm > 0 ? max(0, $actualKm - $bookedKm) : max(0, (int) ($validated['extra_km'] ?? 0));
            $validated['extra_km'] = $extraKm;
            $validated['extra_km_rate'] = $extraKmRate;
            $validated['extra_km_fare'] = (float) round($extraKm * $extraKmRate);
        }

        if (
            isset($validated['base_fare'])
            || isset($validated['toll_fare'])
            || isset($validated['addon_fare'])
            || isset($validated['discount_fare'])
            || isset($validated['extra_km_fare'])
            || isset($validated['route_additional_fare'])
            || isset($validated['custom_price_fields_fare'])
        ) {
            $baseFare = (float) ($validated['base_fare'] ?? $booking->base_fare);
            $tollFare = (float) ($validated['toll_fare'] ?? $booking->toll_fare);
            $addonFare = (float) ($validated['addon_fare'] ?? $booking->addon_fare);
            $discountFare = (float) ($validated['discount_fare'] ?? $booking->discount_fare);
            $extraKmFare = (float) ($validated['extra_km_fare'] ?? $booking->extra_km_fare ?? 0);
            $routeAdditionalFare = (float) ($validated['route_additional_fare'] ?? $booking->route_additional_fare ?? 0);
            $customPriceFieldsFare = (float) ($validated['custom_price_fields_fare'] ?? $booking->custom_price_fields_fare ?? 0);
            $validated['total_amount'] = max(
                0,
                round($baseFare + $tollFare + $extraKmFare + $routeAdditionalFare + $addonFare + $customPriceFieldsFare - $discountFare)
            );
        }

        $cabType = $validated['cab_type'] ?? null;
        unset($validated['cab_type']);

        if (isset($validated['customer_phone'])) {
            $validated['customer_phone'] = IndianPhone::format($validated['customer_phone']);
        }

        $effectiveServiceType = $validated['service_type'] ?? $booking->service_type;
        if ($effectiveServiceType === 'cab' && ! empty($cabType)) {
            $validated['selected_units'] = [strtolower(trim($cabType))];
        }

        if (! empty($validated['departure_time'])) {
            $validated['departure_time'] = date('Y-m-d H:i:s', strtotime($validated['departure_time']));
        }

        $previousStatus = (string) $booking->status;
        $previousDriverId = $booking->assigned_driver_id;
        $newStatus = $validated['status'] ?? null;

        if ($newStatus === null) {
            $booking->update($validated);
        } else {
            if (! BookingWorkflow::isKnownStatus($newStatus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unknown booking status "'.$newStatus.'".',
                    'allowed' => BookingWorkflow::STATUSES,
                ], 422);
            }

            unset($validated['status']);

            $fromStatus = (string) $booking->status;

            if ($fromStatus !== $newStatus) {
                // Admin override: the ops console deliberately is not bound by
                // the stage order, so a trip can be corrected or reopened at
                // any point. Customer/driver flows still use transition().
                Log::warning('Admin override: booking stage changed outside the workflow order.', [
                    'booking' => $booking->booking_reference,
                    'from' => $fromStatus,
                    'to' => $newStatus,
                    'admin_id' => $request->user()?->id,
                ]);
            }

            try {
                if (in_array($newStatus, [BookingWorkflow::CONFIRMED, BookingWorkflow::DRIVER_ASSIGNED, BookingWorkflow::STARTED, BookingWorkflow::COMPLETED], true)) {
                    $validated['availability_status'] = 'confirmed';
                }
                $this->workflow->forceTransition($booking, $newStatus, $validated);
            } catch (InvalidBookingTransition $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'transition' => $e->toArray(),
                ], 422);
            }
        }

        $booking->load('driver');

        if ($booking->assigned_driver_id !== null || $booking->status === BookingWorkflow::DRIVER_ASSIGNED) {
            $this->bidding->syncWithDriverAllotment($booking, $booking->assigned_driver_id);
        }

        if ($booking->assigned_driver_id !== $previousDriverId) {
            $this->notifications->driverAllotted($booking, $booking->driver);
        } elseif ($newStatus !== null && $previousStatus !== (string) $booking->status) {
            $this->notifications->bookingStatusChanged($booking, $previousStatus, (string) $booking->status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully!',
            'booking' => $booking,
        ]);
    }

    /**
     * Allot or change assigned driver for a booking.
     */
    public function allotDriver(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
            'status' => 'nullable|string',
        ]);

        $driverId = $validated['driver_id'] ?? null;
        $driverId = $driverId === null ? null : (int) $driverId;

        $requestedStatus = $validated['status'] ?? null;

        // The allot-driver form sends "" for "keep the current stage".
        if ($requestedStatus === '') {
            $requestedStatus = null;
        }

        if ($requestedStatus !== null && ! BookingWorkflow::isKnownStatus($requestedStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown booking status "'.$requestedStatus.'".',
                'allowed' => BookingWorkflow::STATUSES,
            ], 422);
        }

        $targetStatus = $requestedStatus
            ?? $this->statusAfterAllotment((string) $booking->status, $driverId);

        try {
            // Admin override: a chauffeur can be allotted or replaced at any
            // stage, and an explicitly chosen stage wins even when the trip
            // already moved past it.
            $this->workflow->forceTransition($booking, $targetStatus, [
                'assigned_driver_id' => $driverId,
                'availability_status' => $driverId !== null ? 'confirmed' : ($booking->is_beyond_region ? 'unconfirmed' : $booking->availability_status),
            ]);
        } catch (InvalidBookingTransition $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'transition' => $e->toArray(),
            ], 422);
        }

        $this->bidding->syncWithDriverAllotment($booking, $driverId);

        $booking->load('driver');
        $this->notifications->driverAllotted($booking, $booking->driver);

        return response()->json([
            'success' => true,
            'message' => $driverId ? 'Driver allotted successfully!' : 'Driver allotment removed.',
            'booking' => $booking,
        ]);
    }

    /**
     * Admin verifies a customer's UPI UTR or assigns collection responsibility to the driver.
     */
    public function verifyPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:confirm_utr,reject_assign_driver,mark_paid',
            'upi_utr' => 'nullable|string|min:6|max:40|regex:/^[A-Za-z0-9\-_]+$/',
            'payment_notes' => 'nullable|string|max:255',
        ]);

        $booking = Booking::findOrFail($id);
        $action = $validated['action'];
        $submittedUtr = ! empty($validated['upi_utr'])
            ? strtoupper(trim((string) $validated['upi_utr']))
            : null;
        $effectiveUtr = $submittedUtr ?: ($booking->upi_utr ?: null);

        if ($action === 'confirm_utr' && $effectiveUtr === null) {
            return response()->json([
                'success' => false,
                'message' => 'Enter or verify the UPI UTR reference number before confirming.',
            ], 422);
        }

        $updated = DB::transaction(function () use ($booking, $action, $effectiveUtr, $validated, $request) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($action === 'reject_assign_driver') {
                $note = trim((string) ($validated['payment_notes'] ?? ''))
                    ?: 'UPI UTR not confirmed — Driver will collect ₹'.number_format((float) $locked->total_amount).' on trip completion';

                $attrs = [
                    'payment_status' => 'driver_collect',
                    'payment_collection_by' => 'driver',
                    'advance_amount' => 0.0,
                    'balance_amount' => (float) $locked->total_amount,
                    'upi_verified_at' => null,
                    'upi_verified_by' => $request->user()?->id,
                    'payment_notes' => $note,
                ];
                if ($effectiveUtr !== null) {
                    $attrs['upi_utr'] = $effectiveUtr;
                }

                $targetStatus = ($locked->status === BookingWorkflow::REQUESTED && ! $locked->is_beyond_region)
                    ? BookingWorkflow::CONFIRMED
                    : (string) $locked->status;

                return $this->workflow->forceTransition($locked, $targetStatus, $attrs);
            }

            $note = trim((string) ($validated['payment_notes'] ?? ''))
                ?: ($effectiveUtr ? "UPI UTR {$effectiveUtr} confirmed by Admin" : 'Payment confirmed by Admin');

            $isPartPay = $locked->payment_mode === 'part_pay';
            $advPct = max(1.0, min(99.0, (float) ($locked->advance_percentage ?: 20)));
            $advAmt = $isPartPay
                ? ((float) $locked->advance_amount > 0 ? (float) $locked->advance_amount : round(((float) $locked->total_amount) * ($advPct / 100), 2))
                : (float) $locked->total_amount;
            $balAmt = $isPartPay
                ? round(max(0.0, ((float) $locked->total_amount) - $advAmt), 2)
                : 0.0;

            $attrs = [
                'payment_status' => $isPartPay && $balAmt > 0 ? 'partial_paid' : 'paid',
                'payment_collection_by' => $isPartPay && $balAmt > 0 ? 'driver' : 'platform',
                'advance_amount' => $advAmt,
                'balance_amount' => $balAmt,
                'upi_verified_at' => now(),
                'upi_verified_by' => $request->user()?->id,
                'payment_notes' => $note,
            ];
            if ($effectiveUtr !== null) {
                $attrs['upi_utr'] = $effectiveUtr;
            }

            $targetStatus = ($locked->status === BookingWorkflow::REQUESTED && ! $locked->is_beyond_region)
                ? BookingWorkflow::CONFIRMED
                : (string) $locked->status;

            return $this->workflow->forceTransition($locked, $targetStatus, $attrs);
        });

        $updated->load(['driver', 'paymentAttempts.user']);

        // Record / resolve the matching payment attempt for audit tracking
        $latestAttempt = PaymentAttempt::where('booking_id', $updated->id)
            ->whereIn('status', ['initiated', 'utr_submitted', 'driver_collect'])
            ->latest('id')
            ->first();
        $resolvedStatus = $action === 'reject_assign_driver' ? 'rejected' : 'verified';
        if ($latestAttempt) {
            $latestAttempt->forceFill([
                'status' => $resolvedStatus,
                'upi_utr' => $updated->upi_utr ?: $latestAttempt->upi_utr,
                'notes' => $updated->payment_notes ?: $latestAttempt->notes,
                'resolved_at' => now(),
            ])->save();
        } else {
            PaymentAttempt::create([
                'booking_id' => $updated->id,
                'user_id' => $updated->customer_user_id,
                'payment_mode' => (string) ($updated->payment_mode ?: 'full_upi'),
                'payment_channel' => $action === 'reject_assign_driver' ? 'driver_collect' : 'manual_upi',
                'amount' => (float) ($updated->advance_amount > 0 ? $updated->advance_amount : $updated->total_amount),
                'status' => $resolvedStatus,
                'upi_utr' => $updated->upi_utr,
                'notes' => $updated->payment_notes,
                'attempted_at' => now(),
                'resolved_at' => now(),
            ]);
        }

        if ($updated->service_type !== 'bus' && $updated->status === BookingWorkflow::CONFIRMED) {
            $this->bidding->openForNewBooking($updated);
        }
        $this->notifications->paymentVerificationUpdated($updated, $action);

        return response()->json([
            'success' => true,
            'message' => $action === 'reject_assign_driver'
                ? 'Collection assigned to Driver upon trip completion.'
                : 'UPI payment confirmed by Admin!',
            'booking' => $updated->fresh(['driver', 'paymentAttempts.user']),
        ]);
    }

    /**
     * Stage to keep or move to after a driver change when the caller did not
     * name one explicitly. Pre-pickup bookings advance to "Driver Assigned";
     * a started, completed or cancelled trip keeps its stage so a correction
     * never rewrites history by accident.
     */
    private function statusAfterAllotment(string $current, ?int $driverId): string
    {
        if ($driverId === null) {
            return $current === BookingWorkflow::DRIVER_ASSIGNED
                ? BookingWorkflow::CONFIRMED
                : $current;
        }

        return in_array($current, [BookingWorkflow::REQUESTED, BookingWorkflow::BIDDING, BookingWorkflow::CONFIRMED], true)
            ? BookingWorkflow::DRIVER_ASSIGNED
            : $current;
    }

    /**
     * Generate structured invoice data for print/download.
     */
    public function invoice($id)
    {
        $booking = Booking::with('driver')->findOrFail($id);
        $tpl = PlatformSetting::invoiceSettings();

        $prefix = trim((string) ($tpl['invoice_prefix'] ?? 'INV')) ?: 'INV';
        $invoiceNumber = $prefix.'-'.date('Y', strtotime($booking->created_at ?? now())).'-'.$booking->booking_reference;
        $invoiceDate = $booking->created_at ? $booking->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');

        $cgstPct = max(0.0, (float) ($tpl['invoice_cgst_pct'] ?? 2.5));
        $sgstPct = max(0.0, (float) ($tpl['invoice_sgst_pct'] ?? 2.5));

        $extraKmFare = (float) ($booking->extra_km_fare ?? 0);
        $routeAdditionalFare = (float) ($booking->route_additional_fare ?? 0);
        $customPriceFieldsFare = (float) ($booking->custom_price_fields_fare ?? 0);

        $taxableBase = round(
            $booking->base_fare
            + $extraKmFare
            + $routeAdditionalFare
            + $customPriceFieldsFare
            + $booking->addon_fare
            - $booking->discount_fare,
            2
        );
        $cgst = round($taxableBase * ($cgstPct / 100), 2);
        $sgst = round($taxableBase * ($sgstPct / 100), 2);

        $termsLines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($tpl['invoice_terms'] ?? '')) ?: []),
            static fn ($line) => $line !== ''
        ));

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
            'template' => [
                'style' => $tpl['invoice_template_style'] ?? 'classic_gst',
                'accent_color' => $tpl['invoice_accent_color'] ?? '#d49b4c',
                'title' => $tpl['invoice_title'] ?? 'TAX INVOICE',
                'prefix' => $prefix,
                'brand_name' => $tpl['invoice_brand_name'] ?? 'INTERCITY WHEELS',
                'place_of_supply' => $tpl['invoice_place_of_supply'] ?? 'Maharashtra (27)',
                'cgst_pct' => $cgstPct,
                'sgst_pct' => $sgstPct,
                'show_stamp' => ($tpl['invoice_show_stamp'] ?? '1') === '1',
                'show_otp' => ($tpl['invoice_show_otp'] ?? '1') === '1',
                'terms' => $termsLines,
                'signatory_name' => $tpl['invoice_signatory_name'] ?? 'IntercityWheels Mobility Services',
                'signatory_label' => $tpl['invoice_signatory_label'] ?? 'Authorized Signatory',
            ],
            'company' => [
                'brand_name' => $tpl['invoice_brand_name'] ?? 'INTERCITY WHEELS',
                'name' => $tpl['invoice_company_legal'] ?? 'Intercity Wheels Pvt. Ltd.',
                'gstin' => $tpl['invoice_gstin'] ?? ' 19AAJCI2327Q1ZZ',
                'hsn_sac' => $tpl['invoice_sac_code'] ?? '996412 (Passenger Transport Service)',
                'place_of_supply' => $tpl['invoice_place_of_supply'] ?? 'Maharashtra (27)',
                'address' => $tpl['invoice_company_address'] ?? 'Floor 4, Tech Vista Towers, Senapati Bapat Road, Pune - 411016, Maharashtra, India',
                'support_phone' => $tpl['invoice_support_phone'] ?? '700 3434 088',
                'support_email' => $tpl['invoice_support_email'] ?? 'intercitywheelscompany@gmail.com',
                'website' => 'https://intercitywheels.com',
            ],
            'customer' => [
                'name' => $booking->customer_name,
                'phone' => $booking->customer_phone,
                'email' => $booking->customer_email ?? 'intercitywheelscompany@gmail.com',
                'pickup_location' => $booking->pickup_location,
                'drop_location' => $booking->drop_location,
                'trip_type' => strtoupper($booking->trip_type ?? 'One-Way'),
                'service_type' => strtoupper($booking->service_type ?? 'Cab'),
                'cab_type' => $booking->cab_type,
                'route' => $booking->route_summary,
                'travel_date' => $booking->departure_time ? $booking->departure_time->format('d M Y, h:i A') : 'Scheduled Departure',
            ],
            'driver' => $booking->driver ? [
                'name' => $booking->driver->name,
                'phone' => $booking->driver->phone,
                'vehicle_model' => $booking->driver->vehicle_model,
                'vehicle_plate' => $booking->driver->vehicle_plate,
                'rating' => $booking->driver->rating,
            ] : null,
            'charges' => [
                'base_fare' => $booking->base_fare,
                'toll_fare' => $booking->toll_fare,
                'extra_km_fare' => $extraKmFare,
                'route_additional_fare' => $routeAdditionalFare,
                'custom_price_fields_fare' => $customPriceFieldsFare,
                'booked_distance_km' => (int) ($booking->booked_distance_km ?? 0),
                'actual_distance_km' => (int) ($booking->actual_distance_km ?? 0),
                'extra_km' => (int) ($booking->extra_km ?? 0),
                'extra_km_rate' => (float) ($booking->extra_km_rate ?? 0),
                'fare_breakdown' => $booking->fare_breakdown,
                'addon_fare' => $booking->addon_fare,
                'discount_fare' => $booking->discount_fare,
                'taxable_amount' => $taxableBase,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'total_amount' => $booking->total_amount,
            ],
            'payment' => [
                'method' => match ($booking->payment_mode) {
                    'part_pay' => 'Part Pay ('.((float) $booking->advance_percentage).'% Advance Online + Balance to Driver)',
                    'full_upi' => $booking->payment_collection_by === 'driver'
                        ? 'Full UPI (Driver to Collect on Completion)'
                        : 'Full UPI Payment (Direct UPI / UTR)',
                    'cash' => 'Pay Cash / UPI to Chauffeur',
                    default => 'Online Escrow (Prepaid UPI / Card)',
                },
                'payment_mode' => $booking->payment_mode ?? 'full_online',
                'payment_gateway' => $booking->payment_gateway ?? 'manual',
                'payment_status' => $booking->payment_status ?? 'paid',
                'payment_collection_by' => $booking->payment_collection_by ?? 'platform',
                'upi_utr' => $booking->upi_utr,
                'upi_verified_at' => $booking->upi_verified_at?->toIso8601String(),
                'driver_collected_amount' => $booking->driver_collected_amount,
                'advance_percentage' => $booking->advance_percentage ?? 100.0,
                'advance_amount' => $booking->advance_amount ?? (float) $booking->total_amount,
                'balance_amount' => $booking->balance_amount ?? 0.0,
                'transaction_ref' => $booking->razorpay_payment_id
                    ?: ($booking->upi_utr ? 'UTR-'.$booking->upi_utr : ('TXN-'.strtoupper(substr(md5($booking->booking_reference), 0, 10)))),
                'status' => match ($booking->payment_status) {
                    'pending' => 'PAYMENT PENDING',
                    'pending_upi_verification' => 'UPI UTR PENDING ADMIN CONFIRMATION',
                    'awaiting_upi_utr' => 'AWAITING UPI UTR',
                    'driver_collect' => 'DRIVER TO COLLECT ON COMPLETION',
                    'upi_rejected' => 'UTR REJECTED · DRIVER COLLECTS',
                    'partial_paid' => 'PARTIAL ADVANCE ('.((float) $booking->advance_percentage).'%)',
                    'pay_on_pickup' => 'PAY TO CHAUFFEUR',
                    default => match ($booking->payment_mode) {
                        'part_pay' => $booking->driver_collected_at ? 'PAID IN FULL (DRIVER SETTLED)' : 'PARTIAL ADVANCE ('.((float) $booking->advance_percentage).'%)',
                        'cash' => $booking->driver_collected_at ? 'COLLECTED BY CHAUFFEUR' : 'PAY TO CHAUFFEUR',
                        default => 'PAID IN FULL',
                    },
                },
                'start_otp' => $booking->start_otp,
            ],
        ];

        return response()->json([
            'success' => true,
            'invoice' => $invoiceData,
            'booking' => $booking,
        ]);
    }
}
