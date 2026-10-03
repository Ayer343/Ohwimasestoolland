<?php

namespace App\Http\Middleware;

use App\Services\ApiLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip logging for specific paths (optional)
        $skipPaths = [
            'api/v1/health',
            'api/v1/payments/webhook',
        ];
        
        $shouldSkip = false;
        foreach ($skipPaths as $path) {
            if ($request->is($path)) {
                $shouldSkip = true;
                break;
            }
        }
        
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $responseTime = microtime(true) - $startTime;
        
        // Log the request (you can add conditions here)
        if (!$shouldSkip && config('logging.api.enabled', true)) {
            // Check if ApiLogger exists, otherwise use Log facade
            if (class_exists(ApiLogger::class)) {
                ApiLogger::log($request, $response, $responseTime * 1000); // Convert to milliseconds
            } else {
                // Fallback logging
                Log::channel('api')->info('API Request', [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'response_time_ms' => round($responseTime * 1000, 2),
                    'status_code' => $response->getStatusCode(),
                ]);
            }
        }
        
        // Add response time header (useful for debugging)
        $response->headers->set('X-Response-Time-MS', round($responseTime * 1000, 2));
        
        return $response;
    }
}