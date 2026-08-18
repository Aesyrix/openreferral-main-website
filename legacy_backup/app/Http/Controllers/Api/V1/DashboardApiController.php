<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if ($user->isAgent()) {
            $pendingLeads = Lead::where('assigned_agent_id', $user->id)
                ->where('status', LeadStatus::ASSIGNED)
                ->latest()->get();

            $activeLeads = Lead::where('assigned_agent_id', $user->id)
                ->whereIn('status', [LeadStatus::ACCEPTED, LeadStatus::CONTACTED, LeadStatus::APPOINTMENT_SET, LeadStatus::SHOWING, LeadStatus::UNDER_CONTRACT])
                ->latest()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => 'agent',
                    'pending_acceptance_count' => $pendingLeads->count(),
                    'pending_leads' => $pendingLeads,
                    'active_pipeline_count' => $activeLeads->count(),
                    'active_leads' => $activeLeads,
                    'subscription' => $user->activeSubscription,
                ]
            ]);
        }

        $unassigned = Lead::whereIn('status', [LeadStatus::NEW, LeadStatus::QUALIFIED])->count();
        $pendingAccept = Lead::where('status', LeadStatus::ASSIGNED)->count();
        $slaBreached = Lead::where('status', LeadStatus::UNRESPONSIVE)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => is_object($user->role) ? $user->role->value : $user->role,
                'unassigned_count' => $unassigned,
                'pending_accept_count' => $pendingAccept,
                'sla_breached_count' => $slaBreached,
                'total_leads' => Lead::count(),
            ]
        ]);
    }
}
