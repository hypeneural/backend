<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Support\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List user notifications
     * GET /v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $limit = $request->integer('limit', 20);

        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('cursor')) {
            $cursor = CursorPaginator::decodeCursor($request->cursor);
            if ($cursor) {
                $query->where('created_at', '<', $cursor['created_at']);
            }
        }

        $notifications = $query->limit($limit + 1)->get();
        $hasMore = $notifications->count() > $limit;

        if ($hasMore) {
            $notifications = $notifications->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $notifications->isNotEmpty()) {
            $nextCursor = CursorPaginator::encodeCursor([
                'created_at' => $notifications->last()->created_at?->toISOString(),
            ]);
        }

        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => [
                'notifications' => $notifications->map(fn($n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'body' => $n->body,
                    'image_url' => $n->image_url,
                    'data' => $n->data,
                    'is_read' => $n->read_at !== null,
                    'read_at' => $n->read_at?->toISOString(),
                    'created_at' => $n->created_at?->toISOString(),
                ]),
            ],
            'meta' => [
                'success' => true,
                'unread_count' => $unreadCount,
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
            'errors' => null,
        ]);
    }

    /**
     * Get unread count
     * GET /v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => ['count' => $count],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Mark notification as read
     * PATCH /v1/notifications/{id}/read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'data' => ['read_at' => $notification->read_at?->toISOString()],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Mark all notifications as read
     * POST /v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['marked_count' => $updated],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Delete notification
     * DELETE /v1/notifications/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'data' => ['message' => 'Notification deleted.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Get notification settings
     * GET /v1/notifications/settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $settings = NotificationSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'push_enabled' => true,
                'email_enabled' => false,
                'types' => [
                    'experience_nearby' => true,
                    'family_invite' => true,
                    'memory_reaction' => true,
                    'plan_reminder' => true,
                    'trending' => true,
                ],
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
            ]
        );

        return response()->json([
            'data' => [
                'push_enabled' => $settings->push_enabled,
                'email_enabled' => $settings->email_enabled,
                'types' => $settings->types ?? [],
                'quiet_hours' => [
                    'enabled' => $settings->quiet_hours_start !== null,
                    'start' => $settings->quiet_hours_start,
                    'end' => $settings->quiet_hours_end,
                ],
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Update notification settings
     * PUT /v1/notifications/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'push_enabled' => 'nullable|boolean',
            'email_enabled' => 'nullable|boolean',
            'types' => 'nullable|array',
            'types.*' => 'boolean',
            'quiet_hours' => 'nullable|array',
            'quiet_hours.enabled' => 'nullable|boolean',
            'quiet_hours.start' => 'nullable|date_format:H:i',
            'quiet_hours.end' => 'nullable|date_format:H:i',
        ]);

        $user = $request->user();

        $settings = NotificationSetting::firstOrCreate(
            ['user_id' => $user->id],
            ['push_enabled' => true, 'email_enabled' => false]
        );

        $updateData = [];

        if ($request->has('push_enabled')) {
            $updateData['push_enabled'] = $request->boolean('push_enabled');
        }

        if ($request->has('email_enabled')) {
            $updateData['email_enabled'] = $request->boolean('email_enabled');
        }

        if ($request->has('types')) {
            $updateData['types'] = array_merge($settings->types ?? [], $request->types);
        }

        if ($request->has('quiet_hours')) {
            $quietHours = $request->quiet_hours;
            if (isset($quietHours['enabled']) && $quietHours['enabled']) {
                $updateData['quiet_hours_start'] = $quietHours['start'] ?? null;
                $updateData['quiet_hours_end'] = $quietHours['end'] ?? null;
            } else {
                $updateData['quiet_hours_start'] = null;
                $updateData['quiet_hours_end'] = null;
            }
        }

        $settings->update($updateData);

        return response()->json([
            'data' => [
                'push_enabled' => $settings->push_enabled,
                'email_enabled' => $settings->email_enabled,
                'types' => $settings->types ?? [],
                'quiet_hours' => [
                    'enabled' => $settings->quiet_hours_start !== null,
                    'start' => $settings->quiet_hours_start,
                    'end' => $settings->quiet_hours_end,
                ],
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
