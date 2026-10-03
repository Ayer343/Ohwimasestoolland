<?php

namespace App\Services;

use App\Models\AgentInvitation;
use App\Models\AgentInvitationLog;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Services\SmsTemplateService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class MultiChannelInvitationService
{
    protected $smsService;
    protected $emailService;
    protected $whatsappService;
    protected $smsTemplateService;

    protected $invitationExpiryDays;
    protected $invitationWarningDays;
    protected $invitationAutoExpiry;
    protected $invitationResendExtendsExpiry;

    public function __construct(
        SmsService $smsService,
        EmailService $emailService,
        WhatsAppService $whatsappService,
        SmsTemplateService $smsTemplateService
    ) {
        $this->smsService = $smsService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;
        $this->smsTemplateService = $smsTemplateService;
        
        $this->invitationExpiryDays = (int) config('app.invitation_expiry_days', 7);
        $this->invitationWarningDays = (int) config('app.invitation_warning_days', 2);
        $this->invitationAutoExpiry = (bool) config('app.invitation_auto_expiry', true);
        $this->invitationResendExtendsExpiry = (bool) config('app.invitation_resend_extends_expiry', true);
    }

    /**
     * ✅ FIXED: Unified message sending that handles both User and UserInvitation models
     */
    public function sendMessage($recipient, string $message, string $messageType = 'invitation', array $options = []): array
    {
        try {
            $channel = $options['channel'] ?? 'sms';
            $invitationId = $options['invitation_id'] ?? null;
            
            // ✅ FIXED: Determine if this is a user invitation or agent invitation
            if ($messageType === 'user_invitation') {
                return $this->sendUserInvitationMessage($recipient, $message, $channel, $options);
            } elseif ($messageType === 'agent_invitation') {
                return $this->sendAgentInvitationMessage($recipient, $message, $channel, $options);
            } else {
                return $this->sendGenericMessage($recipient, $message, $channel, $options);
            }

        } catch (\Exception $e) {
            Log::error('Multi-channel message failed', [
                'recipient_type' => is_object($recipient) ? get_class($recipient) : gettype($recipient),
                'channel' => $options['channel'] ?? 'unknown',
                'message_type' => $messageType,
                'invitation_id' => $options['invitation_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'error_code' => 'MULTI_CHANNEL_EXCEPTION'
            ]);

            return [
                'success' => false,
                'message' => 'Message sending failed: ' . $e->getMessage(),
                'channel' => $options['channel'] ?? 'unknown',
                'error_code' => 'SEND_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ FIXED: Send user invitation message with proper model handling
     */
    private function sendUserInvitationMessage($user, string $message, string $channel, array $options = []): array
    {
        try {
            $invitationId = $options['invitation_id'] ?? null;
            $invitationToken = $options['invitation_token'] ?? null;
            
            // ✅ FIXED: Get the UserInvitation model to avoid calling methods on User model
            $userInvitation = null;
            if ($invitationId) {
                $userInvitation = UserInvitation::find($invitationId);
            } elseif ($invitationToken) {
                $userInvitation = UserInvitation::where('token', $invitationToken)->first();
            }
            
            // If we can't find the invitation, log error but still try to send
            if (!$userInvitation) {
                Log::warning('User invitation not found for message sending', [
                    'user_id' => $user->id ?? 'unknown',
                    'invitation_id' => $invitationId,
                    'invitation_token' => $invitationToken ? substr($invitationToken, 0, 10) . '...' : null
                ]);
            }

            // ✅ FIXED: Use the invitation URL from the UserInvitation model, not User model
            $invitationUrl = $userInvitation ? $userInvitation->getInvitationUrl() : ($options['invitation_url'] ?? '#');
            
            // Prepare enhanced options with proper invitation data
            $enhancedOptions = array_merge($options, [
                'invitation_url' => $invitationUrl,
                'user_invitation' => $userInvitation,
                'message_type' => 'user_invitation'
            ]);

            switch ($channel) {
                case 'sms':
                    return $this->sendSmsMessage($user, $message, 'user_invitation', $enhancedOptions);
                    
                case 'whatsapp':
                    return $this->sendWhatsAppMessage($user, $message, 'user_invitation', $enhancedOptions);
                    
                case 'email':
                    return $this->sendEmailMessage($user, $message, 'user_invitation', $enhancedOptions);
                    
                default:
                    return [
                        'success' => false,
                        'message' => "Unsupported channel for user invitation: {$channel}",
                        'channel' => $channel,
                        'error_code' => 'UNSUPPORTED_CHANNEL'
                    ];
            }

        } catch (\Exception $e) {
            Log::error('User invitation message sending failed', [
                'user_id' => $user->id ?? 'unknown',
                'channel' => $channel,
                'invitation_id' => $options['invitation_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'User invitation sending failed: ' . $e->getMessage(),
                'channel' => $channel,
                'error_code' => 'USER_INVITATION_SEND_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ FIXED: Send agent invitation message
     */
    private function sendAgentInvitationMessage($agent, string $message, string $channel, array $options = []): array
    {
        try {
            $invitationId = $options['invitation_id'] ?? null;
            $agentInvitation = null;
            
            if ($invitationId) {
                $agentInvitation = AgentInvitation::find($invitationId);
            }

            // Prepare enhanced options
            $enhancedOptions = array_merge($options, [
                'agent_invitation' => $agentInvitation,
                'message_type' => 'agent_invitation'
            ]);

            switch ($channel) {
                case 'sms':
                    return $this->sendSmsMessage($agent, $message, 'agent_invitation', $enhancedOptions);
                    
                case 'whatsapp':
                    return $this->sendWhatsAppMessage($agent, $message, 'agent_invitation', $enhancedOptions);
                    
                case 'email':
                    return $this->sendEmailMessage($agent, $message, 'agent_invitation', $enhancedOptions);
                    
                default:
                    return [
                        'success' => false,
                        'message' => "Unsupported channel for agent invitation: {$channel}",
                        'channel' => $channel,
                        'error_code' => 'UNSUPPORTED_CHANNEL'
                    ];
            }

        } catch (\Exception $e) {
            Log::error('Agent invitation message sending failed', [
                'agent_id' => $agent->id ?? 'unknown',
                'channel' => $channel,
                'invitation_id' => $options['invitation_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Agent invitation sending failed: ' . $e->getMessage(),
                'channel' => $channel,
                'error_code' => 'AGENT_INVITATION_SEND_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ FIXED: Send generic message (fallback)
     */
    private function sendGenericMessage($recipient, string $message, string $channel, array $options = []): array
    {
        try {
            switch ($channel) {
                case 'sms':
                    return $this->sendSmsMessage($recipient, $message, 'generic', $options);
                    
                case 'whatsapp':
                    return $this->sendWhatsAppMessage($recipient, $message, 'generic', $options);
                    
                case 'email':
                    return $this->sendEmailMessage($recipient, $message, 'generic', $options);
                    
                default:
                    return [
                        'success' => false,
                        'message' => "Unsupported channel: {$channel}",
                        'channel' => $channel,
                        'error_code' => 'UNSUPPORTED_CHANNEL'
                    ];
            }

        } catch (\Exception $e) {
            Log::error('Generic message sending failed', [
                'recipient_type' => is_object($recipient) ? get_class($recipient) : gettype($recipient),
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Generic message sending failed: ' . $e->getMessage(),
                'channel' => $channel,
                'error_code' => 'GENERIC_SEND_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ FIXED: Send SMS message with proper error handling
     */
    public function sendSmsMessage($recipient, string $message, string $messageType = 'invitation', array $options = []): array
    {
        // ✅ FIXED: Extract phone number safely from different recipient types
        $phone = $this->extractPhoneNumber($recipient);
        
        if (empty($phone)) {
            return [
                'success' => false,
                'message' => 'Recipient phone number not available',
                'provider' => null,
                'error_code' => 'NO_PHONE'
            ];
        }

        try {
            // ✅ FIXED: Validate and truncate SMS message if too long
            $messageLength = strlen($message);
            if ($messageLength > 160) {
                Log::warning("SMS message truncated automatically", [
                    'original_length' => $messageLength,
                    'recipient_type' => is_object($recipient) ? get_class($recipient) : gettype($recipient),
                    'message_type' => $messageType
                ]);
                
                $message = $this->truncateMessageForSms($message);
            }

            $smsOptions = array_merge([
                'is_test' => false,
                'type' => $messageType,
                'channel' => 'sms',
            ], $options);

            // ✅ FIXED: Add recipient ID based on type
            if ($recipient instanceof User) {
                $smsOptions['user_id'] = $recipient->id;
            } elseif ($recipient instanceof UserInvitation) {
                $smsOptions['user_id'] = $recipient->user_id;
            }

            $smsResult = $this->smsService->sendWithDefaultProvider($phone, $message, $smsOptions);

            return [
                'success' => $smsResult['success'] ?? false,
                'message' => $smsResult['message'] ?? ($smsResult['success'] ? 'SMS sent successfully' : 'SMS sending failed'),
                'provider' => $smsResult['provider'] ?? null,
                'message_id' => $smsResult['message_id'] ?? null,
                'delivery_receipt' => $smsResult['delivery_receipt'] ?? null,
                'error_code' => $smsResult['error_code'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error("SMS message failed: " . $e->getMessage(), [
                'recipient_type' => is_object($recipient) ? get_class($recipient) : gettype($recipient),
                'phone' => substr($phone, 0, 3) . '...' . substr($phone, -3),
                'message_type' => $messageType,
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'SMS sending failed: ' . $e->getMessage(),
                'provider' => null,
                'error_code' => 'SMS_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ FIXED: Send WhatsApp message with proper recipient handling
     */
    public function sendWhatsAppMessage($recipient, string $message, string $messageType = 'invitation', array $options = []): array
    {
        // ✅ FIXED: Extract phone number safely
        $phone = $this->extractPhoneNumber($recipient);
        
        if (empty($phone)) {
            return [
                'success' => false,
                'message' => 'Recipient phone number not available for WhatsApp',
                'provider' => null,
                'error_code' => 'NO_PHONE'
            ];
        }

        try {
            // Check if WhatsApp service is available and configured
            if (!$this->whatsappService->isConfigured()) {
                Log::warning("WhatsApp service not configured, using SMS fallback", [
                    'recipient_type' => is_object($recipient) ? get_class($recipient) : gettype($recipient),
                    'message_type' => $messageType
                ]);
                
                // Fallback to SMS
                $smsResult = $this->sendSmsMessage($recipient, $message, $messageType, $options);
                
                if ($smsResult['success']) {
                    return [
                        'success' => true,
                        'message' => 'WhatsApp message sent via SMS fallback',
                        'provider' => $smsResult['provider'] ?? null,
                        'message_id' => $smsResult['message_id'] ?? null,
                        'fallback_used' => true,
                        'original_channel' => 'whatsapp',
                        'actual_channel' => 'sms'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'WhatsApp fallback failed: ' . $smsResult['message'],
                        'provider' => null,
                        'error_code' => 'WHATSAPP_FALLBACK_FAILED',
                        'fallback_error' => $smsResult['message']
                    ];
                }
            }

            // ✅ FIXED: Prepare WhatsApp options
            $whatsappOptions = array_merge($options, [
                'message_type' => $messageType,
            ]);

            // ✅ FIXED: Add recipient ID based on type
            if ($recipient instanceof User) {
                $whatsappOptions['user_id'] = $recipient->id;
            } elseif ($recipient instanceof UserInvitation) {
                $whatsappOptions['user_id'] = $recipient->user_id;
            }

            $whatsappResult = $this->whatsappService->sendMessage($phone, $message, $whatsappOptions);

            return [
                'success' => $whatsappResult['success'] ?? false,
                'message' => $whatsappResult['message'] ?? ($whatsappResult['success'] ? 'WhatsApp message sent successfully' : 'WhatsApp sending failed'),
                'provider' => $whatsappResult['provider'] ?? 'whatsapp',
                'message_id' => $whatsappResult['message_id'] ?? null,
                'delivery_receipt' => $whatsappResult['delivery_receipt'] ?? null,
                'error_code' => $whatsappResult['error_code'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error("WhatsApp message failed: " . $e->getMessage(), [
                'recipient_type' => is_object($recipient) ? get_class($recipient) : gettype($recipient),
                'phone' => substr($phone, 0, 3) . '...' . substr($phone, -3),
                'message_type' => $messageType,
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'WhatsApp sending failed: ' . $e->getMessage(),
                'provider' => null,
                'error_code' => 'WHATSAPP_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ FIXED: Send Email message with proper recipient handling
     */
    public function sendEmailMessage($recipient, string $message, string $messageType = 'invitation', array $options = []): array
    {
        // ✅ FIXED: Extract email address safely
        $email = $this->extractEmailAddress($recipient);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Recipient email address not available or invalid',
                'provider' => null,
                'error_code' => 'NO_EMAIL'
            ];
        }

        try {
            $systemSettings = SystemSetting::first();
            if (!$systemSettings || empty($systemSettings->system_email)) {
                return [
                    'success' => false,
                    'message' => 'System email not configured',
                    'provider' => null,
                    'error_code' => 'SYSTEM_EMAIL_NOT_CONFIGURED'
                ];
            }

            // ✅ FIXED: Prepare email data with proper model handling
            $emailData = array_merge([
                'message' => $message,
                'message_type' => $messageType,
                'system_name' => $systemSettings->system_name,
                'system_email' => $systemSettings->system_email,
            ], $options);

            // ✅ FIXED: Add recipient information based on type
            if ($recipient instanceof User) {
                $emailData['user'] = $recipient;
                $emailData['user_name'] = $recipient->name;
            } elseif ($recipient instanceof UserInvitation) {
                $emailData['user'] = $recipient->user;
                $emailData['user_name'] = $recipient->user->name ?? 'User';
                $emailData['invitation'] = $recipient;
            }

            // ✅ FIXED: Get template with fallback
            $template = $this->getEmailTemplate($messageType);
            
            // Ensure template exists, create fallback if not
            if (!$this->emailTemplateExists($template)) {
                Log::warning("Email template not found, using fallback: {$template}");
                $this->createFallbackEmailTemplate($template);
            }

            // Use EmailService for sending
            $emailResult = $this->emailService->sendEmail(
                $email,
                $this->getEmailSubject($messageType, $options),
                $template,
                $emailData
            );

            return [
                'success' => $emailResult['success'] ?? false,
                'message' => $emailResult['message'] ?? ($emailResult['success'] ? 'Email sent successfully' : 'Email sending failed'),
                'provider' => 'email',
                'message_id' => $emailResult['message_id'] ?? null,
                'delivery_receipt' => $emailResult['delivery_receipt'] ?? null,
                'error_code' => $emailResult['error_code'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error("Email message failed: " . $e->getMessage(), [
                'recipient_type' => is_object($recipient) ? get_class($recipient) : gettype($recipient),
                'email' => substr($email, 0, 3) . '...' . substr($email, strpos($email, '@')),
                'message_type' => $messageType,
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage(),
                'provider' => null,
                'error_code' => 'EMAIL_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ NEW: Extract phone number from different recipient types
     */
    private function extractPhoneNumber($recipient): ?string
    {
        if ($recipient instanceof User) {
            return $recipient->phone;
        } elseif ($recipient instanceof UserInvitation) {
            return $recipient->user->phone ?? null;
        } elseif (is_array($recipient) && isset($recipient['phone'])) {
            return $recipient['phone'];
        } elseif (is_string($recipient) && preg_match('/\+?[\d\s\-\(\)]+/', $recipient)) {
            return $recipient;
        }
        
        return null;
    }

    /**
     * ✅ NEW: Extract email address from different recipient types
     */
    private function extractEmailAddress($recipient): ?string
    {
        if ($recipient instanceof User) {
            return $recipient->email;
        } elseif ($recipient instanceof UserInvitation) {
            return $recipient->user->email ?? null;
        } elseif (is_array($recipient) && isset($recipient['email'])) {
            return $recipient['email'];
        } elseif (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $recipient;
        }
        
        return null;
    }

    /**
     * ✅ FIXED: Flexible method that accepts both User object OR contact information
     */
    public function getAvailableChannelsForUser($user, ?string $phone = null): array
    {
        try {
            $userObject = null;
            $email = null;
            $phoneNumber = $phone;

            // Handle both User object and contact info parameters
            if ($user instanceof User) {
                $userObject = $user;
                $email = $user->email;
                $phoneNumber = $user->phone;
            } else {
                $email = is_string($user) ? $user : null;
                
                if (!$phoneNumber && is_string($user) && preg_match('/\+?[\d\s\-\(\)]+/', $user)) {
                    $phoneNumber = $user;
                    $email = null;
                }
            }

            // Create a temporary user object for validation if needed
            if (!$userObject) {
                $userObject = new User();
                $userObject->email = $email;
                $userObject->phone = $phoneNumber;
            }

            $availableChannels = $this->getAvailableChannels($userObject);
            
            $channels = [];
            foreach ($availableChannels as $channel) {
                $channels[$channel] = [
                    'available' => true,
                    'validation' => $this->validateChannelForAgent($channel, $userObject),
                    'contact_info' => $this->getChannelContactInfo($channel, $userObject)
                ];
            }
            
            $allChannels = ['sms', 'whatsapp', 'email'];
            $unavailableChannels = array_diff($allChannels, $availableChannels);
            
            foreach ($unavailableChannels as $channel) {
                $validation = $this->validateChannelForAgent($channel, $userObject);
                $channels[$channel] = [
                    'available' => false,
                    'validation' => $validation,
                    'contact_info' => $this->getChannelContactInfo($channel, $userObject),
                    'reason' => $validation['message'] ?? 'Channel not available'
                ];
            }
            
            Log::debug("Available channels retrieved for user/contact", [
                'user_id' => $userObject->id ?? 'temp',
                'email' => $email,
                'phone' => $phoneNumber,
                'available_channels' => $availableChannels,
            ]);
            
            return [
                'available_channels' => $availableChannels,
                'channels_details' => $channels,
                'user_contact_info' => [
                    'has_phone' => !empty($phoneNumber),
                    'has_email' => !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL),
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error("Error getting available channels for user/contact: " . $e->getMessage());
            
            return [
                'available_channels' => [],
                'channels_details' => [],
                'user_contact_info' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ FIXED: Get available channels with service configuration checks
     */
    public function getAvailableChannels(User $user): array
    {
        $channels = [];

        // Check SMS capability
        if (!empty($user->phone) && $this->smsService->isConfigured()) {
            $channels[] = 'sms';
        }

        // Check WhatsApp capability
        if (!empty($user->phone) && $this->whatsappService->isConfigured()) {
            $channels[] = 'whatsapp';
        }

        // Check Email capability
        if (!empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL) && $this->emailService->isConfigured()) {
            $channels[] = 'email';
        }

        return array_unique($channels);
    }

    /**
     * ✅ FIXED: Validate channel for user with detailed reporting
     */
    public function validateChannelForAgent(string $channel, User $user): array
    {
        $availableChannels = $this->getAvailableChannels($user);
        $isValid = in_array($channel, $availableChannels);

        $validationDetails = [
            'valid' => $isValid,
            'channel' => $channel,
            'user_id' => $user->id,
            'available_channels' => $availableChannels,
            'contact_info' => [
                'has_phone' => !empty($user->phone),
                'has_email' => !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL),
            ]
        ];

        if (!$isValid) {
            $validationDetails['message'] = $this->getChannelValidationMessage($channel, $user);
            $validationDetails['error_code'] = $this->getChannelErrorCode($channel, $user);
        }

        return $validationDetails;
    }

    /**
     * ✅ NEW: Get channel contact information
     */
    private function getChannelContactInfo(string $channel, User $user): array
    {
        switch ($channel) {
            case 'sms':
            case 'whatsapp':
                return [
                    'type' => 'phone',
                    'value' => $user->phone,
                    'valid' => !empty($user->phone)
                ];
            
            case 'email':
                return [
                    'type' => 'email',
                    'value' => $user->email,
                    'valid' => !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)
                ];
            
            default:
                return [
                    'type' => 'unknown',
                    'value' => null,
                    'valid' => false
                ];
        }
    }

    /**
     * ✅ NEW: Smart message truncation for SMS
     */
    private function truncateMessageForSms(string $message): string
    {
        $maxLength = 160;
        
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        $truncated = substr($message, 0, $maxLength - 3);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace > $maxLength - 20) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }

    /**
     * ✅ FIXED: Get email template with proper fallbacks
     */
    private function getEmailTemplate(string $messageType): string
    {
        $templates = [
            'user_invitation' => 'emails.user-invitation',
            'agent_invitation' => 'emails.agent-invitation',
            'landlord_registration' => 'emails.landlord-invitation',
            'verification_code' => 'emails.verification-code',
            'expiration_warning' => 'emails.expiration-warning',
            'welcome_message' => 'emails.welcome-message',
        ];

        $template = $templates[$messageType] ?? 'emails.default';
        
        if (!$this->emailTemplateExists($template)) {
            Log::warning("Email template not found, using default: {$template}");
            return 'emails.default';
        }

        return $template;
    }

    /**
     * ✅ NEW: Check if email template exists
     */
    private function emailTemplateExists(string $template): bool
    {
        return view()->exists($template);
    }

    /**
     * ✅ NEW: Create fallback email template if missing
     */
    private function createFallbackEmailTemplate(string $template): void
    {
        $templatePath = resource_path('views/' . str_replace('.', '/', $template) . '.blade.php');
        $templateDir = dirname($templatePath);
        
        if (!is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }
        
        $templateContent = <<<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <title>{{ $subject ?? 'Message from System' }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
        .content { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $system_name ?? 'System' }}</h1>
        </div>
        
        <div class="content">
            @if(isset($subject))
                <h2>{{ $subject }}</h2>
            @endif
            
            @if(isset($message))
                <p>{!! nl2br(e($message)) !!}</p>
            @else
                <p>{{ $content ?? 'No message content provided.' }}</p>
            @endif
        </div>
        
        <div class="footer">
            <p>This is an automated message from {{ $system_name ?? 'System' }}.</p>
        </div>
    </div>
</body>
</html>
BLADE;

        if (!file_exists($templatePath)) {
            file_put_contents($templatePath, $templateContent);
            Log::info("Created fallback email template: {$template}");
        }
    }

    /**
     * ✅ NEW: Get email subject based on message type
     */
    private function getEmailSubject(string $messageType, array $options = []): string
    {
        $systemSettings = SystemSetting::first();
        $systemName = $systemSettings->system_name ?? 'System';

        switch ($messageType) {
            case 'user_invitation':
                return "User Invitation - {$systemName}";
            
            case 'agent_invitation':
                $zone = $options['plan_zone'] ?? 'Unknown Zone';
                return "Field Agent Invitation - Zone: {$zone} - {$systemName}";
            
            case 'landlord_registration':
                return "Property Registration Complete - {$systemName}";
            
            case 'verification_code':
                return "Verification Code - {$systemName}";
            
            case 'expiration_warning':
                return "Invitation Expiration Warning - {$systemName}";
            
            case 'welcome_message':
                return "Welcome to {$systemName}";
            
            default:
                return "Message from {$systemName}";
        }
    }

    /**
     * ✅ NEW: Get channel validation message
     */
    private function getChannelValidationMessage(string $channel, User $user): string
    {
        switch ($channel) {
            case 'sms':
                if (empty($user->phone)) return 'Agent phone number not available';
                if (!$this->smsService->isConfigured()) return 'SMS service not configured';
                return 'SMS channel not available';
            
            case 'whatsapp':
                if (empty($user->phone)) return 'Agent phone number not available';
                if (!$this->whatsappService->isConfigured()) return 'WhatsApp service not configured';
                return 'WhatsApp channel not available';
            
            case 'email':
                if (empty($user->email)) return 'Agent email address not available';
                if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) return 'Agent email address is invalid';
                if (!$this->emailService->isConfigured()) return 'Email service not configured';
                return 'Email channel not available';
            
            default:
                return "Unknown channel: {$channel}";
        }
    }

    /**
     * ✅ NEW: Get channel error code
     */
    private function getChannelErrorCode(string $channel, User $user): string
    {
        switch ($channel) {
            case 'sms':
                return empty($user->phone) ? 'NO_PHONE' : 'SMS_NOT_CONFIGURED';
            
            case 'whatsapp':
                return empty($user->phone) ? 'NO_PHONE' : 'WHATSAPP_NOT_CONFIGURED';
            
            case 'email':
                if (empty($user->email)) return 'NO_EMAIL';
                return !filter_var($user->email, FILTER_VALIDATE_EMAIL) ? 'INVALID_EMAIL' : 'EMAIL_NOT_CONFIGURED';
            
            default:
                return 'UNKNOWN_CHANNEL';
        }
    }

    /**
     * ✅ ENHANCED: Send invitation via multiple channels with assignment tracking
     */
    public function sendInvitation(AgentInvitation $invitation, array $channels = ['sms'], bool $isResend = false): array
    {
        $results = [];
        $successCount = 0;
        $agent = $invitation->agent;
        $plan = $invitation->plan;

        // Update invitation with comprehensive tracking
        $updateData = [
            'status' => AgentInvitation::STATUS_SENT,
            'sent_at' => now(),
            'last_sent_at' => now(),
            'invitation_method' => count($channels) > 1 ? 'both' : ($channels[0] ?? 'sms'),
        ];

        // Handle expiration date logic with configuration
        if ($isResend && $this->invitationResendExtendsExpiry) {
            $newExpiration = now()->addDays($this->invitationExpiryDays);
            $updateData['expires_at'] = $newExpiration;
            
            Log::info("Extending invitation expiry during resend", [
                'invitation_id' => $invitation->id,
                'previous_expires_at' => $invitation->expires_at?->toISOString(),
                'new_expires_at' => $newExpiration->toISOString(),
                'expiry_days' => $this->invitationExpiryDays
            ]);
        } elseif (!$invitation->expires_at) {
            $updateData['expires_at'] = now()->addDays($this->invitationExpiryDays);
        }

        // Increment resend count and track resend activity
        if ($isResend) {
            $updateData['resend_count'] = ($invitation->resend_count ?? 0) + 1;
            $updateData['delivery_attempts'] = ($invitation->delivery_attempts ?? 0) + 1;
        } else {
            $updateData['delivery_attempts'] = ($invitation->delivery_attempts ?? 0) + 1;
        }

        $invitation->update($updateData);
        $invitation->refresh();

        // Send via each channel with individual tracking
        foreach ($channels as $channel) {
            $message = $this->generateInvitationMessage($invitation, $channel);
            $options = [
                'channel' => $channel,
                'invitation_id' => $invitation->id,
                'plan_id' => $plan->id,
                'is_test' => false,
                'message_type' => 'agent_invitation',
                'expiry_days' => $this->invitationExpiryDays,
                'is_resend' => $isResend
            ];

            $result = $this->sendMessage($agent, $message, 'agent_invitation', $options);
            $results[$channel] = $result;

            if ($result['success']) {
                $successCount++;
                
                // Update invitation with channel-specific tracking
                $channelUpdateData = [
                    'provider' => $result['provider'] ?? null,
                    'last_sent_at' => now(),
                ];

                // Add delivery receipt if available
                if (!empty($result['delivery_receipt'])) {
                    $channelUpdateData['delivery_receipt'] = array_merge(
                        $invitation->delivery_receipt ?? [],
                        [$channel => $result['delivery_receipt']]
                    );
                }

                // Update metadata with channel results
                $metadata = $invitation->metadata ?? [];
                $metadata['channel_results'] = $metadata['channel_results'] ?? [];
                $metadata['channel_results'][$channel] = [
                    'sent_at' => now()->toISOString(),
                    'success' => true,
                    'provider' => $result['provider'] ?? null,
                    'message_id' => $result['message_id'] ?? null
                ];

                $channelUpdateData['metadata'] = $metadata;
                $invitation->update($channelUpdateData);
            }
        }

        $overallSuccess = $successCount > 0;

        Log::info("Multi-channel invitation completed", [
            'invitation_id' => $invitation->id,
            'agent_id' => $agent->id,
            'plan_id' => $plan->id,
            'assignment_id' => $invitation->assignment_id,
            'channels_attempted' => $channels,
            'channels_successful' => array_keys(array_filter($results, fn($r) => $r['success'])),
            'success_count' => $successCount,
            'is_resend' => $isResend,
            'resend_count' => $invitation->resend_count,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'expiry_days' => $this->invitationExpiryDays,
        ]);

        return [
            'success' => $overallSuccess,
            'message' => $overallSuccess ? 
                "Invitation sent via {$successCount} of " . count($channels) . " channel(s)" : 
                "Failed to send invitation via any channel",
            'results' => $results,
            'success_count' => $successCount,
            'total_channels' => count($channels),
            'channels_attempted' => $channels,
            'channels_successful' => array_keys(array_filter($results, fn($r) => $r['success'])),
            'is_resend' => $isResend,
            'resend_count' => $invitation->resend_count,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'expiry_days' => $this->invitationExpiryDays,
            'invitation_status' => $invitation->status,
            'assignment_id' => $invitation->assignment_id
        ];
    }

    /**
     * ✅ FIXED: Generate channel-specific invitation messages with SMS length validation
     */
    private function generateInvitationMessage(AgentInvitation $invitation, string $channel): string
    {
        $agent = $invitation->agent;
        $plan = $invitation->plan;
        $invitationUrl = route('agent.invitations.accept', $invitation->token);
        $expiryDate = $invitation->expires_at->format('M j, Y');
        $daysUntilExpiry = $invitation->getDaysUntilExpiry();

        // Handle nullable dates
        $startDate = $plan->registration_start_date ? $plan->registration_start_date->format('M j, Y') : 'TBC';
        $endDate = $plan->registration_end_date ? $plan->registration_end_date->format('M j, Y') : 'TBC';

        // Channel-specific message formatting
        switch ($channel) {
            case 'sms':
                // ✅ FIXED: Use SMS template service for proper length management
                return $this->smsTemplateService->generateInvitationMessage($plan, $invitationUrl, $agent->name);
                
            case 'whatsapp':
                // WhatsApp can handle longer messages with emojis
                $message = "👋 Hello {$agent->name}!\n\n" .
                          "You've been invited as a *Field Agent* for property registration.\n\n" .
                          "📍 *Zone:* {$plan->zone}" . ($plan->section ? ", Section: {$plan->section}" : "") . "\n" .
                          "📅 *Period:* {$startDate} to {$endDate}\n" .
                          "🏠 *Estimated Properties:* {$plan->estimated_houses}\n\n" .
                          "🔐 *Accept your invitation:*\n" .
                          "{$invitationUrl}\n\n" .
                          "⏰ *Link expires:* {$expiryDate} ({$daysUntilExpiry} days)\n\n" .
                          "Thank you! 🙏";
                return $message;

            case 'email':
                // Email will use HTML template, this is just fallback
                $message = "Hello {$agent->name}!\n\n" .
                          "You've been invited as a Field Agent for property registration.\n\n" .
                          "📍 Zone: {$plan->zone}" . ($plan->section ? ", Section: {$plan->section}" : "") . "\n" .
                          "📅 Period: {$startDate} to {$endDate}\n" .
                          "🏠 Estimated Properties: {$plan->estimated_houses}\n\n" .
                          "🔐 Accept your invitation:\n" .
                          "{$invitationUrl}\n\n" .
                          "⏰ Link expires: {$expiryDate} ({$daysUntilExpiry} days)\n\n" .
                          "Thank you!";
                return $message;

            default:
                return "Hello {$agent->name}! You have a new field agent assignment. Accept: {$invitationUrl}";
        }
    }

    /**
     * ✅ FIXED: Send landlord registration invitation with SMS optimization
     */
    public function sendLandlordRegistrationInvitation(User $landlord, Property $property, array $channels = ['email']): array
    {
        $results = [];
        $successCount = 0;

        foreach ($channels as $channel) {
            $message = $this->generateLandlordInvitationMessage($landlord, $property, $channel);
            $options = [
                'channel' => $channel,
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'message_type' => 'landlord_registration',
                'landlord' => $landlord,
                'property' => $property
            ];

            $result = $this->sendMessage($landlord, $message, 'landlord_registration', $options);
            $results[$channel] = $result;

            if ($result['success']) {
                $successCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'message' => $successCount > 0 ? 
                "Landlord invitation sent via {$successCount} channel(s)" : 
                "Failed to send landlord invitation",
            'results' => $results,
            'success_count' => $successCount,
            'channels_attempted' => $channels,
            'channels_successful' => array_keys(array_filter($results, fn($r) => $r['success']))
        ];
    }

    /**
     * ✅ FIXED: Generate landlord invitation message with channel-specific optimization
     */
    private function generateLandlordInvitationMessage(User $landlord, Property $property, string $channel = 'email'): string
    {
        $registrationUrl = route('landlord.registration.complete', [
            'token' => $this->generateLandlordToken($landlord, $property)
        ]);

        switch ($channel) {
            case 'sms':
                // ✅ FIXED: Use SMS-optimized message
                return $this->generateSmsLandlordInvitation($landlord, $property, $registrationUrl);
                
            case 'whatsapp':
                // WhatsApp formatted message
                return "👋 Hello {$landlord->name}!\n\n" .
                       "Your property has been registered:\n\n" .
                       "🏠 *Property:* {$property->property_name}\n" .
                       "📍 *Location:* {$property->street_name}, {$property->zone}\n" .
                       "🔢 *Registration:* {$property->registration_pattern}\n" .
                       "📅 *Registered:* {$property->registration_date->format('M j, Y')}\n\n" .
                       "To complete registration and access your portal:\n" .
                       "{$registrationUrl}\n\n" .
                       "⏰ *Link expires in 7 days*\n\n" .
                       "Thank you! 🏡";
                
            case 'email':
            default:
                // Detailed email message
                return "Hello {$landlord->name}!\n\n" .
                       "Your property has been registered in our system:\n" .
                       "🏠 Property: {$property->property_name}\n" .
                       "📍 Location: {$property->street_name}, {$property->zone}\n" .
                       "🔢 Registration: {$property->registration_pattern}\n" .
                       "📅 Registered: {$property->registration_date->format('M j, Y')}\n\n" .
                       "To complete your registration and access your landlord portal, please set your password:\n" .
                       "{$registrationUrl}\n\n" .
                       "This link expires in 7 days.\n\n" .
                       "Thank you for registering with us!";
        }
    }

    /**
     * ✅ NEW: Generate SMS-optimized landlord invitation
     */
    private function generateSmsLandlordInvitation(User $landlord, Property $property, string $registrationUrl): string
    {
        $shortName = $this->getShortName($landlord->name);
        $propName = $this->shortenPropertyName($property->property_name);
        $zone = $this->shortenZoneName($property->zone);

        // Method 1: Personalized short message
        $message = "Hi {$shortName}! {$propName} in {$zone} registered. Complete: {$registrationUrl}";
        if (strlen($message) <= 160) {
            return $message;
        }

        // Method 2: Shorter version
        $message = "Property {$propName} in {$zone} registered. Complete: {$registrationUrl}";
        if (strlen($message) <= 160) {
            return $message;
        }

        // Method 3: Ultra-short version
        $message = "{$propName} registration: {$registrationUrl}";
        if (strlen($message) <= 160) {
            return $message;
        }

        // Method 4: URL-only fallback
        return $this->getUrlOnlyInvitation($registrationUrl);
    }

    /**
     * ✅ NEW: Shorten property name intelligently
     */
    private function shortenPropertyName(string $propertyName, int $maxLength = 20): string
    {
        if (strlen($propertyName) <= $maxLength) {
            return $propertyName;
        }

        // Remove common prefixes/suffixes to shorten
        $shortName = $propertyName;
        $replacements = [
            'apartment' => 'Apt',
            'apartments' => 'Apts',
            'building' => 'Bldg',
            'complex' => 'Cmp',
            'estate' => 'Est',
            'house' => 'Hse',
            'villa' => 'Vla',
            'residence' => 'Res',
            'residential' => 'Res',
            'commercial' => 'Comm',
        ];

        foreach ($replacements as $full => $short) {
            $shortName = str_ireplace($full, $short, $shortName);
        }

        // If still too long, truncate
        if (strlen($shortName) > $maxLength) {
            $shortName = substr($shortName, 0, $maxLength - 3) . '...';
        }

        return $shortName;
    }

    /**
     * ✅ NEW: Get shortened name (first name only)
     */
    private function getShortName(string $fullName): string
    {
        $names = explode(' ', trim($fullName));
        return $names[0] ?? $fullName;
    }

    /**
     * ✅ NEW: Shorten zone name
     */
    private function shortenZoneName(string $zone, int $maxLength = 15): string
    {
        if (strlen($zone) <= $maxLength) {
            return $zone;
        }

        $abbreviations = [
            'zone' => 'Zn',
            'area' => 'Ar',
            'section' => 'Sec',
            'district' => 'Dist',
            'north' => 'N',
            'south' => 'S',
            'east' => 'E', 
            'west' => 'W',
            'central' => 'Ctr',
        ];

        $shortZone = $zone;
        foreach ($abbreviations as $full => $short) {
            $shortZone = str_ireplace($full, $short, $shortZone);
        }

        if (strlen($shortZone) > $maxLength) {
            $shortZone = substr($shortZone, 0, $maxLength - 3) . '...';
        }

        return $shortZone;
    }

    /**
     * ✅ NEW: URL-only fallback
     */
    private function getUrlOnlyInvitation(string $acceptUrl): string
    {
        if (strlen($acceptUrl) > 160) {
            $parsedUrl = parse_url($acceptUrl);
            $shortUrl = $parsedUrl['path'] ?? 'Complete registration in app';
            return "Complete: {$shortUrl}";
        }
        
        return "Complete registration: {$acceptUrl}";
    }

    /**
     * ✅ NEW: Generate landlord token (placeholder - implement as needed)
     */
    private function generateLandlordToken(User $landlord, Property $property): string
    {
        // Implement your token generation logic here
        // This could be a JWT, random string, or use Laravel's built-in tokens
        return md5($landlord->id . $property->id . now()->timestamp);
    }

    /**
     * ✅ NEW: Get comprehensive channel statistics
     */
    public function getChannelStatistics($planId = null): array
    {
        try {
            $query = AgentInvitation::query();

            if ($planId) {
                $query->where('plan_id', $planId);
            }

            $total = $query->count();
            
            // Get counts by invitation method (channel)
            $channelCounts = $query->clone()
                ->selectRaw('invitation_method, COUNT(*) as count')
                ->groupBy('invitation_method')
                ->get()
                ->pluck('count', 'invitation_method')
                ->toArray();

            // Calculate success rates and response times per channel
            $successRates = [];
            $responseTimes = [];
            $channels = ['sms', 'whatsapp', 'email', 'both'];
            
            foreach ($channels as $channel) {
                $channelQuery = clone $query;
                $channelTotal = $channelCounts[$channel] ?? 0;
                $channelAccepted = $channelQuery->where('invitation_method', $channel)
                    ->where('status', AgentInvitation::STATUS_ACCEPTED)
                    ->count();
                
                $successRates[$channel] = $channelTotal > 0 ? 
                    round(($channelAccepted / $channelTotal) * 100, 2) : 0;

                // Calculate average response time
                $acceptedInvitations = $channelQuery->where('invitation_method', $channel)
                    ->where('status', AgentInvitation::STATUS_ACCEPTED)
                    ->whereNotNull('sent_at')
                    ->whereNotNull('accepted_at')
                    ->get();

                $totalResponseTime = 0;
                $countWithTimes = 0;
                
                foreach ($acceptedInvitations as $invitation) {
                    $responseTime = $invitation->sent_at->diffInHours($invitation->accepted_at);
                    $totalResponseTime += $responseTime;
                    $countWithTimes++;
                }

                $responseTimes[$channel] = $countWithTimes > 0 ? 
                    round($totalResponseTime / $countWithTimes, 2) : 0;
            }

            return [
                'total_invitations' => $total,
                'channel_breakdown' => $channelCounts,
                'success_rates' => $successRates,
                'response_times_hours' => $responseTimes,
                'most_used_channel' => $this->getMostUsedChannel($channelCounts),
                'most_effective_channel' => $this->getMostEffectiveChannel($successRates),
                'fastest_response_channel' => $this->getFastestResponseChannel($responseTimes),
                'config' => $this->getServiceConfiguration()
            ];

        } catch (\Exception $e) {
            Log::error('Error getting channel statistics: ' . $e->getMessage());
            
            return [
                'total_invitations' => 0,
                'channel_breakdown' => [],
                'success_rates' => [],
                'response_times_hours' => [],
                'most_used_channel' => 'none',
                'most_effective_channel' => 'none',
                'fastest_response_channel' => 'none',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Get most used channel
     */
    private function getMostUsedChannel(array $channelCounts): string
    {
        if (empty($channelCounts)) {
            return 'none';
        }
        
        return array_search(max($channelCounts), $channelCounts) ?: 'none';
    }

    /**
     * ✅ NEW: Get most effective channel
     */
    private function getMostEffectiveChannel(array $successRates): string
    {
        if (empty($successRates)) {
            return 'none';
        }
        
        $filteredRates = array_filter($successRates, fn($rate) => $rate > 0);
        if (empty($filteredRates)) {
            return 'none';
        }
        
        return array_search(max($filteredRates), $filteredRates) ?: 'none';
    }

    /**
     * ✅ NEW: Get fastest response channel
     */
    private function getFastestResponseChannel(array $responseTimes): string
    {
        if (empty($responseTimes)) {
            return 'none';
        }
        
        $filteredTimes = array_filter($responseTimes, fn($time) => $time > 0);
        if (empty($filteredTimes)) {
            return 'none';
        }
        
        return array_search(min($filteredTimes), $filteredTimes) ?: 'none';
    }

    /**
     * ✅ NEW: Get service configuration for external use
     */
    public function getServiceConfiguration(): array
    {
        return [
            'invitation_expiry_days' => $this->invitationExpiryDays,
            'invitation_warning_days' => $this->invitationWarningDays,
            'invitation_auto_expiry' => $this->invitationAutoExpiry,
            'invitation_resend_extends_expiry' => $this->invitationResendExtendsExpiry,
            'sms_service_configured' => $this->smsService->isConfigured(),
            'email_service_configured' => $this->emailService->isConfigured(),
            'whatsapp_service_configured' => $this->whatsappService->isConfigured(),
            'supported_channels' => ['sms', 'whatsapp', 'email'],
            'default_channel_priority' => ['sms', 'email', 'whatsapp']
        ];
    }

    /**
     * ✅ NEW: Get system health status
     */
    public function getSystemHealth(): array
    {
        $health = [
            'overall' => 'healthy',
            'services' => [],
            'timestamp' => now()->toISOString()
        ];

        // Check SMS service
        $health['services']['sms'] = [
            'configured' => $this->smsService->isConfigured(),
            'status' => $this->smsService->isConfigured() ? 'healthy' : 'not_configured'
        ];

        // Check Email service
        $health['services']['email'] = [
            'configured' => $this->emailService->isConfigured(),
            'status' => $this->emailService->isConfigured() ? 'healthy' : 'not_configured'
        ];

        // Check WhatsApp service
        $health['services']['whatsapp'] = [
            'configured' => $this->whatsappService->isConfigured(),
            'status' => $this->whatsappService->isConfigured() ? 'healthy' : 'not_configured'
        ];

        // Determine overall health
        $configuredServices = array_filter($health['services'], fn($service) => $service['configured']);
        if (empty($configuredServices)) {
            $health['overall'] = 'critical';
        } elseif (count($configuredServices) < 2) {
            $health['overall'] = 'degraded';
        }

        return $health;
    }

    /**
     * ✅ NEW: Get system status for external use
     */
    public function getSystemStatus(): array
    {
        return [
            'service' => 'MultiChannelInvitationService',
            'status' => 'operational',
            'timestamp' => now()->toISOString(),
            'configuration' => $this->getServiceConfiguration(),
            'health' => $this->getSystemHealth(),
            'supported_channels' => ['sms', 'whatsapp', 'email'],
            'version' => '1.0.0'
        ];
    }
}