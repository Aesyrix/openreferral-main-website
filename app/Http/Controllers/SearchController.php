<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Global search across leads (and agents, for staff/admin). */
class SearchController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $term = trim((string) $request->input('q'));
        $user = $request->user();

        if ($term === '') {
            return back();
        }

        $leads = Lead::query()
            ->with('assignedAgent')
            ->unless($user->isStaff(), fn ($q) => $q->assignedTo($user->id))
            ->search($term)
            ->latest()
            ->limit(50)
            ->get();

        // An exact reference match goes straight to the lead.
        $exact = $leads->firstWhere('reference_id', strtoupper($term));

        if ($exact) {
            return redirect()->route(
                $user->isStaff() ? 'admin.leads.show' : 'agent.leads.show',
                $exact->id
            );
        }

        $agents = $user->isStaff()
            ? User::query()->agents()
                ->with('agentProfile')
                ->where(function ($q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->limit(25)
                ->get()
            : collect();

        return view('search.index', compact('term', 'leads', 'agents'));
    }
}
