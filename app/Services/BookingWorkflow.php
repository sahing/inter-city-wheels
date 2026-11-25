<?php

namespace App\Services;

use App\Exceptions\InvalidBookingTransition;
use App\Models\Booking;

/**
 * The booking lifecycle, in one place.
 *
 * The platform advertises five stages (seed: workflow_stages) plus the
 * operational "Driver Assigned" and "Cancelled" states. Before this class the
 * status was written directly from several controllers, so any endpoint could
 * jump a booking to any string: a booking could be created already
 * "Completed". Every customer and driver write now goes through transition(),
 * which rejects everything outside the table below.
 *
 * The admin console is the one deliberate exception: ops staff must be able to
 * correct a mis-staged trip at any point, so its writes go through
 * forceTransition() instead.
 */
class BookingWorkflow
{
    public const REQUESTED = 'Requested';

    public const BIDDING = 'Bidding';

    public const CONFIRMED = 'Confirmed';

    public const DRIVER_ASSIGNED = 'Driver Assigned';

    public const STARTED = 'Started';

    public const COMPLETED = 'Completed';

    public const CANCELLED = 'Cancelled';

    /** Statuses a booking may hold. */
    public const STATUSES = [
        self::REQUESTED,
        self::BIDDING,
        self::CONFIRMED,
        self::DRIVER_ASSIGNED,
        self::STARTED,
        self::COMPLETED,
        self::CANCELLED,
    ];

    /**
     * Allowed forward edges. A status may always transition to itself
     * (idempotent re-save, e.g. an admin editing a Confirmed booking without
     * touching its status).
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::REQUESTED => [self::BIDDING, self::CONFIRMED, self::DRIVER_ASSIGNED, self::CANCELLED],
        // Re-opening an already confirmed booking for a driver auction is a
        // legitimate admin move (e.g. no driver accepted the first offer).
        self::BIDDING => [self::CONFIRMED, self::DRIVER_ASSIGNED, self::CANCELLED],
        self::CONFIRMED => [self::BIDDING, self::DRIVER_ASSIGNED, self::CANCELLED],
        // Un-assigning a driver deliberately returns the booking to Confirmed
        // so it can be re-offered, which is a legitimate operational rollback.
        self::DRIVER_ASSIGNED => [self::STARTED, self::CONFIRMED, self::CANCELLED],
        self::STARTED => [self::COMPLETED],
        self::COMPLETED => [],
        self::CANCELLED => [],
    ];

    /** Statuses that may never be reached again once entered. */
    public const TERMINAL = [self::COMPLETED, self::CANCELLED];

    public static function isKnownStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * @return list<string>
     */
    public static function allowedFrom(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        if (! self::isKnownStatus($from) || ! self::isKnownStatus($to)) {
            return false;
        }

        if ($from === $to) {
            return true;
        }

        return in_array($to, self::allowedFrom($from), true);
    }

    /**
     * @throws InvalidBookingTransition
     */
    public static function assertTransition(string $from, string $to, bool $allowSameStatus = true): void
    {
        if (! $allowSameStatus && $from === $to) {
            throw new InvalidBookingTransition($from, $to, self::allowedFrom($from));
        }

        if (! self::canTransition($from, $to)) {
            throw new InvalidBookingTransition($from, $to, self::allowedFrom($from));
        }
    }

    /**
     * Move a booking to $to, optionally using an already-loaded instance the
     * caller locked inside its transaction.
     *
     * @param  array<string, mixed>  $attributes  Additional columns to persist with the status.
     * @param  bool  $allowSameStatus  Pass false where a repeat would repeat a side effect
     *                                 (a payout, a trip start) rather than being a harmless re-save.
     *
     * @throws InvalidBookingTransition
     */
    public function transition(
        Booking $booking,
        string $to,
        array $attributes = [],
        bool $allowSameStatus = true
    ): Booking {
        $from = (string) $booking->status;

        self::assertTransition($from, $to, $allowSameStatus);

        $booking->fill($attributes);
        $booking->status = $to;
        $booking->save();

        return $booking;
    }

    /**
     * Admin override: move a booking to any known status, in any direction.
     *
     * Operational corrections do not always follow the happy path — a trip may
     * need reopening after it was completed by mistake, or a chauffeur may need
     * replacing on a live trip. Only the admin console may call this;
     * customer and driver surfaces keep using transition(), which still
     * enforces the stage table above.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidBookingTransition when $to is not a known status
     */
    public function forceTransition(Booking $booking, string $to, array $attributes = []): Booking
    {
        $from = (string) $booking->status;

        if (! self::isKnownStatus($to)) {
            throw new InvalidBookingTransition($from, $to, self::allowedFrom($from));
        }

        $booking->fill($attributes);
        $booking->status = $to;
        $booking->save();

        return $booking;
    }

    /**
     * Create a booking in its initial Requested state, then move it along the
     * legal path towards $target if one exists. Used by the admin "create
     * booking" screen, which may legitimately start at Confirmed or Driver
     * Assigned.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $finalAttributes
     *
     * @throws InvalidBookingTransition
     */
    public function create(array $attributes, string $target = self::REQUESTED, array $finalAttributes = []): Booking
    {
        if (! self::isKnownStatus($target)) {
            throw new InvalidBookingTransition(self::REQUESTED, $target, self::allowedFrom(self::REQUESTED));
        }

        $attributes['status'] = self::REQUESTED;
        $booking = Booking::create($attributes);

        if ($target === self::REQUESTED) {
            return $booking;
        }

        return $this->transition($booking, $target, $finalAttributes);
    }
}
