<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SecuritySupervisorAssignment;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Super Admins and Admins can view all
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }
        
        // Area Supervisors can view security personnel
        if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
            return true;
        }
        
        // Security personnel with supervisor level >= 2 can view
        if ($user->type === User::TYPE_SECURITY_PERSONNEL && 
            isset($user->supervisor_level) && 
            $user->supervisor_level >= 2 && 
            $user->can_be_supervisor) {
            return true;
        }
        
        // Check active supervisor assignments with view_personnel permission
        $hasAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereJsonContains('metadata->permissions', 'view_personnel')
                  ->orWhereJsonContains('metadata->permissions', 'manage_personnel');
            })
            ->exists();
        
        if ($hasAssignment) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }
        
        // Admins can view all users
        if ($user->isAdmin()) {
            return true;
        }
        
        // Area Supervisors can view security personnel
        if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
            return $model->type === User::TYPE_SECURITY_PERSONNEL;
        }
        
        // Security personnel with supervisor level >= 2 can view other security personnel
        if ($user->type === User::TYPE_SECURITY_PERSONNEL && 
            isset($user->supervisor_level) && 
            $user->supervisor_level >= 2 && 
            $user->can_be_supervisor) {
            return $model->type === User::TYPE_SECURITY_PERSONNEL;
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Super Admins and Admins can always create
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }
        
        // Area Supervisors can create security personnel
        if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
            return true;
        }
        
        // Security personnel with supervisor level >= 2 can create
        if ($user->type === User::TYPE_SECURITY_PERSONNEL && 
            isset($user->supervisor_level) && 
            $user->supervisor_level >= 2 && 
            $user->can_be_supervisor) {
            return true;
        }
        
        // Check active supervisor assignments with manage_personnel permission
        $hasAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereJsonContains('metadata->permissions', 'manage_personnel')
                  ->orWhereJsonContains('metadata->permissions', 'add_personnel')
                  ->orWhereJsonContains('metadata->permissions', 'manage_security_team');
            })
            ->exists();
        
        if ($hasAssignment) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }
        
        // Super admins can update anyone
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Admins can update non-admin users
        if ($user->isAdmin()) {
            return !$model->isAdmin() && !$model->isSuperAdmin();
        }
        
        // Area Supervisors can update security personnel in their scope
        if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
            return $model->type === User::TYPE_SECURITY_PERSONNEL;
        }
        
        // Security personnel with supervisor level >= 2 can update other security personnel
        if ($user->type === User::TYPE_SECURITY_PERSONNEL && 
            isset($user->supervisor_level) && 
            $user->supervisor_level >= 2 && 
            $user->can_be_supervisor) {
            return $model->type === User::TYPE_SECURITY_PERSONNEL;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }
        
        // Only super admins can delete users
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }
    
    /**
     * Determine whether the user can change user type/role.
     */
    public function changeRole(User $user, User $model): bool
    {
        // Cannot change your own role
        if ($user->id === $model->id) {
            return false;
        }
        
        // Only super admins can change roles
        return $user->isSuperAdmin();
    }
    
    /**
     * Determine whether the user can suspend users.
     */
    public function suspend(User $user, User $model): bool
    {
        // Cannot suspend yourself
        if ($user->id === $model->id) {
            return false;
        }
        
        // Super admins can suspend anyone
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Admins can suspend non-admin users
        if ($user->isAdmin()) {
            return !$model->isAdmin() && !$model->isSuperAdmin();
        }
        
        return false;
    }
}