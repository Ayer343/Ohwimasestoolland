<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceSessionStart
{
    public function handle(Request $request, Closure $next)
    {
        if (!session()->isStarted()) {
            session()->start();
        }
        
        if (!session()->has('_token')) {
            session()->put('_token', bin2hex(random_bytes(40)));
        }
        
        return $next($request);
    }
}