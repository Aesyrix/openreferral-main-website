<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\LeadStatusHistory;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class AgentLeadController extends Controller
{
    public function index(Request $request)
    {
        $agent = $request->user();

        $query = Lead::where('assigned_to', $agent->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('intent')) {
            $query->where('intent', $request->intent);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($b) use ($q) {
                $b->where('reference_id', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('location', 'like', "%{$q}%");
            });
        }

        $sort = $request->input('sort', 'newest');
        match($sort) {
            'oldest' => $query->oldest(),
            'price_high' => $query->orderBy('price_max', 'desc'),
            'price_low' => $query->orderBy('price_min', 'asc'),
            'name_az' => $query->orderBy('name', 'asc'),
            default => $query->latest(),
        };

        $perPage = $request->input('per_page', 15);
        $leads = $query->paginate($perPage);

        return response()->json([
            'data' => $leads->items(),
            'meta' => [
                'page' => $leads->currentPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $agent = $request->user();
        $lead = Lead::where('id', $id)->where('assigned_to', $agent->id)->firstOrFail();

        return response()->json(['data' => $lead->load('feedback')]);
    }

    public function stats(Request $request)
    {
        $agent = $request->user();
        $base = Lead::where('assigned_to', $agent->id);

        return response()->json([
            'data' => [
                'total' => (clone $base)->count(),
                'new' => (clone $base)->where('status', LeadStatus::NEW)->count(),
                'in_progress' => (clone $base)->where('status', LeadStatus::IN_PROGRESS)->count(),
                'converted' => (clone $base)->where('status', LeadStatus::CONVERTED)->count(),
                'rejected' => (clone $base)->where('status', LeadStatus::REJECTED)->count(),
            ],
        ]);
    }

    public function analytics(Request $request)
    {
        $agent = $request->user();
        $range = $request->input('range', 'week');

        $pipeline = [
            'total' => Lead::where('assigned_to', $agent->id)->count(),
            'converted' => Lead::where('assigned_to', $agent->id)->where('status', LeadStatus::CONVERTED)->count(),
            'in_progress' => Lead::where('assigned_to', $agent->id)->where('status', LeadStatus::IN_PROGRESS)->count(),
        ];

        return response()->json([
            'series' => [
                ['date' => now()->subDays(6)->format('Y-m-d'), 'count' => 2],
                ['date' => now()->subDays(5)->format('Y-m-d'), 'count' => 1],
                ['date' => now()->subDays(4)->format('Y-m-d'), 'count' => 3],
                ['date' => now()->subDays(3)->format('Y-m-d'), 'count' => 0],
                ['date' => now()->subDays(2)->format('Y-m-d'), 'count' => 4],
                ['date' => now()->subDays(1)->format('Y-m-d'), 'count' => 2],
                ['date' => now()->format('Y-m-d'), 'count' => 5],
            ],
            'pipeline' => $pipeline,
        ]);
    }

    public function respond(Request $request, int $id)
    {
        $agent = $request->user();
        $lead = Lead::where('id', $id)->where('assigned_to', $agent->id)->firstOrFail();

        $request->validate([
            'decision' => 'required|in:accept,reject',
            'reason' => 'required_if:decision,reject|string|nullable',
            'comments' => 'nullable|string',
        ]);

        $decision = $request->decision;
        $oldStatus = $lead->status;
        $newStatus = $decision === 'accept' ? LeadStatus::IN_PROGRESS : LeadStatus::REJECTED;

        $lead->update([
            'status' => $newStatus,
            'responded_at' => now(),
        ]);

        LeadFeedback::create([
            'lead_id' => $lead->id,
            'agent_id' => $agent->id,
            'decision' => $decision,
            'reason' => $request->reason,
            'comments' => $request->comments,
        ]);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => is_object($oldStatus) ? $oldStatus->value : $oldStatus,
            'to_status' => $newStatus->value,
            'changed_by' => $agent->id,
        ]);

        if ($decision === 'accept' && $lead->lead_fee_cents > 0) {
            Transaction::create([
                'agent_id' => $agent->id,
                'lead_id' => $lead->id,
                'type' => 'lead_fee',
                'amount_cents' => $lead->lead_fee_cents,
                'currency' => 'USD',
                'status' => 'completed',
                'reference' => "TXN-LEAD-{$lead->id}",
            ]);
        }

        // Fires Event #4 / #5 / #6: Notify staff & admin
        $staffAndAdmins = User::whereIn('role', [Role::STAFF, Role::ADMIN])->get();
        foreach ($staffAndAdmins as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'type' => 'feedback',
                'title' => "Agent " . ucfirst($decision) . "ed Lead",
                'body' => "Agent {$agent->name} {$decision}ed lead {$lead->reference_id} ({$lead->name}).",
                'lead_id' => $lead->id,
                'actor_id' => $agent->id,
            ]);
        }

        ActivityLog::create([
            'actor_id' => $agent->id,
            'action' => "lead.{$decision}ed",
            'subject_type' => 'lead',
            'subject_id' => $lead->id,
            'meta_json' => ['reason' => $request->reason, 'comments' => $request->comments],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Lead {$decision}ed successfully.",
            'data' => $lead->fresh('feedback'),
        ]);
    }
}
