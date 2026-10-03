<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\DeveloperSetting;
use App\Models\DeveloperEmailAudit;
use App\Services\DeveloperEmailConfigurationService;
use App\Services\DeveloperEmailService;
use App\Notifications\DeveloperEmailConfigurationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;
use Carbon\Carbon;

class DeveloperEmailConfigurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of exceptions allowed before failing.
     */
    public $maxExceptions = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 300; // 5 minutes

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public $failOnTimeout = true;

    /**
     * The user performing the update.
     */
    protected $user;

    /**
     * The action to perform.
     */
    protected $action;

    /**
     * The configuration data.
     */
    protected $configData;

    /**
     * The old configuration.
     */
    protected $oldConfig;

    /**
     * The job ID for tracking.
     */
    protected $jobId;

    /**
     * Email service instance.
     */
    protected $emailService;

    /**
     * Configuration service instance.
     */
    protected $configService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        User $user,
        string $action,
        array $configData,
        array $oldConfig,
        string $jobId
    ) {
        $this->user = $user;
        $this->action = $action;
        $this->configData = $configData;
        $this->oldConfig = $oldConfig;
        $this->jobId = $jobId;

        // Set job properties
        $this->onQueue('developer_emails');
        $this->delay = now()->addSeconds(5); // Small delay for UI responsiveness
    }

    /**
     * Execute the job.
     */
    public function handle(
        DeveloperEmailConfigurationService $configService,
        DeveloperEmailService $emailService
    ): void {
        $this->configService = $configService;
        $this->emailService = $emailService;

        try {
            // Update job status to processing
            $this->updateStatus('processing', 10, 'Starting email configuration update...');

            Log::info('Starting DeveloperEmailConfigurationJob', [
                'job_id' => $this->jobId,
                'user_id' => $this->user->id,
                'action' => $this->action,
                'queue' => $this->queue
            ]);

            // Perform action based on type
            switch ($this->action) {
                case 'update':
                    $this->handleUpdate();
                    break;
                case 'reset':
                    $this->handleReset();
                    break;
                case 'fix':
                    $this->handleFix();
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown action: {$this->action}");
            }

            // Update job status to completed
            $this->updateStatus('completed', 100, 'Email configuration update completed successfully!');

            // Log successful completion
            Log::info('DeveloperEmailConfigurationJob completed successfully', [
                'job_id' => $this->jobId,
                'user_id' => $this->user->id,
                'action' => $this->action,
                'duration' => $this->getJobDuration()
            ]);

        } catch (Throwable $e) {
            $this->handleFailure($e);
            throw $e; // Re-throw to allow Laravel's retry mechanism
        }
    }

    /**
     * Handle email configuration update.
     */
    protected function handleUpdate(): void
    {
        // Step 1: Validate configuration data
        $this->updateStatus('processing', 20, 'Validating configuration data...');
        $this->validateConfigData();

        // Step 2: Save to .env file
        $this->updateStatus('processing', 30, 'Saving configuration to .env file...');
        $saveResult = $this->configService->saveDeveloperSmtpConfig($this->configData);

        if (!$saveResult['success']) {
            throw new \Exception("Failed to save to .env: " . $saveResult['message']);
        }

        // Step 3: Update database records
        $this->updateStatus('processing', 50, 'Updating database records...');
        $this->updateDatabaseRecords();

        // Step 4: Test the configuration
        $this->updateStatus('processing', 70, 'Testing email configuration...');
        $testResult = $this->testConfiguration();

        // Step 5: Clear cache
        $this->updateStatus('processing', 90, 'Clearing cache...');
        $this->clearCache();

        // Step 6: Log audit
        $this->logAudit('update', $testResult['success'], $testResult['message']);

        // Step 7: Send notification if test was successful
        if ($testResult['success']) {
            $this->updateStatus('processing', 95, 'Sending notification...');
            $this->sendSuccessNotification($testResult);
        }
    }

    /**
     * Handle email configuration reset.
     */
    protected function handleReset(): void
    {
        $this->updateStatus('processing', 20, 'Resetting email configuration to defaults...');

        // Reset configuration
        $resetResult = $this->configService->resetDeveloperEmailConfig();

        if (!$resetResult['success']) {
            throw new \Exception("Failed to reset configuration: " . $resetResult['message']);
        }

        // Clear database records
        $settings = DeveloperSetting::first();
        if ($settings) {
            $settings->update([
                'developer_smtp_host' => null,
                'developer_smtp_port' => null,
                'developer_smtp_username' => null,
                'developer_smtp_password' => null,
                'developer_smtp_encryption' => null,
                'developer_email_from' => null,
                'developer_email_from_name' => null,
            ]);
        }

        // Clear cache
        $this->clearCache();

        // Log audit
        $this->logAudit('reset', true, 'Configuration reset to defaults');

        // Send notification
        $this->sendSuccessNotification([
            'success' => true,
            'message' => 'Email configuration reset to defaults'
        ]);
    }

    /**
     * Handle email configuration fix.
     */
    protected function handleFix(): void
    {
        $this->updateStatus('processing', 20, 'Fixing common email configuration issues...');

        // Run fix common issues
        $fixResult = $this->emailService->fixCommonIssues('developer');

        if (!$fixResult['success']) {
            throw new \Exception("Failed to fix issues: " . $fixResult['message']);
        }

        // Clear cache
        $this->clearCache();

        // Log audit
        $this->logAudit('fix', $fixResult['success'], $fixResult['message']);

        // Send notification
        $this->sendSuccessNotification($fixResult);
    }

    /**
     * Validate configuration data.
     */
    protected function validateConfigData(): void
    {
        $requiredFields = ['host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($this->configData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate email format
        if (!filter_var($this->configData['username'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format for username");
        }

        if (!filter_var($this->configData['from_address'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format for from address");
        }

        // Validate port range
        if (!is_numeric($this->configData['port']) || $this->configData['port'] < 1 || $this->configData['port'] > 65535) {
            throw new \InvalidArgumentException("Invalid port number");
        }

        // Validate encryption type
        $validEncryption = ['tls', 'ssl', 'none'];
        if (!in_array($this->configData['encryption'], $validEncryption)) {
            throw new \InvalidArgumentException("Invalid encryption type. Must be one of: " . implode(', ', $validEncryption));
        }
    }

    /**
     * Update database records.
     */
    protected function updateDatabaseRecords(): void
    {
        $settings = DeveloperSetting::first();
        
        if (!$settings) {
            $settings = DeveloperSetting::create();
        }

        $updateData = [
            'developer_smtp_host' => $this->configData['host'],
            'developer_smtp_port' => $this->configData['port'],
            'developer_smtp_username' => $this->configData['username'],
            'developer_smtp_encryption' => $this->configData['encryption'],
            'developer_email_from' => $this->configData['from_address'],
            'developer_email_from_name' => $this->configData['from_name'],
        ];

        // Encrypt and store password
        if (!empty($this->configData['password'])) {
            $updateData['developer_smtp_password'] = Crypt::encryptString($this->configData['password']);
        }

        $settings->update($updateData);

        Log::info('Database records updated', [
            'settings_id' => $settings->id,
            'updated_fields' => array_keys($updateData)
        ]);
    }

    /**
     * Test the email configuration.
     */
    protected function testConfiguration(): array
    {
        try {
            $testResult = $this->emailService->testConfiguration(
                null,
                $this->configData['from_address'],
                'connection',
                'developer'
            );

            Log::info('Email configuration test completed', [
                'success' => $testResult['success'],
                'message' => $testResult['message']
            ]);

            return $testResult;

        } catch (\Exception $e) {
            Log::error('Email configuration test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clear relevant caches.
     */
    protected function clearCache(): void
    {
        try {
            Cache::forget('developer_email_config_v2_' . $this->user->id);
            Cache::forget('email_configuration_status');
            Cache::forget('developer_settings');

            // Clear Laravel caches
            if (function_exists('artisan')) {
                \Illuminate\Support\Facades\Artisan::call('config:clear');
                \Illuminate\Support\Facades\Artisan::call('view:clear');
            }

            Log::info('Cache cleared for email configuration update');

        } catch (\Exception $e) {
            Log::warning('Failed to clear cache: ' . $e->getMessage());
            // Don't throw, continue with job
        }
    }

    /**
     * Log audit entry.
     */
    protected function logAudit(string $action, bool $success, string $message): void
    {
        try {
            DeveloperEmailAudit::create([
                'user_id' => $this->user->id,
                'job_id' => $this->jobId,
                'action' => $action,
                'status' => $success ? 'success' : 'failed',
                'message' => Str::limit($message, 500),
                'old_configuration' => json_encode($this->oldConfig),
                'new_configuration' => json_encode($this->configData),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Background Job',
                'metadata' => json_encode([
                    'timestamp' => now()->toISOString(),
                    'app_env' => config('app.env'),
                    'user_type' => $this->user->type,
                    'job_class' => self::class
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log audit: ' . $e->getMessage());
        }
    }

    /**
     * Send success notification.
     */
    protected function sendSuccessNotification(array $result): void
    {
        try {
            $notificationData = [
                'action' => $this->action,
                'success' => $result['success'],
                'message' => $result['message'],
                'job_id' => $this->jobId,
                'config_data' => $this->sanitizeConfigData($this->configData),
                'old_config' => $this->oldConfig,
                'timestamp' => now()->toISOString(),
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email
                ]
            ];

            // Send to user who initiated the update
            $this->user->notify(new DeveloperEmailConfigurationNotification($notificationData));

            // Also send to system admins if configured
            if (config('developer.email_notify_admins', false)) {
                $this->notifyAdmins($notificationData);
            }

            Log::info('Success notification sent', [
                'user_id' => $this->user->id,
                'action' => $this->action
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send success notification: ' . $e->getMessage());
        }
    }

    /**
     * Notify system administrators.
     */
    protected function notifyAdmins(array $data): void
    {
        try {
            $adminEmails = config('developer.admin_notification_emails', []);
            
            if (!empty($adminEmails)) {
                foreach ($adminEmails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        // Create a temporary user for notification
                        $tempUser = new User();
                        $tempUser->email = $email;
                        $tempUser->notify(new DeveloperEmailConfigurationNotification($data));
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to notify admins: ' . $e->getMessage());
        }
    }

    /**
     * Update job status in cache.
     */
    protected function updateStatus(string $status, int $progress, string $message): void
    {
        try {
            $statusData = [
                'status' => $status,
                'progress' => $progress,
                'message' => $message,
                'timestamp' => now()->toISOString(),
                'user_id' => $this->user->id,
                'job_id' => $this->jobId,
                'action' => $this->action
            ];

            $cacheKey = 'developer_email_update_status_' . $this->jobId;
            Cache::put($cacheKey, $statusData, 604800); // 7 days

            // Also update user's job list
            $userJobsKey = 'developer_email_update_keys_' . $this->user->id;
            $userJobs = Cache::get($userJobsKey, []);
            
            if (!in_array($this->jobId, $userJobs)) {
                $userJobs[] = $this->jobId;
                Cache::put($userJobsKey, $userJobs, 604800);
            }

            Log::debug('Job status updated', [
                'job_id' => $this->jobId,
                'status' => $status,
                'progress' => $progress,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update job status: ' . $e->getMessage());
        }
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(Throwable $e): void
    {
        try {
            // Update status to failed
            $this->updateStatus('failed', 0, 'Job failed: ' . $e->getMessage());

            // Log audit with failure
            $this->logAudit($this->action, false, 'Job failed: ' . $e->getMessage());

            // Send failure notification
            $this->sendFailureNotification($e);

            Log::error('DeveloperEmailConfigurationJob failed', [
                'job_id' => $this->jobId,
                'user_id' => $this->user->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts()
            ]);

        } catch (\Exception $notificationError) {
            Log::error('Failed to handle job failure: ' . $notificationError->getMessage());
        }
    }

    /**
     * Send failure notification.
     */
    protected function sendFailureNotification(Throwable $e): void
    {
        try {
            $notificationData = [
                'action' => $this->action,
                'success' => false,
                'message' => 'Job failed: ' . $e->getMessage(),
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
                'timestamp' => now()->toISOString(),
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email
                ]
            ];

            $this->user->notify(new DeveloperEmailConfigurationNotification($notificationData));

        } catch (\Exception $notificationError) {
            Log::error('Failed to send failure notification: ' . $notificationError->getMessage());
        }
    }

    /**
     * Sanitize configuration data for logging/notifications.
     */
    protected function sanitizeConfigData(array $config): array
    {
        $sanitized = $config;
        
        // Remove sensitive data
        if (isset($sanitized['password'])) {
            $sanitized['password'] = '[REDACTED]';
        }
        
        return $sanitized;
    }

    /**
     * Get job duration in seconds.
     */
    protected function getJobDuration(): float
    {
        $startTime = $this->job->getRawBody() ? json_decode($this->job->getRawBody(), true)['created_at'] ?? null : null;
        
        if ($startTime) {
            return Carbon::parse($startTime)->diffInSeconds(Carbon::now(), true);
        }
        
        return 0;
    }

    /**
     * The job failed to process.
     */
    public function failed(Throwable $e): void
    {
        $this->handleFailure($e);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 seconds, 1 minute, 2 minutes
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }
}