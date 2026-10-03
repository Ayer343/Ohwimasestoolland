<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Services\EnvironmentConfigService;

class UpdateWhatsAppConfiguration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $config,      // Environment variables to update
        public string $provider,   // WhatsApp provider (twilio, vonage, etc.)
        public int $userId         // User who triggered the update
    ) {
        //
    }

    public function handle(EnvironmentConfigService $environmentService)
    {
        Log::info("Processing WhatsApp configuration update job", [
            'provider' => $this->provider,
            'user_id' => $this->userId,
            'config_keys' => array_keys($this->config)
        ]);

        try {
            // Clear all caches before making changes
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            // Backup current .env file
            $this->backupEnvFile();

            // Update .env file with new configuration
            $this->updateEnvFile($this->config);

            // Clear and cache config
            Artisan::call('config:clear');
            sleep(1); // Small delay to ensure file is written
            Artisan::call('config:cache');
            sleep(2); // Let cache take effect

            // Verify the update was successful
            $verification = $this->verifyUpdate();

            if ($verification['success']) {
                Log::info("WhatsApp configuration updated successfully for {$this->provider}", [
                    'user_id' => $this->userId,
                    'provider' => $this->provider,
                    'updated_variables' => $this->config,
                    'verification' => $verification
                ]);
            } else {
                Log::warning("WhatsApp configuration update verification failed", [
                    'user_id' => $this->userId,
                    'provider' => $this->provider,
                    'verification_errors' => $verification['errors']
                ]);
                
                // Restore backup if verification failed
                $this->restoreEnvBackup();
                throw new \Exception("Configuration update verification failed");
            }

        } catch (\Exception $e) {
            Log::error("WhatsApp configuration update job failed for {$this->provider}: " . $e->getMessage(), [
                'user_id' => $this->userId,
                'provider' => $this->provider,
                'config' => $this->config,
                'exception' => $e->getTraceAsString()
            ]);
            
            // Re-throw the exception so the job can be retried
            throw $e;
        }
    }

    /**
     * Backup .env file
     */
    protected function backupEnvFile(): string
    {
        try {
            $envPath = base_path('.env');
            $backupPath = base_path('.env.backup.whatsapp.' . date('Y-m-d-H-i-s'));

            if (file_exists($envPath)) {
                copy($envPath, $backupPath);
                Log::info('Environment file backed up', ['backup_path' => $backupPath]);
                return $backupPath;
            }
            
            return '';
        } catch (\Exception $e) {
            Log::warning('Failed to backup .env file: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Restore .env backup
     */
    protected function restoreEnvBackup(): bool
    {
        try {
            $backups = glob(base_path('.env.backup.whatsapp.*'));
            if (!empty($backups)) {
                $latestBackup = max($backups);
                copy($latestBackup, base_path('.env'));
                Log::info('Environment file restored from backup', ['backup_path' => $latestBackup]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update .env file
     */
    protected function updateEnvFile(array $data): bool
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            throw new \Exception('.env file not found at: ' . $envPath);
        }

        $envContent = file_get_contents($envPath);
        $updated = false;

        foreach ($data as $key => $value) {
            $escapedValue = $this->escapeEnvValue($value);
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$escapedValue}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                $updated = true;
            } else {
                // Add new variable if it doesn't exist
                $envContent .= "\n{$key}={$escapedValue}";
                $updated = true;
            }
        }

        if ($updated) {
            // Normalize line endings
            $envContent = preg_replace('~\R~u', "\n", $envContent);
            $envContent = trim($envContent) . "\n";
            
            file_put_contents($envPath, $envContent);
            
            // Clear opcache if enabled
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            Log::debug('Environment file updated', ['updated_keys' => array_keys($data)]);
        }

        return $updated;
    }

    /**
     * Escape environment value
     */
    protected function escapeEnvValue($value): string
    {
        // Handle boolean values
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return $value;
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
     * Verify the update was successful
     */
    protected function verifyUpdate(): array
    {
        $errors = [];
        
        // Reload environment to get updated values
        if (function_exists('app')) {
            app()->loadEnvironmentFrom('.env');
        }
        
        foreach ($this->config as $key => $expectedValue) {
            $actualValue = env($key);
            
            // Compare values (loose comparison for environment variables)
            if ($actualValue != $expectedValue) {
                $errors[] = [
                    'key' => $key,
                    'expected' => $expectedValue,
                    'actual' => $actualValue
                ];
            }
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors,
            'checked_variables' => count($this->config)
        ];
    }
}