<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BiddingException;
use App\Http\Controllers\Controller;
use App\Models\BiddingSession;
use App\Models\Driver;
use App\Services\BiddingService;
use Illuminate\Http\Request;

/**
 * The driver-facing side of the marketplace, reached through the WhatsApp link.
 *
 * Privacy boundary: `show()` returns BiddingSession::toDriverPayload(), which is
 * built from the session's own columns and never joins the booking. Customer
 * name, phone, email, addresses and the customer's fare are physically absent
 * from the response — not hidden with CSS, not stripped in JavaScript.
 */
class BiddingController extends Controller
{
    public function __construct(private readonly BiddingService $bidding) {}

    public function show(Request $request, string $token)
    {
        $session = $this->bidding->findByToken($token);

        if (! $session) {
            return response()->json([
                'success' => false,
                'code' => 'bidding_not_found',
                'message' => 'This bidding link is not valid.',
            ], 404);
        }

        $driver = $request->user()?->driverProfile();

        return response()->json([
            'success' => true,
            'session' => $session->toDriverPayload($driver?->id),
            'viewer' => $this->viewerState($request, $session, $driver),
        ]);
    }

    /**
     * Place a bid. Every rule (eligibility, ordering, limits, deadline) is
     * enforced server-side in BiddingService.
     */
    public function placeBid(Request $request, string $token)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:10000000',
        ]);

        $session = $this->bidding->findByToken($token);

        if (! $session) {
            return response()->json([
                'success' => false,
                'code' => 'bidding_not_found',
                'message' => 'This bidding link is not valid.',
            ], 404);
        }

        $user = $request->user();

        if (! $user || $user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'code' => 'driver_only',
                'message' => 'Only registered driver partners can bid.',
            ], 403);
        }

        $driver = $user->driverProfile();

        if (! $driver) {
            return response()->json([
                'success' => false,
                'code' => 'driver_profile_missing',
                'message' => 'No driver profile is linked to this account.',
            ], 403);
        }

        try {
            $bid = $this->bidding->placeBid(
                $session,
                $driver,
                (float) $validated['amount'],
                $request->ip()
            );
        } catch (BiddingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        $session->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Bid placed. You are currently the lowest bidder.',
            'bid' => [
                'id' => $bid->id,
                'amount' => (float) $bid->bid_amount,
                'placed_at' => $bid->created_at?->toIso8601String(),
                'is_lowest' => $bid->is_winning,
            ],
            'session' => $session->toDriverPayload($driver->id),
            'viewer' => $this->viewerState($request, $session, $driver),
        ]);
    }

    /**
     * Every auction a signed-in driver may bid on right now, in the shape the
     * driver portal renders. Each entry is still `toDriverPayload()` — route,
     * distance, money and deadline only — plus the link to its own page.
     */
    public function activeAuctions(Request $request)
    {
        $driver = $request->user()?->driverProfile();

        $sessions = BiddingSession::with('booking')
            ->where('status', BiddingSession::STATUS_OPEN)
            ->where(function ($query) {
                $query->whereNull('closes_at')->orWhere('closes_at', '>', now());
            })
            ->orderBy('closes_at')
            ->get();

        $auctions = $sessions->map(function (BiddingSession $session) use ($driver) {
            $payload = $session->toDriverPayload($driver?->id);
            $myBids = $driver
                ? $session->bids()->where('driver_id', $driver->id)->count()
                : 0;

            $payload['bid_url'] = route('bidding.page', ['token' => $session->token]);
            $payload['my_bids'] = $myBids;
            $payload['remaining_bids'] = max(0, BiddingSession::MAX_BIDS_PER_DRIVER - $myBids);

            return $payload;
        })->values();

        return response()->json([
            'success' => true,
            'count' => $auctions->count(),
            'can_bid' => (bool) ($driver?->is_verified),
            'auctions' => $auctions,
        ]);
    }

    /**
     * A driver's own bid history for one auction (nothing about competitors).
     */
    public function mine(Request $request, string $token)
    {
        $session = $this->bidding->findByToken($token);

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'This bidding link is not valid.'], 404);
        }

        $driver = $request->user()?->driverProfile();

        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'No driver profile is linked to this account.'], 403);
        }

        $bids = $session->bids()
            ->where('driver_id', $driver->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($bid) => [
                'id' => $bid->id,
                'amount' => (float) $bid->bid_amount,
                'placed_at' => $bid->created_at?->toIso8601String(),
                'is_lowest' => (bool) $bid->is_winning,
                'flagged' => (bool) $bid->flagged,
            ])->values();

        return response()->json([
            'success' => true,
            'bids' => $bids,
            'remaining_bids' => max(0, BiddingSession::MAX_BIDS_PER_DRIVER - $bids->count()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewerState(Request $request, BiddingSession $session, ?Driver $driver): array
    {
        $winning = $session->winningBid()->first();

        return [
            'authenticated' => $request->user() !== null,
            'is_driver' => $request->user()?->role === 'driver',
            'driver_profile' => $driver ? [
                'id' => $driver->id,
                'name' => $driver->name,
                'vehicle_model' => $driver->vehicle_model,
                'vehicle_plate' => $driver->vehicle_plate,
                'rating' => (float) $driver->rating,
                'is_verified' => (bool) $driver->is_verified,
            ] : null,
            'can_bid' => $session->isOpen()
                && $driver !== null
                && $request->user()?->role === 'driver'
                && $driver->is_verified,
            'awarded_to_you' => $winning !== null
                && $driver !== null
                && (int) $winning->driver_id === (int) $driver->id,
        ];
    }
}
