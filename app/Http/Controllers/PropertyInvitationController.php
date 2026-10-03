<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\LandlordInvitation;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use App\Http\Traits\PropertyAuthorizationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyInvitationController extends Controller
{
    use PropertyAuthorizationTrait;

    protected $multiChannelInvitationService;
    protected $smsService;
    protected $smsTemplateService;

    public function __construct(
        MultiChannelInvitationService $multiChannelInvitationService,
        SmsService $smsService,
        SmsTemplateService $smsTemplateService
    ) {
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->smsService = $smsService;
        $this->smsTemplateService = $smsTemplateService;
    }

    /**
     * Resend landlord invitation
     */
    public function resendLandlordInvitation(Request $request, Property $property)
    {
        // Authorization check
        if (!Gate::allows('update', $property)) {
            if (auth()->user()->isFieldAgent() && $property->registered_by != auth()->id()) {
                return $this->unauthorizedResponse($request, 'You can only resend invitations for properties you registered.');
            }
            
            return $this->unauthorizedResponse($request, 'Unauthorized to resend invitation for this property.');
        }

        $landlord = $property->landlord;
        if (!$landlord) {
            return $this->errorResponse($request, 'No landlord associated with this property.');
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'channels' => 'sometimes|array',
            'channels.*' => 'in:sms,email,whatsapp',
            'custom_message' => 'nullable|string|max:500',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
            'force_sms' => 'sometimes|boolean',
            'sms_provider' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        DB::beginTransaction();

        try {
            // Check SMS specifically if requested
            if (in_array('sms', $request->channels ?? [])) {
                $smsPrecheck = $this->precheckSmsInvitation($landlord);
                if (!$smsPrecheck['can_send'] && !$request->boolean('force_sms')) {
                    $request->merge([
                        'channels' => array_diff($request->channels, ['sms'])
                    ]);
                }
            }

            // Find existing invitation or create new one
            $invitation = LandlordInvitation::where('property_id', $property->id)
                ->where('landlord_id', $landlord->id)
                ->where('status', '!=', LandlordInvitation::STATUS_CANCELLED)
                ->latest()
                ->first();

            if ($invitation) {
                if ($invitation->isExpired() || $invitation->status === LandlordInvitation::STATUS_FAILED) {
                    // Create a new invitation
                    $invitation = LandlordInvitation::create([
                        'property_id' => $property->id,
                        'landlord_id' => $landlord->id,
                        'invited_by' => auth()->id(),
                        'channels' => $request->channels ?? ['email'],
                        'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                        'custom_message' => $request->custom_message,
                        'expires_at' => now()->addDays($request->expires_in_days ?? 7),
                        'metadata' => [
                            'sms_provider' => $request->sms_provider ?? null,
                            'is_resend' => true,
                            'original_invitation_id' => $invitation->id
                        ]
                    ]);
                } else {
                    // Update existing invitation
                    $updateData = [];
                    
                    if ($request->has('channels')) {
                        $updateData['channels'] = $request->channels;
                    }
                    
                    if ($request->has('custom_message')) {
                        $updateData['custom_message'] = $request->custom_message;
                    }
                    
                    if ($request->has('expires_in_days')) {
                        $updateData['expires_at'] = now()->addDays($request->expires_in_days);
                    }
                    
                    if (!empty($updateData)) {
                        $invitation->update($updateData);
                    }
                    
                    $invitation->resend($request->channels ?? []);
                }
            } else {
                // Create new invitation
                $invitation = LandlordInvitation::create([
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'invited_by' => auth()->id(),
                    'channels' => $request->channels ?? ['email'],
                    'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                    'custom_message' => $request->custom_message,
                    'expires_at' => now()->addDays($request->expires_in_days ?? 7),
                    'metadata' => [
                        'sms_provider' => $request->sms_provider ?? null
                    ]
                ]);
            }

            // Send the invitation
            $invitationRequestData = [
                'invitation_channels' => $request->channels ?? ['email'],
                'custom_message' => $request->custom_message,
                'expires_in_days' => $request->expires_in_days ?? 7
            ];

            if ($request->filled('sms_provider')) {
                $invitationRequestData['sms_provider'] = $request->sms_provider;
            }

            $invitationRequest = new Request($invitationRequestData);
            $result = $this->sendEnhancedLandlordInvitation($landlord, $property, $invitationRequest);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'invitation_id' => $invitation->id,
                    'invitation_url' => $invitation->getInvitationUrl(),
                    'expires_at' => $invitation->expires_at->format('Y-m-d H:i:s')
                ]);
            }

            if ($result['success']) {
                return redirect()->back()
                    ->with('success', 'Landlord invitation resent successfully via ' . 
                        implode(', ', $result['channels_successful'] ?? []) . '.')
                    ->with('invitation_url', $result['invitation_url'] ?? null);
            } else {
                return redirect()->back()
                    ->with('error', 'Failed to resend landlord invitation: ' . $result['message'])
                    ->with('invitation_result', $result);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse($request, 'Failed to resend landlord invitation. Please try again.');
        }
    }

    /**
     * Test SMS invitation
     */
    public function testSmsInvitation(Request $request, Property $property)
    {
        // Authorization check
        if (!Gate::allows('update', $property)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to test SMS invitation for this property.'
            ], 403);
        }

        $landlord = $property->landlord;
        if (!$landlord) {
            return response()->json([
                'success' => false,
                'message' => 'No landlord associated with this property.'
            ], 404);
        }

        // Create a test invitation
        $testInvitation = LandlordInvitation::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'invited_by' => auth()->id(),
            'channels' => ['sms'],
            'invitation_type' => LandlordInvitation::TYPE_TEST,
            'status' => LandlordInvitation::STATUS_TEST,
            'expires_at' => now()->addDay()
        ]);

        // Generate test message
        $testMessage = $this->smsTemplateService->generateLandlordInvitationMessage(
            $landlord,
            $property,
            $testInvitation->getInvitationUrl(),
            $testInvitation->token
        );

        $messageInfo = $this->smsTemplateService->getMessageInfo($testMessage);

        // Test SMS sending
        $testResult = $this->smsService->sendTestMessage(
            $this->smsService->getDefaultProvider() ?? 'arkesel',
            $landlord->phone,
            $testMessage
        );

        // Prepare test report
        $report = [
            'success' => $testResult['success'] ?? false,
            'test_type' => 'sms_invitation',
            'landlord' => [
                'id' => $landlord->id,
                'name' => $landlord->name,
                'phone' => $this->maskPhoneNumber($landlord->phone),
                'phone_valid' => \App\Models\User::isValidPhoneNumber($landlord->phone)
            ],
            'property' => [
                'id' => $property->id,
                'name' => $property->property_name,
                'registration_pattern' => $property->registration_pattern
            ],
            'message' => [
                'content' => $testMessage,
                'length' => $messageInfo['length'],
                'fits_in_sms' => $messageInfo['fits'],
                'segments' => $messageInfo['segments'],
                'preview' => substr($testMessage, 0, 100) . '...'
            ],
            'sms_service' => [
                'status' => $this->smsService->getSystemStatus(),
                'provider_used' => $testResult['provider'] ?? null,
                'default_provider' => $this->smsService->getDefaultProvider()
            ],
            'test_result' => $testResult,
            'timestamp' => now()->toISOString()
        ];

        // Delete test invitation
        $testInvitation->delete();

        return response()->json([
            'success' => true,
            'message' => 'SMS invitation test completed',
            'report' => $report
        ]);
    }

    /**
     * Get SMS invitation statistics
     */
    public function getSmsInvitationStats(Property $property, Request $request)
    {
        // Authorization check
        if (!Gate::allows('view', $property)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view SMS invitation stats.'
            ], 403);
        }

        $invitations = LandlordInvitation::where('property_id', $property->id)
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->get();

        $smsStats = [
            'total_invitations' => $invitations->count(),
            'sms_attempts' => $invitations->whereIn('sms_sent', [true])->count(),
            'sms_successful' => $invitations->where('sms_status', 'sent')->count(),
            'sms_failed' => $invitations->where('sms_status', 'failed')->count(),
            'last_sms_sent' => $invitations->where('sms_sent', true)->first()->sent_at ?? null,
            'sms_success_rate' => $invitations->where('sms_sent', true)->count() > 0 ? 
                round(($invitations->where('sms_status', 'sent')->count() / $invitations->where('sms_sent', true)->count()) * 100, 2) : 0
        ];

        $recentSmsAttempts = $invitations->where('sms_sent', true)
            ->take(5)
            ->map(function($invitation) {
                return [
                    'sent_at' => $invitation->sent_at->format('Y-m-d H:i:s'),
                    'status' => $invitation->sms_status,
                    'channels' => $invitation->channels,
                    'resend_count' => $invitation->resend_count
                ];
            });

        return response()->json([
            'success' => true,
            'property_id' => $property->id,
            'sms_statistics' => $smsStats,
            'recent_sms_attempts' => $recentSmsAttempts,
            'landlord_info' => $property->landlord ? [
                'has_phone' => !empty($property->landlord->phone),
                'phone_valid' => \App\Models\User::isValidPhoneNumber($property->landlord->phone),
                'last_phone_update' => $property->landlord->updated_at->format('Y-m-d H:i:s')
            ] : null,
            'sms_service_status' => $this->smsService->getSystemStatus()
        ]);
    }

    /**
     * Enhanced: Send landlord invitation
     */
    private function sendEnhancedLandlordInvitation($landlord, $property, Request $request): array
    {
        try {
            $channels = $request->invitation_channels ?? ['email'];
            $availableChannels = $this->multiChannelInvitationService->getAvailableChannels($landlord);
            
            // Filter channels to only available ones
            $channels = array_intersect($channels, $availableChannels);
            
            if (empty($channels)) {
                return [
                    'success' => false,
                    'message' => 'No available communication channels for this landlord.',
                    'available_channels' => $availableChannels
                ];
            }

            // Check SMS readiness before creating invitation
            $smsPrecheck = [];
            if (in_array('sms', $channels)) {
                $smsPrecheck = $this->precheckSmsInvitation($landlord);
                if (!$smsPrecheck['can_send']) {
                    $channels = array_diff($channels, ['sms']);
                }
            }

            // Create landlord invitation record
            $invitation = LandlordInvitation::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'invited_by' => auth()->id(),
                'channels' => $channels,
                'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                'custom_message' => $request->custom_message,
                'expires_at' => now()->addDays($request->expires_in_days ?? 7),
                'metadata' => [
                    'sms_precheck' => $smsPrecheck,
                    'requested_channels' => $request->invitation_channels ?? [],
                    'expires_in_days' => $request->expires_in_days ?? 7,
                    'sms_provider' => $request->sms_provider ?? null
                ]
            ]);

            $results = [];
            $successCount = 0;
            $channelsSuccessful = [];
            $channelsFailed = [];

            // Process channels with priority
            $orderedChannels = $this->orderChannelsByPriority($channels);
            
            foreach ($orderedChannels as $channel) {
                try {
                    $result = $this->sendInvitationViaChannel($landlord, $property, $invitation, $channel, $request->custom_message);
                    
                    $results[$channel] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                        $channelsSuccessful[] = $channel;
                    } else {
                        $channelsFailed[] = [
                            'channel' => $channel,
                            'error' => $result['message']
                        ];
                    }
                } catch (\Exception $e) {
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
            } else {
                $invitation->markAsFailed('All communication channels failed');
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
                'channels_attempted' => $channels,
                'channels_successful' => $channelsSuccessful,
                'channels_failed' => $channelsFailed,
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send enhanced landlord invitation: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send landlord invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send invitation via specific channel
     */
    private function sendInvitationViaChannel($landlord, $property, $invitation, $channel, $customMessage = null): array
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
                    'message' => "Unknown channel: {$channel}"
                ];
        }
    }

    /**
     * Send email invitation
     */
    private function sendEmailInvitation($landlord, $property, $invitation, $customMessage = null): array
    {
        try {
            if (empty($landlord->email) || !filter_var($landlord->email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Landlord does not have a valid email address'
                ];
            }

            // Send email
            \Mail::to($landlord->email)
                ->send(new \App\Mail\LandlordInvitationMail($landlord, $property, $invitation, $customMessage));

            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send email invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS invitation
     */
    private function sendSmsInvitation($landlord, $property, $invitation): array
    {
        try {
            // Check SMS service readiness
            $smsStatus = $this->smsService->getSystemStatus();
            
            if (!$smsStatus['system_ready'] || !$smsStatus['can_send_sms']) {
                return [
                    'success' => false,
                    'message' => 'SMS service is not configured or ready'
                ];
            }

            // Check if landlord has valid phone number
            if (empty($landlord->phone) || !\App\Models\User::isValidPhoneNumber($landlord->phone)) {
                return [
                    'success' => false,
                    'message' => 'Landlord does not have a valid phone number'
                ];
            }

            // Generate SMS message
            $message = $this->smsTemplateService->generateLandlordInvitationMessage(
                $landlord,
                $property,
                $invitation->getInvitationUrl(),
                $invitation->token
            );

            // Get default provider
            $defaultProvider = $this->smsService->getDefaultProvider();
            if (!$defaultProvider) {
                $availableProviders = $this->smsService->getAvailableProviders();
                $defaultProvider = array_key_first($availableProviders) ?? 'arkesel';
            }

            // Get SMS provider from invitation metadata or use default
            $smsProvider = $invitation->metadata['sms_provider'] ?? $defaultProvider;

            // Send SMS
            $smsResult = $this->smsService->sendSMS(
                $smsProvider,
                $landlord->phone,
                $message,
                [
                    'is_test' => false,
                    'category' => 'landlord_invitation',
                    'invitation_id' => $invitation->id,
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id
                ]
            );

            return [
                'success' => $smsResult['success'] ?? false,
                'message' => $smsResult['message'] ?? 'SMS sending completed',
                'provider' => $smsResult['provider'] ?? $smsProvider,
                'message_id' => $smsResult['message_id'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Exception in sendSmsInvitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send WhatsApp invitation
     */
    private function sendWhatsAppInvitation($landlord, $property, $invitation): array
    {
        try {
            if (empty($landlord->phone)) {
                return [
                    'success' => false,
                    'message' => 'Landlord does not have a phone number'
                ];
            }

            $message = $this->generateWhatsAppInvitationMessage($landlord, $property, $invitation);
            
            // Try to send via WhatsApp service
            if (method_exists(\App\Services\WhatsAppService::class, 'send')) {
                $result = app(\App\Services\WhatsAppService::class)->send($landlord->phone, $message);
            } else {
                // Fallback to SMS
                return $this->sendSmsInvitation($landlord, $property, $invitation);
            }
            
            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? ($result['success'] ? 'WhatsApp message sent' : 'Failed to send WhatsApp')
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp invitation: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate WhatsApp invitation message
     */
    private function generateWhatsAppInvitationMessage($landlord, $property, $invitation): string
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
    private function precheckSmsInvitation($landlord): array
    {
        $precheck = [
            'can_send' => false,
            'checks' => [],
            'issues' => [],
            'warnings' => []
        ];

        if (empty($landlord->phone)) {
            $precheck['checks']['has_phone'] = false;
            $precheck['issues'][] = 'Landlord has no phone number';
            return $precheck;
        }
        $precheck['checks']['has_phone'] = true;

        if (!\App\Models\User::isValidPhoneNumber($landlord->phone)) {
            $precheck['checks']['phone_valid'] = false;
            $precheck['issues'][] = 'Phone number format is invalid';
            return $precheck;
        }
        $precheck['checks']['phone_valid'] = true;

        $smsStatus = $this->smsService->getSystemStatus();
        if (!$smsStatus['system_ready']) {
            $precheck['checks']['sms_service_ready'] = false;
            $precheck['issues'][] = 'SMS service is not ready';
            return $precheck;
        }
        $precheck['checks']['sms_service_ready'] = true;

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
     * Helper: Response methods
     */
    private function unauthorizedResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 403);
        }
        return redirect()->back()->with('error', $message);
    }

    private function validationErrorResponse(Request $request, $validator)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    private function errorResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 500);
        }
        return redirect()->back()->with('error', $message);
    }
}