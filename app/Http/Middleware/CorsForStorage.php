<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adds CORS headers to /storage/* responses so Flutter web can load images
 * from a different origin (e.g. localhost:51752 → 127.0.0.1:8000).
 *
 * Only applied in local development. In production, Apache/Nginx serves
 * these files directly and .htaccess handles the headers.
 */
class CorsForStorage
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only touch storage requests
        if ($request->is('storage/*')) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Range');
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, Content-Range, Accept-Ranges');
            $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        }

        return $response;
    }
}