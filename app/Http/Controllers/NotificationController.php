<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The in-app notification list for dashboard users. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->with(['lead', 'actor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->boolean('unread')) {
            $query->unread();
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(25)->withQueryString(),
            'unreadCount' => Notification::where('user_id', $request->user()->id)->unread()->count(),
        ]);
    }

    public function markRead(Request $request, int $id): RedirectResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);

        $notification->update(['read_at' => now()]);

        if ($notification->lead_id) {
            return redirect()->to($request->user()->isStaff()
                ? route('admin.leads.show', $notification->lead_id)
                : route('agent.leads.show', $notification->lead_id));
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('success', $updated > 0
            ? "{$updated} notification(s) marked read."
            : 'Nothing new to catch up on.');
    }
}
