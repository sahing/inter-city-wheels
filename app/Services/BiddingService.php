<?php

namespace App\Services;

use App\Exceptions\BiddingException;
use App\Exceptions\InvalidBookingTransition;
use App\Models\BiddingSession;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverBid;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The driver marketplace: a reverse auction in which the admin publishes a
 * maximum acceptable driver price and verified drivers compete downwards.
 *
 * Guarantees this service owns:
 *  - a driver sees only route summary + money until the admin awards the bid;
 *  - every new bid must undercut the current lowest by at least ₹1;
 *  - the admin keeps the fare minus the winning bid, and the winning bid can
 *    never exceed the fare minus the configured margin percentage;
 *  - awarding is the only thing that assigns a driver, and it happens once,
 *    inside a transaction, with the booking moved along the state machine;
 *  - the driver is paid the winning bid (not a percentage of the fare).
 */
class BiddingService
{
    public function __construct(
        private readonly BookingWorkflow $workflow,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Automatically open a bidding session for a newly created booking so it stays
     * open for driver bids until a driver is allotted.
     */
    public function openForNewBooking(Booking $booking): ?BiddingSession
    {
        if ($booking->service_type !== 'cab' || $booking->assigned_driver_id !== null) {
            return null;
        }

        if (! in_array((string) $booking->status, [
            BookingWorkflow::REQUESTED,
            BookingWorkflow::BIDDING,
            BookingWorkflow::CONFIRMED,
        ], true)) {
            return null;
        }

        $alreadyExists = $booking->biddingSessions()
            ->whereIn('status', [
                BiddingSession::STATUS_OPEN,
                BiddingSession::STATUS_CLOSED,
                BiddingSession::STATUS_AWARDED,
            ])
            ->exists();

        if ($alreadyExists) {
            return null;
        }

        $feePct = $this->resolveFeePct(null);
        $probe = new BiddingSession([
            'booking_id' => $booking->id,
            'pickup_city' => '',
            'drop_city' => '',
            'start_amount' => 0,
            'admin_fee_pct' => $feePct,
        ]);
        $probe->setRelation('booking', $booking);
        $maxDriverPrice = $probe->maxDriverPrice();

        if ($maxDriverPrice < BiddingSession::MIN_BID_STEP) {
            return null;
        }

        [$pickupCity, $dropCity] = $this->routeCities($booking);
        $distanceKm = $this->routeDistance($booking, $pickupCity, $dropCity);

        $session = BiddingSession::create([
            'booking_id' => $booking->id,
            'token' => BiddingSession::generateToken(),
            'pickup_city' => $pickupCity,
            'drop_city' => $dropCity,
            'distance_km' => $distanceKm,
            'start_amount' => $maxDriverPrice,
            'admin_fee_pct' => $feePct,
            'status' => BiddingSession::STATUS_OPEN,
            'closes_at' => null,
            'opened_by' => null,
        ]);

        $this->notifications->biddingOpened($session);

        return $session;
    }

    /**
     * Mark open/closed auctions for this booking as Allotted (awarded) when a
     * driver is allotted, or re-open one if allotment is removed.
     */
    public function syncWithDriverAllotment(Booking $booking, ?int $driverId): void
    {
        if ($driverId !== null) {
            $sessions = $booking->biddingSessions()
                ->whereIn('status', [
                    BiddingSession::STATUS_OPEN,
                    BiddingSession::STATUS_CLOSED,
                    BiddingSession::STATUS_AWARDED,
                ])
                ->get();

            foreach ($sessions as $session) {
                $matchingBid = $session->bids()
                    ->where('driver_id', $driverId)
                    ->orderBy('bid_amount')
                    ->orderBy('id')
                    ->first();

                $session->bids()->update(['is_winning' => false]);

                if ($matchingBid) {
                    $matchingBid->forceFill(['is_winning' => true, 'status' => 'accepted'])->save();
                    $session->awarded_bid_id = $matchingBid->id;
                    $booking->forceFill([
                        'assigned_bid_id' => $matchingBid->id,
                        'driver_payout_amount' => (float) $matchingBid->bid_amount,
                    ])->save();
                } else {
                    $session->awarded_bid_id = null;
                    $booking->forceFill([
                        'assigned_bid_id' => null,
                        'driver_payout_amount' => null,
                    ])->save();
                }

                $session->status = BiddingSession::STATUS_AWARDED;
                $session->awarded_at = $session->awarded_at ?? now();
                $session->closed_at = $session->closed_at ?? now();
                $session->save();
            }

            return;
        }

        // Driver allotment was removed; ensure an auction is open if booking is
        // back in an unallotted pre-pickup stage.
        $this->openForNewBooking($booking);
    }

    /**
     * Open (or return) the auction for a booking.
     *
     * @param  array{start_amount?: mixed, admin_fee_pct?: mixed, closes_at?: mixed, admin_notes?: mixed}  $input
     *
     * @throws BiddingException
     */
    public function open(Booking $booking, array $input, User $admin): BiddingSession
    {
        if (! in_array($booking->status, [
            BookingWorkflow::REQUESTED,
            BookingWorkflow::CONFIRMED,
            BookingWorkflow::BIDDING,
        ], true)) {
            throw BiddingException::bookingNotBiddable((string) $booking->status);
        }

        $existing = $booking->biddingSessions()
            ->whereIn('status', [BiddingSession::STATUS_OPEN, BiddingSession::STATUS_CLOSED])
            ->latest('id')
            ->first();

        $reconfiguringAutoSession = $existing !== null
            && $existing->opened_by === null
            && $existing->bids()->count() === 0;

        if ($existing && ! $reconfiguringAutoSession) {
            throw BiddingException::alreadyOpen($existing->id);
        }

        $feePct = $this->resolveFeePct($input['admin_fee_pct'] ?? null);

        $probe = new BiddingSession([
            'booking_id' => $booking->id,
            'pickup_city' => '',
            'drop_city' => '',
            'start_amount' => 0,
            'admin_fee_pct' => $feePct,
        ]);
        $probe->setRelation('booking', $booking);
        $maxDriverPrice = $probe->maxDriverPrice();

        if ($maxDriverPrice < BiddingSession::MIN_BID_STEP) {
            throw BiddingException::invalidStartAmount($maxDriverPrice);
        }

        $startAmount = $input['start_amount'] ?? $maxDriverPrice;
        $startAmount = is_numeric($startAmount) ? (float) $startAmount : 0.0;

        if ($startAmount <= 0 || $startAmount > $maxDriverPrice) {
            throw BiddingException::invalidStartAmount($maxDriverPrice);
        }

        $closesAt = $this->resolveDeadline($input['closes_at'] ?? null);

        [$pickupCity, $dropCity] = $this->routeCities($booking);
        $distanceKm = $this->routeDistance($booking, $pickupCity, $dropCity);

        $session = DB::transaction(function () use (
            $booking,
            $admin,
            $feePct,
            $startAmount,
            $closesAt,
            $pickupCity,
            $dropCity,
            $distanceKm,
            $input,
            $existing,
            $reconfiguringAutoSession
        ) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($reconfiguringAutoSession && $existing) {
                $existing->update([
                    'pickup_city' => $pickupCity,
                    'drop_city' => $dropCity,
                    'distance_km' => $distanceKm,
                    'start_amount' => $startAmount,
                    'admin_fee_pct' => $feePct,
                    'status' => BiddingSession::STATUS_OPEN,
                    'closes_at' => $closesAt,
                    'closed_at' => null,
                    'opened_by' => $admin->id,
                    'admin_notes' => $input['admin_notes'] ?? null,
                ]);
                $session = $existing->refresh();
            } else {
                $session = BiddingSession::create([
                    'booking_id' => $locked->id,
                    'token' => BiddingSession::generateToken(),
                    'pickup_city' => $pickupCity,
                    'drop_city' => $dropCity,
                    'distance_km' => $distanceKm,
                    'start_amount' => $startAmount,
                    'admin_fee_pct' => $feePct,
                    'status' => BiddingSession::STATUS_OPEN,
                    'closes_at' => $closesAt,
                    'opened_by' => $admin->id,
                    'admin_notes' => $input['admin_notes'] ?? null,
                ]);
            }

            // Requested/Confirmed -> Bidding is a legal step in the workflow.
            if ($locked->status !== BookingWorkflow::BIDDING) {
                $this->workflow->transition($locked, BookingWorkflow::BIDDING);
            }

            return $session->refresh();
        });

        $this->notifications->biddingOpened($session);

        return $session;
    }

    /**
     * Place a bid. The amount must undercut the current lowest by at least ₹1.
     *
     * @throws BiddingException
     */
    public function placeBid(
        BiddingSession $session,
        Driver $driver,
        float $amount,
        ?string $ip = null,
        bool $skipVerification = false
    ): DriverBid {
        $session = $this->closeIfDue($session);

        if ($session->status === BiddingSession::STATUS_AWARDED) {
            throw BiddingException::alreadyAwarded();
        }

        if ($session->status === BiddingSession::STATUS_CANCELLED) {
            throw BiddingException::closed();
        }

        if (! $session->isOpen()) {
            throw BiddingException::closed();
        }

        $this->assertDriverEligible($driver, $session, $skipVerification);

        $amount = round($amount, 2);
        $lowest = $session->currentLowestAmount();
        $minDriverPrice = $session->minDriverPrice();

        if ($lowest === null) {
            if ($amount > (float) $session->start_amount) {
                throw BiddingException::bidTooHigh(null, (float) $session->start_amount, true);
            }
        } elseif ($amount > $lowest - BiddingSession::MIN_BID_STEP) {
            throw BiddingException::bidTooHigh($lowest, (float) $session->start_amount);
        }

        if ($amount < BiddingSession::MIN_BID_STEP) {
            throw new BiddingException(
                'Bid amount must be at least ₹'.number_format(BiddingSession::MIN_BID_STEP, 0).'.',
                422,
                'invalid_bid_amount'
            );
        }

        if ($amount < $minDriverPrice) {
            throw BiddingException::bidTooLow($minDriverPrice);
        }

        $bidCount = $session->bids()->where('driver_id', $driver->id)->count();

        if ($bidCount >= BiddingSession::MAX_BIDS_PER_DRIVER) {
            throw BiddingException::bidLimitReached(BiddingSession::MAX_BIDS_PER_DRIVER);
        }

        [$bid, $instantWonBooking] = DB::transaction(function () use ($session, $driver, $amount, $ip, $minDriverPrice) {
            $locked = BiddingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            // Re-check inside the lock: two simultaneous bids must not both win.
            if (! $locked->isOpen()) {
                throw BiddingException::closed();
            }

            $lowestNow = $locked->currentLowestAmount();

            if ($lowestNow === null) {
                if ($amount > (float) $locked->start_amount) {
                    throw BiddingException::bidTooHigh(null, (float) $locked->start_amount, true);
                }
            } elseif ($amount > $lowestNow - BiddingSession::MIN_BID_STEP) {
                throw BiddingException::bidTooHigh($lowestNow, (float) $locked->start_amount);
            }

            $flagReason = $this->suspicionReason($locked, $driver, $ip);

            $bid = DriverBid::create([
                'booking_id' => $locked->booking_id,
                'session_id' => $locked->id,
                'driver_id' => $driver->id,
                'bid_amount' => $amount,
                'status' => 'pending',
                'is_winning' => false,
                'ip_address' => $ip ? Str::limit($ip, 45, '') : null,
                'flagged' => $flagReason !== null,
                'flag_reason' => $flagReason,
            ]);

            $instantWonBooking = null;

            // Reaching the maximum service-charge floor immediately wins and stops bidding.
            if ($flagReason === null && $amount <= $minDriverPrice) {
                $booking = Booking::whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();

                $this->workflow->transition($booking, BookingWorkflow::DRIVER_ASSIGNED, [
                    'assigned_driver_id' => $driver->id,
                    'assigned_bid_id' => $bid->id,
                    'driver_payout_amount' => (float) $bid->bid_amount,
                ]);

                $locked->bids()->update(['is_winning' => false]);
                $bid->forceFill(['is_winning' => true, 'status' => 'accepted'])->save();

                $locked->status = BiddingSession::STATUS_AWARDED;
                $locked->awarded_bid_id = $bid->id;
                $locked->awarded_at = now();
                $locked->closed_at = $locked->closed_at ?? now();
                $locked->save();

                $instantWonBooking = $booking->fresh(['driver', 'customer']);
            } else {
                $this->refreshProvisionalWinner($locked);
            }

            return [$bid->refresh(), $instantWonBooking];
        });

        $this->notifications->bidPlaced($session->fresh(), $driver, $amount, $instantWonBooking !== null);

        if ($instantWonBooking) {
            $this->notifications->driverAllotted($instantWonBooking, $driver, (float) $bid->bid_amount);
        }

        return $bid;
    }

    /**
     * Close the auction (manually, or lazily when the deadline passes).
     */
    public function close(BiddingSession $session, ?User $actor = null): BiddingSession
    {
        if (in_array($session->status, [
            BiddingSession::STATUS_CLOSED,
            BiddingSession::STATUS_AWARDED,
            BiddingSession::STATUS_CANCELLED,
        ], true)) {
            return $session;
        }

        return DB::transaction(function () use ($session) {
            $locked = BiddingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            $locked->status = BiddingSession::STATUS_CLOSED;
            $locked->closed_at = now();
            $locked->save();

            $this->refreshProvisionalWinner($locked);

            return $locked;
        });
    }

    /**
     * Approve a bid and assign the driver. This is the only path from a winning
     * bid to an assignment, and it reveals full trip details to that driver.
     *
     * @throws BiddingException
     */
    public function award(BiddingSession $session, ?int $bidId, User $admin): Booking
    {
        $session = $this->closeIfDue($session);

        if ($session->status === BiddingSession::STATUS_AWARDED) {
            throw BiddingException::alreadyAwarded();
        }

        if (! $session->isAwardable()) {
            throw BiddingException::notOpen();
        }

        $bid = $bidId !== null
            ? $session->bids()->whereKey($bidId)->first()
            : $session->currentLowestBid();

        if (! $bid) {
            throw BiddingException::noBids();
        }

        $awardedBooking = DB::transaction(function () use ($session, $bid) {
            $lockedSession = BiddingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->status === BiddingSession::STATUS_AWARDED) {
                throw BiddingException::alreadyAwarded();
            }

            $booking = Booking::whereKey($lockedSession->booking_id)->lockForUpdate()->firstOrFail();

            try {
                $this->workflow->transition($booking, BookingWorkflow::DRIVER_ASSIGNED, [
                    'assigned_driver_id' => $bid->driver_id,
                    // Under the auction the driver earns their bid; the admin
                    // keeps the spread between fare and bid.
                    'assigned_bid_id' => $bid->id,
                    'driver_payout_amount' => (float) $bid->bid_amount,
                ]);
            } catch (InvalidBookingTransition $e) {
                throw new BiddingException($e->getMessage(), 422, 'booking_transition_blocked', $e->toArray());
            }

            $lockedSession->bids()->update(['is_winning' => false]);
            $bid->forceFill(['is_winning' => true, 'status' => 'accepted'])->save();

            $lockedSession->status = BiddingSession::STATUS_AWARDED;
            $lockedSession->awarded_bid_id = $bid->id;
            $lockedSession->awarded_at = now();
            $lockedSession->closed_at = $lockedSession->closed_at ?? now();
            $lockedSession->save();

            return $booking->fresh(['driver', 'customer']);
        });

        $this->notifications->driverAllotted($awardedBooking, $awardedBooking->driver, (float) $bid->bid_amount);

        return $awardedBooking;
    }

    /**
     * Abandon the auction without assigning anybody.
     */
    public function cancel(BiddingSession $session, User $admin): BiddingSession
    {
        if ($session->status === BiddingSession::STATUS_AWARDED) {
            throw BiddingException::alreadyAwarded();
        }

        return DB::transaction(function () use ($session) {
            $locked = BiddingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            $locked->status = BiddingSession::STATUS_CANCELLED;
            $locked->closed_at = now();
            $locked->save();

            $booking = Booking::whereKey($locked->booking_id)->lockForUpdate()->first();

            // Put the booking back in the confirmed pool so an admin can try
            // again or allot a driver manually.
            if ($booking && $booking->status === BookingWorkflow::BIDDING) {
                try {
                    $this->workflow->transition($booking, BookingWorkflow::CONFIRMED);
                } catch (InvalidBookingTransition) {
                    // Leave the booking where it is; the cancel itself stands.
                }
            }

            return $locked;
        });
    }

    /**
     * Resolve a link token to its session, closing it first if the deadline has
     * passed. Returns null for unknown tokens.
     */
    public function findByToken(string $token): ?BiddingSession
    {
        $session = BiddingSession::where('token', $token)->first();

        if (! $session) {
            return null;
        }

        return $this->closeIfDue($session);
    }

    /**
     * Close every auction whose deadline has passed. Scheduled command entry
     * point; the request path also closes lazily so a cron gap cannot keep an
     * expired auction open.
     */
    public function closeDueSessions(): int
    {
        $due = BiddingSession::where('status', BiddingSession::STATUS_OPEN)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->get();

        $closed = 0;

        foreach ($due as $session) {
            $this->close($session);
            $closed++;
        }

        return $closed;
    }

    public function shareUrl(BiddingSession $session): string
    {
        return route('bidding.page', ['token' => $session->token]);
    }

    /**
     * The message an admin sends on WhatsApp. Contains no customer identity and
     * no exact addresses — exactly what a bidding driver may see.
     */
    public function shareText(BiddingSession $session): string
    {
        $distance = $session->distance_km !== null ? ' • '.$session->distance_km.' km' : '';
        $closes = $session->closes_at ? ' • closes '.$session->closes_at->format('d M Y, h:i A') : '';

        return "IntercityWheels bidding\n"
            ."Booking {$session->booking->booking_reference}\n"
            ."{$session->pickup_city} → {$session->drop_city}{$distance}{$closes}\n"
            .'Start price ₹'.number_format((float) $session->start_amount, 0)."\n"
            ."Bid your lowest price — every bid must undercut the current lowest.\n"
            .$this->shareUrl($session);
    }

    public function whatsappUrl(BiddingSession $session): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->shareText($session));
    }

    /**
     * @throws BiddingException
     */
    private function assertDriverEligible(Driver $driver, BiddingSession $session, bool $skipVerification = false): void
    {
        if (! $skipVerification && ! $driver->is_verified) {
            throw BiddingException::driverNotEligible('Only verified drivers may bid.');
        }

        if ((int) $driver->id === (int) ($session->booking->assigned_driver_id ?? 0)) {
            throw BiddingException::driverNotEligible('This trip is already assigned to you.');
        }
    }

    /**
     * Cheap manipulation signal: two different driver rows bidding on the same
     * auction from one IP. Flagged bids are shown to the admin but cannot win
     * by default; the admin can still award one explicitly.
     */
    private function suspicionReason(BiddingSession $session, Driver $driver, ?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        $sameIpDifferentDriver = $session->bids()
            ->where('ip_address', $ip)
            ->where('driver_id', '!=', $driver->id)
            ->exists();

        return $sameIpDifferentDriver ? 'Same IP address as another driver' : null;
    }

    private function refreshProvisionalWinner(BiddingSession $session): void
    {
        $lowest = $session->bids()
            ->where('flagged', false)
            ->orderBy('bid_amount')
            ->orderBy('id')
            ->first();

        $session->bids()->update(['is_winning' => false]);

        if ($lowest) {
            $lowest->forceFill(['is_winning' => true])->save();
        }
    }

    private function closeIfDue(BiddingSession $session): BiddingSession
    {
        if ($session->status === BiddingSession::STATUS_OPEN && $session->isPastDeadline()) {
            return $this->close($session);
        }

        return $session;
    }

    private function resolveFeePct(mixed $input): float
    {
        if (is_numeric($input)) {
            return round(min(90.0, max(0.0, (float) $input)), 2);
        }

        $configured = PlatformSetting::get('bidding_min_service_charge_pct')
            ?? PlatformSetting::get('bidding_admin_fee_pct', (string) BiddingSession::DEFAULT_ADMIN_FEE_PCT);

        return round(min(90.0, max(0.0, (float) $configured)), 2);
    }

    private function resolveDeadline(mixed $input): ?Carbon
    {
        if ($input === null || $input === '') {
            // Keep auction open until a driver is allotted when no explicit deadline is provided.
            return null;
        }

        try {
            $deadline = Carbon::parse((string) $input);
        } catch (Throwable) {
            throw BiddingException::invalidDeadline();
        }

        if ($deadline->isPast()) {
            throw BiddingException::invalidDeadline();
        }

        return $deadline;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function routeCities(Booking $booking): array
    {
        $parts = preg_split('/\s*(?:→|->|to)\s*/u', (string) $booking->route_summary) ?: [];

        $from = trim($parts[0] ?? '');
        $to = trim($parts[1] ?? '');

        return [
            $from !== '' ? $from : 'Pickup city',
            $to !== '' ? $to : 'Drop city',
        ];
    }

    /**
     * Distance is used for driver pricing context. Prefer a configured route;
     * never invent one if it cannot be grounded (null is honest here, and the
     * masked payload simply omits it).
     */
    private function routeDistance(Booking $booking, string $pickupCity, string $dropCity): ?int
    {
        $route = RouteFare::where('is_active', true)
            ->where(function ($query) use ($pickupCity, $dropCity) {
                $query->where(function ($q) use ($pickupCity, $dropCity) {
                    $q->whereRaw('LOWER(from_city) like ?', [strtolower($pickupCity).'%'])
                        ->whereRaw('LOWER(to_city) like ?', [strtolower($dropCity).'%']);
                })->orWhere(function ($q) use ($pickupCity, $dropCity) {
                    $q->whereRaw('LOWER(from_city) like ?', [strtolower($dropCity).'%'])
                        ->whereRaw('LOWER(to_city) like ?', [strtolower($pickupCity).'%']);
                });
            })
            ->first();

        return $route?->distance_km !== null ? (int) $route->distance_km : null;
    }
}
