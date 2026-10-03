<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireTwoFactorAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // Check if user has 2FA enabled but not verified in this session
        if ($user && $user->two_factor_enabled && !$request->session()->get('2fa_verified')) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication required.',
                'error_code' => '2FA_REQUIRED',
                'requires_2fa' => true
            ], 403);
        }
        
        return $next($request);
    }
}