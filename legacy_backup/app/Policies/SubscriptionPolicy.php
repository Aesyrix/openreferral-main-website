<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        return $subscription->agent_id === $user->id;
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
