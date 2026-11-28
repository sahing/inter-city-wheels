<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BiddingException;
use App\Http\Controllers\Controller;
use App\Models\BiddingSession;
use App\Models\Booking;
use App\Services\BiddingService;
use Illuminate\Http\Request;

/**
 * Admin control of the driver marketplace.
 *
 * Every response here may contain customer identity and the admin margin — the
 * routes live inside the `auth + role:admin` group and must never be reachable
 * from the public bidding link.
 */
class BiddingAdminController extends Controller
{
    public function __construct(private readonly BiddingService $bidding) {}

    /**
     * Auction board: every session with its booking, bid count and best price.
     */
    public function index(Request $request)
    {
        $query = BiddingSession::with('booking')->latest('id');

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        $sessions = $query->limit(200)->get()->map(function (BiddingSession $session) {
            $lowest = $session->currentLowestAmount();

            return [
                'id' => $session->id,
                'token' => $session->token,
                'status' => $session->status,
                'is_open' => $session->isOpen(),
                'booking_id' => $session->booking_id,
                'booking_reference' => $session->booking?->booking_reference,
                'booking_status' => $session->booking?->status,
                'customer_name' => $session->booking?->customer_name,
                'route_summary' => $session->booking?->route_summary,
                'pickup_city' => $session->pickup_city,
                'drop_city' => $session->drop_city,
                'distance_km' => $session->distance_km,
                'start_amount' => (float) $session->start_amount,
                'admin_fee_pct' => (float) $session->admin_fee_pct,
                'max_driver_price' => $session->maxDriverPrice(),
                'lowest_bid' => $lowest,
                'admin_profit_at_lowest' => $lowest === null
                    ? null
                    : $session->adminProfitAt($lowest),
                'bid_count' => $session->bids()->count(),
                'closes_at' => $session->closes_at?->toIso8601String(),
                'awarded_bid_id' => $session->awarded_bid_id,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Open the auction for a booking and return the WhatsApp share package.
     */
    public function store(Request $request, int $bookingId)
    {
        $validated = $request->validate([
            'start_amount' => 'nullable|numeric|min:1',
            'admin_fee_pct' => 'nullable|numeric|min:0|max:90',
            'closes_at' => 'nullable|string',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::findOrFail($bookingId);

        try {
            $session = $this->bidding->open($booking, $validated, $request->user());
        } catch (BiddingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bidding opened. Share the link with your drivers.',
            'session' => $session->toAdminPayload(),
            'share' => $this->sharePackage($session),
        ], 201);
    }

    /**
     * Ensure an open auction exists for a booking and return its WhatsApp share package
     * for one-click copy/share directly from Outstation Bookings & Dispatch Management.
     */
    public function share(Request $request, int $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        try {
            $session = $this->bidding->ensureShareableSession($booking, $request->user());
        } catch (BiddingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'success' => true,
            'session' => $session->toAdminPayload(),
            'share' => $this->sharePackage($session),
        ]);
    }

    public function show(int $id)
    {
        $session = BiddingSession::findOrFail($id);

        return response()->json([
            'success' => true,
            'session' => $session->toAdminPayload(),
            'share' => $this->sharePackage($session),
        ]);
    }

    public function close(Request $request, int $id)
    {
        $session = BiddingSession::findOrFail($id);

        $closed = $this->bidding->close($session, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Bidding closed.',
            'session' => $closed->fresh()->toAdminPayload(),
        ]);
    }

    /**
     * Approve the winning bid and assign the driver.
     */
    public function award(Request $request, int $id)
    {
        $validated = $request->validate([
            'bid_id' => 'nullable|integer|exists:driver_bids,id',
        ]);

        $session = BiddingSession::findOrFail($id);

        try {
            $booking = $this->bidding->award(
                $session,
                isset($validated['bid_id']) ? (int) $validated['bid_id'] : null,
                $request->user()
            );
        } catch (BiddingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        $booking->load('driver');

        return response()->json([
            'success' => true,
            'message' => 'Winning bid approved. Driver assigned and trip details released.',
            'booking' => [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'status' => $booking->status,
                'assigned_driver_id' => $booking->assigned_driver_id,
                'driver_payout_amount' => (float) $booking->driver_payout_amount,
                'total_amount' => (float) $booking->total_amount,
                'admin_profit' => round((float) $booking->total_amount - (float) $booking->driver_payout_amount, 2),
            ],
            'driver' => $booking->driver ? [
                'id' => $booking->driver->id,
                'name' => $booking->driver->name,
                'phone' => $booking->driver->phone,
                'vehicle_model' => $booking->driver->vehicle_model,
                'vehicle_plate' => $booking->driver->vehicle_plate,
            ] : null,
            'session' => $session->fresh()->toAdminPayload(),
        ]);
    }

    /**
     * Abandon the auction; the booking returns to the confirmed pool.
     */
    public function cancel(Request $request, int $id)
    {
        $session = BiddingSession::findOrFail($id);

        try {
            $cancelled = $this->bidding->cancel($session, $request->user());
        } catch (BiddingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bidding cancelled.',
            'session' => $cancelled->fresh()->toAdminPayload(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sharePackage(BiddingSession $session): array
    {
        return [
            'url' => $this->bidding->shareUrl($session),
            'whatsapp_url' => $this->bidding->whatsappUrl($session),
            'text' => $this->bidding->shareText($session),
        ];
    }
}
