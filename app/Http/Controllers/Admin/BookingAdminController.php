<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBookingTransition;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingWorkflow;
use Illuminate\Http\Request;

class BookingAdminController extends Controller
{
    public function __construct(
        private readonly BookingWorkflow $workflow,
        private readonly BookingReferenceGenerator $references,
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
            'selected_units' => [$validated['service_type'] === 'cab' ? 'Admin Assigned' : 'Sleeper Seat'],
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

        $booking->load('driver');

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

        if (! empty($validated['departure_time'])) {
            $validated['departure_time'] = date('Y-m-d H:i:s', strtotime($validated['departure_time']));
        }

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

            try {
                // P0-5: an admin edit may not teleport a booking between stages.
                $this->workflow->transition($booking, $newStatus, $validated);
            } catch (InvalidBookingTransition $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'transition' => $e->toArray(),
                ], 422);
            }
        }

        $booking->load('driver');

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
        $targetStatus = $driverId
            ? ($validated['status'] ?? BookingWorkflow::DRIVER_ASSIGNED)
            : BookingWorkflow::CONFIRMED;

        if (! BookingWorkflow::isKnownStatus($targetStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown booking status "'.$targetStatus.'".',
                'allowed' => BookingWorkflow::STATUSES,
            ], 422);
        }

        try {
            $this->workflow->transition($booking, $targetStatus, [
                'assigned_driver_id' => $driverId,
            ]);
        } catch (InvalidBookingTransition $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'transition' => $e->toArray(),
            ], 422);
        }

        $booking->load('driver');

        return response()->json([
            'success' => true,
            'message' => $driverId ? 'Driver allotted successfully!' : 'Driver allotment removed.',
            'booking' => $booking,
        ]);
    }

    /**
     * Generate structured invoice data for print/download.
     */
    public function invoice($id)
    {
        $booking = Booking::with('driver')->findOrFail($id);

        $invoiceNumber = 'INV-'.date('Y', strtotime($booking->created_at ?? now())).'-'.$booking->booking_reference;
        $invoiceDate = $booking->created_at ? $booking->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');

        // Tax calculation: 5% GST (2.5% CGST + 2.5% SGST) for passenger transport
        $taxableBase = round($booking->base_fare + $booking->addon_fare - $booking->discount_fare, 2);
        $cgst = round($taxableBase * 0.025, 2);
        $sgst = round($taxableBase * 0.025, 2);
        $tollCharge = round($booking->toll_fare, 2);
        $grandTotal = round($taxableBase + $cgst + $sgst + $tollCharge, 2);

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
            'company' => [
                'name' => 'IntercityWheels Mobility Services Pvt. Ltd.',
                'gstin' => '27AAACI9912K1Z8',
                'hsn_sac' => '996412 (Passenger Transport Service)',
                'address' => 'Floor 4, Tech Vista Towers, Senapati Bapat Road, Pune - 411016, Maharashtra, India',
                'support_phone' => '1800-209-WHEELS',
                'support_email' => 'billing@intercitywheels.com',
                'website' => 'https://intercitywheels.com',
            ],
            'customer' => [
                'name' => $booking->customer_name,
                'phone' => $booking->customer_phone,
                'email' => $booking->customer_email ?? 'billing@intercitywheels.com',
                'pickup_location' => $booking->pickup_location,
                'drop_location' => $booking->drop_location,
                'trip_type' => strtoupper($booking->trip_type ?? 'One-Way'),
                'service_type' => strtoupper($booking->service_type ?? 'Cab'),
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
                'method' => 'Online Escrow (Prepaid UPI / Card)',
                'transaction_ref' => 'TXN-'.strtoupper(substr(md5($booking->booking_reference), 0, 10)),
                'status' => 'PAID IN FULL',
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
