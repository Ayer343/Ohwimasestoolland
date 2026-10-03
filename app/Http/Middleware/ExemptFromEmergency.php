<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExemptFromMaintenance
{
    public function handle(Request $request, Closure $next)
    {
        // Mark request as exempt from maintenance mode checks
        $request->attributes->add(['exempt_from_maintenance' => true]);
        return $next($request);
    }
}