<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Device;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** In-app notification list, read state, and FCM device registration. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $notifications = Notification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => Notification::where('user_id', $request->user()->id)->unread()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $model = Notification::where('user_id', $request->user()->id)->findOrFail($notification);

        if (! $model->isRead()) {
            $model->update(['read_at' => now()]);
        }

        return response()->json(['data' => new NotificationResource($model->refresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked read.', 'updated' => $updated]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => Notification::where('user_id', $request->user()->id)->unread()->count(),
        ]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:400'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
        ]);

        // A token belongs to one user: re-registering moves it across accounts.
        $device = Device::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device registered for push notifications.',
            'data' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ],
        ]);
    }

    public function removeDevice(Request $request, string $token): JsonResponse
    {
        Device::where('user_id', $request->user()->id)->where('token', $token)->delete();

        return response()->json(['message' => 'Device unregistered.']);
    }
}
