<?php

namespace App\Http\Controllers\Agent;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Notification;
use App\Services\AnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Web counterpart of the app's home screen — same numbers as
 * `GET /api/leads/stats` and `GET /api/leads/analytics`.
 */
class DashboardController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): View
    {
        $agent = $request->user();

        return view('agent.dashboard', [
            'agent' => $agent->load(['agentProfile', 'serviceAreas']),
            'counts' => $analytics->statusCounts($agent->id),
            'conversionRate' => $analytics->conversionRate($agent->id),
            'avgResponseHours' => $analytics->avgResponseHours($agent->id),
            'trend' => $analytics->leadsPerDay('week', $agent->id),
            'awaiting' => Lead::assignedTo($agent->id)
                ->status(LeadStatus::NEW)
                ->orderBy('assigned_at')
                ->get(),
            'active' => Lead::assignedTo($agent->id)
                ->status(LeadStatus::IN_PROGRESS)
                ->latest('responded_at')
                ->take(10)
                ->get(),
            'revenueCents' => $agent->transactions()->where('status', 'completed')->sum('amount_cents'),
            'unreadNotifications' => Notification::where('user_id', $agent->id)->unread()->count(),
        ]);
    }
}
