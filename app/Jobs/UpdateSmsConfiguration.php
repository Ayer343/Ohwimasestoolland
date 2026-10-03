<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class UpdateSmsConfiguration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $provider;
    public $envData;
    public $userId;
    public $timeout = 60;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(string $provider, array $envData, ?int $userId = null)
    {
        $this->provider = $provider;
        $this->envData = $envData;
        $this->userId = $userId;
        
        // Set specific queue for SMS configuration updates
        $this->onQueue('sms-configuration');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting SMS configuration update job", [
            'provider' => $this->provider,
            'user_id' => $this->userId,
            'env_data_keys' => array_keys($this->envData)
        ]);

        try {
            // Clear all caches before making changes
            $this->clearCaches();

            // Backup current .env file
            $backupPath = $this->backupEnvFile();

            // Update .env file
            $this->updateEnvFile($this->envData);

            // Clear and cache config
            $this->rebuildConfigCache();

            // Verify the update was successful
            $verificationResult = $this->verifyEnvironmentUpdate();

            if ($verificationResult['success']) {
                Log::info("SMS configuration update completed successfully", [
                    'provider' => $this->provider,
                    'user_id' => $this->userId,
                    'verified_variables' => $verificationResult['verified_count']
                ]);

                // Clean up old backups (keep only last 5)
                $this->cleanupOldBackups();

            } else {
                Log::warning("SMS configuration update verification failed", [
                    'provider' => $this->provider,
                    'failed_verifications' => $verificationResult['failed']
                ]);

                // Restore backup if verification failed
                $this->restoreEnvBackup($backupPath);
                
                throw new \Exception("Configuration update verification failed. Changes reverted.");
            }

        } catch (\Exception $e) {
            Log::error("SMS configuration update job failed", [
                'provider' => $this->provider,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Re-throw to allow retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SMS configuration update job failed after all attempts", [
            'provider' => $this->provider,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Optionally notify admin or user about the failure
        // Notification::send($adminUsers, new SmsConfigUpdateFailed($this->provider, $exception->getMessage()));
    }

    /**
     * Clear application caches
     */
    protected function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        Log::debug("Caches cleared for SMS configuration update");
    }

    /**
     * Backup .env file
     */
    protected function backupEnvFile(): string
    {
        $envPath = base_path('.env');
        $backupPath = base_path('.env.backup.sms.' . date('Y-m-d-H-i-s') . '.job');
        
        if (File::exists($envPath)) {
            File::copy($envPath, $backupPath);
            Log::info("Environment file backed up", ['backup_path' => $backupPath]);
            return $backupPath;
        }
        
        throw new \Exception('Environment file not found for backup');
    }

    /**
     * Update .env file with new configuration
     */
    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        if (!File::isWritable($envPath)) {
            throw new \Exception('.env file is not writable');
        }

        $envContent = File::get($envPath);
        $updated = false;

        foreach ($data as $key => $value) {
            $escapedValue = $this->escapeEnvValue($value);
            
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$escapedValue}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                $updated = true;
                Log::debug("Updated environment variable", ['key' => $key, 'value' => $escapedValue]);
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
                $updated = true;
                Log::debug("Added new environment variable", ['key' => $key, 'value' => $escapedValue]);
            }
        }

        if ($updated) {
            // Normalize line endings and ensure proper formatting
            $envContent = preg_replace('~\R~u', "\n", $envContent);
            $envContent = trim($envContent) . "\n";
            
            File::put($envPath, $envContent);
            Log::info("Environment file updated with SMS configuration", [
                'provider' => $this->provider,
                'updated_variables' => count($data)
            ]);
        } else {
            Log::warning("No changes made to environment file");
        }
    }

    /**
     * Escape environment value properly
     */
    protected function escapeEnvValue($value): string
    {
        // Handle boolean values
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        // Handle numeric values
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        // Handle empty values
        if (empty($value) && $value !== '0') {
            return '""';
        }
        
        // If value contains spaces, quotes, or special characters, wrap in quotes
        if (preg_match('/[\\s#"\'\\\\`$!*?[\]{}|&;()<>]/', $value) || empty($value)) {
            $value = str_replace(['"', '\\'], ['\"', '\\\\'], $value);
            return '"' . $value . '"';
        }
        
        return $value;
    }

    /**
     * Rebuild configuration cache
     */
    protected function rebuildConfigCache(): void
    {
        // Small delay before rebuilding cache
        sleep(1);
        
        Artisan::call('config:cache');
        
        // Additional delay for cache to be fully rebuilt
        sleep(2);
        
        Log::debug("Configuration cache rebuilt");
    }

    /**
     * Verify that environment variables were updated correctly
     */
    protected function verifyEnvironmentUpdate(): array
    {
        $verified = [];
        $failed = [];

        foreach ($this->envData as $key => $expectedValue) {
            $actualValue = env($key);
            $expectedClean = $this->cleanEnvValue($expectedValue);
            $actualClean = $this->cleanEnvValue($actualValue);
            
            if ($actualClean === $expectedClean) {
                $verified[$key] = [
                    'expected' => $expectedValue,
                    'actual' => $actualValue
                ];
            } else {
                $failed[$key] = [
                    'expected' => $expectedValue,
                    'actual' => $actualValue,
                    'expected_clean' => $expectedClean,
                    'actual_clean' => $actualClean
                ];
            }
        }

        Log::info("Environment update verification", [
            'verified_count' => count($verified),
            'failed_count' => count($failed),
            'failed_keys' => array_keys($failed)
        ]);

        return [
            'success' => empty($failed),
            'verified_count' => count($verified),
            'failed_count' => count($failed),
            'verified' => $verified,
            'failed' => $failed
        ];
    }

    /**
     * Clean environment value for comparison
     */
    protected function cleanEnvValue($value)
    {
        if (is_string($value)) {
            // Remove surrounding quotes
            $value = trim($value, '"\'');
            // Convert boolean strings
            if ($value === 'true') return true;
            if ($value === 'false') return false;
        }
        return $value;
    }

    /**
     * Restore environment from backup
     */
    protected function restoreEnvBackup(string $backupPath): void
    {
        if (File::exists($backupPath)) {
            File::copy($backupPath, base_path('.env'));
            Log::info("Environment restored from backup due to verification failure", [
                'backup_path' => $backupPath
            ]);
            
            // Clear cache after restore
            $this->clearCaches();
        }
    }

    /**
     * Clean up old backup files (keep only last 5)
     */
    protected function cleanupOldBackups(): void
    {
        $backups = File::glob(base_path('.env.backup.sms.*.job'));
        $keepLast = 5;
        
        if (count($backups) > $keepLast) {
            sort($backups);
            $toDelete = array_slice($backups, 0, count($backups) - $keepLast);
            
            foreach ($toDelete as $backup) {
                File::delete($backup);
                Log::debug("Deleted old SMS backup", ['backup_path' => $backup]);
            }
            
            Log::info("Cleaned up old SMS backups", ['deleted_count' => count($toDelete)]);
        }
    }
}