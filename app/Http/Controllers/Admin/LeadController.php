<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\LeadWorkflow;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lead CRUD, assignment, and status changes for staff + admin (§7). */
class LeadController extends Controller
{
    public function __construct(private LeadWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $request->validate([
            'status' => ['nullable', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'intent' => ['nullable', Rule::in(['buying', 'selling'])],
            'agent_id' => ['nullable', 'integer'],
            'assignment' => ['nullable', Rule::in(['assigned', 'unassigned'])],
            'q' => ['nullable', 'string', 'max:190'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'price_high', 'price_low', 'name_az'])],
        ]);

        $leads = Lead::query()
            ->with(['assignedAgent', 'assignedBy', 'latestFeedback'])
            ->when($request->filled('status'), fn ($q) => $q->status($request->input('status')))
            ->when($request->filled('intent'), fn ($q) => $q->where('intent', $request->input('intent')))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('assigned_to', $request->integer('agent_id')))
            ->when($request->input('assignment') === 'assigned', fn ($q) => $q->whereNotNull('assigned_to'))
            ->when($request->input('assignment') === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->search($request->input('q'))
            ->sorted($request->input('sort'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.leads.index', [
            'leads' => $leads,
            'agents' => $this->assignableAgents(),
            'statusCounts' => Lead::query()
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->pluck('aggregate', 'status'),
            'unassignedCount' => Lead::whereNull('assigned_to')->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('admin.leads.create', [
            'agents' => $this->assignableAgents(),
            'defaultFee' => (float) Setting::get('default_lead_fee_dollars', 250),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $data = $this->validateLead($request);

        $lead = Lead::create($this->leadAttributes($data) + [
            'status' => LeadStatus::NEW,
            'created_by' => auth()->id(),
        ]);

        ActivityLogger::lead('lead.created', $lead->id, ['reference_id' => $lead->reference_id]);

        if (! empty($data['assign_to'])) {
            $agent = User::query()->agents()->findOrFail($data['assign_to']);
            $this->workflow->assign($lead, $agent, auth()->user());

            return redirect()
                ->route('admin.leads.show', $lead->id)
                ->with('success', "Lead {$lead->reference_id} created and assigned to {$agent->name}.");
        }

        return redirect()
            ->route('admin.leads.show', $lead->id)
            ->with('success', "Lead {$lead->reference_id} created. Assign it to an agent when you're ready.");
    }

    public function show(int $id): View
    {
        $lead = Lead::with([
            'assignedAgent.agentProfile',
            'assignedBy',
            'createdBy',
            'feedback.agent',
            'assignments.agent',
            'assignments.assignedBy',
            'statusHistory.changedBy',
            'transactions',
        ])->findOrFail($id);

        $this->authorize('view', $lead);

        return view('admin.leads.show', [
            'lead' => $lead,
            'agents' => $this->assignableAgents(),
        ]);
    }

    public function edit(int $id): View
    {
        $lead = Lead::findOrFail($id);

        $this->authorize('update', $lead);

        return view('admin.leads.edit', [
            'lead' => $lead,
            'agents' => $this->assignableAgents(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);

        $this->authorize('update', $lead);

        $data = $this->validateLead($request, creating: false);

        $lead->update($this->leadAttributes($data));

        ActivityLogger::lead('lead.updated', $lead->id);

        return redirect()
            ->route('admin.leads.show', $lead->id)
            ->with('success', "Lead {$lead->reference_id} updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);

        $this->authorize('delete', $lead);

        $reference = $lead->reference_id;
        $lead->delete();

        ActivityLogger::lead('lead.deleted', $lead->id, ['reference_id' => $reference]);

        return redirect()
            ->route('admin.leads.index')
            ->with('success', "Lead {$reference} deleted.");
    }

    /** Assign or reassign — fires event #3. */
    public function assign(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['agent_id' => ['required', 'integer', 'exists:users,id']]);

        $lead = Lead::findOrFail($id);

        $this->authorize('assign', $lead);

        $agent = User::query()->agents()->findOrFail($data['agent_id']);

        $this->workflow->assign($lead, $agent, auth()->user());

        return back()->with('success', "Lead {$lead->reference_id} assigned to {$agent->name}. They've been notified.");
    }

    public function unassign(int $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);

        $this->authorize('assign', $lead);

        $this->workflow->unassign($lead, auth()->user());

        return back()->with('success', "Lead {$lead->reference_id} returned to the unassigned pool.");
    }

    /** Status change, e.g. marking a lead converted — fires event #7. */
    public function status(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(LeadStatus::cases(), 'value'))],
        ]);

        $lead = Lead::findOrFail($id);

        $this->authorize('changeStatus', $lead);

        $status = LeadStatus::from($data['status']);

        $this->workflow->changeStatus($lead, $status, auth()->user());

        return back()->with('success', "Lead {$lead->reference_id} marked {$status->label()}.");
    }

    /** The agent's decision, reason, and comments. */
    public function feedback(int $id): View
    {
        $lead = Lead::with(['feedback.agent', 'assignedAgent'])->findOrFail($id);

        $this->authorize('view', $lead);

        return view('admin.leads.feedback', ['lead' => $lead]);
    }

    // ------------------------------------------------------------- Internals

    private function validateLead(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'intent' => ['required', Rule::in(['buying', 'selling'])],
            'property_type' => ['nullable', 'string', 'max:120'],
            'price_min' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'price_max' => ['nullable', 'integer', 'min:0', 'max:4294967295', 'gte:price_min'],
            'location' => ['required', 'string', 'max:190'],
            'timeline' => ['nullable', 'string', 'max:80'],
            'bedrooms' => ['nullable', 'string', 'max:20'],
            'bathrooms' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'lead_fee_dollars' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'assign_to' => $creating ? ['nullable', 'integer', 'exists:users,id'] : ['nullable'],
        ]);
    }

    private function leadAttributes(array $data): array
    {
        $fee = $data['lead_fee_dollars'] ?? Setting::get('default_lead_fee_dollars', 250);

        return [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'intent' => LeadIntent::from($data['intent']),
            'property_type' => $data['property_type'] ?? null,
            'price_min' => $data['price_min'] ?? null,
            'price_max' => $data['price_max'] ?? null,
            'location' => $data['location'],
            'timeline' => $data['timeline'] ?? null,
            'bedrooms' => $data['bedrooms'] ?? null,
            'bathrooms' => $data['bathrooms'] ?? null,
            'source' => $data['source'] ?? 'Dashboard',
            'notes' => $data['notes'] ?? null,
            'lead_fee_cents' => (int) round(((float) $fee) * 100),
        ];
    }

    /** @return \Illuminate\Support\Collection<int,User> */
    private function assignableAgents()
    {
        return User::query()->agents()->active()->with('agentProfile')->orderBy('name')->get();
    }
}
