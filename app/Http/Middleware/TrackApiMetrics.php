<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackApiMetrics
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $start;
        
        // Track metrics (for monitoring)
        $endpoint = $request->method() . ' ' . $request->path();
        $statusCode = $response->getStatusCode();
        
        // Store in cache for dashboard display
        $metrics = Cache::get('api_metrics', []);
        $metrics[] = [
            'endpoint' => $endpoint,
            'duration' => round($duration * 1000, 2), // ms
            'status' => $statusCode,
            'timestamp' => now(),
        ];
        
        // Keep only last 1000 metrics
        $metrics = array_slice($metrics, -1000);
        Cache::put('api_metrics', $metrics, 3600);
        
        // Add response headers for performance monitoring
        if (!$response->headers->has('X-Response-Time')) {
            $response->headers->set('X-Response-Time', round($duration * 1000, 2) . 'ms');
        }
        
        return $response;
    }
}