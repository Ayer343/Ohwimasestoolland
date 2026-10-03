<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RailwaySessionFix
{
    public function handle(Request $request, Closure $next)
    {
        // Fix for Railway's proxy setup
        if ($request->server->has('HTTP_X_FORWARDED_PROTO')) {
            $request->server->set('HTTPS', 'on');
            $request->setTrustedProxies([$request->getClientIp()], 
                Request::HEADER_X_FORWARDED_ALL
            );
        }

        return $next($request);
    }
}