<?php

namespace App\Policies;

use App\Models\RegistrationPlan;
use App\Models\User;

class RegistrationPlanPolicy
{
    /**
     * Super admin bypasses every check.
     * Return `true` here and every other method is skipped.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function view(User $user, RegistrationPlan $plan): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function update(User $user, RegistrationPlan $plan): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, RegistrationPlan $plan): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function restore(User $user, RegistrationPlan $plan): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function forceDelete(User $user, RegistrationPlan $plan): bool
    {
        return $user->isSuperAdmin();
    }
}