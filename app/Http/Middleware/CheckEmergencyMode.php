<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\EmergencyMode;

class CheckEmergencyMode
{
    public function handle(Request $request, Closure $next, $module = null)
    {
        // Check if any emergency mode is active
        $activeEmergency = EmergencyMode::active()->first();
        
        if ($activeEmergency) {
            // Check if specific module is affected
            if ($module && $activeEmergency->isModuleAffected($module)) {
                // Check if the requested operation is allowed
                $operation = $request->route()->getName() ?? $request->route()->getActionName();
                
                if (!$activeEmergency->isOperationAllowed($operation)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This operation is restricted during emergency mode.',
                        'emergency_mode' => [
                            'name' => $activeEmergency->name,
                            'reason' => $activeEmergency->reason,
                            'allowed_operations' => $activeEmergency->allowed_operations,
                        ]
                    ], 423); // 423 Locked status code
                }
            }
            
            // Add emergency mode info to request
            $request->attributes->add([
                'emergency_mode' => $activeEmergency,
            ]);
        }
        
        return $next($request);
    }
}