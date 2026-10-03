<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ApiLogger
{
    /**
     * Log API request and response
     */
    public static function log(Request $request, Response $response, float $responseTimeMs): void
    {
        // Only log API routes
        if (!$request->is('api/*')) {
            return;
        }
        
        // Skip logging for health checks and webhooks (optional)
        $skipPaths = ['health', 'webhook'];
        foreach ($skipPaths as $path) {
            if (str_contains($request->path(), $path)) {
                return;
            }
        }
        
        // Prepare log data
        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'response_time_ms' => round($responseTimeMs, 2),
            'status_code' => $response->getStatusCode(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ];
        
        // Add request body (excluding sensitive data)
        $requestBody = $request->except(['password', 'password_confirmation', 'token']);
        if (!empty($requestBody)) {
            $logData['request_body'] = $requestBody;
        }
        
        // Log to database (optional)
        if (config('logging.api.database_enabled', false)) {
            try {
                DB::table('api_logs')->insert([
                    'method' => $logData['method'],
                    'url' => $logData['url'],
                    'ip' => $logData['ip'],
                    'user_agent' => $logData['user_agent'],
                    'response_time_ms' => $logData['response_time_ms'],
                    'status_code' => $logData['status_code'],
                    'user_id' => $logData['user_id'],
                    'request_body' => json_encode($logData['request_body'] ?? []),
                    'created_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Fallback to file logging if database fails
                Log::channel('api')->error('Failed to log API request to database', [
                    'error' => $e->getMessage(),
                    'log_data' => $logData
                ]);
            }
        }
        
        // Log to file (always enabled)
        $logChannel = $response->getStatusCode() >= 400 ? 'error' : 'api';
        
        Log::channel($logChannel)->info('API Request', $logData);
    }
    
    /**
     * Log API error
     */
    public static function error(Request $request, \Exception $e, float $responseTimeMs): void
    {
        Log::channel('error')->error('API Error', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'response_time_ms' => round($responseTimeMs, 2),
            'user_id' => auth()->id(),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    /**
     * Get API statistics (for dashboard)
     */
    public static function getStats($days = 7): array
    {
        try {
            $startDate = now()->subDays($days);
            
            $stats = DB::table('api_logs')
                ->where('created_at', '>=', $startDate)
                ->select(
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('SUM(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 ELSE 0 END) as successful_requests'),
                    DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as failed_requests'),
                    DB::raw('AVG(response_time_ms) as avg_response_time'),
                    DB::raw('MAX(response_time_ms) as max_response_time'),
                    DB::raw('MIN(response_time_ms) as min_response_time')
                )
                ->first();
            
            $endpoints = DB::table('api_logs')
                ->where('created_at', '>=', $startDate)
                ->select('method', 'url', DB::raw('COUNT(*) as count'))
                ->groupBy('method', 'url')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();
            
            return [
                'total_requests' => $stats->total_requests ?? 0,
                'successful_requests' => $stats->successful_requests ?? 0,
                'failed_requests' => $stats->failed_requests ?? 0,
                'avg_response_time_ms' => round($stats->avg_response_time ?? 0, 2),
                'max_response_time_ms' => round($stats->max_response_time ?? 0, 2),
                'min_response_time_ms' => round($stats->min_response_time ?? 0, 2),
                'success_rate' => $stats->total_requests > 0 
                    ? round(($stats->successful_requests / $stats->total_requests) * 100, 2)
                    : 0,
                'top_endpoints' => $endpoints,
            ];
        } catch (\Exception $e) {
            return [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'avg_response_time_ms' => 0,
                'max_response_time_ms' => 0,
                'min_response_time_ms' => 0,
                'success_rate' => 0,
                'top_endpoints' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}