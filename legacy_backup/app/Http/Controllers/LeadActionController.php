<?php

namespace App\Http\Controllers;

use App\Enums\NoteVisibility;

use App\Models\Lead;

use App\Models\Note;
use App\Models\User;
use App\Services\LeadAssigner;
use App\Services\LeadPacketPdfService;
use App\Services\LeadWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeadActionController extends Controller
{
    public function assign(Request $request, string $uuid, LeadAssigner $assigner)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('assign', $lead);

        $request->validate([
            'agent_id' => 'required|exists:users,id',
            'note' => 'nullable|string',
            'sla_hours' => 'nullable|integer|min:1|max:48',
        ]);

        $agent = User::findOrFail($request->agent_id);
        $assigner->assign($lead, $agent, auth()->user(), $request->note, $request->sla_hours);

        return back()->with('success', "Lead {$lead->public_ref} successfully assigned to {$agent->name}.");
    }

    public function reassign(Request $request, string $uuid, LeadAssigner $assigner)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('reassign', $lead);

        $request->validate([
            'agent_id' => 'required|exists:users,id',
            'note' => 'required|string|min:4',
            'sla_hours' => 'nullable|integer|min:1|max:48',
        ]);

        $agent = User::findOrFail($request->agent_id);
        $assigner->reassign($lead, $agent, auth()->user(), $request->note, $request->sla_hours);

        return back()->with('success', "Lead {$lead->public_ref} reassigned to {$agent->name}.");
    }

    public function accept(Request $request, string $uuid, LeadWorkflow $workflow)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('accept', $lead);

        $workflow->acceptLead($lead, auth()->user());

        return back()->with('success', "Exclusive referral {$lead->public_ref} accepted! You have locked exclusivity.");
    }

    public function reject(Request $request, string $uuid, LeadWorkflow $workflow)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('reject', $lead);

        $request->validate([
            'reason' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $workflow->rejectLead($lead, auth()->user(), $request->reason, $request->note);

        return back()->with('success', "Referral {$lead->public_ref} rejected and returned to OpenReferral staff queue.");
    }

    public function updateProgress(Request $request, string $uuid, LeadWorkflow $workflow)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $lead);

        $request->validate([
            'status' => 'required|string',
            'outcome' => 'required|string',
            'note' => 'required|string|min:8',
            'next_action_at' => 'nullable|date',
            'appointment_at' => 'nullable|date',
            'expected_close_at' => 'nullable|date',
        ]);

        $workflow->updateProgress(
            $lead,
            auth()->user(),
            $request->status,
            $request->outcome,
            $request->note,
            $request->next_action_at,
            $request->appointment_at,
            $request->expected_close_at
        );

        return back()->with('success', 'Progress update logged successfully.');
    }

    public function close(Request $request, string $uuid, LeadWorkflow $workflow)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('close', $lead);

        $request->validate([
            'sale_price' => 'required|numeric|min:1000',
            'referral_fee_pct' => 'required|numeric|min:1|max:50',
            'closed_at' => 'nullable|date',
        ]);

        $salePriceCents = (int) ($request->sale_price * 100);
        $workflow->closeWon($lead, auth()->user(), $salePriceCents, (float)$request->referral_fee_pct, $request->closed_at);

        return back()->with('success', "Lead {$lead->public_ref} marked Closed Won! Referral fee recorded.");
    }

    public function addNote(Request $request, string $uuid)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $lead);

        $request->validate([
            'body' => 'required|string|min:3',
            'visibility' => 'nullable|string|in:internal,shared_with_agent',
        ]);

        $vis = $request->visibility ?? (auth()->user()->isAgent() ? 'shared_with_agent' : 'internal');

        Note::create([
            'notable_type' => Lead::class,
            'notable_id' => $lead->id,
            'user_id' => auth()->id(),
            'visibility' => NoteVisibility::from($vis),
            'body' => $request->body,
        ]);

        return back()->with('success', 'Note added.');
    }

    public function downloadPacket(string $uuid, LeadPacketPdfService $pdfService)
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $lead);

        return $pdfService->generateLeadPacket($lead);
    }
}
