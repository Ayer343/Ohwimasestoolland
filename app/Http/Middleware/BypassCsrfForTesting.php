<?php

namespace App\Http\Middleware;

use Closure;

class BypassCsrfForTesting
{
    public function handle($request, Closure $next)
    {
        // Allow test routes to bypass CSRF
        if ($request->is('test-*') || $request->is('debug-*') || $request->is('_test-*')) {
            return $next($request);
        }
        
        // For all other routes, let the normal CSRF middleware handle it
        return app()->make(\App\Http\Middleware\VerifyCsrfToken::class)->handle($request, $next);
    }
}