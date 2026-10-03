<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DeveloperEmailService;
use App\Notifications\DeveloperEmailVerificationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use Carbon\Carbon;

class SendDeveloperEmailVerificationJob implements ShouldQueue
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
    public $timeout = 120; // 2 minutes

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public $failOnTimeout = true;

    /**
     * The user to verify.
     */
    protected $user;

    /**
     * The email address to verify.
     */
    protected $email;

    /**
     * The verification type.
     */
    protected $type;

    /**
     * Email service instance.
     */
    protected $emailService;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $email, string $type = 'configuration')
    {
        $this->user = $user;
        $this->email = $email;
        $this->type = $type;

        // Set job properties
        $this->onQueue('developer_emails');
        $this->delay = now()->addSeconds(10); // Small delay
    }

    /**
     * Execute the job.
     */
    public function handle(DeveloperEmailService $emailService): void
    {
        $this->emailService = $emailService;

        try {
            Log::info('Starting SendDeveloperEmailVerificationJob', [
                'user_id' => $this->user->id,
                'email' => $this->email,
                'type' => $this->type,
                'queue' => $this->queue
            ]);

            // Generate verification code
            $verificationCode = $this->generateVerificationCode();

            // Store verification data in cache
            $this->storeVerificationData($verificationCode);

            // Send verification email
            $this->sendVerificationEmail($verificationCode);

            // Log success
            Log::info('SendDeveloperEmailVerificationJob completed successfully', [
                'user_id' => $this->user->id,
                'email' => $this->email,
                'type' => $this->type
            ]);

        } catch (Throwable $e) {
            $this->handleFailure($e);
            throw $e; // Re-throw to allow Laravel's retry mechanism
        }
    }

    /**
     * Generate verification code.
     */
    protected function generateVerificationCode(): string
    {
        // Generate a 6-digit numeric code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Ensure uniqueness (simple check)
        $attempts = 0;
        $maxAttempts = 5;

        while ($attempts < $maxAttempts) {
            $existingCode = Cache::get('email_verification_code_' . $code);
            if (!$existingCode) {
                break;
            }
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $attempts++;
        }

        return $code;
    }

    /**
     * Store verification data in cache.
     */
    protected function storeVerificationData(string $verificationCode): void
    {
        $verificationData = [
            'code' => $verificationCode,
            'email' => $this->email,
            'user_id' => $this->user->id,
            'type' => $this->type,
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addHours(24)->toISOString(),
            'attempts' => 0,
            'verified' => false,
            'job_class' => self::class
        ];

        // Store in cache with multiple keys for easy access
        $cacheKey = 'email_verification_' . $this->user->id;
        $codeKey = 'email_verification_code_' . $verificationCode;
        $emailKey = 'email_verification_email_' . md5($this->email);

        Cache::put($cacheKey, $verificationData, 86400); // 24 hours
        Cache::put($codeKey, $this->user->id, 86400); // 24 hours
        Cache::put($emailKey, $verificationData, 86400); // 24 hours

        // Also store in database for audit trail (optional)
        $this->logVerificationAttempt($verificationCode, 'sent');

        Log::info('Verification data stored', [
            'user_id' => $this->user->id,
            'email' => $this->email,
            'code' => $verificationCode,
            'cache_key' => $cacheKey
        ]);
    }

    /**
     * Send verification email.
     */
    protected function sendVerificationEmail(string $verificationCode): void
    {
        try {
            // Prepare email data
            $subject = $this->getEmailSubject();
            $content = $this->getEmailContent($verificationCode);

            // Send email using the email service
            $result = $this->emailService->sendVerificationEmail(
                $this->email,
                $subject,
                $verificationCode,
                $this->user,
                $this->type
            );

            if (!$result) {
                throw new \Exception('Failed to send verification email');
            }

            // Send notification to user
            $this->sendVerificationNotification($verificationCode);

            Log::info('Verification email sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->email,
                'code' => $verificationCode,
                'type' => $this->type,
                'subject' => $subject
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'user_id' => $this->user->id,
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get email subject based on verification type.
     */
    protected function getEmailSubject(): string
    {
        $appName = config('app.name', 'Application');
        
        switch ($this->type) {
            case 'configuration':
                return "Email Configuration Verification - {$appName}";
            case 'password_reset':
                return "Password Reset Verification - {$appName}";
            case 'account_verification':
                return "Account Verification - {$appName}";
            case 'security_alert':
                return "Security Alert Verification - {$appName}";
            case 'test':
                return "Test Email Verification - {$appName}";
            default:
                return "Verification Code - {$appName}";
        }
    }

    /**
     * Get email content based on verification type.
     */
    protected function getEmailContent(string $verificationCode): array
    {
        $appName = config('app.name', 'Application');
        $appUrl = config('app.url', 'https://example.com');
        
        $baseContent = [
            'app_name' => $appName,
            'app_url' => $appUrl,
            'verification_code' => $verificationCode,
            'expiry_hours' => 24,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'ip_address' => request()->ip() ?? 'Unknown',
            'user_agent' => request()->userAgent() ?? 'Unknown',
            'verification_type' => $this->type
        ];

        switch ($this->type) {
            case 'configuration':
                return array_merge($baseContent, [
                    'title' => 'Email Configuration Verification',
                    'message' => 'You have successfully updated your email configuration. Please verify your email address by entering the code below:',
                    'instructions' => 'Enter this code in the verification field on the email configuration page.',
                    'action_required' => 'Verification required within 24 hours.',
                    'contact_support' => 'If you did not initiate this configuration change, please contact support immediately.',
                    'action_url' => route('developer.settings.index', ['section' => 'email'])
                ]);

            case 'password_reset':
                return array_merge($baseContent, [
                    'title' => 'Password Reset Verification',
                    'message' => 'You have requested a password reset. Please verify your identity by entering the code below:',
                    'instructions' => 'Enter this code in the password reset verification field.',
                    'action_required' => 'Complete password reset within 24 hours.',
                    'contact_support' => 'If you did not request a password reset, please ignore this email.',
                    'action_url' => route('password.reset')
                ]);

            case 'account_verification':
                return array_merge($baseContent, [
                    'title' => 'Account Verification',
                    'message' => 'Please verify your account by entering the code below:',
                    'instructions' => 'Enter this code in the account verification field.',
                    'action_required' => 'Complete verification within 24 hours.',
                    'contact_support' => 'If you did not create an account, please contact support.',
                    'action_url' => route('verification.verify')
                ]);

            case 'security_alert':
                return array_merge($baseContent, [
                    'title' => 'Security Alert Verification',
                    'message' => 'A security alert has been triggered for your account. Please verify this action:',
                    'instructions' => 'Enter this code to confirm the security action.',
                    'action_required' => 'Action required within 1 hour.',
                    'contact_support' => 'If you did not initiate this security action, contact support immediately.',
                    'action_url' => route('security.verify')
                ]);

            case 'test':
                return array_merge($baseContent, [
                    'title' => 'Test Email Verification',
                    'message' => 'This is a test email to verify your email configuration is working correctly.',
                    'instructions' => 'Enter this code in the test verification field to confirm email delivery.',
                    'action_required' => 'No action required - this is just a test.',
                    'contact_support' => 'If you received this email unexpectedly, please contact support.',
                    'action_url' => route('developer.settings.index', ['section' => 'email'])
                ]);

            default:
                return array_merge($baseContent, [
                    'title' => 'Verification Required',
                    'message' => 'Please verify your action by entering the code below:',
                    'instructions' => 'Enter this code in the verification field.',
                    'action_required' => 'Complete verification within 24 hours.',
                    'contact_support' => 'If you did not initiate this action, please ignore this email.',
                    'action_url' => route('developer.settings.index')
                ]);
        }
    }

    /**
     * Send verification notification to user.
     */
    protected function sendVerificationNotification(string $verificationCode): void
    {
        try {
            $notificationData = [
                'type' => $this->type,
                'email' => $this->email,
                'verification_code' => $verificationCode,
                'expires_at' => now()->addHours(24)->toISOString(),
                'timestamp' => now()->toISOString(),
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email
                ],
                'app_name' => config('app.name', 'Application'),
                'app_url' => config('app.url', 'https://example.com')
            ];

            $this->user->notify(new DeveloperEmailVerificationNotification($notificationData));

            Log::debug('Verification notification sent', [
                'user_id' => $this->user->id,
                'email' => $this->email,
                'type' => $this->type
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send verification notification: ' . $e->getMessage());
            // Don't throw, continue with job
        }
    }

    /**
     * Log verification attempt to database.
     */
    protected function logVerificationAttempt(string $code, string $action): void
    {
        try {
            // You can use DeveloperEmailAudit model or create a specific VerificationLog model
            if (class_exists('App\Models\DeveloperEmailAudit')) {
                \App\Models\DeveloperEmailAudit::create([
                    'user_id' => $this->user->id,
                    'job_id' => $this->job->getJobId() ?? Str::uuid(),
                    'action' => 'verification_' . $action,
                    'status' => $action === 'sent' ? 'success' : 'failed',
                    'message' => "Verification email {$action} for {$this->type}",
                    'old_configuration' => null,
                    'new_configuration' => json_encode([
                        'email' => $this->email,
                        'type' => $this->type,
                        'code_prefix' => substr($code, 0, 2) . '****' // Partial code for security
                    ]),
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'Background Job',
                    'metadata' => json_encode([
                        'timestamp' => now()->toISOString(),
                        'app_env' => config('app.env'),
                        'job_class' => self::class,
                        'verification_type' => $this->type
                    ])
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log verification attempt: ' . $e->getMessage());
        }
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(Throwable $e): void
    {
        try {
            // Log verification attempt as failed
            $this->logVerificationAttempt('', 'failed');

            // Send failure notification to user
            $this->sendFailureNotification($e);

            Log::error('SendDeveloperEmailVerificationJob failed', [
                'user_id' => $this->user->id,
                'email' => $this->email,
                'type' => $this->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
                'job_id' => $this->job->getJobId() ?? 'unknown'
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
                'type' => $this->type,
                'email' => $this->email,
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => now()->toISOString(),
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email
                ],
                'app_name' => config('app.name', 'Application')
            ];

            $this->user->notify(new DeveloperEmailVerificationNotification($notificationData));

        } catch (\Exception $notificationError) {
            Log::error('Failed to send failure notification: ' . $notificationError->getMessage());
        }
    }

    /**
     * Get the cache key for this verification.
     */
    public static function getCacheKey(int $userId): string
    {
        return 'email_verification_' . $userId;
    }

    /**
     * Verify a code.
     */
    public static function verifyCode(int $userId, string $code): array
    {
        $cacheKey = self::getCacheKey($userId);
        $verificationData = Cache::get($cacheKey);

        if (!$verificationData) {
            return [
                'success' => false,
                'message' => 'Verification code not found or expired',
                'code' => 'not_found'
            ];
        }

        // Check if code matches
        if ($verificationData['code'] !== $code) {
            // Increment attempts
            $verificationData['attempts']++;
            Cache::put($cacheKey, $verificationData, 86400);
            
            // Check if max attempts exceeded
            if ($verificationData['attempts'] >= 5) {
                Cache::forget($cacheKey);
                Cache::forget('email_verification_code_' . $code);
                Cache::forget('email_verification_email_' . md5($verificationData['email']));
                
                return [
                    'success' => false,
                    'message' => 'Too many failed attempts. Please request a new verification code.',
                    'code' => 'max_attempts'
                ];
            }
            
            $remainingAttempts = 5 - $verificationData['attempts'];
            return [
                'success' => false,
                'message' => "Invalid verification code. {$remainingAttempts} attempts remaining.",
                'code' => 'invalid',
                'remaining_attempts' => $remainingAttempts
            ];
        }

        // Check if expired
        if (now()->greaterThan(Carbon::parse($verificationData['expires_at']))) {
            Cache::forget($cacheKey);
            Cache::forget('email_verification_code_' . $code);
            Cache::forget('email_verification_email_' . md5($verificationData['email']));
            
            return [
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.',
                'code' => 'expired'
            ];
        }

        // Check if already verified
        if ($verificationData['verified'] ?? false) {
            return [
                'success' => false,
                'message' => 'This code has already been used.',
                'code' => 'already_used'
            ];
        }

        // Mark as verified
        $verificationData['verified'] = true;
        $verificationData['verified_at'] = now()->toISOString();
        Cache::put($cacheKey, $verificationData, 86400);

        return [
            'success' => true,
            'message' => 'Email verified successfully!',
            'code' => 'verified',
            'email' => $verificationData['email'],
            'type' => $verificationData['type'],
            'verified_at' => $verificationData['verified_at']
        ];
    }

    /**
     * Check if email is verified.
     */
    public static function isVerified(int $userId, string $email): bool
    {
        $cacheKey = self::getCacheKey($userId);
        $verificationData = Cache::get($cacheKey);

        if (!$verificationData) {
            return false;
        }

        return ($verificationData['email'] === $email) && 
               ($verificationData['verified'] ?? false) &&
               !now()->greaterThan(Carbon::parse($verificationData['expires_at']));
    }

    /**
     * Get verification status.
     */
    public static function getVerificationStatus(int $userId): array
    {
        $cacheKey = self::getCacheKey($userId);
        $verificationData = Cache::get($cacheKey);

        if (!$verificationData) {
            return [
                'verified' => false,
                'has_pending' => false,
                'expired' => true,
                'message' => 'No verification pending'
            ];
        }

        $expired = now()->greaterThan(Carbon::parse($verificationData['expires_at']));
        $verified = $verificationData['verified'] ?? false;

        return [
            'verified' => $verified,
            'has_pending' => !$verified && !$expired,
            'expired' => $expired,
            'email' => $verificationData['email'],
            'type' => $verificationData['type'],
            'created_at' => $verificationData['created_at'],
            'expires_at' => $verificationData['expires_at'],
            'attempts' => $verificationData['attempts'],
            'remaining_attempts' => 5 - $verificationData['attempts'],
            'message' => $verified ? 'Already verified' : ($expired ? 'Verification expired' : 'Verification pending')
        ];
    }

    /**
     * Resend verification email.
     */
    public static function resendVerification(int $userId): bool
    {
        $cacheKey = self::getCacheKey($userId);
        $verificationData = Cache::get($cacheKey);

        if (!$verificationData || $verificationData['verified'] || now()->greaterThan(Carbon::parse($verificationData['expires_at']))) {
            return false;
        }

        // Check if we've already sent too many
        $resendCount = Cache::get('verification_resend_count_' . $userId, 0);
        if ($resendCount >= 3) {
            return false;
        }

        // Increment resend count
        Cache::put('verification_resend_count_' . $userId, $resendCount + 1, 3600); // 1 hour

        // Dispatch new job
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        self::dispatch($user, $verificationData['email'], $verificationData['type']);

        return true;
    }

    /**
     * Clear verification data.
     */
    public static function clearVerification(int $userId): void
    {
        $cacheKey = self::getCacheKey($userId);
        $verificationData = Cache::get($cacheKey);

        if ($verificationData) {
            Cache::forget($cacheKey);
            Cache::forget('email_verification_code_' . $verificationData['code']);
            Cache::forget('email_verification_email_' . md5($verificationData['email']));
            Cache::forget('verification_resend_count_' . $userId);
        }
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
        return now()->addMinutes(5);
    }
}