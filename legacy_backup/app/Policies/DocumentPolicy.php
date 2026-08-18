<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Lead;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        // If document belongs to a Lead:
        if ($document->documentable_type === Lead::class || str_contains($document->documentable_type, 'Lead')) {
            $lead = Lead::find($document->documentable_id);
            if (!$lead || $lead->assigned_agent_id !== $user->id) {
                return false;
            }
            // Document MUST be shared with agent
            $vis = is_object($document->visibility) ? $document->visibility->value : (string)$document->visibility;
            return $vis === 'shared_with_agent';
        }

        // If uploader
        return $document->uploaded_by_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $document->uploaded_by_id && $document->created_at->diffInMinutes(now()) <= 10) {
            return true;
        }

        return false;
    }
}
