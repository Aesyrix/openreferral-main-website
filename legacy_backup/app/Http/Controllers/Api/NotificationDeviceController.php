<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\Device;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationDeviceController extends Controller
{
    public function notifications(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        Notification::where('id', $id)->where('user_id', $request->user()->id)->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count();
        return response()->json(['unread_count' => $count]);
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:android,ios,web',
        ]);

        Device::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->platform,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function removeDevice(Request $request, string $token)
    {
        Device::where('token', $token)->where('user_id', $request->user()->id)->delete();
        return response()->json(['success' => true]);
    }

    public function getContent(string $slug)
    {
        $page = ContentPage::where('slug', $slug)->firstOrFail();
        return response()->json([
            'title' => $page->title,
            'intro' => $page->intro,
            'sections' => $page->body_json,
        ]);
    }
}
