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
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Transport\Dsn;

class DeveloperEmailService
{
    /**
     * Test email configuration with comprehensive debugging
     */
    public function testConfiguration($settings, $testEmail, $testType = 'connection', $configType = 'developer')
    {
        Log::debug('=== DEVELOPER EMAIL SERVICE TEST START ===');
        Log::debug('Test type: ' . $testType);
        Log::debug('Config type: ' . $configType);
        Log::debug('Test email: ' . $testEmail);
        
        try {
            // Determine which configuration to use
            $config = $this->getEmailConfig($configType);
            
            Log::debug('Using configuration:', [
                'type' => $configType,
                'username' => $config['username'] ?? 'not_set',
                'host' => $config['host'] ?? 'not_set',
                'port' => $config['port'] ?? 'not_set',
                'encryption' => $config['encryption'] ?? 'not_set'
            ]);
            
            // Validate configuration first
            $validationResult = $this->validateSmtpConfig($config);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => 'Configuration invalid: ' . $validationResult['message'],
                    'timestamp' => now()->toISOString(),
                    'type' => $configType
                ];
            }
            
            switch ($testType) {
                case 'connection':
                    return $this->testConnection($config, $configType);
                case 'send':
                    return $this->testSendEmail($config, $testEmail, $configType);
                case 'template':
                    return $this->testTemplateEmail($config, $testEmail, $configType);
                case 'full':
                    return $this->runFullTest($config, $testEmail, $configType);
                default:
                    return [
                        'success' => false,
                        'message' => 'Invalid test type'
                    ];
            }
        } catch (\Exception $e) {
            Log::error('Email configuration test failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'test_type' => $testType,
                'config_type' => $configType
            ]);
            return [
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'type' => $configType
            ];
        }
    }

    /**
     * UPDATED: Get email configuration with proper decryption
     */
    private function getEmailConfig($configType = 'developer')
    {
        Log::debug('Getting email config for type: ' . $configType);
        
        if ($configType === 'developer') {
            // Use ConfigurationService to get decrypted password
            $configService = new DeveloperEmailConfigurationService();
            $decryptedPassword = $configService->getDecryptedPassword();
            
            // Validate password format
            $passwordValidation = $configService->validateEnvPassword();
            
            if (!$passwordValidation['valid']) {
                Log::warning('Password validation failed: ' . ($passwordValidation['message'] ?? 'Unknown error'));
            }
            
            // Get Gmail help if applicable
            $gmailHelp = $configService->getGmailHelp();
            if ($gmailHelp) {
                Log::debug('Gmail configuration detected', $gmailHelp);
            }
            
            // Ensure encryption is properly set
            $encryption = env('DEVELOPER_MAIL_ENCRYPTION', 'tls');
            if (!in_array($encryption, ['tls', 'ssl', 'none'])) {
                $encryption = 'tls'; // Default to TLS
            }
            
            return [
                'transport' => 'smtp',
                'host' => env('DEVELOPER_MAIL_HOST', 'smtp.gmail.com'),
                'port' => (int) env('DEVELOPER_MAIL_PORT', 587),
                'encryption' => $encryption,
                'username' => env('DEVELOPER_MAIL_USERNAME', ''),
                'password' => $decryptedPassword,
                'timeout' => (int) env('DEVELOPER_MAIL_TIMEOUT', 30),
                'from_address' => env('DEVELOPER_MAIL_FROM_ADDRESS', ''),
                'from_name' => env('DEVELOPER_MAIL_FROM_NAME', 'Developer System'),
                'verify_peer' => env('DEVELOPER_MAIL_VERIFY_PEER', true),
                'verify_peer_name' => env('DEVELOPER_MAIL_VERIFY_PEER_NAME', true),
                'auth_mode' => env('DEVELOPER_MAIL_AUTH_MODE', null),
                'local_domain' => env('DEVELOPER_MAIL_LOCAL_DOMAIN', null),
                'password_status' => $passwordValidation,
                'gmail_help' => $gmailHelp,
                'config_type' => 'developer'
            ];
        } else {
            // Use main system configuration
            $encryption = env('MAIL_ENCRYPTION', 'tls');
            if (!in_array($encryption, ['tls', 'ssl', 'none'])) {
                $encryption = 'tls';
            }
            
            return [
                'transport' => 'smtp',
                'host' => env('MAIL_HOST', 'smtp.gmail.com'),
                'port' => (int) env('MAIL_PORT', 587),
                'encryption' => $encryption,
                'username' => env('MAIL_USERNAME', ''),
                'password' => env('MAIL_PASSWORD', ''),
                'timeout' => (int) env('MAIL_TIMEOUT', 30),
                'from_address' => env('MAIL_FROM_ADDRESS', ''),
                'from_name' => env('MAIL_FROM_NAME', ''),
                'config_type' => 'system'
            ];
        }
    }

    /**
     * Validate SMTP configuration comprehensively
     */
    private function validateSmtpConfig(array $config)
    {
        try {
            Log::debug('Validating SMTP configuration', [
                'config_type' => $config['config_type'] ?? 'unknown',
                'has_password' => !empty($config['password']),
                'password_length' => strlen($config['password'] ?? '')
            ]);
            
            // Basic validation
            $requiredFields = ['host', 'port', 'username', 'password'];
            foreach ($requiredFields as $field) {
                if (empty($config[$field])) {
                    $error = "Required field '{$field}' is empty";
                    
                    // Provide helpful message for password
                    if ($field === 'password') {
                        $error .= ". Please check password encryption/format.";
                        
                        // Add Gmail-specific help
                        if (isset($config['gmail_help']) && $config['gmail_help']) {
                            $error .= " Gmail requires 16-character App Password.";
                        }
                    }
                    
                    Log::error($error);
                    return ['valid' => false, 'message' => $error];
                }
            }
            
            // Port validation
            if (!is_numeric($config['port']) || $config['port'] < 1 || $config['port'] > 65535) {
                $error = "Invalid port number: {$config['port']}";
                Log::error($error);
                return ['valid' => false, 'message' => $error];
            }
            
            // Email validation for username
            if (!filter_var($config['username'], FILTER_VALIDATE_EMAIL)) {
                $error = "Username must be a valid email address";
                Log::error($error);
                return ['valid' => false, 'message' => $error];
            }
            
            // Encryption validation
            $validEncryption = ['tls', 'ssl', 'null', 'none', ''];
            if (!in_array($config['encryption'], $validEncryption)) {
                $error = "Invalid encryption: {$config['encryption']}";
                Log::error($error);
                return ['valid' => false, 'message' => $error];
            }
            
            // Check for Gmail-specific issues
            if (str_contains(strtolower($config['host']), 'gmail.com')) {
                $passwordLength = strlen(str_replace(' ', '', $config['password']));
                if ($passwordLength !== 16) {
                    Log::warning('Gmail password length may be incorrect', [
                        'length' => $passwordLength,
                        'expected' => 16
                    ]);
                }
            }
            
            Log::debug('SMTP configuration validation passed');
            return [
                'valid' => true, 
                'message' => 'Configuration is valid',
                'password_length' => strlen($config['password'])
            ];
            
        } catch (\Exception $e) {
            Log::error('Validation error: ' . $e->getMessage());
            return ['valid' => false, 'message' => 'Validation error: ' . $e->getMessage()];
        }
    }

    /**
     * Test SMTP connection with multiple fallback methods
     */
    private function testConnection(array $config, $configType = 'developer')
    {
        $startTime = microtime(true);
        
        try {
            Log::debug('Testing SMTP connection for ' . $configType, [
                'host' => $config['host'],
                'port' => $config['port'],
                'username' => $config['username'],
                'has_password' => !empty($config['password']),
                'password_length' => strlen($config['password'] ?? '')
            ]);
            
            // Method 1: Test socket connection first
            $this->testSocketConnection($config['host'], $config['port'], $config['timeout'] ?? 10);
            
            // Method 2: Test SMTP with Symfony Transport
            $transport = $this->createMailTransport($config);
            
            // Method 3: Verify authentication by starting transport
            $transport->start();
            
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::debug('SMTP connection test passed', [
                'time_ms' => $connectionTime,
                'config_type' => $configType,
                'host' => $config['host'],
                'username' => $config['username']
            ]);
            
            return [
                'success' => true,
                'message' => ucfirst($configType) . ' SMTP connection and authentication successful',
                'connection_time_ms' => $connectionTime,
                'timestamp' => now()->toISOString(),
                'config' => [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'encryption' => $config['encryption'],
                    'username' => $config['username'],
                    'type' => $configType,
                    'authentication' => 'verified'
                ]
            ];
            
        } catch (\Exception $e) {
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Analyze the error for better messages
            $errorMessage = $e->getMessage();
            $userMessage = $this->analyzeSmtpError($errorMessage, $config);
            
            Log::error('SMTP connection test failed: ' . $errorMessage, [
                'config' => [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'username' => $config['username']
                ],
                'config_type' => $configType
            ]);
            
            return [
                'success' => false,
                'message' => $userMessage,
                'connection_time_ms' => $connectionTime,
                'timestamp' => now()->toISOString(),
                'type' => $configType,
                'error_details' => $errorMessage
            ];
        }
    }

    /**
     * NEW: Analyze SMTP error for better user messages
     */
    private function analyzeSmtpError($errorMessage, $config)
    {
        $lowerError = strtolower($errorMessage);
        
        // Authentication errors
        if (str_contains($lowerError, 'authentication failed') || 
            str_contains($lowerError, 'invalid login') ||
            str_contains($lowerError, '535') || 
            str_contains($lowerError, 'auth')) {
            
            $host = strtolower($config['host'] ?? '');
            
            if (str_contains($host, 'gmail.com')) {
                return "Gmail authentication failed. Make sure:\n" .
                       "1. 2-Step Verification is enabled on your Google account\n" .
                       "2. You're using a 16-character App Password (not your regular password)\n" .
                       "3. App Password is generated for 'Mail' application";
            }
            
            return "Authentication failed. Please check your username and password.";
        }
        
        // Connection errors
        if (str_contains($lowerError, 'connection refused') ||
            str_contains($lowerError, 'could not connect') ||
            str_contains($lowerError, 'connection timed out')) {
            
            return "Cannot connect to SMTP server. Check:\n" .
                   "1. SMTP host and port are correct\n" .
                   "2. Firewall allows outgoing connections on port " . ($config['port'] ?? 'unknown') . "\n" .
                   "3. Server is accessible from your location";
        }
        
        // TLS/SSL errors
        if (str_contains($lowerError, 'tls') || 
            str_contains($lowerError, 'ssl') ||
            str_contains($lowerError, 'encryption')) {
            
            $port = $config['port'] ?? 587;
            $encryption = $config['encryption'] ?? 'tls';
            
            if ($port == 587 && $encryption != 'tls') {
                return "Port 587 usually requires TLS encryption. Please use encryption: 'tls'";
            }
            if ($port == 465 && $encryption != 'ssl') {
                return "Port 465 usually requires SSL encryption. Please use encryption: 'ssl'";
            }
            
            return "Encryption error. Try changing encryption from '{$encryption}' to " . 
                   ($encryption == 'tls' ? "'ssl'" : "'tls'");
        }
        
        // Password format errors
        if (str_contains($lowerError, 'password') && 
            str_contains(strtolower($config['host'] ?? ''), 'gmail.com')) {
            return "Gmail password format issue. Use 16-character App Password from Google.";
        }
        
        // Default error
        return "Connection failed: " . $errorMessage;
    }

    /**
     * Test socket connection as fallback
     */
    private function testSocketConnection($host, $port, $timeout = 10)
    {
        Log::debug('Testing socket connection', ['host' => $host, 'port' => $port]);
        
        // Use error suppression and check connection
        $socket = @fsockopen(
            $host, 
            $port, 
            $errno, 
            $errstr, 
            $timeout
        );
        
        if (!$socket) {
            throw new \Exception("Cannot connect to {$host}:{$port} - {$errstr} (Code: {$errno})");
        }
        
        fclose($socket);
        Log::debug('Socket connection successful');
    }

    /**
     * Create and test mail transport with proper timeout handling
     */
    private function createMailTransport(array $config)
    {
        Log::debug('Creating mail transport', [
            'host' => $config['host'],
            'port' => $config['port'],
            'username' => $config['username'],
            'encryption' => $config['encryption'] ?? 'tls',
            'config_type' => $config['config_type'] ?? 'unknown'
        ]);
        
        try {
            // Handle encryption for DSN
            $scheme = 'smtp';
            if ($config['encryption'] === 'ssl' || $config['port'] == 465) {
                $scheme = 'smtps';
            }
            
            // Create DSN for Symfony Mailer
            $dsn = new Dsn(
                $scheme,
                $config['host'],
                $config['username'],
                $config['password'],
                $config['port']
            );
            
            // Create transport factory
            $factory = new EsmtpTransportFactory();
            $transport = $factory->create($dsn);
            
            // Set timeout
            $timeout = $config['timeout'] ?? 30;
            if (method_exists($transport, 'setStreamTimeout')) {
                $transport->setStreamTimeout($timeout);
                Log::debug('Set stream timeout: ' . $timeout . ' seconds');
            } elseif (method_exists($transport, 'setTimeout')) {
                $transport->setTimeout($timeout);
                Log::debug('Set timeout: ' . $timeout . ' seconds');
            }
            
            // Set local domain if specified
            if (!empty($config['local_domain'])) {
                if (method_exists($transport, 'setLocalDomain')) {
                    $transport->setLocalDomain($config['local_domain']);
                    Log::debug('Set local domain: ' . $config['local_domain']);
                }
            }
            
            // Set peer verification
            if (isset($config['verify_peer']) && !$config['verify_peer']) {
                if (method_exists($transport, 'getStream')) {
                    $stream = $transport->getStream();
                    if ($stream && method_exists($stream, 'setVerifyPeer')) {
                        $stream->setVerifyPeer(false);
                        Log::debug('Disabled peer verification');
                    }
                }
            }
            
            Log::debug('Mail transport created successfully');
            return $transport;
            
        } catch (\Exception $e) {
            Log::error('Failed to create mail transport: ' . $e->getMessage(), [
                'config' => [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'username' => $config['username']
                ],
                'error_details' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Test sending an email
     */
    private function testSendEmail(array $config, $testEmail, $configType = 'developer')
    {
        $startTime = microtime(true);
        
        try {
            Log::debug('Testing email send for ' . $configType, ['to' => $testEmail]);
            
            // Create transport
            $transport = $this->createMailTransport($config);
            $mailer = new SymfonyMailer($transport);
            
            // Create test email
            $email = (new Email())
                ->from($config['from_address'])
                ->to($testEmail)
                ->subject('Test Email - ' . ucfirst($configType) . ' Configuration')
                ->text('This is a test email from the ' . ucfirst($configType) . ' Settings. If you receive this, your email configuration is working correctly.')
                ->html('<p>This is a test email from the <strong>' . ucfirst($configType) . ' Settings</strong>.</p><p>If you receive this, your email configuration is working correctly.</p>');
            
            // Send email
            $mailer->send($email);
            
            $sendTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::debug('Test email sent successfully', [
                'time_ms' => $sendTime,
                'to' => $testEmail,
                'from' => $config['from_address'],
                'config_type' => $configType
            ]);
            
            return [
                'success' => true,
                'message' => ucfirst($configType) . ' test email sent successfully to ' . $testEmail,
                'send_time_ms' => $sendTime,
                'timestamp' => now()->toISOString(),
                'type' => $configType,
                'details' => [
                    'from' => $config['from_address'],
                    'to' => $testEmail,
                    'subject' => 'Test Email - ' . ucfirst($configType) . ' Configuration'
                ]
            ];
            
        } catch (\Exception $e) {
            $sendTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Analyze the error
            $errorMessage = $e->getMessage();
            $userMessage = $this->analyzeSmtpError($errorMessage, $config);
            
            Log::error('Failed to send test email: ' . $errorMessage, [
                'to' => $testEmail,
                'from' => $config['from_address'] ?? 'not_set',
                'config_type' => $configType
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send test email: ' . $userMessage,
                'send_time_ms' => $sendTime,
                'timestamp' => now()->toISOString(),
                'type' => $configType,
                'error_details' => $errorMessage
            ];
        }
    }

    /**
     * Test sending a template email
     */
    private function testTemplateEmail(array $config, $testEmail, $configType = 'developer')
    {
        $startTime = microtime(true);
        
        try {
            Log::debug('Testing template email for ' . $configType, ['to' => $testEmail]);
            
            // Create transport
            $transport = $this->createMailTransport($config);
            $mailer = new SymfonyMailer($transport);
            
            // Generate HTML content
            $htmlContent = $this->generateTestTemplate($testEmail, $config, $configType);
            
            // Create email
            $email = (new Email())
                ->from($config['from_address'])
                ->to($testEmail)
                ->subject('Template Test - ' . ucfirst($configType) . ' Email Configuration')
                ->html($htmlContent);
            
            // Send email
            $mailer->send($email);
            
            $sendTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::debug('Template email sent successfully', [
                'time_ms' => $sendTime,
                'to' => $testEmail,
                'config_type' => $configType
            ]);
            
            return [
                'success' => true,
                'message' => ucfirst($configType) . ' template test email sent successfully to ' . $testEmail,
                'send_time_ms' => $sendTime,
                'timestamp' => now()->toISOString(),
                'type' => $configType
            ];
            
        } catch (\Exception $e) {
            $sendTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('Failed to send template email: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send template email: ' . $e->getMessage(),
                'send_time_ms' => $sendTime,
                'timestamp' => now()->toISOString(),
                'type' => $configType
            ];
        }
    }

    /**
     * Run full email test suite
     */
    private function runFullTest(array $config, $testEmail, $configType = 'developer')
    {
        $results = [];
        $overallSuccess = true;
        $messages = [];
        
        // Test 1: Connection
        Log::debug('Starting full test - Connection');
        $connectionResult = $this->testConnection($config, $configType);
        $results['connection'] = $connectionResult;
        $overallSuccess = $overallSuccess && $connectionResult['success'];
        $messages[] = $connectionResult['message'];
        
        if ($connectionResult['success']) {
            // Test 2: Send plain email
            Log::debug('Starting full test - Send');
            $sendResult = $this->testSendEmail($config, $testEmail, $configType);
            $results['send'] = $sendResult;
            $overallSuccess = $overallSuccess && $sendResult['success'];
            $messages[] = $sendResult['message'];
            
            // Test 3: Send template email
            Log::debug('Starting full test - Template');
            $templateResult = $this->testTemplateEmail($config, $testEmail, $configType);
            $results['template'] = $templateResult;
            $overallSuccess = $overallSuccess && $templateResult['success'];
            $messages[] = $templateResult['message'];
        }
        
        return [
            'success' => $overallSuccess,
            'message' => $overallSuccess ? 'All tests passed' : 'Some tests failed',
            'timestamp' => now()->toISOString(),
            'type' => $configType,
            'results' => $results,
            'summary' => $messages,
            'config_type' => $configType
        ];
    }

    /**
     * Generate test template HTML
     */
    private function generateTestTemplate($testEmail, $config, $configType)
    {
        $passwordStatus = $config['password_status'] ?? [];
        $passwordInfo = isset($passwordStatus['valid']) ? 
            ($passwordStatus['valid'] ? '✓ Valid format' : '✗ Invalid: ' . ($passwordStatus['message'] ?? 'Unknown')) : 
            'Unknown';
        
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Test Email Template</title>
                <style>
                    body { 
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                        line-height: 1.6; 
                        color: #333; 
                        margin: 0; 
                        padding: 0; 
                        background-color: #f4f4f4;
                    }
                    .container { 
                        max-width: 600px; 
                        margin: 20px auto; 
                        background: white; 
                        border-radius: 10px; 
                        overflow: hidden; 
                        box-shadow: 0 0 20px rgba(0,0,0,0.1);
                    }
                    .header { 
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                        color: white; 
                        padding: 30px 20px; 
                        text-align: center; 
                    }
                    .header h1 { 
                        margin: 0; 
                        font-size: 24px; 
                        font-weight: 300;
                    }
                    .content { 
                        padding: 40px; 
                    }
                    .success-badge { 
                        display: inline-block; 
                        background: #4CAF50; 
                        color: white; 
                        padding: 10px 20px; 
                        border-radius: 30px; 
                        font-weight: bold; 
                        margin-bottom: 20px;
                    }
                    .test-details { 
                        background: #f9f9f9; 
                        padding: 20px; 
                        border-radius: 5px; 
                        margin: 20px 0; 
                        border-left: 4px solid #4a90e2;
                    }
                    .detail-item { 
                        margin-bottom: 10px; 
                        display: flex;
                    }
                    .detail-label { 
                        font-weight: bold; 
                        min-width: 150px; 
                        color: #555;
                    }
                    .detail-value { 
                        flex: 1;
                    }
                    .footer { 
                        background: #333; 
                        color: #aaa; 
                        padding: 20px; 
                        text-align: center; 
                        font-size: 12px;
                    }
                    .timestamp { 
                        color: #666; 
                        font-size: 11px; 
                        margin-top: 20px; 
                        text-align: center;
                    }
                    .warning { 
                        background: #fff3cd; 
                        border: 1px solid #ffeaa7; 
                        padding: 10px; 
                        border-radius: 5px; 
                        margin: 10px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>" . ucfirst($configType) . " Email Configuration Test</h1>
                        <p>System Verification</p>
                    </div>
                    
                    <div class='content'>
                        <div class='success-badge'>✓ Configuration Verified</div>
                        
                        <p>Your " . $configType . " email configuration has been successfully tested and verified.</p>
                        
                        <div class='test-details'>
                            <h3>Test Configuration Details:</h3>
                            <div class='detail-item'>
                                <span class='detail-label'>Test Type:</span>
                                <span class='detail-value'>Template Email</span>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>Configuration:</span>
                                <span class='detail-value'>" . ucfirst($configType) . " Settings</span>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>Recipient:</span>
                                <span class='detail-value'>{$testEmail}</span>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>Sender:</span>
                                <span class='detail-value'>" . htmlspecialchars($config['from_name']) . " &lt;" . htmlspecialchars($config['from_address']) . "&gt;</span>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>SMTP Server:</span>
                                <span class='detail-value'>" . htmlspecialchars($config['host']) . ":" . htmlspecialchars($config['port']) . "</span>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>Encryption:</span>
                                <span class='detail-value'>" . strtoupper($config['encryption']) . "</span>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>Password Status:</span>
                                <span class='detail-value'>{$passwordInfo}</span>
                            </div>
                        </div>
                        
                        " . ($config['gmail_help'] ? "
                        <div class='warning'>
                            <h4>Gmail Configuration Notes:</h4>
                            <p>Your configuration uses Gmail SMTP. Make sure:</p>
                            <ul>
                                <li>2-Step Verification is enabled in Google Account</li>
                                <li>You're using a 16-character App Password</li>
                                <li>App Password is generated for 'Mail' application</li>
                            </ul>
                        </div>
                        " : "") . "
                        
                        <p>This HTML email confirms that your SMTP settings are properly configured for sending rich content emails.</p>
                        <p>If you can read this message, your " . $configType . " email configuration is working correctly.</p>
                    </div>
                    
                    <div class='timestamp'>
                        Test performed on " . now()->format('F j, Y \a\t g:i A') . "
                    </div>
                    
                    <div class='footer'>
                        <p>Developer Settings System | Automated Test Email</p>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }

    /**
     * Check email configuration status - UPDATED with better diagnostics
     */
    public function checkConfigurationStatus($configType = 'developer')
    {
        try {
            Log::debug('Checking email configuration status for: ' . $configType);
            
            // Get configuration
            $config = $this->getEmailConfig($configType);
            
            // Check if configuration is set
            if (empty($config['host']) || empty($config['username'])) {
                return [
                    'configured' => false,
                    'connection' => false,
                    'authentication' => false,
                    'can_send' => false,
                    'message' => ucfirst($configType) . ' email configuration not set up',
                    'timestamp' => now()->toISOString(),
                    'type' => $configType,
                    'diagnostics' => [
                        'missing_host' => empty($config['host']),
                        'missing_username' => empty($config['username']),
                        'has_password' => !empty($config['password']),
                        'password_length' => strlen($config['password'] ?? '')
                    ]
                ];
            }
            
            // Validate configuration
            $validation = $this->validateSmtpConfig($config);
            if (!$validation['valid']) {
                return [
                    'configured' => true,
                    'connection' => false,
                    'authentication' => false,
                    'can_send' => false,
                    'message' => 'Configuration invalid: ' . $validation['message'],
                    'timestamp' => now()->toISOString(),
                    'type' => $configType,
                    'validation_details' => $validation
                ];
            }
            
            // Test connection
            $connectionTest = $this->testConnection($config, $configType);
            
            if (!$connectionTest['success']) {
                return [
                    'configured' => true,
                    'connection' => false,
                    'authentication' => false,
                    'can_send' => false,
                    'message' => $connectionTest['message'],
                    'timestamp' => now()->toISOString(),
                    'type' => $configType,
                    'connection_details' => $connectionTest
                ];
            }
            
            // Try to send a test email
            $testEmail = $config['username'];
            $sendTest = $this->testSendEmail($config, $testEmail, $configType);
            
            return [
                'configured' => true,
                'connection' => true,
                'authentication' => true,
                'can_send' => $sendTest['success'],
                'message' => $sendTest['success'] 
                    ? ucfirst($configType) . ' email configuration is fully operational' 
                    : ucfirst($configType) . ' connection works but cannot send emails',
                'last_test' => now()->toISOString(),
                'connection_time' => $connectionTest['connection_time_ms'] ?? null,
                'send_time' => $sendTest['send_time_ms'] ?? null,
                'type' => $configType,
                'config_summary' => [
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'encryption' => $config['encryption'],
                    'from_address' => $config['from_address'],
                    'from_name' => $config['from_name'],
                    'username' => $config['username'],
                    'password_status' => $config['password_status'] ?? []
                ],
                'test_results' => [
                    'connection' => $connectionTest,
                    'send' => $sendTest
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to check email configuration status: ' . $e->getMessage());
            return [
                'configured' => false,
                'connection' => false,
                'authentication' => false,
                'can_send' => false,
                'message' => 'Error checking configuration: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'type' => $configType
            ];
        }
    }

    /**
     * NEW: Comprehensive configuration test with detailed results
     */
    public function runComprehensiveTest($configType = 'developer')
    {
        try {
            Log::info('Starting comprehensive email test for: ' . $configType);
            
            $results = [];
            
            // 1. Get configuration
            $config = $this->getEmailConfig($configType);
            $results['configuration'] = $config;
            
            // 2. Validate configuration
            $validation = $this->validateSmtpConfig($config);
            $results['validation'] = $validation;
            
            // 3. Test connection if validation passes
            if ($validation['valid']) {
                $connectionTest = $this->testConnection($config, $configType);
                $results['connection_test'] = $connectionTest;
                
                // 4. Test sending if connection works
                if ($connectionTest['success']) {
                    $sendTest = $this->testSendEmail($config, $config['username'], $configType);
                    $results['send_test'] = $sendTest;
                    
                    // 5. Test template email
                    $templateTest = $this->testTemplateEmail($config, $config['username'], $configType);
                    $results['template_test'] = $templateTest;
                }
            }
            
            // 6. Run diagnostics
            $results['diagnostics'] = $this->getDetailedDiagnostics($configType);
            
            // 7. Calculate overall status
            $overallSuccess = true;
            if (!$validation['valid']) $overallSuccess = false;
            if (isset($results['connection_test']) && !$results['connection_test']['success']) $overallSuccess = false;
            if (isset($results['send_test']) && !$results['send_test']['success']) $overallSuccess = false;
            
            Log::info('Comprehensive test completed', [
                'success' => $overallSuccess,
                'config_type' => $configType,
                'timestamp' => now()->toISOString()
            ]);
            
            return [
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 
                    ucfirst($configType) . ' email configuration is fully operational' : 
                    ucfirst($configType) . ' email configuration has issues',
                'timestamp' => now()->toISOString(),
                'config_type' => $configType,
                'results' => $results,
                'recommendations' => $this->generateRecommendations($results)
            ];
            
        } catch (\Exception $e) {
            Log::error('Comprehensive test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Comprehensive test failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'config_type' => $configType
            ];
        }
    }

    /**
     * NEW: Generate recommendations based on test results
     */
    private function generateRecommendations(array $results)
    {
        $recommendations = [];
        
        // Check validation issues
        if (isset($results['validation']) && !$results['validation']['valid']) {
            $recommendations[] = "Fix validation errors: " . ($results['validation']['message'] ?? 'Unknown');
        }
        
        // Check connection issues
        if (isset($results['connection_test']) && !$results['connection_test']['success']) {
            $error = $results['connection_test']['error_details'] ?? '';
            
            if (str_contains(strtolower($error), 'authentication')) {
                $recommendations[] = "Authentication failed. Check username and password.";
                
                // Gmail-specific recommendations
                $config = $results['configuration'] ?? [];
                if (str_contains(strtolower($config['host'] ?? ''), 'gmail.com')) {
                    $recommendations[] = "For Gmail: Use 16-character App Password (not regular password)";
                    $recommendations[] = "Enable 2-Step Verification in Google Account first";
                }
            }
            
            if (str_contains(strtolower($error), 'connection refused')) {
                $recommendations[] = "Cannot connect to SMTP server. Check host and port.";
                $recommendations[] = "Verify firewall allows outgoing connections";
            }
        }
        
        // Check sending issues
        if (isset($results['send_test']) && !$results['send_test']['success']) {
            $recommendations[] = "Email sending failed. Check from address and permissions.";
        }
        
        // Check password issues
        $config = $results['configuration'] ?? [];
        $passwordStatus = $config['password_status'] ?? [];
        if (isset($passwordStatus['valid']) && !$passwordStatus['valid']) {
            $recommendations[] = "Password issue: " . ($passwordStatus['message'] ?? 'Unknown');
        }
        
        // Add general recommendations
        if (empty($recommendations)) {
            $recommendations[] = "Configuration appears to be working correctly.";
            $recommendations[] = "Test with different email addresses to ensure reliability.";
        }
        
        return $recommendations;
    }

    /**
     * Get detailed email diagnostics
     */
    public function getDetailedDiagnostics($configType = 'developer')
    {
        $config = $this->getEmailConfig($configType);
        
        return [
            'system' => [
                'php_version' => phpversion(),
                'laravel_version' => app()->version(),
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'timezone' => config('app.timezone'),
                'environment' => app()->environment(),
            ],
            'php_mail' => [
                'mail_function' => function_exists('mail'),
                'smtp' => ini_get('SMTP'),
                'smtp_port' => ini_get('smtp_port'),
                'sendmail_path' => ini_get('sendmail_path'),
                'openssl_loaded' => extension_loaded('openssl'),
                'curl_loaded' => extension_loaded('curl'),
            ],
            'configuration' => [
                'type' => $configType,
                'host' => $config['host'] ?? 'not_set',
                'port' => $config['port'] ?? 'not_set',
                'encryption' => $config['encryption'] ?? 'not_set',
                'username' => $config['username'] ?? 'not_set',
                'has_password' => !empty($config['password']),
                'password_length' => strlen($config['password'] ?? ''),
                'from_address' => $config['from_address'] ?? 'not_set',
                'from_name' => $config['from_name'] ?? 'not_set',
                'password_status' => $config['password_status'] ?? []
            ],
            'env_status' => $this->checkEnvFileStatus(),
            'permissions' => $this->checkFilePermissions(),
            'gmail_detected' => str_contains(strtolower($config['host'] ?? ''), 'gmail.com'),
            'gmail_help' => $config['gmail_help'] ?? null,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Check .env file status
     */
    private function checkEnvFileStatus()
    {
        $envPath = base_path('.env');
        
        return [
            'exists' => file_exists($envPath),
            'readable' => is_readable($envPath),
            'writable' => is_writable($envPath),
            'size' => file_exists($envPath) ? filesize($envPath) : 0,
            'last_modified' => file_exists($envPath) ? date('Y-m-d H:i:s', filemtime($envPath)) : null,
            'path' => $envPath,
        ];
    }

    /**
     * Check file permissions
     */
   private function checkFilePermissions()
{
    $paths = [
        base_path('.env') => 'Read/Write .env',
        storage_path('logs') => 'Write logs',
        storage_path('framework/cache') => 'Cache directory',
        storage_path('framework/views') => 'Views cache',
        storage_path('framework/sessions') => 'Sessions',
        storage_path() => 'Storage directory',
    ];
    
    $permissions = [];
    foreach ($paths as $path => $description) {
        $exists = file_exists($path);
        $permissions[$description] = [
            'exists' => $exists,
            'readable' => $exists ? is_readable($path) : false,
            'writable' => $exists ? is_writable($path) : false,
            'is_dir' => $exists ? is_dir($path) : false,
        ];
        
        // Only try to get owner/group on Unix/Linux
        if ($exists && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            try {
                if (function_exists('posix_getpwuid')) {
                    $permissions[$description]['owner'] = posix_getpwuid(fileowner($path))['name'] ?? 'unknown';
                }
                if (function_exists('posix_getgrgid')) {
                    $permissions[$description]['group'] = posix_getgrgid(filegroup($path))['name'] ?? 'unknown';
                }
                $permissions[$description]['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
            } catch (\Exception $e) {
                // Silently fail on permission checks
            }
        } else {
            $permissions[$description]['owner'] = 'windows';
            $permissions[$description]['group'] = 'windows';
            $permissions[$description]['permissions'] = 'windows';
        }
    }
    
    return $permissions;
}

    /**
     * NEW: Fix common configuration issues automatically
     */
    public function fixCommonIssues($configType = 'developer')
    {
        try {
            Log::info('Attempting to fix common email configuration issues', ['type' => $configType]);
            
            $fixesApplied = [];
            $config = $this->getEmailConfig($configType);
            
            // Fix 1: Check and fix encryption for common ports
            if ($config['port'] == 587 && $config['encryption'] != 'tls') {
                $oldEncryption = $config['encryption'];
                $config['encryption'] = 'tls';
                $fixesApplied[] = "Changed encryption from '{$oldEncryption}' to 'tls' for port 587";
                Log::debug('Fixed encryption for port 587');
            }
            
            if ($config['port'] == 465 && $config['encryption'] != 'ssl') {
                $oldEncryption = $config['encryption'];
                $config['encryption'] = 'ssl';
                $fixesApplied[] = "Changed encryption from '{$oldEncryption}' to 'ssl' for port 465";
                Log::debug('Fixed encryption for port 465');
            }
            
            // Fix 2: Check for Gmail App Password format issues
            if (str_contains(strtolower($config['host']), 'gmail.com')) {
                $password = $config['password'] ?? '';
                $cleanPassword = str_replace(' ', '', $password);
                
                if (strlen($cleanPassword) != 16) {
                    $fixesApplied[] = "Gmail password length incorrect: " . strlen($cleanPassword) . " chars (should be 16)";
                    Log::warning('Gmail password length issue detected');
                }
                
                // Check if password looks encrypted (base64)
                if (base64_encode(base64_decode($cleanPassword, true)) === $cleanPassword) {
                    $fixesApplied[] = "Password appears to be encrypted, should be plain App Password";
                    Log::warning('Password appears to be encrypted in .env');
                }
            }
            
            // Fix 3: Check timeout value
            if (empty($config['timeout']) || $config['timeout'] < 10) {
                $oldTimeout = $config['timeout'] ?? 'not_set';
                $config['timeout'] = 30;
                $fixesApplied[] = "Increased timeout from {$oldTimeout} to 30 seconds";
                Log::debug('Fixed timeout value');
            }
            
            // Save fixes if any were applied
            if (!empty($fixesApplied)) {
                // Update .env with fixes
                $this->updateConfigurationWithFixes($config, $configType);
                
                Log::info('Applied email configuration fixes', [
                    'fixes' => $fixesApplied,
                    'config_type' => $configType
                ]);
            }
            
            return [
                'success' => true,
                'message' => !empty($fixesApplied) ? 'Applied ' . count($fixesApplied) . ' fixes' : 'No fixes needed',
                'fixes_applied' => $fixesApplied,
                'timestamp' => now()->toISOString(),
                'config_type' => $configType
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to fix common issues: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fix issues: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'config_type' => $configType
            ];
        }
    }

    /**
     * NEW: Update configuration with fixes
     */
    private function updateConfigurationWithFixes(array $config, $configType)
    {
        try {
            if ($configType !== 'developer') {
                return; // Only fix developer configuration
            }
            
            $envPath = base_path('.env');
            $envContent = File::get($envPath);
            
            // Update encryption if changed
            if (isset($config['encryption'])) {
                $pattern = "/^DEVELOPER_MAIL_ENCRYPTION=.*$/m";
                $replacement = "DEVELOPER_MAIL_ENCRYPTION=\"{$config['encryption']}\"";
                
                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                } else {
                    $envContent .= "\nDEVELOPER_MAIL_ENCRYPTION=\"{$config['encryption']}\"\n";
                }
            }
            
            // Update timeout if changed
            if (isset($config['timeout'])) {
                $pattern = "/^DEVELOPER_MAIL_TIMEOUT=.*$/m";
                $replacement = "DEVELOPER_MAIL_TIMEOUT={$config['timeout']}";
                
                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, $replacement, $envContent);
                } else {
                    $envContent .= "\nDEVELOPER_MAIL_TIMEOUT={$config['timeout']}\n";
                }
            }
            
            // Write back
            File::put($envPath, $envContent);
            
            // Clear config cache
            Artisan::call('config:clear');
            Artisan::call('config:cache');
            
            Log::debug('Updated .env with configuration fixes');
            
        } catch (\Exception $e) {
            Log::error('Failed to update .env with fixes: ' . $e->getMessage());
        }
    }

    /**
     * NEW: Reset to default configuration
     */
    public function resetToDefaults($configType = 'developer')
    {
        try {
            Log::info('Resetting email configuration to defaults', ['type' => $configType]);
            
            if ($configType === 'developer') {
                // Use DeveloperEmailConfigurationService to reset
                $configService = new DeveloperEmailConfigurationService();
                $result = $configService->resetDeveloperEmailConfig();
                
                return [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'timestamp' => now()->toISOString(),
                    'config_type' => $configType,
                    'details' => $result
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Reset only available for developer configuration',
                'timestamp' => now()->toISOString(),
                'config_type' => $configType
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to reset configuration: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Reset failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'config_type' => $configType
            ];
        }
    }
}