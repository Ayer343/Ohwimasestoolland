<?php

namespace App\Services;

use App\Models\User;
use App\Models\Property;
use App\Models\LandlordInvitation;
use App\Mail\LandlordInvitationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class LandlordInvitationService
{
    protected $multiChannelInvitationService;
    protected $smsService;
    protected $smsTemplateService;
    protected $whatsappService;

    public function __construct(
        MultiChannelInvitationService $multiChannelInvitationService,
        SmsService $smsService,
        SmsTemplateService $smsTemplateService,
        WhatsAppService $whatsappService = null
    ) {
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->smsService = $smsService;
        $this->smsTemplateService = $smsTemplateService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Send landlord invitation for property registration
     */
    public function sendInvitation(User $landlord, Property $property, array $data): array
    {
        try {
            $channels = $data['invitation_channels'] ?? ['email'];
            $availableChannels = $this->multiChannelInvitationService->getAvailableChannels($landlord);
            
            // Filter channels to only available ones
            $channels = array_intersect($channels, $availableChannels);
            
            if (empty($channels)) {
                return [
                    'success' => false,
                    'message' => 'No available communication channels for this landlord.',
                    'available_channels' => $availableChannels,
                    'landlord_info' => [
                        'has_email' => !empty($landlord->email),
                        'has_phone' => !empty($landlord->phone),
                        'phone_valid' => User::isValidPhoneNumber($landlord->phone)
                    ]
                ];
            }

            // Check SMS readiness before creating invitation
            $smsPrecheck = [];
            if (in_array('sms', $channels)) {
                $smsPrecheck = $this->precheckSmsInvitation($landlord);
                if (!$smsPrecheck['can_send']) {
                    // Remove SMS from channels if not ready
                    $channels = array_diff($channels, ['sms']);
                    Log::warning('SMS pre-check failed, removing from channels', [
                        'landlord_id' => $landlord->id,
                        'precheck_result' => $smsPrecheck,
                        'remaining_channels' => $channels
                    ]);
                }
            }

            // Create landlord invitation record
            $invitation = LandlordInvitation::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'invited_by' => auth()->id(),
                'channels' => $channels,
                'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                'custom_message' => $data['custom_message'] ?? null,
                'expires_at' => now()->addDays($data['expires_in_days'] ?? 7),
                'metadata' => [
                    'sms_precheck' => $smsPrecheck,
                    'requested_channels' => $data['invitation_channels'] ?? [],
                    'custom_message_length' => strlen($data['custom_message'] ?? ''),
                    'expires_in_days' => $data['expires_in_days'] ?? 7,
                    'sms_provider' => $data['sms_provider'] ?? null
                ]
            ]);

            $results = [];
            $successCount = 0;
            $channelsSuccessful = [];
            $channelsFailed = [];

            // Process channels with priority (SMS first, then email, then WhatsApp)
            $orderedChannels = $this->orderChannelsByPriority($channels);
            
            foreach ($orderedChannels as $channel) {
                try {
                    Log::info("Processing {$channel} invitation", [
                        'landlord_id' => $landlord->id,
                        'property_id' => $property->id,
                        'invitation_id' => $invitation->id
                    ]);

                    $result = $this->sendInvitationViaChannel($landlord, $property, $invitation, $channel, $data['custom_message'] ?? null);
                    
                    $results[$channel] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                        $channelsSuccessful[] = $channel;
                        
                        Log::info("{$channel} invitation sent successfully", [
                            'landlord_id' => $landlord->id,
                            'result' => $result
                        ]);
                    } else {
                        $channelsFailed[] = [
                            'channel' => $channel,
                            'error' => $result['message'],
                            'error_code' => $result['error_code'] ?? 'UNKNOWN'
                        ];
                        
                        Log::warning("{$channel} invitation failed", [
                            'landlord_id' => $landlord->id,
                            'error' => $result['message']
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error("Error sending invitation via {$channel}: " . $e->getMessage());
                    
                    $results[$channel] = [
                        'success' => false,
                        'message' => "Failed to send via {$channel}: " . $e->getMessage(),
                        'channel' => $channel,
                        'error_code' => 'EXCEPTION'
                    ];
                    
                    $channelsFailed[] = [
                        'channel' => $channel,
                        'error' => $e->getMessage(),
                        'error_code' => 'EXCEPTION'
                    ];
                }
            }

            // Update invitation status based on sending results
            if ($successCount > 0) {
                $invitation->markAsSent($channelsSuccessful);
                
                Log::info('Landlord invitation partially/completely successful', [
                    'invitation_id' => $invitation->id,
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'channels_successful' => $channelsSuccessful,
                    'channels_failed' => $channelsFailed,
                    'token' => $invitation->token
                ]);
            } else {
                $invitation->markAsFailed('All communication channels failed');
                
                Log::error('All invitation channels failed', [
                    'invitation_id' => $invitation->id,
                    'channels_attempted' => $channels,
                    'results' => $results
                ]);
            }

            // Prepare detailed response
            $response = [
                'success' => $successCount > 0,
                'message' => $successCount > 0 ? 
                    "Invitation sent via " . implode(', ', $channelsSuccessful) : 
                    "Failed to send invitation via any channel",
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'invitation_url' => $invitation->getInvitationUrl(),
                'expires_at' => $invitation->expires_at->format('Y-m-d H:i:s'),
                'channels_attempted' => $channels,
                'channels_successful' => $channelsSuccessful,
                'channels_failed' => $channelsFailed,
                'results' => $results,
                'sms_precheck' => $smsPrecheck,
                'landlord_info' => [
                    'id' => $landlord->id,
                    'name' => $landlord->name,
                    'phone' => $this->maskPhoneNumber($landlord->phone),
                    'email' => $landlord->email,
                    'has_valid_phone' => User::isValidPhoneNumber($landlord->phone),
                    'has_valid_email' => !empty($landlord->email) && filter_var($landlord->email, FILTER_VALIDATE_EMAIL)
                ]
            ];

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to send landlord invitation: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send landlord invitation: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Send invitation via specific channel
     */
    public function sendInvitationViaChannel(User $landlord, Property $property, LandlordInvitation $invitation, string $channel, ?string $customMessage = null): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmailInvitation($landlord, $property, $invitation, $customMessage);
            case 'sms':
                return $this->sendSmsInvitation($landlord, $property, $invitation);
            case 'whatsapp':
                return $this->sendWhatsAppInvitation($landlord, $property, $invitation);
            default:
                return [
                    'success' => false,
                    'message' => "Unknown channel: {$channel}",
                    'channel' => $channel
                ];
        }
    }

    /**
     * Send email invitation
     */
    private function sendEmailInvitation(User $landlord, Property $property, LandlordInvitation $invitation, $customMessage = null): array
    {
        try {
            // Check if landlord has email
            if (empty($landlord->email) || !filter_var($landlord->email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Landlord does not have a valid email address',
                    'channel' => 'email'
                ];
            }

            // Send email using the LandlordInvitationMail
            Mail::to($landlord->email)
                ->send(new LandlordInvitationMail($landlord, $property, $invitation, $customMessage));
            
            Log::info('Email invitation sent successfully', [
                'landlord_email' => $landlord->email,
                'invitation_id' => $invitation->id,
                'property_id' => $property->id
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'channel' => 'email'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send email invitation: ' . $e->getMessage(), [
                'landlord_email' => $landlord->email,
                'invitation_id' => $invitation->id,
                'property_id' => $property->id
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
                'channel' => 'email'
            ];
        }
    }

    /**
     * Send SMS invitation with comprehensive error handling
     */
    private function sendSmsInvitation(User $landlord, Property $property, LandlordInvitation $invitation): array
    {
        try {
            // Check SMS service readiness first
            $smsStatus = $this->smsService->getSystemStatus();
            
            if (!$smsStatus['system_ready'] || !$smsStatus['can_send_sms']) {
                Log::warning('SMS service not ready for landlord invitation', [
                    'landlord_id' => $landlord->id,
                    'property_id' => $property->id,
                    'sms_status' => $smsStatus
                ]);
                
                return [
                    'success' => false,
                    'message' => 'SMS service is not configured or ready',
                    'channel' => 'sms',
                    'error_code' => 'SMS_SERVICE_NOT_READY',
                    'system_status' => $smsStatus
                ];
            }

            // Check if landlord has valid phone number
            if (empty($landlord->phone) || !User::isValidPhoneNumber($landlord->phone)) {
                return [
                    'success' => false,
                    'message' => 'Landlord does not have a valid phone number',
                    'channel' => 'sms',
                    'error_code' => 'INVALID_PHONE_NUMBER',
                    'phone_number' => $this->maskPhoneNumber($landlord->phone ?? 'none')
                ];
            }

            // Generate optimized SMS message using template service
            $message = $this->smsTemplateService->generateLandlordInvitationMessage(
                $landlord,
                $property,
                $invitation->getInvitationUrl(),
                $invitation->token
            );

            // Get message info for logging
            $messageInfo = $this->smsTemplateService->getMessageInfo($message);
            
            Log::info('Generated SMS message for landlord invitation', [
                'landlord_id' => $landlord->id,
                'property_id' => $property->id,
                'invitation_id' => $invitation->id,
                'message_length' => $messageInfo['length'],
                'fits_in_sms' => $messageInfo['fits'],
                'segments' => $messageInfo['segments'],
                'message_preview' => substr($message, 0, 50) . '...'
            ]);

            // Get default provider or fallback
            $defaultProvider = $this->smsService->getDefaultProvider();
            if (!$defaultProvider) {
                $availableProviders = $this->smsService->getAvailableProviders();
                $defaultProvider = array_key_first($availableProviders) ?? 'arkesel';
            }

            // Get SMS provider from invitation metadata or use default
            $smsProvider = $invitation->metadata['sms_provider'] ?? $defaultProvider;

            // Send SMS with proper configuration
            $smsResult = $this->smsService->sendSMS(
                $smsProvider,
                $landlord->phone,
                $message,
                [
                    'is_test' => false,
                    'category' => 'landlord_invitation',
                    'invitation_id' => $invitation->id,
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'metadata' => [
                        'template_type' => 'landlord_invitation',
                        'property_name' => $property->property_name,
                        'registration_pattern' => $property->registration_pattern,
                        'invitation_token' => substr($invitation->token, 0, 10) . '...'
                    ]
                ]
            );

            // Comprehensive result handling
            $response = [
                'success' => $smsResult['success'] ?? false,
                'message' => $smsResult['message'] ?? 'SMS sending completed',
                'channel' => 'sms',
                'provider' => $smsResult['provider'] ?? $smsProvider,
                'message_id' => $smsResult['message_id'] ?? null,
                'status_code' => $smsResult['status_code'] ?? null,
                'execution_time' => $smsResult['execution_time_ms'] ?? null,
                'details' => $smsResult['details'] ?? [],
                'message_info' => $messageInfo,
                'timestamp' => now()->toISOString()
            ];

            // Log detailed results
            if ($response['success']) {
                Log::info('SMS invitation sent successfully', [
                    'landlord_id' => $landlord->id,
                    'property_id' => $property->id,
                    'invitation_id' => $invitation->id,
                    'provider' => $response['provider'],
                    'message_id' => $response['message_id'],
                    'execution_time' => $response['execution_time'] . 'ms'
                ]);
                
                // Add extra success details
                $response['success_details'] = [
                    'sent_at' => now()->toISOString(),
                    'message_length' => $messageInfo['length'],
                    'recipient' => $this->maskPhoneNumber($landlord->phone),
                    'provider_ready' => $smsStatus['ready_providers'] > 0
                ];
            } else {
                Log::error('SMS invitation failed', [
                    'landlord_id' => $landlord->id,
                    'property_id' => $property->id,
                    'invitation_id' => $invitation->id,
                    'provider' => $response['provider'],
                    'error_message' => $response['message'],
                    'error_code' => $smsResult['error_code'] ?? 'UNKNOWN',
                    'sms_response' => $smsResult
                ]);
                
                // Add error recovery information
                $response['recovery_suggestions'] = $this->getSmsRecoverySuggestions($smsResult);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Exception in sendSmsInvitation: ' . $e->getMessage(), [
                'landlord_id' => $landlord->id ?? 'unknown',
                'property_id' => $property->id ?? 'unknown',
                'invitation_id' => $invitation->id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS invitation: ' . $e->getMessage(),
                'channel' => 'sms',
                'error_code' => 'EXCEPTION',
                'exception' => get_class($e),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Send WhatsApp invitation
     */
    private function sendWhatsAppInvitation(User $landlord, Property $property, LandlordInvitation $invitation): array
    {
        try {
            // Check if landlord has phone
            if (empty($landlord->phone)) {
                return [
                    'success' => false,
                    'message' => 'Landlord does not have a phone number',
                    'channel' => 'whatsapp'
                ];
            }

            $message = $this->generateWhatsAppInvitationMessage($landlord, $property, $invitation);
            
            // Check if WhatsApp service has send() method, or use alternative
            if ($this->whatsappService && method_exists($this->whatsappService, 'send')) {
                $result = $this->whatsappService->send($landlord->phone, $message);
            } else if ($this->whatsappService && method_exists($this->whatsappService, 'sendMessage')) {
                $result = $this->whatsappService->sendMessage($landlord->phone, $message);
            } else {
                // Fallback to SMS if WhatsApp service not available
                Log::warning('WhatsApp service not available, falling back to SMS');
                return $this->sendSmsInvitation($landlord, $property, $invitation);
            }
            
            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? ($result['success'] ? 'WhatsApp message sent successfully' : 'Failed to send WhatsApp message'),
                'channel' => 'whatsapp'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp invitation: ' . $e->getMessage(), [
                'landlord_phone' => $landlord->phone ?? 'no phone'
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage(),
                'channel' => 'whatsapp'
            ];
        }
    }

    /**
     * Generate WhatsApp invitation message
     */
    private function generateWhatsAppInvitationMessage(User $landlord, Property $property, LandlordInvitation $invitation): string
    {
        $propertyType = $property->propertyType->name ?? ($property->custom_property_type ?? 'Property');
        $invitationUrl = $invitation->getInvitationUrl();
        
        return "🏠 *Property Registration*\n\n" .
               "Hello {$landlord->name},\n\n" .
               "Your *{$propertyType}* has been registered successfully.\n\n" .
               "📍 *Location:* {$property->street_name}, {$property->zone}\n" .
               "🔢 *Registration ID:* {$property->registration_pattern}\n" .
               "📅 *Registered:* " . ($property->registration_date?->format('M j, Y') ?? 'Today') . "\n\n" .
               "Complete registration here:\n" .
               "{$invitationUrl}\n\n" .
               "⏰ *Link expires:* {$invitation->expires_at->format('F j, Y')}\n\n" .
               "Thank you for using Property Portal!";
    }

    /**
     * Pre-check SMS invitation feasibility
     */
    public function precheckSmsInvitation(User $landlord): array
    {
        $precheck = [
            'can_send' => false,
            'checks' => [],
            'issues' => [],
            'warnings' => []
        ];

        // Check 1: Landlord has phone
        if (empty($landlord->phone)) {
            $precheck['checks']['has_phone'] = false;
            $precheck['issues'][] = 'Landlord has no phone number';
            return $precheck;
        }
        $precheck['checks']['has_phone'] = true;

        // Check 2: Phone number is valid
        if (!User::isValidPhoneNumber($landlord->phone)) {
            $precheck['checks']['phone_valid'] = false;
            $precheck['issues'][] = 'Phone number format is invalid';
            return $precheck;
        }
        $precheck['checks']['phone_valid'] = true;

        // Check 3: SMS service is ready
        $smsStatus = $this->smsService->getSystemStatus();
        if (!$smsStatus['system_ready']) {
            $precheck['checks']['sms_service_ready'] = false;
            $precheck['issues'][] = 'SMS service is not ready';
            $precheck['warnings'][] = 'Check SMS provider configuration';
            return $precheck;
        }
        $precheck['checks']['sms_service_ready'] = true;
        $precheck['sms_status'] = $smsStatus;

        // Check 4: Default provider available
        $defaultProvider = $this->smsService->getDefaultProvider();
        if (!$defaultProvider) {
            $precheck['checks']['has_default_provider'] = false;
            $precheck['warnings'][] = 'No default SMS provider configured';
        } else {
            $precheck['checks']['has_default_provider'] = true;
            $precheck['default_provider'] = $defaultProvider;
        }

        $precheck['can_send'] = true;
        return $precheck;
    }

    /**
     * Order channels by priority
     */
    private function orderChannelsByPriority(array $channels): array
    {
        $priorityOrder = ['sms', 'whatsapp', 'email'];
        
        $ordered = [];
        foreach ($priorityOrder as $channel) {
            if (in_array($channel, $channels)) {
                $ordered[] = $channel;
            }
        }
        
        // Add any remaining channels
        foreach ($channels as $channel) {
            if (!in_array($channel, $ordered)) {
                $ordered[] = $channel;
            }
        }
        
        return $ordered;
    }

    /**
     * Get SMS recovery suggestions based on error
     */
    private function getSmsRecoverySuggestions(array $smsResult): array
    {
        $suggestions = [];
        $errorCode = $smsResult['error_code'] ?? 'UNKNOWN';
        
        switch ($errorCode) {
            case 'SMS_SERVICE_NOT_READY':
                $suggestions = [
                    'Check SMS provider configuration',
                    'Verify SMS provider API keys',
                    'Test SMS connection in admin panel'
                ];
                break;
                
            case 'INVALID_PHONE_NUMBER':
                $suggestions = [
                    'Verify landlord phone number format',
                    'Ensure phone number includes country code',
                    'Check for special characters in phone number'
                ];
                break;
                
            case 'PROVIDER_DISABLED':
                $suggestions = [
                    'Enable SMS provider in settings',
                    'Switch to alternative SMS provider',
                    'Check provider balance/credits'
                ];
                break;
                
            case 'NETWORK_ERROR':
                $suggestions = [
                    'Check internet connectivity',
                    'Verify SMS API endpoint is accessible',
                    'Retry after few minutes'
                ];
                break;
                
            default:
                $suggestions = [
                    'Retry sending the invitation',
                    'Check SMS service logs',
                    'Contact system administrator'
                ];
        }
        
        return $suggestions;
    }

    /**
     * Extract SMS details from invitation result
     */
    public function extractSmsDetails(array $invitationResult): ?array
    {
        if (isset($invitationResult['results']['sms'])) {
            $smsResult = $invitationResult['results']['sms'];
            return [
                'success' => $smsResult['success'] ?? false,
                'provider' => $smsResult['provider'] ?? null,
                'message_id' => $smsResult['message_id'] ?? null,
                'status_code' => $smsResult['status_code'] ?? null,
                'execution_time' => $smsResult['execution_time'] ?? null,
                'message_length' => $smsResult['message_info']['length'] ?? null,
                'delivery_status' => $smsResult['success'] ? 'sent' : 'failed',
                'error_details' => !($smsResult['success'] ?? false) ? [
                    'error_code' => $smsResult['error_code'] ?? 'UNKNOWN',
                    'message' => $smsResult['message'] ?? 'Unknown error'
                ] : null
            ];
        }
        
        return null;
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhoneNumber($phoneNumber): string
    {
        if (empty($phoneNumber)) {
            return 'none';
        }
        
        if (strlen($phoneNumber) <= 8) {
            return substr($phoneNumber, 0, 3) . '****';
        }
        
        return substr($phoneNumber, 0, 4) . '****' . substr($phoneNumber, -4);
    }

    /**
     * Generate SMS test message
     */
    public function generateTestMessage(User $landlord, Property $property, LandlordInvitation $invitation): string
    {
        return $this->smsTemplateService->generateLandlordInvitationMessage(
            $landlord,
            $property,
            $invitation->getInvitationUrl(),
            $invitation->token
        );
    }

    /**
     * Get available channels for a landlord
     */
    public function getAvailableChannels(User $landlord): array
    {
        return $this->multiChannelInvitationService->getAvailableChannels($landlord);
    }

    /**
     * Validate invitation data
     */
    public function validateInvitationData(array $data): array
    {
        $errors = [];
        
        // Validate channels
        if (empty($data['invitation_channels'])) {
            $errors[] = 'Please select at least one invitation channel.';
        } else {
            $validChannels = ['sms', 'email', 'whatsapp'];
            $invalidChannels = array_diff($data['invitation_channels'], $validChannels);
            if (!empty($invalidChannels)) {
                $errors[] = 'Invalid channels selected: ' . implode(', ', $invalidChannels);
            }
        }

        // Validate custom message length
        if (!empty($data['custom_message']) && strlen($data['custom_message']) > 500) {
            $errors[] = 'Custom message must not exceed 500 characters.';
        }

        // Validate expiry days
        if (!empty($data['expires_in_days'])) {
            $expiresInDays = (int) $data['expires_in_days'];
            if ($expiresInDays < 1 || $expiresInDays > 30) {
                $errors[] = 'Expiry days must be between 1 and 30.';
            }
        }

        return $errors;
    }

    /**
     * Create invitation for existing landlord without sending
     */
    public function createInvitationOnly(User $landlord, Property $property, array $data): ?LandlordInvitation
    {
        try {
            $channels = $data['invitation_channels'] ?? ['email'];
            
            return LandlordInvitation::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'invited_by' => auth()->id(),
                'channels' => $channels,
                'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                'custom_message' => $data['custom_message'] ?? null,
                'expires_at' => now()->addDays($data['expires_in_days'] ?? 7),
                'metadata' => [
                    'sms_provider' => $data['sms_provider'] ?? null,
                    'expires_in_days' => $data['expires_in_days'] ?? 7
                ],
                'status' => LandlordInvitation::STATUS_PENDING
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create invitation: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send invitation to existing invitation record
     */
    public function sendToExistingInvitation(LandlordInvitation $invitation, ?array $channels = null): array
    {
        try {
            $landlord = $invitation->landlord;
            $property = $invitation->property;
            
            if (!$landlord || !$property) {
                return [
                    'success' => false,
                    'message' => 'Invalid invitation record. Landlord or property not found.'
                ];
            }

            // Use provided channels or invitation channels
            $channelsToUse = $channels ?? $invitation->channels;
            
            $results = [];
            $successCount = 0;
            $channelsSuccessful = [];

            foreach ($channelsToUse as $channel) {
                $result = $this->sendInvitationViaChannel($landlord, $property, $invitation, $channel, $invitation->custom_message);
                
                $results[$channel] = $result;
                
                if ($result['success']) {
                    $successCount++;
                    $channelsSuccessful[] = $channel;
                }
            }

            // Update invitation status
            if ($successCount > 0) {
                $invitation->markAsSent($channelsSuccessful);
                
                return [
                    'success' => true,
                    'message' => 'Invitation sent via ' . implode(', ', $channelsSuccessful),
                    'channels_successful' => $channelsSuccessful,
                    'results' => $results
                ];
            } else {
                $invitation->markAsFailed('All communication channels failed');
                
                return [
                    'success' => false,
                    'message' => 'Failed to send invitation via any channel',
                    'results' => $results
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to send to existing invitation: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get invitation statistics for a property
     */
    public function getInvitationStats(Property $property): array
    {
        $invitations = LandlordInvitation::where('property_id', $property->id)
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->get();

        $stats = [
            'total_invitations' => $invitations->count(),
            'successful_invitations' => $invitations->where('status', LandlordInvitation::STATUS_SENT)->count(),
            'failed_invitations' => $invitations->where('status', LandlordInvitation::STATUS_FAILED)->count(),
            'pending_invitations' => $invitations->where('status', LandlordInvitation::STATUS_PENDING)->count(),
            'expired_invitations' => $invitations->where('is_expired', true)->count(),
            'last_invitation_sent' => $invitations->first()->sent_at ?? null,
            'sms_success_rate' => $invitations->where('sms_sent', true)->count() > 0 ? 
                round(($invitations->where('sms_status', 'sent')->count() / $invitations->where('sms_sent', true)->count()) * 100, 2) : 0,
            'email_success_rate' => $invitations->where('email_sent', true)->count() > 0 ? 
                round(($invitations->where('email_status', 'sent')->count() / $invitations->where('email_sent', true)->count()) * 100, 2) : 0,
            'whatsapp_success_rate' => $invitations->where('whatsapp_sent', true)->count() > 0 ? 
                round(($invitations->where('whatsapp_status', 'sent')->count() / $invitations->where('whatsapp_sent', true)->count()) * 100, 2) : 0
        ];

        return $stats;
    }

    /**
     * Check if landlord can receive invitation
     */
    public function canReceiveInvitation(User $landlord): array
    {
        $availableChannels = $this->getAvailableChannels($landlord);
        
        return [
            'can_receive' => !empty($availableChannels),
            'available_channels' => $availableChannels,
            'has_email' => !empty($landlord->email) && filter_var($landlord->email, FILTER_VALIDATE_EMAIL),
            'has_phone' => !empty($landlord->phone) && User::isValidPhoneNumber($landlord->phone),
            'is_active' => $landlord->status === User::STATUS_ACTIVE
        ];
    }
}