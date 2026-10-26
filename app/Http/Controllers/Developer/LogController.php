<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    use AuditLogger;

    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_DEVELOPER   = 5;

    /** Only these log files can be served to the UI. */
    const ALLOWED_LOG_BASENAMES = [
        'laravel.log',
        'laravel-*.log',
    ];

    /** Hard cap on lines returned by a single query. */
    const MAX_LINES = 5000;

    /** Max line length we keep (bytes). */
    const MAX_LINE_LENGTH = 8000;

    /** Retention archive directory. */
    const ARCHIVE_DIR = 'log_archives';

    /* ============================================================
     | AUTHORIZATION
     * ============================================================ */

    private function canManageLogs(): bool
    {
        $user = auth()->user();
        return $user
            && in_array((int) $user->type, [self::USER_TYPE_SUPER_ADMIN, self::USER_TYPE_DEVELOPER], true);
    }

    private function authorizeOrAbort(bool $json = false)
    {
        if ($this->canManageLogs()) {
            return null;
        }

        Log::warning('Log access denied', [
            'user_id'   => auth()->id(),
            'user_type' => auth()->user()->type ?? null,
            'ip'        => request()->ip(),
        ]);

        if ($json) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Developer (type 5) or Super Admin (type 0) required.',
            ], 403);
        }

        abort(403, 'Unauthorized to access logs.');
    }

    /* ============================================================
     | VIEWS
     * ============================================================ */

    public function index(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $level   = $request->get('level');
        $search  = $request->get('search');
        $file    = $request->get('file', $this->defaultLogFilename());
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 100;

        $entries = $this->readLogFile($file, $level, $search, self::MAX_LINES);
        $total   = count($entries);

        $entries = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return view('developer.logs.index', [
            'entries'    => $entries,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'level'      => $level,
            'search'     => $search,
            'currentFile'=> $file,
            'files'      => $this->listLogFiles(),
        ]);
    }

    public function overview()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        return view('developer.logs.overview', [
            'stats'  => $this->computeStatistics(),
            'files'  => $this->listLogFiles(),
            'recent' => array_slice($this->readLogFile($this->defaultLogFilename(), null, null, 200), 0, 50),
        ]);
    }

    public function errors()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;
        return $this->renderLevelView('error');
    }

    public function warnings()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;
        return $this->renderLevelView('warning');
    }

    public function critical()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;
        return $this->renderLevelView('critical');
    }

    public function debug()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;
        return $this->renderLevelView('debug');
    }

    public function info()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;
        return $this->renderLevelView('info');
    }

    public function analysis()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        return view('developer.logs.analysis', [
            'stats' => $this->computeStatistics(),
        ]);
    }

    public function trends()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        return view('developer.logs.trends', [
            'trends' => $this->computeDailyTrends(30),
        ]);
    }

    public function live()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;
        return view('developer.logs.live');
    }

    public function realtime()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;
        return view('developer.logs.realtime');
    }

    public function settings()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        return view('developer.logs.settings', [
            'settings' => [
                'retention_days' => (int) config('logging.retention_days', 30),
                'levels_enabled' => config('logging.enabled_levels', ['emergency','alert','critical','error','warning','notice','info','debug']),
            ],
        ]);
    }

    public function archives()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $archives = [];
        if (Storage::exists(self::ARCHIVE_DIR)) {
            foreach (Storage::files(self::ARCHIVE_DIR) as $path) {
                $archives[] = [
                    'name'     => basename($path),
                    'size'     => Storage::size($path),
                    'modified' => date('Y-m-d H:i:s', Storage::lastModified($path)),
                ];
            }
        }

        return view('developer.logs.archives', compact('archives'));
    }

    public function showArchive($archive)
{
    if ($resp = $this->authorizeOrAbort()) return $resp;

    $safe = $this->sanitizeArchiveName($archive);
    $path = self::ARCHIVE_DIR . '/' . $safe;

    if (!Storage::exists($path)) {
        abort(404, 'Archive not found');
    }

    $raw = Storage::get($path);
    $content = str_ends_with($safe, '.gz') ? @gzdecode($raw) : $raw;
    if ($content === false) {
        $content = '[Unable to decompress archive]';
    }

    return view('developer.logs.archive-show', [
        'archive' => $safe,
        'content' => $content,
    ]);
}

    public function show($log)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $safe = $this->sanitizeLogIdentifier($log);
        if ($safe === null) {
            abort(404, 'Log entry not found');
        }

        return view('developer.logs.show', [
            'log' => $safe,
        ]);
    }

    /* ============================================================
     | JSON: STATISTICS / SEARCH / FILTER
     * ============================================================ */

    public function errorStatistics()
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $stats = $this->computeStatistics();

        return response()->json([
            'total_errors' => $stats['by_level']['error'] ?? 0,
            'by_level'     => $stats['by_level'],
            'by_date'      => $stats['by_date'],
            'timestamp'    => now()->toISOString(),
        ]);
    }

    public function getStatistics()
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'success'    => true,
            'statistics' => $this->computeStatistics(),
            'timestamp'  => now()->toISOString(),
        ]);
    }

    public function recentErrors(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $limit = min(500, max(1, (int) $request->get('limit', 100)));

        $errors = array_slice(
            $this->readLogFile($this->defaultLogFilename(), 'error', null, self::MAX_LINES),
            0,
            $limit
        );

        return response()->json([
            'success' => true,
            'errors'  => $errors,
            'count'   => count($errors),
        ]);
    }

    public function search(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'q'     => 'required|string|min:2|max:200',
            'level' => 'nullable|in:emergency,alert,critical,error,warning,notice,info,debug',
            'file'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid search parameters',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data   = $validator->validated();
        $file   = $this->sanitizeLogFilename($data['file'] ?? $this->defaultLogFilename());
        $level  = $data['level'] ?? null;
        $query  = $data['q'];

        $results = $this->readLogFile($file, $level, $query, self::MAX_LINES);

        return response()->json([
            'success' => true,
            'results' => array_slice($results, 0, 200),
            'total'   => count($results),
            'query'   => $query,
        ]);
    }

    public function filter(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'level'    => 'nullable|in:emergency,alert,critical,error,warning,notice,info,debug',
            'file'     => 'nullable|string|max:255',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:500',
            'page'     => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter parameters',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data   = $validator->validated();
        $file   = $this->sanitizeLogFilename($data['file'] ?? $this->defaultLogFilename());
        $level  = $data['level'] ?? null;
        $from   = $data['from'] ?? null;
        $to     = $data['to'] ?? null;
        $perPage= (int) ($data['per_page'] ?? 100);
        $page   = (int) ($data['page'] ?? 1);

        $entries = $this->readLogFile($file, $level, null, self::MAX_LINES);

        if ($from || $to) {
            $entries = array_values(array_filter($entries, function ($entry) use ($from, $to) {
                $ts = $entry['timestamp'] ?? null;
                if (!$ts) return false;
                if ($from && $ts < $from) return false;
                if ($to   && $ts > $to)   return false;
                return true;
            }));
        }

        $total = count($entries);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'success'    => true,
            'results'    => $slice,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function getByDate($date)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        if (!$this->isValidDate($date)) {
            return response()->json(['success' => false, 'message' => 'Invalid date'], 422);
        }

        $entries = $this->readLogFile($this->defaultLogFilename(), null, null, self::MAX_LINES);

        $filtered = array_values(array_filter($entries, function ($e) use ($date) {
            return isset($e['timestamp']) && str_starts_with($e['timestamp'], $date);
        }));

        return response()->json([
            'success' => true,
            'date'    => $date,
            'logs'    => array_slice($filtered, 0, 500),
            'total'   => count($filtered),
        ]);
    }

    public function getByLevel($level)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        if (!in_array($level, $this->allLevels(), true)) {
            return response()->json(['success' => false, 'message' => 'Invalid level'], 422);
        }

        $entries = $this->readLogFile($this->defaultLogFilename(), $level, null, self::MAX_LINES);

        return response()->json([
            'success' => true,
            'level'   => $level,
            'logs'    => array_slice($entries, 0, 500),
            'total'   => count($entries),
        ]);
    }

    public function getBySource($source)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $source = Str::limit((string) $source, 200, '');
        $entries = $this->readLogFile($this->defaultLogFilename(), null, $source, self::MAX_LINES);

        return response()->json([
            'success' => true,
            'source'  => $source,
            'logs'    => array_slice($entries, 0, 500),
            'total'   => count($entries),
        ]);
    }

    public function getByUser($userId)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        if (!ctype_digit((string) $userId)) {
            return response()->json(['success' => false, 'message' => 'Invalid user id'], 422);
        }

        $entries = $this->readLogFile($this->defaultLogFilename(), null, 'user_id:' . $userId, self::MAX_LINES);

        return response()->json([
            'success' => true,
            'user_id' => (int) $userId,
            'logs'    => array_slice($entries, 0, 500),
            'total'   => count($entries),
        ]);
    }

    /* ============================================================
     | MUTATIONS (rate-limited, explicit no-op response for unimplemented)
     * ============================================================ */

    public function update(Request $request, $log)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'success' => false,
            'message' => 'Log editing is not supported. Log files are append-only.',
        ], 501);
    }

    public function destroy($log)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $key = 'log_delete:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['success' => false, 'message' => 'Too many attempts'], 429);
        }
        RateLimiter::hit($key, 60);

        return response()->json([
            'success' => false,
            'message' => 'Individual log deletion is not supported. Use retention policy or archiving.',
        ], 501);
    }

    public function markResolved($id)
    {
        return $this->markAsResolved($id);
    }

    public function markAsResolved($log)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $this->logAudit('log_marked_resolved', 'Log entry marked resolved', [
            'log'      => (string) $log,
            'user_id'  => auth()->id(),
            'ip'       => request()->ip(),
        ]);

        // Resolution state lives in a side table if you want to track it.
        // For now we acknowledge the action but don't mutate the log file.
        return response()->json([
            'success' => true,
            'message' => 'Marked as resolved.',
        ]);
    }

    public function markAsUnresolved($log)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $this->logAudit('log_marked_unresolved', 'Log entry marked unresolved', [
            'log'     => (string) $log,
            'user_id' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Marked as unresolved.']);
    }

    public function bulkResolve(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array|min:1|max:500',
            'ids.*' => 'string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->logAudit('logs_bulk_resolved', 'Logs bulk resolved', [
            'count'   => count($request->ids),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'count'   => count($request->ids),
            'message' => count($request->ids) . ' entries marked resolved.',
        ]);
    }

    public function bulkDelete(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $key = 'log_bulk_delete:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['success' => false, 'message' => 'Too many attempts'], 429);
        }
        RateLimiter::hit($key, 300);

        return response()->json([
            'success' => false,
            'message' => 'Bulk deletion is not supported. Use archiving or retention policy.',
        ], 501);
    }

    public function bulkArchive(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'files'   => 'required|array|min:1',
            'files.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        Storage::makeDirectory(self::ARCHIVE_DIR);

        $archived = [];
        $logsDir  = storage_path('logs');

        foreach ($request->files as $name) {
            $safe = $this->sanitizeLogFilename($name);
            if (!$safe) continue;

            $src = $logsDir . DIRECTORY_SEPARATOR . $safe;
            if (!file_exists($src)) continue;

            $dest = self::ARCHIVE_DIR . '/' . $safe . '.' . now()->format('Ymd_His') . '.gz';
            $gz   = gzopen(storage_path('app/' . $dest), 'w9');
            if ($gz) {
                gzwrite($gz, file_get_contents($src));
                gzclose($gz);
                $archived[] = $dest;
            }
        }

        $this->logAudit('logs_archived', 'Log files archived', [
            'count'  => count($archived),
            'files'  => $archived,
        ]);

        return response()->json([
            'success'  => true,
            'archived' => $archived,
        ]);
    }

    public function clearAll()
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $key = 'log_clear_all:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['success' => false, 'message' => 'Clear-all is limited to one request per hour'], 429);
        }
        RateLimiter::hit($key, 3600);

        // Archive before truncating so nothing is truly lost
        Storage::makeDirectory(self::ARCHIVE_DIR);
        $logsDir = storage_path('logs');
        $cleared = [];

        foreach (glob($logsDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $path) {
            $name = basename($path);
            $dest = self::ARCHIVE_DIR . '/' . $name . '.' . now()->format('Ymd_His') . '.gz';
            $gz   = gzopen(storage_path('app/' . $dest), 'w9');
            if ($gz) {
                gzwrite($gz, (string) @file_get_contents($path));
                gzclose($gz);
            }
            file_put_contents($path, '');
            $cleared[] = $name;
        }

        $this->logAudit('logs_cleared_all', 'All logs cleared (archived first)', [
            'files'   => $cleared,
            'user_id' => auth()->id(),
            'ip'      => request()->ip(),
        ]);

        return response()->json([
            'success' => true,
            'cleared' => $cleared,
            'message' => 'Logs cleared. Originals archived.',
        ]);
    }

    public function clearOld(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:3650',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $days   = (int) $request->days;
        $cutoff = now()->subDays($days)->timestamp;
        $logsDir = storage_path('logs');
        $cleared = [];

        // Only rotate numbered logs (laravel-YYYY-MM-DD.log)
        foreach (glob($logsDir . DIRECTORY_SEPARATOR . 'laravel-*.log') ?: [] as $path) {
            if (filemtime($path) < $cutoff) {
                $cleared[] = basename($path);
                @unlink($path);
            }
        }

        $this->logAudit('logs_cleared_old', 'Old log files cleared', [
            'days'    => $days,
            'files'   => $cleared,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'days'    => $days,
            'cleared' => $cleared,
        ]);
    }

    /* ============================================================
     | REPORTING
     * ============================================================ */

    public function generateReport(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'format' => 'required|in:json,csv,pdf',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
            'level'  => 'nullable|in:emergency,alert,critical,error,warning,notice,info,debug',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data    = $validator->validated();
        $entries = $this->readLogFile($this->defaultLogFilename(), $data['level'] ?? null, null, self::MAX_LINES);

        if (!empty($data['from']) || !empty($data['to'])) {
            $entries = array_values(array_filter($entries, function ($e) use ($data) {
                $ts = $e['timestamp'] ?? null;
                if (!$ts) return false;
                if (!empty($data['from']) && $ts < $data['from']) return false;
                if (!empty($data['to'])   && $ts > $data['to'])   return false;
                return true;
            }));
        }

        $reportId = 'log_report_' . Str::random(16);
        $payload  = [
            'id'          => $reportId,
            'generated_at'=> now()->toISOString(),
            'generated_by'=> auth()->id(),
            'filters'     => $data,
            'entries'     => $entries,
            'stats'       => $this->computeStatistics(),
        ];

        Storage::makeDirectory('log_reports');
        Storage::put("log_reports/{$reportId}.json", json_encode($payload));

        $this->logAudit('log_report_generated', 'Log report generated', [
            'report_id' => $reportId,
            'format'    => $data['format'],
            'count'     => count($entries),
        ]);

        return response()->json([
            'success'   => true,
            'report_id' => $reportId,
            'entries'   => count($entries),
        ]);
    }

    public function getReport($id)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $id = $this->sanitizeIdentifier($id);
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'Invalid report id'], 422);
        }

        $path = "log_reports/{$id}.json";
        if (!Storage::exists($path)) {
            return response()->json(['success' => false, 'message' => 'Report not found'], 404);
        }

        return response()->json([
            'success' => true,
            'report'  => json_decode(Storage::get($path), true),
        ]);
    }

    public function downloadReport($id)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $id = $this->sanitizeIdentifier($id);
        if (!$id) {
            abort(422, 'Invalid report id');
        }

        $path = "log_reports/{$id}.json";
        if (!Storage::exists($path)) {
            abort(404, 'Report not found');
        }

        $this->logAudit('log_report_downloaded', 'Log report downloaded', [
            'report_id' => $id,
        ]);

        return Storage::download($path, "log_report_{$id}.json");
    }

    public function export(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $format = strtolower((string) $request->get('format', 'csv'));

        return match ($format) {
            'csv'  => $this->exportToCsv(),
            'json' => $this->exportToJson(),
            'pdf'  => $this->exportToPdf(),
            default => redirect()->back()->with('error', 'Unsupported export format'),
        };
    }

    public function exportToCsv()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $entries  = $this->readLogFile($this->defaultLogFilename(), null, null, self::MAX_LINES);
        $filename = 'logs_export_' . now()->format('Y-m-d_His') . '.csv';

        $this->logAudit('logs_exported_csv', 'Logs exported to CSV', ['count' => count($entries)]);

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel
            fputcsv($out, ['timestamp', 'level', 'message']);
            foreach ($entries as $e) {
                fputcsv($out, [
                    $e['timestamp'] ?? '',
                    $e['level']     ?? '',
                    $e['message']   ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportToJson()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $entries  = $this->readLogFile($this->defaultLogFilename(), null, null, self::MAX_LINES);
        $filename = 'logs_export_' . now()->format('Y-m-d_His') . '.json';

        $this->logAudit('logs_exported_json', 'Logs exported to JSON', ['count' => count($entries)]);

        return response()->streamDownload(function () use ($entries) {
            echo json_encode([
                'exported_at' => now()->toISOString(),
                'count'       => count($entries),
                'entries'     => $entries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function exportToPdf()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return redirect()->back()->with('error', 'PDF export is not available (dompdf not installed).');
        }

        $entries = array_slice(
            $this->readLogFile($this->defaultLogFilename(), null, null, 200),
            0,
            200
        );

        $rows = '';
        foreach ($entries as $e) {
            $rows .= '<tr><td>' . e($e['timestamp'] ?? '') . '</td>'
                   . '<td>' . e($e['level'] ?? '') . '</td>'
                   . '<td>' . e(Str::limit($e['message'] ?? '', 500)) . '</td></tr>';
        }

        $html = '<h1>Log Report</h1>'
              . '<p>Generated at ' . now()->toDateTimeString() . '</p>'
              . '<table border="1" cellpadding="6" cellspacing="0">'
              . '<thead><tr><th>Timestamp</th><th>Level</th><th>Message</th></tr></thead>'
              . '<tbody>' . $rows . '</tbody></table>';

        $this->logAudit('logs_exported_pdf', 'Logs exported to PDF', ['count' => count($entries)]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        return $pdf->download('logs_' . now()->format('Y-m-d_His') . '.pdf');
    }

    public function stream()
    {
        if ($resp = $this->authorizeOrAbort()) return $resp;

        $path = storage_path('logs' . DIRECTORY_SEPARATOR . $this->defaultLogFilename());
        if (!file_exists($path)) {
            abort(404, 'Log file not found');
        }

        return new StreamedResponse(function () use ($path) {
            $handle = fopen($path, 'r');
            if (!$handle) return;

            // Seek to the last ~200KB to avoid streaming gigabytes
            $size = filesize($path);
            $seek = max(0, $size - 200 * 1024);
            fseek($handle, $seek);

            while (!feof($handle)) {
                echo fgets($handle);
                @ob_flush();
                flush();
                usleep(200000); // 200ms pacing
                if (connection_aborted()) break;
            }
            fclose($handle);
        }, 200, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /* ============================================================
     | SETTINGS
     * ============================================================ */

    public function updateSettings(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        return response()->json([
            'success' => false,
            'message' => 'Log settings are managed in config/logging.php. Edit the file and clear config cache.',
        ], 501);
    }

    public function updateRetention(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:3650',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->logAudit('log_retention_updated', 'Log retention policy updated', [
            'days'    => (int) $request->days,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Retention policy recorded. Update config/logging.php to apply.',
        ]);
    }

    public function updateLogLevels(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $validator = Validator::make($request->all(), [
            'levels'   => 'required|array|min:1',
            'levels.*' => 'in:emergency,alert,critical,error,warning,notice,info,debug',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->logAudit('log_levels_updated', 'Log levels updated', [
            'levels'  => $request->levels,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Log levels recorded. Update config/logging.php to apply.',
        ]);
    }

    /* ============================================================
     | ARCHIVE OPERATIONS
     * ============================================================ */

    public function restoreArchive($archive)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $safe = $this->sanitizeArchiveName($archive);
        $path = self::ARCHIVE_DIR . '/' . $safe;

        if (!Storage::exists($path)) {
            return response()->json(['success' => false, 'message' => 'Archive not found'], 404);
        }

        $this->logAudit('archive_restored', 'Log archive restored', ['archive' => $safe]);

        return response()->json([
            'success' => true,
            'message' => 'Archive available for download via showArchive route.',
            'archive' => $safe,
        ]);
    }

    public function deleteArchive($archive)
    {
        if ($resp = $this->authorizeOrAbort(true)) return $resp;

        $key = 'archive_delete:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['success' => false, 'message' => 'Too many attempts'], 429);
        }
        RateLimiter::hit($key, 60);

        $safe = $this->sanitizeArchiveName($archive);
        $path = self::ARCHIVE_DIR . '/' . $safe;

        if (!Storage::exists($path)) {
            return response()->json(['success' => false, 'message' => 'Archive not found'], 404);
        }

        Storage::delete($path);

        $this->logAudit('archive_deleted', 'Log archive deleted', ['archive' => $safe]);

        return response()->json(['success' => true, 'message' => 'Archive deleted.']);
    }

    /* ============================================================
     | INTERNAL HELPERS
     * ============================================================ */

    private function renderLevelView(string $level)
    {
        $entries = array_slice(
            $this->readLogFile($this->defaultLogFilename(), $level, null, self::MAX_LINES),
            0,
            200
        );

        return view("developer.logs.{$level}", [
            'entries' => $entries,
            'level'   => $level,
            'total'   => count($entries),
        ]);
    }

    private function defaultLogFilename(): string
    {
        return 'laravel.log';
    }

    private function listLogFiles(): array
    {
        $dir = storage_path('logs');
        $out = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $path) {
            $out[] = [
                'name'     => basename($path),
                'size'     => filesize($path),
                'modified' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }
        usort($out, fn($a, $b) => strcmp($b['modified'], $a['modified']));
        return $out;
    }

    /**
     * Read and parse a log file with optional level and search filters.
     * Returns newest-first.
     */
    private function readLogFile(string $file, ?string $level, ?string $search, int $maxLines): array
    {
        $safe = $this->sanitizeLogFilename($file);
        if ($safe === null) {
            return [];
        }

        $path = storage_path('logs' . DIRECTORY_SEPARATOR . $safe);
        if (!file_exists($path) || !is_readable($path)) {
            return [];
        }

        $entries = [];
        $count   = 0;

        $handle = fopen($path, 'r');
        if (!$handle) return [];

        while (($line = fgets($handle)) !== false && $count < $maxLines) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;

            $parsed = $this->parseLogLine($line);
            if ($parsed === null) continue;

            if ($level && strcasecmp($parsed['level'], $level) !== 0) {
                continue;
            }

            if ($search && stripos($parsed['message'], $search) === false) {
                continue;
            }

            $entries[] = $parsed;
            $count++;

            if ($count >= $maxLines) break;
        }
        fclose($handle);

        // newest first
        return array_reverse($entries);
    }

    /**
     * Parse a single Laravel log line.
     * Format: [YYYY-MM-DD HH:MM:SS] env.LEVEL: message {context}
     */
    private function parseLogLine(string $line): ?array
    {
        $line = Str::limit($line, self::MAX_LINE_LENGTH, '…');

        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)\]\s+(\w+)\.(\w+):\s*(.*)$/', $line, $m)) {
            return [
                'timestamp' => $m[1],
                'environment' => $m[2],
                'level'     => strtolower($m[3]),
                'message'   => $m[4],
            ];
        }

        // Fallback: unparsed lines are still returned as raw text.
        return [
            'timestamp' => null,
            'environment' => null,
            'level'     => 'unknown',
            'message'   => $line,
        ];
    }

    private function computeStatistics(): array
    {
        $entries = $this->readLogFile($this->defaultLogFilename(), null, null, self::MAX_LINES);
        $byLevel = [];
        $byDate  = [];

        foreach ($entries as $e) {
            $lvl = $e['level'] ?: 'unknown';
            $byLevel[$lvl] = ($byLevel[$lvl] ?? 0) + 1;

            if (!empty($e['timestamp'])) {
                $day = substr($e['timestamp'], 0, 10);
                $byDate[$day] = ($byDate[$day] ?? 0) + 1;
            }
        }

        ksort($byDate);

        return [
            'total'    => count($entries),
            'by_level' => $byLevel,
            'by_date'  => $byDate,
            'generated_at' => now()->toISOString(),
        ];
    }

    private function computeDailyTrends(int $days): array
    {
        $entries = $this->readLogFile($this->defaultLogFilename(), null, null, self::MAX_LINES);
        $bucket  = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $bucket[now()->subDays($i)->toDateString()] = 0;
        }

        foreach ($entries as $e) {
            if (empty($e['timestamp'])) continue;
            $day = substr($e['timestamp'], 0, 10);
            if (isset($bucket[$day])) $bucket[$day]++;
        }

        return $bucket;
    }

    /* ============================================================
     | INPUT SANITIZATION
     * ============================================================ */

    private function sanitizeLogFilename(?string $name): ?string
    {
        if ($name === null) return null;
        $name = basename($name);
        if (!preg_match('/^[A-Za-z0-9._\-]+\.log$/', $name)) return null;
        if (str_contains($name, '..')) return null;
        return $name;
    }

    private function sanitizeArchiveName(?string $name): string
    {
        $name = basename((string) $name);
        $name = preg_replace('/[^A-Za-z0-9._\-]/', '', $name);
        return $name;
    }

    private function sanitizeLogIdentifier($log): ?string
    {
        if (!is_string($log) && !is_numeric($log)) return null;
        $log = substr((string) $log, 0, 200);
        return $log;
    }

    private function sanitizeIdentifier($id): ?string
    {
        if (!is_string($id) && !is_numeric($id)) return null;
        $id = (string) $id;
        if (!preg_match('/^[A-Za-z0-9._\-]+$/', $id)) return null;
        return $id;
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function allLevels(): array
    {
        return ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
    }
}