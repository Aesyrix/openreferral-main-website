<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lead shape the mobile app reads (BACKEND_REQUIREMENTS §4). Prices are
 * whole dollars; the lead fee is cents, matching the schema.
 */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_id' => $this->reference_id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'phone' => $this->phone,
            'email' => $this->email,
            'intent' => $this->intent?->value,
            'property_type' => $this->property_type,
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
            'location' => $this->location,
            'timeline' => $this->timeline,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'source' => $this->source,
            'notes' => $this->notes,
            'status' => $this->status?->value,
            'lead_fee_cents' => $this->lead_fee_cents,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'my_feedback' => $this->whenLoaded(
                'feedback',
                fn () => LeadFeedbackResource::collection($this->feedback)
            ),
        ];
    }
}
