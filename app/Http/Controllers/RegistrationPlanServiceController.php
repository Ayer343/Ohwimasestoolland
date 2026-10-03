<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RegistrationPlanServiceController extends Controller
{
    protected $smsService;
    protected $whatsappService;
    protected $emailService;

    public function __construct(
        SmsService $smsService,
        WhatsAppService $whatsappService,
        EmailService $emailService
    ) {
        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
        $this->emailService = $emailService;
    }

    /**
     * Get status of all communication services
     */
    public function getServiceStatus()
    {
        try {
            $status = [
                'sms' => $this->getSmsStatus(),
                'whatsapp' => $this->getWhatsAppStatus(),
                'email' => $this->getEmailStatus(),
                'timestamp' => now()->toDateTimeString(),
            ];

            // Determine overall status
            $status['overall_status'] = $this->calculateOverallStatus($status);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get service status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get service status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SMS service status
     */
    protected function getSmsStatus()
    {
        try {
            $status = $this->smsService->getSystemStatus();
            
            return [
                'enabled' => $status['enabled'] ?? false,
                'provider' => $status['provider'] ?? 'unknown',
                'status' => $status['status'] ?? 'inactive',
                'is_configured' => $status['is_configured'] ?? false,
                'balance' => $status['balance'] ?? null,
                'sender_id' => $status['sender_id'] ?? null,
                'message' => $status['message'] ?? 'SMS service is operational'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get SMS status: ' . $e->getMessage());
            return [
                'enabled' => false,
                'provider' => 'error',
                'status' => 'error',
                'is_configured' => false,
                'message' => 'Error fetching SMS status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get WhatsApp service status
     */
    protected function getWhatsAppStatus()
    {
        try {
            $status = $this->whatsappService->getSystemStatus();
            
            return [
                'enabled' => $status['enabled'] ?? false,
                'provider' => $status['provider'] ?? 'unknown',
                'status' => $status['status'] ?? 'inactive',
                'is_configured' => $status['is_configured'] ?? false,
                'is_production' => $status['is_production'] ?? false,
                'message' => $status['message'] ?? 'WhatsApp service is operational'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp status: ' . $e->getMessage());
            return [
                'enabled' => false,
                'provider' => 'error',
                'status' => 'error',
                'is_configured' => false,
                'is_production' => false,
                'message' => 'Error fetching WhatsApp status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Email service status
     */
    protected function getEmailStatus()
    {
        try {
            $status = $this->emailService->getSystemStatus();
            
            return [
                'enabled' => $status['enabled'] ?? false,
                'driver' => $status['driver'] ?? 'unknown',
                'status' => $status['status'] ?? 'inactive',
                'is_configured' => $status['is_configured'] ?? false,
                'message' => $status['message'] ?? 'Email service is operational'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Email status: ' . $e->getMessage());
            return [
                'enabled' => false,
                'driver' => 'error',
                'status' => 'error',
                'is_configured' => false,
                'message' => 'Error fetching Email status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate overall service status
     */
    protected function calculateOverallStatus($status)
    {
        $hasError = false;
        $hasWarning = false;
        
        foreach (['sms', 'whatsapp', 'email'] as $service) {
            if (isset($status[$service]['status'])) {
                if ($status[$service]['status'] === 'error') {
                    $hasError = true;
                } elseif ($status[$service]['status'] === 'warning' || !$status[$service]['is_configured']) {
                    $hasWarning = true;
                }
            }
        }
        
        if ($hasError) {
            return 'degraded';
        } elseif ($hasWarning) {
            return 'warning';
        } else {
            return 'operational';
        }
    }

    /**
     * Test communication channels
     */
    public function testChannels(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,whatsapp,email',
            'test_phone' => 'required_if:channels.*,sms,whatsapp|nullable|string|max:20',
            'test_email' => 'required_if:channels.*,email|nullable|email',
            'test_message' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $results = [];
        $allSuccessful = true;

        foreach ($validated['channels'] as $channel) {
            try {
                switch ($channel) {
                    case 'sms':
                        if (empty($validated['test_phone'])) {
                            $results[$channel] = [
                                'success' => false,
                                'message' => 'Phone number required for SMS test'
                            ];
                            $allSuccessful = false;
                        } else {
                            $results[$channel] = $this->testSmsChannel(
                                $validated['test_phone'], 
                                $validated['test_message']
                            );
                        }
                        break;
                        
                    case 'whatsapp':
                        if (empty($validated['test_phone'])) {
                            $results[$channel] = [
                                'success' => false,
                                'message' => 'Phone number required for WhatsApp test'
                            ];
                            $allSuccessful = false;
                        } else {
                            $results[$channel] = $this->testWhatsAppChannel(
                                $validated['test_phone'], 
                                $validated['test_message']
                            );
                        }
                        break;
                        
                    case 'email':
                        if (empty($validated['test_email'])) {
                            $results[$channel] = [
                                'success' => false,
                                'message' => 'Email address required for Email test'
                            ];
                            $allSuccessful = false;
                        } else {
                            $results[$channel] = $this->testEmailChannel(
                                $validated['test_email'], 
                                $validated['test_message']
                            );
                        }
                        break;
                        
                    default:
                        $results[$channel] = [
                            'success' => false,
                            'message' => 'Unknown channel: ' . $channel
                        ];
                        $allSuccessful = false;
                }

                if (!$results[$channel]['success']) {
                    $allSuccessful = false;
                }

            } catch (\Exception $e) {
                $results[$channel] = [
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ];
                $allSuccessful = false;
                Log::error("Channel test failed for {$channel}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => $allSuccessful,
            'results' => $results,
            'message' => $allSuccessful 
                ? 'All channels tested successfully!' 
                : 'Some channels failed the test. Check the results for details.'
        ]);
    }

    /**
     * Test SMS channel
     */
    protected function testSmsChannel($phone, $message)
    {
        try {
            // Format phone number
            $phone = $this->formatPhoneNumber($phone);
            
            Log::info('Testing SMS channel', [
                'phone' => $phone,
                'message_length' => strlen($message)
            ]);

            $result = $this->smsService->sendWithDefaultProvider($phone, $message, [
                'is_test' => true,
                'test_id' => uniqid('test_sms_'),
                'test_timestamp' => now()->toDateTimeString()
            ]);

            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'details' => [
                        'phone' => $phone,
                        'message_id' => $result['message_id'] ?? null,
                        'provider' => $result['provider'] ?? 'unknown'
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'SMS sending failed',
                    'details' => [
                        'phone' => $phone,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]
                ];
            }

        } catch (\Exception $e) {
            Log::error('SMS test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMS test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test WhatsApp channel
     */
    protected function testWhatsAppChannel($phone, $message)
    {
        try {
            // Format phone for WhatsApp
            $whatsappPhone = $this->formatWhatsAppPhone($phone);
            
            Log::info('Testing WhatsApp channel', [
                'phone' => $whatsappPhone,
                'message_length' => strlen($message)
            ]);

            $result = $this->whatsappService->sendMessage($whatsappPhone, $message, [
                'is_test' => true,
                'test_id' => uniqid('test_whatsapp_'),
                'test_timestamp' => now()->toDateTimeString()
            ]);

            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'WhatsApp message sent successfully',
                    'details' => [
                        'phone' => $whatsappPhone,
                        'message_id' => $result['message_id'] ?? null,
                        'provider' => $result['provider'] ?? 'unknown'
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'WhatsApp sending failed',
                    'details' => [
                        'phone' => $whatsappPhone,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]
                ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'WhatsApp test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test Email channel
     */
    protected function testEmailChannel($email, $message)
    {
        try {
            Log::info('Testing Email channel', [
                'email' => $email,
                'message_length' => strlen($message)
            ]);

            $result = $this->emailService->send([
                'to' => $email,
                'subject' => 'Test Email from Registration Plan System',
                'message' => $message,
                'is_test' => true,
                'test_id' => uniqid('test_email_'),
                'test_timestamp' => now()->toDateTimeString()
            ]);

            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'details' => [
                        'email' => $email,
                        'message_id' => $result['message_id'] ?? null,
                        'driver' => $result['driver'] ?? 'unknown'
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Email sending failed',
                    'details' => [
                        'email' => $email,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]
                ];
            }

        } catch (\Exception $e) {
            Log::error('Email test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Email test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format phone number for SMS
     */
    protected function formatPhoneNumber($phone)
    {
        // Remove any spaces or special characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Ensure phone starts with +
        if (strpos($phone, '+') !== 0) {
            // Assume Ghana number if not international
            if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
                $phone = '+233' . substr($phone, 1);
            } else {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }

    /**
     * Format phone number for WhatsApp
     */
    protected function formatWhatsAppPhone($phone)
    {
        // Remove any spaces or special characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Add whatsapp: prefix if not present
        if (strpos($phone, 'whatsapp:') !== 0) {
            $phone = 'whatsapp:' . $phone;
        }
        
        return $phone;
    }

    /**
     * Get detailed service status for admin dashboard
     */
    public function getDetailedStatus()
    {
        try {
            $status = [
                'services' => [
                    'sms' => $this->getSmsStatus(),
                    'whatsapp' => $this->getWhatsAppStatus(),
                    'email' => $this->getEmailStatus()
                ],
                'system' => [
                    'environment' => app()->environment(),
                    'timezone' => config('app.timezone'),
                    'current_time' => now()->toDateTimeString()
                ],
                'statistics' => [
                    'total_sms_sent' => $this->getTotalSmsSent(),
                    'total_whatsapp_sent' => $this->getTotalWhatsAppSent(),
                    'total_emails_sent' => $this->getTotalEmailsSent()
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get detailed status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get detailed status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get total SMS sent (example - implement your own logic)
     */
    protected function getTotalSmsSent()
    {
        try {
            // You can implement this based on your database
            // Example: return SmsLog::count();
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get total WhatsApp messages sent (example - implement your own logic)
     */
    protected function getTotalWhatsAppSent()
    {
        try {
            // You can implement this based on your database
            // Example: return WhatsAppLog::count();
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get total emails sent (example - implement your own logic)
     */
    protected function getTotalEmailsSent()
    {
        try {
            // You can implement this based on your database
            // Example: return EmailLog::count();
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Test a single channel (convenience method)
     */
    public function testSingleChannel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:sms,whatsapp,email',
            'phone' => 'required_if:channel,sms,whatsapp|nullable|string|max:20',
            'email' => 'required_if:channel,email|nullable|email',
            'message' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $result = null;

        switch ($validated['channel']) {
            case 'sms':
                $result = $this->testSmsChannel($validated['phone'], $validated['message']);
                break;
            case 'whatsapp':
                $result = $this->testWhatsAppChannel($validated['phone'], $validated['message']);
                break;
            case 'email':
                $result = $this->testEmailChannel($validated['email'], $validated['message']);
                break;
        }

        return response()->json([
            'success' => $result['success'] ?? false,
            'channel' => $validated['channel'],
            'result' => $result
        ]);
    }
}