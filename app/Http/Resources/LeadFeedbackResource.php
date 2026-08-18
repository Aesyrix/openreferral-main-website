<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'agent_id' => $this->agent_id,
            'decision' => $this->decision,
            'reason' => $this->reason,
            'reason_label' => $this->reasonLabel(),
            'comments' => $this->comments,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
