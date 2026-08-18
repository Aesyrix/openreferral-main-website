<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

class PackageController extends Controller
{
    public function show()
    {
        $agent = auth()->user();
        $profile = $agent->agentProfile;
        $subscription = $agent->activeSubscription;

        return view('agent.package.show', compact('agent', 'profile', 'subscription'));
    }
}
