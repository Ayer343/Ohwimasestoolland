<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDeviceIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // Skip for certain routes
        $skipRoutes = ['api/v1/devices/register', 'api/v1/auth/2fa/*'];
        foreach ($skipRoutes as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }
        
        // Check if device is verified
        $deviceId = $request->header('X-Device-ID');
        if ($deviceId && $user) {
            $isVerified = $user->devices()
                ->where('device_id', $deviceId)
                ->where('is_verified', true)
                ->exists();
                
            if (!$isVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not verified. Please verify your device first.',
                    'error_code' => 'DEVICE_NOT_VERIFIED',
                    'requires_verification' => true
                ], 403);
            }
        }
        
        return $next($request);
    }
}