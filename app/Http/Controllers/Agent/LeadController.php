<?php

namespace App\Http\Controllers\Agent;

use App\Enums\LeadStatus;
use App\Enums\RejectionReason;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The agent's own leads on the web, including accept/reject. */
class LeadController extends Controller
{
    public function __construct(private LeadWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'intent' => ['nullable', Rule::in(['buying', 'selling'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'price_high', 'price_low', 'name_az'])],
        ]);

        $agent = $request->user();

        return view('agent.leads.index', [
            'leads' => Lead::assignedTo($agent->id)
                ->when($request->filled('status'), fn ($q) => $q->status($request->input('status')))
                ->when($request->filled('intent'), fn ($q) => $q->where('intent', $request->input('intent')))
                ->search($request->input('q'))
                ->sorted($request->input('sort'))
                ->paginate(15)
                ->withQueryString(),
            'statusCounts' => Lead::assignedTo($agent->id)
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $lead = Lead::with(['feedback.agent', 'statusHistory.changedBy'])
            ->assignedTo($request->user()->id)
            ->findOrFail($id);

        $this->authorize('view', $lead);

        return view('agent.leads.show', [
            'lead' => $lead,
            'reasons' => RejectionReason::options(),
        ]);
    }

    public function respond(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'reason' => ['nullable', 'required_if:decision,reject', Rule::in(array_keys(RejectionReason::options()))],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $agent = $request->user();
        $lead = Lead::assignedTo($agent->id)->findOrFail($id);

        $this->authorize('respond', $lead);

        $this->workflow->respond(
            $lead,
            $agent,
            $data['decision'],
            $data['reason'] ?? null,
            $data['comments'] ?? null,
        );

        return redirect()
            ->route('agent.leads.show', $lead->id)
            ->with('success', $data['decision'] === 'accept'
                ? "You accepted {$lead->reference_id}. Reach out to the client now."
                : "You rejected {$lead->reference_id}. Our team has been notified.");
    }
}
