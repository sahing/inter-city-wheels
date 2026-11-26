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

                return [
                    'id' => $item->id,
                    'event' => $data['event'] ?? 'alert',
                    'category' => $data['category'] ?? 'booking',
                    'title' => $data['title'] ?? 'Notification',
                    'message' => $data['message'] ?? '',
                    'meta' => $data['meta'] ?? [],
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
}
