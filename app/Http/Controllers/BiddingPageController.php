<?php

namespace App\Http\Controllers;

use App\Models\BiddingSession;
use App\Services\BiddingService;
use Illuminate\Http\Request;

/**
 * The public bidding page a driver opens from WhatsApp.
 *
 * Server-rendered so the masked payload is the only booking data in the HTML.
 * Full trip details are added to the view only after the admin has awarded the
 * auction to the signed-in driver.
 */
class BiddingPageController extends Controller
{
    public function __construct(private readonly BiddingService $bidding) {}

    public function show(Request $request, string $token)
    {
        $session = $this->bidding->findByToken($token);

        if (! $session) {
            return response()->view('bidding', [
                'missing' => true,
                'token' => $token,
            ], 404);
        }

        $user = $request->user();
        $driver = $user?->driverProfile();

        $winning = $session->winningBid()->first();
        $awardedToYou = $winning !== null
            && $driver !== null
            && (int) $winning->driver_id === (int) $driver->id;

        // The reveal: only the awarded driver (and only through this page) gets
        // the booking in full.
        $revealedBooking = $awardedToYou
            ? $session->booking()->with('driver')->first()
            : null;

        $myBids = $driver
            ? $session->bids()->where('driver_id', $driver->id)->orderBy('id')->get()
            : collect();

        return response()->view('bidding', [
            'missing' => false,
            'token' => $token,
            'session' => $session,
            'payload' => $session->toDriverPayload($driver?->id),
            'user' => $user,
            'driver' => $driver,
            'isVerified' => $driver?->is_verified ?? false,
            'awardedToYou' => $awardedToYou,
            'revealedBooking' => $revealedBooking,
            'myBids' => $myBids,
            'remainingBids' => max(0, BiddingSession::MAX_BIDS_PER_DRIVER - $myBids->count()),
            'loginUrl' => route('home').'?next='.rawurlencode('/bid/'.$token),
        ]);
    }
}
