<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = User::where('role', 'agent')
            ->with(['agentProfile.package', 'assignedLeads'])
            ->latest()
            ->paginate(20);

        return view('staff.agents.index', compact('agents'));
    }
}
