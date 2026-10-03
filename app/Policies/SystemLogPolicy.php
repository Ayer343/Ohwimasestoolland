<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SystemLog;

class SystemLogPolicy
{
    public function viewAny(User $user)
    {
        // Only developers and super admins can view all logs
        return in_array($user->user_type, [0, 5]);
    }

    public function view(User $user, SystemLog $log)
    {
        // Developers and super admins can view any log
        if (in_array($user->user_type, [0, 5])) {
            return true;
        }
        
        // Users can only view their own logs
        return $log->user_id === $user->id;
    }

    public function resolve(User $user, SystemLog $log)
    {
        // Only developers and super admins can resolve logs
        return in_array($user->user_type, [0, 5]);
    }

    public function delete(User $user, SystemLog $log)
    {
        // Only super admins can delete logs
        return $user->user_type === 0;
    }

    public function forceDelete(User $user, SystemLog $log)
    {
        // Only super admins can force delete logs
        return $user->user_type === 0;
    }

    public function restore(User $user, SystemLog $log)
    {
        // Only super admins can restore deleted logs
        return $user->user_type === 0;
    }
}