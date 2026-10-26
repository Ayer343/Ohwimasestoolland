<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\BackupRestoreCodeNotification;
use App\Services\DeveloperMonitoringService;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DeveloperBackupController extends Controller
{
    use AuditLogger;

    /* ============================================================
     | CONSTANTS
     * ============================================================ */

    /** Authorized user types — referenced from User so there's one source of truth. */
    public const AUTHORIZED_USER_TYPES = [
        User::TYPE_SUPER_ADMIN,
        User::TYPE_DEVELOPER,
    ];

    // Cache keys
    public const BACKUP_LIST_CACHE_KEY  = 'developer_backup_list';
    public const BACKUP_STATS_CACHE_KEY = 'developer_backup_stats';
    public const BACKUP_JOB_STATUS_KEY  = 'developer_backup_job_';
    public const VERIFICATION_CODE_KEY  = 'restore_verification_';
    public const MAINTENANCE_SECRET_KEY = 'developer_maintenance_secret';
    public const RESTORE_LOCK_KEY       = 'developer_backup_restore_lock';

    // Cache TTLs (seconds)
    public const CACHE_TTL_BACKUP_LIST  = 300;   // 5 minutes
    public const CACHE_TTL_BACKUP_STATS = 600;   // 10 minutes
    public const CACHE_TTL_JOB_STATUS   = 1800;  // 30 minutes
    public const CACHE_TTL_VERIFICATION = 900;   // 15 minutes
    public const CACHE_TTL_RESTORE_LOCK = 1800;  // 30 minutes

    // Rate limits
    public const RATE_LIMIT_BACKUP_CREATE_PER_HOUR = 2;

    // Restore code config
    public const RESTORE_CODE_LENGTH         = 6;
    public const RESTORE_CODE_EXPIRY_MINUTES = 15;

    // Drivers that support Cache::tags() — checked via method_exists at runtime,
    // this list is only kept for documentation purposes.
    public const CACHE_TAG_SUPPORTED_DRIVERS = ['redis', 'memcached', 'array', 'dynamodb'];

    protected DeveloperMonitoringService $monitoringService;

    public function __construct(DeveloperMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /* ============================================================
     | AUTHORIZATION
     * ============================================================ */

    private function canAccessBackup(): bool
    {
        $user = auth()->user();

        return $user
            && in_array((int) $user->type, self::AUTHORIZED_USER_TYPES, true);
    }

    private function authorizeOrAbort(bool $json = true)
    {
        if ($this->canAccessBackup()) {
            return null;
        }

        Log::warning('Backup access denied — invalid user type', [
            'user_id'         => auth()->id(),
            'user_type'       => auth()->user()->type ?? null,
            'expected_types'  => self::AUTHORIZED_USER_TYPES,
            'ip'              => request()->ip(),
            'path'            => request()->path(),
        ]);

        if ($json) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Developer or Super Admin access required.',
            ], 403);
        }

        abort(403, 'Unauthorized.');
    }

    /* ============================================================
     | CREATE
     * ============================================================ */

    /**
     * Create a manual backup.
     * Rate-limited to 2 per hour per user.
     */
    public function createBackup()
    {
        // 1. Authorization first — never let an unauthorized request consume
        //    the rate limiter budget for a legitimate user.
        if ($resp = $this->authorizeOrAbort(false)) {
            return $resp;
        }

        // 2. Rate limit
        $key = 'backup_create:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_BACKUP_CREATE_PER_HOUR)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = (int) ceil($seconds / 60);

            return redirect()->back()
                ->with('error', "Too many backup requests. Try again in {$minutes} minute(s).");
        }
        RateLimiter::hit($key, 3600);

        try {
            $backupResult = $this->monitoringService->createBackup('manual');

            if (empty($backupResult['success'])) {
                throw new \RuntimeException($backupResult['message'] ?? 'Backup failed');
            }

            $this->clearBackupCache();

            $this->logAudit('backup_created', 'Manual backup created', [
                'user_type'    => auth()->user()->type,
                'backup_file'  => $backupResult['filename'] ?? null,
                'size'         => $backupResult['size'] ?? null,
                'ip_address'   => request()->ip(),
                'cache_cleared'=> true,
            ]);

            $this->notifySuperAdminsAboutBackup('created', $backupResult['filename'] ?? 'unknown');

            return redirect()
                ->route('developer.settings.index')
                ->with('success', 'Backup created successfully.')
                ->with('backup_info', $backupResult);

        } catch (\Throwable $e) {
            Log::error('Failed to create backup', [
                'user_id'   => auth()->id(),
                'user_type' => auth()->user()->type ?? null,
                'error'     => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create backup: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | LIST
     * ============================================================ */

    public function listBackups()
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $cacheKey = $this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY);
            $cached   = Cache::has($cacheKey);

            $backups = $this->getCachedBackupList();

            $this->logAudit('backups_listed', 'Backup list viewed', [
                'user_type'    => auth()->user()->type,
                'backup_count' => count($backups),
                'cache_hit'    => $cached,
            ]);

            return response()->json([
                'success'          => true,
                'backups'          => $backups,
                'count'            => count($backups),
                'timestamp'        => now()->toISOString(),
                'cached'           => $cached,
                'cache_expires_at' => Cache::get($cacheKey . '_expiry'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to list backups', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list backups: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | RESTORE
     * ============================================================ */

    /**
     * Restore the system from a backup.
     *
     * Workflow:
     *   1. Authorize
     *   2. Validate input (filename, confirm checkbox, verification code)
     *   3. Acquire a lock so two restores can't run concurrently
     *   4. Enter maintenance mode with a secret escape hatch
     *   5. Restore
     *   6. Clear caches, log, notify
     *   7. Always release lock and clean up secrets
     */
    public function restoreBackup(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(false)) {
            return $resp;
        }

        $userId = auth()->id();

        $validator = Validator::make($request->all(), [
            'backup_filename'   => 'required|string',
            'confirm_restore'   => 'required|accepted',
            'verification_code' => [
                'required',
                'string',
                'size:' . self::RESTORE_CODE_LENGTH,
                function ($attribute, $value, $fail) use ($userId) {
                    $stored = Cache::get($this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . $userId));
                    if (!$stored || !hash_equals((string) $stored, (string) $value)) {
                        $fail('Invalid or expired verification code.');
                    }
                },
            ],
        ], [
            'confirm_restore.required'   => 'You must confirm the restore operation.',
            'confirm_restore.accepted'   => 'You must confirm the restore operation.',
            'verification_code.required' => 'Verification code is required.',
            'verification_code.size'     => 'Verification code must be ' . self::RESTORE_CODE_LENGTH . ' characters.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please confirm the restore operation.');
        }

        // Prevent concurrent restores
        $lock = Cache::lock(self::RESTORE_LOCK_KEY, self::CACHE_TTL_RESTORE_LOCK);

        if (!$lock->get()) {
            return redirect()->back()
                ->with('error', 'A restore is already in progress. Please wait for it to finish.');
        }

        $data              = $validator->validated();
        $maintenanceSecret = null;
        $appWentDown       = false;

        try {
            // Maintenance mode escape hatch
            $maintenanceSecret = Str::random(32);
            Cache::put(self::MAINTENANCE_SECRET_KEY, $maintenanceSecret, now()->addHours(2));

            try {
                Artisan::call('down', [
                    'message' => 'System restore in progress',
                    'retry'   => 60,
                    'secret'  => $maintenanceSecret,
                ]);
                $appWentDown = true;
            } catch (\Throwable $e) {
                // If we can't put the app in maintenance mode, abort the restore
                Log::error('Failed to enter maintenance mode — aborting restore', [
                    'error' => $e->getMessage(),
                ]);

                Cache::forget(self::MAINTENANCE_SECRET_KEY);

                return redirect()->back()
                    ->with('error', 'Could not put the application into maintenance mode. Restore aborted.');
            }

            $restoreResult = $this->monitoringService->restoreBackup($data['backup_filename']);

            if (empty($restoreResult['success'])) {
                throw new \RuntimeException($restoreResult['message'] ?? 'Restore failed');
            }

            // Post-restore cleanup
            $this->clearBackupCache();
            Cache::flush();
            Cache::forget(self::MAINTENANCE_SECRET_KEY);
            Cache::forget($this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . $userId));

            $this->logAudit('backup_restored', 'System restored from backup', [
                'user_type'    => auth()->user()->type,
                'backup_file'  => $data['backup_filename'],
                'ip_address'   => request()->ip(),
                'user_agent'   => request()->userAgent(),
                'cache_cleared'=> true,
            ]);

            $this->notifySuperAdminsAboutBackup('restored', $data['backup_filename']);

            return redirect()
                ->route('developer.index')
                ->with('success', 'System restored successfully. The system is back online.')
                ->with('warning', 'Please verify all system functionality after restore.');

        } catch (\Throwable $e) {
            Log::error('Failed to restore backup', [
                'user_id'      => $userId,
                'backup_file'  => $data['backup_filename'] ?? null,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to restore backup: ' . $e->getMessage())
                ->withInput();

        } finally {
            // Always bring the app back online if we took it down
            if ($appWentDown) {
                try {
                    Artisan::call('up');
                } catch (\Throwable $e) {
                    Log::critical('Failed to bring application back online after restore', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Invalidate the verification code on both success and failure
            Cache::forget($this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . $userId));
            Cache::forget(self::MAINTENANCE_SECRET_KEY);

            // Release the lock
            optional($lock)->release();
        }
    }

    /* ============================================================
     | DOWNLOAD / DELETE / VERIFY
     * ============================================================ */

    public function downloadBackup(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filename provided.',
            ], 400);
        }

        try {
            $filename = $validator->validated()['filename'];

            // Belt-and-braces: reject anything that looks like traversal
            if ($this->looksLikePathTraversal($filename)) {
                Log::warning('Backup download rejected — suspicious filename', [
                    'user_id'  => auth()->id(),
                    'filename' => $filename,
                    'ip'       => request()->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename provided.',
                ], 400);
            }

            $backupPath = $this->monitoringService->getBackupPath($filename);

            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found.',
                ], 404);
            }

            $this->logAudit('backup_downloaded', 'Backup file downloaded', [
                'user_type'  => auth()->user()->type,
                'filename'   => $filename,
                'ip_address' => request()->ip(),
            ]);

            return response()->download($backupPath, $filename);

        } catch (\Throwable $e) {
            Log::error('Failed to download backup', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteBackup(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filename provided.',
            ], 400);
        }

        try {
            $filename = $validator->validated()['filename'];

            if ($this->looksLikePathTraversal($filename)) {
                Log::warning('Backup delete rejected — suspicious filename', [
                    'user_id'  => auth()->id(),
                    'filename' => $filename,
                    'ip'       => request()->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename provided.',
                ], 400);
            }

            $deleteResult = $this->monitoringService->deleteBackup($filename);

            if (empty($deleteResult['success'])) {
                throw new \RuntimeException($deleteResult['message'] ?? 'Delete failed');
            }

            $this->clearBackupCache();

            $this->logAudit('backup_deleted', 'Backup file deleted', [
                'user_type'    => auth()->user()->type,
                'filename'     => $filename,
                'ip_address'   => request()->ip(),
                'cache_cleared'=> true,
            ]);

            return response()->json([
                'success'           => true,
                'message'           => 'Backup deleted successfully.',
                'timestamp'         => now()->toISOString(),
                'cache_invalidated' => true,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to delete backup', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyBackup(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filename provided.',
            ], 400);
        }

        try {
            $filename = $validator->validated()['filename'];

            if ($this->looksLikePathTraversal($filename)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename provided.',
                ], 400);
            }

            $cacheKey = $this->getBackupCacheKey('verify_' . md5($filename));
            $cached   = Cache::has($cacheKey);

            $verifyResult = Cache::remember(
                $cacheKey,
                1800,
                fn () => $this->monitoringService->verifyBackup($filename)
            );

            $this->logAudit('backup_verified', 'Backup integrity verified', [
                'user_type'           => auth()->user()->type,
                'filename'            => $filename,
                'verification_result' => !empty($verifyResult['success']) ? 'passed' : 'failed',
                'ip_address'          => request()->ip(),
                'cache_hit'           => $cached,
            ]);

            return response()->json(array_merge($verifyResult, [
                'cached'           => $cached,
                'cache_expires_at' => now()->addSeconds(1800)->toISOString(),
            ]));

        } catch (\Throwable $e) {
            Log::error('Failed to verify backup', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | VIEWS / STATS
     * ============================================================ */

    public function backupHistory()
    {
        if ($resp = $this->authorizeOrAbort(false)) {
            return $resp;
        }

        try {
            $backups = $this->getCachedBackupList();
            $stats   = $this->getCachedBackupStats();

            $totalSize = 0;
            foreach ($backups as $b) {
                $totalSize += is_array($b)
                    ? (int) ($b['size'] ?? 0)
                    : (int) ($b->size ?? 0);
            }

            $this->logAudit('backup_history_viewed', 'Backup history viewed', [
                'user_type'  => auth()->user()->type,
                'backup_count' => count($backups),
            ]);

            return view('developer.backup.history', [
                'backups'          => $backups,
                'total_backups'    => count($backups),
                'total_size'       => $totalSize,
                'latest_backup'    => $backups[0] ?? null,
                'stats'            => $stats,
                'cache_info'       => [
                    'backup_list_expires'  => Cache::get($this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY) . '_expiry'),
                    'backup_stats_expires' => Cache::get($this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY) . '_expiry'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load backup history', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to load backup history: ' . $e->getMessage());
        }
    }

    public function getBackupStats()
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $stats = $this->getCachedBackupStats();

            return response()->json([
                'success'          => true,
                'stats'            => $stats,
                'timestamp'        => now()->toISOString(),
                'cached'           => true,
                'cache_expires_at' => Cache::get($this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY) . '_expiry'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get backup stats', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get backup statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getBackupJobStatus(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'job_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid job ID provided.',
            ], 400);
        }

        try {
            $jobId = $validator->validated()['job_id'];

            $jobStatus = Cache::remember(
                $this->getBackupCacheKey(self::BACKUP_JOB_STATUS_KEY . $jobId),
                self::CACHE_TTL_JOB_STATUS,
                fn () => $this->monitoringService->getJobStatus($jobId)
            );

            return response()->json([
                'success'    => true,
                'job_status' => $jobStatus,
                'timestamp'  => now()->toISOString(),
                'cached'     => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get backup job status', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get backup job status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | VERIFICATION CODE
     * ============================================================ */

    /**
     * Generate a restore verification code and email it to the user.
     * The code is NEVER returned in the HTTP response.
     */
    public function generateVerificationCode()
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            // 6-digit numeric code
            $verificationCode = str_pad(
                (string) random_int(0, 999999),
                self::RESTORE_CODE_LENGTH,
                '0',
                STR_PAD_LEFT
            );

            $expiresAt = now()->addMinutes(self::RESTORE_CODE_EXPIRY_MINUTES);

            $cacheKey = $this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . auth()->id());
            Cache::put($cacheKey, $verificationCode, self::CACHE_TTL_VERIFICATION);

            // Send by email — do NOT return in response
            try {
                auth()->user()->notify(
                    new BackupRestoreCodeNotification(
                        $verificationCode,
                        self::RESTORE_CODE_EXPIRY_MINUTES
                    )
                );
            } catch (\Throwable $e) {
                // If email fails, invalidate the code so it can't be used unknowingly
                Cache::forget($cacheKey);

                Log::error('Failed to send backup restore verification code', [
                    'user_id' => auth()->id(),
                    'error'   => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Could not send verification code. Please try again later.',
                ], 500);
            }

            $this->logAudit('verification_code_generated', 'Restore verification code generated', [
                'user_type'  => auth()->user()->type,
                'expires_at' => $expiresAt->toISOString(),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success'    => true,
                'message'    => 'Verification code sent to your email. It expires in '
                                . self::RESTORE_CODE_EXPIRY_MINUTES . ' minutes.',
                'expires_at' => $expiresAt->toISOString(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to generate verification code', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate verification code: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | CACHE MANAGEMENT
     * ============================================================ */

    public function clearBackupCache(): bool
    {
        try {
            $userId = auth()->id() ?? 'guest';

            $cacheKeys = [
                $this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY),
                $this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY),
                $this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY) . '_expiry',
                $this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY) . '_expiry',
                $this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . $userId),
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            // Tag flush — only if the store supports tags
            try {
                if (method_exists(Cache::store(), 'tags')) {
                    Cache::tags(['backup_verify'])->flush();
                }
            } catch (\Throwable $e) {
                Log::debug('Cache tag flush skipped', ['error' => $e->getMessage()]);
            }

            Log::info('Backup cache cleared', [
                'user_id'            => auth()->id(),
                'user_type'          => auth()->user()->type ?? null,
                'cache_keys_cleared' => $cacheKeys,
                'driver'             => config('cache.default'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to clear backup cache', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function refreshBackupCache(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            if ($request->boolean('force', false)) {
                $this->clearBackupCache();
            }

            $backups = $this->getCachedBackupList(true);
            $stats   = $this->getCachedBackupStats(true);

            return response()->json([
                'success'                 => true,
                'message'                 => 'Backup cache refreshed successfully.',
                'backup_count'            => count($backups),
                'cache_entries_refreshed' => 2,
                'timestamp'               => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to refresh backup cache', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh backup cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function getCachedBackupList(bool $forceRefresh = false): array
    {
        $cacheKey = $this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_expiry');
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_BACKUP_LIST, function () use ($cacheKey) {
            $backups = $this->monitoringService->listBackups();

            Cache::put(
                $cacheKey . '_expiry',
                now()->addSeconds(self::CACHE_TTL_BACKUP_LIST)->toISOString(),
                self::CACHE_TTL_BACKUP_LIST + 60
            );

            return $backups;
        });
    }

    private function getCachedBackupStats(bool $forceRefresh = false): array
    {
        $cacheKey = $this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_expiry');
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_BACKUP_STATS, function () use ($cacheKey) {
            $backups = $this->monitoringService->listBackups();

            $totalSize   = 0;
            $dates       = [];

            foreach ($backups as $b) {
                $totalSize += is_array($b) ? (int) ($b['size'] ?? 0) : (int) ($b->size ?? 0);
                $date       = is_array($b) ? ($b['date'] ?? null) : ($b->date ?? null);
                if ($date) $dates[] = $date;
            }

            $count = count($backups);

            $stats = [
                'total_backups' => $count,
                'total_size'    => $totalSize,
                'average_size'  => $count > 0 ? round($totalSize / $count, 2) : 0,
                'oldest_backup' => !empty($dates) ? min($dates) : null,
                'newest_backup' => !empty($dates) ? max($dates) : null,
                'disk_usage'    => $this->monitoringService->getDiskUsagePercentage(),
                'backup_status' => $this->monitoringService->checkBackupConfiguration(),
            ];

            Cache::put(
                $cacheKey . '_expiry',
                now()->addSeconds(self::CACHE_TTL_BACKUP_STATS)->toISOString(),
                self::CACHE_TTL_BACKUP_STATS + 60
            );

            return $stats;
        });
    }

    private function getBackupCacheKey(string $baseKey): string
    {
        $userId = auth()->id() ?? 'guest';
        $env    = config('app.env', 'production');

        return "dev_backup:{$env}:{$userId}:{$baseKey}";
    }

    private function notifySuperAdminsAboutBackup(string $operation, string $filename): void
    {
        try {
            $user = auth()->user();

            Log::info("Backup {$operation} notification", [
                'operation'    => $operation,
                'filename'     => $filename,
                'performed_by' => $user?->email,
                'user_type'    => $user?->type,
                'timestamp'    => now()->toISOString(),
                'ip_address'   => request()->ip(),
            ]);

            // TODO: implement real notification (mail/Slack/webhook) here.
            // Left as a log by design until a notification channel is confirmed.
        } catch (\Throwable $e) {
            Log::warning('Failed to send backup notification', ['error' => $e->getMessage()]);
        }
    }

    private function looksLikePathTraversal(string $filename): bool
    {
        // Reject any filename containing a path separator, .., or null byte
        return str_contains($filename, '..')
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, "\0")
            || !preg_match('/^[\w\-. ]+$/', $filename);
    }

    public function showBackup(string $filename)
{
    if (!$this->canAccessBackup()) {
        abort(403);
    }

    if ($this->looksLikePathTraversal($filename)) {
        abort(404);
    }

    $backups = $this->getCachedBackupList();
    $backup  = collect($backups)->firstWhere('filename', $filename)
            ?? collect($backups)->firstWhere('name', $filename);

    if (!$backup) {
        abort(404, 'Backup not found.');
    }

    $cacheKey = $this->getBackupCacheKey('verify_' . md5($filename));
    $verification = Cache::get($cacheKey);

    return view('developer.backup.show', [
        'backup'       => $backup,
        'verification' => $verification,
        'stats'        => $this->getCachedBackupStats(),
    ]);
}

public function showRestore()
{
    if (!$this->canAccessBackup()) {
        abort(403);
    }

    $backups = $this->getCachedBackupList();

    return view('developer.backup.restore', [
        'backups' => $backups,
        'stats'   => $this->getCachedBackupStats(),
    ]);
}
}