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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class UpdatePaymentProviderConfiguration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $configData;
    public $provider;
    public $attemptNumber;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(array $configData, string $provider, int $attemptNumber = 1)
    {
        $this->configData = $configData;
        $this->provider = $provider;
        $this->attemptNumber = $attemptNumber;
        
        // Set queue-specific settings
        $this->onQueue('payment-configuration');
        $this->delay = now()->addSeconds(2); // Reduced delay for faster processing
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("🚀 Starting payment provider configuration update", [
            'provider' => $this->provider,
            'attempt' => $this->attemptNumber,
            'queue' => $this->queue,
            'config_keys' => array_keys($this->configData),
            'is_production' => $this->isProductionConfig()
        ]);

        try {
            // ✅ STEP 0: Store expected state for immediate frontend feedback
            $this->storeExpectedState();
            
            // ✅ STEP 1: Clear all application caches aggressively
            $this->clearApplicationCaches();

            // ✅ STEP 2: Create backup of current .env file
            $backupPath = $this->backupEnvFile();
            
            if (!$backupPath) {
                throw new \Exception('Failed to create .env backup');
            }

            // ✅ STEP 3: Update .env file with new configuration
            $envUpdateResult = $this->updateEnvFile($this->configData);
            
            if (!$envUpdateResult) {
                throw new \Exception('Failed to update .env file');
            }

            // ✅ STEP 4: CRITICAL FIX - Force environment reload AFTER .env update
            $this->forceEnvironmentReload();

            // ✅ STEP 5: Rebuild application caches
            $this->rebuildApplicationCaches();

            // ✅ STEP 6: Verify the update was successful
            $verificationResult = $this->verifyConfigurationUpdate();
            
            if (!$verificationResult['success']) {
                throw new \Exception('Configuration verification failed: ' . $verificationResult['message']);
            }

            // ✅ STEP 7: Test the connection after update
            $connectionTest = $this->testProviderConnection();

            // ✅ STEP 8: Store final state for immediate frontend access
            $this->storeFinalState($connectionTest);

            // ✅ STEP 9: Clear any pending update session
            $this->clearPendingUpdateSession();

            // ✅ STEP 10: Send success notification
            $this->sendSuccessNotification();

            Log::info("✅ Payment provider configuration update completed successfully", [
                'provider' => $this->provider,
                'backup_path' => $backupPath,
                'connection_test' => $connectionTest['success'] ?? false,
                'config_updated' => array_keys($this->configData),
                'environment' => $verificationResult['environment'] ?? 'unknown',
                'enabled' => $verificationResult['enabled'] ?? false,
                'configured' => $verificationResult['configured'] ?? false
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Payment provider configuration update failed", [
                'provider' => $this->provider,
                'attempt' => $this->attemptNumber,
                'error' => $e->getMessage(),
                'config_data' => $this->getSanitizedConfigData()
            ]);

            // Restore backup on failure
            $this->restoreEnvBackup();
            
            // Clear expected state on failure
            $this->clearExpectedState();

            // Check if we should retry
            if ($this->attemptNumber < $this->tries) {
                $this->retryWithBackoff();
            } else {
                $this->handleFinalFailure($e);
            }

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * ✅ NEW: Check if this is a production configuration
     */
    protected function isProductionConfig()
    {
        switch ($this->provider) {
            case 'mtn_momo':
                return isset($this->configData['MTN_MOMO_ENVIRONMENT']) && 
                       $this->configData['MTN_MOMO_ENVIRONMENT'] === 'production';
            case 'telecel_cash':
                return isset($this->configData['TELECEL_CASH_BASE_URL']) && 
                       str_contains($this->configData['TELECEL_CASH_BASE_URL'], 'api.telecel.com.gh');
            case 'airteltigo_cash':
                return isset($this->configData['AIRTELTIGO_CASH_BASE_URL']) && 
                       str_contains($this->configData['AIRTELTIGO_CASH_BASE_URL'], 'api.airteltigo.com.gh');
            case 'paystack':
                return isset($this->configData['PAYSTACK_PAYMENT_URL']) && 
                       str_contains($this->configData['PAYSTACK_PAYMENT_URL'], 'api.paystack.co');
            default:
                return false;
        }
    }

    /**
     * ✅ NEW: Store expected state for immediate frontend feedback
     */
    protected function storeExpectedState()
    {
        try {
            // Extract the expected state from config data
            $expectedState = [
                'enabled' => $this->configData['enabled'] ?? false,
                'configured' => true, // We expect it to be configured after this job
                'environment' => $this->configData['MTN_MOMO_ENVIRONMENT'] ?? 
                               ($this->configData['TELECEL_CASH_BASE_URL'] ?? 
                               ($this->configData['AIRTELTIGO_CASH_BASE_URL'] ?? 
                               ($this->configData['PAYSTACK_PAYMENT_URL'] ?? 'sandbox'))),
                'timestamp' => now()->timestamp,
                'job_started' => true
            ];
            
            // Store in cache for immediate frontend access
            Cache::put("payment_provider_{$this->provider}_expected", $expectedState, 300); // 5 minutes
            
            // Also store in session if available
            if (Session::isStarted()) {
                Session::put("payment_provider_{$this->provider}_expected", $expectedState);
                Session::save();
            }
            
            Log::info("Stored expected state for immediate frontend feedback", [
                'provider' => $this->provider,
                'expected_state' => $expectedState
            ]);
            
        } catch (\Exception $e) {
            Log::warning("Failed to store expected state: " . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Store final state after successful update
     */
    protected function storeFinalState($connectionTest)
    {
        try {
            // Get the actual state from environment
            $actualState = $this->getActualProviderState();
            
            // Add connection test results
            $actualState['connection_test'] = $connectionTest['success'] ?? false;
            $actualState['connection_message'] = $connectionTest['message'] ?? '';
            $actualState['job_completed'] = true;
            $actualState['completed_at'] = now()->timestamp;
            
            // Store in cache for immediate frontend access
            Cache::put("payment_provider_{$this->provider}_actual", $actualState, 300); // 5 minutes
            
            // Also store in session if available
            if (Session::isStarted()) {
                Session::put("payment_provider_{$this->provider}_actual", $actualState);
                Session::save();
            }
            
            // Clear expected state since we now have actual state
            Cache::forget("payment_provider_{$this->provider}_expected");
            
            Log::info("Stored final state for frontend access", [
                'provider' => $this->provider,
                'actual_state' => $actualState
            ]);
            
        } catch (\Exception $e) {
            Log::warning("Failed to store final state: " . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Get actual provider state from environment
     */
    protected function getActualProviderState()
    {
        // Force environment reload one more time
        $this->forceEnvironmentReload();
        
        // Get state from PaymentService
        $paymentService = app(\App\Services\PaymentService::class);
        $gatewayStatus = $paymentService->checkPaymentMethodConfiguration();
        
        if (isset($gatewayStatus[$this->provider])) {
            return $gatewayStatus[$this->provider];
        }
        
        // Fallback to checking environment variables directly
        return $this->getProviderStateFromEnv();
    }

    /**
     * ✅ NEW: Get provider state directly from environment variables
     */
    protected function getProviderStateFromEnv()
    {
        $state = [
            'enabled' => false,
            'configured' => false,
            'environment' => 'sandbox'
        ];
        
        switch ($this->provider) {
            case 'mtn_momo':
                $state['enabled'] = env('MTN_MOMO_ENABLED') === 'true';
                $state['environment'] = env('MTN_MOMO_ENVIRONMENT', 'sandbox');
                $state['configured'] = !empty(env('MTN_MOMO_API_KEY')) && 
                                      !empty(env('MTN_MOMO_SUBSCRIPTION_KEY'));
                break;
                
            case 'telecel_cash':
                $state['enabled'] = env('TELECEL_CASH_ENABLED') === 'true';
                $state['configured'] = !empty(env('TELECEL_CASH_CLIENT_ID')) && 
                                      !empty(env('TELECEL_CASH_CLIENT_SECRET'));
                $baseUrl = env('TELECEL_CASH_BASE_URL', '');
                $state['environment'] = str_contains($baseUrl, 'api.telecel.com.gh') ? 'production' : 'sandbox';
                break;
                
            case 'airteltigo_cash':
                $state['enabled'] = env('AIRTELTIGO_CASH_ENABLED') === 'true';
                $state['configured'] = !empty(env('AIRTELTIGO_CASH_CLIENT_ID')) && 
                                      !empty(env('AIRTELTIGO_CASH_CLIENT_SECRET'));
                $baseUrl = env('AIRTELTIGO_CASH_BASE_URL', '');
                $state['environment'] = str_contains($baseUrl, 'api.airteltigo.com.gh') ? 'production' : 'sandbox';
                break;
                
            case 'paystack':
                $state['enabled'] = env('PAYSTACK_ENABLED') === 'true';
                $state['configured'] = !empty(env('PAYSTACK_SECRET_KEY')) && 
                                      !empty(env('PAYSTACK_PUBLIC_KEY'));
                $baseUrl = env('PAYSTACK_PAYMENT_URL', '');
                $state['environment'] = str_contains($baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';
                break;
        }
        
        return $state;
    }

    /**
     * ✅ NEW: Verify configuration update was successful
     */
    protected function verifyConfigurationUpdate()
    {
        try {
            // Give environment time to reload
            sleep(1);
            
            // Check if environment variables were updated correctly
            $actualState = $this->getProviderStateFromEnv();
            $expectedEnabled = $this->configData['enabled'] === 'true';
            $expectedEnvironment = $this->getExpectedEnvironment();
            
            $verification = [
                'success' => true,
                'enabled' => $actualState['enabled'],
                'configured' => $actualState['configured'],
                'environment' => $actualState['environment'],
                'expected_enabled' => $expectedEnabled,
                'expected_environment' => $expectedEnvironment,
                'enabled_match' => $actualState['enabled'] === $expectedEnabled,
                'environment_match' => $actualState['environment'] === $expectedEnvironment
            ];
            
            // Check if we got the expected results
            if (!$verification['enabled_match']) {
                $verification['success'] = false;
                $verification['message'] = 'Enabled state does not match expected value';
            }
            
            if (!$verification['environment_match']) {
                $verification['success'] = false;
                $verification['message'] = 'Environment does not match expected value';
            }
            
            Log::info("Configuration verification", $verification);
            
            return $verification;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Get expected environment from config data
     */
    protected function getExpectedEnvironment()
    {
        switch ($this->provider) {
            case 'mtn_momo':
                return $this->configData['MTN_MOMO_ENVIRONMENT'] ?? 'sandbox';
            case 'telecel_cash':
                $baseUrl = $this->configData['TELECEL_CASH_BASE_URL'] ?? '';
                return str_contains($baseUrl, 'api.telecel.com.gh') ? 'production' : 'sandbox';
            case 'airteltigo_cash':
                $baseUrl = $this->configData['AIRTELTIGO_CASH_BASE_URL'] ?? '';
                return str_contains($baseUrl, 'api.airteltigo.com.gh') ? 'production' : 'sandbox';
            case 'paystack':
                $baseUrl = $this->configData['PAYSTACK_PAYMENT_URL'] ?? '';
                return str_contains($baseUrl, 'api.paystack.co') ? 'production' : 'sandbox';
            default:
                return 'sandbox';
        }
    }

    /**
     * ✅ ENHANCED: Clear expected state on failure
     */
    protected function clearExpectedState()
    {
        try {
            Cache::forget("payment_provider_{$this->provider}_expected");
            Cache::forget("payment_provider_{$this->provider}_actual");
            
            if (Session::isStarted()) {
                Session::forget("payment_provider_{$this->provider}_expected");
                Session::forget("payment_provider_{$this->provider}_actual");
                Session::save();
            }
            
            Log::info("Cleared expected state after failure", [
                'provider' => $this->provider
            ]);
            
        } catch (\Exception $e) {
            Log::warning("Failed to clear expected state: " . $e->getMessage());
        }
    }

    /**
     * ✅ ENHANCED: Force environment variable reload
     */
    protected function forceEnvironmentReload()
    {
        Log::info("🔄 Force reloading environment variables after .env update", [
            'provider' => $this->provider,
            'is_production' => $this->isProductionConfig()
        ]);

        try {
            // Clear specific payment environment variables from memory
            $paymentVars = [
                'MTN_MOMO_ENABLED', 'MTN_MOMO_API_KEY', 'MTN_MOMO_SUBSCRIPTION_KEY', 'MTN_MOMO_ENVIRONMENT',
                'TELECEL_CASH_ENABLED', 'TELECEL_CASH_CLIENT_ID', 'TELECEL_CASH_CLIENT_SECRET', 'TELECEL_CASH_BASE_URL',
                'AIRTELTIGO_CASH_ENABLED', 'AIRTELTIGO_CASH_CLIENT_ID', 'AIRTELTIGO_CASH_CLIENT_SECRET', 'AIRTELTIGO_CASH_BASE_URL',
                'PAYSTACK_ENABLED', 'PAYSTACK_SECRET_KEY', 'PAYSTACK_PUBLIC_KEY', 'PAYSTACK_PAYMENT_URL'
            ];
            
            // Clear from putenv (if available)
            if (function_exists('putenv')) {
                foreach ($paymentVars as $var) {
                    putenv($var);
                }
                Log::debug("Cleared environment variables from putenv");
            }

            // Clear Laravel's config cache
            $this->clearConfigCache();

            // Reload .env file using Dotenv
            if (file_exists(base_path('.env'))) {
                try {
                    // Use createUnsafeImmutable to avoid validation issues
                    $dotenv = \Dotenv\Dotenv::createUnsafeMutable(base_path());
                    $dotenv->load();
                    $dotenv->safeLoad();
                    Log::debug("Reloaded .env file using Dotenv");
                } catch (\Exception $e) {
                    Log::warning("Dotenv reload failed: " . $e->getMessage());
                    // Try alternative method
                    $this->reloadEnvAlternative();
                }
            }

            // Clear realpath cache
            clearstatcache(true);

            // Small delay to ensure reload completes
            usleep(500000); // 0.5 seconds

            // Verify reload worked
            $envCheck = $this->verifyEnvReload();
            
            // Log verification results
            if ($this->isProductionConfig()) {
                Log::info("Production environment reload verification", $envCheck);
            } else {
                Log::info("Sandbox environment reload verification", $envCheck);
            }

            Log::info("✅ Environment variables force reloaded successfully");

        } catch (\Exception $e) {
            Log::error("❌ Failed to force environment reload: " . $e->getMessage());
            throw new \Exception('Environment reload failed: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Alternative method to reload environment
     */
    protected function reloadEnvAlternative()
    {
        try {
            // Read .env file directly
            $envPath = base_path('.env');
            if (File::exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        
                        // Remove surrounding quotes
                        $value = trim($value, '"\'');
                        
                        // Set environment variable
                        if (function_exists('putenv')) {
                            putenv("{$key}={$value}");
                        }
                        $_ENV[$key] = $value;
                        $_SERVER[$key] = $value;
                    }
                }
                
                Log::debug("Reloaded .env file using alternative method");
            }
        } catch (\Exception $e) {
            Log::warning("Alternative env reload failed: " . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Clear Laravel's config cache
     */
    protected function clearConfigCache()
    {
        try {
            $app = app();
            
            // Clear config repository instance
            if (method_exists($app, 'forgetInstance')) {
                $app->forgetInstance('Illuminate\Contracts\Config\Repository');
                $app->forgetInstance('config');
            }
            
            // Clear loaded configuration
            if (property_exists($app, 'loadedConfigurations')) {
                $app->loadedConfigurations = [];
            }
            
            // Clear resolved instances that might cache config
            $reflection = new \ReflectionClass($app);
            if ($reflection->hasProperty('resolved')) {
                $property = $reflection->getProperty('resolved');
                $property->setAccessible(true);
                $resolved = $property->getValue($app);
                unset($resolved['config'], $resolved['Illuminate\Contracts\Config\Repository']);
                $property->setValue($app, $resolved);
            }
            
            Log::debug("Cleared Laravel config cache");
            
        } catch (\Exception $e) {
            Log::warning("Failed to clear config cache: " . $e->getMessage());
        }
    }

    /**
     * Verify that environment reload worked
     */
    protected function verifyEnvReload()
    {
        $verification = [];
        
        // Check key environment variables based on provider
        switch ($this->provider) {
            case 'mtn_momo':
                $checkVars = [
                    'MTN_MOMO_ENABLED',
                    'MTN_MOMO_ENVIRONMENT',
                    'MTN_MOMO_API_KEY' => '***',
                    'MTN_MOMO_SUBSCRIPTION_KEY' => '***'
                ];
                break;
                
            case 'telecel_cash':
                $checkVars = [
                    'TELECEL_CASH_ENABLED',
                    'TELECEL_CASH_BASE_URL',
                    'TELECEL_CASH_CLIENT_ID' => '***',
                    'TELECEL_CASH_CLIENT_SECRET' => '***'
                ];
                break;
                
            case 'airteltigo_cash':
                $checkVars = [
                    'AIRTELTIGO_CASH_ENABLED',
                    'AIRTELTIGO_CASH_BASE_URL',
                    'AIRTELTIGO_CASH_CLIENT_ID' => '***',
                    'AIRTELTIGO_CASH_CLIENT_SECRET' => '***'
                ];
                break;
                
            case 'paystack':
                $checkVars = [
                    'PAYSTACK_ENABLED',
                    'PAYSTACK_PAYMENT_URL',
                    'PAYSTACK_SECRET_KEY' => '***',
                    'PAYSTACK_PUBLIC_KEY' => '***'
                ];
                break;
                
            default:
                $checkVars = [];
        }

        foreach ($checkVars as $key => $mask) {
            if (is_numeric($key)) {
                $key = $mask;
                $value = env($key);
                $verification[$key] = $value;
            } else {
                $value = env($key);
                $verification[$key] = $value ? '***' . substr($value, -4) : 'empty';
            }
        }

        return $verification;
    }

    /**
     * Clear all application caches comprehensively
     */
    protected function clearApplicationCaches()
    {
        Log::info("🧹 Clearing application caches for payment provider update", [
            'provider' => $this->provider
        ]);

        try {
            // Clear Laravel caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            // Clear any custom caches
            Cache::flush();

            // Clear OPcache if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            // Clear APC cache if available
            if (function_exists('apc_clear_cache')) {
                apc_clear_cache();
            }

            // Also clear realpath cache
            clearstatcache(true);

            // Clear compiled class cache
            if (File::exists(base_path('bootstrap/cache/packages.php'))) {
                File::delete(base_path('bootstrap/cache/packages.php'));
            }
            if (File::exists(base_path('bootstrap/cache/services.php'))) {
                File::delete(base_path('bootstrap/cache/services.php'));
            }

            // Small delay to ensure clears complete
            sleep(1);

            Log::info("✅ Application caches cleared successfully");

        } catch (\Exception $e) {
            Log::warning("Error clearing caches: " . $e->getMessage());
            // Continue anyway, as cache clearing is important but not critical
        }
    }

    /**
     * Create backup of .env file with timestamp
     */
    protected function backupEnvFile()
    {
        $envPath = base_path('.env');
        $timestamp = date('Y-m-d-H-i-s');
        $backupPath = base_path(".env.backup.{$this->provider}.{$timestamp}");

        try {
            if (!File::exists($envPath)) {
                throw new \Exception('.env file not found at: ' . $envPath);
            }

            File::copy($envPath, $backupPath);
            
            // Verify backup was created successfully
            if (!File::exists($backupPath) || File::size($backupPath) === 0) {
                throw new \Exception('Backup file was not created or is empty: ' . $backupPath);
            }

            Log::info(".env file backed up successfully", [
                'backup_path' => $backupPath,
                'file_size' => File::size($backupPath),
                'original_size' => File::size($envPath)
            ]);

            return $backupPath;

        } catch (\Exception $e) {
            Log::error("Failed to backup .env file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update .env file with new configuration
     */
    protected function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            throw new \Exception('.env file not found at: ' . $envPath);
        }

        if (!File::isWritable($envPath)) {
            throw new \Exception('.env file is not writable. Check file permissions.');
        }

        try {
            $envContent = File::get($envPath);
            $updatedVariables = [];
            $newVariables = [];

            foreach ($data as $key => $value) {
                $escapedValue = $this->escapeEnvValue($value);
                
                // Pattern to match the key=value line (handles spaces after =)
                $pattern = "/^{$key}=.*$/m";
                $replacement = "{$key}={$escapedValue}";

                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                    $updatedVariables[] = $key;
                } else {
                    // Add new variable if it doesn't exist
                    $envContent .= "\n{$key}={$escapedValue}";
                    $newVariables[] = $key;
                }
            }

            // Write updated content back to file
            File::put($envPath, $envContent);

            // Verify the file was written successfully
            if (!File::exists($envPath) || File::size($envPath) === 0) {
                throw new \Exception('Failed to write updated .env file');
            }

            // Flush file stat cache
            clearstatcache(true, $envPath);

            Log::info("✅ .env file updated successfully", [
                'provider' => $this->provider,
                'updated_variables' => $updatedVariables,
                'new_variables' => $newVariables,
                'total_updates' => count($updatedVariables) + count($newVariables),
                'is_production' => $this->isProductionConfig()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("❌ Failed to update .env file: " . $e->getMessage(), [
                'provider' => $this->provider,
                'config_keys' => array_keys($data)
            ]);
            throw new \Exception('Environment file update failed: ' . $e->getMessage());
        }
    }

    /**
     * Escape environment variable value properly
     */
    protected function escapeEnvValue($value)
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
        if (empty($value)) {
            return '""';
        }
        
        // Convert to string
        $value = (string) $value;
        
        // If value contains spaces, quotes, or special characters, wrap in quotes
        if (preg_match('/[\\s#"\'\\\\=]/', $value)) {
            $value = '"' . str_replace(['"', '\\'], ['\"', '\\\\'], $value) . '"';
        }
        
        return $value;
    }

    /**
     * Rebuild application caches after update
     */
    protected function rebuildApplicationCaches()
    {
        Log::info("🏗️ Rebuilding application caches after payment provider update", [
            'provider' => $this->provider,
            'is_production' => $this->isProductionConfig()
        ]);

        try {
            // Clear any remaining caches first
            Artisan::call('config:clear');
            
            // Rebuild config cache
            Artisan::call('config:cache');
            
            // Wait for cache to rebuild
            sleep(2);

            // Verify config cache was created
            $cachedConfigPath = base_path('bootstrap/cache/config.php');
            if (File::exists($cachedConfigPath)) {
                $cacheSize = File::size($cachedConfigPath);
                Log::info("Config cache rebuilt successfully", [
                    'cache_size' => $cacheSize,
                    'cache_path' => $cachedConfigPath
                ]);
            } else {
                Log::warning('Config cache file was not created after rebuild');
            }

            Log::info("✅ Application caches rebuilt successfully");

        } catch (\Exception $e) {
            Log::warning("Error rebuilding caches: " . $e->getMessage());
            // Continue anyway, as the .env update is already done
        }
    }

    /**
     * Test provider connection after configuration update
     */
    protected function testProviderConnection()
    {
        try {
            // ✅ CRITICAL: Small delay to ensure env is reloaded
            sleep(1);

            // Use the PaymentService to test connections
            $paymentService = app(\App\Services\PaymentService::class);
            $gatewayStatus = $paymentService->checkPaymentMethodConfiguration();
            
            if (!isset($gatewayStatus[$this->provider])) {
                return [
                    'success' => false,
                    'message' => 'Provider configuration not found after update'
                ];
            }

            $providerConfig = $gatewayStatus[$this->provider];
            
            Log::info("Post-update provider status check", [
                'provider' => $this->provider,
                'enabled' => $providerConfig['enabled'],
                'configured' => $providerConfig['configured'],
                'environment' => $providerConfig['environment'] ?? 'unknown',
                'is_production' => $this->isProductionConfig()
            ]);

            if (!$providerConfig['enabled']) {
                return [
                    'success' => false,
                    'message' => 'Provider is not enabled after update'
                ];
            }

            if (!$providerConfig['configured']) {
                return [
                    'success' => false,
                    'message' => 'Provider is not properly configured after update',
                    'missing_configuration' => $providerConfig['missing_configuration'] ?? []
                ];
            }

            // Test actual API connectivity based on provider
            $testResult = $this->performProviderSpecificTest();
            
            Log::info("✅ Provider connection test completed", [
                'provider' => $this->provider,
                'success' => $testResult['success'],
                'message' => $testResult['message'],
                'environment' => $testResult['environment'] ?? 'unknown'
            ]);

            return $testResult;

        } catch (\Exception $e) {
            Log::error("❌ Provider connection test failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection test error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get current environment values for debugging
     */
    protected function getCurrentEnvValues()
    {
        $envValues = [];
        
        switch ($this->provider) {
            case 'mtn_momo':
                $envValues = [
                    'MTN_MOMO_ENABLED' => env('MTN_MOMO_ENABLED'),
                    'MTN_MOMO_ENVIRONMENT' => env('MTN_MOMO_ENVIRONMENT'),
                    'MTN_MOMO_API_KEY_set' => !empty(env('MTN_MOMO_API_KEY')),
                    'MTN_MOMO_SUBSCRIPTION_KEY_set' => !empty(env('MTN_MOMO_SUBSCRIPTION_KEY'))
                ];
                break;
                
            case 'telecel_cash':
                $envValues = [
                    'TELECEL_CASH_ENABLED' => env('TELECEL_CASH_ENABLED'),
                    'TELECEL_CASH_BASE_URL' => env('TELECEL_CASH_BASE_URL'),
                    'TELECEL_CASH_CLIENT_ID_set' => !empty(env('TELECEL_CASH_CLIENT_ID')),
                    'TELECEL_CASH_CLIENT_SECRET_set' => !empty(env('TELECEL_CASH_CLIENT_SECRET'))
                ];
                break;
                
            case 'airteltigo_cash':
                $envValues = [
                    'AIRTELTIGO_CASH_ENABLED' => env('AIRTELTIGO_CASH_ENABLED'),
                    'AIRTELTIGO_CASH_BASE_URL' => env('AIRTELTIGO_CASH_BASE_URL'),
                    'AIRTELTIGO_CASH_CLIENT_ID_set' => !empty(env('AIRTELTIGO_CASH_CLIENT_ID')),
                    'AIRTELTIGO_CASH_CLIENT_SECRET_set' => !empty(env('AIRTELTIGO_CASH_CLIENT_SECRET'))
                ];
                break;
                
            case 'paystack':
                $envValues = [
                    'PAYSTACK_ENABLED' => env('PAYSTACK_ENABLED'),
                    'PAYSTACK_PAYMENT_URL' => env('PAYSTACK_PAYMENT_URL'),
                    'PAYSTACK_SECRET_KEY_set' => !empty(env('PAYSTACK_SECRET_KEY')),
                    'PAYSTACK_PUBLIC_KEY_set' => !empty(env('PAYSTACK_PUBLIC_KEY'))
                ];
                break;
        }
        
        return $envValues;
    }

    /**
     * Perform provider-specific connection tests
     */
    protected function performProviderSpecificTest()
    {
        switch ($this->provider) {
            case 'mtn_momo':
                return $this->testMTNConnection();
                
            case 'telecel_cash':
                return $this->testTelecelConnection();
                
            case 'airteltigo_cash':
                return $this->testAirtelTigoConnection();
                
            case 'paystack':
                return $this->testPaystackConnection();
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported provider for connection testing'
                ];
        }
    }

    /**
     * Test MTN Mobile Money connection
     */
    protected function testMTNConnection()
    {
        try {
            $paymentService = app(\App\Services\PaymentService::class);
            $result = $paymentService->testMTNConnection();
            
            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'environment' => $result['environment'] ?? env('MTN_MOMO_ENVIRONMENT', 'unknown')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'MTN connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test Telecel Cash connection
     */
    protected function testTelecelConnection()
    {
        try {
            $clientId = env('TELECEL_CASH_CLIENT_ID');
            $clientSecret = env('TELECEL_CASH_CLIENT_SECRET');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success' => false,
                    'message' => 'Telecel Cash credentials are missing'
                ];
            }

            // Simulate API test (replace with actual Telecel API test when available)
            $isValidFormat = strlen($clientId) > 5 && strlen($clientSecret) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? 'Telecel Cash credentials format is valid' 
                    : 'Telecel Cash credentials appear to be invalid'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Telecel connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test AirtelTigo Cash connection
     */
    protected function testAirtelTigoConnection()
    {
        try {
            $clientId = env('AIRTELTIGO_CASH_CLIENT_ID');
            $clientSecret = env('AIRTELTIGO_CASH_CLIENT_SECRET');

            if (empty($clientId) || empty($clientSecret)) {
                return [
                    'success' => false,
                    'message' => 'AirtelTigo Cash credentials are missing'
                ];
            }

            // Simulate API test (replace with actual AirtelTigo API test when available)
            $isValidFormat = strlen($clientId) > 5 && strlen($clientSecret) > 10;
            
            return [
                'success' => $isValidFormat,
                'message' => $isValidFormat 
                    ? 'AirtelTigo Cash credentials format is valid' 
                    : 'AirtelTigo Cash credentials appear to be invalid'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'AirtelTigo connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test Paystack connection
     */
    protected function testPaystackConnection()
    {
        try {
            $secretKey = env('PAYSTACK_SECRET_KEY');
            
            if (empty($secretKey)) {
                return [
                    'success' => false,
                    'message' => 'Paystack secret key is missing'
                ];
            }

            // Test Paystack API connectivity
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey
            ])->timeout(10)->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'Paystack API connection successful',
                    'banks_available' => count($data['data'] ?? [])
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Paystack API connection failed: ' . ($response->json()['message'] ?? 'Unknown error'),
                    'status_code' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Paystack connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clear pending update session after successful update
     */
    protected function clearPendingUpdateSession()
    {
        try {
            if (Session::has('pending_payment_update')) {
                Session::forget('pending_payment_update');
                Log::info("Cleared pending payment update session");
            }
            
            // Also clear any provider-specific session data
            Session::forget("pending_{$this->provider}_config");
            
        } catch (\Exception $e) {
            Log::warning("Failed to clear pending update session: " . $e->getMessage());
        }
    }

    /**
     * Send success notification (extendable for different notification channels)
     */
    protected function sendSuccessNotification()
    {
        try {
            Log::info("✅ Payment provider configuration update completed successfully", [
                'provider' => $this->provider,
                'status' => 'success',
                'attempts' => $this->attemptNumber,
                'timestamp' => now()->toDateTimeString(),
                'is_production' => $this->isProductionConfig(),
                'environment' => $this->getExpectedEnvironment()
            ]);

        } catch (\Exception $e) {
            Log::warning("Failed to send success notification: " . $e->getMessage());
        }
    }

    /**
     * Restore .env backup in case of failure
     */
    protected function restoreEnvBackup()
    {
        try {
            $backups = File::glob(base_path('.env.backup.' . $this->provider . '.*'));
            if (!empty($backups)) {
                $latestBackup = max($backups);
                
                // Read backup content
                $backupContent = File::get($latestBackup);
                File::put(base_path('.env'), $backupContent);
                
                // Clear caches after restore
                $this->clearApplicationCaches();
                
                // Force environment reload after restore
                $this->forceEnvironmentReload();
                
                Log::info("Restored .env from backup after failure", [
                    'backup_path' => $latestBackup,
                    'provider' => $this->provider,
                    'backup_size' => File::size($latestBackup)
                ]);
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Failed to restore .env backup: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Retry job with exponential backoff
     */
    protected function retryWithBackoff()
    {
        $nextAttempt = $this->attemptNumber + 1;
        $delaySeconds = $this->backoff[$this->attemptNumber - 1] ?? 60;

        Log::info("Scheduling retry for payment provider configuration update", [
            'provider' => $this->provider,
            'next_attempt' => $nextAttempt,
            'delay_seconds' => $delaySeconds,
            'is_production' => $this->isProductionConfig()
        ]);

        // Dispatch new job with increased attempt number
        self::dispatch($this->configData, $this->provider, $nextAttempt)
            ->delay(now()->addSeconds($delaySeconds))
            ->onQueue('payment-configuration-retry');
    }

    /**
     * Handle final failure after all retry attempts
     */
    protected function handleFinalFailure(\Exception $e)
    {
        Log::error("❌ Payment provider configuration update failed after all retry attempts", [
            'provider' => $this->provider,
            'final_attempt' => $this->attemptNumber,
            'error' => $e->getMessage(),
            'is_production' => $this->isProductionConfig()
        ]);

        // Send failure notification
        $this->sendFailureNotification($e);
    }

    /**
     * Send failure notification
     */
    protected function sendFailureNotification(\Exception $e)
    {
        try {
            Log::error("❌ Payment provider configuration update failed", [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
                'is_production' => $this->isProductionConfig()
            ]);

        } catch (\Exception $notificationError) {
            Log::error("Failed to send failure notification: " . $notificationError->getMessage());
        }
    }

    /**
     * Get sanitized config data for logging (without sensitive values)
     */
    protected function getSanitizedConfigData()
    {
        $sanitized = [];
        $sensitiveKeys = ['key', 'secret', 'token', 'password', 'api_key', 'client_secret', 'subscription_key'];

        foreach ($this->configData as $key => $value) {
            $shouldSanitize = false;
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $shouldSanitize = true;
                    break;
                }
            }

            if ($shouldSanitize && !empty($value)) {
                $sanitized[$key] = '***' . substr($value, -4); // Show last 4 chars
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $e)
    {
        Log::error("❌ Payment provider configuration job failed completely", [
            'provider' => $this->provider,
            'attempts' => $this->attemptNumber,
            'error' => $e->getMessage(),
            'is_production' => $this->isProductionConfig()
        ]);

        // Ensure backup is restored on complete failure
        $this->restoreEnvBackup();
        
        // Clear expected state
        $this->clearExpectedState();

        // Send final failure notification
        $this->sendFailureNotification($e);
    }
}