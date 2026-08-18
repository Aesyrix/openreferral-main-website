<?php

namespace App\Http\Controllers\Staff;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\RejectionReason;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::with(['assignedAgent', 'assignedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $leads = $query->latest()->paginate(20)->withQueryString();
        $agents = User::where('role', 'agent')->get();

        return view('staff.leads.index', compact('leads', 'agents'));
    }

    public function create()
    {
        return view('staff.leads.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'type' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postal_code' => 'required|string',
            'preferred_areas' => 'nullable|string',
            'beds' => 'nullable|integer',
            'baths' => 'nullable|numeric',
            'budget_min' => 'nullable|numeric',
            'budget_max' => 'nullable|numeric',
            'timeline' => 'nullable|string',
            'prequalified' => 'nullable|boolean',
            'dnc' => 'nullable|boolean',
            'added_by' => 'nullable|string',
            'notes_internal' => 'nullable|string',
        ]);

        $lead = Lead::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'type' => LeadType::from($request->type),
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'preferred_areas' => $request->preferred_areas,
            'beds' => $request->beds,
            'baths' => $request->baths,
            'budget_min' => $request->budget_min ? (int)($request->budget_min * 100) : null,
            'budget_max' => $request->budget_max ? (int)($request->budget_max * 100) : null,
            'timeline' => $request->timeline ?? 'ASAP',
            'prequalified' => $request->boolean('prequalified'),
            'dnc' => $request->boolean('dnc'),
            'added_by' => $request->added_by,
            'notes_internal' => $request->notes_internal,
            'status' => LeadStatus::QUALIFIED,
            'source' => 'manual',
            'created_by_id' => auth()->id(),
        ]);

        return redirect()->route('staff.leads.show', $lead->uuid)->with('success', "Lead {$lead->public_ref} created successfully.");
    }

    public function show(string $uuid)
    {
        $lead = Lead::where('uuid', $uuid)
            ->with(['assignedAgent', 'assignedBy', 'updates.user', 'notes.user', 'documents.uploader', 'assignments.agent', 'activities.user'])
            ->firstOrFail();

        $agents = User::where('role', 'agent')->where('status', 'active')->with('agentProfile')->get();
        $rejectionReasons = RejectionReason::where('is_active', true)->orderBy('sort_order')->get();

        return view('staff.leads.show', compact('lead', 'agents', 'rejectionReasons'));
    }
}
