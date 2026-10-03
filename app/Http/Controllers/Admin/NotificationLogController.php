<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationLogController extends Controller
{
    /**
     * Display a listing of notification logs
     */
    public function index(Request $request)
    {
        // Only admins can view logs
        $this->authorize('view', NotificationLog::class);
        
        $query = NotificationLog::query();
        
        // Filter by type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }
        
        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('provider', 'like', "%{$search}%");
            });
        }
        
        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);
        
        // Get statistics
        $stats = $this->getStatistics();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $logs,
                'stats' => $stats
            ]);
        }
        
        return view('admin.notification-logs.index', compact('logs', 'stats'));
    }
    
    /**
     * Display a specific notification log
     */
    public function show(NotificationLog $log)
    {
        $this->authorize('view', $log);
        
        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }
    
    /**
     * Clean up old notification logs
     */
    public function cleanup(Request $request)
    {
        $this->authorize('delete', NotificationLog::class);
        
        $validator = Validator::make($request->all(), [
            'older_than_days' => 'nullable|integer|min:1|max:365',
            'keep_errors' => 'nullable|boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $olderThanDays = $request->older_than_days ?? 30;
        $keepErrors = $request->keep_errors ?? true;
        
        $cutoffDate = now()->subDays($olderThanDays);
        
        DB::beginTransaction();
        
        try {
            $query = NotificationLog::where('created_at', '<', $cutoffDate);
            
            // Keep error logs if configured
            if ($keepErrors) {
                $query->where('status', '!=', 'error')
                      ->where('status', '!=', 'failed');
            }
            
            $deletedCount = $query->delete();
            
            // Get count of kept logs
            $keptCount = NotificationLog::where('created_at', '<', $cutoffDate)->count();
            
            DB::commit();
            
            Log::info('Notification logs cleanup completed', [
                'deleted_count' => $deletedCount,
                'kept_count' => $keptCount,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
                'keep_errors' => $keepErrors
            ]);
            
            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'kept_count' => $keptCount,
                'deleted_older_than' => "{$olderThanDays} days",
                'message' => "Cleaned up {$deletedCount} notification logs older than {$olderThanDays} days"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to cleanup notification logs: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup notification logs: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get notification log statistics
     */
    private function getStatistics()
    {
        return [
            'total' => NotificationLog::count(),
            'by_type' => NotificationLog::groupBy('type')
                ->select('type', DB::raw('count(*) as count'))
                ->pluck('count', 'type'),
            'by_status' => NotificationLog::groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->pluck('count', 'status'),
            'today' => NotificationLog::whereDate('created_at', today())->count(),
            'errors' => NotificationLog::where('status', 'error')->count(),
            'last_hour' => NotificationLog::where('created_at', '>=', now()->subHour())->count(),
            'total_sent' => NotificationLog::where('status', 'sent')->count(),
            'total_failed' => NotificationLog::where('status', 'failed')->count(),
            'average_attempts' => NotificationLog::avg('attempts') ?? 0,
        ];
    }
}