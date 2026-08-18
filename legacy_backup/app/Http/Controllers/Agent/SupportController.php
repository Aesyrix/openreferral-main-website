<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index()
    {
        $agent = auth()->user();
        $tickets = Ticket::where('requester_id', $agent->id)->latest()->get();

        return view('agent.support.index', compact('tickets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|min:4',
            'body' => 'required|string|min:10',
            'priority' => 'nullable|string',
        ]);

        Ticket::create([
            'subject' => $request->subject,
            'body' => $request->body,
            'priority' => $request->priority ?? 'normal',
            'status' => 'open',
            'requester_id' => auth()->id(),
        ]);

        return back()->with('success', 'Support ticket opened successfully.');
    }
}
