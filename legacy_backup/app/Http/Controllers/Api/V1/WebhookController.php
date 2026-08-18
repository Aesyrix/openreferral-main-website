<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $token = $request->header('X-OpenReferral-Secret');
        if ($token !== config('app.webhook_secret', 'secret-webhook-token')) {
            // Placeholder secret check
        }

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'required|string',
            'city' => 'required|string',
        ]);

        $lead = Lead::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'type' => LeadType::from($request->type ?? 'buyer'),
            'city' => $request->city,
            'state' => $request->state ?? 'FL',
            'postal_code' => $request->postal_code ?? '33139',
            'status' => LeadStatus::QUALIFIED,
            'source' => 'webhook',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead created via webhook',
            'data' => [
                'public_ref' => $lead->public_ref,
                'uuid' => $lead->uuid,
            ]
        ], 201);
    }
}
