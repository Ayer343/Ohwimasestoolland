<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Maintenance;

class GlobalMaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        // Check if any maintenance is active
        $activeMaintenance = Maintenance::active()->first();
        
        if ($activeMaintenance) {
            // Add maintenance info to all requests
            $request->attributes->add([
                'maintenance_mode' => $activeMaintenance,
            ]);
            
            // Add maintenance header for API responses
            if ($request->is('api/*') || $request->wantsJson()) {
                $response = $next($request);
                $response->header('X-Maintenance-Mode', 'active');
                $response->header('X-Maintenance-Title', $activeMaintenance->title);
                $response->header('X-Maintenance-Impact', $activeMaintenance->impact_level);
                return $response;
            }
        }
        
        return $next($request);
    }
}