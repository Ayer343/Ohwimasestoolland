<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Maintenance;
use App\Exceptions\MaintenanceModeException;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next, $module = null)
    {
        // Check active maintenance affecting the module
        $activeMaintenance = Maintenance::active()
            ->whereJsonContains('affected_modules', $module)
            ->first();
        
        if ($activeMaintenance) {
            // Check if the requested operation is allowed
            $operation = $request->route()->getName() ?? $request->route()->getActionName();
            $allowedOperations = $activeMaintenance->allowed_operations ?? [];
            
            if (!in_array($operation, $allowedOperations) && !in_array('*', $allowedOperations)) {
                // Check if user is exempt (super admin or developer)
                $user = $request->user();
                if ($user && in_array($user->type, [0, 5])) {
                    return $next($request);
                }
                
                throw new MaintenanceModeException(
                    'This service is currently under maintenance. Please try again later.',
                    [
                        'title' => $activeMaintenance->title,
                        'description' => $activeMaintenance->user_impact_description,
                        'estimated_completion' => $activeMaintenance->scheduled_end?->toISOString(),
                        'allowed_operations' => $allowedOperations,
                        'reference_id' => $activeMaintenance->reference_id,
                    ]
                );
            }
        }
        
        return $next($request);
    }
}