<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (empty($q)) {
            return back();
        }

        $user = auth()->user();

        // If direct public_ref match e.g. OR-1005
        $exactLead = Lead::where('public_ref', 'like', "%{$q}%");
        if ($user->isAgent()) {
            $exactLead->where('assigned_agent_id', $user->id);
        }
        $target = $exactLead->first();

        if ($target) {
            if ($user->isAdmin()) {
                return redirect()->route('admin.leads.show', $target->uuid);
            } elseif ($user->isStaff()) {
                return redirect()->route('staff.leads.show', $target->uuid);
            } else {
                return redirect()->route('agent.leads.show', $target->uuid);
            }
        }

        // Otherwise redirect to lead list with search query
        if ($user->isAdmin()) {
            return redirect()->route('admin.leads.index', ['q' => $q]);
        } elseif ($user->isStaff()) {
            return redirect()->route('staff.leads.index', ['q' => $q]);
        } else {
            return redirect()->route('agent.leads.index', ['q' => $q]);
        }
    }
}
