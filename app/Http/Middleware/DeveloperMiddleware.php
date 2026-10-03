<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeveloperMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }
        
        // Check if user has developer role
        $user = Auth::user();
        if (!$user->hasRole('developer') && !$user->is_developer) {
            abort(403, 'Developer access required');
        }
        
        // Check if developer access is enabled
        if (!config('app.developer_access_enabled', false)) {
            abort(503, 'Developer access is currently disabled');
        }
        
        // Log developer access
        \Log::channel('developer')->info('Developer access', [
            'developer' => $user->email,
            'ip' => $request->ip(),
            'endpoint' => $request->path(),
            'timestamp' => now()
        ]);
        
        return $next($request);
    }
}