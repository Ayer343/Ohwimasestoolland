<?php

namespace App\Helpers;

use App\Models\ApiLog;
use Illuminate\Support\Facades\DB;

class ApiStatsHelper
{
    /**
     * Get API usage statistics
     */
    public static function getApiUsageStats($developerSettingId = null, $period = 'today')
    {
        $query = ApiLog::query();
        
        // Filter by developer setting if provided
        if ($developerSettingId) {
            // Assuming user has developer_setting_id
            $userIds = \App\Models\User::where('developer_setting_id', $developerSettingId)
                ->pluck('id');
            $query->whereIn('user_id', $userIds);
        }
        
        // Apply time period filter
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
            case 'year':
                $query->where('created_at', '>=', now()->subYear());
                break;
        }
        
        $totalRequests = $query->count();
        $successfulRequests = (clone $query)->whereBetween('status_code', [200, 299])->count();
        $errorRequests = (clone $query)->where('status_code', '>=', 400)->count();
        $avgResponseTime = $query->avg('response_time');
        
        // Get requests by endpoint
        $endpointStats = (clone $query)
            ->select('endpoint', DB::raw('COUNT(*) as request_count'))
            ->groupBy('endpoint')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get();
        
        // Get requests by method
        $methodStats = (clone $query)
            ->select('method', DB::raw('COUNT(*) as request_count'))
            ->groupBy('method')
            ->orderByDesc('request_count')
            ->get();
        
        // Get error rate
        $errorRate = $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0;
        
        // Get success rate
        $successRate = $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 0;
        
        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'error_requests' => $errorRequests,
            'avg_response_time' => $avgResponseTime ? round($avgResponseTime, 2) : 0,
            'error_rate' => round($errorRate, 2),
            'success_rate' => round($successRate, 2),
            'top_endpoints' => $endpointStats,
            'method_distribution' => $methodStats,
            'period' => $period,
        ];
    }
    
    /**
     * Get API usage over time (for charts)
     */
    public static function getApiUsageOverTime($developerSettingId = null, $days = 30)
    {
        $query = ApiLog::query();
        
        if ($developerSettingId) {
            $userIds = \App\Models\User::where('developer_setting_id', $developerSettingId)
                ->pluck('id');
            $query->whereIn('user_id', $userIds);
        }
        
        $data = $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as successful'),
                DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors'),
                DB::raw('AVG(response_time) as avg_response_time')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return $data;
    }
    
    /**
     * Get user-specific API stats
     */
    public static function getUserApiStats($userId, $period = 'today')
    {
        $query = ApiLog::where('user_id', $userId);
        
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
        }
        
        return [
            'total' => $query->count(),
            'successful' => (clone $query)->whereBetween('status_code', [200, 299])->count(),
            'errors' => (clone $query)->where('status_code', '>=', 400)->count(),
            'avg_response_time' => $query->avg('response_time'),
        ];
    }
}