<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class WhatsAppLogController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * List WhatsApp logs
     */
    public function index(Request $request): View
    {
        $query = WhatsAppLog::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Filter by provider
        if ($request->has('provider') && $request->provider !== 'all') {
            $query->where('provider', $request->provider);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('provider', 'like', "%{$search}%")
                  ->orWhere('message_id', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(30);

        // Get statistics
        $statistics = [
            'total' => WhatsAppLog::count(),
            'success' => WhatsAppLog::where('status', 'success')->count(),
            'failed' => WhatsAppLog::where('status', 'failed')->count(),
            'pending' => WhatsAppLog::where('status', 'pending')->count(),
            'today' => WhatsAppLog::whereDate('created_at', now()->toDateString())->count(),
            'this_week' => WhatsAppLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => WhatsAppLog::whereMonth('created_at', now()->month)->count(),
        ];

        // Get provider distribution
        $providers = WhatsAppLog::select('provider')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('provider')
            ->get()
            ->pluck('count', 'provider')
            ->toArray();

        return view('admin.whatsapp.logs.index', compact('logs', 'statistics', 'providers'));
    }

    /**
     * Show log details
     */
    public function show($id): View
    {
        $log = WhatsAppLog::with('user')->findOrFail($id);
        
        return view('admin.whatsapp.logs.show', compact('log'));
    }

    /**
     * Clear logs
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'older_than' => 'nullable|integer|min:1',
                'status' => 'nullable|in:success,failed,pending'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = WhatsAppLog::query();

            // Filter by older than days
            if ($request->has('older_than')) {
                $query->where('created_at', '<', now()->subDays($request->older_than));
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $count = $query->count();
            $query->delete();

            Log::info('WhatsApp logs cleared', [
                'count' => $count,
                'older_than' => $request->older_than ?? 'all',
                'status' => $request->status ?? 'all',
                'cleared_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$count} log entries cleared successfully!",
                'count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing WhatsApp logs: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export logs
     */
    public function export(Request $request)
    {
        try {
            $query = WhatsAppLog::query()
                ->with('user')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('provider') && $request->provider !== 'all') {
                $query->where('provider', $request->provider);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $logs = $query->get();

            // Generate CSV
            $filename = 'whatsapp_logs_' . date('Y-m-d_H-i-s') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function() use ($logs) {
                $handle = fopen('php://output', 'w');
                
                // Add headers
                fputcsv($handle, [
                    'ID', 'Date', 'Provider', 'To', 'Message Type', 'Status', 
                    'Message ID', 'User', 'Message', 'Response', 'Created By'
                ]);
                
                // Add data
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->provider,
                        $log->to,
                        $log->type,
                        $log->status,
                        $log->message_id,
                        $log->user->name ?? 'System',
                        substr($log->message, 0, 100),
                        substr(json_encode($log->response), 0, 200),
                        $log->created_by ?? 'System'
                    ]);
                }
                
                fclose($handle);
            };

            return Response::stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error exporting WhatsApp logs: ' . $e->getMessage());
            
            return redirect()->back()->with('error', 'Failed to export logs: ' . $e->getMessage());
        }
    }

    /**
     * Get log statistics (API)
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = [
                'total' => WhatsAppLog::count(),
                'success' => WhatsAppLog::where('status', 'success')->count(),
                'failed' => WhatsAppLog::where('status', 'failed')->count(),
                'pending' => WhatsAppLog::where('status', 'pending')->count(),
                'today' => WhatsAppLog::whereDate('created_at', now()->toDateString())->count(),
                'this_week' => WhatsAppLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month' => WhatsAppLog::whereMonth('created_at', now()->month)->count(),
                'last_hour' => WhatsAppLog::where('created_at', '>=', now()->subHour())->count(),
                'last_24_hours' => WhatsAppLog::where('created_at', '>=', now()->subDay())->count(),
            ];

            // Provider distribution
            $statistics['providers'] = WhatsAppLog::select('provider')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('provider')
                ->get()
                ->pluck('count', 'provider')
                ->toArray();

            // Status distribution
            $statistics['statuses'] = WhatsAppLog::select('status')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting WhatsApp log statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}