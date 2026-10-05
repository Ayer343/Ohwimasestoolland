<?php

namespace App\Services;

use App\Models\UserEmailAccount;
use App\Models\Email;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use App\Mail\UserInvitationMail;
use App\Mail\AgentInvitationMail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Email as MimeEmail;
use Symfony\Component\Mime\Address;
use Webklex\IMAP\Facades\Client as ImapClient;
use Webklex\PHPIMAP\Client as ImapClientInstance;
use Webklex\PHPIMAP\Message as ImapMessage;
use Exception;
use Carbon\Carbon;

class EmailService
{
    protected $isConfigured;
    protected $provider;
    protected $lastError;
    protected $driver;

    public function __construct()
    {
        $this->driver = config('mail.default', 'log');
        $this->isConfigured = $this->checkConfiguration();
        $this->provider = $this->determineProvider();
        $this->lastError = null;
    }

    // ========================================== //
    // 🏗️ EXISTING SYSTEM EMAIL METHODS          //
    // ========================================== //

    protected function checkConfiguration(): bool
    {
        if (in_array($this->driver, ['log', 'array'])) {
            return false;
        }

        switch ($this->driver) {
            case 'smtp':
                return $this->checkSmtpConfiguration();
            case 'mailgun':
                return $this->checkMailgunConfiguration();
            case 'ses':
                return $this->checkSesConfiguration();
            case 'sendgrid':
                return $this->checkSendgridConfiguration();
            case 'postmark':
                return $this->checkPostmarkConfiguration();
            default:
                return !empty(config('mail.default'));
        }
    }

    protected function checkSmtpConfiguration(): bool
    {
        return !empty(config('mail.mailers.smtp.host')) &&
               !empty(config('mail.mailers.smtp.port')) &&
               !empty(config('mail.mailers.smtp.username')) &&
               !empty(config('mail.mailers.smtp.password'));
    }

    protected function checkMailgunConfiguration(): bool
    {
        return !empty(config('services.mailgun.domain')) &&
               !empty(config('services.mailgun.secret'));
    }

    protected function checkSesConfiguration(): bool
    {
        return !empty(config('services.ses.key')) &&
               !empty(config('services.ses.secret')) &&
               !empty(config('services.ses.region'));
    }

    protected function checkSendgridConfiguration(): bool
    {
        return !empty(config('services.sendgrid.api_key'));
    }

    protected function checkPostmarkConfiguration(): bool
    {
        return !empty(config('services.postmark.token'));
    }

    protected function determineProvider(): string
    {
        $driver = $this->driver;

        if ($driver === 'smtp') {
            $host = config('mail.mailers.smtp.host', '');

            if (str_contains($host, 'gmail')) return 'gmail_smtp';
            if (str_contains($host, 'outlook')) return 'outlook_smtp';
            if (str_contains($host, 'yahoo')) return 'yahoo_smtp';
            if (str_contains($host, 'zoho')) return 'zoho_smtp';

            return 'custom_smtp';
        }

        return $driver;
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function sendEmail($to, $subject, $template, $data = []): array
    {
        $this->lastError = null;

        if (!$this->checkRateLimit()) {
            $this->lastError = 'Rate limit exceeded';
            $this->updateStatistics(false);

            return [
                'success' => false,
                'message' => 'Email rate limit exceeded. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'to' => $to
            ];
        }

        if (!$this->checkDailyLimit()) {
            $this->lastError = 'Daily limit exceeded';
            $this->updateStatistics(false);

            return [
                'success' => false,
                'message' => 'Daily email limit exceeded.',
                'error_code' => 'DAILY_LIMIT_EXCEEDED',
                'to' => $to
            ];
        }

        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: {$to}");
            }

            if (!$this->isConfigured) {
                Log::warning("Email service not configured, would send to: {$to}", [
                    'subject' => $subject,
                    'template' => $template
                ]);

                $this->updateStatistics(true);

                return [
                    'success' => true,
                    'message' => 'Email sent successfully (simulated - email not configured)',
                    'message_id' => 'simulated-' . uniqid(),
                    'provider' => 'simulated',
                    'to' => $to
                ];
            }

            $emailData = $this->prepareEmailData($data);
            $emailData['subject'] = $subject;
            $emailData['to'] = $to;

            Mail::send($template, $emailData, function ($message) use ($to, $subject, $emailData) {
                $message->to($to)
                        ->subject($subject);

                if (!empty($emailData['cc'])) {
                    $message->cc($emailData['cc']);
                }
                if (!empty($emailData['bcc'])) {
                    $message->bcc($emailData['bcc']);
                }
                if (!empty($emailData['attachments'])) {
                    foreach ($emailData['attachments'] as $attachment) {
                        if (is_array($attachment)) {
                            $message->attach($attachment['path'], $attachment['options'] ?? []);
                        } else {
                            $message->attach($attachment);
                        }
                    }
                }
                if (!empty($emailData['reply_to'])) {
                    $message->replyTo($emailData['reply_to']);
                }
            });

            Log::info("Email sent successfully", [
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'provider' => $this->provider
            ]);

            $this->updateStatistics(true);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'message_id' => 'email-' . uniqid(),
                'provider' => $this->provider,
                'to' => $to
            ];

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->updateStatistics(false);

            Log::error("Email sending failed: " . $e->getMessage(), [
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage(),
                'error_code' => 'EMAIL_SEND_FAILED',
                'to' => $to
            ];
        }
    }

    protected function prepareEmailData(array $data): array
    {
        $preparedData = [];

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $preparedData[$key] = (string) $value;
                } elseif (method_exists($value, 'toArray')) {
                    $preparedData[$key] = $value->toArray();
                } else {
                    Log::warning("Skipping object in email data that cannot be converted", [
                        'key' => $key,
                        'object_type' => get_class($value)
                    ]);
                    continue;
                }
            } elseif (is_array($value)) {
                $preparedData[$key] = $this->prepareEmailData($value);
            } else {
                $preparedData[$key] = $value;
            }
        }

        return $preparedData;
    }

    public function sendUserInvitation($to, array $data = []): array
    {
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => "Invalid email address: {$to}",
                    'error_code' => 'INVALID_EMAIL'
                ];
            }

            if (!$this->isConfigured) {
                Log::warning("Email service not configured, would send user invitation to: {$to}");
                return [
                    'success' => true,
                    'message' => 'User invitation email sent successfully (simulated)',
                    'message_id' => 'simulated-' . uniqid(),
                    'provider' => 'simulated'
                ];
            }

            Mail::to($to)->send(new UserInvitationMail($data));

            Log::info('User invitation email sent successfully', [
                'to' => $to,
                'user_name' => $data['userName'] ?? 'Unknown',
                'invitation_id' => $data['invitationId'] ?? 'unknown'
            ]);

            $this->updateStatistics(true);

            return [
                'success' => true,
                'message' => 'User invitation email sent successfully',
                'message_id' => 'user_invitation_' . uniqid(),
                'provider' => $this->provider
            ];

        } catch (Exception $e) {
            Log::error('User invitation email sending failed: ' . $e->getMessage(), [
                'to' => $to,
                'data_keys' => array_keys($data)
            ]);

            $this->updateStatistics(false);

            return [
                'success' => false,
                'message' => 'User invitation email sending failed: ' . $e->getMessage(),
                'error_code' => 'EMAIL_EXCEPTION'
            ];
        }
    }

    public function sendAgentInvitation($to, array $data = []): array
    {
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => "Invalid email address: {$to}",
                    'error_code' => 'INVALID_EMAIL'
                ];
            }

            if (!$this->isConfigured) {
                Log::warning("Email service not configured, would send agent invitation to: {$to}");
                return [
                    'success' => true,
                    'message' => 'Agent invitation email sent successfully (simulated)',
                    'message_id' => 'simulated-' . uniqid(),
                    'provider' => 'simulated'
                ];
            }

            Mail::to($to)->send(new AgentInvitationMail($data));

            Log::info('Agent invitation email sent successfully', [
                'to' => $to,
                'agent_name' => $data['agentName'] ?? 'Unknown',
                'invitation_id' => $data['invitationId'] ?? 'unknown'
            ]);

            $this->updateStatistics(true);

            return [
                'success' => true,
                'message' => 'Agent invitation email sent successfully',
                'message_id' => 'agent_invitation_' . uniqid(),
                'provider' => $this->provider
            ];

        } catch (Exception $e) {
            Log::error('Agent invitation email sending failed: ' . $e->getMessage(), [
                'to' => $to,
                'data_keys' => array_keys($data)
            ]);

            $this->updateStatistics(false);

            return [
                'success' => false,
                'message' => 'Agent invitation email sending failed: ' . $e->getMessage(),
                'error_code' => 'EMAIL_EXCEPTION'
            ];
        }
    }

    public function sendEmailWithRetry($to, $subject, $template, $data = [], $maxRetries = 2): array
    {
        $attempt = 0;

        while ($attempt <= $maxRetries) {
            $result = $this->sendEmail($to, $subject, $template, $data);

            if ($result['success'] || $attempt === $maxRetries) {
                return $result;
            }

            $attempt++;
            Log::warning("Email send attempt {$attempt} failed, retrying...", [
                'to' => $to,
                'error' => $result['message'],
                'next_attempt_in' => min(30, pow(2, $attempt)) . ' seconds'
            ]);

            sleep(min(30, pow(2, $attempt)));
        }

        return $result;
    }

    public function sendBulk(array $recipients, $subject, $template, $data = []): array
    {
        $results = [
            'total' => count($recipients),
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
            'started_at' => now()->toISOString(),
        ];

        foreach ($recipients as $index => $recipient) {
            $dailyLimit = $this->getDailyLimit();
            if ($dailyLimit['remaining'] <= 0) {
                Log::error('Daily email limit reached during bulk send', [
                    'processed' => $index,
                    'total' => count($recipients)
                ]);
                break;
            }

            $result = $this->sendEmail(
                $recipient['email'],
                $subject,
                $template,
                array_merge($data, $recipient['data'] ?? [])
            );

            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $recipient['email'],
                    'error' => $result['message'],
                    'attempt' => $index + 1
                ];
            }

            if (($index + 1) % 10 === 0) {
                usleep(100000);
            }
        }

        $results['completed_at'] = now()->toISOString();
        $results['success_rate'] = $results['total'] > 0 ?
            round(($results['successful'] / $results['total']) * 100, 2) : 0;

        Log::info("Bulk email send completed", $results);

        return $results;
    }

    protected function checkRateLimit(): bool
    {
        $key = 'email_rate_limit_' . now()->format('Y-m-d-H-i');
        $current = Cache::get($key, 0);
        $limit = $this->getRateLimit()['max_emails_per_minute'] ?? 100;

        if ($current >= $limit) {
            Log::warning('Email rate limit exceeded', [
                'current' => $current,
                'limit' => $limit,
                'minute' => now()->format('Y-m-d H:i')
            ]);
            return false;
        }

        Cache::put($key, $current + 1, 65);
        return true;
    }

    protected function checkDailyLimit(): bool
    {
        $dailyLimit = $this->getDailyLimit();
        return $dailyLimit['used_today'] < $dailyLimit['max_emails_per_day'];
    }

    protected function getRateLimit(): array
    {
        $limits = [
            'smtp' => ['max_emails_per_minute' => 100, 'max_connections' => 10],
            'mailgun' => ['max_emails_per_minute' => 1000, 'max_connections' => 50],
            'ses' => ['max_emails_per_second' => 14, 'max_24_hour_send' => 50000],
            'sendgrid' => ['max_emails_per_minute' => 100, 'max_connections' => 10],
            'postmark' => ['max_emails_per_minute' => 500, 'max_connections' => 20],
        ];

        return $limits[$this->driver] ?? ['max_emails_per_minute' => 100, 'max_connections' => 10];
    }

    protected function getDailyLimit(): array
    {
        $dailyLimits = [
            'smtp' => 10000,
            'mailgun' => 10000,
            'ses' => 50000,
            'sendgrid' => 10000,
            'postmark' => 10000,
        ];

        $maxPerDay = $dailyLimits[$this->driver] ?? 10000;
        $usedToday = $this->getEmailsSentToday();

        return [
            'max_emails_per_day' => $maxPerDay,
            'used_today' => $usedToday,
            'remaining' => max(0, $maxPerDay - $usedToday),
            'usage_percentage' => $maxPerDay > 0 ? round(($usedToday / $maxPerDay) * 100, 2) : 0,
        ];
    }

    protected function getEmailsSentToday(): int
    {
        $todayKey = 'email_stats_' . now()->format('Y-m-d');
        $todayStats = Cache::get($todayKey, ['sent' => 0, 'failed' => 0]);
        return $todayStats['sent'];
    }

    protected function updateStatistics(bool $success): void
    {
        $todayKey = 'email_stats_' . now()->format('Y-m-d');
        $totalKey = 'email_stats_total';

        $todayStats = Cache::get($todayKey, ['sent' => 0, 'failed' => 0]);
        $totalStats = Cache::get($totalKey, ['sent' => 0, 'failed' => 0]);

        if ($success) {
            $todayStats['sent']++;
            $totalStats['sent']++;
        } else {
            $todayStats['failed']++;
            $totalStats['failed']++;
        }

        Cache::put($todayKey, $todayStats, 86400);
        Cache::put($totalKey, $totalStats, 2592000);
        Cache::put('last_email_sent', now()->toISOString(), 86400);
    }

    public function getSystemStatus(): array
    {
        $health = $this->checkHealth();

        $status = [
            'system_ready' => $this->isConfigured && $health === 'healthy',
            'enabled' => $this->isConfigured,
            'driver' => $this->driver,
            'provider' => $this->provider,
            'configured' => $this->isConfigured,
            'last_checked' => now()->toISOString(),
            'health' => $health,
            'features' => [
                'html_emails' => true,
                'attachments' => true,
                'templates' => true,
                'bulk_emails' => true,
                'tracking' => $this->supportsTracking(),
            ],
            'limits' => [
                'rate_limit' => $this->getRateLimit(),
                'daily_limit' => $this->getDailyLimit(),
            ],
            'statistics' => $this->getStatistics(),
            'configuration' => $this->getSafeConfiguration(),
            'validation' => $this->validateConfiguration(),
        ];

        $status['message'] = $this->getStatusMessage($status['health']);

        return $status;
    }

    protected function checkHealth(): string
    {
        $cacheKey = 'email_health_check';

        return Cache::remember($cacheKey, 300, function () {
            try {
                if (!$this->isConfigured) {
                    return 'not_configured';
                }

                $testEmail = config('mail.test_email', config('mail.from.address'));
                if (!$testEmail) {
                    Log::warning('No test email configured for health check');
                    return 'degraded';
                }

                if (!app()->environment('production')) {
                    $mailer = app('mail.manager')->mailer();
                    return $mailer ? 'healthy' : 'degraded';
                }

                Mail::raw('Email service health check - ' . config('app.name'), function ($message) use ($testEmail) {
                    $message->to($testEmail)
                            ->subject('Health Check - ' . config('app.name'))
                            ->from(config('mail.from.address'), config('mail.from.name'));
                });

                Log::info('Email health check passed');
                return 'healthy';

            } catch (Exception $e) {
                Log::error('Email health check failed: ' . $e->getMessage());
                return 'unhealthy';
            }
        });
    }

    protected function getStatusMessage(string $health): string
    {
        return match($health) {
            'healthy' => 'Email service is operating normally',
            'degraded' => 'Email service is experiencing issues',
            'unhealthy' => 'Email service is unavailable',
            'not_configured' => 'Email service is not configured',
            default => 'Email service status unknown',
        };
    }

    protected function supportsTracking(): bool
    {
        return in_array($this->driver, ['mailgun', 'ses', 'sendgrid', 'postmark']);
    }

    protected function getStatistics(): array
    {
        $todayKey = 'email_stats_' . now()->format('Y-m-d');
        $totalKey = 'email_stats_total';

        $todayStats = Cache::get($todayKey, ['sent' => 0, 'failed' => 0]);
        $totalStats = Cache::get($totalKey, ['sent' => 0, 'failed' => 0]);

        $totalSent = $todayStats['sent'] + $totalStats['sent'];
        $totalFailed = $todayStats['failed'] + $totalStats['failed'];
        $totalAttempts = $totalSent + $totalFailed;

        $successRate = $totalAttempts > 0 ? ($totalSent / $totalAttempts) * 100 : 0;

        return [
            'total_emails_sent' => $totalSent,
            'successful_emails' => $totalSent,
            'failed_emails' => $totalFailed,
            'success_rate' => round($successRate, 2),
            'emails_sent_today' => $todayStats['sent'],
            'emails_failed_today' => $todayStats['failed'],
            'last_email_sent' => Cache::get('last_email_sent'),
            'average_delivery_time' => null,
        ];
    }

    protected function getSafeConfiguration(): array
    {
        $config = [
            'driver' => $this->driver,
            'provider' => $this->provider,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'timeout' => config('mail.mailers.smtp.timeout', 30),
        ];

        switch ($this->driver) {
            case 'smtp':
                $config['host'] = config('mail.mailers.smtp.host');
                $config['port'] = config('mail.mailers.smtp.port');
                $config['auth'] = !empty(config('mail.mailers.smtp.username'));
                break;
            case 'mailgun':
                $config['domain'] = config('services.mailgun.domain');
                $config['endpoint'] = config('services.mailgun.endpoint', 'api.mailgun.net');
                break;
            case 'ses':
                $config['region'] = config('services.ses.region');
                break;
        }

        return $config;
    }

    public function validateConfiguration(): array
    {
        $errors = [];
        $warnings = [];

        if (empty(config('mail.from.address'))) {
            $errors[] = 'MAIL_FROM_ADDRESS is not set';
        } else if (!filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'MAIL_FROM_ADDRESS is not a valid email address';
        }

        if (empty(config('mail.from.name'))) {
            $warnings[] = 'MAIL_FROM_NAME is not set';
        }

        switch ($this->driver) {
            case 'smtp':
                if (empty(config('mail.mailers.smtp.host'))) {
                    $errors[] = 'MAIL_HOST is not set for SMTP';
                }
                if (empty(config('mail.mailers.smtp.port'))) {
                    $errors[] = 'MAIL_PORT is not set for SMTP';
                }
                if (empty(config('mail.mailers.smtp.username'))) {
                    $warnings[] = 'MAIL_USERNAME is not set for SMTP';
                }
                if (empty(config('mail.mailers.smtp.password'))) {
                    $warnings[] = 'MAIL_PASSWORD is not set for SMTP';
                }
                break;

            case 'mailgun':
                if (empty(config('services.mailgun.domain'))) {
                    $errors[] = 'MAILGUN_DOMAIN is not set';
                }
                if (empty(config('services.mailgun.secret'))) {
                    $errors[] = 'MAILGUN_SECRET is not set';
                }
                break;

            case 'ses':
                if (empty(config('services.ses.key'))) {
                    $errors[] = 'AWS SES KEY is not set';
                }
                if (empty(config('services.ses.secret'))) {
                    $errors[] = 'AWS SES SECRET is not set';
                }
                if (empty(config('services.ses.region'))) {
                    $errors[] = 'AWS SES REGION is not set';
                }
                break;

            case 'sendgrid':
                if (empty(config('services.sendgrid.api_key'))) {
                    $errors[] = 'SENDGRID_API_KEY is not set';
                }
                break;

            case 'postmark':
                if (empty(config('services.postmark.token'))) {
                    $errors[] = 'POSTMARK_TOKEN is not set';
                }
                break;
        }

        if (app()->environment('production')) {
            if ($this->driver === 'log') {
                $warnings[] = 'Using log driver in production - emails will not be sent';
            }
            if ($this->driver === 'smtp' && str_contains(config('mail.mailers.smtp.host', ''), 'gmail')) {
                $warnings[] = 'Using Gmail SMTP in production is not recommended for high volume';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'driver' => $this->driver,
            'provider' => $this->provider
        ];
    }

    public function getSupportedFeatures(): array
    {
        return [
            'templates' => true,
            'attachments' => true,
            'bulk_sending' => true,
            'tracking' => $this->supportsTracking(),
            'html_emails' => true,
            'inline_css' => true,
            'queueing' => true,
            'retry_logic' => true,
            'rate_limiting' => true,
        ];
    }

    public static function getAvailableDrivers(): array
    {
        return [
            'smtp' => 'SMTP',
            'mailgun' => 'Mailgun',
            'ses' => 'Amazon SES',
            'sendgrid' => 'SendGrid',
            'postmark' => 'Postmark',
            'log' => 'Log (Development)',
            'array' => 'Array (Testing)',
        ];
    }

    public function resetStatistics(): void
    {
        $todayKey = 'email_stats_' . now()->format('Y-m-d');
        $totalKey = 'email_stats_total';

        Cache::forget($todayKey);
        Cache::forget($totalKey);
        Cache::forget('last_email_sent');

        Log::info('Email statistics reset');
    }

    public function checkAndAlert(): void
    {
        $health = $this->checkHealth();
        $stats = $this->getStatistics();

        if ($health === 'unhealthy') {
            Log::critical('Email service is unhealthy - immediate attention required', [
                'health' => $health,
                'provider' => $this->provider
            ]);
        }

        if ($stats['success_rate'] < 80) {
            Log::error('Email success rate critically low', $stats);
        } elseif ($stats['success_rate'] < 90) {
            Log::warning('Email success rate below 90%', $stats);
        }

        $dailyLimit = $this->getDailyLimit();
        if ($dailyLimit['usage_percentage'] > 80) {
            Log::warning('Email daily limit usage high', $dailyLimit);
        }
    }

    // ========================================== //
    // 🆕 ACCOUNT MANAGEMENT METHODS              //
    // ========================================== //

    /**
     * Delegate to the model's safe accessor.
     *
     * ✅ SAFE FALLBACK: if the model doesn't expose
     * getDecryptedPassword(), we fall back to the raw encrypted_password
     * attribute (in case the model still uses the old 'encrypted' cast).
     */
    private function getDecryptedPassword(UserEmailAccount $account): ?string
    {
        if (method_exists($account, 'getDecryptedPassword')) {
            return $account->getDecryptedPassword();
        }

        // Fallback: the model attribute may already be decrypted by a cast
        return $account->encrypted_password ?? null;
    }

    /**
     * Sanitize and validate email address
     */
    private function sanitizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        $email = trim($email);

        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = trim($matches[1]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Invalid email address', ['email' => $email]);
            return null;
        }

        return $email;
    }

    /**
     * Sanitize display name
     */
    private function sanitizeDisplayName(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        $name = trim($name);
        $name = preg_replace('/<[^>]+>/', '', $name);
        $name = preg_replace('/\([^)]+\)/', '', $name);
        $name = preg_replace('/[^a-zA-Z0-9\s\.\-_\']/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);

        if (empty($name)) {
            return null;
        }

        if (strlen($name) > 255) {
            $name = substr($name, 0, 252) . '...';
        }

        return $name;
    }

    /**
     * ✅ Build a short preview string from an HTML or plain-text body.
     * Used to populate `body_preview` (schema has no `text_body` column).
     */
    private function makePreview(?string $html, ?string $text, int $length = 200): ?string
    {
        $source = $text ?: strip_tags($html ?? '');
        $source = trim(preg_replace('/\s+/', ' ', $source));

        if ($source === '') {
            return null;
        }

        return mb_substr($source, 0, $length);
    }

    /**
     * Create SMTP transport using factory with Dsn
     */
    private function createSmtpTransport(string $host, int $port, string $username, string $password, string $encryption): EsmtpTransport
    {
        $factory = new EsmtpTransportFactory();

        $dsn = new Dsn(
            'smtp',
            $host,
            $username,
            $password,
            $port,
            [
                'encryption' => $encryption,
                'timeout' => 30,
            ]
        );

        return $factory->create($dsn);
    }

    /**
     * Generate RFC 2822 compliant message ID
     */
    private function generateMessageId(string $email, string $domain = ''): string
    {
        $uniqueId = uniqid() . '.' . md5(microtime() . rand());

        if (empty($domain) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $email);
            $domain = $parts[1] ?? gethostname() ?: 'localhost';
        }

        if (empty($domain)) {
            $domain = gethostname() ?: 'localhost';
        }

        return '<' . $uniqueId . '@' . $domain . '>';
    }

    /**
     * ✅ Convert a comma-separated address string (or an array) into a
     * JSON-encoded array of address strings suitable for a JSON column.
     * Returns null if empty so the DB stores NULL, not '[]'.
     */
    private function addressesToJson($addresses): ?string
    {
        if (empty($addresses)) {
            return null;
        }

        $list = [];

        if (is_array($addresses)) {
            $list = $addresses;
        } elseif (is_string($addresses)) {
            // Could be "a@x.com, b@y.com" or already JSON
            $decoded = json_decode($addresses, true);
            if (is_array($decoded)) {
                $list = $decoded;
            } else {
                $list = array_map('trim', explode(',', $addresses));
            }
        }

        $list = array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                return trim($item);
            }
            if (is_object($item) && isset($item->mail)) {
                return $item->mail;
            }
            if (is_array($item) && isset($item['mail'])) {
                return $item['mail'];
            }
            return null;
        }, $list)));

        if (empty($list)) {
            return null;
        }

        return json_encode($list, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send an email using a user's email account
     */
    public function sendEmailWithAccount(
        UserEmailAccount $account,
        string $to,
        string $subject,
        string $body,
        ?string $cc = null,
        ?string $bcc = null,
        array $attachments = []
    ): array {
        try {
            Log::info('Sending email with account', [
                'account_id' => $account->id,
                'to' => $to,
                'subject' => $subject
            ]);

            if (!$this->checkAccountQuota($account)) {
                return [
                    'success' => false,
                    'message' => 'Daily send limit reached for this account.'
                ];
            }

            $password = $this->getDecryptedPassword($account);

            if (empty($password)) {
                return [
                    'success' => false,
                    'message' => 'No password is stored for this account.'
                ];
            }

            $fromEmail = $this->sanitizeEmail($account->email);
            if (!$fromEmail) {
                return [
                    'success' => false,
                    'message' => 'Invalid from email address: ' . $account->email
                ];
            }

            $fromName = $this->sanitizeDisplayName($account->display_name ?? $account->email);

            $email = new MimeEmail();

            if ($fromName && $fromName !== $fromEmail) {
                $email->from(new Address($fromEmail, $fromName));
            } else {
                $email->from($fromEmail);
            }

            $to = $this->sanitizeEmail($to);
            if (!$to) {
                return [
                    'success' => false,
                    'message' => 'Invalid recipient email address.'
                ];
            }
            $email->to($to);

            $email->subject($subject);
            $email->html($body);
            $email->text(strip_tags($body));

            if ($cc) {
                $cc = $this->sanitizeEmail($cc);
                if ($cc) {
                    $email->cc($cc);
                }
            }

            if ($bcc) {
                $bcc = $this->sanitizeEmail($bcc);
                if ($bcc) {
                    $email->bcc($bcc);
                }
            }

            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $email->attach($attachment['path'], [
                        'as' => $attachment['name'] ?? null,
                        'mime' => $attachment['mime'] ?? null,
                    ]);
                }
            }

            $transport = $this->createSmtpTransport(
                $account->smtp_host,
                $account->smtp_port,
                $account->email,
                $password,
                $account->smtp_encryption
            );

            $mailer = new SymfonyMailer($transport);
            $mailer->send($email);

            $this->saveSentEmail($account, $to, $subject, $body, $cc, $bcc, $attachments);

            if (Schema::hasColumn('user_email_accounts', 'emails_sent_today')) {
                $account->increment('emails_sent_today');
            }

            if (Schema::hasColumn('user_email_accounts', 'last_sent_at')) {
                $account->last_sent_at = now();
            }

            $account->save();

            Log::info('Email sent successfully', [
                'account_id' => $account->id,
                'to' => $to,
                'subject' => $subject
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully!'
            ];

        } catch (Exception $e) {
            Log::error('Failed to send email', [
                'account_id' => $account->id,
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ Save a sent email.
     *
     * - direction = 'outgoing' (matches the new enum)
     * - status    = 'sent'     (valid enum value)
     * - cc/bcc    = JSON arrays (the columns have json_valid() CHECKs)
     * - body_preview populated from the plain-text view of the body
     */
    private function saveSentEmail(
        UserEmailAccount $account,
        string $to,
        string $subject,
        string $body,
        ?string $cc = null,
        ?string $bcc = null,
        array $attachments = []
    ): Email {
        if (empty($account->id)) {
            throw new \RuntimeException('Cannot save sent email without user_email_account_id');
        }

        $messageId = $this->generateMessageId($account->email);

        return Email::create([
            'user_email_account_id' => (int) $account->id,
            'user_id'               => $account->user_id,
            'message_id'            => $messageId,
            'folder'                => 'SENT',
            'direction'             => 'outgoing',       // ✅ matches enum
            'from_email'            => $account->email,
            'from_name'             => $account->display_name ?? $account->email,
            'to_email'              => $to,
            'to_name'               => null,
            'cc'                    => $this->addressesToJson($cc),   // ✅ valid JSON
            'bcc'                   => $this->addressesToJson($bcc),  // ✅ valid JSON
            'subject'               => $subject,
            'body'                  => strip_tags($body),             // plain text
            'html_body'             => $body,                         // HTML
            'body_preview'          => $this->makePreview($body, null), // ✅ NEW
            'status'                => 'sent',                        // ✅ valid enum
            'priority'              => 'normal',
            'attachment_count'      => count($attachments),
            'is_read'               => true,
            'sent_at'               => now(),
            'received_at'           => now(),
        ]);
    }

    /**
     * Check account quota
     */
    public function checkAccountQuota(UserEmailAccount $account): bool
    {
        $dailyLimit = $account->daily_send_limit ?? 500;
        $sentToday = $account->emails_sent_today ?? 0;

        if (Schema::hasColumn('user_email_accounts', 'last_sent_at')) {
            if ($account->last_sent_at && Carbon::parse($account->last_sent_at)->isYesterday()) {
                $account->emails_sent_today = 0;
                $account->save();
                return true;
            }
        }

        return $sentToday < $dailyLimit;
    }

    /**
     * Reply to an email
     */
    public function replyToEmail(
        UserEmailAccount $account,
        Email $originalEmail,
        string $body
    ): array {
        try {
            $subject = $originalEmail->subject;
            if (!str_starts_with($subject, 'Re:')) {
                $subject = 'Re: ' . $subject;
            }

            $replyBody = $body . "\n\n--- Original Message ---\n";
            $replyBody .= "From: {$originalEmail->from_name} <{$originalEmail->from_email}>\n";
            $replyBody .= "Sent: " . ($originalEmail->sent_at ?? $originalEmail->created_at)->format('F j, Y g:i A') . "\n";
            $replyBody .= "Subject: {$originalEmail->subject}\n\n";
            $replyBody .= $originalEmail->body;

            $result = $this->sendEmailWithAccount(
                $account,
                $originalEmail->from_email,
                $subject,
                $replyBody
            );

            if ($result['success']) {
                $originalEmail->update([
                    'is_replied' => true,
                    'replied_at' => now()
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Reply failed', [
                'account_id' => $account->id,
                'original_email_id' => $originalEmail->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send reply: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Forward an email
     */
    public function forwardEmail(
        UserEmailAccount $account,
        Email $originalEmail,
        string $to,
        string $body
    ): array {
        try {
            $subject = $originalEmail->subject;
            if (!str_starts_with($subject, 'Fwd:')) {
                $subject = 'Fwd: ' . $subject;
            }

            $forwardBody = $body . "\n\n--- Forwarded Message ---\n";
            $forwardBody .= "From: {$originalEmail->from_name} <{$originalEmail->from_email}>\n";
            $forwardBody .= "Sent: " . ($originalEmail->sent_at ?? $originalEmail->created_at)->format('F j, Y g:i A') . "\n";
            $forwardBody .= "Subject: {$originalEmail->subject}\n\n";
            $forwardBody .= $originalEmail->body;

            $result = $this->sendEmailWithAccount(
                $account,
                $to,
                $subject,
                $forwardBody
            );

            if ($result['success']) {
                $originalEmail->update([
                    'is_forwarded' => true,
                    'forwarded_at' => now()
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Forward failed', [
                'account_id' => $account->id,
                'original_email_id' => $originalEmail->id,
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to forward email: ' . $e->getMessage()
            ];
        }
    }

    // ========================================== //
    // 📥 IMAP SYNC — webklex/laravel-imap
    // ========================================== //

    /**
     * Sync emails from account using webklex/laravel-imap.
     *
     * ✅ HEADERS-ONLY + SMALL BATCH — fixes the timeout on large mailboxes.
     */
    public function syncAccount(UserEmailAccount $account): array
    {
        $result = [
            'success' => true,
            'message' => 'Sync completed',
            'fetched' => 0,
            'saved'   => 0,
            'skipped' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        try {
            Log::info('Starting email sync for account', [
                'account_id' => $account->id,
                'email'      => $account->email,
            ]);

            $client = $this->connectImap($account);

            $folder = $client->getFolder('INBOX');
            if (!$folder) {
                $result['success'] = false;
                $result['message'] = 'Could not open INBOX folder';
                return $result;
            }

            $fetchStart = microtime(true);

            $messages = $folder->query()
                ->since(now()->subDays(3))
                ->leaveUnread()
                ->setFetchBody(false)
                ->limit(5)
                ->get();

            Log::info('📥 IMAP header fetch complete', [
                'account_id'      => $account->id,
                'message_count'   => $messages->count(),
                'elapsed_seconds' => round(microtime(true) - $fetchStart, 2),
            ]);

            if ($messages->count() === 0) {
                Log::info('No messages in last 3 days — falling back to newest 5', [
                    'account_id' => $account->id,
                ]);

                $fetchStart = microtime(true);
                $messages = $folder->query()
                    ->all()
                    ->leaveUnread()
                    ->setFetchBody(false)
                    ->limit(5)
                    ->get();

                Log::info('📥 IMAP header fetch (fallback) complete', [
                    'account_id'      => $account->id,
                    'message_count'   => $messages->count(),
                    'elapsed_seconds' => round(microtime(true) - $fetchStart, 2),
                ]);
            }

            foreach ($messages as $message) {
                try {
                    $saved = $this->saveReceivedImapMessage($account, $message);

                    if ($saved) {
                        $result['saved']++;
                        $result['fetched']++;
                    } else {
                        $result['skipped']++;
                    }
                } catch (Exception $e) {
                    $result['failed']++;
                    $result['errors'][] = $e->getMessage();

                    Log::warning('Failed to save email', [
                        'account_id' => $account->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $client->disconnect();

            if ($result['failed'] > 0 && $result['saved'] === 0 && $result['skipped'] === 0) {
                $result['success'] = false;
                $result['message'] = sprintf(
                    'Sync failed: all %d message(s) could not be saved.',
                    $result['failed']
                );
            } else {
                $result['message'] = sprintf(
                    'Synced %d new, %d already present, %d failed.',
                    $result['saved'],
                    $result['skipped'],
                    $result['failed']
                );
            }

            $account->update([
                'last_sync_at'          => now(),
                'is_connected'          => true,
                'last_connected_at'     => now(),
                'last_connection_error' => null,
                'status'                => 'verified',
            ]);

            Log::info('Email sync completed', [
                'account_id' => $account->id,
                'saved'      => $result['saved'],
                'skipped'    => $result['skipped'],
                'failed'     => $result['failed'],
            ]);

        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = 'Sync failed: ' . $e->getMessage();
            $result['errors'][] = $e->getMessage();

            $account->update([
                'is_connected'          => false,
                'last_connection_error' => $e->getMessage(),
                'status'                => 'failed',
            ]);

            Log::error('Email sync failed', [
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    /**
     * ✅ LAZY BODY FETCH — called when the user opens an email.
     *
     * Schema note: this app stores the plain-text body in `body`,
     * the HTML in `html_body`, and a short snippet in `body_preview`.
     * There is NO `text_body` column.
     */
    public function fetchAndStoreMessageBody(UserEmailAccount $account, Email $email): bool
    {
        if (empty($email->message_id)) {
            Log::warning('Cannot fetch body: email has no message_id', [
                'email_id' => $email->id,
            ]);
            return false;
        }

        $client = null;

        try {
            $client = $this->connectImap($account);
            $folder = $client->getFolder('INBOX');

            if (!$folder) {
                return false;
            }

            Log::info('📥 Lazy fetching message body', [
                'account_id' => $account->id,
                'email_id'   => $email->id,
                'message_id' => $email->message_id,
            ]);

            $message = $folder->query()
                ->whereMessageId($email->message_id)
                ->setFetchBody(true)
                ->limit(1)
                ->get()
                ->first();

            if (!$message) {
                Log::warning('Message not found on IMAP server', [
                    'email_id'   => $email->id,
                    'message_id' => $email->message_id,
                ]);
                return false;
            }

            $htmlBody = null;
            $textBody = null;
            try { $htmlBody = $message->getHTMLBody(); } catch (Exception $e) {}
            try { $textBody = $message->getTextBody(); } catch (Exception $e) {}

            $attachmentCount = 0;
            try {
                $attachments = $message->getAttachments();
                $attachmentCount = $this->countImapAttribute($attachments);
            } catch (Exception $e) {
                // no attachments
            }

            // ✅ Plain text goes into `body`; HTML into `html_body`;
            //    snippet into `body_preview`. There is no `text_body`.
            $email->update([
                'body'             => $textBody ?: strip_tags($htmlBody ?? '') ?: '',
                'html_body'        => $htmlBody,
                'body_preview'     => $this->makePreview($htmlBody, $textBody),
                'has_attachments'  => $attachmentCount > 0,
                'attachment_count' => $attachmentCount,
            ]);

            Log::info('✅ Lazy body fetch complete', [
                'email_id'    => $email->id,
                'has_html'    => !empty($htmlBody),
                'has_text'    => !empty($textBody),
                'attachments' => $attachmentCount,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Lazy body fetch failed', [
                'email_id' => $email->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        } finally {
            if ($client) {
                try { $client->disconnect(); } catch (Exception $e) {}
            }
        }
    }

    /**
     * Connect to IMAP server.
     */
    private function connectImap(UserEmailAccount $account): ImapClientInstance
    {
        $password = $this->getDecryptedPassword($account);

        if (empty($password)) {
            throw new Exception('No password is stored for this account.');
        }

        $encryption = $account->imap_encryption;
        if ($encryption === 'none' || $encryption === '' || $encryption === null) {
            $encryption = false;
        }

        $timeout = 15;

        $config = [
            'host'           => $account->imap_host,
            'port'           => (int) $account->imap_port,
            'protocol'       => 'imap',
            'encryption'     => $encryption,
            'validate_cert'  => false,
            'username'       => $account->email,
            'password'       => $password,
            'authentication' => null,
            'timeout'        => $timeout,
            'extensions'     => [],
            'proxy'          => [
                'socket'          => null,
                'request_fulluri' => false,
                'username'        => null,
                'password'        => null,
            ],
        ];

        Log::info('🔍 Connecting to IMAP with config', [
            'host'          => $config['host'],
            'port'          => $config['port'],
            'protocol'      => $config['protocol'],
            'encryption'    => $config['encryption'],
            'validate_cert' => $config['validate_cert'],
            'timeout'       => $config['timeout'],
            'username'      => $config['username'],
            'account_id'    => $account->id,
        ]);

        Config::set('imap.accounts.default', $config);

        try {
            $client = ImapClient::account('default');

            Log::info('🔍 Attempting IMAP connection…', [
                'account_id' => $account->id,
                'started_at' => now()->toISOString(),
            ]);

            $start = microtime(true);
            $client->connect();
            $elapsed = round(microtime(true) - $start, 2);

            if (!$client->isConnected()) {
                throw new Exception('IMAP client reports not connected after connect()');
            }

            Log::info('✅ IMAP connected', [
                'account_id' => $account->id,
                'elapsed_seconds' => $elapsed,
            ]);

            return $client;
        } catch (Exception $e) {
            Log::error('❌ IMAP connection threw exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            throw new Exception('IMAP connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * ✅ Persist an IMAP message.
     *
     * Schema alignment:
     *   direction      → 'incoming'   (enum: incoming|outgoing)
     *   status         → 'delivered'  (enum: draft|sent|failed|queued|delivered)
     *   cc / bcc       → JSON arrays  (columns have json_valid() CHECKs)
     *   body           → '' on sync; plain text filled lazily
     *   html_body      → null on sync; HTML filled lazily
     *   body_preview   → null on sync; snippet filled lazily
     *
     * NOTE: There is NO `text_body` column in this schema.
     */
    private function saveReceivedImapMessage(UserEmailAccount $account, ImapMessage $message): bool
    {
        $messageId = $message->getMessageId()
            ? (string) $message->getMessageId()
            : null;

        if (empty($account->id)) {
            Log::error('❌ Cannot save email: account has no id', [
                'account_email' => $account->email ?? null,
            ]);
            throw new \RuntimeException('Cannot save email without user_email_account_id');
        }

        // Dedupe
        if ($messageId) {
            $exists = Email::where('user_email_account_id', $account->id)
                ->where('message_id', $messageId)
                ->exists();

            if ($exists) {
                return false;
            }
        }

        // From — normalized
        $fromEmail = null;
        $fromName  = null;
        try {
            $from = $message->getFrom();
            if ($from && $this->countImapAttribute($from) > 0) {
                $first     = $this->firstImapAddress($from);
                $fromEmail = !empty($first['mail'])     ? trim($first['mail'])     : null;
                $fromName  = !empty($first['personal']) ? trim($first['personal']) : null;
            }
        } catch (Exception $e) {
            // leave nulls
        }

        // To / CC / BCC — raw comma-separated values
        $toRaw  = $this->extractImapAddresses($message->getTo());
        $ccRaw  = $this->extractImapAddresses($message->getCc());
        $bccRaw = $this->extractImapAddresses($message->getBcc());

        // Attachment count from structure (no download)
        $attachmentCount = 0;
        try {
            $structure = $message->getStructure();
            if ($structure) {
                $attachmentCount = $this->countAttachmentsFromStructure($structure);
            }
        } catch (Exception $e) {
            $attachmentCount = 0;
        }

        // Read status
        $isRead = false;
        try {
            $flags = $message->getFlags();
            if ($flags && method_exists($flags, 'has')) {
                $isRead = (bool) $flags->has('seen');
            } elseif (is_iterable($flags)) {
                foreach ($flags as $flag) {
                    if (is_string($flag) && strtolower($flag) === 'seen') {
                        $isRead = true;
                        break;
                    }
                }
            }
        } catch (Exception $e) { /* flags unavailable */ }

        // Date
        $receivedAt = null;
        try {
            $date = $message->getDate();
            if ($date) {
                $receivedAt = Carbon::parse((string) $date);
            }
        } catch (Exception $e) {
            $receivedAt = null;
        }

        // Subject — normalized
        $subject = '';
        try {
            $subject = trim((string) $message->getSubject());
        } catch (Exception $e) {
            $subject = '';
        }
        if ($subject === '') {
            $subject = '(No Subject)';
        }

        // ── Build payload ────────────────────────────────────────
        // NOTE: NO `text_body` key — this schema doesn't have that column.
        //       Plain-text goes into `body` on lazy fetch.
        $payload = [
            'user_email_account_id' => (int) $account->id,
            'user_id'               => $account->user_id,       // denormalized owner
            'message_id'            => $messageId,
            'folder'                => 'INBOX',
            'direction'             => 'incoming',              // ✅ enum-aligned
            'from_email'            => $fromEmail,
            'from_name'             => $fromName,               // null, not ''
            'to_email'              => $toRaw,
            'to_name'               => null,
            'cc'                    => $this->addressesToJson($ccRaw),   // ✅ valid JSON
            'bcc'                   => $this->addressesToJson($bccRaw),  // ✅ valid JSON
            'subject'               => $subject,
            'body'                  => '',                      // filled lazily
            'html_body'             => null,                    // filled lazily
            'body_preview'          => null,                    // filled lazily
            'status'                => 'delivered',             // ✅ enum-aligned
            'priority'              => 'normal',
            'attachment_count'      => $attachmentCount,
            'is_read'               => $isRead,
            'read_at'               => $isRead ? now() : null,
            'received_at'           => $receivedAt ?? now(),
        ];

        try {
            Email::create($payload);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('❌ Email insert failed', [
                'account_id' => $account->id,
                'message_id' => $messageId,
                'sql_error'  => $e->getMessage(),
                'payload'    => $payload,
            ]);
            throw $e;
        }

        return true;
    }

    /**
     * Count attachment parts from a message structure WITHOUT downloading.
     */
    private function countAttachmentsFromStructure($structure): int
    {
        if (!$structure) {
            return 0;
        }

        $count = 0;
        $parts = is_array($structure) ? $structure : [$structure];

        foreach ($parts as $part) {
            if (!is_object($part)) continue;

            $disposition = strtoupper($part->disposition ?? '');
            if ($disposition === 'ATTACHMENT') {
                $count++;
            }

            if (!empty($part->parts)) {
                $count += $this->countAttachmentsFromStructure($part->parts);
            }
        }

        return $count;
    }

    /**
     * Count webklex Attributes / collections / arrays safely.
     */
    private function countImapAttribute($value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if ($value instanceof \Countable) {
            return count($value);
        }

        if (is_object($value) && method_exists($value, 'count')) {
            try {
                return (int) $value->count();
            } catch (\Throwable $e) {
                return 0;
            }
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            try {
                $arr = $value->toArray();
                return is_array($arr) ? count($arr) : 0;
            } catch (\Throwable $e) {
                return 0;
            }
        }

        if (is_iterable($value)) {
            $n = 0;
            foreach ($value as $_) { $n++; }
            return $n;
        }

        return 0;
    }

    /**
     * Get the first address from a webklex Attribute collection.
     */
    private function firstImapAddress($addresses): array
    {
        if (!$addresses) {
            return [];
        }

        try {
            if (is_object($addresses) && method_exists($addresses, 'first')) {
                $first = $addresses->first();
                if (is_object($first)) {
                    return [
                        'mail'     => $first->mail ?? null,
                        'personal' => $first->personal ?? null,
                    ];
                }
                if (is_array($first)) {
                    return $first;
                }
            }

            if (is_object($addresses) && method_exists($addresses, 'toArray')) {
                $arr = $addresses->toArray();
                if (is_array($arr) && !empty($arr)) {
                    $first = reset($arr);
                    if (is_object($first)) {
                        return [
                            'mail'     => $first->mail ?? null,
                            'personal' => $first->personal ?? null,
                        ];
                    }
                    if (is_array($first)) {
                        return $first;
                    }
                }
            }

            if (is_iterable($addresses)) {
                foreach ($addresses as $first) {
                    if (is_object($first)) {
                        return [
                            'mail'     => $first->mail ?? null,
                            'personal' => $first->personal ?? null,
                        ];
                    }
                    if (is_array($first)) {
                        return $first;
                    }
                    break;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return [];
    }

    /**
     * Convert webklex address collection to a comma-separated string.
     */
    private function extractImapAddresses($addresses): ?string
    {
        if (!$addresses) {
            return null;
        }

        $items = [];

        try {
            if (is_array($addresses)) {
                $items = $addresses;
            } elseif (is_object($addresses) && method_exists($addresses, 'toArray')) {
                $items = $addresses->toArray();
            } elseif (is_iterable($addresses)) {
                foreach ($addresses as $item) {
                    $items[] = $item;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($items) || empty($items)) {
            return null;
        }

        $emails = [];
        foreach ($items as $addr) {
            $email = null;

            if (is_object($addr)) {
                $email = $addr->mail ?? $addr->email ?? null;
            } elseif (is_array($addr)) {
                $email = $addr['mail'] ?? $addr['email'] ?? null;
            } elseif (is_string($addr)) {
                $email = $addr;
            }

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return !empty($emails) ? implode(', ', $emails) : null;
    }

    /**
     * Resend an email
     */
    public function resendEmail(UserEmailAccount $account, Email $email): array
    {
        return $this->sendEmailWithAccount(
            $account,
            $email->to_email,
            $email->subject,
            $email->body,
            is_array($email->cc)  ? implode(', ', $email->cc)  : $email->cc,
            is_array($email->bcc) ? implode(', ', $email->bcc) : $email->bcc
        );
    }

    /**
     * ✅ Copy email to draft.
     * status must be one of: draft|sent|failed|queued|delivered
     */
    public function copyToDraft(UserEmailAccount $account, Email $email): array
    {
        try {
            if (empty($account->id)) {
                throw new \RuntimeException('Cannot create draft without user_email_account_id');
            }

            $draft = Email::create([
                'user_email_account_id' => (int) $account->id,
                'user_id'               => $account->user_id,
                'message_id'            => $this->generateMessageId($account->email),
                'folder'                => 'DRAFTS',
                'direction'             => 'outgoing',
                'from_email'            => $account->email,
                'from_name'             => $account->display_name ?? $account->email,
                'to_email'              => $email->to_email,
                'to_name'               => $email->to_name,
                'cc'                    => is_array($email->cc)
                    ? json_encode($email->cc)
                    : $this->addressesToJson($email->cc),
                'bcc'                   => is_array($email->bcc)
                    ? json_encode($email->bcc)
                    : $this->addressesToJson($email->bcc),
                'subject'               => $email->subject,
                'body'                  => $email->body,
                'html_body'             => $email->html_body,
                'body_preview'          => $this->makePreview($email->html_body, $email->body),
                'status'                => 'draft',         // ✅ valid enum value
                'is_draft'              => true,
                'is_read'               => true,
            ]);

            return [
                'success'  => true,
                'message'  => 'Email copied to drafts',
                'draft_id' => $draft->id,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to copy email: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mark email as read
     */
    public function markAsRead(UserEmailAccount $account, Email $email): array
    {
        try {
            $email->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return ['success' => true, 'message' => 'Email marked as read'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mark email as unread
     */
    public function markAsUnread(UserEmailAccount $account, Email $email): array
    {
        try {
            $email->update([
                'is_read' => false,
                'read_at' => null
            ]);

            return ['success' => true, 'message' => 'Email marked as unread'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete email
     */
    public function deleteEmail(UserEmailAccount $account, Email $email): array
    {
        try {
            $email->delete();
            return ['success' => true, 'message' => 'Email deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get account statistics
     */
    public function getAccountStats(UserEmailAccount $account): array
    {
        return [
            'total_emails' => $account->emails()->count(),
            'unread_emails' => $account->emails()->where('is_read', false)->count(),
            'sent_today' => $account->emails_sent_today ?? 0,
            'daily_limit' => $account->daily_send_limit ?? 500,
            'last_sync' => $account->last_sync_at,
            'is_connected' => $account->is_connected,
            'status' => $account->status,
        ];
    }

    /**
     * Test SMTP connection for account
     */
    public function testAccountConnection(UserEmailAccount $account): array
    {
        try {
            $password = $this->getDecryptedPassword($account);

            if (empty($password)) {
                return [
                    'success' => false,
                    'message' => 'No password is stored for this account.'
                ];
            }

            $transport = $this->createSmtpTransport(
                $account->smtp_host,
                $account->smtp_port,
                $account->email,
                $password,
                $account->smtp_encryption
            );

            $transport->ping();

            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }
}