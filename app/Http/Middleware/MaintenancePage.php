<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Maintenance;

class MaintenancePage
{
    public function handle(Request $request, Closure $next)
    {
        // Check for active critical maintenance
        $criticalMaintenance = Maintenance::active()
            ->where('impact_level', 'critical')
            ->first();
        
        if ($criticalMaintenance && !$request->is('developer/*') && !$request->is('login') && !$request->is('maintenance-info')) {
            // Show maintenance page for critical maintenance
            // Exclude developer routes, login, and maintenance info page
            return response()->view('errors.maintenance', [
                'maintenance' => $criticalMaintenance,
            ], 503);
        }
        
        return $next($request);
    }
}