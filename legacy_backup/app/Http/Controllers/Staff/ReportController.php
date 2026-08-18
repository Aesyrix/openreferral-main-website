<?php

namespace App\Http\Controllers\Staff;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $totalLeads = Lead::count();
        $acceptedCount = Lead::whereIn('status', [LeadStatus::ACCEPTED, LeadStatus::CONTACTED, LeadStatus::APPOINTMENT_SET, LeadStatus::SHOWING, LeadStatus::UNDER_CONTRACT, LeadStatus::CLOSED_WON])->count();
        $assignedCount = Lead::whereNotNull('assigned_agent_id')->count();

        $acceptanceRate = $assignedCount > 0 ? ($acceptedCount / $assignedCount) * 100 : 100.0;
        $closedLeads = Lead::where('status', LeadStatus::CLOSED_WON)->get();

        return view('staff.reports.index', compact('totalLeads', 'acceptanceRate', 'closedLeads'));
    }
}
