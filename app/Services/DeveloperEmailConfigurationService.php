<?php

namespace App\Services;

use App\Models\DeveloperSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeveloperEmailConfigurationService
{
    /**
     * Save developer SMTP configuration with FIXED encryption handling
     */
    public function saveDeveloperSmtpConfig(array $config)
    {
        Log::debug('=== SAVE DEVELOPER SMTP CONFIG START ===', array_keys($config));
        
        try {
            // Validate required fields
            $validationResult = $this->validateConfiguration($config);
            if (!$validationResult['valid']) {
                throw new \Exception($validationResult['message']);
            }
            
            // =============================================
            // 1. FIXED: HANDLE PASSWORD CORRECTLY
            // =============================================
            $originalPassword = $config['password'];
            
            // Check if it's a Gmail App Password
            $gmailHint = $this->checkGmailAppPassword($config);
            if ($gmailHint) {
                Log::info('Gmail App Password detected', $gmailHint);
            }
            
            // =============================================
            // 2. UPDATE .ENV FILE WITH PLAIN TEXT PASSWORD
            // =============================================
            $envUpdates = $this->prepareEnvUpdates($config);
            
            Log::debug('Preparing to update .env with:', array_keys($envUpdates));
            
            // Backup .env file before making changes
            $backupResult = $this->backupEnvFile();
            if (!$backupResult['success']) {
                Log::warning('Failed to backup .env: ' . $backupResult['message']);
            }
            
            $updateResult = $this->updateDeveloperEnvFile($envUpdates);
            
            if (!$updateResult['success']) {
                throw new \Exception('Failed to update .env file: ' . $updateResult['message']);
            }
            
            // =============================================
            // 3. CLEAR LARAVEL CONFIG CACHE
            // =============================================
            Artisan::call('config:clear');
            Artisan::call('config:cache');
            
            // =============================================
            // 4. FIXED: SAVE TO DATABASE WITH CORRECT COLUMN NAME
            // =============================================
            $savedConfig = $this->saveToDatabaseFixed($config);
            
            // Clear settings cache
            DeveloperSetting::clearCache();
            
            // =============================================
            // 5. TEST THE NEW CONFIGURATION
            // =============================================
            $testResult = $this->testNewConfiguration($config);
            
            // =============================================
            // 6. LOG SUCCESS WITH PASSWORD FORMAT INFO
            // =============================================
            Log::info('Developer SMTP configuration saved successfully', [
                'updated_keys' => array_keys($envUpdates),
                'env_updated' => true,
                'database_saved' => true,
                'test_result' => $testResult['success'],
                'password_length' => strlen($originalPassword),
                'is_gmail_app_password' => $this->isGmailAppPasswordFormat($originalPassword),
                'timestamp' => now()->toISOString(),
                'developer' => auth()->user()->email ?? 'unknown',
                'ip_address' => request()->ip()
            ]);
            
            return [
                'success' => true,
                'message' => 'Developer SMTP configuration saved successfully',
                'config' => $savedConfig,
                'env_updated' => true,
                'test_result' => $testResult,
                'backup_created' => $backupResult['success'],
                'gmail_hint' => $gmailHint,
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to save developer SMTP config: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'config_data' => $this->maskSensitiveConfig($config)
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to save developer SMTP configuration: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
    
    /**
     * FIXED: Save configuration to database with correct column names
     */
    private function saveToDatabaseFixed(array $config)
    {
        DB::beginTransaction();
        
        try {
            $settings = DeveloperSetting::first();
            
            if (!$settings) {
                $settings = new DeveloperSetting();
            }
            
            // Map config to database columns
            $dbData = [
                'developer_smtp_host' => $config['host'] ?? null,
                'developer_smtp_port' => $config['port'] ?? 587,
                'developer_smtp_username' => $config['username'] ?? null,
                'developer_smtp_encryption' => $config['encryption'] ?? 'tls',
                'developer_email_from' => $config['from_address'] ?? null,
                'developer_email_from_name' => $config['from_name'] ?? null,
                'updated_at' => now(),
            ];
            
            // Encrypt password if provided and not masked
            if (isset($config['password']) && !$this->isMaskedPassword($config['password'])) {
                $dbData['developer_smtp_password'] = Crypt::encryptString($config['password']);
            }
            
            // Update or create settings
            if ($settings->exists) {
                $settings->update($dbData);
            } else {
                $settings->fill($dbData);
                $settings->save();
            }
            
            // Save additional configuration for reference
            $additionalConfig = [
                'last_modified_by' => auth()->user()->email ?? 'system',
                'last_modified_at' => now()->toISOString(),
                'config_version' => '2.0',
                'host' => $config['host'] ?? null,
                'port' => $config['port'] ?? 587,
                'username' => $config['username'] ?? null,
                'encryption' => $config['encryption'] ?? 'tls',
                'from_address' => $config['from_address'] ?? null,
                'from_name' => $config['from_name'] ?? null,
            ];
            
            $settings->additional_config = json_encode($additionalConfig);
            $settings->save();
            
            DB::commit();
            
            // Return sanitized config (without password)
            $savedConfig = $dbData;
            $savedConfig['developer_smtp_password'] = '[ENCRYPTED]';
            $savedConfig['additional_config'] = $additionalConfig;
            
            return $savedConfig;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save to database: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * FIXED: Get decrypted password for email sending
     */
    public function getDecryptedPassword()
    {
        try {
            // First try from database (encrypted) - FIXED COLUMN NAME
            $settings = DeveloperSetting::first();
            
            if ($settings && !empty($settings->developer_smtp_password)) {
                try {
                    return Crypt::decryptString($settings->developer_smtp_password);
                } catch (\Exception $e) {
                    Log::warning('Failed to decrypt database password: ' . $e->getMessage());
                    // Fall through to .env
                }
            }
            
            // Fallback to .env (plain text)
            $envPassword = env('DEVELOPER_MAIL_PASSWORD', '');
            
            if (empty($envPassword)) {
                Log::debug('No password found in database or .env');
                return '';
            }
            
            // Check if .env password is encrypted (base64 looking)
            if (base64_encode(base64_decode($envPassword, true)) === $envPassword) {
                // Looks encrypted, try to decrypt
                try {
                    return Crypt::decryptString($envPassword);
                } catch (\Exception $e) {
                    Log::warning('Failed to decrypt .env password: ' . $e->getMessage());
                    return $envPassword; // Return as plain text if decryption fails
                }
            }
            
            // Plain text in .env
            return $envPassword;
            
        } catch (\Exception $e) {
            Log::error('Failed to get decrypted password: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * FIXED: Get current developer email configuration
     */
    public function getCurrentDeveloperConfig()
    {
        $config = [];
        
        // Try to get from database first
        $settings = DeveloperSetting::first();
        
        if ($settings && !empty($settings->developer_smtp_host)) {
            $config = [
                // SMTP Configuration
                'DEVELOPER_MAIL_HOST' => $settings->developer_smtp_host,
                'DEVELOPER_MAIL_PORT' => $settings->developer_smtp_port,
                'DEVELOPER_MAIL_USERNAME' => $settings->developer_smtp_username,
                'DEVELOPER_MAIL_PASSWORD' => '[ENCRYPTED IN DATABASE]',
                'DEVELOPER_MAIL_ENCRYPTION' => $settings->developer_smtp_encryption,
                'DEVELOPER_MAIL_FROM_ADDRESS' => $settings->developer_email_from,
                'DEVELOPER_MAIL_FROM_NAME' => $settings->developer_email_from_name,
                
                'source' => 'database',
                'last_modified' => $settings->updated_at->format('Y-m-d H:i:s'),
                'has_password' => !empty($settings->developer_smtp_password),
            ];
            
            // Try to get decrypted password for actual use
            $decryptedPassword = $this->getDecryptedPassword();
            if (!empty($decryptedPassword)) {
                $config['DEVELOPER_MAIL_PASSWORD_DECRYPTED'] = '[DECRYPTED]';
                $config['password_length'] = strlen($decryptedPassword);
            }
        } else {
            // Fallback to .env if database config is incomplete
            $config = [
                // SMTP Configuration
                'DEVELOPER_MAIL_HOST' => env('DEVELOPER_MAIL_HOST', 'smtp.gmail.com'),
                'DEVELOPER_MAIL_PORT' => (int) env('DEVELOPER_MAIL_PORT', 587),
                'DEVELOPER_MAIL_USERNAME' => env('DEVELOPER_MAIL_USERNAME', ''),
                'DEVELOPER_MAIL_PASSWORD' => env('DEVELOPER_MAIL_PASSWORD', ''),
                'DEVELOPER_MAIL_ENCRYPTION' => env('DEVELOPER_MAIL_ENCRYPTION', 'tls'),
                'DEVELOPER_MAIL_TIMEOUT' => (int) env('DEVELOPER_MAIL_TIMEOUT', 30),
                'DEVELOPER_MAIL_FROM_ADDRESS' => env('DEVELOPER_MAIL_FROM_ADDRESS', ''),
                'DEVELOPER_MAIL_FROM_NAME' => env('DEVELOPER_MAIL_FROM_NAME', 'Developer System'),
                
                // Advanced settings
                'DEVELOPER_MAIL_LOCAL_DOMAIN' => env('DEVELOPER_MAIL_LOCAL_DOMAIN', ''),
                'DEVELOPER_MAIL_AUTH_MODE' => env('DEVELOPER_MAIL_AUTH_MODE', ''),
                'DEVELOPER_MAIL_VERIFY_PEER' => filter_var(env('DEVELOPER_MAIL_VERIFY_PEER', true), FILTER_VALIDATE_BOOLEAN),
                'DEVELOPER_MAIL_VERIFY_PEER_NAME' => filter_var(env('DEVELOPER_MAIL_VERIFY_PEER_NAME', true), FILTER_VALIDATE_BOOLEAN),
                'DEVELOPER_MAIL_ALLOW_SELF_SIGNED' => filter_var(env('DEVELOPER_MAIL_ALLOW_SELF_SIGNED', false), FILTER_VALIDATE_BOOLEAN),
                'DEVELOPER_MAIL_VALIDATE_CERTIFICATES' => filter_var(env('DEVELOPER_MAIL_VALIDATE_CERTIFICATES', true), FILTER_VALIDATE_BOOLEAN),
                
                // Email addresses
                'DEVELOPER_EMAIL' => env('DEVELOPER_EMAIL', ''),
                'DEVELOPER_ERROR_EMAIL' => env('DEVELOPER_ERROR_EMAIL', ''),
                'DEVELOPER_TEST_EMAIL' => env('DEVELOPER_TEST_EMAIL', ''),
                'DEVELOPER_ALERT_EMAIL' => env('DEVELOPER_ALERT_EMAIL', ''),
                
                'source' => '.env',
                'password_length' => env('DEVELOPER_MAIL_PASSWORD') ? strlen(env('DEVELOPER_MAIL_PASSWORD')) : 0,
                'has_password' => !empty(env('DEVELOPER_MAIL_PASSWORD')),
            ];
        }
        
        $config['timestamp'] = now()->toISOString();
        $config['env_file_status'] = $this->checkEnvFileStatus();
        
        return $config;
    }
    
    /**
     * Validate configuration data
     */
    private function validateConfiguration(array $config)
    {
        try {
            Log::debug('Validating configuration');
            
            $validator = Validator::make($config, [
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
                'username' => 'required|email|max:255',
                'password' => 'required|string|min:6',
                'encryption' => 'required|in:tls,ssl,none',
                'from_address' => 'required|email|max:255',
                'from_name' => 'required|string|max:255',
                'timeout' => 'nullable|integer|min:5|max:300',
                'local_domain' => 'nullable|string|max:255',
                'auth_mode' => 'nullable|string|max:50',
                'developer_email' => 'nullable|email|max:255',
                'developer_error_email' => 'nullable|email|max:255',
                'developer_test_email' => 'nullable|email|max:255',
                'developer_alert_email' => 'nullable|email|max:255',
            ], [
                'host.required' => 'SMTP host is required',
                'port.required' => 'SMTP port is required',
                'username.required' => 'SMTP username is required',
                'username.email' => 'SMTP username must be a valid email',
                'password.required' => 'SMTP password is required',
                'password.min' => 'Password must be at least 6 characters',
                'from_address.required' => 'From email address is required',
                'from_address.email' => 'From address must be a valid email',
                'from_name.required' => 'From name is required',
            ]);
            
            if ($validator->fails()) {
                $errors = $validator->errors()->all();
                Log::warning('Configuration validation failed', $errors);
                return [
                    'valid' => false,
                    'message' => implode(', ', $errors)
                ];
            }
            
            // Additional validation for common SMTP issues
            if ($config['encryption'] === 'ssl' && $config['port'] == 587) {
                return [
                    'valid' => false,
                    'message' => 'SSL encryption typically uses port 465, not 587'
                ];
            }
            
            if ($config['encryption'] === 'tls' && $config['port'] == 465) {
                return [
                    'valid' => false,
                    'message' => 'TLS encryption typically uses port 587, not 465'
                ];
            }
            
            Log::debug('Configuration validation passed');
            return ['valid' => true, 'message' => 'Configuration is valid'];
            
        } catch (\Exception $e) {
            Log::error('Validation error: ' . $e->getMessage());
            return ['valid' => false, 'message' => 'Validation error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Prepare environment variable updates - Store plain password in .env
     */
    private function prepareEnvUpdates(array $config)
    {
        $envUpdates = [];
        
        // Map form fields to .env variable names
        $fieldMapping = [
            'host' => 'DEVELOPER_MAIL_HOST',
            'port' => 'DEVELOPER_MAIL_PORT',
            'username' => 'DEVELOPER_MAIL_USERNAME',
            'password' => 'DEVELOPER_MAIL_PASSWORD',
            'encryption' => 'DEVELOPER_MAIL_ENCRYPTION',
            'timeout' => 'DEVELOPER_MAIL_TIMEOUT',
            'from_address' => 'DEVELOPER_MAIL_FROM_ADDRESS',
            'from_name' => 'DEVELOPER_MAIL_FROM_NAME',
            'local_domain' => 'DEVELOPER_MAIL_LOCAL_DOMAIN',
            'auth_mode' => 'DEVELOPER_MAIL_AUTH_MODE',
        ];
        
        foreach ($fieldMapping as $formField => $envKey) {
            if (isset($config[$formField]) && $config[$formField] !== '') {
                $value = trim($config[$formField]);
                
                // Store plain text password in .env
                if ($formField === 'password') {
                    // Skip if password is masked
                    if ($this->isMaskedPassword($value)) {
                        Log::debug('Skipping masked password update');
                        continue;
                    }
                    // Store plain text in .env
                    $envUpdates[$envKey] = $value;
                    Log::debug('Password stored in .env as plain text');
                } else {
                    $envUpdates[$envKey] = $value;
                }
            }
        }
        
        // Handle additional email addresses
        $emailFields = [
            'developer_email' => 'DEVELOPER_EMAIL',
            'developer_error_email' => 'DEVELOPER_ERROR_EMAIL',
            'developer_test_email' => 'DEVELOPER_TEST_EMAIL',
            'developer_alert_email' => 'DEVELOPER_ALERT_EMAIL',
        ];
        
        foreach ($emailFields as $formField => $envKey) {
            if (!empty($config[$formField])) {
                $envUpdates[$envKey] = trim($config[$formField]);
            }
        }
        
        // Add boolean settings with defaults
        $envUpdates['DEVELOPER_MAIL_VERIFY_PEER'] = ($config['verify_peer'] ?? true) ? 'true' : 'false';
        $envUpdates['DEVELOPER_MAIL_VERIFY_PEER_NAME'] = ($config['verify_peer_name'] ?? true) ? 'true' : 'false';
        $envUpdates['DEVELOPER_MAIL_ALLOW_SELF_SIGNED'] = ($config['allow_self_signed'] ?? false) ? 'true' : 'false';
        $envUpdates['DEVELOPER_MAIL_VALIDATE_CERTIFICATES'] = ($config['validate_certificates'] ?? true) ? 'true' : 'false';
        
        return $envUpdates;
    }
    
    /**
     * Check if password value is masked
     */
    private function isMaskedPassword($value)
    {
        $maskedPatterns = ['******', '[HIDDEN]', '[ENCRYPTED]', '[MASKED]'];
        return in_array($value, $maskedPatterns) || (strlen($value) <= 6 && preg_match('/^\*+$/', $value));
    }
    
    /**
     * Check if password looks like Gmail App Password
     */
    private function checkGmailAppPassword(array $config)
    {
        $host = strtolower($config['host'] ?? '');
        $password = $config['password'] ?? '';
        
        if (str_contains($host, 'gmail.com')) {
            // Gmail App Passwords are 16 chars, letters/numbers only
            $cleanPassword = str_replace(' ', '', $password);
            
            if (strlen($cleanPassword) === 16 && ctype_alnum($cleanPassword)) {
                return [
                    'detected' => true,
                    'format' => '16-character Gmail App Password',
                    'help' => 'Make sure 2-Step Verification is enabled in Google Account'
                ];
            } elseif (strlen($cleanPassword) < 16) {
                return [
                    'detected' => false,
                    'warning' => 'Password too short for Gmail App Password (should be 16 chars)',
                    'help_url' => 'https://support.google.com/accounts/answer/185833'
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Check if password matches Gmail App Password format
     */
    private function isGmailAppPasswordFormat($password)
    {
        $cleanPassword = str_replace(' ', '', $password);
        return strlen($cleanPassword) === 16 && ctype_alnum($cleanPassword);
    }
    
    /**
     * Validate current .env password format
     */
    public function validateEnvPassword()
    {
        $password = env('DEVELOPER_MAIL_PASSWORD', '');
        
        if (empty($password)) {
            return [
                'valid' => false,
                'message' => 'Password not set in .env',
                'action' => 'Set DEVELOPER_MAIL_PASSWORD in .env'
            ];
        }
        
        // Check if it's encrypted (looks like base64 with = at end)
        if (base64_encode(base64_decode($password, true)) === $password) {
            return [
                'valid' => false,
                'message' => 'Password appears to be encrypted in .env',
                'action' => 'Replace with plain App Password (16 chars for Gmail)'
            ];
        }
        
        // Check for Gmail
        $host = env('DEVELOPER_MAIL_HOST', '');
        if (str_contains(strtolower($host), 'gmail.com')) {
            $cleanPassword = str_replace(' ', '', $password);
            if (strlen($cleanPassword) !== 16) {
                return [
                    'valid' => false,
                    'message' => 'Gmail requires 16-character App Password',
                    'action' => 'Generate App Password at https://myaccount.google.com/apppasswords'
                ];
            }
        }
        
        return [
            'valid' => true,
            'message' => 'Password format looks correct',
            'length' => strlen($password)
        ];
    }
    
    /**
     * Get Gmail-specific help
     */
    public function getGmailHelp()
    {
        $host = env('DEVELOPER_MAIL_HOST', '');
        
        if (!str_contains(strtolower($host), 'gmail.com')) {
            return null;
        }
        
        return [
            'title' => 'Gmail Configuration Help',
            'steps' => [
                '1. Enable 2-Step Verification' => 'Go to https://myaccount.google.com/security and enable 2-Step Verification',
                '2. Generate App Password' => 'Visit https://myaccount.google.com/apppasswords and generate password for "Mail"',
                '3. Use 16-Character Password' => 'Copy the 16-character password (remove spaces if present)',
                '4. Update Configuration' => 'Enter the 16-char password in the password field',
                '5. Test Configuration' => 'Use the test button to verify it works'
            ],
            'common_issues' => [
                'Using regular password' => 'Gmail blocks regular passwords for SMTP',
                'Spaces in password' => 'Remove spaces from the generated App Password',
                '2-Step not enabled' => 'Must have 2-Step Verification enabled first'
            ],
            'test_settings' => [
                'SMTP Host' => 'smtp.gmail.com',
                'SMTP Port' => '587 (TLS) or 465 (SSL)',
                'Encryption' => 'tls (for port 587) or ssl (for port 465)',
                'Username' => 'Your full Gmail address',
                'Password' => '16-character App Password'
            ]
        ];
    }
    
    /**
     * Test new configuration immediately after save
     */
    private function testNewConfiguration(array $config)
    {
        try {
            Log::debug('Testing new configuration after save');
            
            // Use the test email or default to the username
            $testEmail = $config['developer_test_email'] ?? $config['developer_email'] ?? $config['username'];
            
            // Test connection
            $testService = new DeveloperEmailService();
            $result = $testService->testConfiguration(
                null, // No settings object needed
                $testEmail,
                'connection',
                'developer'
            );
            
            // Save test result
            $testRecord = [
                'success' => $result['success'],
                'message' => $result['message'],
                'test_type' => 'post_save_connection',
                'test_email' => $testEmail,
                'timestamp' => now()->toISOString(),
                'config_source' => 'fresh_save'
            ];
            
            // Log the test
            Log::info('Post-save configuration test completed', $testRecord);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::warning('Failed to test new configuration: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Post-save test failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Mask sensitive values in config for logging
     */
    private function maskSensitiveConfig(array $config)
    {
        $masked = [];
        $sensitiveKeys = ['password', 'secret', 'key', 'token'];
        
        foreach ($config as $key => $value) {
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive && !empty($value)) {
                $masked[$key] = '[REDACTED]';
            } else {
                $masked[$key] = $value;
            }
        }
        
        return $masked;
    }
    
    /**
     * Test developer SMTP connection with comprehensive testing
     */
    public function testDeveloperSmtpConnection($testEmail = null, $testType = 'full')
    {
        try {
            // Get configuration from .env or database
            $config = $this->getCurrentDeveloperConfig();
            
            if (empty($config['DEVELOPER_MAIL_HOST']) || empty($config['DEVELOPER_MAIL_USERNAME'])) {
                throw new \Exception('Developer email configuration not set');
            }
            
            // Convert to format expected by DeveloperEmailService
            $serviceConfig = [
                'host' => $config['DEVELOPER_MAIL_HOST'],
                'port' => $config['DEVELOPER_MAIL_PORT'],
                'username' => $config['DEVELOPER_MAIL_USERNAME'],
                'password' => $this->getDecryptedPassword(), // Use decrypted password
                'encryption' => $config['DEVELOPER_MAIL_ENCRYPTION'],
                'from_address' => $config['DEVELOPER_MAIL_FROM_ADDRESS'],
                'from_name' => $config['DEVELOPER_MAIL_FROM_NAME'],
                'timeout' => $config['DEVELOPER_MAIL_TIMEOUT'] ?? 30,
            ];
            
            $testEmail = $testEmail ?? $config['DEVELOPER_TEST_EMAIL'] ?? $config['DEVELOPER_MAIL_USERNAME'];
            
            // Use the DeveloperEmailService for testing
            $emailService = new DeveloperEmailService();
            $result = $emailService->testConfiguration(
                null,
                $testEmail,
                $testType,
                'developer'
            );
            
            // Log test result
            Log::info('Developer SMTP connection test completed', [
                'success' => $result['success'] ?? false,
                'test_type' => $testType,
                'test_email' => $testEmail,
                'timestamp' => now()->toISOString()
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Developer SMTP test failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Developer SMTP test failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Update .env file with proper error handling and verification
     */
    private function updateDeveloperEnvFile(array $envUpdates)
    {
        try {
            $envPath = base_path('.env');
            
            if (!File::exists($envPath)) {
                throw new \Exception('.env file not found at: ' . $envPath);
            }
            
            // Check file permissions
            if (!is_writable($envPath)) {
                throw new \Exception('.env file is not writable. Check permissions.');
            }
            
            // Read current content
            $envContent = File::get($envPath);
            $originalContent = $envContent; // Keep for rollback
            
            // Process each update
            $updated = [];
            $added = [];
            
            foreach ($envUpdates as $key => $value) {
                // Escape value for .env file
                $escapedValue = $this->escapeEnvValue($value);
                
                // Pattern to find existing key
                $pattern = "/^{$key}=.*$/m";
                
                if (preg_match($pattern, $envContent)) {
                    // Replace existing
                    $replacement = "{$key}={$escapedValue}";
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                    $updated[] = $key;
                    Log::debug("Updated existing .env variable: {$key}");
                } else {
                    // Add new at appropriate location (before empty lines at end)
                    $replacement = "\n{$key}={$escapedValue}";
                    
                    // Find last non-empty line
                    $lines = explode("\n", $envContent);
                    $lastContentLine = 0;
                    for ($i = count($lines) - 1; $i >= 0; $i--) {
                        if (trim($lines[$i]) !== '') {
                            $lastContentLine = $i;
                            break;
                        }
                    }
                    
                    // Insert before trailing empty lines
                    $lines = array_slice($lines, 0, $lastContentLine + 1);
                    $envContent = implode("\n", $lines) . $replacement . "\n";
                    $added[] = $key;
                    Log::debug("Added new .env variable: {$key}");
                }
            }
            
            // Write back to file
            $result = File::put($envPath, $envContent);
            
            if ($result === false) {
                // Attempt rollback
                File::put($envPath, $originalContent);
                throw new \Exception('Failed to write to .env file. Changes rolled back.');
            }
            
            // Verify write was successful
            $newContent = File::get($envPath);
            $verificationErrors = [];
            
            foreach ($envUpdates as $key => $value) {
                if (!preg_match("/^{$key}=/m", $newContent)) {
                    $verificationErrors[] = $key;
                }
            }
            
            if (!empty($verificationErrors)) {
                // Rollback on verification failure
                File::put($envPath, $originalContent);
                throw new \Exception('Failed to verify updates for: ' . implode(', ', $verificationErrors));
            }
            
            Log::info('Developer .env configuration updated successfully', [
                'updated' => $updated,
                'added' => $added,
                'total_keys' => count($envUpdates),
                'timestamp' => now()->toISOString()
            ]);
            
            return [
                'success' => true,
                'message' => '.env file updated successfully',
                'updated' => $updated,
                'added' => $added,
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to update .env file: ' . $e->getMessage(), [
                'env_updates' => array_keys($envUpdates),
                'env_path' => $envPath ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update .env: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Escape value for .env file with improved handling
     */
    private function escapeEnvValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_numeric($value)) {
            return $value;
        }
        
        if (empty($value)) {
            return '""';
        }
        
        // Remove any existing quotes and trim
        $value = trim($value, '"\'');
        
        // Escape special characters
        $value = str_replace(
            ['"', '$', "\n", "\r", '\\'],
            ['\"', '\$', '', '', '\\\\'],
            $value
        );
        
        // Always wrap in quotes for consistency and safety
        return '"' . $value . '"';
    }
    
    /**
     * Backup current .env file
     */
    public function backupEnvFile()
    {
        try {
            $envPath = base_path('.env');
            $backupDir = storage_path('app/backups/env');
            
            if (!File::exists($envPath)) {
                throw new \Exception('.env file not found');
            }
            
            // Ensure backup directory exists
            if (!File::exists($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_His');
            $backupPath = $backupDir . "/env_backup_{$timestamp}.env";
            
            $copied = File::copy($envPath, $backupPath);
            
            if (!$copied) {
                throw new \Exception('Failed to create backup copy');
            }
            
            // Set proper permissions
            File::chmod($backupPath, 0644);
            
            // Clean up old backups (keep last 10)
            $this->cleanupOldBackups($backupDir);
            
            return [
                'success' => true,
                'message' => '.env file backed up successfully',
                'backup_path' => $backupPath,
                'backup_size' => File::size($backupPath),
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to backup .env file: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Clean up old backup files
     */
    private function cleanupOldBackups($backupDir)
    {
        try {
            $files = glob($backupDir . '/env_backup_*.env');
            
            if (count($files) > 10) {
                // Sort by modification time (oldest first)
                usort($files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                // Remove oldest files (keep last 10)
                $filesToRemove = array_slice($files, 0, count($files) - 10);
                
                foreach ($filesToRemove as $file) {
                    File::delete($file);
                }
                
                Log::debug('Cleaned up old backup files', [
                    'removed' => count($filesToRemove),
                    'kept' => count($files) - count($filesToRemove)
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clean up old backups: ' . $e->getMessage());
        }
    }
    
    /**
     * Check .env file status
     */
    private function checkEnvFileStatus()
    {
        $envPath = base_path('.env');
        
        $status = [
            'path' => $envPath,
            'exists' => File::exists($envPath),
            'readable' => is_readable($envPath),
            'writable' => is_writable($envPath),
            'size' => File::exists($envPath) ? File::size($envPath) : 0,
            'last_modified' => File::exists($envPath) ? date('Y-m-d H:i:s', File::lastModified($envPath)) : null,
        ];
        
        // Add owner/group info only on Unix/Linux systems
        if (File::exists($envPath)) {
            // Check if we're on Windows
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows doesn't have posix functions
                $status['owner'] = 'Windows System';
                $status['group'] = 'Windows Users';
                $status['permissions'] = 'windows';
                
                // Try to get Windows file info
                try {
                    $status['is_windows'] = true;
                    $status['file_attributes'] = $this->getWindowsFileAttributes($envPath);
                } catch (\Exception $e) {
                    Log::warning('Could not get Windows file attributes: ' . $e->getMessage());
                }
            } else {
                // Unix/Linux system
                try {
                    if (function_exists('posix_getpwuid')) {
                        $ownerInfo = posix_getpwuid(File::owner($envPath));
                        $status['owner'] = $ownerInfo['name'] ?? 'unknown';
                    } else {
                        $status['owner'] = 'unknown';
                    }
                    
                    if (function_exists('posix_getgrgid')) {
                        $groupInfo = posix_getgrgid(File::group($envPath));
                        $status['group'] = $groupInfo['name'] ?? 'unknown';
                    } else {
                        $status['group'] = 'unknown';
                    }
                    
                    $status['permissions'] = substr(sprintf('%o', File::perms($envPath)), -4);
                } catch (\Exception $e) {
                    Log::warning('Could not get Unix file ownership info: ' . $e->getMessage());
                    $status['owner'] = 'error';
                    $status['group'] = 'error';
                    $status['permissions'] = 'error';
                }
            }
        } else {
            $status['owner'] = 'unknown';
            $status['group'] = 'unknown';
            $status['permissions'] = 'unknown';
        }
        
        return $status;
    }
    
    /**
     * Get Windows file attributes
     */
    private function getWindowsFileAttributes($path)
    {
        $attributes = [];
        
        try {
            // For Windows, use different approach
            clearstatcache(true, $path);
            
            // Get file info
            $attributes['size'] = filesize($path);
            $attributes['modified'] = filemtime($path);
            $attributes['accessed'] = fileatime($path);
            $attributes['created'] = filectime($path);
            
            // Check if readable/writable
            $attributes['readable'] = is_readable($path);
            $attributes['writable'] = is_writable($path);
            $attributes['executable'] = is_executable($path);
            
            // Get permissions in Windows style
            $perms = fileperms($path);
            $attributes['perms_octal'] = substr(sprintf('%o', $perms), -4);
            $attributes['perms_full'] = sprintf('%o', $perms);
            
            // Basic permission flags
            $attributes['is_readable'] = ($perms & 0x0100) ? true : false;
            $attributes['is_writable'] = ($perms & 0x0080) ? true : false;
            
            // Try to get owner via alternative method on Windows
            if (function_exists('exec')) {
                $output = [];
                exec('whoami 2>&1', $output, $return);
                if ($return === 0 && !empty($output[0])) {
                    $attributes['current_user'] = $output[0];
                }
            }
            
            // Get file type
            $attributes['is_dir'] = is_dir($path);
            $attributes['is_file'] = is_file($path);
            $attributes['is_link'] = is_link($path);
            
            // Get real path
            $attributes['realpath'] = realpath($path);
            
        } catch (\Exception $e) {
            Log::warning('Windows file attributes error: ' . $e->getMessage());
            $attributes['error'] = $e->getMessage();
        }
        
        return $attributes;
    }
    
    /**
     * Reset developer email configuration in .env file
     */
    public function resetDeveloperEmailConfig()
    {
        try {
            $envPath = base_path('.env');
            
            if (!File::exists($envPath)) {
                throw new \Exception('.env file not found');
            }
            
            // Backup before reset
            $backupResult = $this->backupEnvFile();
            
            $envContent = File::get($envPath);
            
            // List of all developer email variables to remove
            $developerVars = [
                'DEVELOPER_MAIL_HOST',
                'DEVELOPER_MAIL_PORT',
                'DEVELOPER_MAIL_USERNAME',
                'DEVELOPER_MAIL_PASSWORD',
                'DEVELOPER_MAIL_ENCRYPTION',
                'DEVELOPER_MAIL_TIMEOUT',
                'DEVELOPER_MAIL_FROM_ADDRESS',
                'DEVELOPER_MAIL_FROM_NAME',
                'DEVELOPER_MAIL_LOCAL_DOMAIN',
                'DEVELOPER_MAIL_AUTH_MODE',
                'DEVELOPER_MAIL_VERIFY_PEER',
                'DEVELOPER_MAIL_VERIFY_PEER_NAME',
                'DEVELOPER_MAIL_ALLOW_SELF_SIGNED',
                'DEVELOPER_MAIL_VALIDATE_CERTIFICATES',
                'DEVELOPER_EMAIL',
                'DEVELOPER_ERROR_EMAIL',
                'DEVELOPER_TEST_EMAIL',
                'DEVELOPER_ALERT_EMAIL',
            ];
            
            // Remove each variable
            $removed = [];
            foreach ($developerVars as $var) {
                $pattern = "/^{$var}=.*$/m";
                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, '', $envContent);
                    $removed[] = $var;
                }
            }
            
            // Remove empty lines caused by removals
            $envContent = preg_replace("/\n{2,}/", "\n", $envContent);
            $envContent = trim($envContent) . "\n";
            
            // Write back
            File::put($envPath, $envContent);
            
            // Clear config cache
            Artisan::call('config:clear');
            Artisan::call('config:cache');
            
            // Clear database settings too
            DB::table('developer_settings')->update([
                'developer_smtp_host' => null,
                'developer_smtp_port' => null,
                'developer_smtp_username' => null,
                'developer_smtp_password' => null,
                'developer_smtp_encryption' => null,
                'developer_email_from' => null,
                'developer_email_from_name' => null,
                'additional_config' => null,
                'updated_at' => now(),
            ]);
            
            Log::info('Developer email configuration reset from .env', [
                'developer' => auth()->user()->email ?? 'unknown',
                'removed_vars' => $removed,
                'backup_created' => $backupResult['success'],
                'timestamp' => now()->toISOString()
            ]);
            
            return [
                'success' => true,
                'message' => 'Developer email configuration reset successfully',
                'env_cleared' => true,
                'db_cleared' => true,
                'removed_variables' => $removed,
                'backup_created' => $backupResult['success'],
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to reset developer email config: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to reset configuration: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Fix .env password format
     */
    public function fixEnvPassword($newPlainPassword)
    {
        try {
            $envPath = base_path('.env');
            
            if (!File::exists($envPath)) {
                throw new \Exception('.env file not found');
            }
            
            $envContent = File::get($envPath);
            
            // Update password in .env
            $escapedPassword = $this->escapeEnvValue($newPlainPassword);
            $pattern = "/^DEVELOPER_MAIL_PASSWORD=.*$/m";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "DEVELOPER_MAIL_PASSWORD={$escapedPassword}", $envContent);
            } else {
                $envContent .= "\nDEVELOPER_MAIL_PASSWORD={$escapedPassword}\n";
            }
            
            // Write back
            File::put($envPath, $envContent);
            
            // Also update database with encrypted version
            $settings = DeveloperSetting::first();
            if ($settings) {
                $settings->developer_smtp_password = Crypt::encryptString($newPlainPassword);
                $settings->save();
            }
            
            // Clear cache
            Artisan::call('config:clear');
            Artisan::call('config:cache');
            
            Log::info('Fixed .env password format', [
                'old_length' => strlen(env('DEVELOPER_MAIL_PASSWORD', '')),
                'new_length' => strlen($newPlainPassword),
                'developer' => auth()->user()->email ?? 'unknown'
            ]);
            
            return [
                'success' => true,
                'message' => 'Password format fixed successfully',
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to fix .env password: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fix password: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Comprehensive email configuration test
     */
    public function comprehensiveTest()
    {
        $results = [];
        
        // 1. Check .env password format
        $results['env_password'] = $this->validateEnvPassword();
        
        // 2. Check database configuration
        $results['database_config'] = $this->checkDatabaseConfigFixed();
        
        // 3. Test connection
        $results['connection_test'] = $this->testDeveloperSmtpConnection(null, 'connection');
        
        // 4. Get Gmail help if applicable
        $results['gmail_help'] = $this->getGmailHelp();
        
        // 5. Check if passwords match between .env and database
        $results['password_match'] = $this->checkPasswordConsistencyFixed();
        
        return $results;
    }
    
    /**
     * FIXED: Check database configuration with correct column names
     */
    private function checkDatabaseConfigFixed()
    {
        try {
            $settings = DeveloperSetting::first();
            
            if (!$settings) {
                return [
                    'exists' => false,
                    'message' => 'No developer settings record found'
                ];
            }
            
            $hasPassword = !empty($settings->developer_smtp_password);
            $hasCompleteConfig = !empty($settings->developer_smtp_host) && 
                                !empty($settings->developer_smtp_username) && 
                                $hasPassword;
            
            return [
                'exists' => true,
                'has_complete_config' => $hasCompleteConfig,
                'has_password' => $hasPassword,
                'last_modified' => $settings->updated_at->format('Y-m-d H:i:s'),
                'config_fields' => [
                    'host' => !empty($settings->developer_smtp_host) ? '✓' : '✗',
                    'port' => !empty($settings->developer_smtp_port) ? '✓' : '✗',
                    'username' => !empty($settings->developer_smtp_username) ? '✓' : '✗',
                    'password' => $hasPassword ? '✓' : '✗',
                    'encryption' => !empty($settings->developer_smtp_encryption) ? '✓' : '✗',
                    'from_address' => !empty($settings->developer_email_from) ? '✓' : '✗',
                    'from_name' => !empty($settings->developer_email_from_name) ? '✓' : '✗',
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * FIXED: Check password consistency
     */
    private function checkPasswordConsistencyFixed()
    {
        try {
            // Get password from .env
            $envPassword = env('DEVELOPER_MAIL_PASSWORD', '');
            
            // Get decrypted password from database
            $dbPassword = $this->getDecryptedPassword();
            
            return [
                'env_set' => !empty($envPassword),
                'db_set' => !empty($dbPassword),
                'match' => $envPassword === $dbPassword,
                'env_length' => strlen($envPassword),
                'db_length' => strlen($dbPassword),
                'note' => $envPassword === $dbPassword 
                    ? 'Passwords match ✓' 
                    : 'Passwords do not match - update configuration'
            ];
            
        } catch (\Exception $e) {
            return [
                'match' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get comprehensive email diagnostics
     */
    public function getDeveloperDiagnostics()
    {
        $emailService = new DeveloperEmailService();
        
        return [
            'system' => $this->getSystemInfo(),
            'php' => $this->getPhpMailInfo(),
            'laravel' => $this->getLaravelMailConfig(),
            'developer_settings' => $this->getCurrentDeveloperConfig(),
            'environment' => $this->getEnvironmentMailConfig(),
            'queue_status' => $this->getQueueStatus(),
            'file_permissions' => $this->checkMailPermissions(),
            'recent_errors' => $this->getRecentMailErrors(),
            'available_drivers' => $this->getAvailableMailDrivers(),
            'configuration_status' => $emailService->checkConfigurationStatus('developer'),
        ];
    }
    
    /**
     * Get system information
     */
    private function getSystemInfo()
    {
        return [
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'timezone' => config('app.timezone'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'url' => config('app.url'),
        ];
    }
    
    /**
     * Get PHP mail information
     */
    private function getPhpMailInfo()
    {
        return [
            'mail_function' => function_exists('mail'),
            'smtp' => ini_get('SMTP'),
            'smtp_port' => ini_get('smtp_port'),
            'sendmail_path' => ini_get('sendmail_path'),
            'mail.add_x_header' => ini_get('mail.add_x_header'),
            'mail.log' => ini_get('mail.log'),
            'openssl_loaded' => extension_loaded('openssl'),
        ];
    }
    
    /**
     * Get Laravel mail configuration
     */
    private function getLaravelMailConfig()
    {
        return [
            'default' => config('mail.default'),
            'from' => config('mail.from'),
            'markdown' => config('mail.markdown'),
            'mailers' => array_keys(config('mail.mailers', [])),
        ];
    }
    
    /**
     * Get environment mail configuration
     */
    private function getEnvironmentMailConfig()
    {
        return [
            'MAIL_MAILER' => env('MAIL_MAILER'),
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_PORT' => env('MAIL_PORT'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_PASSWORD' => env('MAIL_PASSWORD') ? '[SET]' : '[NOT SET]',
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
            'MAIL_FROM_NAME' => env('MAIL_FROM_NAME'),
        ];
    }
    
    /**
     * Get queue status
     */
    private function getQueueStatus()
    {
        try {
            return [
                'connection' => config('queue.default'),
                'table_exists' => Schema::hasTable('jobs'),
                'failed_jobs_table_exists' => Schema::hasTable('failed_jobs'),
                'failed_jobs_count' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'queue_worker_status' => $this->checkQueueWorkerStatus(),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Check queue worker status
     */
    private function checkQueueWorkerStatus()
    {
        try {
            // Check if supervisor is running (common setup)
            if (function_exists('shell_exec')) {
                $output = shell_exec('ps aux | grep "[q]ueue:work"');
                return !empty($output) ? 'running' : 'not_running';
            }
            return 'unknown';
        } catch (\Exception $e) {
            return 'check_failed';
        }
    }
    
    /**
     * Check mail permissions
     */
    private function checkMailPermissions()
    {
        $paths = [
            base_path('.env') => 'Read/Write .env',
            storage_path('logs') => 'Write logs',
            storage_path('framework/cache') => 'Cache directory',
            storage_path('framework/views') => 'Views cache',
            storage_path('framework/sessions') => 'Sessions',
        ];
        
        $permissions = [];
        foreach ($paths as $path => $description) {
            $exists = File::exists($path);
            $permissions[$description] = [
                'exists' => $exists,
                'readable' => $exists ? is_readable($path) : false,
                'writable' => $exists ? is_writable($path) : false,
                'is_dir' => $exists ? is_dir($path) : false,
            ];
        }
        
        return $permissions;
    }
    
    /**
     * Get recent mail errors
     */
    private function getRecentMailErrors()
    {
        try {
            $logPath = storage_path('logs/laravel.log');
            
            if (!File::exists($logPath)) {
                return ['log_file_not_found' => $logPath];
            }
            
            // Read last 100KB of log file (for efficiency)
            $fileSize = File::size($logPath);
            $offset = max(0, $fileSize - 100000); // Last 100KB
            $logContent = File::get($logPath, false, null, $offset, 100000);
            
            // Find recent mail errors with more specific patterns
            $errors = [];
            $patterns = [
                '/mail.*error/i',
                '/SMTP.*error/i',
                '/failed to send/i',
                '/connection.*failed/i',
                '/authentication.*failed/i',
            ];
            
            $lines = explode("\n", $logContent);
            foreach ($lines as $line) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $line)) {
                        // Extract timestamp if present
                        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                            $errors[] = $line;
                            break;
                        }
                    }
                }
            }
            
            // Get last 10 unique errors
            $errors = array_unique($errors);
            $errors = array_slice($errors, -10);
            
            return [
                'total_found' => count($errors),
                'recent_errors' => $errors,
                'log_file_size' => $fileSize,
                'analyzed_bytes' => min(100000, $fileSize),
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get available mail drivers
     */
    private function getAvailableMailDrivers()
    {
        return [
            'smtp' => 'SMTP (Simple Mail Transfer Protocol)',
            'sendmail' => 'Sendmail',
            'mailgun' => 'Mailgun',
            'ses' => 'Amazon SES',
            'postmark' => 'Postmark',
            'log' => 'Log (for testing)',
            'array' => 'Array (for testing)',
        ];
    }
    
    /**
     * Check if .env file is writable
     */
    public function checkEnvWritable()
    {
        $envPath = base_path('.env');
        
        $status = $this->checkEnvFileStatus();
        
        return [
            'status' => $status,
            'can_update' => $status['exists'] && $status['writable'],
            'recommendations' => !$status['writable'] ? [
                'Check file permissions',
                'Ensure web server user has write access',
                'Check SELinux/AppArmor restrictions if applicable'
            ] : [],
        ];
    }
    
    /**
     * Validate current configuration without saving
     */
    public function validateCurrentConfig()
    {
        try {
            $config = $this->getCurrentDeveloperConfig();
            
            // Convert to format for validation
            $validationConfig = [
                'host' => $config['DEVELOPER_MAIL_HOST'],
                'port' => $config['DEVELOPER_MAIL_PORT'],
                'username' => $config['DEVELOPER_MAIL_USERNAME'],
                'password' => $this->getDecryptedPassword(), // Use decrypted password
                'encryption' => $config['DEVELOPER_MAIL_ENCRYPTION'],
                'from_address' => $config['DEVELOPER_MAIL_FROM_ADDRESS'],
                'from_name' => $config['DEVELOPER_MAIL_FROM_NAME'],
            ];
            
            $validationResult = $this->validateConfiguration($validationConfig);
            
            if ($validationResult['valid']) {
                // Test the connection
                $testResult = $this->testDeveloperSmtpConnection(null, 'connection');
                
                return [
                    'valid' => $testResult['success'],
                    'message' => $testResult['success'] ? 'Configuration is valid and working' : 'Configuration valid but test failed',
                    'validation_details' => $validationResult,
                    'test_result' => $testResult,
                    'timestamp' => now()->toISOString()
                ];
            }
            
            return [
                'valid' => false,
                'message' => $validationResult['message'],
                'validation_details' => $validationResult,
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
}