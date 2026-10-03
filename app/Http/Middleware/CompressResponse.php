<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompressResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Only compress JSON responses
        if ($response->headers->get('Content-Type') === 'application/json') {
            $content = $response->getContent();
            
            // Remove whitespace for smaller payload
            $compressed = json_encode(json_decode($content));
            $response->setContent($compressed);
            
            // Add compression headers
            $response->headers->set('Content-Length', strlen($compressed));
        }
        
        return $response;
    }
}