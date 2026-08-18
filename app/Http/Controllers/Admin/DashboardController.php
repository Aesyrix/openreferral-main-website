<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Contracts\View\View;

/** Overview for staff and admin; revenue tiles render for admins only. */
class DashboardController extends Controller
{
    public function index(AnalyticsService $analytics): View
    {
        $counts = $analytics->statusCounts();

        $pendingAgents = User::query()->agents()
            ->where('status', UserStatus::PENDING)
            ->with('agentProfile')
            ->latest()
            ->get();

        return view('admin.dashboard', [
            'counts' => $counts,
            'conversionRate' => $analytics->conversionRate(),
            'acceptanceRate' => $analytics->acceptanceRate(),
            'avgResponseHours' => $analytics->avgResponseHours(),
            'trend' => $analytics->leadsPerDay('week'),
            'pendingAgents' => $pendingAgents,
            'activeAgents' => User::query()->agents()->active()->count(),
            'suspendedAgents' => User::query()->agents()->where('status', UserStatus::SUSPENDED)->count(),
            'unassigned' => Lead::whereNull('assigned_to')->whereNull('deleted_at')->latest()->take(8)->get(),
            'awaitingResponse' => Lead::status(LeadStatus::NEW)
                ->whereNotNull('assigned_to')
                ->with('assignedAgent')
                ->orderBy('assigned_at')
                ->take(8)
                ->get(),
            'recentFeedback' => LeadFeedback::with(['lead', 'agent'])->orderByDesc('id')->take(8)->get(),
            'recentActivity' => ActivityLog::with('actor')->orderByDesc('id')->take(10)->get(),
            'totalRevenueCents' => auth()->user()->isAdmin() ? $analytics->totalRevenueCents() : null,
            'monthRevenueCents' => auth()->user()->isAdmin() ? $analytics->revenueThisMonthCents() : null,
        ]);
    }
}
