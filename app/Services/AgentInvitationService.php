<?php

namespace App\Services;

use App\Models\RegistrationPlan;
use App\Models\User;
use App\Models\AgentInvitation;
use App\Models\AgentInvitationLog;
use App\Models\PlanAgentAssignment;
use App\Models\SystemSetting;
use App\Services\SmsService;
use App\Services\MultiChannelInvitationService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Services\SmsTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AgentInvitationService
{
    protected $smsService;
    protected $multiChannelService;
    protected $emailService;
    protected $whatsappService;
    protected $smsTemplateService;

    // ✅ Configurable expiration period from config
    protected $invitationExpiryDays;
    protected $invitationWarningDays;
    protected $invitationAutoExpiry;
    protected $invitationResendExtendsExpiry;

    public function __construct(
        SmsService $smsService,
        MultiChannelInvitationService $multiChannelService,
        EmailService $emailService,
        WhatsAppService $whatsappService,
        SmsTemplateService $smsTemplateService
    ) {
        $this->smsService = $smsService;
        $this->multiChannelService = $multiChannelService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;
        $this->smsTemplateService = $smsTemplateService;
        
        // ✅ Load configuration from config/app.php - WITH TYPE CASTING FIX
        $this->invitationExpiryDays = (int) config('app.invitation_expiry_days', 7);
        $this->invitationWarningDays = (int) config('app.invitation_warning_days', 2);
        $this->invitationAutoExpiry = config('app.invitation_auto_expiry', true);
        $this->invitationResendExtendsExpiry = config('app.invitation_resend_extends_expiry', true);
    }

    /**
     * ✅ CRITICAL FIX: Create invitation with SINGLE master token for all channels
     * Enhanced with email token verification
     */
    public function createInvitationWithMasterToken(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                // Generate ONE master token for all channels
                $masterToken = Str::random(64);
                
                // Calculate expiration date
                $expiresAt = $this->calculateExpirationDate();
                
                // Create the invitation record
                $invitationData = [
                    'plan_id' => $data['plan_id'],
                    'agent_id' => $data['agent_id'],
                    'assignment_id' => $data['assignment_id'] ?? null,
                    'token' => $masterToken, // ✅ SINGLE token for all channels
                    'invitation_method' => $data['invitation_method'] ?? 'email',
                    'expires_at' => $expiresAt,
                    'status' => AgentInvitation::STATUS_SENT,
                    'message' => $data['message'] ?? null,
                    'last_sent_at' => now(),
                ];

                // Add metadata with enhanced tracking
                $invitationData['metadata'] = [
                    'custom_message' => $data['custom_message'] ?? null,
                    'sent_via_service' => true,
                    'master_token' => $masterToken, // Store master token in metadata for verification
                    'channels_sent' => [], // Track which channels used this token
                    'token_created_at' => now()->toISOString(),
                    'token_hash' => hash('sha256', $masterToken) // Store hash for verification
                ];

                $invitation = AgentInvitation::create($invitationData);
                
                // ✅ CRITICAL: Verify token was stored correctly IMMEDIATELY
                $invitation->refresh();
                
                if ($invitation->token !== $masterToken) {
                    Log::error('CRITICAL: Token mismatch during invitation creation', [
                        'expected_token' => $masterToken,
                        'stored_token' => $invitation->token,
                        'invitation_id' => $invitation->id,
                        'action' => 'FORCING_TOKEN_CORRECTION'
                    ]);
                    
                    // Force update with correct token and log the incident
                    $invitation->update(['token' => $masterToken]);
                    $invitation->refresh();
                    
                    // Log the forced correction
                    AgentInvitationLog::logTokenCorrection(
                        $invitation->id,
                        $invitation->token, // old token
                        $masterToken, // new token
                        'database_storage_mismatch'
                    );
                }

                // ✅ VERIFY: Final verification after potential correction
                $finalVerification = $this->verifyTokenConsistency($invitation->id, $masterToken);
                
                Log::info('Master token invitation created', [
                    'invitation_id' => $invitation->id,
                    'master_token' => $masterToken,
                    'stored_token' => $invitation->token,
                    'tokens_match' => $invitation->token === $masterToken,
                    'final_verification' => $finalVerification['consistent'],
                    'plan_id' => $data['plan_id'],
                    'agent_id' => $data['agent_id']
                ]);

                return [
                    'success' => true,
                    'invitation' => $invitation,
                    'master_token' => $masterToken,
                    'invitation_url' => route('agent.invitations.accept', ['token' => $masterToken]),
                    'token_verified' => $finalVerification['consistent']
                ];

            } catch (\Exception $e) {
                Log::error('Error creating invitation with master token: ' . $e->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Failed to create invitation: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * ✅ CRITICAL FIX: Send multi-channel invitation with ENHANCED token consistency
     */
    public function sendMultiChannelInvitationWithTokenConsistency($planId, $agentId, $channels = ['email'], $customMessage = null): array
    {
        return DB::transaction(function () use ($planId, $agentId, $channels, $customMessage) {
            try {
                $plan = RegistrationPlan::findOrFail($planId);
                $agent = User::findOrFail($agentId);
                
                // Get or create assignment
                $assignment = PlanAgentAssignment::where('plan_id', $planId)
                    ->where('agent_id', $agentId)
                    ->where('is_active', true)
                    ->first();

                if (!$assignment) {
                    return [
                        'success' => false,
                        'message' => 'Agent is not assigned to this plan',
                        'error_code' => 'AGENT_NOT_ASSIGNED'
                    ];
                }

                // ✅ CRITICAL FIX: Create ONE invitation with ONE master token
                $invitationResult = $this->createInvitationWithMasterToken([
                    'plan_id' => $planId,
                    'agent_id' => $agentId,
                    'assignment_id' => $assignment->id,
                    'invitation_method' => in_array('email', $channels) ? 'email' : (in_array('sms', $channels) ? 'sms' : 'whatsapp'),
                    'custom_message' => $customMessage
                ]);

                if (!$invitationResult['success']) {
                    return $invitationResult;
                }

                $invitation = $invitationResult['invitation'];
                $masterToken = $invitationResult['master_token'];
                
                // ✅ CRITICAL: Enhanced token consistency verification before sending
                $tokenVerification = $this->verifyTokenConsistency($invitation->id, $masterToken);
                if (!$tokenVerification['consistent']) {
                    Log::error('CRITICAL: Token inconsistency detected before sending', [
                        'invitation_id' => $invitation->id,
                        'expected_token' => $masterToken,
                        'actual_token' => $tokenVerification['actual_token'],
                        'channels' => $channels,
                        'action' => 'ABORTING_SEND_OPERATION'
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Token inconsistency detected. Please try again.',
                        'error_code' => 'TOKEN_INCONSISTENCY',
                        'verification_details' => $tokenVerification
                    ];
                }

                // Send to each channel using the SAME master token
                $results = [];
                $successfulChannels = [];

                foreach ($channels as $channel) {
                    $channelResult = $this->sendToChannelWithVerifiedToken(
                        $invitation, 
                        $agent, 
                        $plan, 
                        $channel, 
                        $masterToken, 
                        $customMessage
                    );
                    
                    $results[$channel] = $channelResult;
                    
                    if ($channelResult['success']) {
                        $successfulChannels[] = $channel;
                        
                        // Update metadata to track which channels used this token
                        $this->updateChannelTracking($invitation->id, $channel, $masterToken);
                    }
                }

                // ✅ VERIFY: Final token consistency check after all sends
                $finalTokenCheck = $this->verifyTokenConsistency($invitation->id, $masterToken);
                if (!$finalTokenCheck['consistent']) {
                    Log::error('CRITICAL: Token changed during multi-channel send', [
                        'invitation_id' => $invitation->id,
                        'original_token' => $masterToken,
                        'final_token' => $finalTokenCheck['actual_token'],
                        'successful_channels' => $successfulChannels
                    ]);
                }

                // Log multi-channel sending
                AgentInvitationLog::logMultiChannelSent(
                    $invitation->id,
                    implode(',', $channels),
                    'multi_channel_invitation',
                    'multiple',
                    auth()->id() ?? null,
                    request()->ip(),
                    [
                        'master_token' => $masterToken,
                        'channels_attempted' => $channels,
                        'channels_successful' => $successfulChannels,
                        'token_verified_pre_send' => $tokenVerification['consistent'],
                        'token_verified_post_send' => $finalTokenCheck['consistent'],
                        'final_token_state' => $finalTokenCheck['actual_token']
                    ]
                );

                return [
                    'success' => count($successfulChannels) > 0,
                    'message' => count($successfulChannels) > 0 ? 
                        'Invitation sent via ' . implode(', ', $successfulChannels) : 
                        'All channels failed',
                    'invitation_id' => $invitation->id,
                    'master_token' => $masterToken,
                    'channels_attempted' => $channels,
                    'channels_successful' => $successfulChannels,
                    'results' => $results,
                    'invitation_url' => $invitationResult['invitation_url'],
                    'token_consistency' => [
                        'pre_send' => $tokenVerification['consistent'],
                        'post_send' => $finalTokenCheck['consistent']
                    ]
                ];

            } catch (\Exception $e) {
                Log::error('Error in multi-channel invitation: ' . $e->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Multi-channel invitation failed: ' . $e->getMessage(),
                    'error_code' => 'MULTI_CHANNEL_EXCEPTION'
                ];
            }
        });
    }

    /**
     * ✅ ENHANCED: Send to specific channel with verified token consistency
     */
    private function sendToChannelWithVerifiedToken(AgentInvitation $invitation, User $agent, RegistrationPlan $plan, string $channel, string $masterToken, ?string $customMessage = null): array
    {
        try {
            // ✅ CRITICAL: Enhanced token verification before each channel send
            $currentToken = $invitation->fresh()->token;
            if ($currentToken !== $masterToken) {
                Log::error('CRITICAL: Token changed before channel send', [
                    'channel' => $channel,
                    'expected_token' => $masterToken,
                    'current_token' => $currentToken,
                    'invitation_id' => $invitation->id,
                    'action' => 'ABORTING_CHANNEL_SEND'
                ]);
                
                // Attempt automatic repair before failing
                $repairResult = $this->repairTokenInconsistency($invitation->id, $masterToken);
                
                if (!$repairResult['success']) {
                    return [
                        'success' => false,
                        'message' => "Token inconsistency detected for {$channel}. Repair failed.",
                        'error_code' => 'TOKEN_CHANGED_DURING_SEND',
                        'repair_attempted' => true,
                        'repair_result' => $repairResult
                    ];
                }
                
                // Refresh after repair
                $invitation->refresh();
                $currentToken = $invitation->token;
            }

            $invitationUrl = route('agent.invitations.accept', ['token' => $masterToken]);
            
            switch ($channel) {
                case 'email':
                    return $this->sendEmailWithVerifiedToken($agent, $invitation, $plan, $masterToken, $customMessage, $invitationUrl);
                    
                case 'sms':
                    return $this->sendSmsWithVerifiedToken($agent, $invitation, $plan, $masterToken, $customMessage, $invitationUrl);
                    
                case 'whatsapp':
                    return $this->sendWhatsAppWithVerifiedToken($agent, $invitation, $plan, $masterToken, $customMessage, $invitationUrl);
                    
                default:
                    return [
                        'success' => false,
                        'message' => "Unsupported channel: {$channel}",
                        'error_code' => 'UNSUPPORTED_CHANNEL'
                    ];
            }
        } catch (\Exception $e) {
            Log::error("Error sending to channel {$channel}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => "{$channel} send failed: " . $e->getMessage(),
                'error_code' => 'CHANNEL_SEND_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ CRITICAL FIX: Send email with ENHANCED token consistency and verification
     */
    private function sendEmailWithVerifiedToken(User $agent, AgentInvitation $invitation, RegistrationPlan $plan, string $masterToken, ?string $customMessage, string $invitationUrl): array
    {
        try {
            if (empty($agent->email)) {
                return [
                    'success' => false,
                    'message' => 'Agent has no email address',
                    'error_code' => 'NO_EMAIL'
                ];
            }

            // ✅ CRITICAL: Pre-email token verification
            $preSendToken = $invitation->fresh()->token;
            if ($preSendToken !== $masterToken) {
                Log::error('CRITICAL: Token mismatch before email generation', [
                    'invitation_id' => $invitation->id,
                    'expected_token' => $masterToken,
                    'actual_token' => $preSendToken,
                    'agent_email' => $agent->email,
                    'action' => 'ABORTING_EMAIL_GENERATION'
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Token inconsistency detected before email generation',
                    'error_code' => 'TOKEN_MISMATCH_PRE_EMAIL'
                ];
            }

            // ✅ VERIFICATION: Enhanced token verification before sending
            Log::info('Sending email with verified token', [
                'invitation_id' => $invitation->id,
                'agent_email' => $agent->email,
                'master_token' => $masterToken,
                'url_token' => basename(parse_url($invitationUrl, PHP_URL_PATH)),
                'tokens_match' => $masterToken === basename(parse_url($invitationUrl, PHP_URL_PATH)),
                'db_token_match' => $preSendToken === $masterToken
            ]);

            $emailData = [
                'agentName' => $agent->name,
                'planDetails' => [
                    'zone' => $plan->zone,
                    'section' => $plan->section,
                    'estimated_houses' => $plan->estimated_houses,
                    'registration_period' => $plan->registration_start_date && $plan->registration_end_date 
                        ? $plan->registration_start_date->format('M d, Y') . ' to ' . $plan->registration_end_date->format('M d, Y')
                        : 'To be determined',
                ],
                'customMessage' => $customMessage,
                'invitationLink' => $invitationUrl, // ✅ Uses verified master token
                'expiryDate' => $invitation->expires_at->format('F j, Y'),
                'daysUntilExpiry' => $this->invitationExpiryDays,
                'invitationId' => $invitation->id,
                'token' => $masterToken, // Include for debugging
                'tokenVerification' => [
                    'expected' => $masterToken,
                    'actual' => $preSendToken,
                    'consistent' => $preSendToken === $masterToken,
                    'verified_at' => now()->toISOString()
                ]
            ];

            // ✅ FIXED: Use EmailService properly without calling Mail::failures()
            $emailResult = $this->emailService->sendAgentInvitation($agent->email, $emailData);

            if ($emailResult['success']) {
                // ✅ CONFIRMATION: Enhanced token verification after successful send
                $postSendToken = $invitation->fresh()->token;
                $tokenStillConsistent = $postSendToken === $masterToken;
                
                if (!$tokenStillConsistent) {
                    Log::error('CRITICAL: Token changed after email send', [
                        'invitation_id' => $invitation->id,
                        'original_token' => $masterToken,
                        'new_token' => $postSendToken,
                        'agent_email' => $agent->email,
                        'action' => 'ALERT_ADMIN_AND_ATTEMPT_REPAIR'
                    ]);
                    
                    // Attempt automatic repair
                    $this->repairTokenInconsistency($invitation->id, $masterToken);
                }

                // Log email send with token verification
                AgentInvitationLog::logEmailSent(
                    $invitation->id,
                    $agent->email,
                    $emailResult['message_id'] ?? 'unknown',
                    $masterToken,
                    $tokenStillConsistent
                );

                return [
                    'success' => true,
                    'message' => 'Email sent with verified token',
                    'provider' => 'email',
                    'token_verified_pre_send' => true,
                    'token_verified_post_send' => $tokenStillConsistent,
                    'invitation_url' => $invitationUrl,
                    'email_verification' => [
                        'sent_to' => $agent->email,
                        'token_used' => $masterToken,
                        'consistent_throughout' => $tokenStillConsistent
                    ]
                ];
            } else {
                Log::error('Email send failed', [
                    'invitation_id' => $invitation->id,
                    'agent_email' => $agent->email,
                    'error' => $emailResult['message'] ?? 'Unknown error',
                    'token_used' => $masterToken
                ]);
                
                return [
                    'success' => false,
                    'message' => $emailResult['message'] ?? 'Email send failed',
                    'error_code' => 'EMAIL_SEND_FAILED'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Email send with verified token failed: ' . $e->getMessage(), [
                'invitation_id' => $invitation->id,
                'agent_email' => $agent->email,
                'master_token' => $masterToken
            ]);
            
            return [
                'success' => false,
                'message' => 'Email exception: ' . $e->getMessage(),
                'error_code' => 'EMAIL_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ NEW: Emergency token fix for existing invitations
     */
    public function emergencyFixTokenMismatch($invitationId, $correctToken): array
    {
        try {
            $invitation = AgentInvitation::find($invitationId);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found'
                ];
            }

            $originalToken = $invitation->token;
            
            if ($originalToken === $correctToken) {
                return [
                    'success' => true,
                    'message' => 'Tokens already match',
                    'current_token' => $originalToken
                ];
            }

            // Update to correct token
            $invitation->update(['token' => $correctToken]);
            
            // Verify the fix
            $verification = $this->verifyTokenConsistency($invitationId, $correctToken);
            
            Log::info('Emergency token fix applied', [
                'invitation_id' => $invitationId,
                'original_token' => $originalToken,
                'correct_token' => $correctToken,
                'fix_successful' => $verification['consistent'],
                'fixed_by' => 'emergency_fix'
            ]);

            return [
                'success' => true,
                'message' => 'Emergency token fix applied successfully',
                'original_token' => $originalToken,
                'new_token' => $correctToken,
                'verification' => $verification,
                'new_invitation_url' => route('agent.invitations.accept', ['token' => $correctToken])
            ];

        } catch (\Exception $e) {
            Log::error('Emergency token fix failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Emergency fix failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Debug token mismatch for specific invitation
     */
    public function debugTokenMismatch($invitationId): array
    {
        try {
            $invitation = AgentInvitation::find($invitationId);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found'
                ];
            }

            $currentToken = $invitation->token;
            $metadata = $invitation->metadata ?? [];
            $masterTokenFromMetadata = $metadata['master_token'] ?? null;
            
            $generatedUrl = route('agent.invitations.accept', ['token' => $currentToken]);
            $urlToken = basename(parse_url($generatedUrl, PHP_URL_PATH));
            
            // Check email logs for this invitation
            $emailLogs = AgentInvitationLog::where('invitation_id', $invitationId)
                ->where('action', 'email_sent')
                ->orderBy('created_at', 'desc')
                ->get();

            $emailTokens = [];
            foreach ($emailLogs as $log) {
                $logMetadata = $log->metadata ?? [];
                if (isset($logMetadata['token_used'])) {
                    $emailTokens[] = [
                        'sent_at' => $log->created_at->toISOString(),
                        'token_used' => $logMetadata['token_used'],
                        'matches_current' => $logMetadata['token_used'] === $currentToken
                    ];
                }
            }

            return [
                'success' => true,
                'invitation_id' => $invitationId,
                'current_token' => $currentToken,
                'master_token_from_metadata' => $masterTokenFromMetadata,
                'metadata_consistent' => $masterTokenFromMetadata === $currentToken,
                'url_generation' => [
                    'generated_url' => $generatedUrl,
                    'url_token' => $urlToken,
                    'matches_db' => $urlToken === $currentToken
                ],
                'email_history' => $emailTokens,
                'recommendation' => $masterTokenFromMetadata && $masterTokenFromMetadata !== $currentToken ? 
                    'Use emergencyFixTokenMismatch to restore from metadata' : 
                    'No clear repair path found'
            ];

        } catch (\Exception $e) {
            Log::error('Token mismatch debug failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Debug failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Send SMS with verified token consistency
     */
    private function sendSmsWithVerifiedToken(User $agent, AgentInvitation $invitation, RegistrationPlan $plan, string $masterToken, ?string $customMessage, string $invitationUrl): array
    {
        try {
            if (empty($agent->phone)) {
                return [
                    'success' => false,
                    'message' => 'Agent has no phone number',
                    'error_code' => 'NO_PHONE'
                ];
            }

            $message = $customMessage ?? $this->generateSmsInvitationMessage($agent, $plan, $invitationUrl);
            
            $result = $this->smsService->sendWithDefaultProvider(
                $agent->phone,
                $message,
                [
                    'is_test' => false,
                    'plan_id' => $plan->id,
                    'agent_id' => $agent->id,
                    'invitation_id' => $invitation->id,
                    'master_token' => $masterToken, // Include for tracking
                    'channel' => 'sms'
                ]
            );

            return [
                'success' => $result['success'],
                'message' => $result['message'] ?? ($result['success'] ? 'SMS sent' : 'SMS failed'),
                'provider' => $result['provider'] ?? 'unknown',
                'token_verified' => true,
                'invitation_url' => $invitationUrl
            ];

        } catch (\Exception $e) {
            Log::error('SMS send with verified token failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMS exception: ' . $e->getMessage(),
                'error_code' => 'SMS_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ NEW: Send WhatsApp with verified token consistency
     */
    private function sendWhatsAppWithVerifiedToken(User $agent, AgentInvitation $invitation, RegistrationPlan $plan, string $masterToken, ?string $customMessage, string $invitationUrl): array
    {
        try {
            if (empty($agent->phone)) {
                return [
                    'success' => false,
                    'message' => 'Agent has no phone number',
                    'error_code' => 'NO_PHONE'
                ];
            }

            $message = $customMessage ?? $this->generateWhatsAppInvitationMessage($agent, $plan, $invitationUrl);
            
            $result = $this->whatsappService->sendMessage(
                $agent->phone,
                $message,
                [
                    'plan_id' => $plan->id,
                    'agent_id' => $agent->id,
                    'invitation_id' => $invitation->id,
                    'master_token' => $masterToken,
                    'channel' => 'whatsapp'
                ]
            );

            return [
                'success' => $result['success'],
                'message' => $result['message'] ?? ($result['success'] ? 'WhatsApp sent' : 'WhatsApp failed'),
                'provider' => $result['provider'] ?? 'unknown',
                'token_verified' => true,
                'invitation_url' => $invitationUrl
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp send with verified token failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'WhatsApp exception: ' . $e->getMessage(),
                'error_code' => 'WHATSAPP_EXCEPTION'
            ];
        }
    }

    /**
     * ✅ UPDATED: Generate SMS invitation message using SmsTemplateService
     */
    private function generateSmsInvitationMessage(User $agent, RegistrationPlan $plan, string $invitationUrl): string
    {
        try {
            // Use the SmsTemplateService to generate a message that fits within SMS limits
            return $this->smsTemplateService->generateInvitationMessage(
                $plan,
                $invitationUrl,
                $agent->name
            );
            
        } catch (\Exception $e) {
            Log::error('Error generating SMS message: ' . $e->getMessage());
            
            // Fallback ultra-short message
            $zone = $this->shortenZoneNameForSms($plan->zone);
            return "Zone {$zone} assignment: {$invitationUrl}";
        }
    }

    /**
     * ✅ NEW: Generate WhatsApp invitation message using template service principles
     */
    private function generateWhatsAppInvitationMessage(User $agent, RegistrationPlan $plan, string $invitationUrl): string
    {
        try {
            $expiryDate = now()->addDays($this->invitationExpiryDays)->format('M j, Y');
            $zone = $this->shortenZoneNameForSms($plan->zone);
            
            // WhatsApp allows longer messages, but still keep it concise
            $message = "👋 *Hello {$agent->name}!*\n\n" .
                      "📋 *Zone Assignment:* {$zone}\n" .
                      "🏠 *Houses:* {$plan->estimated_houses}\n" .
                      "✅ *Accept:* {$invitationUrl}\n" .
                      "⏰ *Expires:* {$expiryDate}";
            
            // Ensure WhatsApp message isn't excessively long
            if (strlen($message) > 1000) {
                $message = "👋 Hello {$agent->name}! Zone {$zone} assignment. " .
                          "Accept: {$invitationUrl} Expires: {$expiryDate}";
            }
            
            return $message;
            
        } catch (\Exception $e) {
            Log::error('Error generating WhatsApp message: ' . $e->getMessage());
            
            // Fallback message
            return "Hello {$agent->name}! You have a new zone assignment. Accept: {$invitationUrl}";
        }
    }

    /**
     * ✅ NEW: Shorten zone name specifically for SMS
     */
    private function shortenZoneNameForSms(string $zone, int $maxLength = 20): string
    {
        if (strlen($zone) <= $maxLength) {
            return $zone;
        }

        // More aggressive abbreviations for SMS
        $abbreviations = [
            'zone' => 'Zn',
            'area' => 'Ar',
            'section' => 'Sect',
            'district' => 'Dist',
            'north' => 'N',
            'south' => 'S',
            'east' => 'E', 
            'west' => 'W',
            'central' => 'Ctr',
            'northern' => 'N',
            'southern' => 'S',
            'eastern' => 'E',
            'western' => 'W',
            'division' => 'Div',
            'region' => 'Reg',
        ];

        $shortZone = $zone;
        foreach ($abbreviations as $full => $short) {
            $shortZone = str_ireplace($full, $short, $shortZone);
        }

        // Remove extra spaces and special characters
        $shortZone = preg_replace('/\s+/', ' ', $shortZone);
        $shortZone = trim($shortZone);

        // If still too long, truncate
        if (strlen($shortZone) > $maxLength) {
            $shortZone = substr($shortZone, 0, $maxLength - 3) . '...';
        }

        return $shortZone;
    }

    /**
     * ✅ NEW: Validate SMS message length before sending
     */
    private function validateAndAdjustSmsMessage(string $message, string $channel = 'sms'): array
    {
        $maxLength = $channel === 'sms' ? 160 : 1000; // WhatsApp allows more characters
        $currentLength = strlen($message);
        
        if ($currentLength <= $maxLength) {
            return [
                'valid' => true,
                'message' => $message,
                'length' => $currentLength,
                'remaining' => $maxLength - $currentLength
            ];
        }
        
        // Message is too long - need to shorten it
        if ($channel === 'sms') {
            // For SMS, use the template service to generate a shorter version
            Log::warning("SMS message too long: {$currentLength} chars. Attempting to shorten.");
            
            // Extract URL from message (this is a simple extraction - adjust as needed)
            preg_match('/https?:\/\/[^\s]+/', $message, $urlMatches);
            $url = $urlMatches[0] ?? '';
            
            // Create a fallback ultra-short message
            $shortMessage = "Assignment notification: {$url}";
            
            if (strlen($shortMessage) > 160) {
                // Last resort - URL only
                $shortMessage = "Accept: {$url}";
            }
            
            return [
                'valid' => strlen($shortMessage) <= 160,
                'message' => $shortMessage,
                'length' => strlen($shortMessage),
                'remaining' => 160 - strlen($shortMessage),
                'original_length' => $currentLength,
                'adjusted' => true,
                'warning' => "Message was shortened from {$currentLength} to " . strlen($shortMessage) . " characters"
            ];
        }
        
        // For WhatsApp, just truncate if necessary
        if ($channel === 'whatsapp' && $currentLength > $maxLength) {
            $truncatedMessage = substr($message, 0, $maxLength - 3) . '...';
            return [
                'valid' => true,
                'message' => $truncatedMessage,
                'length' => strlen($truncatedMessage),
                'remaining' => $maxLength - strlen($truncatedMessage),
                'adjusted' => true,
                'warning' => "WhatsApp message truncated from {$currentLength} characters"
            ];
        }
        
        return [
            'valid' => false,
            'message' => $message,
            'length' => $currentLength,
            'remaining' => $maxLength - $currentLength,
            'error' => "Message exceeds {$maxLength} character limit"
        ];
    }

    /**
     * ✅ NEW: Test SMS message generation for a plan and agent
     */
    public function testSmsMessageGeneration($planId, $agentId): array
    {
        try {
            $plan = RegistrationPlan::findOrFail($planId);
            $agent = User::findOrFail($agentId);
            
            // Generate a test invitation URL
            $testToken = 'test_token_' . Str::random(32);
            $testUrl = route('agent.invitations.accept', ['token' => $testToken]);
            
            // Generate the message using template service
            $message = $this->smsTemplateService->generateInvitationMessage($plan, $testUrl, $agent->name);
            
            // Validate the message
            $validation = $this->validateAndAdjustSmsMessage($message, 'sms');
            
            return [
                'success' => true,
                'message_content' => $validation['message'],
                'length' => $validation['length'],
                'fits_sms' => $validation['valid'],
                'remaining_chars' => $validation['remaining'],
                'adjusted' => $validation['adjusted'] ?? false,
                'warning' => $validation['warning'] ?? null,
                'message_info' => $this->smsTemplateService->getMessageInfo($validation['message']),
                'test_data' => [
                    'agent_name' => $agent->name,
                    'zone' => $plan->zone,
                    'short_zone' => $this->shortenZoneNameForSms($plan->zone),
                    'houses' => $plan->estimated_houses,
                    'expiry_days' => $this->invitationExpiryDays
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('SMS message generation test failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Update channel tracking in invitation metadata
     */
    private function updateChannelTracking($invitationId, $channel, $tokenUsed): void
    {
        try {
            $invitation = AgentInvitation::find($invitationId);
            if (!$invitation) return;

            $metadata = $invitation->metadata ?? [];
            $channelsSent = $metadata['channels_sent'] ?? [];
            
            $channelsSent[$channel] = [
                'sent_at' => now()->toISOString(),
                'token_used' => $tokenUsed,
                'verified' => $tokenUsed === $invitation->token
            ];
            
            $metadata['channels_sent'] = $channelsSent;
            $metadata['last_channel_update'] = now()->toISOString();
            
            $invitation->update(['metadata' => $metadata]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to update channel tracking: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Verify token consistency between DB and expected value
     */
    public function verifyTokenConsistency($invitationId, $expectedToken = null): array
    {
        try {
            $invitation = AgentInvitation::find($invitationId);
            
            if (!$invitation) {
                return [
                    'consistent' => false,
                    'message' => 'Invitation not found',
                    'actual_token' => null,
                    'expected_token' => $expectedToken
                ];
            }

            $actualToken = $invitation->token;
            
            if ($expectedToken === null) {
                // Just return current token info
                return [
                    'consistent' => true,
                    'message' => 'Token verification (no expected token provided)',
                    'actual_token' => $actualToken,
                    'expected_token' => null
                ];
            }

            $isConsistent = $actualToken === $expectedToken;
            
            if (!$isConsistent) {
                Log::error('Token inconsistency detected', [
                    'invitation_id' => $invitationId,
                    'expected_token' => $expectedToken,
                    'actual_token' => $actualToken,
                    'difference' => $expectedToken === $actualToken ? 'none' : 'tokens_differ'
                ]);
            }

            return [
                'consistent' => $isConsistent,
                'message' => $isConsistent ? 'Tokens are consistent' : 'TOKEN MISMATCH DETECTED',
                'actual_token' => $actualToken,
                'expected_token' => $expectedToken,
                'invitation_url_actual' => route('agent.invitations.accept', ['token' => $actualToken]),
                'invitation_url_expected' => route('agent.invitations.accept', ['token' => $expectedToken])
            ];

        } catch (\Exception $e) {
            Log::error('Token consistency check failed: ' . $e->getMessage());
            
            return [
                'consistent' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
                'actual_token' => null,
                'expected_token' => $expectedToken
            ];
        }
    }

    /**
     * ✅ NEW: Repair token inconsistency
     */
    public function repairTokenInconsistency($invitationId, $correctToken): array
    {
        return DB::transaction(function () use ($invitationId, $correctToken) {
            try {
                $invitation = AgentInvitation::findOrFail($invitationId);
                
                $originalToken = $invitation->token;
                
                if ($originalToken === $correctToken) {
                    return [
                        'success' => true,
                        'message' => 'Tokens already match, no repair needed',
                        'original_token' => $originalToken,
                        'correct_token' => $correctToken
                    ];
                }
                
                // Update to correct token
                $invitation->update(['token' => $correctToken]);
                $invitation->refresh();
                
                // Verify repair
                $verification = $this->verifyTokenConsistency($invitationId, $correctToken);
                
                Log::info('Token inconsistency repaired', [
                    'invitation_id' => $invitationId,
                    'original_token' => $originalToken,
                    'correct_token' => $correctToken,
                    'repair_successful' => $verification['consistent'],
                    'repaired_by' => auth()->id() ?? 'system'
                ]);
                
                // Log the repair action
                AgentInvitationLog::logInvitationAction(
                    $invitationId,
                    'token_repaired',
                    "Token repaired from {$originalToken} to {$correctToken}",
                    auth()->id() ?? null,
                    $invitation->agent_id,
                    $invitation->plan_id,
                    request()->ip(),
                    request()->userAgent(),
                    [
                        'original_token' => $originalToken,
                        'new_token' => $correctToken,
                        'repair_verified' => $verification['consistent']
                    ]
                );
                
                return [
                    'success' => true,
                    'message' => 'Token inconsistency repaired successfully',
                    'original_token' => $originalToken,
                    'new_token' => $correctToken,
                    'verification' => $verification
                ];
                
            } catch (\Exception $e) {
                Log::error('Token repair failed: ' . $e->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Token repair failed: ' . $e->getMessage(),
                    'original_token' => null,
                    'correct_token' => $correctToken
                ];
            }
        });
    }

    /**
     * ✅ FIXED: Added missing SMS configuration check method
     */
    public function checkSmsConfiguration(): array
    {
        try {
            // Check if SMS service has configuration check method
            if (method_exists($this->smsService, 'checkConfiguration')) {
                return $this->smsService->checkConfiguration();
            }

            // Fallback basic SMS configuration check
            $defaultProvider = config('sms.default', 'unknown');
            $providers = array_keys(config('sms.providers', []));
            
            $isConfigured = !empty($defaultProvider) && 
                           !empty($providers) &&
                           !empty(config('sms.providers.' . $defaultProvider));

            return [
                'configured' => $isConfigured,
                'default_provider' => $defaultProvider,
                'providers' => $providers,
                'can_send_sms' => $isConfigured,
                'details' => [
                    'driver' => $defaultProvider,
                    'available_providers' => $providers,
                    'test_mode' => config('sms.test_mode', false)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error checking SMS configuration: ' . $e->getMessage());
            
            return [
                'configured' => false,
                'default_provider' => 'unknown',
                'providers' => [],
                'can_send_sms' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ FIXED: Check WhatsApp provider configuration with proper method access
     */
    public function checkWhatsAppConfiguration(): array
    {
        try {
            // ✅ FIX: Use public methods only - check if WhatsApp service has getSystemStatus method
            if (method_exists($this->whatsappService, 'getSystemStatus')) {
                return $this->whatsappService->getSystemStatus();
            }
            
            // ✅ FIX: Alternative - check if there's a public method to check configuration
            if (method_exists($this->whatsappService, 'isConfigured')) {
                $isConfigured = $this->whatsappService->isConfigured();
                return [
                    'configured' => $isConfigured,
                    'default_provider' => 'unknown',
                    'providers' => [],
                    'can_send_whatsapp' => $isConfigured,
                ];
            }

            // ✅ FIX: Final fallback - check configuration directly from config
            $defaultProvider = config('whatsapp.default');
            $providers = config('whatsapp.providers', []);
            
            return [
                'configured' => !empty($defaultProvider) && !empty($providers),
                'default_provider' => $defaultProvider ?? 'unknown',
                'providers' => array_keys($providers),
                'can_send_whatsapp' => !empty($defaultProvider),
            ];
        } catch (\Exception $e) {
            Log::error('Error checking WhatsApp configuration: ' . $e->getMessage());
            
            return [
                'configured' => false,
                'default_provider' => 'unknown',
                'providers' => [],
                'can_send_whatsapp' => false,
            ];
        }
    }

    /**
     * ✅ UPDATED: Get comprehensive system invitation status for dashboard
     */
    public function getSystemInvitationStatus(): array
    {
        try {
            $emailConfig = $this->checkEmailConfiguration();
            $smsConfig = $this->checkSmsConfiguration();
            $whatsappConfig = $this->checkWhatsAppConfiguration();
            $stats = $this->getInvitationStatistics();
            $channelStats = $this->getChannelPerformanceStats();
            
            // Determine overall system status
            $overallStatus = 'ready';
            $issues = [];
            $availableChannels = [];

            // Check channel availability
            if ($emailConfig['configured']) {
                $availableChannels[] = 'email';
            } else {
                $issues[] = 'Email system not configured';
            }

            if ($smsConfig['configured']) {
                $availableChannels[] = 'sms';
            } else {
                $issues[] = 'SMS system not configured';
            }

            if ($whatsappConfig['configured']) {
                $availableChannels[] = 'whatsapp';
            }

            // Update overall status based on issues
            if (count($issues) > 1) {
                $overallStatus = 'degraded';
            }

            if ($stats['with_expiration_issues'] > 0) {
                $overallStatus = 'warning';
                $issues[] = "{$stats['with_expiration_issues']} invitations have expiration issues";
            }

            if ($stats['failed'] > 10) {
                $overallStatus = 'warning';
                $issues[] = "{$stats['failed']} failed invitations";
            }

            if (empty($availableChannels)) {
                $overallStatus = 'error';
                $issues[] = 'No communication channels configured';
            }

            return [
                'overall_status' => $overallStatus,
                'available_channels' => $availableChannels,
                'email_configured' => $emailConfig['configured'],
                'sms_configured' => $smsConfig['configured'],
                'whatsapp_configured' => $whatsappConfig['configured'],
                'email_status' => $emailConfig,
                'sms_status' => $smsConfig,
                'whatsapp_status' => $whatsappConfig,
                'statistics' => $stats,
                'channel_performance' => $channelStats,
                'issues' => $issues,
                'expiry_configuration' => $this->getExpiryConfiguration(),
                'can_send_invitations' => !empty($availableChannels),
                'active_invitations' => AgentInvitation::active()->count(),
                'expiring_soon' => AgentInvitation::expiringSoon($this->invitationWarningDays)->count(),
                'multi_agent_plans' => RegistrationPlan::where('agent_assignment_type', 'multiple')->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting system invitation status: ' . $e->getMessage());
            
            return [
                'overall_status' => 'error',
                'available_channels' => [],
                'email_configured' => false,
                'sms_configured' => false,
                'whatsapp_configured' => false,
                'statistics' => $this->getInvitationStatistics(),
                'issues' => ['Unable to determine system status: ' . $e->getMessage()],
                'expiry_configuration' => $this->getExpiryConfiguration(),
                'can_send_invitations' => false,
                'active_invitations' => 0,
                'expiring_soon' => 0,
            ];
        }
    }

    /**
     * ✅ NEW: Get channel performance statistics
     */
    public function getChannelPerformanceStats(): array
    {
        try {
            $stats = [];
            $channels = ['sms', 'whatsapp', 'email', 'both'];

            foreach ($channels as $channel) {
                $invitations = AgentInvitation::where('invitation_method', $channel)->get();
                $total = $invitations->count();

                if ($total > 0) {
                    $stats[$channel] = [
                        'total' => $total,
                        'accepted' => $invitations->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
                        'expired' => $invitations->where('status', AgentInvitation::STATUS_EXPIRED)->count(),
                        'failed' => $invitations->where('status', AgentInvitation::STATUS_FAILED)->count(),
                        'success_rate' => round(($invitations->where('status', AgentInvitation::STATUS_ACCEPTED)->count() / $total) * 100, 2),
                        'avg_response_time' => $this->calculateAverageResponseTime($invitations),
                    ];
                }
            }

            return $stats;

        } catch (\Exception $e) {
            Log::error('Error getting channel performance stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ NEW: Calculate average response time for invitations
     */
    private function calculateAverageResponseTime($invitations): ?float
    {
        $acceptedInvitations = $invitations->where('status', AgentInvitation::STATUS_ACCEPTED)
            ->whereNotNull('sent_at')
            ->whereNotNull('accepted_at');

        if ($acceptedInvitations->isEmpty()) {
            return null;
        }

        $totalMinutes = 0;
        foreach ($acceptedInvitations as $invitation) {
            $totalMinutes += $invitation->sent_at->diffInMinutes($invitation->accepted_at);
        }

        return round($totalMinutes / $acceptedInvitations->count(), 2);
    }

    /**
     * ✅ UPDATED: Get expiry configuration with enhanced details
     */
    public function getExpiryConfiguration(): array
    {
        return [
            'expiry_days' => $this->invitationExpiryDays,
            'warning_days' => $this->invitationWarningDays,
            'auto_expiry' => $this->invitationAutoExpiry,
            'resend_extends_expiry' => $this->invitationResendExtendsExpiry,
            'description' => "Invitations expire after {$this->invitationExpiryDays} days, with warnings starting {$this->invitationWarningDays} days before expiry",
            'auto_expiry_enabled' => $this->invitationAutoExpiry,
            'resend_extends_enabled' => $this->invitationResendExtendsExpiry
        ];
    }

    /**
     * ✅ NEW: Update expiry configuration
     */
    public function updateExpiryConfiguration(array $config): array
    {
        try {
            // Validate configuration
            $validator = validator($config, [
                'expiry_days' => 'required|integer|min:1|max:30',
                'warning_days' => 'required|integer|min:1|max:7',
                'auto_expiry_enabled' => 'required|boolean',
                'resend_extends_expiry' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Invalid configuration data',
                    'errors' => $validator->errors()->toArray()
                ];
            }

            // Update configuration
            $this->invitationExpiryDays = (int) $config['expiry_days'];
            $this->invitationWarningDays = (int) $config['warning_days'];
            $this->invitationAutoExpiry = (bool) $config['auto_expiry_enabled'];
            $this->invitationResendExtendsExpiry = (bool) $config['resend_extends_expiry'];

            Log::info('Invitation expiry configuration updated', [
                'new_config' => $this->getExpiryConfiguration(),
                'updated_by' => auth()->id() ?? 'system'
            ]);

            return [
                'success' => true,
                'message' => 'Expiry configuration updated successfully',
                'configuration' => $this->getExpiryConfiguration()
            ];

        } catch (\Exception $e) {
            Log::error('Error updating expiry configuration: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update expiry configuration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ UPDATED: Enhanced email configuration check
     */
    public function checkEmailConfiguration(): array
    {
        try {
            $systemSettings = SystemSetting::first();
            
            if (!$systemSettings) {
                return [
                    'success' => false,
                    'message' => 'System settings not found',
                    'configured' => false,
                    'issues' => ['System settings are not configured']
                ];
            }

            // Enhanced email configuration check
            $mailConfig = config('mail');
            $defaultMailer = $mailConfig['default'] ?? null;
            $mailerConfig = $mailConfig['mailers'][$defaultMailer] ?? [];
            
            $canSendEmails = !empty($defaultMailer) && 
                           !empty($mailerConfig['host']) &&
                           !empty($systemSettings->system_email) &&
                           !empty($systemSettings->system_name) &&
                           filter_var($systemSettings->system_email, FILTER_VALIDATE_EMAIL);

            $issues = [];
            $recommendations = [];

            if (empty($defaultMailer)) {
                $issues[] = 'No default mailer configured';
                $recommendations[] = 'Set MAIL_MAILER in .env file';
            }

            if (empty($mailerConfig['host'])) {
                $issues[] = 'Mail host not configured';
                $recommendations[] = 'Set MAIL_HOST in .env file';
            }

            if (empty($systemSettings->system_email)) {
                $issues[] = 'System email not set';
                $recommendations[] = 'Configure system email in settings';
            } elseif (!filter_var($systemSettings->system_email, FILTER_VALIDATE_EMAIL)) {
                $issues[] = 'System email is invalid';
                $recommendations[] = 'Update system email with a valid email address';
            }

            if (empty($systemSettings->system_name)) {
                $issues[] = 'System name not set';
                $recommendations[] = 'Configure system name in settings';
            }

            // Test email template existence
            $templateExists = view()->exists('emails.agent-invitation');
            if (!$templateExists) {
                $issues[] = 'Email template not found';
                $recommendations[] = 'Create agent invitation email template';
            }

            return [
                'success' => true,
                'configured' => $canSendEmails && $templateExists,
                'system_email' => $systemSettings->system_email,
                'system_name' => $systemSettings->system_name,
                'mailer' => $defaultMailer,
                'host' => $mailerConfig['host'] ?? 'not set',
                'port' => $mailerConfig['port'] ?? 'not set',
                'encryption' => $mailerConfig['encryption'] ?? 'not set',
                'template_exists' => $templateExists,
                'can_send_emails' => $canSendEmails && $templateExists,
                'issues' => $issues,
                'recommendations' => $recommendations
            ];

        } catch (\Exception $e) {
            Log::error('Error checking email configuration: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error checking email configuration: ' . $e->getMessage(),
                'configured' => false,
                'issues' => ['Unable to check email configuration']
            ];
        }
    }

    /**
     * ✅ FIXED: Find or create a field agent with enhanced validation and proper logging
     */
    public function findOrCreateAgent(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                // Validate required fields
                if (empty($data['name']) || (empty($data['phone']) && empty($data['email']))) {
                    return [
                        'success' => false,
                        'message' => 'Name and at least one contact method (phone or email) are required'
                    ];
                }

                // Check if user already exists with this phone
                if (!empty($data['phone'])) {
                    $existingUser = User::where('phone', $data['phone'])->first();
                    
                    if ($existingUser) {
                        if ($existingUser->type === User::TYPE_FIELD_AGENT) {
                            return [
                                'success' => true,
                                'agent_id' => $existingUser->id,
                                'agent' => $existingUser,
                                'is_new' => false,
                                'message' => 'Existing field agent found'
                            ];
                        } else {
                            return [
                                'success' => false,
                                'message' => 'Phone number already registered as a different user type'
                            ];
                        }
                    }
                }

                // Check if user exists with this email
                if (!empty($data['email'])) {
                    $existingUser = User::where('email', $data['email'])->first();
                    
                    if ($existingUser) {
                        if ($existingUser->type === User::TYPE_FIELD_AGENT) {
                            return [
                                'success' => true,
                                'agent_id' => $existingUser->id,
                                'agent' => $existingUser,
                                'is_new' => false,
                                'message' => 'Existing field agent found'
                            ];
                        } else {
                            return [
                                'success' => false,
                                'message' => 'Email already registered as a different user type'
                            ];
                        }
                    }
                }

                // Create new agent
                $agentData = [
                    'name' => $data['name'],
                    'type' => User::TYPE_FIELD_AGENT,
                    'status' => 'pending',
                    'password' => Hash::make(Str::random(12)),
                    'invitation_accepted_at' => null,
                ];

                if (!empty($data['email'])) {
                    $agentData['email'] = $data['email'];
                }

                if (!empty($data['phone'])) {
                    $agentData['phone'] = $data['phone'];
                }

                $agent = User::create($agentData);

                // ✅ FIXED: Proper logging for agent creation without invitation_id
                try {
                    AgentInvitationLog::create([
                        'invitation_id' => null, // Explicitly set to null for agent creation
                        'action' => 'agent_created',
                        'details' => "Field agent created: {$data['name']}",
                        'user_id' => auth()->id(),
                        'agent_id' => $agent->id,
                        'plan_id' => null,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'metadata' => [
                            'agent_creation' => true, 
                            'contact_methods' => array_filter([
                                'email' => $data['email'] ?? null, 
                                'phone' => $data['phone'] ?? null
                            ])
                        ]
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to log agent creation (non-critical): ' . $e->getMessage());
                    // Don't fail the entire operation if logging fails
                }

                return [
                    'success' => true,
                    'agent_id' => $agent->id,
                    'agent' => $agent,
                    'is_new' => true,
                    'message' => 'Field agent created successfully',
                    'contact_methods' => [
                        'has_email' => !empty($data['email']),
                        'has_phone' => !empty($data['phone'])
                    ]
                ];

            } catch (\Exception $e) {
                Log::error('Failed to find or create field agent: ' . $e->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Failed to create field agent: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * ✅ COMPLETELY REWRITTEN: Enhanced invitation sending with multi-channel support
     */
    public function sendInvitation($planId, array $data = [], $agentId = null): array
    {
        // ✅ FIXED: Use the new token-consistent method by default
        $channels = [];
        $method = $data['invitation_method'] ?? 'email';
        
        // Determine channels based on method
        switch ($method) {
            case 'sms':
                $channels = ['sms'];
                break;
            case 'whatsapp':
                $channels = ['whatsapp'];
                break;
            case 'email':
                $channels = ['email'];
                break;
            case 'all_channels':
                $channels = ['email', 'sms', 'whatsapp'];
                break;
            default:
                $channels = ['email'];
        }

        return $this->sendMultiChannelInvitationWithTokenConsistency(
            $planId,
            $agentId,
            $channels,
            $data['custom_message'] ?? null
        );
    }

    /**
     * ✅ NEW: Validate channel configuration for invitation method
     */
    private function validateChannelConfiguration(string $method, User $agent): array
    {
        $availableChannels = [];
        $issues = [];

        // Check agent contact methods
        $hasPhone = !empty($agent->phone);
        $hasEmail = !empty($agent->email) && filter_var($agent->email, FILTER_VALIDATE_EMAIL);

        // Check system configuration
        $emailConfig = $this->checkEmailConfiguration();
        $smsConfig = $this->checkSmsConfiguration();
        $whatsappConfig = $this->checkWhatsAppConfiguration();

        // Validate based on method
        switch ($method) {
            case 'sms':
                if (!$hasPhone) {
                    $issues[] = 'Agent does not have a phone number';
                }
                if (!$smsConfig['configured']) {
                    $issues[] = 'SMS system not configured';
                }
                $availableChannels = $hasPhone && $smsConfig['configured'] ? ['sms'] : [];
                break;

            case 'whatsapp':
                if (!$hasPhone) {
                    $issues[] = 'Agent does not have a phone number';
                }
                if (!$whatsappConfig['configured']) {
                    $issues[] = 'WhatsApp system not configured';
                }
                $availableChannels = $hasPhone && $whatsappConfig['configured'] ? ['whatsapp'] : [];
                break;

            case 'email':
                if (!$hasEmail) {
                    $issues[] = 'Agent does not have a valid email address';
                }
                if (!$emailConfig['configured']) {
                    $issues[] = 'Email system not configured';
                }
                $availableChannels = $hasEmail && $emailConfig['configured'] ? ['email'] : [];
                break;

            case 'both':
                $emailReady = $hasEmail && $emailConfig['configured'];
                $smsReady = $hasPhone && $smsConfig['configured'];
                
                if (!$emailReady && !$smsReady) {
                    $issues[] = 'Neither email nor SMS is available';
                } elseif (!$emailReady) {
                    $issues[] = 'Email not available, falling back to SMS';
                    $method = 'sms';
                    $availableChannels = ['sms'];
                } elseif (!$smsReady) {
                    $issues[] = 'SMS not available, falling back to email';
                    $method = 'email';
                    $availableChannels = ['email'];
                } else {
                    $availableChannels = ['email', 'sms'];
                }
                break;

            default:
                $issues[] = "Unsupported invitation method: {$method}";
                $availableChannels = [];
        }

        $canSend = !empty($availableChannels);

        return [
            'can_send' => $canSend,
            'message' => $canSend ? 'Channel configuration valid' : implode(', ', $issues),
            'error_code' => $canSend ? null : 'CHANNEL_CONFIG_INVALID',
            'issues' => $issues,
            'available_channels' => $availableChannels,
            'method' => $method,
            'agent_has_phone' => $hasPhone,
            'agent_has_email' => $hasEmail,
            'email_configured' => $emailConfig['configured'],
            'sms_configured' => $smsConfig['configured'],
            'whatsapp_configured' => $whatsappConfig['configured']
        ];
    }

    /**
     * ✅ NEW: Resend invitation with enhanced multi-channel support
     */
    public function resendInvitation($invitationId, $preferredChannel = null): array
    {
        return DB::transaction(function () use ($invitationId, $preferredChannel) {
            try {
                $invitation = AgentInvitation::with(['agent', 'plan', 'assignment'])->findOrFail($invitationId);

                if ($invitation->isAccepted()) {
                    return [
                        'success' => false,
                        'message' => 'Cannot resend an accepted invitation',
                        'error_code' => 'ALREADY_ACCEPTED'
                    ];
                }

                if (!$invitation->can_resend) {
                    return [
                        'success' => false,
                        'message' => 'Invitation cannot be resent (may have reached resend limit)',
                        'error_code' => 'RESEND_LIMIT_REACHED'
                    ];
                }

                $agent = $invitation->agent;
                $method = $preferredChannel ?? $invitation->invitation_method;

                // Validate channel configuration
                $channelValidation = $this->validateChannelConfiguration($method, $agent);
                if (!$channelValidation['can_send']) {
                    return [
                        'success' => false,
                        'message' => $channelValidation['message'],
                        'error_code' => $channelValidation['error_code'],
                        'available_channels' => $channelValidation['available_channels']
                    ];
                }

                // Use token-consistent resend
                $channels = [$method];
                $result = $this->sendMultiChannelInvitationWithTokenConsistency(
                    $invitation->plan_id,
                    $invitation->agent_id,
                    $channels,
                    $invitation->metadata['custom_message'] ?? null
                );

                if ($result['success']) {
                    // Update invitation for resend
                    $updateData = [
                        'resend_count' => $invitation->resend_count + 1,
                        'last_sent_at' => now(),
                    ];

                    // Extend expiration if configured
                    if ($this->invitationResendExtendsExpiry) {
                        $newExpiration = $this->calculateExpirationDate();
                        $updateData['expires_at'] = $newExpiration;
                    }

                    $invitation->update($updateData);

                    // Log resend action
                    AgentInvitationLog::logInvitationAction(
                        $invitation->id,
                        AgentInvitationLog::ACTION_RESENT,
                        "Invitation resent via {$method}",
                        auth()->id() ?? null,
                        $agent->id,
                        $invitation->plan_id,
                        request()->ip(),
                        request()->userAgent(),
                        [
                            'method' => $method,
                            'resend_count' => $invitation->resend_count + 1,
                            'extends_expiry' => $this->invitationResendExtendsExpiry,
                            'previous_expires_at' => $invitation->expires_at?->toISOString(),
                            'new_expires_at' => $updateData['expires_at']->toISOString() ?? null
                        ]
                    );

                    return [
                        'success' => true,
                        'message' => "Invitation resent successfully via {$method}",
                        'method' => $method,
                        'resend_count' => $invitation->resend_count + 1,
                        'extends_expiry' => $this->invitationResendExtendsExpiry,
                        'new_expires_at' => $updateData['expires_at'] ?? null
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Failed to resend invitation: ' . $result['message'],
                        'error_code' => $result['error_code'] ?? 'RESEND_FAILED'
                    ];
                }

            } catch (\Exception $e) {
                Log::error('Error resending invitation: ' . $e->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Error resending invitation: ' . $e->getMessage(),
                    'error_code' => 'RESEND_EXCEPTION'
                ];
            }
        });
    }

    /**
     * ✅ NEW: Fix expiration issues for all invitations
     */
    public function fixExpirationIssues(): array
    {
        try {
            $invitationsWithIssues = AgentInvitation::withExpirationIssues()->get();
            $fixedCount = 0;
            $details = [];

            foreach ($invitationsWithIssues as $invitation) {
                $validation = $invitation->validateExpiration();
                
                if ($validation['requires_fix'] && $invitation->fixExpirationDate()) {
                    $fixedCount++;
                    $details[] = [
                        'invitation_id' => $invitation->id,
                        'issue' => implode(', ', $validation['issues']),
                        'fixed_at' => now()->toISOString()
                    ];

                    // Log the fix
                    AgentInvitationLog::logCorruptionFixed(
                        $invitation->id,
                        implode(', ', $validation['issues']),
                        $validation['details']['raw_database_value'] ?? null,
                        $invitation->expires_at?->toISOString()
                    );
                }
            }

            return [
                'success' => true,
                'message' => "Fixed {$fixedCount} invitations with expiration issues",
                'fixed_count' => $fixedCount,
                'total_with_issues' => $invitationsWithIssues->count(),
                'details' => $details
            ];

        } catch (\Exception $e) {
            Log::error('Error fixing expiration issues: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error fixing expiration issues: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Get expiration analysis for reporting
     */
    public function getExpirationAnalysis($planId = null): array
    {
        try {
            $query = AgentInvitation::query();
            
            if ($planId) {
                $query->where('plan_id', $planId);
            }

            $invitations = $query->get();
            $analysis = [
                'total_invitations' => $invitations->count(),
                'by_status' => $invitations->groupBy('status')->map->count(),
                'expiration_health' => $invitations->groupBy(function ($invitation) {
                    return $invitation->getExpirationHealth();
                })->map->count(),
                'corrupted_count' => $invitations->filter(function ($invitation) {
                    return $invitation->isCorrupted();
                })->count(),
                'needs_attention' => $invitations->filter(function ($invitation) {
                    return $invitation->getExpirationHealth() === 'critical' || 
                           $invitation->getExpirationHealth() === 'missing_expiration';
                })->count(),
                'average_days_until_expiry' => round($invitations->filter(function ($invitation) {
                    return $invitation->getDaysUntilExpiry() !== null;
                })->avg(function ($invitation) {
                    return $invitation->getDaysUntilExpiry();
                }), 2),
                'config' => $this->getExpiryConfiguration()
            ];

            return [
                'success' => true,
                'analysis' => $analysis
            ];

        } catch (\Exception $e) {
            Log::error('Error getting expiration analysis: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error getting expiration analysis: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ UPDATED: Enhanced invitation statistics with channel breakdown
     */
    public function getInvitationStatistics($planId = null): array
    {
        try {
            $query = AgentInvitation::query();

            if ($planId) {
                $query->where('plan_id', $planId);
            }

            $total = $query->count();
            $sent = $query->where('status', AgentInvitation::STATUS_SENT)->count();
            $accepted = $query->where('status', AgentInvitation::STATUS_ACCEPTED)->count();
            $expired = $query->where('status', AgentInvitation::STATUS_EXPIRED)->count();
            $failed = $query->where('status', AgentInvitation::STATUS_FAILED)->count();
            $revoked = $query->where('status', AgentInvitation::STATUS_REVOKED)->count();
            
            // Channel breakdown
            $channelBreakdown = $planId 
                ? AgentInvitation::where('plan_id', $planId)->groupBy('invitation_method')->selectRaw('invitation_method, COUNT(*) as count')->get()->pluck('count', 'invitation_method')
                : AgentInvitation::groupBy('invitation_method')->selectRaw('invitation_method, COUNT(*) as count')->get()->pluck('count', 'invitation_method');

            // Get invitations with expiration issues
            $withExpirationIssues = $planId 
                ? AgentInvitation::withExpirationIssues()->where('plan_id', $planId)->count()
                : AgentInvitation::withExpirationIssues()->count();

            $successRate = $total > 0 ? round(($accepted / $total) * 100, 2) : 0;

            return [
                'total' => $total,
                'sent' => $sent,
                'accepted' => $accepted,
                'expired' => $expired,
                'failed' => $failed,
                'revoked' => $revoked,
                'with_expiration_issues' => $withExpirationIssues,
                'success_rate' => $successRate,
                'pending' => $sent - $accepted - $expired - $failed - $revoked,
                'channel_breakdown' => $channelBreakdown,
                'expiry_days' => $this->invitationExpiryDays,
                'multi_agent_invitations' => $planId ? AgentInvitation::where('plan_id', $planId)->whereNotNull('assignment_id')->count() : AgentInvitation::whereNotNull('assignment_id')->count()
            ];

        } catch (\Exception $e) {
            Log::error('Error getting invitation statistics: ' . $e->getMessage());
            
            return [
                'total' => 0,
                'sent' => 0,
                'accepted' => 0,
                'expired' => 0,
                'failed' => 0,
                'revoked' => 0,
                'with_expiration_issues' => 0,
                'success_rate' => 0,
                'pending' => 0,
                'channel_breakdown' => [],
                'expiry_days' => $this->invitationExpiryDays,
                'multi_agent_invitations' => 0
            ];
        }
    }

    /**
     * ✅ NEW: Calculate expiration date
     */
    private function calculateExpirationDate(): Carbon
    {
        return now()->addDays($this->invitationExpiryDays);
    }

    /**
     * ✅ NEW: Compare dates with tolerance for microsecond differences
     */
    private function datesAreEffectivelyEqual(?Carbon $date1, ?Carbon $date2, int $toleranceSeconds = 1): bool
    {
        if ($date1 === null || $date2 === null) {
            return $date1 === $date2;
        }

        return abs($date1->diffInSeconds($date2)) <= $toleranceSeconds;
    }

    /**
     * ✅ NEW: Simple email sending method without Mail::failures()
     */
    public function sendSimpleEmailInvitation($agentEmail, $emailData): array
    {
        try {
            // Use the EmailService directly without calling Mail::failures()
            $result = $this->emailService->sendAgentInvitation($agentEmail, $emailData);

            if ($result['success']) {
                Log::info('Simple email invitation sent successfully', [
                    'agent_email' => $agentEmail,
                    'invitation_id' => $emailData['invitationId'] ?? 'unknown'
                ]);
            } else {
                Log::error('Simple email invitation failed', [
                    'agent_email' => $agentEmail,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Simple email invitation exception: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Email exception: ' . $e->getMessage()
            ];
        }
    }
}