<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebugDatabaseQueries
{
    public function handle(Request $request, Closure $next)
    {
        if (!app()->environment('local')) {
            return $next($request);
        }
        
        // Enable query log
        DB::enableQueryLog();
        
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = (microtime(true) - $startTime) * 1000;
        $queries = DB::getQueryLog();
        
        $slowQueries = [];
        foreach ($queries as $query) {
            if ($query['time'] > 100) { // Queries taking > 100ms
                $slowQueries[] = [
                    'sql' => $query['query'],
                    'bindings' => $query['bindings'],
                    'time' => $query['time'] . 'ms'
                ];
            }
        }
        
        if (!empty($slowQueries)) {
            Log::channel('api')->warning('Slow API request detected', [
                'url' => $request->fullUrl(),
                'duration_ms' => round($duration, 2),
                'query_count' => count($queries),
                'slow_queries' => $slowQueries,
            ]);
        }
        
        return $response;
    }
}