<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $closedLeads = Lead::where('status', LeadStatus::CLOSED_WON)->get();
        $closedVolumeCents = $closedLeads->sum('sale_price_cents');
        $referralFeeRevenueCents = $closedLeads->sum('referral_fee_cents');
        $avgFeePct = $closedLeads->avg('referral_fee_pct') ?? 20.0;
        $closedDealsCount = $closedLeads->count();

        $agentStats = User::where('role', Role::AGENT)
            ->with(['agentProfile', 'assignedLeads'])
            ->get()
            ->map(function ($agent) {
                $closed = Lead::where('assigned_agent_id', $agent->id)
                    ->where('status', LeadStatus::CLOSED_WON)
                    ->get();

                $agent->closed_count = $closed->count();
                $agent->closed_volume_cents = $closed->sum('sale_price_cents');
                $agent->referral_fee_earned_cents = $closed->sum('referral_fee_cents');

                return $agent;
            });

        return view('admin.reports.index', compact(
            'closedLeads',
            'closedVolumeCents',
            'referralFeeRevenueCents',
            'avgFeePct',
            'closedDealsCount',
            'agentStats'
        ));
    }
}
