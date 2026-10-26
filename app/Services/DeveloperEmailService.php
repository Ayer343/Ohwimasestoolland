<?php

namespace App\Services;

use App\Models\DeveloperSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Encryption\DecryptException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class DeveloperEmailService
{
    /**
     * Test email configuration (connection-only).
     *
     * Email-sending test types ('send', 'template', 'full') were removed
     * because the corresponding Blade UI has been stripped.
     */
    public function testConfiguration($settings, $testEmail = null, $testType = 'connection', $configType = 'developer')
    {
        Log::debug('=== DEVELOPER EMAIL SERVICE TEST START ===', [
            'test_type'   => $testType,
            'config_type' => $configType,
        ]);

        try {
            $config = $this->getEmailConfig($configType);

            Log::debug('Using configuration', [
                'type'       => $configType,
                'username'   => $config['username'] ?? 'not_set',
                'host'       => $config['host'] ?? 'not_set',
                'port'       => $config['port'] ?? 'not_set',
                'encryption' => $config['encryption'] ?? 'not_set',
            ]);

            $validationResult = $this->validateSmtpConfig($config);
            if (!$validationResult['valid']) {
                return [
                    'success'   => false,
                    'message'   => 'Configuration invalid: ' . $validationResult['message'],
                    'timestamp' => now()->toISOString(),
                    'type'      => $configType,
                ];
            }

            // Only 'connection' is supported now.
            if ($testType === 'connection') {
                return $this->testConnection($config, $configType);
            }

            return [
                'success'   => false,
                'message'   => "Test type '{$testType}' has been removed. Only 'connection' is supported.",
                'timestamp' => now()->toISOString(),
                'type'      => $configType,
            ];
        } catch (\Throwable $e) {
            Log::error('Email configuration test failed: ' . $e->getMessage(), [
                'trace'       => $e->getTraceAsString(),
                'test_type'   => $testType,
                'config_type' => $configType,
            ]);

            return [
                'success'   => false,
                'message'   => 'Test failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'type'      => $configType,
            ];
        }
    }

    /**
     * Safely decrypt a value that may or may not be encrypted.
     *
     * .env values should always be plaintext. If a value is encrypted
     * (base64 JSON payload starting with "eyJ"), we attempt to decrypt it;
     * if that fails, we fall back to the raw value instead of throwing
     * and spamming warnings.
     */
    private function safeDecrypt(?string $value, string $context = 'value'): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Laravel encrypted payloads are base64-encoded JSON objects: {"iv":...}
        // They always start with "eyJ" after base64 encoding.
        if (!str_starts_with($value, 'eyJ')) {
            return $value; // Plaintext — return as-is
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Silently fall back to the raw value. Log at debug level only.
            Log::debug("Failed to decrypt {$context}, using raw value", [
                'context' => $context,
                'reason'  => $e->getMessage(),
            ]);

            return $value;
        }
    }

    /**
     * Get email configuration with safe decryption.
     */
    private function getEmailConfig($configType = 'developer')
    {
        Log::debug('Getting email config for type: ' . $configType);

        if ($configType === 'developer') {
            $rawPassword = env('DEVELOPER_MAIL_PASSWORD', '');

            // Never throw on invalid encrypted payloads — fall back to plaintext.
            $decryptedPassword = $this->safeDecrypt($rawPassword, 'DEVELOPER_MAIL_PASSWORD');

            $passwordValidation = $this->validateEnvPassword($rawPassword, $decryptedPassword);

            $gmailHelp = $this->getGmailHelp(
                env('DEVELOPER_MAIL_HOST', 'smtp.gmail.com'),
                env('DEVELOPER_MAIL_USERNAME', '')
            );

            $encryption = env('DEVELOPER_MAIL_ENCRYPTION', 'tls');
            if (!in_array($encryption, ['tls', 'ssl', 'none', 'null', ''], true)) {
                $encryption = 'tls';
            }

            return [
                'transport'       => 'smtp',
                'host'            => env('DEVELOPER_MAIL_HOST', 'smtp.gmail.com'),
                'port'            => (int) env('DEVELOPER_MAIL_PORT', 587),
                'encryption'      => $encryption,
                'username'        => env('DEVELOPER_MAIL_USERNAME', ''),
                'password'        => $decryptedPassword,
                'timeout'         => (int) env('DEVELOPER_MAIL_TIMEOUT', 30),
                'from_address'    => env('DEVELOPER_MAIL_FROM_ADDRESS', ''),
                'from_name'       => env('DEVELOPER_MAIL_FROM_NAME', 'Developer System'),
                'verify_peer'     => env('DEVELOPER_MAIL_VERIFY_PEER', true),
                'verify_peer_name'=> env('DEVELOPER_MAIL_VERIFY_PEER_NAME', true),
                'auth_mode'       => env('DEVELOPER_MAIL_AUTH_MODE', null),
                'local_domain'    => env('DEVELOPER_MAIL_LOCAL_DOMAIN', null),
                'password_status' => $passwordValidation,
                'gmail_help'      => $gmailHelp,
                'config_type'     => 'developer',
            ];
        }

        // System mail config
        $rawPassword = env('MAIL_PASSWORD', '');
        $decryptedPassword = $this->safeDecrypt($rawPassword, 'MAIL_PASSWORD');

        $encryption = env('MAIL_ENCRYPTION', 'tls');
        if (!in_array($encryption, ['tls', 'ssl', 'none', 'null', ''], true)) {
            $encryption = 'tls';
        }

        return [
            'transport'    => 'smtp',
            'host'         => env('MAIL_HOST', 'smtp.gmail.com'),
            'port'         => (int) env('MAIL_PORT', 587),
            'encryption'   => $encryption,
            'username'     => env('MAIL_USERNAME', ''),
            'password'     => $decryptedPassword,
            'timeout'      => (int) env('MAIL_TIMEOUT', 30),
            'from_address' => env('MAIL_FROM_ADDRESS', ''),
            'from_name'    => env('MAIL_FROM_NAME', ''),
            'config_type'  => 'system',
        ];
    }

    /**
     * Validate password format (plaintext or encrypted).
     */
    private function validateEnvPassword(string $rawPassword, ?string $decryptedPassword): array
    {
        if ($rawPassword === '') {
            return ['valid' => false, 'message' => 'Password is empty'];
        }

        // If the raw value looks encrypted, make sure it decrypted successfully.
        if (str_starts_with($rawPassword, 'eyJ') && $rawPassword === $decryptedPassword) {
            return [
                'valid'   => false,
                'message' => 'Password appears to be encrypted but could not be decrypted. '
                           . 'APP_KEY may have changed. Store the password as plaintext in .env.',
            ];
        }

        return ['valid' => true, 'message' => 'Password format OK'];
    }

    /**
     * Detect Gmail and return help text (null if not Gmail).
     */
    private function getGmailHelp(string $host, string $username): ?array
    {
        $host = strtolower($host);
        $username = strtolower($username);

        if (!str_contains($host, 'gmail.com') && !str_contains($username, '@gmail.com')) {
            return null;
        }

        return [
            'requires_2fa'            => true,
            'requires_app_password'   => true,
            'app_password_length'     => 16,
            'app_password_spaces_ok'  => true,
            'notes'                   => [
                'Enable 2-Step Verification on your Google account.',
                'Generate a 16-character App Password under Security → App Passwords.',
                'Use the App Password as DEVELOPER_MAIL_PASSWORD (plaintext, spaces are ignored).',
            ],
        ];
    }

    /**
     * Validate SMTP configuration.
     */
    private function validateSmtpConfig(array $config)
    {
        try {
            Log::debug('Validating SMTP configuration', [
                'config_type'     => $config['config_type'] ?? 'unknown',
                'has_password'    => !empty($config['password']),
                'password_length' => strlen($config['password'] ?? ''),
            ]);

            foreach (['host', 'port', 'username', 'password'] as $field) {
                if (empty($config[$field])) {
                    $error = "Required field '{$field}' is empty";

                    if ($field === 'password') {
                        $error .= '. Check password format (must be plaintext in .env).';
                        if (!empty($config['gmail_help'])) {
                            $error .= ' Gmail requires a 16-character App Password.';
                        }
                    }

                    Log::error($error);
                    return ['valid' => false, 'message' => $error];
                }
            }

            if (!is_numeric($config['port']) || $config['port'] < 1 || $config['port'] > 65535) {
                $error = "Invalid port number: {$config['port']}";
                Log::error($error);
                return ['valid' => false, 'message' => $error];
            }

            if (!filter_var($config['username'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Username must be a valid email address';
                Log::error($error);
                return ['valid' => false, 'message' => $error];
            }

            $validEncryption = ['tls', 'ssl', 'null', 'none', ''];
            if (!in_array($config['encryption'], $validEncryption, true)) {
                $error = "Invalid encryption: {$config['encryption']}";
                Log::error($error);
                return ['valid' => false, 'message' => $error];
            }

            if (str_contains(strtolower($config['host']), 'gmail.com')) {
                $passwordLength = strlen(str_replace(' ', '', $config['password']));
                if ($passwordLength !== 16) {
                    Log::warning('Gmail password length may be incorrect', [
                        'length'   => $passwordLength,
                        'expected' => 16,
                    ]);
                }
            }

            Log::debug('SMTP configuration validation passed');
            return [
                'valid'           => true,
                'message'         => 'Configuration is valid',
                'password_length' => strlen($config['password']),
            ];
        } catch (\Throwable $e) {
            Log::error('Validation error: ' . $e->getMessage());
            return ['valid' => false, 'message' => 'Validation error: ' . $e->getMessage()];
        }
    }

    /**
     * Test SMTP connection.
     */
    private function testConnection(array $config, $configType = 'developer')
    {
        $startTime = microtime(true);

        try {
            Log::debug('Testing SMTP connection for ' . $configType, [
                'host'            => $config['host'],
                'port'            => $config['port'],
                'username'        => $config['username'],
                'has_password'    => !empty($config['password']),
                'password_length' => strlen($config['password'] ?? ''),
            ]);

            $this->testSocketConnection($config['host'], $config['port'], $config['timeout'] ?? 10);

            $transport = $this->createMailTransport($config);
            $transport->start();

            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('SMTP connection test passed', [
                'time_ms'     => $connectionTime,
                'config_type' => $configType,
                'host'        => $config['host'],
                'username'    => $config['username'],
            ]);

            return [
                'success'            => true,
                'message'            => ucfirst($configType) . ' SMTP connection and authentication successful',
                'connection_time_ms' => $connectionTime,
                'timestamp'          => now()->toISOString(),
                'config'             => [
                    'host'           => $config['host'],
                    'port'           => $config['port'],
                    'encryption'     => $config['encryption'],
                    'username'       => $config['username'],
                    'type'           => $configType,
                    'authentication' => 'verified',
                ],
            ];
        } catch (\Throwable $e) {
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
            $errorMessage = $e->getMessage();
            $userMessage = $this->analyzeSmtpError($errorMessage, $config);

            Log::error('SMTP connection test failed: ' . $errorMessage, [
                'config' => [
                    'host'     => $config['host'],
                    'port'     => $config['port'],
                    'username' => $config['username'],
                ],
                'config_type' => $configType,
            ]);

            return [
                'success'            => false,
                'message'            => $userMessage,
                'connection_time_ms' => $connectionTime,
                'timestamp'          => now()->toISOString(),
                'type'               => $configType,
                'error_details'      => $errorMessage,
            ];
        }
    }

    /**
     * Analyze SMTP errors for friendlier messages.
     */
    private function analyzeSmtpError($errorMessage, $config)
    {
        $lowerError = strtolower($errorMessage);

        if (str_contains($lowerError, 'authentication failed')
            || str_contains($lowerError, 'invalid login')
            || str_contains($lowerError, '535')
            || str_contains($lowerError, 'auth')) {

            $host = strtolower($config['host'] ?? '');
            if (str_contains($host, 'gmail.com')) {
                return "Gmail authentication failed. Make sure:\n"
                     . "1. 2-Step Verification is enabled on your Google account\n"
                     . "2. You're using a 16-character App Password (not your regular password)\n"
                     . "3. App Password is generated for 'Mail' application";
            }

            return 'Authentication failed. Please check your username and password.';
        }

        if (str_contains($lowerError, 'connection refused')
            || str_contains($lowerError, 'could not connect')
            || str_contains($lowerError, 'connection timed out')) {
            return "Cannot connect to SMTP server. Check:\n"
                 . "1. SMTP host and port are correct\n"
                 . '2. Firewall allows outgoing connections on port ' . ($config['port'] ?? 'unknown') . "\n"
                 . '3. Server is accessible from your location';
        }

        if (str_contains($lowerError, 'tls')
            || str_contains($lowerError, 'ssl')
            || str_contains($lowerError, 'encryption')) {

            $port = $config['port'] ?? 587;
            $encryption = $config['encryption'] ?? 'tls';

            if ($port == 587 && $encryption !== 'tls') {
                return "Port 587 usually requires TLS encryption. Please use encryption: 'tls'";
            }
            if ($port == 465 && $encryption !== 'ssl') {
                return "Port 465 usually requires SSL encryption. Please use encryption: 'ssl'";
            }

            return "Encryption error. Try changing encryption from '{$encryption}' to "
                 . ($encryption === 'tls' ? "'ssl'" : "'tls'");
        }

        if (str_contains($lowerError, 'password')
            && str_contains(strtolower($config['host'] ?? ''), 'gmail.com')) {
            return 'Gmail password format issue. Use 16-character App Password from Google.';
        }

        return 'Connection failed: ' . $errorMessage;
    }

    /**
     * Test socket connection.
     */
    private function testSocketConnection($host, $port, $timeout = 10)
    {
        Log::debug('Testing socket connection', ['host' => $host, 'port' => $port]);

        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            throw new \Exception("Cannot connect to {$host}:{$port} - {$errstr} (Code: {$errno})");
        }

        fclose($socket);
        Log::debug('Socket connection successful');
    }

    /**
     * Create Symfony mail transport.
     */
    private function createMailTransport(array $config)
    {
        Log::debug('Creating mail transport', [
            'host'        => $config['host'],
            'port'        => $config['port'],
            'username'    => $config['username'],
            'encryption'  => $config['encryption'] ?? 'tls',
            'config_type' => $config['config_type'] ?? 'unknown',
        ]);

        try {
            $scheme = 'smtp';
            if ($config['encryption'] === 'ssl' || $config['port'] == 465) {
                $scheme = 'smtps';
            }

            $dsn = new Dsn(
                $scheme,
                $config['host'],
                $config['username'],
                $config['password'],
                $config['port']
            );

            $factory = new EsmtpTransportFactory();
            $transport = $factory->create($dsn);

            $timeout = $config['timeout'] ?? 30;
            if (method_exists($transport, 'setStreamTimeout')) {
                $transport->setStreamTimeout($timeout);
            } elseif (method_exists($transport, 'setTimeout')) {
                $transport->setTimeout($timeout);
            }

            if (!empty($config['local_domain']) && method_exists($transport, 'setLocalDomain')) {
                $transport->setLocalDomain($config['local_domain']);
            }

            if (isset($config['verify_peer']) && !$config['verify_peer']) {
                if (method_exists($transport, 'getStream')) {
                    $stream = $transport->getStream();
                    if ($stream && method_exists($stream, 'setVerifyPeer')) {
                        $stream->setVerifyPeer(false);
                    }
                }
            }

            Log::debug('Mail transport created successfully');
            return $transport;
        } catch (\Throwable $e) {
            Log::error('Failed to create mail transport: ' . $e->getMessage(), [
                'config' => [
                    'host'     => $config['host'],
                    'port'     => $config['port'],
                    'username' => $config['username'],
                ],
                'error_details' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check email configuration status — connection + validation only.
     * No test emails are sent.
     */
    public function checkConfigurationStatus($configType = 'developer')
    {
        try {
            Log::debug('Checking email configuration status for: ' . $configType);

            $config = $this->getEmailConfig($configType);

            if (empty($config['host']) || empty($config['username'])) {
                return [
                    'configured'     => false,
                    'connection'     => false,
                    'authentication' => false,
                    'can_send'       => false,
                    'message'        => ucfirst($configType) . ' email configuration not set up',
                    'timestamp'      => now()->toISOString(),
                    'type'           => $configType,
                    'diagnostics'    => [
                        'missing_host'     => empty($config['host']),
                        'missing_username' => empty($config['username']),
                        'has_password'     => !empty($config['password']),
                        'password_length'  => strlen($config['password'] ?? ''),
                    ],
                ];
            }

            $validation = $this->validateSmtpConfig($config);
            if (!$validation['valid']) {
                return [
                    'configured'         => true,
                    'connection'         => false,
                    'authentication'     => false,
                    'can_send'           => false,
                    'message'            => 'Configuration invalid: ' . $validation['message'],
                    'timestamp'          => now()->toISOString(),
                    'type'               => $configType,
                    'validation_details' => $validation,
                ];
            }

            $connectionTest = $this->testConnection($config, $configType);

            if (!$connectionTest['success']) {
                return [
                    'configured'         => true,
                    'connection'         => false,
                    'authentication'     => false,
                    'can_send'           => false,
                    'message'            => $connectionTest['message'],
                    'timestamp'          => now()->toISOString(),
                    'type'               => $configType,
                    'connection_details' => $connectionTest,
                ];
            }

            return [
                'configured'       => true,
                'connection'       => true,
                'authentication'   => true,
                'can_send'         => true,
                'message'          => ucfirst($configType) . ' email configuration is fully operational (connection verified)',
                'last_test'        => now()->toISOString(),
                'connection_time'  => $connectionTest['connection_time_ms'] ?? null,
                'type'             => $configType,
                'config_summary'   => [
                    'host'            => $config['host'],
                    'port'            => $config['port'],
                    'encryption'      => $config['encryption'],
                    'from_address'    => $config['from_address'],
                    'from_name'       => $config['from_name'],
                    'username'        => $config['username'],
                    'password_status' => $config['password_status'] ?? [],
                ],
                'test_results'     => [
                    'connection' => $connectionTest,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to check email configuration status: ' . $e->getMessage());
            return [
                'configured'     => false,
                'connection'     => false,
                'authentication' => false,
                'can_send'       => false,
                'message'        => 'Error checking configuration: ' . $e->getMessage(),
                'timestamp'      => now()->toISOString(),
                'type'           => $configType,
            ];
        }
    }

    /**
     * Comprehensive test — validation + connection only.
     */
    public function runComprehensiveTest($configType = 'developer')
    {
        try {
            Log::info('Starting comprehensive email test for: ' . $configType);

            $results = [];
            $config = $this->getEmailConfig($configType);
            $results['configuration'] = $config;

            $validation = $this->validateSmtpConfig($config);
            $results['validation'] = $validation;

            if ($validation['valid']) {
                $results['connection_test'] = $this->testConnection($config, $configType);
            }

            $results['diagnostics'] = $this->getDetailedDiagnostics($configType);

            $overallSuccess = $validation['valid']
                && (!isset($results['connection_test']) || $results['connection_test']['success']);

            Log::info('Comprehensive test completed', [
                'success'     => $overallSuccess,
                'config_type' => $configType,
                'timestamp'   => now()->toISOString(),
            ]);

            return [
                'success'         => $overallSuccess,
                'message'         => $overallSuccess
                    ? ucfirst($configType) . ' email configuration is fully operational'
                    : ucfirst($configType) . ' email configuration has issues',
                'timestamp'       => now()->toISOString(),
                'config_type'     => $configType,
                'results'         => $results,
                'recommendations' => $this->generateRecommendations($results),
            ];
        } catch (\Throwable $e) {
            Log::error('Comprehensive test failed: ' . $e->getMessage());
            return [
                'success'     => false,
                'message'     => 'Comprehensive test failed: ' . $e->getMessage(),
                'timestamp'   => now()->toISOString(),
                'config_type' => $configType,
            ];
        }
    }

    /**
     * Generate recommendations based on test results.
     */
    private function generateRecommendations(array $results)
    {
        $recommendations = [];

        if (isset($results['validation']) && !$results['validation']['valid']) {
            $recommendations[] = 'Fix validation errors: ' . ($results['validation']['message'] ?? 'Unknown');
        }

        if (isset($results['connection_test']) && !$results['connection_test']['success']) {
            $error = $results['connection_test']['error_details'] ?? '';

            if (str_contains(strtolower($error), 'authentication')) {
                $recommendations[] = 'Authentication failed. Check username and password.';

                $config = $results['configuration'] ?? [];
                if (str_contains(strtolower($config['host'] ?? ''), 'gmail.com')) {
                    $recommendations[] = 'For Gmail: Use a 16-character App Password (not your regular password)';
                    $recommendations[] = 'Enable 2-Step Verification in your Google Account first';
                }
            }

            if (str_contains(strtolower($error), 'connection refused')) {
                $recommendations[] = 'Cannot connect to SMTP server. Check host and port.';
                $recommendations[] = 'Verify firewall allows outgoing connections';
            }
        }

        $config = $results['configuration'] ?? [];
        $passwordStatus = $config['password_status'] ?? [];
        if (isset($passwordStatus['valid']) && !$passwordStatus['valid']) {
            $recommendations[] = 'Password issue: ' . ($passwordStatus['message'] ?? 'Unknown');
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Configuration appears to be working correctly.';
        }

        return $recommendations;
    }

    /**
     * Detailed diagnostics.
     */
    public function getDetailedDiagnostics($configType = 'developer')
    {
        $config = $this->getEmailConfig($configType);

        return [
            'system' => [
                'php_version'     => phpversion(),
                'laravel_version' => app()->version(),
                'server'          => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'timezone'        => config('app.timezone'),
                'environment'     => app()->environment(),
            ],
            'php_mail' => [
                'mail_function'  => function_exists('mail'),
                'smtp'           => ini_get('SMTP'),
                'smtp_port'      => ini_get('smtp_port'),
                'sendmail_path'  => ini_get('sendmail_path'),
                'openssl_loaded' => extension_loaded('openssl'),
                'curl_loaded'    => extension_loaded('curl'),
            ],
            'configuration' => [
                'type'            => $configType,
                'host'            => $config['host'] ?? 'not_set',
                'port'            => $config['port'] ?? 'not_set',
                'encryption'      => $config['encryption'] ?? 'not_set',
                'username'        => $config['username'] ?? 'not_set',
                'has_password'    => !empty($config['password']),
                'password_length' => strlen($config['password'] ?? ''),
                'from_address'    => $config['from_address'] ?? 'not_set',
                'from_name'       => $config['from_name'] ?? 'not_set',
                'password_status' => $config['password_status'] ?? [],
            ],
            'env_status'     => $this->checkEnvFileStatus(),
            'permissions'    => $this->checkFilePermissions(),
            'gmail_detected' => str_contains(strtolower($config['host'] ?? ''), 'gmail.com'),
            'gmail_help'     => $config['gmail_help'] ?? null,
            'timestamp'      => now()->toISOString(),
        ];
    }

    /**
     * Check .env file status.
     */
    private function checkEnvFileStatus()
    {
        $envPath = base_path('.env');

        return [
            'exists'        => file_exists($envPath),
            'readable'      => is_readable($envPath),
            'writable'      => is_writable($envPath),
            'size'          => file_exists($envPath) ? filesize($envPath) : 0,
            'last_modified' => file_exists($envPath) ? date('Y-m-d H:i:s', filemtime($envPath)) : null,
            'path'          => $envPath,
        ];
    }

    /**
     * Check file permissions.
     */
    private function checkFilePermissions()
    {
        $paths = [
            base_path('.env')                  => 'Read/Write .env',
            storage_path('logs')               => 'Write logs',
            storage_path('framework/cache')    => 'Cache directory',
            storage_path('framework/views')    => 'Views cache',
            storage_path('framework/sessions') => 'Sessions',
            storage_path()                     => 'Storage directory',
        ];

        $permissions = [];
        foreach ($paths as $path => $description) {
            $exists = file_exists($path);

            $entry = [
                'exists'   => $exists,
                'readable' => $exists ? is_readable($path) : false,
                'writable' => $exists ? is_writable($path) : false,
                'is_dir'   => $exists ? is_dir($path) : false,
            ];

            if ($exists && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                try {
                    if (function_exists('posix_getpwuid')) {
                        $entry['owner'] = posix_getpwuid(fileowner($path))['name'] ?? 'unknown';
                    }
                    if (function_exists('posix_getgrgid')) {
                        $entry['group'] = posix_getgrgid(filegroup($path))['name'] ?? 'unknown';
                    }
                    $entry['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
                } catch (\Throwable $e) {
                    // Silently ignore
                }
            } else {
                $entry['owner'] = 'windows';
                $entry['group'] = 'windows';
                $entry['permissions'] = 'windows';
            }

            $permissions[$description] = $entry;
        }

        return $permissions;
    }

    /**
     * Fix common configuration issues.
     */
    public function fixCommonIssues($configType = 'developer')
    {
        try {
            Log::info('Attempting to fix common email configuration issues', ['type' => $configType]);

            $fixesApplied = [];
            $config = $this->getEmailConfig($configType);

            if ($config['port'] == 587 && $config['encryption'] !== 'tls') {
                $oldEncryption = $config['encryption'];
                $config['encryption'] = 'tls';
                $fixesApplied[] = "Changed encryption from '{$oldEncryption}' to 'tls' for port 587";
            }

            if ($config['port'] == 465 && $config['encryption'] !== 'ssl') {
                $oldEncryption = $config['encryption'];
                $config['encryption'] = 'ssl';
                $fixesApplied[] = "Changed encryption from '{$oldEncryption}' to 'ssl' for port 465";
            }

            if (str_contains(strtolower($config['host']), 'gmail.com')) {
                $password = $config['password'] ?? '';
                $cleanPassword = str_replace(' ', '', $password);

                if (strlen($cleanPassword) !== 16) {
                    $fixesApplied[] = 'Gmail password length incorrect: ' . strlen($cleanPassword)
                                    . ' chars (should be 16)';
                }
            }

            if (empty($config['timeout']) || $config['timeout'] < 10) {
                $oldTimeout = $config['timeout'] ?? 'not_set';
                $config['timeout'] = 30;
                $fixesApplied[] = "Increased timeout from {$oldTimeout} to 30 seconds";
            }

            if (!empty($fixesApplied)) {
                $this->updateConfigurationWithFixes($config, $configType);
            }

            return [
                'success'       => true,
                'message'       => !empty($fixesApplied)
                    ? 'Applied ' . count($fixesApplied) . ' fixes'
                    : 'No fixes needed',
                'fixes_applied' => $fixesApplied,
                'timestamp'     => now()->toISOString(),
                'config_type'   => $configType,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to fix common issues: ' . $e->getMessage());
            return [
                'success'     => false,
                'message'     => 'Failed to fix issues: ' . $e->getMessage(),
                'timestamp'   => now()->toISOString(),
                'config_type' => $configType,
            ];
        }
    }

    /**
     * Persist fixes to .env.
     */
    private function updateConfigurationWithFixes(array $config, $configType)
    {
        try {
            if ($configType !== 'developer') {
                return;
            }

            $envPath = base_path('.env');
            $envContent = File::get($envPath);

            if (isset($config['encryption'])) {
                $pattern = '/^DEVELOPER_MAIL_ENCRYPTION=.*$/m';
                $replacement = "DEVELOPER_MAIL_ENCRYPTION=\"{$config['encryption']}\"";
                $envContent = preg_match($pattern, $envContent)
                    ? preg_replace($pattern, $replacement, $envContent)
                    : $envContent . "\n{$replacement}\n";
            }

            if (isset($config['timeout'])) {
                $pattern = '/^DEVELOPER_MAIL_TIMEOUT=.*$/m';
                $replacement = "DEVELOPER_MAIL_TIMEOUT={$config['timeout']}";
                $envContent = preg_match($pattern, $envContent)
                    ? preg_replace($pattern, $replacement, $envContent)
                    : $envContent . "\n{$replacement}\n";
            }

            File::put($envPath, $envContent);

            Artisan::call('config:clear');
            Artisan::call('config:cache');

            Log::debug('Updated .env with configuration fixes');
        } catch (\Throwable $e) {
            Log::error('Failed to update .env with fixes: ' . $e->getMessage());
        }
    }

    /**
     * Reset developer email config to defaults.
     */
    public function resetToDefaults($configType = 'developer')
    {
        try {
            Log::info('Resetting email configuration to defaults', ['type' => $configType]);

            if ($configType === 'developer') {
                $configService = new DeveloperEmailConfigurationService();
                $result = $configService->resetDeveloperEmailConfig();

                return [
                    'success'     => $result['success'],
                    'message'     => $result['message'],
                    'timestamp'   => now()->toISOString(),
                    'config_type' => $configType,
                    'details'     => $result,
                ];
            }

            return [
                'success'     => false,
                'message'     => 'Reset only available for developer configuration',
                'timestamp'   => now()->toISOString(),
                'config_type' => $configType,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to reset configuration: ' . $e->getMessage());
            return [
                'success'     => false,
                'message'     => 'Reset failed: ' . $e->getMessage(),
                'timestamp'   => now()->toISOString(),
                'config_type' => $configType,
            ];
        }
    }
}