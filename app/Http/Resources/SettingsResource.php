<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'push_notifications' => (bool) $this->push_notifications,
            'email_notifications' => (bool) $this->email_notifications,
            'language' => $this->language ?? 'en',
            'theme' => $this->theme ?? 'system',
        ];
    }
}
