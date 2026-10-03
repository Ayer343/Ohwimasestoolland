<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserEmailAccount;

class UserEmailAccountPolicy
{
    public function view(User $user, UserEmailAccount $emailAccount)
    {
        return $user->id === $emailAccount->user_id;
    }

    public function create(User $user)
    {
        return true; // Any authenticated user can create
    }

    public function update(User $user, UserEmailAccount $emailAccount)
    {
        return $user->id === $emailAccount->user_id;
    }

    public function delete(User $user, UserEmailAccount $emailAccount)
    {
        return $user->id === $emailAccount->user_id;
    }

    public function restore(User $user, UserEmailAccount $emailAccount)
    {
        return $user->id === $emailAccount->user_id;
    }

    public function forceDelete(User $user, UserEmailAccount $emailAccount)
    {
        return $user->id === $emailAccount->user_id;
    }
}