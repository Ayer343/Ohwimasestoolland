<?php

namespace App\Http\Middleware;

use Closure;

class CheckUserStatus
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        
        if ($user && $user->isArchived()) {
            auth()->logout();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been archived. Please contact support for assistance.'
                ], 403);
            }
            
            return redirect()->route('login')
                ->with('error', 'Your account has been archived. Please contact support.');
        }
        
        return $next($request);
    }
}