<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lead;

use App\Services\LeadWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeadApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Lead::query();

        if ($user->isAgent()) {
            $query->where('assigned_agent_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($b) use ($q) {
                $b->where('public_ref', 'like', "%{$q}%")
                  ->orWhere('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%")
                  ->orWhere('city', 'like', "%{$q}%");
            });
        }

        $leads = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $leads->items(),
            'meta' => [
                'page' => $leads->currentPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ]
        ]);
    }

    public function show(string $uuid)
    {
        $lead = Lead::where('uuid', $uuid)->first();
        if (!$lead) {
            return response()->json(['success' => false, 'message' => 'Lead not found'], 404);
        }

        if (Gate::denies('view', $lead)) {
            return response()->json(['success' => false, 'message' => 'Lead not found'], 404);
        }

        $lead->load(['assignedAgent', 'updates.user', 'documents']);

        return response()->json([
            'success' => true,
            'data' => $lead,
        ]);
    }

    public function accept(string $uuid, LeadWorkflow $workflow, Request $request)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        if (Gate::denies('accept', $lead)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized or invalid lead state'], 403);
        }

        $lead = $workflow->acceptLead($lead, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Lead accepted and exclusivity locked',
            'data' => $lead,
        ]);
    }

    public function reject(string $uuid, LeadWorkflow $workflow, Request $request)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        if (Gate::denies('reject', $lead)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'reason' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $lead = $workflow->rejectLead($lead, $request->user(), $request->reason, $request->note);

        return response()->json([
            'success' => true,
            'message' => 'Lead rejected and returned to staff pool',
            'data' => $lead,
        ]);
    }

    public function progress(string $uuid, LeadWorkflow $workflow, Request $request)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        if (Gate::denies('update', $lead)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|string',
            'outcome' => 'required|string',
            'note' => 'required|string|min:8',
            'next_action_at' => 'nullable|date',
            'appointment_at' => 'nullable|date',
            'expected_close_at' => 'nullable|date',
        ]);

        $lead = $workflow->updateProgress(
            $lead,
            $request->user(),
            $request->status,
            $request->outcome,
            $request->note,
            $request->next_action_at,
            $request->appointment_at,
            $request->expected_close_at
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress update logged successfully',
            'data' => $lead,
        ]);
    }
}
