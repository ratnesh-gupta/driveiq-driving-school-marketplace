<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unreadOnly' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $query = AppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unreadOnly')) {
            $query->unread();
        }

        $limit = (int) $request->input('limit', 30);
        $items = $query->limit($limit)->get();

        $unreadCount = AppNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'data' => NotificationResource::collection($items),
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->markRead();

        return response()->json(new NotificationResource($notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated' => $updated,
        ]);
    }
}
