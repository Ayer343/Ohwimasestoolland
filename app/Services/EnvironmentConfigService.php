<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class EnvironmentConfigService
{
    protected $envPath;
    
    public function __construct()
    {
        $this->envPath = base_path('.env');
    }

    /**
     * Get current app configuration from .env
     */
    public function getAppConfiguration(): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return [
                    'app_name' => 'Laravel',
                    'app_short_name' => 'Laravel',
                    'app_url' => 'http://localhost',
                    'app_env' => 'local',
                    'app_debug' => 'true'
                ];
            }

            $envContent = File::get($this->envPath);
            
            return [
                'app_name' => $this->getEnvValue($envContent, 'APP_NAME', 'Laravel'),
                'app_short_name' => $this->getEnvValue($envContent, 'APP_SHORT_NAME', 'Laravel'),
                'app_url' => $this->getEnvValue($envContent, 'APP_URL', 'http://localhost'),
                'app_env' => $this->getEnvValue($envContent, 'APP_ENV', 'local'),
                'app_debug' => $this->getEnvValue($envContent, 'APP_DEBUG', 'true')
            ];
            
        } catch (\Exception $e) {
            Log::error('Error reading app configuration: ' . $e->getMessage());
            return [
                'app_name' => 'Laravel',
                'app_short_name' => 'Laravel',
                'app_url' => 'http://localhost',
                'app_env' => 'local',
                'app_debug' => 'true'
            ];
        }
    }

    /**
     * Update app name configuration in .env file
     */
    public function updateAppConfiguration(string $appName, string $appShortName): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return [
                    'success' => false,
                    'message' => '.env file not found'
                ];
            }

            $envContent = File::get($this->envPath);
            $updates = [];

            // Update APP_NAME
            $appNamePattern = '/^APP_NAME=.*$/m';
            $appNameReplacement = 'APP_NAME="' . addslashes($appName) . '"';
            
            if (preg_match($appNamePattern, $envContent)) {
                $envContent = preg_replace($appNamePattern, $appNameReplacement, $envContent);
            } else {
                $envContent .= "\n" . $appNameReplacement;
            }
            $updates[] = 'APP_NAME';

            // Update APP_SHORT_NAME
            $appShortNamePattern = '/^APP_SHORT_NAME=.*$/m';
            $appShortNameReplacement = 'APP_SHORT_NAME="' . addslashes($appShortName) . '"';
            
            if (preg_match($appShortNamePattern, $envContent)) {
                $envContent = preg_replace($appShortNamePattern, $appShortNameReplacement, $envContent);
            } else {
                $appNamePosition = strpos($envContent, $appNameReplacement);
                if ($appNamePosition !== false) {
                    $insertPosition = $appNamePosition + strlen($appNameReplacement);
                    $envContent = substr_replace($envContent, "\n" . $appShortNameReplacement, $insertPosition, 0);
                } else {
                    $envContent .= "\n" . $appShortNameReplacement;
                }
            }
            $updates[] = 'APP_SHORT_NAME';

            File::put($this->envPath, $envContent);
            
            Artisan::call('config:clear');
            
            Log::info('App configuration updated in .env file', [
                'app_name' => $appName,
                'app_short_name' => $appShortName,
                'updates' => $updates
            ]);

            return [
                'success' => true,
                'message' => 'App name configuration updated successfully',
                'updates' => $updates
            ];
            
        } catch (\Exception $e) {
            Log::error('Error updating app name in .env: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update app name in .env: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update email configuration in .env file
     */
    public function updateEmailConfiguration(string $email, ?string $password = null): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return [
                    'success' => false,
                    'message' => '.env file not found'
                ];
            }

            $envContent = File::get($this->envPath);
            $updated = false;

            if (preg_match('/^MAIL_USERNAME=.*/m', $envContent)) {
                $envContent = preg_replace(
                    '/^MAIL_USERNAME=.*/m',
                    'MAIL_USERNAME=' . $email,
                    $envContent
                );
                $updated = true;
            } else {
                $envContent .= "\nMAIL_USERNAME=" . $email;
                $updated = true;
            }

            if ($password !== null) {
                if (preg_match('/^MAIL_PASSWORD=.*/m', $envContent)) {
                    $envContent = preg_replace(
                        '/^MAIL_PASSWORD=.*/m',
                        'MAIL_PASSWORD=' . $password,
                        $envContent
                    );
                    $updated = true;
                } else {
                    $envContent .= "\nMAIL_PASSWORD=" . $password;
                    $updated = true;
                }
            }

            if (preg_match('/^MAIL_FROM_ADDRESS=.*/m', $envContent)) {
                $envContent = preg_replace(
                    '/^MAIL_FROM_ADDRESS=.*/m',
                    'MAIL_FROM_ADDRESS=' . $email,
                    $envContent
                );
            } else {
                $envContent .= "\nMAIL_FROM_ADDRESS=" . $email;
            }

            if (preg_match('/^MAIL_FROM_NAME=.*/m', $envContent)) {
                $envContent = preg_replace(
                    '/^MAIL_FROM_NAME=.*/m',
                    'MAIL_FROM_NAME="' . config('app.name', 'Property System') . '"',
                    $envContent
                );
            } else {
                $envContent .= "\nMAIL_FROM_NAME=\"" . config('app.name', 'Property System') . "\"";
            }

            if ($updated) {
                File::put($this->envPath, $envContent);
                Artisan::call('config:clear');
                
                Log::info('Email configuration updated in .env file', [
                    'email' => $email,
                    'password_updated' => $password !== null,
                    'env_file' => $this->envPath
                ]);

                return [
                    'success' => true,
                    'message' => 'Email configuration updated successfully'
                ];
            }

            return [
                'success' => false,
                'message' => 'No changes made to email configuration'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update email configuration in .env: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update email configuration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update complete mail configuration
     */
    public function updateMailConfiguration(array $config): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return ['success' => false, 'message' => '.env file not found'];
            }

            $envContent = File::get($this->envPath);
            $updates = [];

            $mailConfigs = [
                'MAIL_MAILER' => $config['mailer'] ?? 'smtp',
                'MAIL_HOST' => $config['host'] ?? 'smtp.gmail.com',
                'MAIL_PORT' => $config['port'] ?? '587',
                'MAIL_USERNAME' => $config['username'] ?? '',
                'MAIL_PASSWORD' => $config['password'] ?? '',
                'MAIL_ENCRYPTION' => $config['encryption'] ?? 'tls',
                'MAIL_FROM_ADDRESS' => $config['from_address'] ?? $config['username'] ?? '',
                'MAIL_FROM_NAME' => $config['from_name'] ?? config('app.name', 'Property System'),
            ];

            foreach ($mailConfigs as $key => $value) {
                if ($key === 'MAIL_PASSWORD' && !empty($value)) {
                    $value = $this->escapeEnvValue($value);
                }
                
                if (preg_match("/^{$key}=.*/m", $envContent)) {
                    $envContent = preg_replace(
                        "/^{$key}=.*/m",
                        "{$key}={$value}",
                        $envContent
                    );
                } else {
                    $envContent .= "\n{$key}={$value}";
                }
                $updates[] = $key;
            }

            File::put($this->envPath, $envContent);
            Artisan::call('config:clear');

            Log::info('Mail configuration updated in .env file', [
                'updates' => $updates,
                'config_keys_updated' => array_keys($config)
            ]);

            return [
                'success' => true,
                'message' => 'Mail configuration updated successfully',
                'updates' => $updates
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update mail configuration: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update mail configuration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get current mail configuration from .env
     */
    public function getMailConfiguration(): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return [];
            }

            $envContent = File::get($this->envPath);
            $config = [];

            $keys = [
                'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME',
                'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'
            ];

            foreach ($keys as $key) {
                if (preg_match("/^{$key}=(.*)/m", $envContent, $matches)) {
                    $value = trim($matches[1], '"\'');
                    $config[strtolower(substr($key, 5))] = $value;
                }
            }

            return $config;

        } catch (\Exception $e) {
            Log::error('Failed to read mail configuration from .env: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current WhatsApp configuration from .env
     */
    public function getWhatsAppConfiguration(): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return [
                    'provider' => 'none',
                    'status' => 'not_configured',
                    'error' => '.env file not found'
                ];
            }

            $envContent = File::get($this->envPath);
            
            $config = [
                'provider' => $this->getEnvValue($envContent, 'WHATSAPP_PROVIDER', 'none'),
                'twilio_sid' => $this->getEnvValue($envContent, 'TWILIO_SID', ''),
                'twilio_token' => $this->getEnvValue($envContent, 'TWILIO_AUTH_TOKEN', ''),
                'twilio_whatsapp_from' => $this->getEnvValue($envContent, 'TWILIO_WHATSAPP_FROM', ''),
                'vonage_key' => $this->getEnvValue($envContent, 'VONAGE_KEY', ''),
                'vonage_secret' => $this->getEnvValue($envContent, 'VONAGE_SECRET', ''),
                'vonage_whatsapp_from' => $this->getEnvValue($envContent, 'VONAGE_WHATSAPP_FROM', ''),
                'whatsapp_api_url' => $this->getEnvValue($envContent, 'WHATSAPP_API_URL', ''),
                'whatsapp_api_key' => $this->getEnvValue($envContent, 'WHATSAPP_API_KEY', ''),
                'dialog_api_key' => $this->getEnvValue($envContent, 'DIALOG_API_KEY', ''),
                'dialog_phone_number_id' => $this->getEnvValue($envContent, 'DIALOG_PHONE_NUMBER_ID', ''),
                'dialog_business_id' => $this->getEnvValue($envContent, 'DIALOG_BUSINESS_ID', ''),
                'wati_api_key' => $this->getEnvValue($envContent, 'WATI_API_KEY', ''),
                'wati_api_url' => $this->getEnvValue($envContent, 'WATI_API_URL', ''),
                'status' => $this->getWhatsAppConfigurationStatus($envContent)
            ];

            return $config;
            
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp configuration: ' . $e->getMessage());
            return [
                'provider' => 'none',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update WhatsApp configuration in .env file
     */
    public function updateWhatsAppConfiguration(array $config): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return [
                    'success' => false,
                    'message' => '.env file not found'
                ];
            }

            $envContent = File::get($this->envPath);
            $provider = $config['provider'] ?? 'none';
            
            // Backup before making changes
            $this->backupEnvFile();

            // Define all WhatsApp configuration keys
            $whatsappConfigs = [
                'WHATSAPP_PROVIDER' => $provider,
                'TWILIO_SID' => $config['twilio_sid'] ?? '',
                'TWILIO_AUTH_TOKEN' => $config['twilio_token'] ?? '',
                'TWILIO_WHATSAPP_FROM' => $config['twilio_whatsapp_from'] ?? '',
                'VONAGE_KEY' => $config['vonage_key'] ?? '',
                'VONAGE_SECRET' => $config['vonage_secret'] ?? '',
                'VONAGE_WHATSAPP_FROM' => $config['vonage_whatsapp_from'] ?? '',
                'WHATSAPP_API_URL' => $config['whatsapp_api_url'] ?? '',
                'WHATSAPP_API_KEY' => $config['whatsapp_api_key'] ?? '',
                'DIALOG_API_KEY' => $config['dialog_api_key'] ?? '',
                'DIALOG_PHONE_NUMBER_ID' => $config['dialog_phone_number_id'] ?? '',
                'DIALOG_BUSINESS_ID' => $config['dialog_business_id'] ?? '',
                'WATI_API_KEY' => $config['wati_api_key'] ?? '',
                'WATI_API_URL' => $config['wati_api_url'] ?? '',
            ];

            $updates = [];
            foreach ($whatsappConfigs as $key => $value) {
                $escapedValue = $this->escapeEnvValue($value);
                
                if (preg_match("/^{$key}=.*/m", $envContent)) {
                    $envContent = preg_replace(
                        "/^{$key}=.*/m",
                        "{$key}={$escapedValue}",
                        $envContent
                    );
                } else {
                    $envContent .= "\n{$key}={$escapedValue}";
                }
                $updates[] = $key;
            }

            File::put($this->envPath, $envContent);
            Artisan::call('config:clear');

            Log::info('WhatsApp configuration updated in .env file', [
                'provider' => $provider,
                'updates' => $updates
            ]);

            return [
                'success' => true,
                'message' => 'WhatsApp configuration updated successfully',
                'provider' => $provider,
                'updates' => $updates
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update WhatsApp configuration: ' . $e->getMessage(), [
                'config' => $config
            ]);
            
            // Attempt to restore backup
            $this->restoreEnvBackup();
            
            return [
                'success' => false,
                'message' => 'Failed to update WhatsApp configuration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get WhatsApp configuration status
     */
    protected function getWhatsAppConfigurationStatus(string $envContent): string
    {
        $provider = $this->getEnvValue($envContent, 'WHATSAPP_PROVIDER', 'none');
        
        if ($provider === 'none') {
            return 'not_configured';
        }

        switch ($provider) {
            case 'twilio':
                $sid = $this->getEnvValue($envContent, 'TWILIO_SID');
                $token = $this->getEnvValue($envContent, 'TWILIO_AUTH_TOKEN');
                $from = $this->getEnvValue($envContent, 'TWILIO_WHATSAPP_FROM');
                
                if (empty($sid) || empty($token) || empty($from)) {
                    return 'incomplete';
                }
                break;
                
            case 'vonage':
                $key = $this->getEnvValue($envContent, 'VONAGE_KEY');
                $secret = $this->getEnvValue($envContent, 'VONAGE_SECRET');
                $from = $this->getEnvValue($envContent, 'VONAGE_WHATSAPP_FROM');
                
                if (empty($key) || empty($secret) || empty($from)) {
                    return 'incomplete';
                }
                break;
                
            case 'custom':
                $url = $this->getEnvValue($envContent, 'WHATSAPP_API_URL');
                $apiKey = $this->getEnvValue($envContent, 'WHATSAPP_API_KEY');
                
                if (empty($url) || empty($apiKey)) {
                    return 'incomplete';
                }
                break;
                
            case '360dialog':
                $apiKey = $this->getEnvValue($envContent, 'DIALOG_API_KEY');
                $phoneId = $this->getEnvValue($envContent, 'DIALOG_PHONE_NUMBER_ID');
                $businessId = $this->getEnvValue($envContent, 'DIALOG_BUSINESS_ID');
                
                if (empty($apiKey) || empty($phoneId) || empty($businessId)) {
                    return 'incomplete';
                }
                break;
                
            case 'wati':
                $apiKey = $this->getEnvValue($envContent, 'WATI_API_KEY');
                $apiUrl = $this->getEnvValue($envContent, 'WATI_API_URL');
                
                if (empty($apiKey) || empty($apiUrl)) {
                    return 'incomplete';
                }
                break;
        }

        return 'configured';
    }

    /**
     * Get environment value from content
     */
    protected function getEnvValue(string $envContent, string $key, string $default = ''): string
    {
        if (preg_match("/^{$key}=(.*)/m", $envContent, $matches)) {
            return trim($matches[1], '"\'');
        }
        
        return $default;
    }

    /**
     * Validate email configuration
     */
    public function validateEmailConfiguration(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password (basic validation)
     */
    public function validatePassword(string $password): bool
    {
        return !empty(trim($password));
    }

    /**
     * Test email configuration with both email and password
     */
    public function testEmailConfiguration(string $email, ?string $password = null): array
    {
        if (!$this->validateEmailConfiguration($email)) {
            return [
                'success' => false,
                'message' => 'Invalid email address format'
            ];
        }

        if ($password !== null && !$this->validatePassword($password)) {
            return [
                'success' => false,
                'message' => 'Password cannot be empty'
            ];
        }

        $updateResult = $this->updateEmailConfiguration($email, $password);
        
        if (!$updateResult['success']) {
            return $updateResult;
        }

        $currentConfig = $this->getMailConfiguration();
        
        if (($currentConfig['username'] ?? '') !== $email) {
            return [
                'success' => false,
                'message' => 'Failed to verify email configuration update'
            ];
        }

        if ($password !== null && ($currentConfig['password'] ?? '') !== $password) {
            Log::warning('Password update verification failed', [
                'expected' => $password,
                'actual' => $currentConfig['password'] ?? 'null'
            ]);
        }

        return [
            'success' => true,
            'message' => 'Email configuration updated and verified successfully',
            'current_config' => $currentConfig
        ];
    }

    /**
     * Escape special characters in .env values
     */
    protected function escapeEnvValue(string $value): string
    {
        if (preg_match('/[\\s"\'#=]/', $value)) {
            $value = str_replace('"', '\"', $value);
            $value = '"' . $value . '"';
        }
        
        return $value;
    }

    /**
     * Test SMTP connection with current configuration
     */
    public function testSmtpConnection(): array
    {
        try {
            $config = $this->getMailConfiguration();
            
            if (empty($config['username']) || empty($config['password'])) {
                return [
                    'success' => false,
                    'message' => 'Email username or password not configured'
                ];
            }

            $transport = new \Swift_SmtpTransport(
                $config['host'] ?? 'smtp.gmail.com',
                $config['port'] ?? 587,
                $config['encryption'] ?? 'tls'
            );
            
            $transport->setUsername($config['username']);
            $transport->setPassword($config['password']);
            $transport->setTimeout(10);
            
            $mailer = new \Swift_Mailer($transport);
            $mailer->getTransport()->start();
            
            Log::info('SMTP connection test successful', [
                'host' => $config['host'],
                'username' => $config['username']
            ]);

            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];

        } catch (\Exception $e) {
            Log::error('SMTP connection test failed: ' . $e->getMessage(), [
                'config' => [
                    'host' => $config['host'] ?? 'unknown',
                    'username' => $config['username'] ?? 'unknown',
                    'port' => $config['port'] ?? 'unknown'
                ]
            ]);

            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update specific configuration value
     */
    public function updateConfigValue(string $key, string $value): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return ['success' => false, 'message' => '.env file not found'];
            }

            $envContent = File::get($this->envPath);
            $escapedValue = $this->escapeEnvValue($value);
            
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$escapedValue}",
                    $envContent
                );
            } else {
                $envContent .= "\n{$key}={$escapedValue}";
            }

            File::put($this->envPath, $envContent);
            Artisan::call('config:clear');

            Log::info('Configuration value updated', ['key' => $key]);

            return [
                'success' => true,
                'message' => "Configuration {$key} updated successfully"
            ];

        } catch (\Exception $e) {
            Log::error("Failed to update configuration {$key}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Failed to update configuration: " . $e->getMessage()
            ];
        }
    }

    /**
     * Backup .env file before making changes
     */
    public function backupEnvFile(): array
    {
        try {
            if (!File::exists($this->envPath)) {
                return ['success' => false, 'message' => '.env file not found'];
            }

            $backupPath = $this->envPath . '.backup.' . date('Y-m-d-His');
            File::copy($this->envPath, $backupPath);

            Log::info('.env file backed up', ['backup_path' => $backupPath]);

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'backup_path' => $backupPath
            ];

        } catch (\Exception $e) {
            Log::error('Failed to backup .env file: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create backup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore .env backup
     */
    protected function restoreEnvBackup(): bool
    {
        try {
            $backups = File::glob(base_path('.env.backup.*'));
            if (!empty($backups)) {
                $latestBackup = max($backups);
                File::copy($latestBackup, $this->envPath);
                Log::info('Restored .env from backup: ' . $latestBackup);
                return true;
            }
            Log::warning('No backups found to restore');
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to restore .env backup: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test WhatsApp configuration
     */
    public function testWhatsAppConfiguration(array $config): array
    {
        try {
            $provider = $config['provider'] ?? 'none';
            
            if ($provider === 'none') {
                return [
                    'success' => false,
                    'message' => 'No WhatsApp provider selected'
                ];
            }

            $validationResult = $this->validateWhatsAppConfiguration($config);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            $updateResult = $this->updateWhatsAppConfiguration($config);
            if (!$updateResult['success']) {
                return $updateResult;
            }

            switch ($provider) {
                case 'twilio':
                    return $this->testTwilioConfiguration($config);
                    
                case 'vonage':
                    return $this->testVonageConfiguration($config);
                    
                case 'custom':
                    return $this->testCustomWhatsAppConfiguration($config);
                    
                case '360dialog':
                    return $this->test360DialogConfiguration($config);
                    
                case 'wati':
                    return $this->testWatiConfiguration($config);
                    
                default:
                    return [
                        'success' => false,
                        'message' => 'Unsupported WhatsApp provider: ' . $provider
                    ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp configuration test failed: ' . $e->getMessage(), [
                'provider' => $config['provider'] ?? 'unknown'
            ]);

            return [
                'success' => false,
                'message' => 'WhatsApp configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate WhatsApp configuration
     */
    protected function validateWhatsAppConfiguration(array $config): array
    {
        $provider = $config['provider'] ?? 'none';
        
        switch ($provider) {
            case 'twilio':
                if (empty($config['twilio_sid']) || empty($config['twilio_token']) || empty($config['twilio_whatsapp_from'])) {
                    return [
                        'success' => false,
                        'message' => 'Twilio configuration requires SID, Token, and WhatsApp From number'
                    ];
                }
                break;
                
            case 'vonage':
                if (empty($config['vonage_key']) || empty($config['vonage_secret']) || empty($config['vonage_whatsapp_from'])) {
                    return [
                        'success' => false,
                        'message' => 'Vonage configuration requires API Key, Secret, and WhatsApp From number'
                    ];
                }
                break;
                
            case 'custom':
                if (empty($config['whatsapp_api_url']) || empty($config['whatsapp_api_key'])) {
                    return [
                        'success' => false,
                        'message' => 'Custom WhatsApp configuration requires API URL and API Key'
                    ];
                }
                break;
                
            case '360dialog':
                if (empty($config['dialog_api_key']) || empty($config['dialog_phone_number_id']) || empty($config['dialog_business_id'])) {
                    return [
                        'success' => false,
                        'message' => '360Dialog configuration requires API Key, Phone Number ID, and Business ID'
                    ];
                }
                break;
                
            case 'wati':
                if (empty($config['wati_api_key']) || empty($config['wati_api_url'])) {
                    return [
                        'success' => false,
                        'message' => 'WATI configuration requires API Key and API URL'
                    ];
                }
                break;
                
            case 'none':
                return [
                    'success' => false,
                    'message' => 'Please select a WhatsApp provider'
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported WhatsApp provider: ' . $provider
                ];
        }

        return ['success' => true];
    }

    /**
     * Test Twilio configuration
     */
    protected function testTwilioConfiguration(array $config): array
    {
        try {
            $sid = $config['twilio_sid'] ?? '';
            $token = $config['twilio_token'] ?? '';
            $from = $config['twilio_whatsapp_from'] ?? '';
            
            if (!preg_match('/^AC/', $sid)) {
                return [
                    'success' => false,
                    'message' => 'Invalid Twilio SID format. Should start with "AC"'
                ];
            }
            
            if (!preg_match('/^whatsapp:\+\d+$/', $from)) {
                return [
                    'success' => false,
                    'message' => 'Invalid WhatsApp From format. Should be: whatsapp:+1234567890'
                ];
            }

            Log::info('Twilio configuration test passed', [
                'sid' => substr($sid, 0, 8) . '...',
                'from' => $from
            ]);

            return [
                'success' => true,
                'message' => 'Twilio configuration validated successfully',
                'provider' => 'twilio'
            ];

        } catch (\Exception $e) {
            Log::error('Twilio configuration test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Twilio configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test Vonage configuration
     */
    protected function testVonageConfiguration(array $config): array
    {
        try {
            $key = $config['vonage_key'] ?? '';
            $secret = $config['vonage_secret'] ?? '';
            $from = $config['vonage_whatsapp_from'] ?? '';
            
            if (!preg_match('/^[a-zA-Z0-9]+$/', $key)) {
                return [
                    'success' => false,
                    'message' => 'Invalid Vonage API Key format'
                ];
            }
            
            if (!preg_match('/^\+\d+$/', $from)) {
                return [
                    'success' => false,
                    'message' => 'Invalid WhatsApp From format. Should be: +1234567890'
                ];
            }

            Log::info('Vonage configuration test passed', [
                'key' => substr($key, 0, 8) . '...',
                'from' => $from
            ]);

            return [
                'success' => true,
                'message' => 'Vonage configuration validated successfully',
                'provider' => 'vonage'
            ];

        } catch (\Exception $e) {
            Log::error('Vonage configuration test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Vonage configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test custom WhatsApp configuration
     */
    protected function testCustomWhatsAppConfiguration(array $config): array
    {
        try {
            $url = $config['whatsapp_api_url'] ?? '';
            $apiKey = $config['whatsapp_api_key'] ?? '';
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid WhatsApp API URL format'
                ];
            }
            
            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => 'WhatsApp API Key is required'
                ];
            }

            Log::info('Custom WhatsApp configuration test passed', [
                'url' => $url,
                'api_key_length' => strlen($apiKey)
            ]);

            return [
                'success' => true,
                'message' => 'Custom WhatsApp configuration validated successfully',
                'provider' => 'custom'
            ];

        } catch (\Exception $e) {
            Log::error('Custom WhatsApp configuration test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Custom WhatsApp configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test 360Dialog configuration
     */
    protected function test360DialogConfiguration(array $config): array
    {
        try {
            $apiKey = $config['dialog_api_key'] ?? '';
            $phoneId = $config['dialog_phone_number_id'] ?? '';
            $businessId = $config['dialog_business_id'] ?? '';
            
            if (empty($apiKey) || empty($phoneId) || empty($businessId)) {
                return [
                    'success' => false,
                    'message' => '360Dialog requires API Key, Phone Number ID, and Business ID'
                ];
            }

            Log::info('360Dialog configuration test passed', [
                'api_key_length' => strlen($apiKey),
                'phone_id' => $phoneId,
                'business_id' => $businessId
            ]);

            return [
                'success' => true,
                'message' => '360Dialog configuration validated successfully',
                'provider' => '360dialog'
            ];

        } catch (\Exception $e) {
            Log::error('360Dialog configuration test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '360Dialog configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test WATI configuration
     */
    protected function testWatiConfiguration(array $config): array
    {
        try {
            $apiKey = $config['wati_api_key'] ?? '';
            $apiUrl = $config['wati_api_url'] ?? '';
            
            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => 'WATI API Key is required'
                ];
            }
            
            if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid WATI API URL format'
                ];
            }

            Log::info('WATI configuration test passed', [
                'api_key_length' => strlen($apiKey),
                'api_url' => $apiUrl
            ]);

            return [
                'success' => true,
                'message' => 'WATI configuration validated successfully',
                'provider' => 'wati'
            ];

        } catch (\Exception $e) {
            Log::error('WATI configuration test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'WATI configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test app configuration update
     */
    public function testAppConfiguration(string $appName, string $appShortName): array
    {
        try {
            $updateResult = $this->updateAppConfiguration($appName, $appShortName);
            
            if (!$updateResult['success']) {
                return $updateResult;
            }

            $currentConfig = $this->getAppConfiguration();
            
            if ($currentConfig['app_name'] !== $appName || $currentConfig['app_short_name'] !== $appShortName) {
                return [
                    'success' => false,
                    'message' => 'Failed to verify app configuration update'
                ];
            }

            return [
                'success' => true,
                'message' => 'App configuration updated and verified successfully',
                'current_config' => $currentConfig
            ];

        } catch (\Exception $e) {
            Log::error('App configuration test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'App configuration test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all configuration sections
     */
    public function getAllConfiguration(): array
    {
        return [
            'app' => $this->getAppConfiguration(),
            'mail' => $this->getMailConfiguration(),
            'whatsapp' => $this->getWhatsAppConfiguration()
        ];
    }

    /**
     * Validate app name and short name
     */
    public function validateAppConfiguration(string $appName, string $appShortName): array
    {
        $errors = [];

        if (empty(trim($appName))) {
            $errors[] = 'App name cannot be empty';
        }

        if (empty(trim($appShortName))) {
            $errors[] = 'App short name cannot be empty';
        }

        if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $appName)) {
            $errors[] = 'App name contains invalid characters';
        }

        if (!preg_match('/^[a-z0-9\-_]+$/', $appShortName)) {
            $errors[] = 'App short name can only contain lowercase letters, numbers, hyphens, and underscores';
        }

        if (strlen($appShortName) > 50) {
            $errors[] = 'App short name must be 50 characters or less';
        }

        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get WhatsApp provider display name
     */
    public function getWhatsAppProviderDisplayName(string $provider): string
    {
        $providers = [
            'twilio' => 'Twilio WhatsApp',
            'vonage' => 'Vonage WhatsApp',
            'custom' => 'Custom WhatsApp API',
            '360dialog' => '360Dialog WhatsApp',
            'wati' => 'WATI WhatsApp',
            'none' => 'None',
        ];

        return $providers[$provider] ?? $provider;
    }

    /**
     * Get all WhatsApp providers with their configuration status
     */
    public function getAllWhatsAppProviders(): array
    {
        $currentConfig = $this->getWhatsAppConfiguration();
        $providers = [];

        $providerList = ['twilio', 'vonage', 'custom', '360dialog', 'wati'];

        foreach ($providerList as $provider) {
            $providers[$provider] = [
                'name' => $this->getWhatsAppProviderDisplayName($provider),
                'key' => $provider,
                'is_active' => $currentConfig['provider'] === $provider,
                'is_configured' => $this->isProviderConfigured($provider, $currentConfig),
                'status' => $currentConfig['provider'] === $provider ? $currentConfig['status'] : 'inactive',
            ];
        }

        return $providers;
    }

    /**
     * Check if a specific provider is configured
     */
    protected function isProviderConfigured(string $provider, array $config): bool
    {
        switch ($provider) {
            case 'twilio':
                return !empty($config['twilio_sid']) && 
                       !empty($config['twilio_token']) && 
                       !empty($config['twilio_whatsapp_from']);
                
            case 'vonage':
                return !empty($config['vonage_key']) && 
                       !empty($config['vonage_secret']) && 
                       !empty($config['vonage_whatsapp_from']);
                
            case 'custom':
                return !empty($config['whatsapp_api_url']) && 
                       !empty($config['whatsapp_api_key']);
                
            case '360dialog':
                return !empty($config['dialog_api_key']) && 
                       !empty($config['dialog_phone_number_id']) && 
                       !empty($config['dialog_business_id']);
                
            case 'wati':
                return !empty($config['wati_api_key']) && 
                       !empty($config['wati_api_url']);
                
            default:
                return false;
        }
    }
}