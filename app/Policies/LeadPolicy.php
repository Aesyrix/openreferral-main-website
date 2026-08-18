<?php

namespace App\Policies;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Lead $lead): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        // Agents only ever see leads assigned to them.
        return $user->isStaff() || $lead->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff() && $user->isActive();
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->isStaff() && $user->isActive();
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->isStaff() && $user->isActive();
    }

    public function changeStatus(User $user, Lead $lead): bool
    {
        return $user->isStaff() && $user->isActive();
    }

    /** Only the assigned agent can accept/reject, and only while it's open. */
    public function respond(User $user, Lead $lead): bool
    {
        return $user->isAgent()
            && $user->isActive()
            && $lead->assigned_to === $user->id
            && ! in_array($lead->status, [LeadStatus::CONVERTED, LeadStatus::REJECTED], true);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isStaff() && $user->isActive();
    }

    public function forceDelete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Lead $lead): bool
    {
        return $user->isStaff() && $user->isActive();
    }
}
