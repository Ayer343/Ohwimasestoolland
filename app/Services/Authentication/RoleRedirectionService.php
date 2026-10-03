<?php

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class RoleRedirectionService
{
    /**
     * Role-based redirection mapping
     * ✅ COMPLETE: Added all user types including sanitation personnel
     */
    protected array $redirectMap = [
        User::TYPE_SUPER_ADMIN => 'super-admin.dashboard',
        User::TYPE_ADMIN => 'admin.dashboard',
        User::TYPE_LANDLORD => 'landlord.dashboard',
        User::TYPE_TENANT => 'tenant.dashboard',
        User::TYPE_FIELD_AGENT => 'field-agent.dashboard',
        User::TYPE_DEVELOPER => 'developer.dashboard',
        User::TYPE_SECURITY_PERSONNEL => 'security.dashboard',
        User::TYPE_SANITATION_PERSONNEL => 'sanitation.dashboard',  // ✅ ADDED
        User::TYPE_CONTRACTOR => 'contractor.dashboard',
        User::TYPE_FORMER_LANDLORD => 'dashboard',
    ];

    /**
     * Role slug to route name mapping (for multi-role users)
     */
    protected array $roleSlugMap = [
        'super-admin' => 'super-admin.dashboard',
        'admin' => 'admin.dashboard',
        'landlord' => 'landlord.dashboard',
        'tenant' => 'tenant.dashboard',
        'field-agent' => 'field-agent.dashboard',
        'developer' => 'developer.dashboard',
        'security-personnel' => 'security.dashboard',
        'sanitation-personnel' => 'sanitation.dashboard',  // ✅ ADDED
        'contractor' => 'contractor.dashboard',
    ];

    /**
     * User type names for display and logging
     */
    protected array $typeNames = [
        User::TYPE_SUPER_ADMIN => 'Super Admin',
        User::TYPE_ADMIN => 'Admin',
        User::TYPE_LANDLORD => 'Landlord',
        User::TYPE_TENANT => 'Tenant',
        User::TYPE_FIELD_AGENT => 'Field Agent',
        User::TYPE_DEVELOPER => 'Developer',
        User::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
        User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',  // ✅ ADDED
        User::TYPE_CONTRACTOR => 'Contractor',
        User::TYPE_FORMER_LANDLORD => 'Former Landlord',
    ];

    /**
     * Role priority for multi-role users (higher = more important)
     */
    protected array $rolePriority = [
        'super-admin' => 100,
        'developer' => 95,
        'admin' => 90,
        'landlord' => 80,
        'contractor' => 75,
        'security-personnel' => 70,
        'sanitation-personnel' => 65,  // ✅ ADDED
        'field-agent' => 60,
        'tenant' => 50,
    ];

    /**
     * Default fallback route
     */
    protected string $defaultRoute = 'dashboard';

    /**
     * Redirect user based on their type after successful login
     * ✅ COMPLETE: Supports all user types with proper error handling
     */
    public function redirect(User $user): RedirectResponse
    {
        // ✅ CRITICAL: Save session before any redirect
        session()->save();
        
        $userType = $user->getRawOriginal('type');
        $userTypeName = $this->typeNames[$userType] ?? 'Unknown';
        
        // ✅ Check by role first (for multi-role users)
        $routeName = $this->getRouteFromRoles($user);
        
        // If no role-based route found, use legacy type
        if (!$routeName) {
            $routeName = $this->getRouteFromType($userType);
        }
        
        // Log the redirect for debugging
        Log::info('RoleRedirectionService: Redirecting user', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_type' => $userType,
            'user_type_name' => $userTypeName,
            'user_roles' => $user->roles->pluck('slug')->toArray(),
            'route_name' => $routeName,
            'session_id' => session()->getId(),
        ]);
        
        // If no route found, handle appropriately
        if (!$routeName) {
            Log::warning('RoleRedirectionService: No route found for user', [
                'user_id' => $user->id,
                'user_type' => $userType,
                'user_roles' => $user->roles->pluck('slug')->toArray(),
            ]);
            
            // Try to redirect based on first available role
            if ($user->roles->isNotEmpty()) {
                $firstRole = $user->roles->first();
                $routeName = $this->roleSlugMap[$firstRole->slug] ?? null;
                
                if ($routeName && Route::has($routeName)) {
                    Log::info('RoleRedirectionService: Using first available role', [
                        'user_id' => $user->id,
                        'role' => $firstRole->slug,
                        'route' => $routeName,
                    ]);
                } else {
                    $routeName = null;
                }
            }
            
            // If still no route, use default
            if (!$routeName || !Route::has($routeName)) {
                $routeName = $this->defaultRoute;
                
                Log::warning('RoleRedirectionService: Using default dashboard', [
                    'user_id' => $user->id,
                    'default_route' => $routeName,
                ]);
            }
        }
        
        // ✅ Check if route exists
        if (!Route::has($routeName)) {
            Log::error('RoleRedirectionService: Dashboard route not found', [
                'route' => $routeName,
                'user_id' => $user->id,
                'user_type' => $userType,
                'user_roles' => $user->roles->pluck('slug')->toArray(),
            ]);
            
            // Log out user with invalid route
            auth()->logout();
            session()->invalidate();
            
            return redirect()->route('login')
                ->withErrors(['login' => 'Invalid user role configuration. Please contact administrator.']);
        }

        // ✅ Save session before generating redirect
        session()->save();
        
        Log::info('RoleRedirectionService: Redirecting to route', [
            'user_id' => $user->id,
            'route' => $routeName,
            'url' => route($routeName),
            'session_id' => session()->getId(),
        ]);

        // ✅ Use route() directly instead of intended()
        $response = redirect()->route($routeName);
        
        // ✅ Save session after generating response
        session()->save();

        return $response;
    }

    /**
     * Get route from user's roles (for multi-role users)
     * Returns the highest priority role's dashboard
     */
    protected function getRouteFromRoles(User $user): ?string
    {
        $roles = $user->roles;
        
        if ($roles->isEmpty()) {
            return null;
        }
        
        // Sort roles by priority
        $sortedRoles = $roles->sortByDesc(function($role) {
            return $this->rolePriority[$role->slug] ?? 0;
        });
        
        // Get the highest priority role
        $primaryRole = $sortedRoles->first();
        
        if ($primaryRole && isset($this->roleSlugMap[$primaryRole->slug])) {
            $routeName = $this->roleSlugMap[$primaryRole->slug];
            
            if (Route::has($routeName)) {
                Log::info('RoleRedirectionService: Found route from primary role', [
                    'user_id' => $user->id,
                    'role' => $primaryRole->slug,
                    'route' => $routeName,
                    'priority' => $this->rolePriority[$primaryRole->slug] ?? 0,
                ]);
                return $routeName;
            }
        }
        
        // Try to find any role with a valid route
        foreach ($sortedRoles as $role) {
            if (isset($this->roleSlugMap[$role->slug])) {
                $routeName = $this->roleSlugMap[$role->slug];
                if (Route::has($routeName)) {
                    Log::info('RoleRedirectionService: Found route from role', [
                        'user_id' => $user->id,
                        'role' => $role->slug,
                        'route' => $routeName,
                    ]);
                    return $routeName;
                }
            }
        }
        
        return null;
    }

    /**
     * Get route from legacy user type
     */
    protected function getRouteFromType(int $userType): ?string
    {
        if (isset($this->redirectMap[$userType])) {
            $routeName = $this->redirectMap[$userType];
            
            if (Route::has($routeName)) {
                return $routeName;
            }
            
            Log::warning('RoleRedirectionService: Route exists in mapping but not defined', [
                'user_type' => $userType,
                'route' => $routeName,
            ]);
        }
        
        return null;
    }

    /**
     * Get dashboard route name for user type
     */
    public function getDashboardRoute(User $user): ?string
    {
        $userType = $user->getRawOriginal('type');
        
        // Check roles first
        $routeName = $this->getRouteFromRoles($user);
        
        if ($routeName) {
            return $routeName;
        }
        
        // Fallback to legacy type
        return $this->redirectMap[$userType] ?? null;
    }

    /**
     * Get dashboard URL for user
     */
    public function getDashboardUrl(User $user): ?string
    {
        $routeName = $this->getDashboardRoute($user);
        
        if ($routeName && Route::has($routeName)) {
            return route($routeName);
        }
        
        return null;
    }

    /**
     * Check if user can access a specific dashboard
     */
    public function canAccessDashboard(User $user, string $dashboardType): bool
    {
        $userDashboard = $this->getDashboardRoute($user);
        
        if (!$userDashboard) {
            return false;
        }
        
        // Extract the dashboard type from route name
        $userDashboardType = explode('.', $userDashboard)[0] ?? '';
        
        return $userDashboardType === $dashboardType;
    }

    /**
     * Check if user has any valid dashboard
     */
    public function hasValidDashboard(User $user): bool
    {
        $routeName = $this->getDashboardRoute($user);
        
        return $routeName && Route::has($routeName);
    }

    /**
     * Get all available dashboard routes
     */
    public function getAllDashboardRoutes(): array
    {
        return $this->redirectMap;
    }

    /**
     * Get available dashboards for a specific user
     */
    public function getAvailableDashboards(User $user): array
    {
        $available = [];
        $seenRoutes = [];
        
        // Check by roles
        foreach ($user->roles as $role) {
            if (isset($this->roleSlugMap[$role->slug])) {
                $routeName = $this->roleSlugMap[$role->slug];
                if (Route::has($routeName) && !in_array($routeName, $seenRoutes)) {
                    $available[] = [
                        'role' => $role->slug,
                        'role_name' => $role->display_name ?? $role->name,
                        'route' => $routeName,
                        'url' => route($routeName),
                        'priority' => $this->rolePriority[$role->slug] ?? 0,
                        'is_primary' => $role->id === $user->getPrimaryRoleAttribute()?->id,
                    ];
                    $seenRoutes[] = $routeName;
                }
            }
        }
        
        // Check by legacy type
        $userType = $user->getRawOriginal('type');
        if (isset($this->redirectMap[$userType])) {
            $routeName = $this->redirectMap[$userType];
            if (Route::has($routeName) && !in_array($routeName, $seenRoutes)) {
                $available[] = [
                    'role' => 'legacy',
                    'role_name' => $this->typeNames[$userType] ?? 'Legacy User',
                    'route' => $routeName,
                    'url' => route($routeName),
                    'priority' => 0,
                    'is_primary' => empty($available),
                ];
                $seenRoutes[] = $routeName;
            }
        }
        
        // Sort by priority (highest first)
        usort($available, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        return $available;
    }

    /**
     * Get user type name for display
     */
    public function getUserTypeName(User $user): string
    {
        $userType = $user->getRawOriginal('type');
        return $this->typeNames[$userType] ?? 'Unknown';
    }

    /**
     * Get role priority for a specific role slug
     */
    public function getRolePriority(string $roleSlug): int
    {
        return $this->rolePriority[$roleSlug] ?? 0;
    }

    /**
     * Check if a role slug has a valid dashboard route
     */
    public function hasDashboardRoute(string $roleSlug): bool
    {
        if (!isset($this->roleSlugMap[$roleSlug])) {
            return false;
        }
        
        return Route::has($this->roleSlugMap[$roleSlug]);
    }

    /**
     * Get all role slugs that have dashboards
     */
    public function getRolesWithDashboards(): array
    {
        $roles = [];
        
        foreach ($this->roleSlugMap as $slug => $route) {
            if (Route::has($route)) {
                $roles[] = $slug;
            }
        }
        
        return $roles;
    }

    /**
     * Redirect to login with error (helper method)
     */
    protected function redirectToLoginWithError(string $error): RedirectResponse
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        
        return redirect()->route('login')
            ->withErrors(['login' => $error]);
    }

    /**
     * Get the primary dashboard for a user based on highest priority role
     */
    public function getPrimaryDashboard(User $user): ?array
    {
        $available = $this->getAvailableDashboards($user);
        
        if (empty($available)) {
            return null;
        }
        
        // Return the first one (already sorted by priority)
        return $available[0];
    }

    /**
     * Get dashboard switching options for multi-role users
     */
    public function getDashboardSwitchingOptions(User $user): array
    {
        $available = $this->getAvailableDashboards($user);
        $primary = $this->getPrimaryDashboard($user);
        
        return [
            'primary' => $primary,
            'available' => $available,
            'can_switch' => count($available) > 1,
        ];
    }

    /**
     * Redirect to a specific dashboard by role slug
     */
    public function redirectToRoleDashboard(User $user, string $roleSlug): ?RedirectResponse
    {
        if (!isset($this->roleSlugMap[$roleSlug])) {
            Log::warning('RoleRedirectionService: Unknown role slug', [
                'role_slug' => $roleSlug,
                'user_id' => $user->id,
            ]);
            return null;
        }
        
        $routeName = $this->roleSlugMap[$roleSlug];
        
        if (!Route::has($routeName)) {
            Log::error('RoleRedirectionService: Route not found for role slug', [
                'role_slug' => $roleSlug,
                'route' => $routeName,
                'user_id' => $user->id,
            ]);
            return null;
        }
        
        // Check if user has this role
        if (!$user->hasRole($roleSlug)) {
            Log::warning('RoleRedirectionService: User does not have role', [
                'role_slug' => $roleSlug,
                'user_id' => $user->id,
                'user_roles' => $user->roles->pluck('slug')->toArray(),
            ]);
            return null;
        }
        
        session()->save();
        
        Log::info('RoleRedirectionService: Redirecting to specific role dashboard', [
            'user_id' => $user->id,
            'role_slug' => $roleSlug,
            'route' => $routeName,
        ]);
        
        return redirect()->route($routeName);
    }
}