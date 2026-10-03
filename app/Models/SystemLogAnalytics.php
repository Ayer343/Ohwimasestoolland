<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemLogAnalytics extends Model
{
    use HasFactory;

    protected $table = 'system_log_analytics';
    
    protected $fillable = [
        'analytics_date',
        'total_logs',
        'error_logs',
        'critical_logs',
        'warning_logs',
        'info_logs',
        'debug_logs',
        'resolved_logs',
        'unresolved_logs',
        'recurring_logs',
        'sla_breached_logs',
        'alerts_sent',
        'avg_response_time_ms',
        'avg_resolution_time_hours',
        'source_distribution',
        'component_distribution',
        'user_type_distribution',
        'severity_distribution',
        'priority_distribution',
    ];

    protected $casts = [
        'analytics_date' => 'date',
        'source_distribution' => 'array',
        'component_distribution' => 'array',
        'user_type_distribution' => 'array',
        'severity_distribution' => 'array',
        'priority_distribution' => 'array',
        'avg_response_time_ms' => 'decimal:2',
        'avg_resolution_time_hours' => 'decimal:2',
    ];

    /**
     * Generate analytics for a specific date
     */
    public static function generateForDate($date = null): self
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now()->subDay();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        
        $logs = SystemLog::whereBetween('created_at', [$start, $end])->get();
        
        $analytics = [
            'analytics_date' => $date->format('Y-m-d'),
            'total_logs' => $logs->count(),
            'error_logs' => $logs->whereIn('level', [
                SystemLog::LEVEL_ERROR, 
                SystemLog::LEVEL_CRITICAL, 
                SystemLog::LEVEL_EMERGENCY, 
                SystemLog::LEVEL_ALERT
            ])->count(),
            'critical_logs' => $logs->where('level', SystemLog::LEVEL_CRITICAL)->count(),
            'warning_logs' => $logs->where('level', SystemLog::LEVEL_WARNING)->count(),
            'info_logs' => $logs->where('level', SystemLog::LEVEL_INFO)->count(),
            'debug_logs' => $logs->where('level', SystemLog::LEVEL_DEBUG)->count(),
            'resolved_logs' => $logs->where('resolved', true)->count(),
            'unresolved_logs' => $logs->where('resolved', false)->count(),
            'recurring_logs' => $logs->where('is_recurring', true)->count(),
            'sla_breached_logs' => $logs->where('sla_breached', true)->count(),
            'alerts_sent' => $logs->where('alert_sent', true)->count(),
            'avg_response_time_ms' => $logs->avg('response_time_ms'),
            'avg_resolution_time_hours' => SystemLog::whereBetween('created_at', [$start, $end])
                ->where('resolved', true)
                ->whereNotNull('resolved_at')
                ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, resolved_at)')),
        ];
        
        // Distribution data
        $analytics['source_distribution'] = $logs->groupBy('source')->map->count()->toArray();
        $analytics['component_distribution'] = $logs->groupBy('component')->map->count()->toArray();
        $analytics['user_type_distribution'] = $logs->groupBy('user_type')->map->count()->toArray();
        $analytics['severity_distribution'] = $logs->groupBy('severity')->map->count()->toArray();
        $analytics['priority_distribution'] = $logs->groupBy('priority')->map->count()->toArray();
        
        return self::updateOrCreate(
            ['analytics_date' => $analytics['analytics_date']],
            $analytics
        );
    }

    /**
     * Get analytics for date range
     */
    public static function getRangeAnalytics($startDate, $endDate): array
    {
        return self::whereBetween('analytics_date', [$startDate, $endDate])
            ->orderBy('analytics_date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->analytics_date->format('Y-m-d'),
                    'total_logs' => $item->total_logs,
                    'error_logs' => $item->error_logs,
                    'resolved_logs' => $item->resolved_logs,
                    'unresolved_logs' => $item->unresolved_logs,
                    'resolution_rate' => $item->total_logs > 0 ? 
                        round(($item->resolved_logs / $item->total_logs) * 100, 1) : 0,
                    'avg_resolution_time_hours' => $item->avg_resolution_time_hours,
                ];
            })
            ->toArray();
    }
}