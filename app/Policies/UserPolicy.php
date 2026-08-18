<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->isActive();
    }

    public function view(User $user, User $model): bool
    {
        return ($user->isStaff() && $user->isActive()) || $user->id === $model->id;
    }

    /** Only admins create accounts from the dashboard (staff or agents). */
    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        // Staff manage agents; only an admin touches staff/admin accounts.
        return $model->isAgent()
            ? $user->isStaff() && $user->isActive()
            : $user->isAdmin() && $user->isActive();
    }

    /** Approve / reject / suspend an agent signup. */
    public function moderate(User $user, User $model): bool
    {
        return $model->isAgent() && $user->isStaff() && $user->isActive();
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // no self-deletion from the dashboard
        }

        if ($model->isAgent()) {
            return $user->isStaff() && $user->isActive();
        }

        if (! $user->isAdmin()) {
            return false;
        }

        // Never remove the last remaining admin.
        return $model->isAdmin()
            ? User::where('role', Role::ADMIN)->count() > 1
            : true;
    }

    public function manageStaff(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }
}
