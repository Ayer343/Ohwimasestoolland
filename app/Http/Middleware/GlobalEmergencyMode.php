<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\EmergencyMode;

class GlobalEmergencyMode
{
    public function handle(Request $request, Closure $next)
    {
        // Check if any emergency mode is active
        $activeEmergency = EmergencyMode::active()->first();
        
        if ($activeEmergency) {
            // Add emergency mode info to all requests
            $request->attributes->add([
                'emergency_mode' => $activeEmergency,
            ]);
            
            // Add emergency mode header for API responses
            if ($request->is('api/*') || $request->wantsJson()) {
                $response = $next($request);
                $response->header('X-Emergency-Mode', 'active');
                $response->header('X-Emergency-Name', $activeEmergency->name);
                $response->header('X-Emergency-Severity', $activeEmergency->severity_level);
                return $response;
            }
        }
        
        return $next($request);
    }
}