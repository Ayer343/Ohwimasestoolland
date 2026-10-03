<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\SystemLogComment;
use App\Models\SystemLogAttachment;
use App\Models\SystemLogAnalytics;
use App\Models\User;
use App\Models\Maintenance;
use App\Models\EmergencyMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SystemLogController extends Controller
{
    /**
     * Display system logs with filtering
     */
    public function index(Request $request)
    {
        $query = SystemLog::with(['user', 'resolver', 'assignee', 'maintenance', 'emergencyMode'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        $this->applyFilters($query, $request);

        $logs = $query->paginate(50);
        
        // Get filter options
        $filterOptions = $this->getFilterOptions();
        
        // Get statistics
        $statistics = SystemLog::getStatistics($request->get('time_range', '24h'));

        return view('developer.logs.index', compact('logs', 'filterOptions', 'statistics'));
    }

    /**
     * Show log details
     */
    public function show($id)
    {
        $log = SystemLog::with([
            'user',
            'resolver',
            'assignee',
            'maintenance',
            'emergencyMode',
            'comments' => function($query) {
                $query->with('creator')->orderBy('created_at', 'desc');
            },
            'attachments' => function($query) {
                $query->with('uploader')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // Get similar logs
        $similarLogs = SystemLog::where('message', 'like', '%' . substr($log->message, 0, 50) . '%')
            ->where('id', '!=', $log->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Get log history for recurring logs
        $logHistory = [];
        if ($log->is_recurring) {
            $logHistory = SystemLog::where('message', $log->message)
                ->where('source', $log->source)
                ->where('component', $log->component)
                ->where('id', '!=', $log->id)
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();
        }

        return view('developer.logs.show', compact('log', 'similarLogs', 'logHistory'));
    }

    /**
     * Get logs via API for dashboard/analytics
     */
    public function getLogs(Request $request)
    {
        try {
            $query = SystemLog::query();
            
            // Apply filters
            $this->applyFilters($query, $request);
            
            $limit = $request->get('limit', 50);
            $logs = $query->orderBy('created_at', 'desc')->limit($limit)->get();
            
            return response()->json([
                'success' => true,
                'logs' => $logs->map(function ($log) {
                    return $this->transformLogForApi($log);
                }),
                'total' => $logs->count(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch logs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get log statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $timeRange = $request->get('time_range', '24h');
            $statistics = SystemLog::getStatistics($timeRange);
            
            // Add additional statistics if requested
            if ($request->boolean('detailed', false)) {
                $statistics['top_sources'] = SystemLog::getTopSources(10, $timeRange);
                $statistics['trends'] = SystemLog::getTrends(7);
            }
            
            return response()->json([
                'success' => true,
                'statistics' => $statistics,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch log statistics', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
            ], 500);
        }
    }

    /**
     * Get log analytics
     */
    public function getAnalytics(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->get('end_date', now()->format('Y-m-d'));
            
            $analytics = SystemLogAnalytics::getRangeAnalytics($startDate, $endDate);
            
            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch log analytics', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
            ], 500);
        }
    }

    /**
     * Mark log as resolved
     */
    public function markResolved($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        if ($log->resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Log is already resolved',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'resolution_notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $log->markAsResolved(
                Auth::id(),
                $request->resolution_notes,
                $request->metadata
            );

            // Add resolution comment
            if ($request->resolution_notes) {
                SystemLogComment::create([
                    'system_log_id' => $log->id,
                    'comment' => "Resolved: " . $request->resolution_notes,
                    'created_by' => Auth::id(),
                    'created_by_name' => Auth::user()->name,
                    'created_by_type' => Auth::user()->type,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Log marked as resolved',
                'log' => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to mark log as resolved', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark log as resolved',
            ], 500);
        }
    }

    /**
     * Assign log to user
     */
    public function assignLog($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $log->assignTo($request->user_id);

            // Add assignment comment
            $user = User::find($request->user_id);
            $comment = "Assigned to {$user->name}";
            
            if ($request->notes) {
                $comment .= ": " . $request->notes;
            }

            SystemLogComment::create([
                'system_log_id' => $log->id,
                'comment' => $comment,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Log assigned successfully',
                'log' => $this->transformLogForApi($log),
                'assigned_to' => $user->name,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to assign log', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign log',
            ], 500);
        }
    }

    /**
     * Escalate log
     */
    public function escalateLog($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $log->escalate($request->reason);

            // Add escalation comment
            SystemLogComment::create([
                'system_log_id' => $log->id,
                'comment' => "Escalated: " . $request->reason,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Log escalated successfully',
                'log' => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to escalate log', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to escalate log',
            ], 500);
        }
    }

    /**
     * Add comment to log
     */
    public function addComment($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:5',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $comment = SystemLogComment::create([
                'system_log_id' => $log->id,
                'comment' => $request->comment,
                'metadata' => $request->metadata,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // If this is the first comment and log requires intervention, update status
            if ($log->requires_human_intervention && $log->intervention_status === SystemLog::INTERVENTION_PENDING) {
                $log->intervention_status = SystemLog::INTERVENTION_IN_PROGRESS;
                $log->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment' => [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'created_by' => $comment->created_by_name,
                    'created_by_type' => $comment->created_by_type,
                    'created_at' => $comment->created_at->toISOString(),
                    'time_ago' => $comment->created_at->diffForHumans(),
                    'metadata' => $comment->metadata,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add comment', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
            ], 500);
        }
    }

    /**
     * Upload attachment to log
     */
    public function uploadAttachment($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $filename = 'log_' . $log->id . '_' . time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('log_attachments', $filename, 'public');

            $attachment = SystemLogAttachment::create([
                'system_log_id' => $log->id,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'path' => $path,
                'size' => $file->getSize(),
                'metadata' => $request->metadata,
                'uploaded_by' => Auth::id(),
            ]);

            // Add upload comment
            SystemLogComment::create([
                'system_log_id' => $log->id,
                'comment' => "Uploaded attachment: {$file->getClientOriginalName()}",
                'metadata' => ['attachment_id' => $attachment->id],
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully',
                'attachment' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->formatted_size,
                    'url' => $attachment->url,
                    'is_image' => $attachment->is_image,
                    'is_document' => $attachment->is_document,
                    'uploaded_at' => $attachment->created_at->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload attachment', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment',
            ], 500);
        }
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment($logId, $attachmentId, Request $request)
    {
        $attachment = SystemLogAttachment::where('system_log_id', $logId)
            ->where('id', $attachmentId)
            ->firstOrFail();

        try {
            // Delete file from storage
            Storage::disk('public')->delete($attachment->path);
            
            // Delete attachment record
            $attachment->delete();

            // Add deletion comment
            SystemLogComment::create([
                'system_log_id' => $logId,
                'comment' => "Deleted attachment: {$attachment->original_filename}",
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete attachment', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
            ], 500);
        }
    }

    /**
     * Send alert for log
     */
    public function sendAlert($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        if ($log->alert_sent) {
            return response()->json([
                'success' => false,
                'message' => 'Alert already sent for this log',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'channels' => 'required|array',
            'channels.*' => 'in:email,sms,whatsapp,in_app,slack',
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $log->sendAlert(
                $request->channels,
                $request->recipients
            );

            // Add alert comment
            $channels = implode(', ', $request->channels);
            $comment = "Alert sent via {$channels}";
            
            if ($request->message) {
                $comment .= ": " . $request->message;
            }

            SystemLogComment::create([
                'system_log_id' => $log->id,
                'comment' => $comment,
                'metadata' => [
                    'channels' => $request->channels,
                    'recipients' => $request->recipients,
                ],
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alert sent successfully',
                'log' => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send alert', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send alert',
            ], 500);
        }
    }

    /**
     * Archive log
     */
    public function archiveLog($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        if ($log->archived) {
            return response()->json([
                'success' => false,
                'message' => 'Log is already archived',
            ], 400);
        }

        try {
            $log->archive();

            // Add archive comment
            SystemLogComment::create([
                'system_log_id' => $log->id,
                'comment' => 'Log archived',
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Log archived successfully',
                'log' => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to archive log', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive log',
            ], 500);
        }
    }

    /**
     * Unarchive log
     */
    public function unarchiveLog($id, Request $request)
    {
        $log = SystemLog::findOrFail($id);
        
        if (!$log->archived) {
            return response()->json([
                'success' => false,
                'message' => 'Log is not archived',
            ], 400);
        }

        try {
            $log->unarchive();

            // Add unarchive comment
            SystemLogComment::create([
                'system_log_id' => $log->id,
                'comment' => 'Log unarchived',
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Log unarchived successfully',
                'log' => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unarchive log', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unarchive log',
            ], 500);
        }
    }

    /**
     * Bulk update logs
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'log_ids' => 'required|array',
            'log_ids.*' => 'exists:system_logs,id',
            'action' => 'required|in:resolve,archive,unarchive,assign,escalate',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $logs = SystemLog::whereIn('id', $request->log_ids)->get();
            $updatedCount = 0;
            $failedCount = 0;
            $results = [];

            foreach ($logs as $log) {
                try {
                    switch ($request->action) {
                        case 'resolve':
                            if (!$log->resolved) {
                                $log->markAsResolved(Auth::id(), $request->data['notes'] ?? null);
                                $updatedCount++;
                                $results[] = ['id' => $log->id, 'status' => 'resolved'];
                            }
                            break;
                            
                        case 'archive':
                            if (!$log->archived) {
                                $log->archive();
                                $updatedCount++;
                                $results[] = ['id' => $log->id, 'status' => 'archived'];
                            }
                            break;
                            
                        case 'unarchive':
                            if ($log->archived) {
                                $log->unarchive();
                                $updatedCount++;
                                $results[] = ['id' => $log->id, 'status' => 'unarchived'];
                            }
                            break;
                            
                        case 'assign':
                            if (isset($request->data['user_id'])) {
                                $log->assignTo($request->data['user_id']);
                                $updatedCount++;
                                $results[] = ['id' => $log->id, 'status' => 'assigned'];
                            }
                            break;
                            
                        case 'escalate':
                            $log->escalate($request->data['reason'] ?? 'Bulk escalation');
                            $updatedCount++;
                            $results[] = ['id' => $log->id, 'status' => 'escalated'];
                            break;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $results[] = ['id' => $log->id, 'status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            // Add bulk action comment
            if ($updatedCount > 0) {
                $comment = "Bulk {$request->action} action performed on {$updatedCount} logs";
                if ($failedCount > 0) {
                    $comment .= " ({$failedCount} failed)";
                }

                // Add comment to first log
                $firstLog = $logs->first();
                if ($firstLog) {
                    SystemLogComment::create([
                        'system_log_id' => $firstLog->id,
                        'comment' => $comment,
                        'metadata' => [
                            'action' => $request->action,
                            'total_logs' => count($request->log_ids),
                            'updated_count' => $updatedCount,
                            'failed_count' => $failedCount,
                            'data' => $request->data,
                        ],
                        'created_by' => Auth::id(),
                        'created_by_name' => Auth::user()->name,
                        'created_by_type' => Auth::user()->type,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk action completed: {$updatedCount} updated, {$failedCount} failed",
                'results' => $results,
                'updated_count' => $updatedCount,
                'failed_count' => $failedCount,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to perform bulk update', [
                'action' => $request->action,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk update',
            ], 500);
        }
    }

    /**
     * Search logs
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3',
            'fields' => 'nullable|array',
            'fields.*' => 'in:message,summary,file,context,trace',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = SystemLog::query();
            $searchQuery = $request->query;
            $fields = $request->fields ?? ['message', 'summary', 'file'];
            
            $query->where(function ($q) use ($fields, $searchQuery) {
                foreach ($fields as $field) {
                    if ($field === 'context' || $field === 'trace') {
                        $q->orWhere($field, 'like', "%{$searchQuery}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$searchQuery}%");
                    }
                }
            });

            $logs = $query->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($log) {
                    return $this->transformLogForApi($log);
                });

            return response()->json([
                'success' => true,
                'logs' => $logs,
                'total' => $logs->count(),
                'query' => $searchQuery,
                'fields_searched' => $fields,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search logs', [
                'query' => $request->query,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search logs',
            ], 500);
        }
    }

    /**
     * Export logs
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,json,xml',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = SystemLog::query();
            
            // Apply date range
            if ($request->start_date) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->end_date) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }
            
            // Apply filters
            if ($request->filters) {
                $this->applyFilters($query, new Request($request->filters));
            }
            
            $logs = $query->orderBy('created_at', 'desc')->get();
            
            // Default columns if not specified
            $columns = $request->columns ?? [
                'reference_id',
                'level',
                'severity',
                'priority',
                'source',
                'component',
                'message',
                'resolved',
                'created_at',
                'resolved_at',
                'response_time_ms',
            ];
            
            // Generate filename
            $filename = 'system_logs_export_' . now()->format('Y-m-d_H-i-s') . '.' . $request->format;
            
            switch ($request->format) {
                case 'csv':
                    return $this->exportToCsv($logs, $columns, $filename);
                    
                case 'json':
                    return $this->exportToJson($logs, $columns, $filename);
                    
                case 'xml':
                    return $this->exportToXml($logs, $columns, $filename);
            }
        } catch (\Exception $e) {
            Log::error('Failed to export logs', [
                'format' => $request->format,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export logs',
            ], 500);
        }
    }

    /**
     * Get log insights and recommendations
     */
    public function getInsights(Request $request)
    {
        try {
            $timeRange = $request->get('time_range', '7d');
            $startDate = match($timeRange) {
                '1h' => now()->subHour(),
                '24h' => now()->subDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                default => now()->subDays(7),
            };

            $insights = [];
            
            // Get recurring errors
            $recurringErrors = SystemLog::where('created_at', '>=', $startDate)
                ->where('is_recurring', true)
                ->where('resolved', false)
                ->groupBy('message', 'source', 'component')
                ->select('message', 'source', 'component')
                ->selectRaw('COUNT(*) as occurrence_count')
                ->selectRaw('MAX(created_at) as last_occurrence')
                ->orderByDesc('occurrence_count')
                ->limit(10)
                ->get()
                ->map(function ($error) {
                    return [
                        'message' => substr($error->message, 0, 100) . (strlen($error->message) > 100 ? '...' : ''),
                        'source' => $error->source,
                        'component' => $error->component,
                        'occurrence_count' => $error->occurrence_count,
                        'last_occurrence' => $error->last_occurrence?->toISOString(),
                        'priority' => 'high',
                        'recommendation' => 'Investigate root cause and implement fix',
                    ];
                });
            
            if ($recurringErrors->isNotEmpty()) {
                $insights[] = [
                    'type' => 'recurring_errors',
                    'title' => 'Recurring Errors Detected',
                    'description' => $recurringErrors->count() . ' recurring errors found',
                    'priority' => 'high',
                    'items' => $recurringErrors,
                ];
            }
            
            // Get SLA breached logs
            $slaBreached = SystemLog::where('created_at', '>=', $startDate)
                ->where('sla_breached', true)
                ->where('resolved', false)
                ->count();
            
            if ($slaBreached > 0) {
                $insights[] = [
                    'type' => 'sla_breaches',
                    'title' => 'SLA Breaches Detected',
                    'description' => "{$slaBreached} logs have breached SLA",
                    'priority' => 'critical',
                    'recommendation' => 'Prioritize resolution of SLA-breached logs',
                ];
            }
            
            // Get logs requiring human intervention
            $needsIntervention = SystemLog::where('created_at', '>=', $startDate)
                ->where('requires_human_intervention', true)
                ->where('resolved', false)
                ->whereIn('intervention_status', [SystemLog::INTERVENTION_PENDING, SystemLog::INTERVENTION_IN_PROGRESS])
                ->count();
            
            if ($needsIntervention > 0) {
                $insights[] = [
                    'type' => 'pending_intervention',
                    'title' => 'Logs Requiring Intervention',
                    'description' => "{$needsIntervention} logs require human intervention",
                    'priority' => 'high',
                    'recommendation' => 'Assign team members to investigate and resolve',
                ];
            }
            
            // Get high severity unresolved logs
            $highSeverity = SystemLog::where('created_at', '>=', $startDate)
                ->whereIn('severity', [SystemLog::SEVERITY_HIGH, SystemLog::SEVERITY_CRITICAL])
                ->where('resolved', false)
                ->count();
            
            if ($highSeverity > 0) {
                $insights[] = [
                    'type' => 'high_severity',
                    'title' => 'High Severity Logs Unresolved',
                    'description' => "{$highSeverity} high/critical severity logs are unresolved",
                    'priority' => 'high',
                    'recommendation' => 'Prioritize resolution of high severity issues',
                ];
            }
            
            // Get error rate trend
            $errorStats = SystemLog::getStatistics($timeRange);
            $errorRate = $errorStats['error_logs'] / max(1, $errorStats['total_logs']) * 100;
            
            if ($errorRate > 10) {
                $insights[] = [
                    'type' => 'high_error_rate',
                    'title' => 'High Error Rate',
                    'description' => "Error rate is " . round($errorRate, 1) . "%",
                    'priority' => 'medium',
                    'recommendation' => 'Investigate root causes of frequent errors',
                ];
            }
            
            // Get common error sources
            $topSources = SystemLog::getTopSources(5, $timeRange);
            if (!empty($topSources)) {
                $highestSource = $topSources[0];
                if ($highestSource['error_rate'] > 50) {
                    $insights[] = [
                        'type' => 'error_prone_source',
                        'title' => 'Error Prone Source Identified',
                        'description' => "{$highestSource['source']} has {$highestSource['error_rate']}% error rate",
                        'priority' => 'medium',
                        'recommendation' => 'Focus debugging efforts on ' . $highestSource['source'],
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'insights' => $insights,
                'total_insights' => count($insights),
                'critical_insights' => count(array_filter($insights, fn($i) => $i['priority'] === 'critical')),
                'high_priority_insights' => count(array_filter($insights, fn($i) => in_array($i['priority'], ['critical', 'high']))),
                'time_range' => $timeRange,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate insights', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate insights',
            ], 500);
        }
    }

    /**
     * Clean up old logs based on retention policy
     */
    public function cleanup(Request $request)
    {
        try {
            $retentionDays = $request->get('retention_days', 90);
            $archiveOnly = $request->boolean('archive_only', true);
            $dryRun = $request->boolean('dry_run', false);
            
            $cutoffDate = now()->subDays($retentionDays);
            
            $query = SystemLog::where('created_at', '<', $cutoffDate)
                ->where('archived', false);
            
            $logsToProcess = $query->count();
            
            if ($dryRun) {
                return response()->json([
                    'success' => true,
                    'message' => 'Dry run completed',
                    'logs_to_process' => $logsToProcess,
                    'cutoff_date' => $cutoffDate->toISOString(),
                    'retention_days' => $retentionDays,
                    'archive_only' => $archiveOnly,
                    'timestamp' => now()->toISOString(),
                ]);
            }
            
            $processed = 0;
            $archived = 0;
            $deleted = 0;
            
            if ($archiveOnly) {
                // Archive old logs
                $archived = $query->update(['archived' => true, 'archived_at' => now()]);
                $processed = $archived;
            } else {
                // Delete old logs (with soft delete)
                $deleted = $query->delete();
                $processed = $deleted;
            }
            
            // Log cleanup activity
            SystemLog::createLog([
                'level' => SystemLog::LEVEL_INFO,
                'source' => 'SystemLogController',
                'component' => 'cleanup',
                'message' => "Log cleanup completed: {$processed} logs processed",
                'context' => [
                    'retention_days' => $retentionDays,
                    'archive_only' => $archiveOnly,
                    'cutoff_date' => $cutoffDate->toISOString(),
                    'archived' => $archived,
                    'deleted' => $deleted,
                ],
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Log cleanup completed',
                'processed' => $processed,
                'archived' => $archived,
                'deleted' => $deleted,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->toISOString(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup logs',
            ], 500);
        }
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Apply filters to query
     */
    private function applyFilters($query, Request $request)
    {
        // Level filter
        if ($request->filled('level')) {
            $levels = is_array($request->level) ? $request->level : explode(',', $request->level);
            $query->whereIn('level', $levels);
        }

        // Severity filter
        if ($request->filled('severity')) {
            $severities = is_array($request->severity) ? $request->severity : explode(',', $request->severity);
            $query->whereIn('severity', $severities);
        }

        // Priority filter
        if ($request->filled('priority')) {
            $priorities = is_array($request->priority) ? $request->priority : explode(',', $request->priority);
            $query->whereIn('priority', $priorities);
        }

        // Source filter
        if ($request->filled('source')) {
            $sources = is_array($request->source) ? $request->source : explode(',', $request->source);
            $query->whereIn('source', $sources);
        }

        // Component filter
        if ($request->filled('component')) {
            $components = is_array($request->component) ? $request->component : explode(',', $request->component);
            $query->whereIn('component', $components);
        }

        // Log group filter
        if ($request->filled('log_group')) {
            $logGroups = is_array($request->log_group) ? $request->log_group : explode(',', $request->log_group);
            $query->whereIn('log_group', $logGroups);
        }

        // User filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // User type filter
        if ($request->filled('user_type')) {
            $userTypes = is_array($request->user_type) ? $request->user_type : explode(',', $request->user_type);
            $query->whereIn('user_type', $userTypes);
        }

        // Resolution status
        if ($request->filled('resolved')) {
            $query->where('resolved', $request->boolean('resolved'));
        }

        // Archived status
        if ($request->filled('archived')) {
            $query->where('archived', $request->boolean('archived'));
        }

        // Requires human intervention
        if ($request->filled('requires_intervention')) {
            $query->where('requires_human_intervention', $request->boolean('requires_intervention'));
        }

        // Intervention status
        if ($request->filled('intervention_status')) {
            $statuses = is_array($request->intervention_status) ? $request->intervention_status : explode(',', $request->intervention_status);
            $query->whereIn('intervention_status', $statuses);
        }

        // SLA breached
        if ($request->filled('sla_breached')) {
            $query->where('sla_breached', $request->boolean('sla_breached'));
        }

        // Recurring logs
        if ($request->filled('is_recurring')) {
            $query->where('is_recurring', $request->boolean('is_recurring'));
        }

        // Alert sent
        if ($request->filled('alert_sent')) {
            $query->where('alert_sent', $request->boolean('alert_sent'));
        }

        // Assigned to
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Related maintenance
        if ($request->filled('related_maintenance_id')) {
            $query->where('related_maintenance_id', $request->related_maintenance_id);
        }

        // Related emergency
        if ($request->filled('related_emergency_id')) {
            $query->where('related_emergency_id', $request->related_emergency_id);
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Search term
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%")
                  ->orWhere('file', 'like', "%{$search}%");
            });
        }

        // Tags filter
        if ($request->filled('tags')) {
            $tags = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Affected modules filter
        if ($request->filled('affected_module')) {
            $query->whereJsonContains('affected_modules', $request->affected_module);
        }
    }

    /**
     * Get filter options for UI
     */
    private function getFilterOptions(): array
    {
        return [
            'levels' => [
                SystemLog::LEVEL_EMERGENCY => 'Emergency',
                SystemLog::LEVEL_ALERT => 'Alert',
                SystemLog::LEVEL_CRITICAL => 'Critical',
                SystemLog::LEVEL_ERROR => 'Error',
                SystemLog::LEVEL_WARNING => 'Warning',
                SystemLog::LEVEL_NOTICE => 'Notice',
                SystemLog::LEVEL_INFO => 'Info',
                SystemLog::LEVEL_DEBUG => 'Debug',
                SystemLog::LEVEL_TRACE => 'Trace',
            ],
            'severities' => [
                SystemLog::SEVERITY_CRITICAL => 'Critical',
                SystemLog::SEVERITY_HIGH => 'High',
                SystemLog::SEVERITY_MEDIUM => 'Medium',
                SystemLog::SEVERITY_LOW => 'Low',
            ],
            'priorities' => [
                SystemLog::PRIORITY_CRITICAL => 'Critical',
                SystemLog::PRIORITY_HIGH => 'High',
                SystemLog::PRIORITY_MEDIUM => 'Medium',
                SystemLog::PRIORITY_LOW => 'Low',
            ],
            'intervention_statuses' => [
                SystemLog::INTERVENTION_PENDING => 'Pending',
                SystemLog::INTERVENTION_IN_PROGRESS => 'In Progress',
                SystemLog::INTERVENTION_COMPLETED => 'Completed',
                SystemLog::INTERVENTION_ESCALATED => 'Escalated',
            ],
            'log_groups' => [
                SystemLog::GROUP_AUTHENTICATION => 'Authentication',
                SystemLog::GROUP_AUTHORIZATION => 'Authorization',
                SystemLog::GROUP_DATABASE => 'Database',
                SystemLog::GROUP_API => 'API',
                SystemLog::GROUP_QUEUE => 'Queue',
                SystemLog::GROUP_CACHE => 'Cache',
                SystemLog::GROUP_STORAGE => 'Storage',
                SystemLog::GROUP_EMAIL => 'Email',
                SystemLog::GROUP_SMS => 'SMS',
                SystemLog::GROUP_WHATSAPP => 'WhatsApp',
                SystemLog::GROUP_PAYMENT => 'Payment',
                SystemLog::GROUP_MAINTENANCE => 'Maintenance',
                SystemLog::GROUP_EMERGENCY => 'Emergency',
                SystemLog::GROUP_SECURITY => 'Security',
                SystemLog::GROUP_PERFORMANCE => 'Performance',
                SystemLog::GROUP_BUSINESS => 'Business',
                SystemLog::GROUP_SYSTEM => 'System',
                SystemLog::GROUP_APPLICATION => 'Application',
            ],
            'user_types' => [
                '0' => 'Super Admin',
                '1' => 'Admin',
                '2' => 'Landlord',
                '3' => 'Tenant',
                '4' => 'Field Agent',
                '5' => 'Developer',
                '6' => 'Security Checkpoint',
            ],
            'sources' => SystemLog::distinct()->whereNotNull('source')->pluck('source')->toArray(),
            'components' => SystemLog::distinct()->whereNotNull('component')->pluck('component')->toArray(),
        ];
    }

    /**
     * Transform log for API response
     */
    private function transformLogForApi(SystemLog $log): array
    {
        return [
            'id' => $log->id,
            'reference_id' => $log->reference_id,
            'level' => $log->level,
            'level_name' => $log->level_name,
            'level_color' => $log->level_color,
            'severity' => $log->severity,
            'severity_color' => $log->severity_color,
            'priority' => $log->priority,
            'priority_color' => $log->priority_color,
            'source' => $log->source,
            'component' => $log->component,
            'message' => $log->message,
            'summary' => $log->summary,
            'file' => $log->file,
            'line' => $log->line,
            'resolved' => $log->resolved,
            'resolved_at' => $log->resolved_at?->toISOString(),
            'resolved_by' => $log->resolved_by,
            'resolved_by_name' => $log->resolver?->name,
            'archived' => $log->archived,
            'archived_at' => $log->archived_at?->toISOString(),
            'requires_human_intervention' => $log->requires_human_intervention,
            'intervention_status' => $log->intervention_status,
            'intervention_status_color' => $log->intervention_status_color,
            'sla_breached' => $log->sla_breached,
            'sla_deadline' => $log->sla_deadline?->toISOString(),
            'is_recurring' => $log->is_recurring,
            'occurrence_count' => $log->occurrence_count,
            'frequency_per_hour' => $log->frequency_per_hour,
            'alert_sent' => $log->alert_sent,
            'alert_sent_at' => $log->alert_sent_at?->toISOString(),
            'assigned_to' => $log->assigned_to,
            'assigned_to_name' => $log->assignee?->name,
            'assigned_at' => $log->assigned_at?->toISOString(),
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name,
            'user_type' => $log->user_type,
            'affected_users_count' => $log->affected_users_count,
            'affected_modules' => $log->affected_modules_list,
            'response_time_ms' => $log->response_time_ms,
            'response_code' => $log->response_code,
            'created_at' => $log->created_at->toISOString(),
            'updated_at' => $log->updated_at->toISOString(),
            'time_since_creation' => $log->time_since_creation,
            'time_since_last_occurrence' => $log->time_since_last_occurrence,
            'resolution_time_hours' => $log->resolution_time_hours,
            'formatted_resolution_time' => $log->formatted_resolution_time,
            'is_stale' => $log->is_stale,
            'comments_count' => $log->comments->count(),
            'attachments_count' => $log->attachments->count(),
            'related_maintenance_id' => $log->related_maintenance_id,
            'related_maintenance_title' => $log->maintenance?->title,
            'related_emergency_id' => $log->related_emergency_id,
            'related_emergency_name' => $log->emergencyMode?->name,
        ];
    }

    /**
     * Export logs to CSV
     */
    private function exportToCsv($logs, $columns, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($logs, $columns) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Write headers
            fputcsv($file, $columns);
            
            // Write data
            foreach ($logs as $log) {
                $row = [];
                foreach ($columns as $column) {
                    $row[] = $this->getColumnValue($log, $column);
                }
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export logs to JSON
     */
    private function exportToJson($logs, $columns, $filename)
    {
        $data = $logs->map(function ($log) use ($columns) {
            $row = [];
            foreach ($columns as $column) {
                $row[$column] = $this->getColumnValue($log, $column);
            }
            return $row;
        });

        return response()->json($data, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export logs to XML
     */
    private function exportToXml($logs, $columns, $filename)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><logs></logs>');
        
        foreach ($logs as $log) {
            $logElement = $xml->addChild('log');
            foreach ($columns as $column) {
                $value = $this->getColumnValue($log, $column);
                $logElement->addChild($column, htmlspecialchars($value));
            }
        }

        return response($xml->asXML(), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get column value for export
     */
    private function getColumnValue($log, $column)
    {
        $value = $log->$column;
        
        if (is_array($value)) {
            return json_encode($value);
        }
        
        if ($value instanceof \Carbon\Carbon) {
            return $value->toISOString();
        }
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if ($value === null) {
            return '';
        }
        
        return $value;
    }
}