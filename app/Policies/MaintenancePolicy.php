<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Maintenance;

class MaintenancePolicy
{
    public function viewAny(User $user)
    {
        // All users can view maintenance schedule
        return true;
    }

    public function view(User $user, Maintenance $maintenance)
    {
        // All users can view maintenance details
        return true;
    }

    public function create(User $user)
    {
        // Only developers and super admins can create maintenance
        return in_array($user->user_type, [0, 5]);
    }

    public function update(User $user, Maintenance $maintenance)
    {
        // Only developers and super admins can update maintenance
        return in_array($user->user_type, [0, 5]);
    }

    public function delete(User $user, Maintenance $maintenance)
    {
        // Only super admins can delete maintenance
        return $user->user_type === 0;
    }

    public function start(User $user, Maintenance $maintenance)
    {
        // Only developers and super admins can start maintenance
        return in_array($user->user_type, [0, 5]);
    }

    public function complete(User $user, Maintenance $maintenance)
    {
        // Only developers and super admins can complete maintenance
        return in_array($user->user_type, [0, 5]);
    }

    public function cancel(User $user, Maintenance $maintenance)
    {
        // Only developers and super admins can cancel maintenance
        return in_array($user->user_type, [0, 5]);
    }

    public function notify(User $user, Maintenance $maintenance)
    {
        // Only developers and super admins can send notifications
        return in_array($user->user_type, [0, 5]);
    }
}