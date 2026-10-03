<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SecuritySupervisorAssignment;
use Illuminate\Support\Facades\Log;

class SecuritySupervisorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission = null)
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'You must be logged in to access this resource.');
        }

        // Log the request for debugging
        Log::info('Security Supervisor Middleware Check', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'route' => $request->route()->getName(),
            'permission_required' => $permission,
            'supervisor_level' => $user->supervisor_level ?? 0,
            'can_be_supervisor' => $user->can_be_supervisor ?? false,
            'roles' => $user->roles->pluck('slug')->toArray(),
        ]);

        // ✅ SUPER ADMIN AND ADMIN BYPASS
        if ($this->isSuperAdminOrAdmin($user)) {
            Log::info('Super Admin/Admin bypass granted', ['user_id' => $user->id]);
            return $next($request);
        }

        // ✅ CHECK IF USER IS AN AREA SUPERVISOR
        $isSupervisor = $this->isAreaSupervisor($user);

        if (!$isSupervisor) {
            Log::warning('Access denied - User is not a supervisor', ['user_id' => $user->id]);
            abort(403, 'You must be an Area Supervisor to access this resource.');
        }

        // ✅ CHECK SPECIFIC PERMISSION IF REQUIRED
        if ($permission) {
            $hasPermission = $this->hasPermission($user, $permission);
            
            if (!$hasPermission) {
                Log::warning('Access denied - Missing permission', [
                    'user_id' => $user->id,
                    'permission_required' => $permission
                ]);
                abort(403, "You need the '{$permission}' permission to perform this action.");
            }
            
            Log::info('Permission granted', [
                'user_id' => $user->id,
                'permission' => $permission
            ]);
        }

        return $next($request);
    }

    /**
     * Check if user is Super Admin or Admin
     *
     * @param User $user
     * @return bool
     */
    private function isSuperAdminOrAdmin(User $user): bool
    {
        // Check by role
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return true;
        }
        
        // Check by type (legacy)
        if (isset($user->type)) {
            if ((int) $user->type === User::TYPE_SUPER_ADMIN || 
                (int) $user->type === User::TYPE_ADMIN) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a user is an Area Supervisor
     *
     * @param User $user
     * @return bool
     */
    private function isAreaSupervisor(User $user): bool
    {
        // ✅ 1. Check by role
        if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
            return true;
        }

        // ✅ 2. Check by supervisor level (Post Commander or higher)
        if (isset($user->supervisor_level) && $user->supervisor_level >= 3) {
            return true;
        }

        // ✅ 3. Check by supervisor type in active assignments
        $hasSupervisorAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereIn('supervisor_type', [
                    'area_supervisor', 
                    'post_commander', 
                    'section_lead'
                ])
                // OR has personnel management permission in metadata
                ->orWhereJsonContains('metadata->permissions', 'manage_personnel')
                ->orWhereJsonContains('metadata->permissions', 'manage_security_team');
            })
            ->exists();

        if ($hasSupervisorAssignment) {
            return true;
        }

        // ✅ 4. Check if user can_be_supervisor AND has supervisor_level >= 2
        if (isset($user->can_be_supervisor) && $user->can_be_supervisor && 
            isset($user->supervisor_level) && $user->supervisor_level >= 2) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has a specific permission
     *
     * @param User $user
     * @param string $permission
     * @return bool
     */
    private function hasPermission(User $user, string $permission): bool
    {
        // Super Admins and Admins have all permissions
        if ($this->isSuperAdminOrAdmin($user)) {
            return true;
        }

        // Check assignment metadata for permissions
        $hasPermission = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) use ($permission) {
                $q->whereJsonContains('metadata->permissions', $permission)
                  ->orWhereJsonContains('metadata->permissions', 'full_access')
                  ->orWhereJsonContains('metadata->permissions', 'manage_security_team');
            })
            ->exists();

        if ($hasPermission) {
            return true;
        }

        // Check based on supervisor level
        $levelPermissions = $this->getLevelPermissions($user->supervisor_level ?? 0);
        if (in_array($permission, $levelPermissions)) {
            return true;
        }

        return false;
    }

    /**
     * Get permissions for a supervisor level
     *
     * @param int $level
     * @return array
     */
    private function getLevelPermissions(int $level): array
    {
        $basePermissions = ['view_personnel', 'view_schedules', 'view_attendance'];
        
        $levelPermissions = match($level) {
            1 => array_merge($basePermissions, ['manage_team_schedule']),
            2 => array_merge($basePermissions, [
                'manage_team_schedule',
                'manage_personnel',
                'add_personnel',
                'assign_tasks',
                'view_performance'
            ]),
            3 => array_merge($basePermissions, [
                'manage_team_schedule',
                'manage_personnel',
                'add_personnel',
                'assign_tasks',
                'view_performance',
                'manage_security_team',
                'full_access'
            ]),
            default => $basePermissions,
        };

        return $levelPermissions;
    }
}