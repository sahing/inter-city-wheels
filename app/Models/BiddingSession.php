<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One reverse auction for one booking.
 *
 * Visibility rule (enforced in the API, not just the UI): a bidding driver may
 * see the route summary and the money, never the customer's identity, contact
 * or exact addresses. Those live on the Booking and reach the driver only after
 * the admin approves the winner.
 */
class BiddingSession extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_AWARDED = 'awarded';

    public const STATUS_CANCELLED = 'cancelled';

    /** Default minimum admin margin, used when a session does not override it. */
    public const DEFAULT_ADMIN_FEE_PCT = 15.0;

    /** A bid must undercut the current lowest by at least this much. */
    public const MIN_BID_STEP = 1.0;

    /** How many times one driver may bid in one session. */
    public const MAX_BIDS_PER_DRIVER = 20;

    /** Default bidding window, in hours, when the admin does not set a deadline. */
    public const DEFAULT_WINDOW_HOURS = 24;

    protected $guarded = [];

    protected $casts = [
        'start_amount' => 'float',
        'admin_fee_pct' => 'float',
        'distance_km' => 'integer',
        'closes_at' => 'datetime',
        'closed_at' => 'datetime',
        'awarded_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bids()
    {
        return $this->hasMany(DriverBid::class, 'session_id')->latest('id');
    }

    public function winningBid()
    {
        return $this->belongsTo(DriverBid::class, 'awarded_bid_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public static function generateToken(): string
    {
        // 48 chars of CSPRNG entropy: the link is the only thing a driver needs
        // besides their own login, so it must not be guessable.
        return Str::random(48);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && ! $this->isPastDeadline();
    }

    public function isPastDeadline(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    public function isAwardable(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_CLOSED], true);
    }

    public function currentLowestBid(): ?DriverBid
    {
        return $this->bids()
            ->where('flagged', false)
            ->orderBy('bid_amount')
            ->orderBy('id')
            ->first();
    }

    public function currentLowestAmount(): ?float
    {
        $bid = $this->currentLowestBid();

        return $bid === null ? null : (float) $bid->bid_amount;
    }

    /**
     * The most the admin may pay a driver: the customer fare minus the fixed
     * margin percentage.
     */
    public function maxDriverPrice(): float
    {
        $customerAmount = (float) $this->booking->total_amount;
        $feePct = min(90.0, max(0.0, (float) $this->admin_fee_pct));

        return floor($customerAmount * (1 - ($feePct / 100)));
    }

    public function adminProfitAt(float $driverPrice): float
    {
        return round((float) $this->booking->total_amount - $driverPrice, 2);
    }

    /**
     * Everything a bidding driver is allowed to know, and nothing else.
     *
     * Read from the session's own columns only: no join, so customer name,
     * phone, email, addresses and the customer's fare cannot leak by accident.
     *
     * @return array<string, mixed>
     */
    public function toDriverPayload(?int $viewerDriverId = null): array
    {
        $lowest = $this->currentLowestBid();

        return [
            'id' => $this->id,
            'token' => $this->token,
            'booking_reference' => (string) $this->booking->booking_reference,
            'service_type' => (string) $this->booking->service_type,
            'trip_type' => (string) $this->booking->trip_type,
            'pickup_city' => $this->pickup_city,
            'drop_city' => $this->drop_city,
            'distance_km' => $this->distance_km,
            'departure_date' => $this->booking->departure_time?->toDateString(),
            'departure_at' => $this->booking->departure_time?->toIso8601String(),
            'start_amount' => (float) $this->start_amount,
            'max_driver_price' => $this->maxDriverPrice(),
            'current_lowest' => $lowest === null ? null : (float) $lowest->bid_amount,
            'you_are_lowest' => $lowest !== null
                && $viewerDriverId !== null
                && (int) $lowest->driver_id === (int) $viewerDriverId,
            'status' => $this->status,
            'closes_at' => $this->closes_at?->toIso8601String(),
            'is_open' => $this->isOpen(),
        ];
    }

    /**
     * The admin view: the booking in full plus every bid with its bidder.
     *
     * @return array<string, mixed>
     */
    public function toAdminPayload(): array
    {
        $booking = $this->booking;
        $lowest = $this->currentLowestBid();

        $bids = $this->bids()->with('driver')->get()->map(function (DriverBid $bid) use ($lowest) {
            return [
                'id' => $bid->id,
                'driver_id' => $bid->driver_id,
                'driver_name' => $bid->driver?->name,
                'driver_phone' => $bid->driver?->phone,
                'driver_rating' => $bid->driver?->rating,
                'vehicle_model' => $bid->driver?->vehicle_model,
                'vehicle_plate' => $bid->driver?->vehicle_plate,
                'fleet_category' => $bid->driver?->fleet_category,
                'bid_amount' => (float) $bid->bid_amount,
                'status' => $bid->status,
                'is_lowest' => $lowest !== null && (int) $lowest->id === (int) $bid->id,
                'is_winning' => (bool) $bid->is_winning,
                'flagged' => (bool) $bid->flagged,
                'flag_reason' => $bid->flag_reason,
                'ip_address' => $bid->ip_address,
                'placed_at' => $bid->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return [
            'id' => $this->id,
            'token' => $this->token,
            'status' => $this->status,
            'is_open' => $this->isOpen(),
            'start_amount' => (float) $this->start_amount,
            'admin_fee_pct' => (float) $this->admin_fee_pct,
            'max_driver_price' => $this->maxDriverPrice(),
            'admin_profit_at_lowest' => $lowest === null
                ? null
                : $this->adminProfitAt((float) $lowest->bid_amount),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'awarded_at' => $this->awarded_at?->toIso8601String(),
            'awarded_bid_id' => $this->awarded_bid_id,
            'pickup_city' => $this->pickup_city,
            'drop_city' => $this->drop_city,
            'distance_km' => $this->distance_km,
            'bids' => $bids,
            'bid_count' => count($bids),
            'lowest_bid' => $lowest === null ? null : (float) $lowest->bid_amount,
            'booking' => [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'status' => $booking->status,
                'service_type' => $booking->service_type,
                'trip_type' => $booking->trip_type,
                'route_summary' => $booking->route_summary,
                'pickup_location' => $booking->pickup_location,
                'drop_location' => $booking->drop_location,
                'departure_time' => $booking->departure_time?->toIso8601String(),
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'customer_email' => $booking->customer_email,
                'total_amount' => (float) $booking->total_amount,
                'assigned_driver_id' => $booking->assigned_driver_id,
                'driver_payout_amount' => $booking->driver_payout_amount === null
                    ? null
                    : (float) $booking->driver_payout_amount,
            ],
        ];
    }
}
