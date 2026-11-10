<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\User;
use App\Services\AgentAccountService;
use App\Services\AnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Agent roster and the approval workflow (BACKEND_GUIDE §3, §7). */
class AgentController extends Controller
{
    public function __construct(private AgentAccountService $accounts) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'status' => ['nullable', Rule::in(array_column(UserStatus::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $agents = User::query()->agents()
            ->with(['agentProfile', 'serviceAreas'])
            ->withCount([
                'assignedLeads',
                'assignedLeads as converted_leads_count' => fn ($q) => $q->where('status', 'converted'),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->input('q').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->orderByRaw("FIELD(status, 'pending', 'active', 'suspended', 'rejected')")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.agents.index', [
            'agents' => $agents,
            'statusCounts' => User::query()->agents()
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function show(int $id, AnalyticsService $analytics): View
    {
        $agent = User::query()->agents()
            ->with(['agentProfile.approver', 'serviceAreas', 'settings', 'devices'])
            ->findOrFail($id);

        $this->authorize('view', $agent);

        return view('admin.agents.show', [
            'agent' => $agent,
            'counts' => $analytics->statusCounts($agent->id),
            'conversionRate' => $analytics->conversionRate($agent->id),
            'acceptanceRate' => $analytics->acceptanceRate($agent->id),
            'avgResponseHours' => $analytics->avgResponseHours($agent->id),
            'rejectionReasons' => $analytics->rejectionReasons($agent->id),
            'leads' => Lead::assignedTo($agent->id)->latest()->paginate(15, ['*'], 'leads'),
            'feedback' => LeadFeedback::where('agent_id', $agent->id)
                ->with('lead')
                ->orderByDesc('id')
                ->take(20)
                ->get(),
            'revenueCents' => $agent->transactions()->where('status', 'completed')->sum('amount_cents'),
            // Shown on the delete confirmation so the admin sees the blast
            // radius before erasing anyone.
            'impact' => app(\App\Services\UserEraser::class)->impact($agent),
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $agent = $this->findAgent($id);

        $this->accounts->approve($agent, auth()->user());

        return back()->with('success', "{$agent->name} approved — they can now receive leads.");
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $agent = $this->findAgent($id);

        $this->accounts->reject($agent, auth()->user(), $data['reason'] ?? null);

        return back()->with('success', "{$agent->name}'s application was rejected.");
    }

    public function suspend(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $agent = $this->findAgent($id);

        $this->accounts->suspend($agent, auth()->user(), $data['reason'] ?? null);

        return back()->with('success', "{$agent->name} suspended. Open leads were returned to the pool.");
    }

    public function restore(int $id): RedirectResponse
    {
        $agent = $this->findAgent($id);

        $this->accounts->restore($agent, auth()->user());

        return back()->with('success', "{$agent->name} reactivated.");
    }

    /**
     * Permanent deletion. Irreversible, so the admin has to type the agent's
     * name — checked here and not only in the browser.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $agent = $this->findAgent($id);

        $this->authorize('delete', $agent);

        $request->validate(
            ['confirm_name' => ['required', 'string']],
            ['confirm_name.required' => "Type the agent's name to confirm deletion."]
        );

        if (trim($request->input('confirm_name')) !== $agent->name) {
            return back()->with('error', 'The name did not match, so nothing was deleted.');
        }

        $name = $agent->name;

        $impact = $this->accounts->remove($agent, auth()->user());

        $kept = $impact['transactions'] > 0
            ? " Their {$impact['transactions']} transaction(s) were kept for reporting."
            : '';

        return redirect()
            ->route('admin.agents.index')
            ->with('success', "{$name} deleted permanently.{$kept}");
    }

    /** Toggle the blue-check shown in the app. */
    public function toggleVerified(int $id): RedirectResponse
    {
        $agent = $this->findAgent($id);

        $profile = $agent->agentProfile ?? $agent->agentProfile()->create([]);
        $profile->update(['verified' => ! $profile->verified]);

        \App\Support\ActivityLogger::user(
            $profile->verified ? 'agent.verified' : 'agent.unverified',
            $agent->id
        );

        return back()->with('success', $profile->verified
            ? "{$agent->name} is now marked verified."
            : "Verified badge removed from {$agent->name}.");
    }

    private function findAgent(int $id): User
    {
        $agent = User::query()->agents()->findOrFail($id);

        $this->authorize('moderate', $agent);

        return $agent;
    }
}
