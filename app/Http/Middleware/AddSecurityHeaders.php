<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Security headers for API
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Only add CSP for non-API routes
        if (!$request->is('api/*')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'");
        }
        
        return $response;
    }
}