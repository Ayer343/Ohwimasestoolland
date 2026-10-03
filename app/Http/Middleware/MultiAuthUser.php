<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class MultiAuthUser
{
    /**
     * Handle an incoming request.
     * 
     * This middleware now supports BOTH legacy type AND role-based authentication.
     * A user can access a route if EITHER:
     * 1. Their legacy type matches the allowed types, OR
     * 2. They have a role that maps to one of the allowed types
     */
    public function handle(Request $request, Closure $next, ...$userTypes): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Authentication required.'], 401);
            }
            return redirect()->route('login')->withErrors(['error' => 'Please login to access this page.']);
        }

        $user = auth()->user();
        
        // Convert string user types to integers (since middleware parameters are strings)
        $allowedTypes = array_map('intval', $userTypes);
        
        // Check BOTH legacy type AND roles
        $isAuthorized = $this->isUserAuthorized($user, $allowedTypes);
        
        if (!$isAuthorized) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You are not authorized to access this page.',
                    'required_types' => $allowedTypes,
                    'user_type' => $user->getRawOriginal('type'),
                    'user_roles' => $user->roles->pluck('slug')->toArray()
                ], 403);
            }
            
            // Don't redirect away if we're in the middle of switching roles
            if ($request->ajax() || $request->wantsJson() || $request->has('_switch')) {
                return response()->json([
                    'message' => 'You are not authorized to access this page.',
                    'required_types' => $allowedTypes
                ], 403);
            }
            
            // Store the intended URL before redirecting
            $intendedUrl = $request->fullUrl();
            session(['url.intended' => $intendedUrl]);
            
            // Redirect to appropriate dashboard based on user's PRIMARY access
            return $this->redirectToBestDashboard($user, $allowedTypes);
        }

        // Update last activity timestamp for authenticated users
        $user->updateLastActivity();

        return $next($request);
    }

    /**
     * Check if user is authorized based on legacy type OR roles.
     * 
     * @param User $user
     * @param array $allowedTypes
     * @return bool
     */
    protected function isUserAuthorized(User $user, array $allowedTypes): bool
    {
        // METHOD 1: Check legacy type first (for backward compatibility)
        $userType = $user->getRawOriginal('type');
        if (in_array($userType, $allowedTypes)) {
            return true;
        }
        
        // METHOD 2: Check roles (for multi-role users)
        // Map role slugs to type integers
        $roleTypeMap = [
            'super-admin' => User::TYPE_SUPER_ADMIN,
            'admin' => User::TYPE_ADMIN,
            'landlord' => User::TYPE_LANDLORD,
            'tenant' => User::TYPE_TENANT,
            'field-agent' => User::TYPE_FIELD_AGENT,
            'developer' => User::TYPE_DEVELOPER,
            'security-personnel' => User::TYPE_SECURITY_PERSONNEL,
            'contractor' => User::TYPE_CONTRACTOR,
            'sanitation-personnel' => User::TYPE_SANITATION_PERSONNEL, // ✅ ADDED
        ];
        
        foreach ($user->roles as $role) {
            if (isset($roleTypeMap[$role->slug])) {
                $roleType = $roleTypeMap[$role->slug];
                if (in_array($roleType, $allowedTypes)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get the best dashboard to redirect to based on user's available access.
     * 
     * @param User $user
     * @param array $attemptedTypes
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectToBestDashboard(User $user, array $attemptedTypes)
    {
        // First, try to redirect based on session role (if exists)
        $selectedRole = session('selected_role');
        if ($selectedRole) {
            $routeMap = [
                'super-admin' => 'super-admin.dashboard',
                'admin' => 'admin.dashboard',
                'developer' => 'developer.dashboard',
                'landlord' => 'landlord.dashboard',
                'field-agent' => 'field-agent.dashboard',
                'security-personnel' => 'security.dashboard',
                'tenant' => 'tenant.dashboard',
                'contractor' => 'contractor.dashboard',
                'sanitation-personnel' => 'sanitation.dashboard', // ✅ ADDED
            ];
            
            if (isset($routeMap[$selectedRole]) && \Route::has($routeMap[$selectedRole])) {
                return redirect()->route($routeMap[$selectedRole])
                    ->withErrors(['error' => 'Access denied. You don\'t have permission for that dashboard.']);
            }
        }
        
        // Second, try based on legacy type
        $userType = $user->getRawOriginal('type');
        $typeRouteMap = [
            User::TYPE_SUPER_ADMIN => 'super-admin.dashboard',
            User::TYPE_ADMIN => 'admin.dashboard',
            User::TYPE_LANDLORD => 'landlord.dashboard',
            User::TYPE_TENANT => 'tenant.dashboard',
            User::TYPE_FIELD_AGENT => 'field-agent.dashboard',
            User::TYPE_DEVELOPER => 'developer.dashboard',
            User::TYPE_SECURITY_PERSONNEL => 'security.dashboard',
            User::TYPE_CONTRACTOR => 'contractor.dashboard',
            User::TYPE_SANITATION_PERSONNEL => 'sanitation.dashboard', // ✅ ADDED
            User::TYPE_FORMER_LANDLORD => 'dashboard', // Former landlords go to main dashboard
        ];
        
        if (isset($typeRouteMap[$userType]) && \Route::has($typeRouteMap[$userType])) {
            return redirect()->route($typeRouteMap[$userType])
                ->withErrors(['error' => 'You are not authorized to access this page.']);
        }
        
        // Third, try based on first available role
        if ($user->roles->isNotEmpty()) {
            $firstRole = $user->roles->first();
            $roleRouteMap = [
                'super-admin' => 'super-admin.dashboard',
                'admin' => 'admin.dashboard',
                'developer' => 'developer.dashboard',
                'landlord' => 'landlord.dashboard',
                'field-agent' => 'field-agent.dashboard',
                'security-personnel' => 'security.dashboard',
                'tenant' => 'tenant.dashboard',
                'contractor' => 'contractor.dashboard',
                'sanitation-personnel' => 'sanitation.dashboard', // ✅ ADDED
            ];
            
            if (isset($roleRouteMap[$firstRole->slug]) && \Route::has($roleRouteMap[$firstRole->slug])) {
                return redirect()->route($roleRouteMap[$firstRole->slug])
                    ->withErrors(['error' => 'You are not authorized to access this page.']);
            }
        }
        
        // Final fallback - check if user has any accessible dashboard
        if ($this->hasAnyAccessibleDashboard($user)) {
            return redirect()->route('dashboard')
                ->withErrors(['error' => 'You are not authorized to access this page.']);
        }
        
        // Absolute fallback - logout and redirect to login
        auth()->logout();
        return redirect()->route('login')
            ->withErrors(['error' => 'Your account does not have access to any dashboard. Please contact support.']);
    }

    /**
     * Check if user has any accessible dashboard.
     * 
     * @param User $user
     * @return bool
     */
    protected function hasAnyAccessibleDashboard(User $user): bool
    {
        $allDashboards = [
            'super-admin.dashboard',
            'admin.dashboard',
            'developer.dashboard',
            'landlord.dashboard',
            'field-agent.dashboard',
            'security.dashboard',
            'tenant.dashboard',
            'contractor.dashboard',
            'sanitation.dashboard', // ✅ ADDED
        ];
        
        foreach ($allDashboards as $dashboard) {
            if (\Route::has($dashboard)) {
                return true;
            }
        }
        
        return \Route::has('dashboard');
    }

    /**
     * Get a list of all allowed user types for a specific route.
     * Useful for debugging and error messages.
     * 
     * @param array $allowedTypes
     * @return array
     */
    public function getAllowedTypeNames(array $allowedTypes): array
    {
        $typeNames = [
            User::TYPE_SUPER_ADMIN => 'Super Admin',
            User::TYPE_ADMIN => 'Admin',
            User::TYPE_LANDLORD => 'Landlord',
            User::TYPE_TENANT => 'Tenant',
            User::TYPE_FIELD_AGENT => 'Field Agent',
            User::TYPE_DEVELOPER => 'Developer',
            User::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
            User::TYPE_CONTRACTOR => 'Contractor',
            User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel', // ✅ ADDED
            User::TYPE_FORMER_LANDLORD => 'Former Landlord',
        ];
        
        $names = [];
        foreach ($allowedTypes as $type) {
            if (isset($typeNames[$type])) {
                $names[] = $typeNames[$type];
            }
        }
        
        return $names;
    }

    /**
     * Get the user type name for a specific type.
     * 
     * @param int $type
     * @return string
     */
    public function getUserTypeName(int $type): string
    {
        $typeNames = [
            User::TYPE_SUPER_ADMIN => 'Super Admin',
            User::TYPE_ADMIN => 'Admin',
            User::TYPE_LANDLORD => 'Landlord',
            User::TYPE_TENANT => 'Tenant',
            User::TYPE_FIELD_AGENT => 'Field Agent',
            User::TYPE_DEVELOPER => 'Developer',
            User::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
            User::TYPE_CONTRACTOR => 'Contractor',
            User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel', // ✅ ADDED
            User::TYPE_FORMER_LANDLORD => 'Former Landlord',
        ];
        
        return $typeNames[$type] ?? 'Unknown';
    }

    /**
     * Check if a user can access any of the allowed types (helper method).
     * 
     * @param User $user
     * @param array $allowedTypes
     * @return bool
     */
    public function userCanAccess(User $user, array $allowedTypes): bool
    {
        return $this->isUserAuthorized($user, $allowedTypes);
    }

    /**
     * Check if a user is a sanitation personnel (helper method).
     * 
     * @param User $user
     * @return bool
     */
    public function isSanitationPersonnel(User $user): bool
    {
        return $user->type === User::TYPE_SANITATION_PERSONNEL ||
               $user->hasRole('sanitation-personnel');
    }

    /**
     * Check if a user has any of the given roles.
     * 
     * @param User $user
     * @param array $roleSlugs
     * @return bool
     */
    public function userHasAnyRole(User $user, array $roleSlugs): bool
    {
        foreach ($roleSlugs as $slug) {
            if ($user->hasRole($slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all role slugs for a user.
     * 
     * @param User $user
     * @return array
     */
    public function getUserRoleSlugs(User $user): array
    {
        return $user->roles->pluck('slug')->toArray();
    }

    /**
     * Get the user's primary role slug.
     * 
     * @param User $user
     * @return string|null
     */
    public function getUserPrimaryRole(User $user): ?string
    {
        $primaryRole = $user->getPrimaryRoleAttribute();
        return $primaryRole ? $primaryRole->slug : null;
    }
}