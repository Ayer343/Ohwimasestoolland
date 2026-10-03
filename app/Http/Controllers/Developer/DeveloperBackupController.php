<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperSetting;
use App\Services\DeveloperMonitoringService;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DeveloperBackupController extends Controller
{
    use AuditLogger;

    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_DEVELOPER = 5;

    // Cache keys and configuration
    const BACKUP_LIST_CACHE_KEY = 'developer_backup_list';
    const BACKUP_STATS_CACHE_KEY = 'developer_backup_stats';
    const BACKUP_JOB_STATUS_KEY = 'developer_backup_job_';
    const VERIFICATION_CODE_KEY = 'restore_verification_';
    
    // Cache TTLs (in seconds)
    const CACHE_TTL_BACKUP_LIST = 300;      // 5 minutes
    const CACHE_TTL_BACKUP_STATS = 600;     // 10 minutes
    const CACHE_TTL_JOB_STATUS = 1800;      // 30 minutes
    const CACHE_TTL_VERIFICATION = 900;     // 15 minutes

    protected $monitoringService;

    public function __construct(DeveloperMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Check if user can access backup functions
     */
    private function canAccessBackup()
    {
        $user = auth()->user();
        
        // STRICT ENFORCEMENT: Only type 5 (developer) or type 0 (super admin) allowed
        return $user->type === self::USER_TYPE_DEVELOPER || $user->type === self::USER_TYPE_SUPER_ADMIN;
    }

    /**
     * Check if user can create backups
     */
    private function canCreateBackup()
    {
        return $this->canAccessBackup();
    }

    /**
     * Check if user can list backups
     */
    private function canListBackups()
    {
        return $this->canAccessBackup();
    }

    /**
     * Check if user can restore backups
     */
    private function canRestoreBackup()
    {
        return $this->canAccessBackup();
    }

    /**
     * Check if user can download backups
     */
    private function canDownloadBackup()
    {
        return $this->canAccessBackup();
    }

    /**
     * Check if user can delete backups
     */
    private function canDeleteBackup()
    {
        return $this->canAccessBackup();
    }

    /**
     * Create manual backup
     */
    public function createBackup()
    {
        $key = 'backup_create:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()->with('error', "Too many backup requests. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key, 3600);
        
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canCreateBackup()) {
                Log::warning('Backup creation denied - invalid user type', [
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->type,
                    'expected_types' => [self::USER_TYPE_DEVELOPER, self::USER_TYPE_SUPER_ADMIN],
                    'ip' => request()->ip()
                ]);
                abort(403, 'Unauthorized to create backups. You must be a developer (type 5) or super admin (type 0).');
            }
            
            $backupResult = $this->monitoringService->createBackup('manual');
            
            if (!$backupResult['success']) {
                throw new \Exception($backupResult['message']);
            }
            
            // Clear backup-related cache after successful backup
            $this->clearBackupCache();
            
            $this->logAudit('backup_created', 'Manual backup created', [
                'user_type' => auth()->user()->type,
                'backup_file' => $backupResult['filename'],
                'size' => $backupResult['size'],
                'ip_address' => request()->ip(),
                'cache_cleared' => true
            ]);
            
            $this->notifySuperAdminsAboutBackup('created', $backupResult['filename']);
            
            return redirect()->route('developer.settings.index')
                ->with('success', 'Backup created successfully!')
                ->with('backup_info', $backupResult);
                
        } catch (\Exception $e) {
            Log::error('Failed to create backup: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return redirect()->back()->with('error', 'Failed to create backup: ' . $e->getMessage());
        }
    }

    /**
     * List available backups with caching
     */
    public function listBackups()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canListBackups()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to list backups. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            // Get backups with cache
            $backups = $this->getCachedBackupList();
            
            $this->logAudit('backups_listed', 'Backup list viewed', [
                'user_type' => auth()->user()->type,
                'backup_count' => count($backups),
                'cache_hit' => Cache::has($this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY))
            ]);
            
            return response()->json([
                'success' => true,
                'backups' => $backups,
                'count' => count($backups),
                'timestamp' => now()->toISOString(),
                'cached' => true,
                'cache_expires_at' => Cache::get($this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY) . '_expiry')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to list backups: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to list backups: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore from backup
     */
    public function restoreBackup(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid CSRF token');
        }
        
        $validator = Validator::make($request->all(), [
            'backup_filename' => 'required|string',
            'confirm_restore' => 'required|accepted',
            'verification_code' => [
                'required',
                'string',
                'size:6',
                function ($attribute, $value, $fail) {
                    $storedCode = Cache::get($this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . auth()->id()));
                    if (!$storedCode || $storedCode !== $value) {
                        $fail('Invalid verification code');
                    }
                }
            ]
        ], [
            'confirm_restore.required' => 'You must confirm the restore operation',
            'verification_code.required' => 'Verification code is required',
            'verification_code.size' => 'Verification code must be 6 digits'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please confirm the restore operation');
        }
        
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canRestoreBackup()) {
                Log::warning('Backup restore denied - invalid user type', [
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->type,
                    'expected_types' => [self::USER_TYPE_DEVELOPER, self::USER_TYPE_SUPER_ADMIN],
                    'ip' => request()->ip()
                ]);
                abort(403, 'Unauthorized to restore backups. You must be a developer (type 5) or super admin (type 0).');
            }
            
            $data = $validator->validated();
            
            // Put application in maintenance mode
            Artisan::call('down', [
                'message' => 'System restore in progress',
                'retry' => 60,
                'secret' => Str::random(32)
            ]);
            
            $restoreResult = $this->monitoringService->restoreBackup($data['backup_filename']);
            
            if (!$restoreResult['success']) {
                Artisan::call('up');
                throw new \Exception($restoreResult['message']);
            }
            
            // Clear all backup cache after restore
            $this->clearBackupCache();
            
            // Clear general cache to ensure fresh data
            Cache::flush();
            
            $this->logAudit('backup_restored', 'System restored from backup', [
                'user_type' => auth()->user()->type,
                'backup_file' => $data['backup_filename'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'cache_cleared' => true
            ]);
            
            $this->notifySuperAdminsAboutBackup('restored', $data['backup_filename']);
            
            // Remove used verification code
            Cache::forget($this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . auth()->id()));
            
            return redirect()->route('developer.index')
                ->with('success', 'System restored successfully! The system is now back online.')
                ->with('warning', 'Please verify all system functionality after restore.');
                
        } catch (\Exception $e) {
            Artisan::call('up');
            
            Log::error('Failed to restore backup: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return redirect()->back()
                ->with('error', 'Failed to restore backup: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Download backup file
     */
    public function downloadBackup(Request $request)
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canDownloadBackup()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to download backups. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename provided'
                ], 400);
            }
            
            $filename = $validator->validated()['filename'];
            
            $backupPath = $this->monitoringService->getBackupPath($filename);
            
            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }
            
            $this->logAudit('backup_downloaded', 'Backup file downloaded', [
                'user_type' => auth()->user()->type,
                'filename' => $filename,
                'ip_address' => request()->ip(),
                'cache_hit' => Cache::has($this->getBackupCacheKey('backup_download_' . md5($filename)))
            ]);
            
            // Cache download attempt (not the file itself)
            Cache::put(
                $this->getBackupCacheKey('backup_download_' . md5($filename)), 
                now()->toISOString(), 
                3600
            );
            
            return response()->download($backupPath, $filename, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to download backup: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete backup file
     */
    public function deleteBackup(Request $request)
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canDeleteBackup()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete backups. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename provided'
                ], 400);
            }
            
            $filename = $validator->validated()['filename'];
            
            $deleteResult = $this->monitoringService->deleteBackup($filename);
            
            if (!$deleteResult['success']) {
                throw new \Exception($deleteResult['message']);
            }
            
            // Clear backup cache after deletion
            $this->clearBackupCache();
            
            $this->logAudit('backup_deleted', 'Backup file deleted', [
                'user_type' => auth()->user()->type,
                'filename' => $filename,
                'ip_address' => request()->ip(),
                'cache_cleared' => true
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully',
                'timestamp' => now()->toISOString(),
                'cache_invalidated' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete backup: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify backup integrity
     */
    public function verifyBackup(Request $request)
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canAccessBackup()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to verify backups. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename provided'
                ], 400);
            }
            
            $filename = $validator->validated()['filename'];
            
            // Cache verification results for frequently verified backups
            $cacheKey = $this->getBackupCacheKey('verify_' . md5($filename));
            $verifyResult = Cache::remember($cacheKey, 1800, function () use ($filename) {
                return $this->monitoringService->verifyBackup($filename);
            });
            
            $this->logAudit('backup_verified', 'Backup integrity verified', [
                'user_type' => auth()->user()->type,
                'filename' => $filename,
                'verification_result' => $verifyResult['success'] ? 'passed' : 'failed',
                'ip_address' => request()->ip(),
                'cache_hit' => Cache::has($cacheKey)
            ]);
            
            return response()->json(array_merge($verifyResult, [
                'cached' => true,
                'cache_expires_at' => now()->addSeconds(1800)->toISOString()
            ]));
            
        } catch (\Exception $e) {
            Log::error('Failed to verify backup: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View backup history with caching
     */
    public function backupHistory()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canListBackups()) {
                Log::warning('Backup history access denied - invalid user type', [
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user()->type,
                    'expected_types' => [self::USER_TYPE_DEVELOPER, self::USER_TYPE_SUPER_ADMIN],
                    'ip' => request()->ip()
                ]);
                abort(403, 'Unauthorized to view backup history. You must be a developer (type 5) or super admin (type 0).');
            }
            
            // Get cached backup list
            $backups = $this->getCachedBackupList();
            
            // Get cached stats
            $stats = $this->getCachedBackupStats();
            
            $this->logAudit('backup_history_viewed', 'Backup history viewed', [
                'user_type' => auth()->user()->type,
                'backup_count' => count($backups),
                'cache_hit_backup_list' => Cache::has($this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY)),
                'cache_hit_backup_stats' => Cache::has($this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY))
            ]);
            
            return view('developer.backup.history', [
                'backups' => $backups,
                'total_backups' => count($backups),
                'total_size' => array_sum(array_column($backups, 'size')),
                'latest_backup' => !empty($backups) ? $backups[0] : null,
                'stats' => $stats,
                'cache_info' => [
                    'backup_list_expires' => Cache::get($this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY) . '_expiry'),
                    'backup_stats_expires' => Cache::get($this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY) . '_expiry')
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to load backup history: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return redirect()->back()->with('error', 'Failed to load backup history: ' . $e->getMessage());
        }
    }

    /**
     * Get backup statistics with caching
     */
    public function getBackupStats()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canListBackups()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view backup statistics. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            // Get cached stats
            $stats = $this->getCachedBackupStats();
            
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'timestamp' => now()->toISOString(),
                'cached' => true,
                'cache_expires_at' => Cache::get($this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY) . '_expiry')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get backup stats: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get backup statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get backup job status with caching
     */
    public function getBackupJobStatus(Request $request)
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canCreateBackup()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view backup job status. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'job_id' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid job ID provided'
                ], 400);
            }
            
            $jobId = $validator->validated()['job_id'];
            
            // Get job status with cache
            $jobStatus = Cache::remember(
                $this->getBackupCacheKey(self::BACKUP_JOB_STATUS_KEY . $jobId),
                self::CACHE_TTL_JOB_STATUS,
                function () use ($jobId) {
                    return $this->monitoringService->getJobStatus($jobId);
                }
            );
            
            return response()->json([
                'success' => true,
                'job_status' => $jobStatus,
                'timestamp' => now()->toISOString(),
                'cached' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get backup job status: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get backup job status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify super admins about backup operation
     */
    private function notifySuperAdminsAboutBackup(string $operation, string $filename)
    {
        try {
            $user = auth()->user();
            $operationText = ucfirst($operation);
            $timestamp = now()->format('Y-m-d H:i:s');
            
            Log::info("Backup {$operation} notification", [
                'operation' => $operation,
                'filename' => $filename,
                'performed_by' => $user->email,
                'user_type' => $user->type,
                'timestamp' => $timestamp,
                'ip_address' => request()->ip(),
                'cache_cleared' => true
            ]);
            
            // You can add email notification logic here if needed
            
        } catch (\Exception $e) {
            Log::warning('Failed to send backup notification: ' . $e->getMessage());
        }
    }

    /**
     * Generate verification code for restore operation
     */
    public function generateVerificationCode()
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canRestoreBackup()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to generate verification codes. You must be a developer (type 5) or super admin (type 0).'
                ], 403);
            }
            
            $verificationCode = Str::random(6);
            $expiresAt = now()->addMinutes(15);
            
            // Store verification code in cache with proper key
            $cacheKey = $this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . auth()->id());
            Cache::put($cacheKey, $verificationCode, self::CACHE_TTL_VERIFICATION);
            
            $this->logAudit('verification_code_generated', 'Restore verification code generated', [
                'user_type' => auth()->user()->type,
                'expires_at' => $expiresAt,
                'ip_address' => request()->ip(),
                'cache_key' => $cacheKey
            ]);
            
            return response()->json([
                'success' => true,
                'verification_code' => $verificationCode,
                'expires_at' => $expiresAt->toISOString(),
                'message' => 'Verification code generated. It will expire in 15 minutes.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate verification code: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate verification code: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear backup-related cache
     */
    public function clearBackupCache()
    {
        try {
            $userId = auth()->id();
            
            // List of cache keys to clear
            $cacheKeys = [
                $this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY),
                $this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY),
                $this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY) . '_expiry',
                $this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY) . '_expiry',
            ];
            
            // Clear job status cache for this user
            $jobPattern = $this->getBackupCacheKey(self::BACKUP_JOB_STATUS_KEY) . '*';
            
            // Also clear any verification codes
            Cache::forget($this->getBackupCacheKey(self::VERIFICATION_CODE_KEY . $userId));
            
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
            
            // Clear verification cache for this backup
            Cache::tags(['backup_verify'])->flush();
            
            Log::info('Backup cache cleared', [
                'user_id' => $userId,
                'user_type' => auth()->user()->type,
                'cache_keys_cleared' => $cacheKeys
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to clear backup cache: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'user_type' => auth()->user()->type
            ]);
            return false;
        }
    }

    // =====================================================
    // Cache Management Methods
    // =====================================================

    /**
     * Get cached backup list
     */
    private function getCachedBackupList(bool $forceRefresh = false)
    {
        $cacheKey = $this->getBackupCacheKey(self::BACKUP_LIST_CACHE_KEY);
        
        if ($forceRefresh) {
            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_expiry');
        }
        
        return Cache::remember($cacheKey, self::CACHE_TTL_BACKUP_LIST, function () use ($cacheKey) {
            $backups = $this->monitoringService->listBackups();
            
            // Store expiry timestamp
            Cache::put(
                $cacheKey . '_expiry', 
                now()->addSeconds(self::CACHE_TTL_BACKUP_LIST)->toISOString(), 
                self::CACHE_TTL_BACKUP_LIST + 60 // Extra minute for safety
            );
            
            return $backups;
        });
    }

    /**
     * Get cached backup statistics
     */
    private function getCachedBackupStats(bool $forceRefresh = false)
    {
        $cacheKey = $this->getBackupCacheKey(self::BACKUP_STATS_CACHE_KEY);
        
        if ($forceRefresh) {
            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_expiry');
        }
        
        return Cache::remember($cacheKey, self::CACHE_TTL_BACKUP_STATS, function () use ($cacheKey) {
            $backups = $this->monitoringService->listBackups();
            
            $totalSize = array_sum(array_column($backups, 'size'));
            $backupCount = count($backups);
            
            // Calculate oldest and newest backup dates
            $oldestDate = null;
            $newestDate = null;
            
            if ($backupCount > 0) {
                $dates = array_column($backups, 'date');
                $oldestDate = min($dates);
                $newestDate = max($dates);
            }
            
            $stats = [
                'total_backups' => $backupCount,
                'total_size' => $totalSize,
                'average_size' => $backupCount > 0 ? round($totalSize / $backupCount, 2) : 0,
                'oldest_backup' => $oldestDate,
                'newest_backup' => $newestDate,
                'disk_usage' => $this->monitoringService->getDiskUsagePercentage(),
                'backup_status' => $this->monitoringService->checkBackupConfiguration()
            ];
            
            // Store expiry timestamp
            Cache::put(
                $cacheKey . '_expiry', 
                now()->addSeconds(self::CACHE_TTL_BACKUP_STATS)->toISOString(), 
                self::CACHE_TTL_BACKUP_STATS + 60 // Extra minute for safety
            );
            
            return $stats;
        });
    }

    /**
     * Generate backup cache key with user context
     */
    private function getBackupCacheKey(string $baseKey): string
    {
        $userId = auth()->id() ?? 'guest';
        $appEnv = config('app.env', 'production');
        
        return "dev_backup:{$appEnv}:{$userId}:{$baseKey}";
    }

    /**
     * Cache backup data with error handling
     */
    private function cacheBackupData(string $key, $data, ?int $ttl = null): bool
    {
        try {
            $cacheKey = $this->getBackupCacheKey($key);
            $ttl = $ttl ?? self::CACHE_TTL_BACKUP_LIST;
            
            Cache::put($cacheKey, $data, $ttl);
            
            // Store expiry timestamp
            Cache::put(
                $cacheKey . '_expiry',
                now()->addSeconds($ttl)->toISOString(),
                $ttl + 60
            );
            
            return true;
            
        } catch (\Exception $e) {
            Log::warning("Backup cache write failed for key: {$key}", [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return false;
        }
    }

    /**
     * Get data from cache or fetch fresh with error handling
     */
    private function getCachedOrFreshBackup(string $key, callable $callback, ?int $ttl = null)
    {
        $cacheKey = $this->getBackupCacheKey($key);
        
        try {
            return Cache::remember($cacheKey, $ttl ?? self::CACHE_TTL_BACKUP_LIST, function () use ($callback, $cacheKey, $ttl) {
                $data = $callback();
                
                // Store expiry timestamp
                Cache::put(
                    $cacheKey . '_expiry',
                    now()->addSeconds($ttl ?? self::CACHE_TTL_BACKUP_LIST)->toISOString(),
                    ($ttl ?? self::CACHE_TTL_BACKUP_LIST) + 60
                );
                
                return $data;
            });
            
        } catch (\Exception $e) {
            Log::warning("Backup cache read failed, using fresh data: {$key}", [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return $callback();
        }
    }

    /**
     * Refresh specific backup cache
     */
    public function refreshBackupCache(Request $request)
    {
        try {
            // Check authorization - STRICT: only type 5 or 0
            if (!$this->canListBackups()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to refresh backup cache.'
                ], 403);
            }
            
            $forceRefresh = $request->get('force', false);
            
            if ($forceRefresh) {
                $this->clearBackupCache();
            }
            
            // Re-fetch cached data
            $backups = $this->getCachedBackupList(true);
            $stats = $this->getCachedBackupStats(true);
            
            return response()->json([
                'success' => true,
                'message' => 'Backup cache refreshed successfully',
                'backup_count' => count($backups),
                'cache_entries_refreshed' => 2,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to refresh backup cache: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh backup cache: ' . $e->getMessage()
            ], 500);
        }
    }
}