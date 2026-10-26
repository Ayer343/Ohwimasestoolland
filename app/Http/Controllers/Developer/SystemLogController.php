<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\SystemLogComment;
use App\Models\SystemLogAttachment;
use App\Models\SystemLogAnalytics;
use App\Models\User;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SystemLogController extends Controller
{
    use AuditLogger;

    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_DEVELOPER   = 5;

    /** Roles permitted to receive system-log alerts. */
    const ALERT_RECIPIENT_TYPES = [self::USER_TYPE_SUPER_ADMIN, self::USER_TYPE_DEVELOPER];

    /** Hard cap on rows returned by an export. */
    const EXPORT_ROW_LIMIT = 50000;

    /** Hard cap on rows returned by search. */
    const SEARCH_ROW_LIMIT = 100;

    /** Allowed upload mime types for log attachments. */
    const ALLOWED_UPLOAD_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/csv', 'text/x-log',
        'application/json', 'application/zip', 'application/x-zip-compressed',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /** Allowed file extensions for log attachments (belt-and-braces). */
    const ALLOWED_UPLOAD_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'txt', 'csv', 'log',
        'json', 'zip', 'doc', 'docx', 'xls', 'xlsx',
    ];

    /* ============================================================
     | AUTHORIZATION
     * ============================================================ */

    private function canManageLogs(): bool
    {
        $user = Auth::user();
        return $user
            && in_array((int) $user->type, [self::USER_TYPE_SUPER_ADMIN, self::USER_TYPE_DEVELOPER], true);
    }

    private function authorizeOrAbort(bool $json = true)
    {
        if ($this->canManageLogs()) {
            return null;
        }

        Log::warning('System log access denied', [
            'user_id'   => Auth::id(),
            'user_type' => Auth::user()->type ?? null,
            'ip'        => request()->ip(),
            'route'     => request()->path(),
        ]);

        if ($json) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Developer (type 5) or Super Admin (type 0) required.',
            ], 403);
        }

        abort(403, 'Unauthorized to access system logs.');
    }

    private function requireSuperAdmin(bool $json = true)
    {
        $user = Auth::user();
        if ($user && (int) $user->type === self::USER_TYPE_SUPER_ADMIN) {
            return null;
        }

        Log::warning('Super-admin-only action denied', [
            'user_id'   => Auth::id(),
            'user_type' => $user->type ?? null,
            'route'     => request()->path(),
        ]);

        return $json
            ? response()->json(['success' => false, 'message' => 'This action requires a Super Admin.'], 403)
            : abort(403, 'This action requires a Super Admin.');
    }

    /* ============================================================
     | BROWSE
     * ============================================================ */

    public function index(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(false)) return $resp;

        $query = SystemLog::with(['user', 'resolver', 'assignee', 'maintenance', 'emergencyMode'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $request);

        $logs = $query->paginate(50);

        $filterOptions = $this->getFilterOptions();

        $statistics = SystemLog::getStatistics($this->normalizeTimeRange($request->get('time_range', '24h')));

        return view('developer.system-logs.index', compact('logs', 'filterOptions', 'statistics'));
    }

    public function show($id)
    {
        if ($resp = $this->authorizeOrAbort(false)) return $resp;

        $log = SystemLog::with([
            'user',
            'resolver',
            'assignee',
            'maintenance',
            'emergencyMode',
            'comments' => function ($query) {
                $query->with('creator')->orderBy('created_at', 'desc');
            },
            'attachments' => function ($query) {
                $query->with('uploader')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // Cap the LIKE window to avoid full-table scans on long messages.
        $similarNeedle = Str::limit((string) $log->message, 50, '');
        $similarLogs = collect();

        if ($similarNeedle !== '') {
            $similarLogs = SystemLog::where('message', 'like', '%' . $similarNeedle . '%')
                ->where('id', '!=', $log->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();
        }

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

        return view('developer.system-logs.show', compact('log', 'similarLogs', 'logHistory'));
    }

    public function getLogs(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        try {
            $query = SystemLog::query();
            $this->applyFilters($query, $request);

            $limit = min(500, max(1, (int) $request->get('limit', 50)));
            $logs = $query->orderBy('created_at', 'desc')->limit($limit)->get();

            return response()->json([
                'success'   => true,
                'logs'      => $logs->map(fn ($log) => $this->transformLogForApi($log)),
                'total'     => $logs->count(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch logs',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function search(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $validator = Validator::make($request->all(), [
            'query'    => 'required|string|min:3|max:200',
            'fields'   => 'nullable|array',
            'fields.*' => 'in:message,summary,file,context,trace',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $query       = SystemLog::query();
            $searchQuery = $request->query; // NB: ->query is the form field named 'query'
            $fields      = $request->fields ?? ['message', 'summary', 'file'];

            $query->where(function ($q) use ($fields, $searchQuery) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', '%' . $searchQuery . '%');
                }
            });

            $logs = $query->orderBy('created_at', 'desc')
                ->limit(self::SEARCH_ROW_LIMIT)
                ->get()
                ->map(fn ($log) => $this->transformLogForApi($log));

            return response()->json([
                'success'        => true,
                'logs'           => $logs,
                'total'          => $logs->count(),
                'query'          => $searchQuery,
                'fields_searched'=> $fields,
                'timestamp'      => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
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

    /* ============================================================
     | STATISTICS / ANALYTICS / INSIGHTS
     * ============================================================ */

    public function getStatistics(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        try {
            $timeRange = $this->normalizeTimeRange($request->get('time_range', '24h'));
            $statistics = SystemLog::getStatistics($timeRange);

            if ($request->boolean('detailed', false)) {
                $statistics['top_sources'] = SystemLog::getTopSources(10, $timeRange);
                $statistics['trends']      = SystemLog::getTrends(7);
            }

            return response()->json([
                'success'    => true,
                'statistics' => $statistics,
                'time_range' => $timeRange,
                'timestamp'  => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch log statistics', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
            ], 500);
        }
    }

    public function getAnalytics(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate   = $request->get('end_date', now()->format('Y-m-d'));

            $analytics = SystemLogAnalytics::getRangeAnalytics($startDate, $endDate);

            return response()->json([
                'success'    => true,
                'analytics'  => $analytics,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'timestamp'  => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch log analytics', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
            ], 500);
        }
    }

    public function getInsights(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $timeRange = $this->normalizeTimeRange($request->get('time_range', '7d'));

        // Short cache — dashboards poll this.
        $cacheKey = 'system_log_insights:' . $timeRange;

        try {
            $payload = Cache::remember($cacheKey, 60, function () use ($timeRange) {
                $startDate = match ($timeRange) {
                    '1h'  => now()->subHour(),
                    '24h' => now()->subDay(),
                    '7d'  => now()->subDays(7),
                    '30d' => now()->subDays(30),
                    '90d' => now()->subDays(90),
                    default => now()->subDays(7),
                };

                $insights = [];

                // Recurring errors
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
                    ->map(fn ($error) => [
                        'message'          => Str::limit($error->message, 100),
                        'source'           => $error->source,
                        'component'        => $error->component,
                        'occurrence_count' => $error->occurrence_count,
                        'last_occurrence'  => $error->last_occurrence
                            ? Carbon::parse($error->last_occurrence)->toISOString()
                            : null,
                        'priority'         => 'high',
                        'recommendation'   => 'Investigate root cause and implement fix',
                    ]);

                if ($recurringErrors->isNotEmpty()) {
                    $insights[] = [
                        'type'        => 'recurring_errors',
                        'title'       => 'Recurring Errors Detected',
                        'description' => $recurringErrors->count() . ' recurring errors found',
                        'priority'    => 'high',
                        'items'       => $recurringErrors,
                    ];
                }

                // SLA breaches
                $slaBreached = SystemLog::where('created_at', '>=', $startDate)
                    ->where('sla_breached', true)
                    ->where('resolved', false)
                    ->count();

                if ($slaBreached > 0) {
                    $insights[] = [
                        'type'           => 'sla_breaches',
                        'title'          => 'SLA Breaches Detected',
                        'description'    => "{$slaBreached} logs have breached SLA",
                        'priority'       => 'critical',
                        'recommendation' => 'Prioritize resolution of SLA-breached logs',
                    ];
                }

                // Pending intervention
                $needsIntervention = SystemLog::where('created_at', '>=', $startDate)
                    ->where('requires_human_intervention', true)
                    ->where('resolved', false)
                    ->whereIn('intervention_status', [
                        SystemLog::INTERVENTION_PENDING,
                        SystemLog::INTERVENTION_IN_PROGRESS,
                    ])
                    ->count();

                if ($needsIntervention > 0) {
                    $insights[] = [
                        'type'           => 'pending_intervention',
                        'title'          => 'Logs Requiring Intervention',
                        'description'    => "{$needsIntervention} logs require human intervention",
                        'priority'       => 'high',
                        'recommendation' => 'Assign team members to investigate and resolve',
                    ];
                }

                // High severity unresolved
                $highSeverity = SystemLog::where('created_at', '>=', $startDate)
                    ->whereIn('severity', [SystemLog::SEVERITY_HIGH, SystemLog::SEVERITY_CRITICAL])
                    ->where('resolved', false)
                    ->count();

                if ($highSeverity > 0) {
                    $insights[] = [
                        'type'           => 'high_severity',
                        'title'          => 'High Severity Logs Unresolved',
                        'description'    => "{$highSeverity} high/critical severity logs are unresolved",
                        'priority'       => 'high',
                        'recommendation' => 'Prioritize resolution of high severity issues',
                    ];
                }

                // Error rate
                $errorStats = SystemLog::getStatistics($timeRange);
                $totalLogs = max(1, $errorStats['total_logs'] ?? 0);
                $errorRate = ($errorStats['error_logs'] ?? 0) / $totalLogs * 100;

                if ($errorRate > 10) {
                    $insights[] = [
                        'type'           => 'high_error_rate',
                        'title'          => 'High Error Rate',
                        'description'    => 'Error rate is ' . round($errorRate, 1) . '%',
                        'priority'       => 'medium',
                        'recommendation' => 'Investigate root causes of frequent errors',
                    ];
                }

                // Error-prone source
                $topSources = SystemLog::getTopSources(5, $timeRange);
                if (!empty($topSources)) {
                    $highestSource = $topSources[0];
                    if (($highestSource['error_rate'] ?? 0) > 50) {
                        $insights[] = [
                            'type'           => 'error_prone_source',
                            'title'          => 'Error Prone Source Identified',
                            'description'    => "{$highestSource['source']} has {$highestSource['error_rate']}% error rate",
                            'priority'       => 'medium',
                            'recommendation' => 'Focus debugging efforts on ' . $highestSource['source'],
                        ];
                    }
                }

                return $insights;
            });

            return response()->json([
                'success'                  => true,
                'insights'                 => $payload,
                'total_insights'           => count($payload),
                'critical_insights'        => count(array_filter($payload, fn ($i) => ($i['priority'] ?? '') === 'critical')),
                'high_priority_insights'   => count(array_filter($payload, fn ($i) => in_array($i['priority'] ?? '', ['critical', 'high'], true))),
                'time_range'               => $timeRange,
                'timestamp'                => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate insights', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate insights',
            ], 500);
        }
    }

    /* ============================================================
     | INDIVIDUAL MUTATIONS
     * ============================================================ */

    public function markResolved($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $log = SystemLog::findOrFail($id);

        if ($log->resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Log is already resolved',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'resolution_notes' => 'nullable|string|max:2000',
            'metadata'         => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $log->markAsResolved(Auth::id(), $request->resolution_notes, $request->metadata);

            if ($request->resolution_notes) {
                SystemLogComment::create([
                    'system_log_id'    => $log->id,
                    'comment'          => 'Resolved: ' . $request->resolution_notes,
                    'created_by'       => Auth::id(),
                    'created_by_name'  => Auth::user()->name,
                    'created_by_type'  => Auth::user()->type,
                    'ip_address'       => $request->ip(),
                    'user_agent'       => $request->userAgent(),
                ]);
            }

            DB::commit();

            $this->logAudit('system_log_resolved', 'System log marked resolved', [
                'log_id'   => $log->id,
                'actor_id' => Auth::id(),
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Log marked as resolved',
                'log'       => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to mark log as resolved', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark log as resolved',
            ], 500);
        }
    }

    public function assignLog($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $log = SystemLog::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'notes'   => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Restrict assignment to developer/super-admin user types.
        $assignee = User::find($request->user_id);
        if (!$assignee || !in_array((int) $assignee->type, self::ALERT_RECIPIENT_TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Assignee must be a Developer or Super Admin.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $log->assignTo($request->user_id);

            $comment = "Assigned to {$assignee->name}";
            if ($request->notes) {
                $comment .= ': ' . $request->notes;
            }

            SystemLogComment::create([
                'system_log_id'   => $log->id,
                'comment'         => $comment,
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            DB::commit();

            $this->logAudit('system_log_assigned', 'System log assigned', [
                'log_id'       => $log->id,
                'assigned_to'  => $assignee->id,
                'actor_id'     => Auth::id(),
            ]);

            return response()->json([
                'success'     => true,
                'message'     => 'Log assigned successfully',
                'log'         => $this->transformLogForApi($log),
                'assigned_to' => $assignee->name,
                'timestamp'   => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to assign log', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign log',
            ], 500);
        }
    }

    public function escalateLog($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $log = SystemLog::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $log->escalate($request->reason);

            SystemLogComment::create([
                'system_log_id'   => $log->id,
                'comment'         => 'Escalated: ' . $request->reason,
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            DB::commit();

            $this->logAudit('system_log_escalated', 'System log escalated', [
                'log_id'   => $log->id,
                'actor_id' => Auth::id(),
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Log escalated successfully',
                'log'       => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to escalate log', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to escalate log',
            ], 500);
        }
    }

    public function addComment($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $log = SystemLog::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'comment'  => 'required|string|min:5|max:5000',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $comment = SystemLogComment::create([
                'system_log_id'   => $log->id,
                'comment'         => $request->comment,
                'metadata'        => $request->metadata,
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            if ($log->requires_human_intervention && $log->intervention_status === SystemLog::INTERVENTION_PENDING) {
                $log->intervention_status = SystemLog::INTERVENTION_IN_PROGRESS;
                $log->save();
            }

            return response()->json([
                'success'   => true,
                'message'   => 'Comment added successfully',
                'comment'   => [
                    'id'              => $comment->id,
                    'comment'         => $comment->comment,
                    'created_by'      => $comment->created_by_name,
                    'created_by_type' => $comment->created_by_type,
                    'created_at'      => $comment->created_at->toISOString(),
                    'time_ago'        => $comment->created_at->diffForHumans(),
                    'metadata'        => $comment->metadata,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to add comment', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
            ], 500);
        }
    }

    /* ============================================================
     | ATTACHMENTS (rate-limited + type-restricted + private disk)
     * ============================================================ */

    public function uploadAttachment($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $key = 'system_log_attach:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many uploads. Please wait before trying again.',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $log = SystemLog::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'file'     => 'required|file|max:10240|mimes:' . implode(',', self::ALLOWED_UPLOAD_EXTENSIONS),
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');

            // Verify mime type against the whitelist (mimes rule checks extension).
            $mime = $file->getMimeType();
            if (!in_array($mime, self::ALLOWED_UPLOAD_MIMES, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File type not permitted.',
                ], 422);
            }

            $originalName = $file->getClientOriginalName();
            $extension    = strtolower($file->getClientOriginalExtension());
            $safeBase     = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'file';
            $safeName     = 'log_' . $log->id . '_' . time() . '_' . $safeBase . '.' . $extension;

            // Store on a private disk, not the public one.
            $disk = config('filesystems.log_attachments_disk', 'local');
            $path = $file->storeAs('log_attachments/' . $log->id, $safeName, $disk);

            $attachment = SystemLogAttachment::create([
                'system_log_id'     => $log->id,
                'filename'          => $safeName,
                'original_filename' => $originalName,
                'mime_type'         => $mime,
                'path'              => $path,
                'disk'              => $disk,
                'size'              => $file->getSize(),
                'metadata'          => $request->metadata,
                'uploaded_by'       => Auth::id(),
            ]);

            SystemLogComment::create([
                'system_log_id'   => $log->id,
                'comment'         => "Uploaded attachment: {$originalName}",
                'metadata'        => ['attachment_id' => $attachment->id],
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            $this->logAudit('system_log_attachment_uploaded', 'Attachment uploaded', [
                'log_id'        => $log->id,
                'attachment_id' => $attachment->id,
                'filename'      => $originalName,
                'size'          => $file->getSize(),
            ]);

            return response()->json([
                'success'    => true,
                'message'    => 'Attachment uploaded successfully',
                'attachment' => [
                    'id'                => $attachment->id,
                    'filename'          => $attachment->original_filename,
                    'mime_type'         => $attachment->mime_type,
                    'size'              => $attachment->formatted_size ?? $file->getSize(),
                    // Never expose a public URL for a private disk — serve
                    // downloads through a dedicated authenticated route.
                    'download_route'    => route('developer.system-logs.attachments.download', [
                        'log' => $log->id,
                        'attachment' => $attachment->id,
                    ]),
                    'is_image'          => $attachment->is_image ?? false,
                    'is_document'       => $attachment->is_document ?? false,
                    'uploaded_at'       => $attachment->created_at->toISOString(),
                ],
                'timestamp'  => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to upload attachment', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment',
            ], 500);
        }
    }

    public function deleteAttachment($logId, $attachmentId, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $attachment = SystemLogAttachment::where('system_log_id', $logId)
            ->where('id', $attachmentId)
            ->firstOrFail();

        try {
            $disk = $attachment->disk ?? config('filesystems.log_attachments_disk', 'local');

            if ($attachment->path && Storage::disk($disk)->exists($attachment->path)) {
                Storage::disk($disk)->delete($attachment->path);
            }

            $originalName = $attachment->original_filename;
            $attachment->delete();

            SystemLogComment::create([
                'system_log_id'   => $logId,
                'comment'         => "Deleted attachment: {$originalName}",
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            $this->logAudit('system_log_attachment_deleted', 'Attachment deleted', [
                'log_id'        => $logId,
                'attachment_id' => $attachmentId,
                'filename'      => $originalName,
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Attachment deleted successfully',
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete attachment', [
                'attachment_id' => $attachmentId,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
            ], 500);
        }
    }

    /* ============================================================
     | ALERTS (rate-limited + recipient-role-restricted)
     * ============================================================ */

    public function sendAlert($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $key = 'system_log_alert:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Alert rate limit reached. Please wait.',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $log = SystemLog::findOrFail($id);

        if ($log->alert_sent) {
            return response()->json([
                'success' => false,
                'message' => 'Alert already sent for this log',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'channels'     => 'required|array|min:1',
            'channels.*'   => 'in:email,sms,whatsapp,in_app,slack',
            'recipients'   => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'message'      => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Restrict recipients to developer / super-admin accounts.
        if ($request->filled('recipients')) {
            $eligible = User::whereIn('id', $request->recipients)
                ->whereIn('type', self::ALERT_RECIPIENT_TYPES)
                ->pluck('id')
                ->all();

            $illegal = array_diff($request->recipients, $eligible);
            if (!empty($illegal)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more recipients are not permitted to receive system-log alerts.',
                    'illegal' => array_values($illegal),
                ], 422);
            }
        }

        try {
            $log->sendAlert($request->channels, $request->recipients);

            $channels = implode(', ', $request->channels);
            $comment  = "Alert sent via {$channels}";
            if ($request->message) {
                $comment .= ': ' . $request->message;
            }

            SystemLogComment::create([
                'system_log_id'   => $log->id,
                'comment'         => $comment,
                'metadata'        => [
                    'channels'   => $request->channels,
                    'recipients' => $request->recipients,
                ],
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            $this->logAudit('system_log_alert_sent', 'System log alert sent', [
                'log_id'     => $log->id,
                'channels'   => $request->channels,
                'recipients' => $request->recipients,
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Alert sent successfully',
                'log'       => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send alert', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send alert',
            ], 500);
        }
    }

    /* ============================================================
     | ARCHIVE / UNARCHIVE
     * ============================================================ */

    public function archiveLog($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $log = SystemLog::findOrFail($id);

        if ($log->archived) {
            return response()->json([
                'success' => false,
                'message' => 'Log is already archived',
            ], 400);
        }

        try {
            $log->archive();

            SystemLogComment::create([
                'system_log_id'   => $log->id,
                'comment'         => 'Log archived',
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            $this->logAudit('system_log_archived', 'System log archived', ['log_id' => $log->id]);

            return response()->json([
                'success'   => true,
                'message'   => 'Log archived successfully',
                'log'       => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to archive log', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive log',
            ], 500);
        }
    }

    public function unarchiveLog($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $log = SystemLog::findOrFail($id);

        if (!$log->archived) {
            return response()->json([
                'success' => false,
                'message' => 'Log is not archived',
            ], 400);
        }

        try {
            $log->unarchive();

            SystemLogComment::create([
                'system_log_id'   => $log->id,
                'comment'         => 'Log unarchived',
                'created_by'      => Auth::id(),
                'created_by_name' => Auth::user()->name,
                'created_by_type' => Auth::user()->type,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            $this->logAudit('system_log_unarchived', 'System log unarchived', ['log_id' => $log->id]);

            return response()->json([
                'success'   => true,
                'message'   => 'Log unarchived successfully',
                'log'       => $this->transformLogForApi($log),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to unarchive log', [
                'log_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unarchive log',
            ], 500);
        }
    }

    /* ============================================================
     | BULK UPDATE (rate-limited + action-specific shape validation)
     * ============================================================ */

    public function bulkUpdate(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $key = 'system_log_bulk:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many bulk operations. Please wait.',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $validator = Validator::make($request->all(), [
            'log_ids'   => 'required|array|min:1|max:500',
            'log_ids.*' => 'exists:system_logs,id',
            'action'    => 'required|in:resolve,archive,unarchive,assign,escalate',
            'data'      => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Action-specific shape validation
        $data = $request->input('data', []);
        $action = $request->action;

        $actionErrors = [];
        switch ($action) {
            case 'assign':
                if (empty($data['user_id'])) {
                    $actionErrors['data.user_id'] = 'user_id is required for assign.';
                } else {
                    $assignee = User::find($data['user_id']);
                    if (!$assignee || !in_array((int) $assignee->type, self::ALERT_RECIPIENT_TYPES, true)) {
                        $actionErrors['data.user_id'] = 'Assignee must be a Developer or Super Admin.';
                    }
                }
                break;

            case 'escalate':
                if (empty($data['reason']) || strlen((string) $data['reason']) < 10) {
                    $actionErrors['data.reason'] = 'reason is required and must be at least 10 characters for escalate.';
                }
                break;
        }

        if (!empty($actionErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $actionErrors,
            ], 422);
        }

        DB::beginTransaction();

        try {
            $logs = SystemLog::whereIn('id', $request->log_ids)->get();
            $updatedCount = 0;
            $failedCount  = 0;
            $results      = [];

            foreach ($logs as $log) {
                try {
                    switch ($action) {
                        case 'resolve':
                            if (!$log->resolved) {
                                $log->markAsResolved(Auth::id(), $data['notes'] ?? null);
                                $updatedCount++;
                                $results[] = ['id' => $log->id, 'status' => 'resolved'];
                            } else {
                                $results[] = ['id' => $log->id, 'status' => 'already_resolved'];
                            }
                            break;

                        case 'archive':
                            if (!$log->archived) {
                                $log->archive();
                                $updatedCount++;
                                $results[] = ['id' => $log->id, 'status' => 'archived'];
                            } else {
                                $results[] = ['id' => $log->id, 'status' => 'already_archived'];
                            }
                            break;

                        case 'unarchive':
                            if ($log->archived) {
                                $log->unarchive();
                                $updatedCount++;
                                $results[] = ['id' => $log->id, 'status' => 'unarchived'];
                            } else {
                                $results[] = ['id' => $log->id, 'status' => 'not_archived'];
                            }
                            break;

                        case 'assign':
                            $log->assignTo($data['user_id']);
                            $updatedCount++;
                            $results[] = ['id' => $log->id, 'status' => 'assigned'];
                            break;

                        case 'escalate':
                            $log->escalate($data['reason']);
                            $updatedCount++;
                            $results[] = ['id' => $log->id, 'status' => 'escalated'];
                            break;
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    $results[] = [
                        'id'     => $log->id,
                        'status' => 'failed',
                        'error'  => config('app.debug') ? $e->getMessage() : 'processing_error',
                    ];
                }
            }

            if ($updatedCount > 0) {
                $comment = "Bulk {$action} action performed on {$updatedCount} logs";
                if ($failedCount > 0) {
                    $comment .= " ({$failedCount} failed)";
                }

                $firstLog = $logs->first();
                if ($firstLog) {
                    SystemLogComment::create([
                        'system_log_id'   => $firstLog->id,
                        'comment'         => $comment,
                        'metadata'        => [
                            'action'        => $action,
                            'total_logs'    => count($request->log_ids),
                            'updated_count' => $updatedCount,
                            'failed_count'  => $failedCount,
                            'data'          => $data,
                        ],
                        'created_by'      => Auth::id(),
                        'created_by_name' => Auth::user()->name,
                        'created_by_type' => Auth::user()->type,
                        'ip_address'      => $request->ip(),
                        'user_agent'      => $request->userAgent(),
                    ]);
                }
            }

            DB::commit();

            $this->logAudit('system_log_bulk_action', 'Bulk action performed', [
                'action'        => $action,
                'updated_count' => $updatedCount,
                'failed_count'  => $failedCount,
            ]);

            return response()->json([
                'success'       => true,
                'message'       => "Bulk action completed: {$updatedCount} updated, {$failedCount} failed",
                'results'       => $results,
                'updated_count' => $updatedCount,
                'failed_count'  => $failedCount,
                'timestamp'     => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to perform bulk update', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk update',
            ], 500);
        }
    }

    /* ============================================================
     | EXPORT (rate-limited + row cap + CSV-injection safe)
     * ============================================================ */

    public function export(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $key = 'system_log_export:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many export requests. Please wait.',
            ], 429);
        }
        RateLimiter::hit($key, 300);

        $validator = Validator::make($request->all(), [
            'format'     => 'required|in:csv,json,xml',
            'filters'    => 'nullable|array',
            'columns'    => 'nullable|array',
            'columns.*'  => 'string|max:100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $query = SystemLog::query();

            if ($request->start_date) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->end_date) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }

            if ($request->filters) {
                $this->applyFilters($query, new Request($request->filters));
            }

            $totalMatched = (clone $query)->count();

            $logs = $query->orderBy('created_at', 'desc')
                ->limit(self::EXPORT_ROW_LIMIT)
                ->get();

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

            $filename = 'system_logs_export_' . now()->format('Y-m-d_H-i-s') . '.' . $request->format;

            $this->logAudit('system_log_exported', 'System logs exported', [
                'format'         => $request->format,
                'rows_exported'  => $logs->count(),
                'rows_matched'   => $totalMatched,
                'filters'        => $request->filters,
                'actor_id'       => Auth::id(),
            ]);

            switch ($request->format) {
                case 'csv':
                    return $this->exportToCsv($logs, $columns, $filename);
                case 'json':
                    return $this->exportToJson($logs, $columns, $filename);
                case 'xml':
                    return $this->exportToXml($logs, $columns, $filename);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to export logs', [
                'format' => $request->format,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export logs',
            ], 500);
        }
    }

    /* ============================================================
     | CLEANUP (super-admin only for non-dry-run; rate-limited)
     * ============================================================ */

    public function cleanup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'retention_days' => 'nullable|integer|min:30|max:3650',
            'archive_only'   => 'nullable|boolean',
            'dry_run'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $dryRun = $request->boolean('dry_run', false);

        // Dry-run is safe for both roles; real cleanup is super-admin-only.
        if ($dryRun) {
            if ($resp = $this->authorizeOrAbort()) return $resp;
        } else {
            if ($resp = $this->requireSuperAdmin()) return $resp;

            $key = 'system_log_cleanup:' . Auth::id();
            if (RateLimiter::tooManyAttempts($key, 1)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cleanup can only run once per hour.',
                ], 429);
            }
            RateLimiter::hit($key, 3600);
        }

        try {
            $retentionDays = (int) $request->get('retention_days', 90);
            $archiveOnly   = $request->boolean('archive_only', true);

            $cutoffDate = now()->subDays($retentionDays);

            $query = SystemLog::where('created_at', '<', $cutoffDate)
                ->where('archived', false);

            $logsToProcess = (clone $query)->count();

            if ($dryRun) {
                return response()->json([
                    'success'         => true,
                    'message'         => 'Dry run completed',
                    'logs_to_process' => $logsToProcess,
                    'cutoff_date'     => $cutoffDate->toISOString(),
                    'retention_days'  => $retentionDays,
                    'archive_only'    => $archiveOnly,
                    'timestamp'       => now()->toISOString(),
                ]);
            }

            $processed = 0;
            $archived  = 0;
            $deleted   = 0;

            if ($archiveOnly) {
                $archived  = (clone $query)->update(['archived' => true, 'archived_at' => now()]);
                $processed = $archived;
            } else {
                $deleted   = (clone $query)->delete();
                $processed = $deleted;
            }

            // Record cleanup itself (after the fact, so it isn't self-deleted).
            SystemLog::createLog([
                'level'     => SystemLog::LEVEL_INFO,
                'source'    => 'SystemLogController',
                'component' => 'cleanup',
                'message'   => "Log cleanup completed: {$processed} logs processed",
                'context'   => [
                    'retention_days' => $retentionDays,
                    'archive_only'   => $archiveOnly,
                    'cutoff_date'    => $cutoffDate->toISOString(),
                    'archived'       => $archived,
                    'deleted'        => $deleted,
                ],
                'user_id'   => Auth::id(),
            ]);

            $this->logAudit('system_log_cleanup', 'System log cleanup performed', [
                'retention_days' => $retentionDays,
                'archive_only'   => $archiveOnly,
                'processed'      => $processed,
                'archived'       => $archived,
                'deleted'        => $deleted,
            ]);

            return response()->json([
                'success'        => true,
                'message'        => 'Log cleanup completed',
                'processed'      => $processed,
                'archived'       => $archived,
                'deleted'        => $deleted,
                'retention_days' => $retentionDays,
                'cutoff_date'    => $cutoffDate->toISOString(),
                'timestamp'      => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to cleanup logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup logs',
            ], 500);
        }
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function normalizeTimeRange(?string $range): string
    {
        $allowed = ['1h', '24h', '7d', '30d', '90d'];
        return in_array($range, $allowed, true) ? $range : '24h';
    }

    private function applyFilters($query, Request $request)
    {
        $explodeList = function ($value) {
            if (is_array($value)) return $value;
            if (is_string($value) && $value !== '') return explode(',', $value);
            return [];
        };

        if ($request->filled('level')) {
            $query->whereIn('level', $explodeList($request->level));
        }

        if ($request->filled('severity')) {
            $query->whereIn('severity', $explodeList($request->severity));
        }

        if ($request->filled('priority')) {
            $query->whereIn('priority', $explodeList($request->priority));
        }

        if ($request->filled('source')) {
            $query->whereIn('source', $explodeList($request->source));
        }

        if ($request->filled('component')) {
            $query->whereIn('component', $explodeList($request->component));
        }

        if ($request->filled('log_group')) {
            $query->whereIn('log_group', $explodeList($request->log_group));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('user_type')) {
            $query->whereIn('user_type', $explodeList($request->user_type));
        }

        if ($request->filled('resolved')) {
            $query->where('resolved', $request->boolean('resolved'));
        }

        if ($request->filled('archived')) {
            $query->where('archived', $request->boolean('archived'));
        }

        if ($request->filled('requires_intervention')) {
            $query->where('requires_human_intervention', $request->boolean('requires_intervention'));
        }

        if ($request->filled('intervention_status')) {
            $query->whereIn('intervention_status', $explodeList($request->intervention_status));
        }

        if ($request->filled('sla_breached')) {
            $query->where('sla_breached', $request->boolean('sla_breached'));
        }

        if ($request->filled('is_recurring')) {
            $query->where('is_recurring', $request->boolean('is_recurring'));
        }

        if ($request->filled('alert_sent')) {
            $query->where('alert_sent', $request->boolean('alert_sent'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('related_maintenance_id')) {
            $query->where('related_maintenance_id', $request->related_maintenance_id);
        }

        if ($request->filled('related_emergency_id')) {
            $query->where('related_emergency_id', $request->related_emergency_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%")
                  ->orWhere('file', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tags')) {
            foreach ($explodeList($request->tags) as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if ($request->filled('affected_module')) {
            $query->whereJsonContains('affected_modules', $request->affected_module);
        }
    }

    private function getFilterOptions(): array
    {
        return Cache::remember('system_log_filter_options', 300, function () {
            return [
                'levels' => [
                    SystemLog::LEVEL_EMERGENCY => 'Emergency',
                    SystemLog::LEVEL_ALERT     => 'Alert',
                    SystemLog::LEVEL_CRITICAL  => 'Critical',
                    SystemLog::LEVEL_ERROR     => 'Error',
                    SystemLog::LEVEL_WARNING   => 'Warning',
                    SystemLog::LEVEL_NOTICE    => 'Notice',
                    SystemLog::LEVEL_INFO      => 'Info',
                    SystemLog::LEVEL_DEBUG     => 'Debug',
                    SystemLog::LEVEL_TRACE     => 'Trace',
                ],
                'severities' => [
                    SystemLog::SEVERITY_CRITICAL => 'Critical',
                    SystemLog::SEVERITY_HIGH     => 'High',
                    SystemLog::SEVERITY_MEDIUM   => 'Medium',
                    SystemLog::SEVERITY_LOW      => 'Low',
                ],
                'priorities' => [
                    SystemLog::PRIORITY_CRITICAL => 'Critical',
                    SystemLog::PRIORITY_HIGH     => 'High',
                    SystemLog::PRIORITY_MEDIUM   => 'Medium',
                    SystemLog::PRIORITY_LOW      => 'Low',
                ],
                'intervention_statuses' => [
                    SystemLog::INTERVENTION_PENDING     => 'Pending',
                    SystemLog::INTERVENTION_IN_PROGRESS => 'In Progress',
                    SystemLog::INTERVENTION_COMPLETED   => 'Completed',
                    SystemLog::INTERVENTION_ESCALATED   => 'Escalated',
                ],
                'log_groups' => [
                    SystemLog::GROUP_AUTHENTICATION => 'Authentication',
                    SystemLog::GROUP_AUTHORIZATION  => 'Authorization',
                    SystemLog::GROUP_DATABASE       => 'Database',
                    SystemLog::GROUP_API            => 'API',
                    SystemLog::GROUP_QUEUE          => 'Queue',
                    SystemLog::GROUP_CACHE          => 'Cache',
                    SystemLog::GROUP_STORAGE        => 'Storage',
                    SystemLog::GROUP_EMAIL          => 'Email',
                    SystemLog::GROUP_SMS            => 'SMS',
                    SystemLog::GROUP_WHATSAPP       => 'WhatsApp',
                    SystemLog::GROUP_PAYMENT        => 'Payment',
                    SystemLog::GROUP_MAINTENANCE    => 'Maintenance',
                    SystemLog::GROUP_EMERGENCY      => 'Emergency',
                    SystemLog::GROUP_SECURITY       => 'Security',
                    SystemLog::GROUP_PERFORMANCE    => 'Performance',
                    SystemLog::GROUP_BUSINESS       => 'Business',
                    SystemLog::GROUP_SYSTEM         => 'System',
                    SystemLog::GROUP_APPLICATION    => 'Application',
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
                'sources'    => SystemLog::distinct()->whereNotNull('source')->pluck('source')->toArray(),
                'components' => SystemLog::distinct()->whereNotNull('component')->pluck('component')->toArray(),
            ];
        });
    }

    private function transformLogForApi(SystemLog $log): array
    {
        return [
            'id'                            => $log->id,
            'reference_id'                  => $log->reference_id,
            'level'                         => $log->level,
            'level_name'                    => $log->level_name,
            'level_color'                   => $log->level_color,
            'severity'                      => $log->severity,
            'severity_color'                => $log->severity_color,
            'priority'                      => $log->priority,
            'priority_color'                => $log->priority_color,
            'source'                        => $log->source,
            'component'                     => $log->component,
            'message'                       => $log->message,
            'summary'                       => $log->summary,
            'file'                          => $log->file,
            'line'                          => $log->line,
            'resolved'                      => $log->resolved,
            'resolved_at'                   => $log->resolved_at?->toISOString(),
            'resolved_by'                   => $log->resolved_by,
            'resolved_by_name'              => $log->resolver?->name,
            'archived'                      => $log->archived,
            'archived_at'                   => $log->archived_at?->toISOString(),
            'requires_human_intervention'   => $log->requires_human_intervention,
            'intervention_status'           => $log->intervention_status,
            'intervention_status_color'     => $log->intervention_status_color,
            'sla_breached'                  => $log->sla_breached,
            'sla_deadline'                  => $log->sla_deadline?->toISOString(),
            'is_recurring'                  => $log->is_recurring,
            'occurrence_count'              => $log->occurrence_count,
            'frequency_per_hour'            => $log->frequency_per_hour,
            'alert_sent'                    => $log->alert_sent,
            'alert_sent_at'                 => $log->alert_sent_at?->toISOString(),
            'assigned_to'                   => $log->assigned_to,
            'assigned_to_name'              => $log->assignee?->name,
            'assigned_at'                   => $log->assigned_at?->toISOString(),
            'user_id'                       => $log->user_id,
            'user_name'                     => $log->user?->name,
            'user_type'                     => $log->user_type,
            'affected_users_count'          => $log->affected_users_count,
            'affected_modules'              => $log->affected_modules_list,
            'response_time_ms'              => $log->response_time_ms,
            'response_code'                 => $log->response_code,
            'created_at'                    => $log->created_at->toISOString(),
            'updated_at'                    => $log->updated_at->toISOString(),
            'time_since_creation'           => $log->time_since_creation,
            'time_since_last_occurrence'    => $log->time_since_last_occurrence,
            'resolution_time_hours'         => $log->resolution_time_hours,
            'formatted_resolution_time'     => $log->formatted_resolution_time,
            'is_stale'                      => $log->is_stale,
            'comments_count'                => $log->comments->count(),
            'attachments_count'             => $log->attachments->count(),
            'related_maintenance_id'        => $log->related_maintenance_id,
            'related_maintenance_title'     => $log->maintenance?->title,
            'related_emergency_id'          => $log->related_emergency_id,
            'related_emergency_name'        => $log->emergencyMode?->name,
        ];
    }

    private function exportToCsv($logs, $columns, $filename)
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $callback = function () use ($logs, $columns) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($file, $columns);

            foreach ($logs as $log) {
                $row = [];
                foreach ($columns as $column) {
                    $row[] = $this->sanitizeCsvCell($this->getColumnValue($log, $column));
                }
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Neutralize CSV formula injection.
     */
    private function sanitizeCsvCell($value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

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
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function exportToXml($logs, $columns, $filename)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><logs></logs>');

        foreach ($logs as $log) {
            $logElement = $xml->addChild('log');
            foreach ($columns as $column) {
                // SimpleXMLElement::addChild escapes XML entities itself —
                // do NOT call htmlspecialchars first (would double-escape).
                $logElement->addChild($column, (string) $this->getColumnValue($log, $column));
            }
        }

        return response($xml->asXML(), 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function getColumnValue($log, $column)
    {
        $value = $log->$column ?? null;

        if (is_array($value)) {
            return json_encode($value);
        }

        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}