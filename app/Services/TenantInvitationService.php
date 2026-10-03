<?php

namespace App\Services;

use App\Models\User;
use App\Models\Property;
use App\Models\TenantInvitation;
use App\Mail\TenantInvitationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TenantInvitationService
{
    protected $smsService;
    protected $whatsappService;

    public function __construct(
        SmsService $smsService,
        WhatsAppService $whatsappService = null
    ) {
        $this->smsService = $smsService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Create tenants and send invitations
     */
    public function createAndInviteTenants(array $tenantsData, Property $property, User $createdBy): array
    {
        $results = [];
        
        foreach ($tenantsData as $index => $tenantData) {
            try {
                Log::info('Creating tenant ' . ($index + 1), [
                    'property_id' => $property->id,
                    'tenant_name' => $tenantData['name'] ?? 'Unknown',
                    'tenant_phone' => $tenantData['phone'] ?? 'Unknown'
                ]);
                
                // Find or create tenant user
                $tenantUser = $this->findOrCreateTenantUser($tenantData, $createdBy);
                
                Log::info('Tenant user found/created', [
                    'tenant_user_id' => $tenantUser->id,
                    'tenant_name' => $tenantUser->name,
                    'is_tenant' => $tenantUser->isTenant()
                ]);
                
                // Attach tenant to property using pivot table
                $property->tenants()->attach($tenantUser->id, [
                    'added_by' => $createdBy->id,
                    'added_at' => now(),
                    'notes' => $tenantData['notes'] ?? null,
                    'status' => 'active'
                ]);
                
                Log::info('Tenant attached to property', [
                    'property_id' => $property->id,
                    'tenant_user_id' => $tenantUser->id
                ]);
                
                // Send invitation if channels specified
                $invitationResult = null;
                if (!empty($tenantData['channels'])) {
                    Log::info('Sending invitation to tenant', [
                        'tenant_user_id' => $tenantUser->id,
                        'channels' => $tenantData['channels']
                    ]);
                    
                    $invitationResult = $this->sendInvitation($tenantUser, $property, $tenantData['channels']);
                    
                    Log::info('Invitation result', [
                        'tenant_user_id' => $tenantUser->id,
                        'invitation_success' => $invitationResult['success'] ?? false,
                        'invitation_id' => $invitationResult['invitation_id'] ?? null,
                        'token' => $invitationResult['token'] ?? null
                    ]);
                } else {
                    Log::warning('No channels specified for tenant invitation', [
                        'tenant_user_id' => $tenantUser->id
                    ]);
                }
                
                $results[] = [
                    'success' => true,
                    'tenant_id' => $tenantUser->id,
                    'tenant_name' => $tenantUser->name,
                    'invitation_result' => $invitationResult,
                    'message' => 'Tenant created' . ($invitationResult ? ' and invitation sent' : '')
                ];
                
            } catch (\Exception $e) {
                Log::error("Failed to create/invite tenant {$index}: " . $e->getMessage(), [
                    'tenant_index' => $index,
                    'tenant_name' => $tenantData['name'] ?? 'Unknown',
                    'tenant_phone' => $tenantData['phone'] ?? 'Unknown',
                    'property_id' => $property->id,
                    'trace' => $e->getTraceAsString()
                ]);
                
                $results[] = [
                    'success' => false,
                    'tenant_index' => $index,
                    'tenant_name' => $tenantData['name'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                    'message' => 'Failed to create/invite tenant'
                ];
            }
        }
        
        Log::info('Tenant creation process completed', [
            'property_id' => $property->id,
            'total_tenants' => count($tenantsData),
            'successful_tenants' => count(array_filter($results, function($result) {
                return $result['success'];
            })),
            'failed_tenants' => count(array_filter($results, function($result) {
                return !$result['success'];
            }))
        ]);
        
        return $results;
    }

    /**
     * Find or create tenant user
     */
    private function findOrCreateTenantUser(array $tenantData, User $createdBy): User
    {
        // Clean phone number
        $cleanPhone = preg_replace('/[^0-9]/', '', $tenantData['phone']);
        
        // Try to find existing user by phone
        $tenantUser = User::findByAnyPhoneFormat($cleanPhone);
        
        if ($tenantUser) {
            // Check if user is already a tenant, if not update their type
            if (!$tenantUser->isTenant()) {
                $tenantUser->update([
                    'type' => User::TYPE_TENANT,
                    'name' => $tenantData['name'],
                    'gender' => $tenantData['gender'] ?? null,
                ]);
            } else {
                // Update existing tenant information
                $updateData = [
                    'name' => $tenantData['name'],
                    'gender' => $tenantData['gender'] ?? null,
                ];
                
                if (!empty($tenantData['email'])) {
                    $updateData['email'] = $tenantData['email'];
                }
                
                $tenantUser->update($updateData);
            }
            
            return $tenantUser;
        }
        
        // Create new tenant user
        return User::create([
            'name' => $tenantData['name'],
            'phone' => $cleanPhone,
            'email' => $tenantData['email'] ?? null,
            'gender' => $tenantData['gender'] ?? null,
            'type' => User::TYPE_TENANT,
            'status' => User::STATUS_ACTIVE,
            'password' => Hash::make(Str::random(12)),
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * Send tenant invitation
     */
    public function sendInvitation(User $tenantUser, Property $property, array $channels): array
    {
        try {
            // Check if user is a tenant
            if (!$tenantUser->isTenant()) {
                return [
                    'success' => false,
                    'message' => 'User is not a tenant.'
                ];
            }

            // Create TenantInvitation record
            $invitation = TenantInvitation::create([
                'user_id' => $tenantUser->id,
                'property_id' => $property->id,
                'invited_by' => auth()->id(),
                'channels' => $channels,
                'status' => TenantInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(7),
                'metadata' => [
                    'property_name' => $property->property_name,
                    'landlord_name' => $property->landlord->name ?? 'N/A',
                    'street_name' => $property->street_name,
                    'zone' => $property->zone,
                    'created_at' => now()->toISOString(),
                    'purpose' => 'tenant_registration'
                ]
            ]);
            
            Log::info('Tenant invitation created', [
                'invitation_id' => $invitation->id,
                'user_id' => $tenantUser->id,
                'property_id' => $property->id,
                'token' => $invitation->token,
                'invitation_url' => $invitation->getInvitationUrl()
            ]);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Failed to create invitation for tenant.'
                ];
            }
            
            $results = [];
            $successCount = 0;
            $channelsSuccessful = [];
            $channelsFailed = [];
            
            // Process channels
            foreach ($channels as $channel) {
                try {
                    $result = $this->sendInvitationViaChannel($tenantUser, $property, $invitation, $channel);
                    
                    $results[$channel] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                        $channelsSuccessful[] = $channel;
                        
                        Log::info("Tenant invitation sent via {$channel}", [
                            'user_id' => $tenantUser->id,
                            'invitation_id' => $invitation->id,
                            'property_id' => $property->id,
                            'channel_result' => $result
                        ]);
                    } else {
                        $channelsFailed[] = [
                            'channel' => $channel,
                            'error' => $result['message']
                        ];
                        
                        Log::warning("Failed to send tenant invitation via {$channel}", [
                            'user_id' => $tenantUser->id,
                            'invitation_id' => $invitation->id,
                            'error' => $result['message']
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error("Error sending tenant invitation via {$channel}: " . $e->getMessage());
                    
                    $results[$channel] = [
                        'success' => false,
                        'message' => "Failed to send via {$channel}: " . $e->getMessage()
                    ];
                    
                    $channelsFailed[] = [
                        'channel' => $channel,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Update invitation status based on sending results
            if ($successCount > 0) {
                $invitation->markAsSent($channelsSuccessful);
                
                Log::info('Tenant invitation sent successfully', [
                    'invitation_id' => $invitation->id,
                    'user_id' => $tenantUser->id,
                    'property_id' => $property->id,
                    'channels_successful' => $channelsSuccessful,
                    'invitation_url' => $invitation->getInvitationUrl()
                ]);
            } else {
                $invitation->markAsFailed('All communication channels failed');
                
                Log::error('All tenant invitation channels failed', [
                    'invitation_id' => $invitation->id,
                    'user_id' => $tenantUser->id,
                    'channels_attempted' => $channels,
                    'results' => $results
                ]);
            }
            
            return [
                'success' => $successCount > 0,
                'message' => $successCount > 0 ? 
                    "Invitation sent via " . implode(', ', $channelsSuccessful) : 
                    "Failed to send invitation via any channel",
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'invitation_url' => $invitation->getInvitationUrl(),
                'expires_at' => $invitation->expires_at->format('Y-m-d H:i:s'),
                'channels_successful' => $channelsSuccessful,
                'channels_failed' => $channelsFailed,
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send tenant invitation: ' . $e->getMessage(), [
                'user_id' => $tenantUser->id ?? null,
                'property_id' => $property->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send tenant invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send tenant invitation via specific channel
     */
    private function sendInvitationViaChannel(User $tenantUser, Property $property, TenantInvitation $invitation, string $channel): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmailInvitation($tenantUser, $property, $invitation);
            case 'sms':
                return $this->sendSmsInvitation($tenantUser, $property, $invitation);
            case 'whatsapp':
                return $this->sendWhatsAppInvitation($tenantUser, $property, $invitation);
            default:
                return [
                    'success' => false,
                    'message' => "Unknown channel: {$channel}"
                ];
        }
    }

    /**
     * Send tenant email invitation
     */
    private function sendEmailInvitation(User $tenantUser, Property $property, TenantInvitation $invitation): array
    {
        try {
            if (empty($tenantUser->email) || !filter_var($tenantUser->email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Tenant does not have a valid email address'
                ];
            }
            
            Mail::to($tenantUser->email)
                ->send(new TenantInvitationMail($tenantUser, $property, $invitation));
            
            Log::info('Tenant email invitation sent successfully', [
                'user_id' => $tenantUser->id,
                'email' => $tenantUser->email,
                'invitation_id' => $invitation->id
            ]);
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send tenant email invitation: ' . $e->getMessage(), [
                'user_id' => $tenantUser->id,
                'email' => $tenantUser->email,
                'invitation_id' => $invitation->id,
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send tenant SMS invitation
     */
    private function sendSmsInvitation(User $tenantUser, Property $property, TenantInvitation $invitation): array
    {
        try {
            // Check SMS service
            $smsStatus = $this->smsService->getSystemStatus();
            
            if (!$smsStatus['system_ready']) {
                return [
                    'success' => false,
                    'message' => 'SMS service is not ready'
                ];
            }
            
            if (empty($tenantUser->phone) || !User::isValidPhoneNumber($tenantUser->phone)) {
                return [
                    'success' => false,
                    'message' => 'Tenant does not have a valid phone number'
                ];
            }
            
            // Generate SMS message
            $landlordName = $property->landlord->name ?? 'N/A';
            $message = "Hello {$tenantUser->name},\n\n" .
              "You've been invited to register as a tenant at:\n" .
              "📍 {$property->property_name}\n" .
              "🏘️ {$property->street_name}, {$property->zone}\n" .
              "📞 Landlord: {$landlordName}\n\n" .
              "Complete your registration:\n" .
              $invitation->getInvitationUrl() . "\n\n" .
              "Link expires: " . $invitation->expires_at->format('M j, Y');
            
            // Send SMS
            $smsResult = $this->smsService->sendSMS(
                $this->smsService->getDefaultProvider() ?? 'arkesel',
                $tenantUser->phone,
                $message,
                [
                    'category' => 'tenant_invitation',
                    'user_id' => $tenantUser->id,
                    'property_id' => $property->id,
                    'invitation_id' => $invitation->id
                ]
            );
            
            Log::info('Tenant SMS invitation sent', [
                'user_id' => $tenantUser->id,
                'phone' => $this->maskPhoneNumber($tenantUser->phone),
                'invitation_id' => $invitation->id,
                'sms_success' => $smsResult['success'] ?? false
            ]);
            
            return [
                'success' => $smsResult['success'] ?? false,
                'message' => $smsResult['message'] ?? 'SMS sending completed'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send tenant SMS invitation: ' . $e->getMessage(), [
                'user_id' => $tenantUser->id,
                'invitation_id' => $invitation->id
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send tenant WhatsApp invitation
     */
    private function sendWhatsAppInvitation(User $tenantUser, Property $property, TenantInvitation $invitation): array
    {
        try {
            if (empty($tenantUser->phone)) {
                return [
                    'success' => false,
                    'message' => 'Tenant does not have a phone number'
                ];
            }
            
            $landlordName = $property->landlord->name ?? 'N/A';
            
            $message = "🏠 *Tenant Registration Invitation*\n\n" .
                      "Hello {$tenantUser->name},\n\n" .
                      "You've been invited to register as a tenant at:\n" .
                      "📍 *Property:* {$property->property_name}\n" .
                      "🏘️ *Address:* {$property->street_name}, {$property->zone}\n" .
                      "👨‍💼 *Landlord:* {$landlordName}\n\n" .
                      "Complete your registration here:\n" .
                      $invitation->getInvitationUrl() . "\n\n" .
                      "⏰ *Link expires:* {$invitation->expires_at->format('F j, Y')}\n\n" .
                      "Thank you!";
        
            // Try to send via WhatsApp service
            if ($this->whatsappService && method_exists($this->whatsappService, 'send')) {
                $result = $this->whatsappService->send($tenantUser->phone, $message);
            } else if ($this->whatsappService && method_exists($this->whatsappService, 'sendMessage')) {
                $result = $this->whatsappService->sendMessage($tenantUser->phone, $message);
            } else {
                // Fallback to SMS
                Log::warning('WhatsApp service not available, falling back to SMS');
                return $this->sendSmsInvitation($tenantUser, $property, $invitation);
            }
            
            Log::info('Tenant WhatsApp invitation sent', [
                'user_id' => $tenantUser->id,
                'invitation_id' => $invitation->id,
                'whatsapp_success' => $result['success'] ?? false
            ]);
            
            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? ($result['success'] ? 'WhatsApp message sent' : 'Failed to send WhatsApp')
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send tenant WhatsApp invitation: ' . $e->getMessage(), [
                'user_id' => $tenantUser->id,
                'invitation_id' => $invitation->id
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Summarize tenant invitation results
     */
    public function summarizeResults(array $results): array
    {
        $successful = array_filter($results, function($result) {
            return $result['success'];
        });
        
        $failed = array_filter($results, function($result) {
            return !$result['success'];
        });
        
        $channelsSuccessful = [];
        foreach ($successful as $result) {
            if (isset($result['invitation_result']['channels_successful'])) {
                $channelsSuccessful = array_merge($channelsSuccessful, $result['invitation_result']['channels_successful']);
            }
        }
        $channelsSuccessful = array_unique($channelsSuccessful);
        
        return [
            'success' => count($successful) > 0,
            'success_count' => count($successful),
            'total_count' => count($results),
            'channels_successful' => $channelsSuccessful,
            'message' => count($successful) > 0 ? 
                count($successful) . ' tenant(s) invited successfully' :
                'Failed to invite tenants',
            'details' => $results
        ];
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
     * Validate tenant data
     */
    public function validateTenantData(array $tenantData): array
    {
        $errors = [];
        
        if (empty($tenantData['name'])) {
            $errors[] = 'Tenant name is required';
        }
        
        if (empty($tenantData['phone'])) {
            $errors[] = 'Tenant phone number is required';
        } elseif (!User::isValidPhoneNumber($tenantData['phone'])) {
            $errors[] = 'Invalid phone number format';
        }
        
        if (!empty($tenantData['email']) && !filter_var($tenantData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        if (empty($tenantData['channels'])) {
            $errors[] = 'Please select at least one invitation channel';
        } else {
            $validChannels = ['sms', 'email', 'whatsapp'];
            $invalidChannels = array_diff($tenantData['channels'], $validChannels);
            if (!empty($invalidChannels)) {
                $errors[] = 'Invalid channels selected: ' . implode(', ', $invalidChannels);
            }
        }

        return $errors;
    }

    /**
     * Get tenant statistics for a property
     */
    public function getTenantStats(Property $property): array
    {
        $tenantUsers = $property->tenants()->get();
        
        return [
            'total_tenants' => $tenantUsers->count(),
            'active_tenants' => $tenantUsers->where('status', 'active')->count(),
            'pending_tenants' => $tenantUsers->where('status', 'pending')->count(),
            'registered_tenants' => $tenantUsers->whereNotNull('email_verified_at')->count(),
            'unregistered_tenants' => $tenantUsers->whereNull('email_verified_at')->count(),
            'tenants_with_invitations' => $tenantUsers->whereHas('invitations')->count(),
            'latest_invitation' => $property->tenantInvitations()->latest()->first()->created_at ?? null
        ];
    }
}