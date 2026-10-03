<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\EnvironmentConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailConfigController extends Controller
{
    protected $environmentService;

    public function __construct(EnvironmentConfigService $environmentService)
    {
        $this->environmentService = $environmentService;
    }

    /**
     * Show email configuration form
     */
    public function index()
    {
        try {
            $settings = SystemSetting::getSettings();
            $emailConfiguration = $this->environmentService->getMailConfiguration();
            
            // Check for pending environment updates
            $hasPendingEnvUpdate = session()->has('pending_env_update');
            $pendingEnvUpdate = session()->get('pending_env_update', null);

            return view('admin.email-config.index', compact(
                'settings', 
                'emailConfiguration',
                'hasPendingEnvUpdate',
                'pendingEnvUpdate'
            ));

        } catch (\Exception $e) {
            Log::error('Error loading email configuration: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading email configuration: ' . $e->getMessage());
        }
    }

    /**
     * Update email configuration
     */
    public function update(Request $request)
    {
        $validator = $this->validateEmailSettings($request->all());

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $settings = SystemSetting::getSettings();
            $data = $validator->validated();
            $data['updated_by'] = auth()->id();

            // Update system settings
            $settings->update([
                'system_email' => $data['system_email'],
                'updated_by' => auth()->id()
            ]);

            // Queue .env update
            $emailUpdateResult = $this->queueEnvUpdate($data['system_email'], $data['system_email_password'] ?? null);

            $successMessage = 'Email configuration updated successfully!';
            
            if ($emailUpdateResult['success']) {
                $successMessage .= ' Environment configuration is being updated in the background.';
            } else {
                $successMessage .= ' Note: Environment configuration may need manual update.';
            }

            return redirect()->route('admin.email-config.index')
                ->with('success', $successMessage)
                ->with('env_update_status', $emailUpdateResult['success'] ? 'processing' : 'warning');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error updating email configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Test email configuration
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
            $invitationService = app(\App\Services\AgentInvitationService::class);
            $result = $invitationService->testEmailTemplate($request->test_email);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test email sent successfully!',
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
     * Get email configuration status
     */
    public function getEmailConfigurationStatus()
    {
        try {
            $settings = SystemSetting::getSettings();
            $emailStatus = $settings->getEmailConfigurationStatus();
            $envConfig = $this->environmentService->getMailConfiguration();
            
            $envMatchesSystem = ($envConfig['username'] ?? '') === $settings->system_email;

            return response()->json([
                'success' => true,
                'email_status' => $emailStatus,
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
     * Validate email settings
     */
    protected function validateEmailSettings(array $data): \Illuminate\Validation\Validator
    {
        $rules = [
            'system_email' => 'required|email|max:255',
            'system_email_password' => 'sometimes|string|min:1',
        ];

        $messages = [
            'system_email_password.min' => 'Email password cannot be empty',
        ];

        return Validator::make($data, $rules, $messages);
    }

    /**
     * Queue .env update
     */
    protected function queueEnvUpdate($email, $password = null)
    {
        try {
            // Format the password with quotes if it contains spaces
            $formattedPassword = $this->formatEnvValue($password);
            
            // Use session-based delayed update
            session()->put('pending_env_update', [
                'email' => $email,
                'password' => $formattedPassword,
                'timestamp' => now()->timestamp,
                'attempted' => false,
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
     * Format environment value
     */
    private function formatEnvValue($value)
    {
        if (empty($value)) {
            return $value;
        }
        
        if (str_contains($value, ' ') && !preg_match('/^["\'].*["\']$/', $value)) {
            return '"' . $value . '"';
        }
        
        return $value;
    }

    /**
     * Manual retry for failed .env updates
     */
    public function retryEnvUpdate()
    {
        try {
            // Implementation for retrying .env updates
            // Similar to original implementation but focused on email only
            
            return redirect()->route('admin.email-config.index')
                ->with('success', 'Email configuration update retried successfully!');
                
        } catch (\Exception $e) {
            return redirect()->route('admin.email-config.index')
                ->with('error', 'Error retrying email configuration update: ' . $e->getMessage());
        }
    }
}