<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RefreshCsrfToken
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Add CSRF token to response headers for AJAX
        if ($request->ajax() || $request->wantsJson()) {
            $response->headers->set('X-CSRF-TOKEN', $request->session()->token());
        }
        
        return $response;
    }
}