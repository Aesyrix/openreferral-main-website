<?php

namespace App\Http\Controllers\Staff;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'unassigned');

        $unassigned = Lead::whereIn('status', [LeadStatus::NEW, LeadStatus::QUALIFIED])->latest()->get();
        $pendingAccept = Lead::where('status', LeadStatus::ASSIGNED)->latest()->get();
        $slaBreached = Lead::where('status', LeadStatus::UNRESPONSIVE)->latest()->get();
        $activePipeline = Lead::whereIn('status', [LeadStatus::ACCEPTED, LeadStatus::CONTACTED, LeadStatus::APPOINTMENT_SET, LeadStatus::SHOWING, LeadStatus::UNDER_CONTRACT])->latest()->get();
        $rejected = Lead::where('status', LeadStatus::REJECTED)->orWhere('rejected_count', '>', 0)->latest()->get();

        $todayFollowUps = FollowUp::with('lead', 'owner')
            ->where('due_date', '<=', now()->toDateString())
            ->where('is_done', false)
            ->get();

        $agents = User::where('role', 'agent')
            ->where('status', 'active')
            ->with('agentProfile')
            ->get();

        return view('staff.dashboard', compact(
            'tab', 'unassigned', 'pendingAccept', 'slaBreached', 'activePipeline', 'rejected',
            'todayFollowUps', 'agents'
        ));
    }
}
