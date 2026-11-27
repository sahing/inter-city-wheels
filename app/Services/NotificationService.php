<?php

namespace App\Services;

use App\Models\BiddingSession;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\User;
use App\Notifications\PlatformAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Throwable;

class NotificationService
{
    public function bookingCreated(Booking $booking, string $source = 'customer', array $beyondCities = []): void
    {
        $booking->loadMissing('customer', 'driver');

        $fare = '₹'.number_format((float) $booking->total_amount);
        $departure = $booking->departure_time
            ? $booking->departure_time->format('d M Y, h:i A')
            : 'Scheduled Departure';
        $paymentLine = match ($booking->payment_mode) {
            'part_pay' => 'Payment: '.((float) $booking->advance_percentage).'% Advance (₹'.number_format((float) $booking->advance_amount).') • Balance ₹'.number_format((float) $booking->balance_amount).' to Driver',
            'full_upi' => $booking->payment_collection_by === 'driver'
                ? "Payment: Full UPI ({$fare}) — Driver to collect on completion"
                : ($booking->upi_utr
                    ? "Payment: Full UPI ({$fare}) — UTR {$booking->upi_utr} (Pending Admin Confirmation)"
                    : "Payment: Full UPI ({$fare}) — Awaiting UTR / Admin Confirmation"),
            'cash' => "Payment: Pay Cash / UPI to Chauffeur ({$fare})",
            default => "Payment: Full Online ({$fare})",
        };

        $ticketUrl = URL::temporarySignedRoute(
            'api.bookings.show',
            now()->addDays(7),
            ['reference' => $booking->booking_reference]
        );

        $isBeyondRegion = (bool) $booking->is_beyond_region;

        $customerTitle = $isBeyondRegion
            ? "Booking Request Recorded (Unconfirmed · Beyond Region): {$booking->booking_reference}"
            : "Booking Confirmed: {$booking->booking_reference}";
        $customerBody = $isBeyondRegion
            ? "Your {$booking->service_type} request ({$booking->route_summary}) for {$fare} has been logged, but this route lies beyond our regional area. Ride confirmation and vehicle availability are NOT guaranteed."
            : "Your {$booking->service_type} trip ({$booking->route_summary}) for {$fare} has been recorded.";
        $customerLines = [
            "Reference: {$booking->booking_reference}",
            "Route: {$booking->route_summary}",
            "Pickup: {$booking->pickup_location}",
            "Drop: {$booking->drop_location}",
            "Departure: {$departure}",
            "Estimated Fare: {$fare}",
            $paymentLine,
            "Ride Start OTP: {$booking->start_otp}",
        ];
        if ($isBeyondRegion) {
            $customerLines[] = 'Availability Status: NOT Confirmed / NOT Guaranteed (Beyond Regional Service Area)';
        }

        $customerNotification = new PlatformAlertNotification(
            event: 'booking.created',
            title: $customerTitle,
            message: $customerBody,
            category: 'booking',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'route_summary' => $booking->route_summary,
                'status' => $booking->status,
                'is_beyond_region' => $isBeyondRegion,
                'availability_status' => $booking->availability_status ?? ($isBeyondRegion ? 'unconfirmed' : 'guaranteed'),
                'total_amount' => (float) $booking->total_amount,
                'payment_mode' => $booking->payment_mode,
                'advance_amount' => $booking->advance_amount,
                'balance_amount' => $booking->balance_amount,
                'start_otp' => $booking->start_otp,
                'tab' => 'my-bookings',
            ],
            lines: $customerLines,
            actionLabel: 'View Booking Details',
            actionUrl: url('/#my-bookings'),
            sendEmail: true,
        );

        $this->notifyBookingCustomer($booking, $customerNotification);

        $adminLines = [
            "Reference: {$booking->booking_reference}",
            "Customer: {$booking->customer_name} ({$booking->customer_phone})",
            "Route: {$booking->route_summary}",
            "Departure: {$departure}",
            "Total Fare: {$fare}",
            "Current Status: {$booking->status}",
        ];
        if ($isBeyondRegion) {
            $adminLines[] = 'Region Notice: Outside regional area — Ride NOT confirmed / availability unverified.';
            if ($beyondCities !== []) {
                $adminLines[] = 'Out-of-Region City Queue: '.implode(', ', $beyondCities).' (Pending Admin Approval to add to City List).';
            }
        }

        $beyondSummary = $beyondCities !== [] ? ' Out-of-region area(s) queued for city approval: '.implode(', ', $beyondCities).'.' : '';

        $adminNotification = new PlatformAlertNotification(
            event: 'booking.created',
            title: $isBeyondRegion
                ? "Out-of-Region Request {$booking->booking_reference} ({$fare})"
                : "New Booking {$booking->booking_reference} ({$fare})",
            message: $isBeyondRegion
                ? "{$booking->customer_name} requested an out-of-region route ({$booking->route_summary}) via {$source}. Ride is unconfirmed.{$beyondSummary}"
                : "{$booking->customer_name} booked {$booking->route_summary} via {$source}.",
            category: 'booking',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'status' => $booking->status,
                'is_beyond_region' => $isBeyondRegion,
                'beyond_region_cities' => $beyondCities,
                'tab' => 'admin',
                'section' => $isBeyondRegion ? 'locations' : 'bookings',
                'ticket_url' => $ticketUrl,
            ],
            lines: $adminLines,
            actionLabel: $isBeyondRegion ? 'Review & Approve City' : 'Open Admin Dispatch',
            actionUrl: $isBeyondRegion ? url('/?tab=admin&section=locations#admin-discovered-cities-card') : url('/#admin'),
            sendEmail: true,
        );

        $this->notifyAdmins($adminNotification);
    }

    public function biddingOpened(BiddingSession $session): void
    {
        $session->loadMissing('booking');
        $bidUrl = route('bidding.page', ['token' => $session->token]);
        $startAmount = '₹'.number_format((float) $session->start_amount);
        $routeText = "{$session->pickup_city} → {$session->drop_city}";

        $driverUsers = $this->resolveVerifiedDriverUsers();
        if ($driverUsers->isEmpty()) {
            return;
        }

        $notification = new PlatformAlertNotification(
            event: 'bidding.opened',
            title: "New Ride Auction: {$routeText}",
            message: "Max driver payout {$startAmount}. Place your lowest bid now.",
            category: 'bidding',
            meta: [
                'session_id' => $session->id,
                'token' => $session->token,
                'booking_reference' => $session->booking?->booking_reference,
                'route_summary' => $routeText,
                'start_amount' => (float) $session->start_amount,
                'tab' => 'bidding',
            ],
            lines: [
                "Route: {$routeText}",
                "Max Driver Offer: {$startAmount}",
            ],
            actionLabel: 'Open Bidding Room',
            actionUrl: $bidUrl,
            sendEmail: false,
        );

        foreach ($driverUsers as $user) {
            $this->notifyUser($user, $notification);
        }
    }

    public function bidPlaced(BiddingSession $session, Driver $driver, float $amount, bool $instantWin = false): void
    {
        $session->loadMissing('booking');
        $ref = $session->booking?->booking_reference ?? ('#'.$session->booking_id);
        $formatted = '₹'.number_format($amount);

        $notification = new PlatformAlertNotification(
            event: 'bidding.bid_placed',
            title: $instantWin
                ? "Instant Win Bid on {$ref} ({$formatted})"
                : "New Driver Bid on {$ref} ({$formatted})",
            message: "{$driver->name} ({$driver->vehicle_plate}) bid {$formatted} for {$session->pickup_city} → {$session->drop_city}.",
            category: 'bidding',
            meta: [
                'session_id' => $session->id,
                'booking_reference' => $ref,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'bid_amount' => $amount,
                'instant_win' => $instantWin,
                'tab' => 'admin',
            ],
            actionLabel: 'Review Bids',
            actionUrl: url('/#admin'),
            sendEmail: false,
        );

        $this->notifyAdmins($notification);
    }

    public function driverAllotted(Booking $booking, ?Driver $driver, ?float $payout = null): void
    {
        $booking->loadMissing('customer', 'driver');

        if ($driver) {
            $customerNotification = new PlatformAlertNotification(
                event: 'booking.driver_assigned',
                title: "Chauffeur Assigned: {$booking->booking_reference}",
                message: "{$driver->name} ({$driver->vehicle_model} • {$driver->vehicle_plate}) is assigned for {$booking->route_summary}. Ride OTP: {$booking->start_otp}.",
                category: 'booking',
                meta: [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'driver_name' => $driver->name,
                    'driver_phone' => $driver->phone,
                    'vehicle_model' => $driver->vehicle_model,
                    'vehicle_plate' => $driver->vehicle_plate,
                    'start_otp' => $booking->start_otp,
                    'tab' => 'my-bookings',
                ],
                lines: [
                    "Booking Reference: {$booking->booking_reference}",
                    "Chauffeur: {$driver->name} ({$driver->phone})",
                    "Vehicle: {$driver->vehicle_model} ({$driver->vehicle_plate})",
                    "Route: {$booking->route_summary}",
                    "Pickup: {$booking->pickup_location}",
                    "Ride Start OTP: {$booking->start_otp}",
                ],
                actionLabel: 'View Trip Pass',
                actionUrl: url('/#my-bookings'),
                sendEmail: true,
            );

            $this->notifyBookingCustomer($booking, $customerNotification);

            $driverUser = $this->resolveDriverUser($driver);
            if ($driverUser) {
                $payoutText = $payout !== null
                    ? '₹'.number_format($payout)
                    : '₹'.number_format((float) ($booking->driver_payout_amount ?? $booking->total_amount));

                $driverNotification = new PlatformAlertNotification(
                    event: 'booking.driver_assigned',
                    title: "Trip Allotted: {$booking->booking_reference}",
                    message: "You have been assigned {$booking->route_summary} (Payout: {$payoutText}). Pickup: {$booking->pickup_location}.",
                    category: 'trip',
                    meta: [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'route_summary' => $booking->route_summary,
                        'customer_name' => $booking->customer_name,
                        'customer_phone' => $booking->customer_phone,
                        'payout' => $payout ?? $booking->driver_payout_amount,
                        'tab' => 'driver-trips',
                    ],
                    lines: [
                        "Reference: {$booking->booking_reference}",
                        "Route: {$booking->route_summary}",
                        "Pickup: {$booking->pickup_location}",
                        "Drop: {$booking->drop_location}",
                        "Passenger: {$booking->customer_name} ({$booking->customer_phone})",
                        "Estimated Payout: {$payoutText}",
                    ],
                    actionLabel: 'Open Assigned Trips',
                    actionUrl: url('/#driver-trips'),
                    sendEmail: true,
                );

                $this->notifyUser($driverUser, $driverNotification);
            }

            $adminNotification = new PlatformAlertNotification(
                event: 'booking.driver_assigned',
                title: "Driver Allotted: {$booking->booking_reference}",
                message: "{$driver->name} ({$driver->vehicle_plate}) assigned to {$booking->route_summary}.",
                category: 'booking',
                meta: [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'driver_id' => $driver->id,
                    'tab' => 'admin',
                ],
                sendEmail: false,
            );

            $this->notifyAdmins($adminNotification);

            return;
        }

        $unassignedNotification = new PlatformAlertNotification(
            event: 'booking.driver_unassigned',
            title: "Chauffeur Reassignment: {$booking->booking_reference}",
            message: "Your trip {$booking->booking_reference} ({$booking->route_summary}) is being reassigned to another verified chauffeur.",
            category: 'booking',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'status' => $booking->status,
                'tab' => 'my-bookings',
            ],
            actionLabel: 'Check Trip Status',
            actionUrl: url('/#my-bookings'),
            sendEmail: true,
        );

        $this->notifyBookingCustomer($booking, $unassignedNotification);
    }

    public function bookingStatusChanged(Booking $booking, string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        $booking->loadMissing('customer', 'driver');

        $isCancelled = $toStatus === BookingWorkflow::CANCELLED;
        $event = $isCancelled ? 'booking.cancelled' : 'booking.status_updated';
        $title = $isCancelled
            ? "Booking Cancelled: {$booking->booking_reference}"
            : "Booking {$toStatus}: {$booking->booking_reference}";

        $customerNotification = new PlatformAlertNotification(
            event: $event,
            title: $title,
            message: "Your booking {$booking->booking_reference} ({$booking->route_summary}) changed from {$fromStatus} to {$toStatus}.",
            category: 'booking',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'from_status' => $fromStatus,
                'status' => $toStatus,
                'tab' => 'my-bookings',
            ],
            lines: [
                "Reference: {$booking->booking_reference}",
                "Route: {$booking->route_summary}",
                "Previous Status: {$fromStatus}",
                "Current Status: {$toStatus}",
            ],
            actionLabel: 'View Booking',
            actionUrl: url('/#my-bookings'),
            sendEmail: true,
        );

        $this->notifyBookingCustomer($booking, $customerNotification);

        if ($booking->driver && $isCancelled) {
            $driverUser = $this->resolveDriverUser($booking->driver);
            if ($driverUser) {
                $this->notifyUser($driverUser, new PlatformAlertNotification(
                    event: 'booking.cancelled',
                    title: "Assigned Trip Cancelled: {$booking->booking_reference}",
                    message: "Trip {$booking->booking_reference} ({$booking->route_summary}) has been cancelled.",
                    category: 'trip',
                    meta: [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'status' => $toStatus,
                        'tab' => 'driver-trips',
                    ],
                    sendEmail: true,
                ));
            }
        }
    }

    public function tripStarted(Booking $booking): void
    {
        $booking->loadMissing('customer', 'driver');
        $driverLabel = $booking->driver
            ? "{$booking->driver->name} ({$booking->driver->vehicle_plate})"
            : 'your chauffeur';

        $customerNotification = new PlatformAlertNotification(
            event: 'trip.started',
            title: "Trip Started: {$booking->booking_reference}",
            message: "Your trip {$booking->route_summary} has commenced with {$driverLabel}. Have a safe journey!",
            category: 'trip',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'status' => $booking->status,
                'tab' => 'my-bookings',
            ],
            lines: [
                "Reference: {$booking->booking_reference}",
                "Route: {$booking->route_summary}",
                "Chauffeur: {$driverLabel}",
            ],
            actionLabel: 'Track My Trip',
            actionUrl: url('/#my-bookings'),
            sendEmail: true,
        );

        $this->notifyBookingCustomer($booking, $customerNotification);

        $this->notifyAdmins(new PlatformAlertNotification(
            event: 'trip.started',
            title: "Trip Started: {$booking->booking_reference}",
            message: "OTP verified. {$booking->route_summary} is now in progress.",
            category: 'trip',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'tab' => 'admin',
            ],
            sendEmail: false,
        ));
    }

    public function upiUtrSubmitted(Booking $booking): void
    {
        $booking->loadMissing('customer', 'driver');
        $fare = '₹'.number_format((float) $booking->total_amount);

        if ($booking->payment_collection_by === 'driver') {
            $this->notifyAdmins(new PlatformAlertNotification(
                event: 'booking.payment_driver_collect',
                title: "Driver Collection Selected: {$booking->booking_reference}",
                message: "{$booking->customer_name} chose driver collection ({$fare}) on trip completion.",
                category: 'booking',
                meta: [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'tab' => 'admin',
                    'section' => 'bookings',
                ],
                sendEmail: false,
            ));

            return;
        }

        $this->notifyAdmins(new PlatformAlertNotification(
            event: 'booking.upi_utr_submitted',
            title: "UPI UTR Submitted: {$booking->booking_reference} ({$fare})",
            message: "{$booking->customer_name} submitted UTR {$booking->upi_utr} for {$fare}. Verify and confirm in Admin Bookings.",
            category: 'booking',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'upi_utr' => $booking->upi_utr,
                'tab' => 'admin',
                'section' => 'bookings',
            ],
            lines: [
                "Reference: {$booking->booking_reference}",
                "Amount: {$fare}",
                "UTR: {$booking->upi_utr}",
            ],
            actionLabel: 'Verify UPI UTR',
            actionUrl: url('/?tab=admin&section=bookings'),
            sendEmail: true,
        ));
    }

    public function paymentVerificationUpdated(Booking $booking, string $action): void
    {
        $booking->loadMissing('customer', 'driver');
        $fare = '₹'.number_format((float) $booking->total_amount);
        $isDriverCollect = $action === 'reject_assign_driver';

        $customerTitle = $isDriverCollect
            ? "Pay Driver on Completion: {$booking->booking_reference}"
            : "UPI Payment Confirmed: {$booking->booking_reference}";
        $customerMsg = $isDriverCollect
            ? "Your UPI UTR could not be confirmed by Admin. Please pay {$fare} directly to your chauffeur when the drive is completed."
            : "Admin confirmed your UPI payment ({$fare}".($booking->upi_utr ? " · UTR {$booking->upi_utr}" : '').").";

        $this->notifyBookingCustomer($booking, new PlatformAlertNotification(
            event: $isDriverCollect ? 'booking.payment_driver_collect' : 'booking.payment_verified',
            title: $customerTitle,
            message: $customerMsg,
            category: 'booking',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'payment_status' => $booking->payment_status,
                'payment_collection_by' => $booking->payment_collection_by,
                'upi_utr' => $booking->upi_utr,
                'tab' => 'my-bookings',
            ],
            actionLabel: 'View My Trip',
            actionUrl: url('/#my-bookings'),
            sendEmail: true,
        ));

        if ($booking->driver) {
            $driverUser = $this->resolveDriverUser($booking->driver);
            if ($driverUser) {
                $this->notifyUser($driverUser, new PlatformAlertNotification(
                    event: $isDriverCollect ? 'trip.collect_required' : 'trip.payment_verified',
                    title: $isDriverCollect
                        ? "Collect {$fare} on Completion: {$booking->booking_reference}"
                        : "Prepaid Confirmed: {$booking->booking_reference}",
                    message: $isDriverCollect
                        ? "Collect {$fare} from {$booking->customer_name} when the drive is completed."
                        : "Admin confirmed customer's UPI payment ({$fare}). No cash collection needed.",
                    category: 'trip',
                    meta: [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'payment_status' => $booking->payment_status,
                        'payment_collection_by' => $booking->payment_collection_by,
                        'tab' => 'driver-trips',
                    ],
                    sendEmail: false,
                ));
            }
        }
    }

    public function tripCompleted(Booking $booking, float $payout = 0.0): void
    {
        $booking->loadMissing('customer', 'driver');
        $fare = '₹'.number_format((float) $booking->total_amount);

        $customerNotification = new PlatformAlertNotification(
            event: 'trip.completed',
            title: "Trip Completed: {$booking->booking_reference}",
            message: "Your trip {$booking->route_summary} is complete. Total fare: {$fare}.",
            category: 'trip',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'total_amount' => (float) $booking->total_amount,
                'tab' => 'my-bookings',
            ],
            lines: [
                "Reference: {$booking->booking_reference}",
                "Route: {$booking->route_summary}",
                "Total Paid: {$fare}",
            ],
            actionLabel: 'View Trip Receipt',
            actionUrl: url('/#my-bookings'),
            sendEmail: true,
        );

        $this->notifyBookingCustomer($booking, $customerNotification);

        if ($booking->driver) {
            $driverUser = $this->resolveDriverUser($booking->driver);
            if ($driverUser) {
                $payoutText = '₹'.number_format($payout);
                $this->notifyUser($driverUser, new PlatformAlertNotification(
                    event: 'trip.completed',
                    title: "Payout Credited: {$payoutText} ({$booking->booking_reference})",
                    message: "Trip {$booking->booking_reference} completed! {$payoutText} has been credited to your driver wallet.",
                    category: 'wallet',
                    meta: [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'payout' => $payout,
                        'tab' => 'driver-wallet',
                    ],
                    lines: [
                        "Reference: {$booking->booking_reference}",
                        "Route: {$booking->route_summary}",
                        "Wallet Credit: {$payoutText}",
                    ],
                    actionLabel: 'View Driver Wallet',
                    actionUrl: url('/#driver-wallet'),
                    sendEmail: true,
                ));
            }
        }

        $this->notifyAdmins(new PlatformAlertNotification(
            event: 'trip.completed',
            title: "Trip Completed: {$booking->booking_reference}",
            message: "{$booking->route_summary} marked completed (Total: {$fare}).",
            category: 'trip',
            meta: [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'tab' => 'admin',
            ],
            sendEmail: false,
        ));
    }

    public function walletWithdrawal(Driver $driver, float $amount, float $balanceAfter): void
    {
        $amountText = '₹'.number_format($amount);
        $balanceText = '₹'.number_format($balanceAfter);

        $driverUser = $this->resolveDriverUser($driver);
        if ($driverUser) {
            $this->notifyUser($driverUser, new PlatformAlertNotification(
                event: 'wallet.withdrawal',
                title: "Withdrawal Initiated: {$amountText}",
                message: "Your payout request of {$amountText} has been initiated. Remaining balance: {$balanceText}.",
                category: 'wallet',
                meta: [
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'tab' => 'driver-wallet',
                ],
                lines: [
                    "Withdrawal Amount: {$amountText}",
                    "Remaining Wallet Balance: {$balanceText}",
                ],
                actionLabel: 'View Wallet Ledger',
                actionUrl: url('/#driver-wallet'),
                sendEmail: true,
            ));
        }

        $this->notifyAdmins(new PlatformAlertNotification(
            event: 'wallet.withdrawal',
            title: "Driver Withdrawal: {$driver->name} ({$amountText})",
            message: "{$driver->name} requested an IMPS payout of {$amountText}. Remaining balance: {$balanceText}.",
            category: 'wallet',
            meta: [
                'driver_id' => $driver->id,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'tab' => 'admin',
            ],
            sendEmail: true,
        ));
    }

    public function vehicleSubmitted(DriverVehicle $vehicle): void
    {
        $this->notifyAdmins(new PlatformAlertNotification(
            event: 'vehicle.submitted',
            title: "New Fleet Submission: {$vehicle->vehicle_plate}",
            message: "{$vehicle->driver_name} submitted {$vehicle->vehicle_model} ({$vehicle->vehicle_plate}) for approval.",
            category: 'fleet',
            meta: [
                'vehicle_id' => $vehicle->id,
                'vehicle_plate' => $vehicle->vehicle_plate,
                'tab' => 'admin',
            ],
            sendEmail: false,
        ));
    }

    public function vehicleStatusChanged(DriverVehicle $vehicle, string $status): void
    {
        $driverUser = $vehicle->user_id ? User::find($vehicle->user_id) : null;
        if (! $driverUser && $vehicle->driver_id) {
            $driver = Driver::find($vehicle->driver_id);
            $driverUser = $this->resolveDriverUser($driver);
        }

        if (! $driverUser) {
            return;
        }

        $this->notifyUser($driverUser, new PlatformAlertNotification(
            event: 'vehicle.'.strtolower($status),
            title: "Vehicle {$status}: {$vehicle->vehicle_plate}",
            message: "Your vehicle {$vehicle->vehicle_model} ({$vehicle->vehicle_plate}) was {$status} by Admin.",
            category: 'fleet',
            meta: [
                'vehicle_id' => $vehicle->id,
                'vehicle_plate' => $vehicle->vehicle_plate,
                'status' => $status,
                'tab' => 'driver-profile',
            ],
            lines: [
                "Vehicle: {$vehicle->vehicle_model} ({$vehicle->vehicle_plate})",
                "Status: {$status}",
                ! empty($vehicle->admin_notes) ? "Notes: {$vehicle->admin_notes}" : '',
            ],
            actionLabel: 'View Driver Fleet',
            actionUrl: url('/#driver-profile'),
            sendEmail: true,
        ));
    }

    public function notifyBookingCustomer(Booking $booking, PlatformAlertNotification $notification): void
    {
        $customerUser = $this->resolveCustomerUser($booking);
        $bookingEmail = strtolower(trim((string) $booking->customer_email));

        if ($customerUser) {
            $this->notifyUser($customerUser, $notification);
        }

        if ($bookingEmail !== '' && filter_var($bookingEmail, FILTER_VALIDATE_EMAIL)) {
            $userEmail = $customerUser ? strtolower(trim((string) $customerUser->email)) : '';
            if ($userEmail !== $bookingEmail) {
                $this->notifyEmailAddress($bookingEmail, $notification);
            }
        }
    }

    public function notifyAdmins(PlatformAlertNotification $notification): void
    {
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notifyUser($admin, $notification);
        }
    }

    public function notifyUser(User $user, PlatformAlertNotification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Failed to deliver notification to user.', [
                'user_id' => $user->id,
                'event' => $notification->event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyEmailAddress(string $email, PlatformAlertNotification $notification): void
    {
        try {
            Notification::route('mail', $email)->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Failed to deliver email notification.', [
                'email' => $email,
                'event' => $notification->event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function resolveCustomerUser(Booking $booking): ?User
    {
        if ($booking->customer_user_id) {
            $user = $booking->customer ?? User::find($booking->customer_user_id);
            if ($user) {
                return $user;
            }
        }

        $email = strtolower(trim((string) $booking->customer_email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $booking->customer_phone) ?? '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        if (strlen($last10) === 10) {
            return User::where('phone', 'like', '%'.$last10.'%')->first();
        }

        return null;
    }

    public function resolveDriverUser(?Driver $driver): ?User
    {
        if (! $driver) {
            return null;
        }

        $exact = User::where('role', 'driver')->where('phone', $driver->phone)->first();
        if ($exact) {
            return $exact;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $driver->phone) ?? '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        if (strlen($last10) === 10) {
            return User::where('role', 'driver')
                ->where('phone', 'like', '%'.$last10.'%')
                ->first();
        }

        return null;
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveVerifiedDriverUsers(): Collection
    {
        $drivers = Driver::where('is_verified', true)->get();
        $users = collect();

        foreach ($drivers as $driver) {
            $user = $this->resolveDriverUser($driver);
            if ($user && ! $users->contains('id', $user->id)) {
                $users->push($user);
            }
        }

        return $users;
    }
}
