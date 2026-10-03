<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class UpdateAppNameConfiguration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $appName;
    public $appShortName;
    public $userId;
    public $action;
    public $tries = 3;
    public $timeout = 30;

    /**
     * Backup path for THIS run (so we restore the correct file on failure).
     */
    protected ?string $backupPath = null;

    /**
     * Create a new job instance.
     *
     * Pass the RAW values (e.g. 'Property Management System'), NOT quoted
     * strings. Quoting is handled internally by escapeEnvValue().
     */
    public function __construct(string $appName, string $appShortName, $userId, string $action = 'update')
    {
        $this->appName      = $appName;
        $this->appShortName = $appShortName;
        $this->userId       = $userId;
        $this->action       = $action;

        $this->onQueue('system_configuration');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $envData = [
            'APP_NAME'       => $this->appName,       // raw, un-quoted
            'APP_SHORT_NAME' => $this->appShortName,  // raw, un-quoted
        ];

        try {
            // 1. Idempotency: skip work if values already match.
            if ($this->valuesAlreadyMatch($envData)) {
                Log::info('UpdateAppNameConfiguration: values already up to date; skipping', [
                    'user_id' => $this->userId,
                    'action'  => $this->action,
                ]);
                return;
            }

            Log::info('Starting app name configuration update job', [
                'user_id'        => $this->userId,
                'action'         => $this->action,
                'app_name'       => $this->appName,
                'app_short_name' => $this->appShortName,
            ]);

            // 2. Clear caches before touching .env.
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            // 3. Backup .env (track path for this run).
            $this->backupPath = $this->backupEnvFile();

            if (!$this->backupPath) {
                throw new \Exception('Failed to backup .env file before app name update');
            }

            // 4. Write APP_NAME and APP_SHORT_NAME.
            $this->updateEnvFile($envData);

            // 5. Flush and (in prod) rebuild the config cache.
            Artisan::call('config:clear');

            if (app()->environment('production')) {
                Artisan::call('config:cache');
            }

            // 6. Reload env + rebind config in the running worker so
            //    config('app.name') reflects the new value immediately.
            $this->reloadConfigInMemory();

            // 7. Verify.
            $verificationResult = $this->verifyAppNameUpdate();

            if (!$verificationResult['success']) {
                throw new \Exception(
                    'App name update verification failed: ' . $verificationResult['message']
                );
            }

            Log::info('App name configuration update completed successfully', [
                'user_id'      => $this->userId,
                'action'       => $this->action,
                'verification' => $verificationResult,
            ]);

            // 8. Prune old backups.
            $this->cleanupOldBackups();

        } catch (\Throwable $e) {
            Log::error('App name configuration update job failed: ' . $e->getMessage(), [
                'user_id'  => $this->userId,
                'action'   => $this->action,
                'trace'    => $e->getTraceAsString(),
            ]);

            // Restore the backup made for THIS run, then re-clear caches.
            $this->restoreEnvBackup();

            throw $e;
        }
    }

    /**
     * Update .env file with app name configuration.
     */
    protected function updateEnvFile(array $data): bool
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found at: ' . $envPath);
        }

        if (!File::isWritable($envPath)) {
            throw new \Exception('.env file is not writable. Check file permissions.');
        }

        $envContent = File::get($envPath);
        $updated    = false;

        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $escapedValue = $this->escapeEnvValue($value);

            // Match KEY=, KEY =, KEY="...", KEY='...', KEY=value
            $pattern     = "/^{$key}\s*=\s*.*/m";
            $replacement = "{$key}={$escapedValue}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                Log::debug("Updated existing env variable: {$key}");
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
                Log::debug("Added new env variable: {$key}");
            }

            $updated = true;
        }

        if ($updated) {
            // Normalize line endings and trailing whitespace.
            $envContent = preg_replace('~\R~u', "\n", $envContent);
            $envContent = trim($envContent) . "\n";

            File::put($envPath, $envContent);

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            Log::info('Updated .env file with app name configuration', array_keys($data));
        } else {
            Log::warning('No changes made to .env file');
        }

        return $updated;
    }

    /**
     * Escape environment value. Single source of truth for quoting.
     */
    protected function escapeEnvValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if ($value === null || $value === '') {
            return '""';
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        // Quote when the value contains whitespace, quotes, or shell chars.
        if (preg_match('/[\s#"\'\\\\`$!*?\[\]{}|&;()<>]/', $value)) {
            $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            return '"' . $value . '"';
        }

        return $value;
    }

    /**
     * Verify APP_NAME / APP_SHORT_NAME were written correctly.
     */
    protected function verifyAppNameUpdate(): array
    {
        $appNameActual      = env('APP_NAME');
        $appShortNameActual = env('APP_SHORT_NAME');

        $appNameMatch      = $this->envValuesMatch($this->appName, $appNameActual);
        $appShortNameMatch = $this->envValuesMatch($this->appShortName, $appShortNameActual);

        if (!$appNameMatch || !$appShortNameMatch) {
            return [
                'success' => false,
                'message' => 'App name configuration mismatch',
                'details' => [
                    'app_name'       => ['expected' => $this->appName,      'actual' => $appNameActual],
                    'app_short_name' => ['expected' => $this->appShortName, 'actual' => $appShortNameActual],
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'App name configuration verified successfully',
            'details' => [
                'app_name'       => $appNameActual,
                'app_short_name' => $appShortNameActual,
            ],
        ];
    }

    /**
     * Tolerant env value comparison: ignores surrounding quotes & whitespace.
     */
    protected function envValuesMatch($expected, $actual): bool
    {
        if ($expected === $actual) {
            return true;
        }

        $normalize = function ($value) {
            if ($value === null) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            return trim((string) $value, " \t\n\r\0\x0B\"'");
        };

        return $normalize($expected) === $normalize($actual);
    }

    /**
     * Check whether .env already holds the target values (skip pointless writes).
     */
    protected function valuesAlreadyMatch(array $envData): bool
    {
        foreach ($envData as $key => $expected) {
            if (!$this->envValuesMatch($expected, env($key))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Backup .env file; return the backup path (or null on failure).
     */
    protected function backupEnvFile(): ?string
    {
        try {
            $envPath    = base_path('.env');
            $backupPath = base_path('.env.backup.appname.' . date('Y-m-d-H-i-s-u'));

            if (File::exists($envPath)) {
                File::copy($envPath, $backupPath);
                Log::info('.env file backed up to: ' . $backupPath);
                return $backupPath;
            }

            Log::warning('.env file not found for backup');
            return null;
        } catch (\Throwable $e) {
            Log::error('Failed to backup .env file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Restore the backup made for THIS run.
     */
    protected function restoreEnvBackup(): bool
    {
        try {
            $backupToRestore = $this->backupPath;

            if (!$backupToRestore || !File::exists($backupToRestore)) {
                // Fall back to the newest app-name backup.
                $backups = File::glob(base_path('.env.backup.appname.*'));
                if (!empty($backups)) {
                    $backupToRestore = max($backups);
                }
            }

            if ($backupToRestore && File::exists($backupToRestore)) {
                File::copy($backupToRestore, base_path('.env'));

                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }

                Artisan::call('config:clear');

                if (app()->environment('production')) {
                    Artisan::call('config:cache');
                }

                $this->reloadConfigInMemory();

                Log::info('Restored .env from app-name backup: ' . $backupToRestore);
                return true;
            }

            Log::warning('No app-name backups found to restore');
            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    protected function reloadConfigInMemory(): void
{
    try {
        if (function_exists('app')) {
            app()->loadEnvironmentFrom('.env');
        }

        if (!function_exists('app') || !app()->bound('config')) {
            return;
        }

        $currentConfig = app('config')->all();

        app()->forgetInstance('config');

        $cachedConfigPath = base_path('bootstrap/cache/config.php');

        if (File::exists($cachedConfigPath)) {
            $configArray = require $cachedConfigPath;
        } else {
            $configArray = $currentConfig;
            $configArray['app']['name']       = $this->appName;
            $configArray['app']['short_name'] = $this->appShortName;
        }

        app()->instance('config', new \Illuminate\Config\Repository($configArray));

    } catch (\Throwable $e) {
        Log::debug('Config rebind after app-name update skipped: ' . $e->getMessage());
    }
}

    /**
     * Keep only the newest 5 app-name backups.
     */
    protected function cleanupOldBackups(): void
    {
        try {
            $backups  = File::glob(base_path('.env.backup.appname.*'));
            $keepLast = 5;

            if (count($backups) > $keepLast) {
                sort($backups);
                $toDelete = array_slice($backups, 0, count($backups) - $keepLast);

                foreach ($toDelete as $backup) {
                    File::delete($backup);
                    Log::info('Deleted old app-name backup: ' . $backup);
                }

                Log::info('Cleaned up ' . count($toDelete) . ' old app-name backups');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to clean up old app-name backups: ' . $e->getMessage());
        }
    }

    /**
     * Handle permanent job failure. handle() already restored the backup
     * on exception, so we only log here — no double rollback.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('App name configuration update job failed permanently', [
            'user_id'   => $this->userId,
            'action'    => $this->action,
            'error'     => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}