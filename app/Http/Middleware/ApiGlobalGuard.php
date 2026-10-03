<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiGlobalGuard
{
    /**
     * Public routes that don't require authentication
     */
    protected $publicRoutes = [
        'api/v1/health',
        'api/v1/auth/login',
        'api/v1/auth/2fa/verify',
        'api/v1/auth/2fa/resend',
        'api/v1/auth/forgot-password',
        'api/v1/auth/reset-password',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if this is an API request
        if ($request->is('api/*')) {
            
            // Skip authentication for public routes
            if ($this->isPublicRoute($request)) {
                return $next($request);
            }
            
            // Check authentication for protected routes
            if (!Auth::guard('sanctum')->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login again.',
                    'error_code' => 'UNAUTHENTICATED',
                    'requires_login' => true
                ], 401);
            }
            
            // Optional: Check if token is expired
            $user = Auth::guard('sanctum')->user();
            if ($user && $this->isTokenExpired($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token expired. Please login again.',
                    'error_code' => 'TOKEN_EXPIRED',
                    'requires_login' => true
                ], 401);
            }
        }

        return $next($request);
    }
    
    /**
     * Check if current route is public
     */
    protected function isPublicRoute($request)
    {
        $currentPath = $request->path();
        
        foreach ($this->publicRoutes as $publicRoute) {
            if ($request->is($publicRoute)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user's token is expired
     */
    protected function isTokenExpired($user)
    {
        // Get current access token
        $token = $user->currentAccessToken();
        
        if ($token && $token->expires_at) {
            return $token->expires_at->isPast();
        }
        
        return false;
    }
}