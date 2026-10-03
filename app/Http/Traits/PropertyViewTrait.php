<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Auth;

trait PropertyViewTrait
{
    /**
     * Get the appropriate view path based on user's PRIMARY role
     * Priority: Super Admin > Admin > Developer > Field Agent > Landlord > Tenant
     */
    protected function getView($viewName)
    {
        $user = auth()->user();
        
        if (!$user) {
            return "properties.{$viewName}";
        }
        
        // CRITICAL FIX: Check session selected role first (dashboard context)
        $selectedRole = session('selected_role');
        
        if ($selectedRole) {
            switch ($selectedRole) {
                case 'super-admin':
                    return "super-admin.{$viewName}";
                case 'admin':
                    return "admin.{$viewName}";
                case 'developer':
                    return "developer.{$viewName}";
                case 'field-agent':
                    return "field-agent.{$viewName}";
                case 'landlord':
                    return "landlord.{$viewName}";
                case 'tenant':
                    return "tenant.{$viewName}";
                case 'security-personnel':
                    return "security.{$viewName}";
                default:
                    // Fall through to role-based detection
                    break;
            }
        }
        
        // PRIORITY ORDER: Most privileged to least privileged
        // This prevents admins/super admins with landlord role from getting landlord view
        
        // 1. Super Admin (highest priority)
        if ($user->isSuperAdmin()) {
            return "super-admin.{$viewName}";
        }
        
        // 2. Admin
        if ($user->isAdmin()) {
            return "admin.{$viewName}";
        }
        
        // 3. Developer
        if ($user->isDeveloper()) {
            return "developer.{$viewName}";
        }
        
        // 4. Field Agent
        if ($user->isFieldAgent()) {
            return "field-agent.{$viewName}";
        }
        
        // 5. Landlord (check after admin/super-admin to prevent conflicts)
        if ($user->isLandlord()) {
            return "landlord.{$viewName}";
        }
        
        // 6. Tenant
        if ($user->isTenant()) {
            return "tenant.{$viewName}";
        }
        
        // 7. Security Personnel
        if ($user->isSecurityPersonnel()) {
            return "security.{$viewName}";
        }
        
        // Default fallback
        return "properties.{$viewName}";
    }

    /**
     * Get the appropriate route name based on user's PRIMARY role
     */
    protected function getRoute($routeName, $params = null)
    {
        $user = auth()->user();
        
        if (!$user) {
            return "properties.{$routeName}";
        }
        
        // Check session selected role first
        $selectedRole = session('selected_role');
        
        if ($selectedRole) {
            switch ($selectedRole) {
                case 'super-admin':
                    $route = "super-admin.{$routeName}";
                    break;
                case 'admin':
                    $route = "admin.{$routeName}";
                    break;
                case 'developer':
                    $route = "developer.{$routeName}";
                    break;
                case 'field-agent':
                    $route = "field-agent.{$routeName}";
                    break;
                case 'landlord':
                    $route = "landlord.{$routeName}";
                    break;
                case 'tenant':
                    $route = "tenant.{$routeName}";
                    break;
                case 'security-personnel':
                    $route = "security.{$routeName}";
                    break;
                default:
                    $route = "properties.{$routeName}";
                    break;
            }
            
            return $params ? route($route, $params) : $route;
        }
        
        // PRIORITY ORDER for routes (most privileged to least)
        if ($user->isSuperAdmin()) {
            $route = "super-admin.{$routeName}";
        } elseif ($user->isAdmin()) {
            $route = "admin.{$routeName}";
        } elseif ($user->isDeveloper()) {
            $route = "developer.{$routeName}";
        } elseif ($user->isFieldAgent()) {
            $route = "field-agent.{$routeName}";
        } elseif ($user->isLandlord()) {
            $route = "landlord.{$routeName}";
        } elseif ($user->isTenant()) {
            $route = "tenant.{$routeName}";
        } elseif ($user->isSecurityPersonnel()) {
            $route = "security.{$routeName}";
        } else {
            $route = "properties.{$routeName}";
        }

        return $params ? route($route, $params) : $route;
    }
    
    /**
     * Check if user has higher priority role than landlord
     * Useful for conditional logic in views
     */
    protected function hasHigherPriorityThanLandlord()
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isDeveloper();
    }
    
    /**
     * Get the appropriate view directory based on user role
     * Helper method for more complex view resolution
     */
    protected function getViewDirectory()
    {
        $user = auth()->user();
        
        if (!$user) {
            return 'properties';
        }
        
        $selectedRole = session('selected_role');
        
        if ($selectedRole) {
            return $selectedRole;
        }
        
        // Priority order
        if ($user->isSuperAdmin()) {
            return 'super-admin';
        }
        
        if ($user->isAdmin()) {
            return 'admin';
        }
        
        if ($user->isDeveloper()) {
            return 'developer';
        }
        
        if ($user->isFieldAgent()) {
            return 'field-agent';
        }
        
        if ($user->isLandlord()) {
            return 'landlord';
        }
        
        if ($user->isTenant()) {
            return 'tenant';
        }
        
        if ($user->isSecurityPersonnel()) {
            return 'security';
        }
        
        return 'properties';
    }
}