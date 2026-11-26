<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransition;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\PlatformSetting;
use App\Services\BiddingService;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingWorkflow;
use App\Services\NotificationService;
use Illuminate\Http\Request;
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
        $query = Booking::with('driver')->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('booking_reference', 'like', "%{$s}%")
                    ->orWhere('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_phone', 'like', "%{$s}%")
                    ->orWhere('route_summary', 'like', "%{$s}%");
            });
        }

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
            'status' => 'nullable|string',
            'assigned_driver_id' => 'nullable|exists:drivers,id',
        ]);

        $baseFare = (float) $validated['base_fare'];
        $tollFare = (float) ($validated['toll_fare'] ?? 0);
        $addonFare = (float) ($validated['addon_fare'] ?? 0);
        $discountFare = (float) ($validated['discount_fare'] ?? 0);
        $totalAmount = max(0, round($baseFare + $tollFare + $addonFare - $discountFare));

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
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'base_fare' => $baseFare,
            'toll_fare' => $tollFare,
            'addon_fare' => $addonFare,
            'discount_fare' => $discountFare,
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
        $booking = Booking::with('driver')->findOrFail($id);

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
            'status' => 'sometimes|required|string',
            'start_otp' => 'sometimes|nullable|string|max:6',
            'assigned_driver_id' => 'nullable|exists:drivers,id',
        ]);

        if (isset($validated['base_fare']) || isset($validated['toll_fare']) || isset($validated['addon_fare']) || isset($validated['discount_fare'])) {
            $baseFare = (float) ($validated['base_fare'] ?? $booking->base_fare);
            $tollFare = (float) ($validated['toll_fare'] ?? $booking->toll_fare);
            $addonFare = (float) ($validated['addon_fare'] ?? $booking->addon_fare);
            $discountFare = (float) ($validated['discount_fare'] ?? $booking->discount_fare);
            $validated['total_amount'] = max(0, round($baseFare + $tollFare + $addonFare - $discountFare));
        }

        $cabType = $validated['cab_type'] ?? null;
        unset($validated['cab_type']);

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

        $taxableBase = round($booking->base_fare + $booking->addon_fare - $booking->discount_fare, 2);
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
                    'full_upi' => 'Full UPI Payment',
                    'cash' => 'Pay Cash / UPI to Chauffeur',
                    default => 'Online Escrow (Prepaid UPI / Card)',
                },
                'payment_mode' => $booking->payment_mode ?? 'full_online',
                'payment_gateway' => $booking->payment_gateway ?? 'manual',
                'payment_status' => $booking->payment_status ?? 'paid',
                'advance_percentage' => $booking->advance_percentage ?? 100.0,
                'advance_amount' => $booking->advance_amount ?? (float) $booking->total_amount,
                'balance_amount' => $booking->balance_amount ?? 0.0,
                'transaction_ref' => $booking->razorpay_payment_id
                    ?: ('TXN-'.strtoupper(substr(md5($booking->booking_reference), 0, 10))),
                'status' => match ($booking->payment_status) {
                    'pending' => 'PAYMENT PENDING',
                    'partial_paid' => 'PARTIAL ADVANCE ('.((float) $booking->advance_percentage).'%)',
                    'pay_on_pickup' => 'PAY TO CHAUFFEUR',
                    default => match ($booking->payment_mode) {
                        'part_pay' => 'PARTIAL ADVANCE ('.((float) $booking->advance_percentage).'%)',
                        'cash' => 'PAY TO CHAUFFEUR',
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
