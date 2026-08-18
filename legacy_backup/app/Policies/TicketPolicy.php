<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        return $ticket->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        return $ticket->requester_id === $user->id;
    }
}
