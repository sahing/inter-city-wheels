<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(30)
            ->get()
            ->map(function (DatabaseNotification $item) {
                $data = is_array($item->data) ? $item->data : [];
                $event = (string) ($data['event'] ?? 'alert');
                $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
                $focus = (string) ($meta['focus'] ?? $this->inferFocus($event, $meta));

                if ($focus !== '' && empty($meta['focus'])) {
                    $meta['focus'] = $focus;
                }

                return [
                    'id' => $item->id,
                    'event' => $event,
                    'category' => $data['category'] ?? 'booking',
                    'title' => $data['title'] ?? 'Notification',
                    'message' => $data['message'] ?? '',
                    'meta' => $meta,
                    'focus' => $focus,
                    'action_url' => $data['action_url'] ?? null,
                    'action_label' => $data['action_label'] ?? null,
                    'read_at' => $item->read_at?->toIso8601String(),
                    'is_read' => $item->read_at !== null,
                    'created_at' => $item->created_at?->toIso8601String(),
                    'time_ago' => $item->created_at?->diffForHumans() ?? 'just now',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $user = $request->user();

        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Derive which booking or console portion a notification points to when
     * older notifications were stored without an explicit `meta.focus`.
     *
     * @param  array<string, mixed>  $meta
     */
    private function inferFocus(string $event, array $meta): string
    {
        return match ($event) {
            'booking.driver_assigned', 'booking.driver_unassigned' => ($meta['tab'] ?? '') === 'driver-trips' ? 'trip' : 'driver',
            'booking.payment_verified', 'booking.payment_driver_collect', 'booking.upi_utr_submitted', 'trip.collect_required', 'trip.payment_verified' => 'payment',
            'trip.completed' => ($meta['tab'] ?? '') === 'driver-wallet' ? 'wallet' : 'receipt',
            'booking.created' => ! empty($meta['is_beyond_region']) ? 'status' : 'otp',
            'booking.status_updated', 'booking.cancelled', 'trip.started' => 'status',
            'wallet.withdrawal' => 'wallet',
            'bidding.opened', 'bidding.bid_placed' => 'bidding',
            default => str_starts_with($event, 'vehicle.') ? 'fleet' : 'summary',
        };
    }
}
