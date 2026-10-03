<?php
// app/Http/Middleware/CheckRegistrationEnabled.php

namespace App\Http\Middleware;

use Closure;
use App\Models\SystemSetting;

class CheckRegistrationEnabled
{
    public function handle($request, Closure $next)
    {
        $settings = SystemSetting::getSettings();
        
        if (!$settings->allow_registration) {
            $message = $settings->registration_disabled_message ?? 'New registrations are currently disabled. Please contact the administrator.';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 403);
            }
            
            return redirect()->route('home')
                ->with('error', $message);
        }
        
        return $next($request);
    }
}