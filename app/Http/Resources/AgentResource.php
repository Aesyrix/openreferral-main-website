<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The agent profile shape the mobile app reads (BACKEND_REQUIREMENTS §3).
 */
class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->agentProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role?->value,
            'status' => $this->status?->value,
            'title' => $profile?->title ?? 'Real Estate Agent',
            'verified' => (bool) ($profile?->verified ?? false),
            'service_areas' => $this->serviceAreas->pluck('area')->values(),
            'license_number' => $profile?->license_number,
            'license_state' => $profile?->license_state,
            'brokerage' => $profile?->brokerage,
            'bio' => $profile?->bio,
            'approved_at' => $profile?->approved_at?->toIso8601String(),
            'rejected_reason' => $profile?->rejected_reason,
            'settings' => new SettingsResource($this->settingsOrDefault()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
