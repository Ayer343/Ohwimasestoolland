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
use Illuminate\Support\Str;

class UpdateSystemConfiguration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $settings;
    public $envData;
    public $userId;
    public $action;
    public $tries = 3;
    public $timeout = 60;

    /**
     * Path to the .env backup created for THIS run.
     * Kept on the instance so we restore the correct file on failure.
     */
    protected ?string $backupPath = null;

    /**
     * Create a new job instance.
     */
    public function __construct($settings, $envData, $userId, $action = 'update')
    {
        $this->settings = $settings;
        $this->envData  = $envData;
        $this->userId   = $userId;
        $this->action   = $action;

        // Set queue specific to system configuration
        $this->onQueue('system_configuration');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
{
    if (empty($this->envData)) {
        Log::info('UpdateSystemConfiguration: nothing to write', [
            'user_id' => $this->userId,
            'action'  => $this->action,
        ]);
        return;
    }

    try {
        Log::info('Starting system configuration update job', [
            'user_id'  => $this->userId,
            'action'   => $this->action,
            'env_keys' => array_keys($this->envData),
        ]);

        // 1. Clear caches BEFORE writing so nothing stale is served.
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        // 2. Backup .env
        $this->backupPath = $this->backupEnvFile();
        if (!$this->backupPath) {
            throw new \Exception('Failed to backup .env file');
        }

        // 3. Write the values.
        $this->updateEnvFile($this->envData);

        // 4. Flush config cache (fast — file delete).
        Artisan::call('config:clear');

        // 5. Rebuild cache ONLY in production (expensive).
        if (app()->environment('production')) {
            Artisan::call('config:cache');
        }

        // 6. Reload env + rebind config IN-MEMORY for this worker process.
        $this->reloadConfigInMemory();

        // 7. Verify.
        $verification = $this->verifyEnvironmentUpdate();
        if (!$verification['success']) {
            throw new \Exception(
                'Environment update verification failed: ' . $verification['message']
            );
        }

        Log::info('System configuration update completed successfully', [
            'user_id'       => $this->userId,
            'action'        => $this->action,
            'verified_keys' => $verification['verified_keys'],
        ]);

        $this->cleanupOldBackups();

    } catch (\Throwable $e) {
        Log::error('System configuration update job failed: ' . $e->getMessage(), [
            'user_id'  => $this->userId,
            'action'   => $this->action,
            'env_data' => array_keys($this->envData),
            'trace'    => $e->getTraceAsString(),
        ]);

        $this->restoreEnvBackup();
        throw $e;
    }
}

    /**
     * Handle a job failure.
     *
     * handle() already restored the backup on exception, so this is
     * purely for logging / observability. Do NOT restore again here —
     * doing so could clobber a good state with an older backup.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('System configuration update job failed permanently', [
            'user_id'   => $this->userId,
            'action'    => $this->action,
            'error'     => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }

    /**
     * Backup .env file and return the backup path.
     */
    protected function backupEnvFile(): ?string
    {
        try {
            $envPath    = base_path('.env');
            $backupPath = base_path('.env.backup.system.' . date('Y-m-d-H-i-s-u'));

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
     * Restore the backup made for THIS run (if any).
     */
    protected function restoreEnvBackup(): bool
    {
        try {
            // Prefer the run-specific backup we tracked; fall back to newest.
            $backupToRestore = $this->backupPath;

            if (!$backupToRestore || !File::exists($backupToRestore)) {
                $backups = File::glob(base_path('.env.backup.system.*'));
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

                Log::info('Restored .env from backup: ' . $backupToRestore);
                return true;
            }

            Log::warning('No system backups found to restore');
            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update .env file with the given key/value pairs.
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
            // Skip invalid keys defensively.
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
            // Normalize line endings and trailing whitespace before writing.
            $envContent = preg_replace('~\R~u', "\n", $envContent);
            $envContent = trim($envContent) . "\n";

            File::put($envPath, $envContent);

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            Log::info('Updated .env file with system configuration', array_keys($data));
        } else {
            Log::warning('No changes made to .env file');
        }

        return $updated;
    }

    /**
     * Escape environment value.
     *
     * This is the single source of truth for quoting. Callers must NOT
     * pre-quote values (e.g. don't pass '"My App"'; pass 'My App').
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

        // Convert non-string scalars defensively.
        if (!is_string($value)) {
            $value = (string) $value;
        }

        // Values with whitespace, quotes, or shell-sensitive characters must
        // be double-quoted with internal quotes/backslashes escaped.
        if (preg_match('/[\s#"\'\\\\`$!*?\[\]{}|&;()<>]/', $value)) {
            $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            return '"' . $value . '"';
        }

        return $value;
    }

    /**
     * Verify every key in $this->envData was written correctly.
     */
    protected function verifyEnvironmentUpdate(): array
{
    $envPath = base_path('.env');
    $raw = File::get($envPath);
    $parsed = $this->parseEnvFile($raw);

    $updatedValues = [];
    $failedKeys = [];

    foreach ($this->envData as $key => $expected) {
        $actual = $parsed[$key] ?? null;
        $match = trim((string)$actual, " \t\n\r\0\x0B\"'") === trim((string)$expected, " \t\n\r\0\x0B\"'");
        $updatedValues[$key] = ['expected' => $expected, 'actual' => $actual, 'match' => $match];
        if (!$match) $failedKeys[] = $key;
    }

    return [
        'success' => empty($failedKeys),
        'message' => empty($failedKeys) ? 'All variables updated' : 'Failed: ' . implode(', ', $failedKeys),
        'verified_keys' => $updatedValues,
        'failed_keys' => $failedKeys,
    ];
}

protected function parseEnvFile(string $contents): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/i', $line, $m)) continue;
        $key = $m[1]; $val = $m[2];
        if (strlen($val) >= 2 && $val[0] === '"' && $val[-1] === '"') {
            $val = str_replace(['\\"','\\\\'], ['"','\\'], substr($val, 1, -1));
        } elseif (strlen($val) >= 2 && $val[0] === "'" && $val[-1] === "'") {
            $val = substr($val, 1, -1);
        }
        $out[$key] = $val;
    }
    return $out;
}

    /**
     * Compare env values tolerantly.
     *
     * Handles cases where the expected value was pre-quoted but env()
     * returns the un-quoted version (or vice-versa), and whitespace drift.
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
            $value = (string) $value;
            // Strip surrounding whitespace and any layer of quotes.
            return trim($value, " \t\n\r\0\x0B\"'");
        };

        return $normalize($expected) === $normalize($actual);
    }

    /**
 * Reload .env and rebind the config repository in the running worker so
 * config('app.name') (and friends) reflect the freshly written values.
 *
 * IMPORTANT: always re-bind 'config' after forgetting it. Returning early
 * after forgetInstance() leaves the container without a config binding,
 * which causes "Target class [config] does not exist".
 */
protected function reloadConfigInMemory(): void
{
    try {
        if (function_exists('app')) {
            app()->loadEnvironmentFrom('.env');
        }

        if (!function_exists('app') || !app()->bound('config')) {
            return;
        }

        // Capture the current config before forgetting it, so we have a
        // fallback if the cache file is missing.
        $currentConfig = app('config')->all();

        app()->forgetInstance('config');

        $cachedConfigPath = base_path('bootstrap/cache/config.php');

        if (File::exists($cachedConfigPath)) {
            // Use the freshly cached config if it exists.
            $configArray = require $cachedConfigPath;
        } else {
            // No cache file — rebuild config by re-running the framework's
            // config loader, then overlay the fresh .env values.
            $configArray = $currentConfig;

            // Re-apply env-backed values we just wrote so config('app.name')
            // reflects the new APP_NAME even without a cached file.
            if (isset($this->envData['APP_NAME'])) {
                $configArray['app']['name'] = $this->envData['APP_NAME'];
            }
            if (isset($this->envData['APP_SHORT_NAME'])) {
                $configArray['app']['short_name'] = $this->envData['APP_SHORT_NAME'];
            }
            if (isset($this->envData['MAIL_FROM_ADDRESS'])) {
                $configArray['mail']['from']['address'] = $this->envData['MAIL_FROM_ADDRESS'];
            }
        }

        app()->instance('config', new \Illuminate\Config\Repository($configArray));

    } catch (\Throwable $e) {
        // Non-fatal: the next request will read the new .env regardless.
        Log::debug('Config rebind after env update skipped: ' . $e->getMessage());
    }
}

    /**
     * Delete all but the newest N backups.
     */
    protected function cleanupOldBackups(): void
    {
        try {
            $backups  = File::glob(base_path('.env.backup.system.*'));
            $keepLast = 5;

            if (count($backups) > $keepLast) {
                sort($backups);
                $toDelete = array_slice($backups, 0, count($backups) - $keepLast);

                foreach ($toDelete as $backup) {
                    File::delete($backup);
                    Log::info('Deleted old system backup: ' . $backup);
                }

                Log::info('Cleaned up ' . count($toDelete) . ' old system backups');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to clean up old backups: ' . $e->getMessage());
        }
    }
}