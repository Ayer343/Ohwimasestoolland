<?php
// app/Http/Middleware/SetNotificationRoleContext.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetNotificationRoleContext
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $selectedRole = session('selected_role');
            
            // Share role context with all views
            view()->share('currentNotificationRole', $selectedRole);
            
            // Add role to request for automatic inclusion in API calls
            if ($selectedRole && $request->expectsJson()) {
                $request->merge(['role' => $selectedRole]);
            }
        }
        
        return $next($request);
    }
}