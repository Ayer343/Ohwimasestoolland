<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\EnvironmentConfigService;
use App\Services\AgentInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailConfigController extends Controller
{
    protected $environmentService;
    protected $invitationService;

    public function __construct(
        EnvironmentConfigService $environmentService,
        AgentInvitationService $invitationService
    ) {
        $this->environmentService = $environmentService;
        $this->invitationService = $invitationService;
    }

    /**
     * Get email configuration status
     */
    public function getEmailConfigurationStatus()
    {
        try {
            $settings = SystemSetting::getSettings();
            $emailStatus = $settings->getEmailConfigurationStatus();
            $envConfig = $this->environmentService->getMailConfiguration();
            
            // Check if .env configuration matches system settings
            $envMatchesSystem = ($envConfig['username'] ?? '') === $settings->system_email;
            
            return response()->json([
                'success' => true,
                'email_status' => $emailStatus,
                'mail_configuration' => [
                    'mail_driver' => config('mail.default'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'system_email' => $settings->system_email,
                    'system_name' => $settings->system_name,
                    'system_short_name' => $settings->system_short_name
                ],
                'env_configuration' => $envConfig,
                'env_matches_system' => $envMatchesSystem,
                'can_send_emails' => $settings->canSendEmails() && $envMatchesSystem
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking email configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test email sending with current configuration
     */
    public function testEmailSending(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'test_email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = SystemSetting::getSettings();
            
            // Check if system can send emails
            $emailCheck = $settings->canSendEmails();
            if (!$emailCheck['can_send']) {
                return response()->json([
                    'success' => false,
                    'message' => 'System email not properly configured: ' . implode(', ', $emailCheck['issues'])
                ], 400);
            }

            // Use the invitation service to test email
            $result = $this->invitationService->testEmailTemplate($request->test_email);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test email sent successfully using system email: ' . $settings->system_email,
                    'test_data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test email: ' . $result['message']
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update email configuration with .env synchronization
     */
    public function updateEmailConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'system_email' => 'required|email|max:255',
            'system_name' => 'required|string|max:255',
            'system_short_name' => 'sometimes|string|max:50|alpha_dash|unique:system_settings,system_short_name,' . SystemSetting::getSettings()->id,
            'system_phone' => 'required|string|max:20',
            'system_address' => 'nullable|string|max:500',
            'mail_host' => 'sometimes|string|max:255',
            'mail_port' => 'sometimes|integer',
            'mail_username' => 'sometimes|email',
            'mail_password' => 'sometimes|string',
            'mail_encryption' => 'sometimes|string|in:tls,ssl'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = SystemSetting::getSettings();
            $data = $request->all();

            // Update system settings
            $settings->update([
                'system_email' => $data['system_email'],
                'system_name' => $data['system_name'],
                'system_short_name' => $data['system_short_name'] ?? $settings->system_short_name,
                'system_phone' => $data['system_phone'],
                'system_address' => $data['system_address'] ?? $settings->system_address,
                'updated_by' => auth()->id()
            ]);

            // Update .env file with complete mail configuration if provided
            $envUpdateResult = ['success' => true, 'message' => 'No .env updates requested'];
            
            if ($request->has('mail_host') || $request->has('mail_username')) {
                $envConfig = [
                    'mailer' => 'smtp',
                    'host' => $data['mail_host'] ?? 'smtp.gmail.com',
                    'port' => $data['mail_port'] ?? '587',
                    'username' => $data['mail_username'] ?? $data['system_email'],
                    'password' => $data['mail_password'] ?? '',
                    'encryption' => $data['mail_encryption'] ?? 'tls',
                    'from_address' => $data['system_email'],
                    'from_name' => $data['system_name']
                ];

                $envUpdateResult = $this->environmentService->updateMailConfiguration($envConfig);
            } else {
                // Just update the basic email configuration
                $envUpdateResult = $this->environmentService->updateEmailConfiguration($data['system_email']);
            }

            // Clear cache to ensure fresh data
            SystemSetting::clearCache();

            $response = [
                'success' => true,
                'message' => 'Email configuration updated successfully',
                'email_configuration' => $settings->getEmailConfiguration(),
                'configuration_status' => $settings->getEmailConfigurationStatus()
            ];

            if ($envUpdateResult['success']) {
                $response['env_update'] = $envUpdateResult;
                $response['message'] .= ' Environment configuration synchronized.';
            } else {
                $response['env_warning'] = $envUpdateResult['message'];
                $response['message'] .= ' System settings updated but environment configuration may need manual update.';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating email configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test .env file configuration update with password support
     */
    public function testEnvConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'test_email' => 'required|email',
            'test_password' => 'sometimes|string|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $testResult = $this->environmentService->testEmailConfiguration(
                $request->test_email, 
                $request->test_password
            );

            return response()->json($testResult);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing environment configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current .env mail configuration
     */
    public function getEnvMailConfiguration()
    {
        try {
            $envConfig = $this->environmentService->getMailConfiguration();

            return response()->json([
                'success' => true,
                'env_configuration' => $envConfig
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving environment configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update complete mail configuration in .env
     */
    public function updateEnvMailConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_host' => 'required|string|max:255',
            'mail_port' => 'required|integer',
            'mail_username' => 'required|email',
            'mail_password' => 'required|string',
            'mail_encryption' => 'required|string|in:tls,ssl'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $envConfig = [
                'host' => $request->mail_host,
                'port' => $request->mail_port,
                'username' => $request->mail_username,
                'password' => $request->mail_password,
                'encryption' => $request->mail_encryption,
                'from_address' => $request->mail_username,
                'from_name' => config('app.name', 'Property System')
            ];

            $updateResult = $this->environmentService->updateMailConfiguration($envConfig);

            return response()->json($updateResult);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating environment configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test SMTP connection with current configuration
     */
    public function testSmtpConnection()
    {
        try {
            $testResult = $this->environmentService->testSmtpConnection();

            return response()->json($testResult);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing SMTP connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Queue .env update to avoid connection reset
     */
    public function queueEmailUpdate($email, $password = null, $action = 'create')
    {
        try {
            // Format the password with quotes if it contains spaces
            $formattedPassword = $this->formatEnvValue($password);
            
            // Option 1: Use Laravel queue if available
            if (config('queue.default') !== 'sync' && class_exists('App\Jobs\UpdateEmailConfiguration')) {
                \App\Jobs\UpdateEmailConfiguration::dispatch($email, $formattedPassword);
                
                return [
                    'success' => true, 
                    'message' => 'Email configuration update queued successfully',
                    'method' => 'queue'
                ];
            }
            
            // Option 2: Use session-based delayed update
            session()->put('pending_env_update', [
                'email' => $email,
                'password' => $formattedPassword, // Store formatted password
                'timestamp' => now()->timestamp,
                'attempted' => false,
                'action' => $action
            ]);
            
            return [
                'success' => true, 
                'message' => 'Email configuration update scheduled via session',
                'method' => 'session'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to schedule email configuration update: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format environment value - wrap in quotes if contains spaces
     */
    private function formatEnvValue($value)
    {
        if (empty($value)) {
            return $value;
        }
        
        // If value contains spaces and is not already quoted, wrap in double quotes
        if (str_contains($value, ' ') && !preg_match('/^["\'].*["\']$/', $value)) {
            return '"' . $value . '"';
        }
        
        return $value;
    }

    /**
     * Check and process pending .env updates
     */
    public function checkPendingEnvUpdates()
    {
        if (!session()->has('pending_env_update') || session('pending_env_update.attempted', false)) {
            return [
                'success' => false,
                'message' => 'No pending environment updates found or update already attempted'
            ];
        }

        $pendingUpdate = session()->get('pending_env_update');
        
        try {
            // Mark as attempted to prevent infinite loops
            session()->put('pending_env_update.attempted', true);
            session()->save();

            // Execute the .env update with the pre-formatted password
            $emailUpdateResult = $this->updateEnvFile([
                'MAIL_USERNAME' => $pendingUpdate['email'],
                'MAIL_PASSWORD' => $pendingUpdate['password'], // Already formatted
                'MAIL_HOST' => 'smtp.gmail.com',
                'MAIL_PORT' => '587',
                'MAIL_ENCRYPTION' => 'tls'
            ]);

            if ($emailUpdateResult['success']) {
                session()->forget('pending_env_update');
                session()->save();
                
                $action = $pendingUpdate['action'] ?? 'create';
                Log::info('Manual retry: Environment update completed successfully', [
                    'email' => $pendingUpdate['email'],
                    'action' => $action
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Email configuration updated successfully!',
                    'action' => $action
                ];
            } else {
                // Keep in session for manual retry but mark as attempted
                Log::warning('Manual retry: Environment update failed', [
                    'email' => $pendingUpdate['email'],
                    'action' => $pendingUpdate['action'] ?? 'create',
                    'error' => $emailUpdateResult['message']
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Email configuration update failed: ' . $emailUpdateResult['message']
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Manual retry: Environment update process failed', [
                'email' => $pendingUpdate['email'],
                'action' => $pendingUpdate['action'] ?? 'create',
                'error' => $e->getMessage()
            ]);
            
            // Mark as attempted to prevent repeated failures
            session()->put('pending_env_update.attempted', true);
            session()->save();
            
            return [
                'success' => false,
                'message' => 'Update process error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Manual retry for failed .env updates
     */
    public function retryEnvUpdate()
    {
        try {
            $result = $this->checkPendingEnvUpdates();
            
            if ($result) {
                if ($result['success']) {
                    return redirect()->route('admin.system-settings.index')
                        ->with('success', 'Email configuration updated successfully!');
                } else {
                    return redirect()->route('admin.system-settings.index')
                        ->with('warning', $result['message']);
                }
            }
            
            return redirect()->route('admin.system-settings.index')
                ->with('info', 'No pending email configuration updates found.');
                
        } catch (\Exception $e) {
            return redirect()->route('admin.system-settings.index')
                ->with('error', 'Error retrying email configuration update: ' . $e->getMessage());
        }
    }

    /**
     * Robust .env file updater
     */
    private function updateEnvFile($updates)
    {
        try {
            $envPath = base_path('.env');
            
            if (!file_exists($envPath)) {
                return [
                    'success' => false,
                    'message' => '.env file not found'
                ];
            }
            
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $updatedLines = [];
            $foundKeys = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip comments and empty lines
                if (empty($line) || str_starts_with($line, '#')) {
                    $updatedLines[] = $line;
                    continue;
                }
                
                // Parse key=value pairs
                if (str_contains($line, '=')) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    
                    if (array_key_exists($key, $updates)) {
                        // Update the value
                        $updatedLines[] = "{$key}={$updates[$key]}";
                        $foundKeys[$key] = true;
                    } else {
                        // Keep original line
                        $updatedLines[] = $line;
                    }
                } else {
                    $updatedLines[] = $line;
                }
            }
            
            // Add any new keys that weren't found
            foreach ($updates as $key => $value) {
                if (!isset($foundKeys[$key])) {
                    $updatedLines[] = "{$key}={$value}";
                }
            }
            
            // Write back to file
            file_put_contents($envPath, implode(PHP_EOL, $updatedLines));
            
            return [
                'success' => true,
                'message' => 'Environment file updated successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to update .env file: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update .env file: ' . $e->getMessage()
            ];
        }
    }
}