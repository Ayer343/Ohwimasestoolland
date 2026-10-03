<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsSecuritySupervisor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user || !$user->isSecuritySupervisor()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Supervisor access required.'
                ], 403);
            }
            
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have supervisor privileges.');
        }
        
        return $next($request);
    }
}