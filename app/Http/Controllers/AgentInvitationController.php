<?php

namespace App\Http\Controllers;

use App\Models\AgentInvitation;
use App\Models\RegistrationPlan;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\SmsService;
use App\Services\AgentInvitationService;
use App\Services\MultiChannelInvitationService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AgentInvitationController extends Controller
{
    protected $smsService;
    protected $invitationService;
    protected $multiChannelService;
    protected $emailService;
    protected $whatsappService;

    // ✅ Configurable expiration properties
    protected $invitationExpiryDays;
    protected $invitationWarningDays;
    protected $invitationAutoExpiry;
    protected $invitationResendExtendsExpiry;

    public function __construct(
        SmsService $smsService,
        AgentInvitationService $invitationService,
        MultiChannelInvitationService $multiChannelService,
        EmailService $emailService,
        WhatsAppService $whatsappService
    ) {
        $this->smsService = $smsService;
        $this->invitationService = $invitationService;
        $this->multiChannelService = $multiChannelService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;
        
        // ✅ Load all configuration from config/app.php
        $this->invitationExpiryDays = config('app.invitation_expiry_days', 7);
        $this->invitationWarningDays = config('app.invitation_warning_days', 2);
        $this->invitationAutoExpiry = config('app.invitation_auto_expiry', true);
        $this->invitationResendExtendsExpiry = config('app.invitation_resend_extends_expiry', true);
    }

    /**
 * ✅ UPDATED: Show the invitation acceptance form with multi-channel support and system branding
 */
public function showAcceptForm($token)
{
    try {
        // ✅ Load invitation without triggering any accessors initially
        $invitation = AgentInvitation::with(['plan', 'agent'])
            ->where('token', $token)
            ->firstOrFail();

        // ✅ Get system settings for branding
        $systemSettings = SystemSetting::getSettings();
        $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');
        $supportEmail = $systemSettings->system_email ?? 'support@example.com';

        // ✅ IMMEDIATE RAW CHECK - before any model methods are called
        $rawExpiresAt = $invitation->getRawOriginal('expires_at');
        $rawStatus = $invitation->getRawOriginal('status');
        $rawCreatedAt = $invitation->getRawOriginal('created_at');
        
        $isActuallyExpired = $rawExpiresAt && Carbon::parse($rawExpiresAt)->isPast();
        
        // ✅ CRITICAL: Detect corrupted invitations (expiration set to same day as creation)
        $isCorrupted = false;
        $hoursDifference = null;
        
        if ($rawCreatedAt && $rawExpiresAt) {
            $createdAt = Carbon::parse($rawCreatedAt);
            $expiresAt = Carbon::parse($rawExpiresAt);
            $hoursDifference = $createdAt->diffInHours($expiresAt);
            $isCorrupted = $hoursDifference < 24; // Less than 1 day difference
        }

        Log::info('Invitation accessed - Enhanced safe check', [
            'invitation_id' => $invitation->id,
            'raw_status' => $rawStatus,
            'raw_expires_at' => $rawExpiresAt,
            'raw_created_at' => $rawCreatedAt,
            'is_actually_expired' => $isActuallyExpired,
            'is_corrupted' => $isCorrupted,
            'hours_difference' => $hoursDifference,
            'token' => $token,
            'auto_expiry_enabled' => $this->invitationAutoExpiry,
            'invitation_method' => $invitation->invitation_method,
            'system_name' => $systemName
        ]);

        // ✅ CRITICAL FIX: Auto-repair corrupted invitations before any status checks
        if ($isCorrupted && $rawStatus === AgentInvitation::STATUS_SENT) {
            Log::warning('Auto-repairing corrupted invitation', [
                'invitation_id' => $invitation->id,
                'current_expires_at' => $rawExpiresAt,
                'created_at' => $rawCreatedAt,
                'hours_difference' => $hoursDifference
            ]);
            
            if ($invitation->fixExpirationDate()) {
                $invitation->refresh();
                Log::info('Successfully auto-repaired corrupted invitation', [
                    'invitation_id' => $invitation->id,
                    'new_expires_at' => $invitation->getRawOriginal('expires_at')
                ]);
                
                // Reset the expiration check after repair
                $isActuallyExpired = false;
            }
        }

        // ✅ Use safe status check after potential repair
        $safeStatus = $invitation->getSafeStatus();

        // ✅ Handle expiration based on safe status
        if ($safeStatus === AgentInvitation::STATUS_EXPIRED || $safeStatus === 'should_be_expired') {
            // Only auto-expire if it's actually expired (not just corrupted)
            if ($this->invitationAutoExpiry && $safeStatus === 'should_be_expired' && !$isCorrupted) {
                Log::warning('Marking invitation as expired on access', [
                    'invitation_id' => $invitation->id,
                    'previous_status' => $rawStatus,
                    'expires_at' => $rawExpiresAt
                ]);
                
                $invitation->markAsExpired();
                $invitation->refresh();
            }

            return redirect()->route('agent.invitations.expired')
                ->with('error', 'This invitation has expired.')
                ->with('invitation', $invitation)
                ->with('systemSettings', $systemSettings);
        }

        // ✅ Check other statuses safely
        if ($invitation->isAccepted()) {
            return redirect()->route('agent.invitations.success')
                ->with('info', 'This invitation has already been accepted. Please login with your credentials.')
                ->with('systemSettings', $systemSettings);
        }

        if ($invitation->isRevoked()) {
            return redirect()->route('agent.invitations.invalid')
                ->with('error', 'This invitation has been revoked. Please contact the administrator.')
                ->with('invitation', $invitation)
                ->with('systemSettings', $systemSettings);
        }

        if (!$invitation->isActive()) {
            Log::warning('Invitation is not active', [
                'invitation_id' => $invitation->id,
                'status' => $rawStatus,
                'is_actually_expired' => $isActuallyExpired,
                'is_corrupted' => $isCorrupted
            ]);
            
            return redirect()->route('agent.invitations.invalid')
                ->with('error', 'This invitation is no longer valid.')
                ->with('invitation', $invitation)
                ->with('systemSettings', $systemSettings);
        }

        // ✅ Only track view AFTER all checks pass and we're sure it's valid
        $invitation->trackView();

        Log::info('Invitation passed all checks, showing form', [
            'invitation_id' => $invitation->id,
            'status' => $invitation->status,
            'viewed_at' => $invitation->viewed_at,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'days_until_expiry' => $invitation->getDaysUntilExpiry(),
            'is_active' => $invitation->isActive(),
            'was_repaired' => $isCorrupted,
            'invitation_method' => $invitation->invitation_method,
            'system_name' => $systemName
        ]);

        // Show expiration warning
        $expirationWarning = null;
        if ($invitation->isExpiringSoon($this->invitationWarningDays)) {
            $days = $invitation->getDaysUntilExpiry();
            $expirationWarning = "This invitation expires in {$days} day" . ($days != 1 ? 's' : '') . ". Please accept it soon.";
        }

        // Show repair notification if invitation was just fixed
        $wasRepaired = $isCorrupted;

        // Get available verification channels based on agent's contact info
        $availableChannels = $this->getAvailableVerificationChannels($invitation->agent);

        return view('agent.invitations.accept', compact(
            'invitation', 
            'expirationWarning', 
            'wasRepaired',
            'availableChannels',
            'systemSettings',
            'systemName',
            'supportEmail'
        ));

    } catch (\Exception $e) {
        Log::error('Error accessing invitation form', [
            'token' => $token,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->route('agent.invitations.invalid')
            ->with('error', 'Invalid invitation link. Please contact the administrator.')
            ->with('systemSettings', SystemSetting::getSettings());
    }
}

    /**
     * ✅ NEW: Get available verification channels for agent
     */
    private function getAvailableVerificationChannels(User $agent)
    {
        $channels = [];

        if ($agent->phone) {
            $channels[] = 'sms';
            $channels[] = 'whatsapp';
        }

        if ($agent->email) {
            $channels[] = 'email';
        }

        return $channels;
    }

    /**
     * ✅ UPDATED: Process the invitation acceptance with multi-channel verification
     */
    public function processAcceptance(Request $request, $token)
    {
        try {
            $invitation = AgentInvitation::with(['plan', 'agent'])
                ->where('token', $token)
                ->firstOrFail();

            // ✅ Use enhanced safe expiration check
            $rawExpiresAt = $invitation->getRawOriginal('expires_at');
            $rawStatus = $invitation->getRawOriginal('status');
            $rawCreatedAt = $invitation->getRawOriginal('created_at');
            
            $isExpired = $rawExpiresAt && Carbon::parse($rawExpiresAt)->isPast();
            
            // ✅ CRITICAL: Detect corrupted invitations
            $isCorrupted = false;
            $hoursDifference = null;
            
            if ($rawCreatedAt && $rawExpiresAt) {
                $createdAt = Carbon::parse($rawCreatedAt);
                $expiresAt = Carbon::parse($rawExpiresAt);
                $hoursDifference = $createdAt->diffInHours($expiresAt);
                $isCorrupted = $hoursDifference < 24;
            }

            Log::info('Processing invitation acceptance - Enhanced check', [
                'invitation_id' => $invitation->id,
                'raw_status' => $rawStatus,
                'raw_expires_at' => $rawExpiresAt,
                'is_expired' => $isExpired,
                'is_corrupted' => $isCorrupted,
                'hours_difference' => $hoursDifference,
                'invitation_method' => $invitation->invitation_method
            ]);

            // ✅ CRITICAL FIX: Auto-repair corrupted invitations before validation
            if ($isCorrupted && $rawStatus === AgentInvitation::STATUS_SENT) {
                Log::warning('Auto-repairing corrupted invitation during acceptance', [
                    'invitation_id' => $invitation->id,
                    'current_expires_at' => $rawExpiresAt
                ]);
                
                if ($invitation->fixExpirationDate()) {
                    $invitation->refresh();
                    $isExpired = false; // Reset after repair
                    Log::info('Successfully repaired corrupted invitation during acceptance', [
                        'invitation_id' => $invitation->id,
                        'new_expires_at' => $invitation->getRawOriginal('expires_at')
                    ]);
                }
            }

            // ✅ Use safe status check after potential repair
            $safeStatus = $invitation->getSafeStatus();

            // Validate invitation status
            if ($isExpired || $safeStatus === AgentInvitation::STATUS_EXPIRED || $safeStatus === 'should_be_expired') {
                // Only auto-expire if it's actually expired (not just corrupted)
                if ($this->invitationAutoExpiry && $safeStatus === 'should_be_expired' && !$isCorrupted) {
                    $invitation->markAsExpired();
                }
                return redirect()->route('agent.invitations.expired')
                    ->with('error', 'This invitation has expired.')
                    ->with('invitation', $invitation);
            }

            if ($invitation->isAccepted()) {
                return redirect()->route('agent.invitations.success')
                    ->with('info', 'This invitation has already been accepted.');
            }

            if ($invitation->isRevoked()) {
                return redirect()->route('agent.invitations.invalid')
                    ->with('error', 'This invitation has been revoked.')
                    ->with('invitation', $invitation);
            }

            if (!$invitation->isActive()) {
                return redirect()->route('agent.invitations.invalid')
                    ->with('error', 'This invitation is no longer valid.')
                    ->with('invitation', $invitation);
            }

            // Validate form input with multi-channel verification support
            $validator = Validator::make($request->all(), [
                'password' => [
                    'required',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'
                ],
                'terms' => 'required|accepted',
                'verification_code' => 'sometimes|required|size:6',
                'verification_channel' => 'sometimes|required|in:sms,whatsapp,email'
            ], [
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Password confirmation does not match',
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character',
                'terms.required' => 'You must accept the terms and conditions',
                'verification_code.required' => 'Verification code is required',
                'verification_code.size' => 'Verification code must be 6 digits',
                'verification_channel.required' => 'Verification channel is required'
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Please fix the errors below.');
            }

            DB::beginTransaction();

            try {
                $agent = $invitation->agent;
                $plan = $invitation->plan;

                // Handle verification if required
                if ($request->has('verification_code')) {
                    $verificationResult = $this->verifyMultiChannelCode(
                        $agent, 
                        $request->verification_code, 
                        $request->verification_channel
                    );
                    
                    if (!$verificationResult['success']) {
                        return redirect()->back()
                            ->with('error', $verificationResult['message'])
                            ->withInput();
                    }
                }

                // Update agent account with secure password
                $passwordHash = Hash::make($request->password);
                
                $agentData = [
                    'password' => $passwordHash,
                    'status' => 'active',
                    'invitation_accepted_at' => now(),
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'preferred_communication_channel' => $request->verification_channel ?? 'sms'
                ];

                // Mark phone as verified if SMS/WhatsApp verification was used
                if (in_array($request->verification_channel ?? '', ['sms', 'whatsapp'])) {
                    $agentData['phone_verified_at'] = now();
                }

                $agent->update($agentData);

                // Mark invitation as accepted with details
                $invitation->markAsAccepted($request->ip(), $request->userAgent());

                // Update plan status to in_progress if it was assigned
                if ($plan && $plan->status === 'assigned') {
                    $plan->update(['status' => 'in_progress']);
                    
                    // ✅ UPDATED: Send multi-channel acceptance confirmation
                    $this->sendMultiChannelAcceptanceConfirmation($agent, $plan, $request->verification_channel ?? 'sms');
                }

                // Log the agent in automatically
                auth()->login($agent);

                DB::commit();

                // Log successful acceptance
                Log::info("Agent invitation accepted successfully", [
                    'invitation_id' => $invitation->id,
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'plan_id' => $plan->id ?? 'unknown',
                    'plan_zone' => $plan->zone ?? 'unknown',
                    'ip_address' => $request->ip(),
                    'accepted_at' => now()->toISOString(),
                    'invitation_duration_days' => $this->invitationExpiryDays,
                    'actual_days_taken' => $invitation->created_at->diffInDays(now()),
                    'auto_expiry_enabled' => $this->invitationAutoExpiry,
                    'was_repaired' => $isCorrupted,
                    'verification_channel' => $request->verification_channel ?? 'none',
                    'original_invitation_method' => $invitation->invitation_method
                ]);

                return redirect()->route('field-agent.dashboard')
                    ->with('success', 'Account setup completed successfully! Welcome to your Field Agent Dashboard.')
                    ->with('info', $isCorrupted ? 'Note: Your invitation expiration was automatically extended.' : null);

            } catch (\Exception $e) {
                DB::rollBack();
                
                Log::error("Failed to process agent invitation acceptance: " . $e->getMessage(), [
                    'token' => $token,
                    'invitation_id' => $invitation->id,
                    'agent_id' => $invitation->agent_id ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'expiry_days' => $this->invitationExpiryDays,
                    'auto_expiry_enabled' => $this->invitationAutoExpiry
                ]);

                return back()->with('error', 'Failed to complete account setup. Please try again or contact support.')
                    ->withInput();
            }

        } catch (\Exception $e) {
            Log::error('Error processing invitation acceptance', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('agent.invitations.invalid')
                ->with('error', 'Invalid invitation link. Please contact the administrator.');
        }
    }

    /**
     * ✅ NEW: Multi-channel verification code validation
     */
    private function verifyMultiChannelCode(User $agent, $code, $channel)
    {
        // Check if code matches
        if ($agent->phone_verification_code !== $code) {
            return [
                'success' => false,
                'message' => 'Invalid verification code'
            ];
        }

        // Check if code is not expired (10 minutes)
        if ($agent->phone_verification_sent_at && $agent->phone_verification_sent_at->addMinutes(10)->isPast()) {
            return [
                'success' => false,
                'message' => 'Verification code has expired'
            ];
        }

        // Clear verification code
        $agent->update([
            'phone_verification_code' => null,
            'phone_verification_sent_at' => null,
            'last_verification_channel' => $channel
        ]);

        Log::info("Multi-channel verification successful", [
            'agent_id' => $agent->id,
            'channel' => $channel,
            'verified_at' => now()->toISOString()
        ]);

        return [
            'success' => true,
            'message' => 'Verification successful'
        ];
    }

   /**
 * ✅ FIXED: Create invitation record with safe column handling
 */
private function createInvitationRecord($planId, $agentId, $invitationMethod = 'email', $existingToken = null)
{
    try {
        $token = $existingToken ?? Str::random(64);
        
        // Use safe creation method
        $invitation = AgentInvitation::createSafe([
            'plan_id' => $planId,
            'agent_id' => $agentId,
            'token' => $token,
            'invitation_method' => $invitationMethod,
            'expires_at' => now()->addDays($this->invitationExpiryDays),
            'status' => AgentInvitation::STATUS_SENT,
            'sent_at' => now(),
            // Only include sent_via if you've run the migration
            'sent_via' => $invitationMethod, // This will be ignored if column doesn't exist
        ]);

        Log::info("Invitation record created safely", [
            'invitation_id' => $invitation->id,
            'plan_id' => $planId,
            'agent_id' => $agentId,
            'token' => $token,
            'method' => $invitationMethod,
            'used_existing_token' => !is_null($existingToken),
        ]);

        return [
            'success' => true,
            'invitation' => $invitation,
            'token' => $token,
            'invitation_url' => route('agent.invitations.accept', ['token' => $token])
        ];

    } catch (\Exception $e) {
        Log::error("Failed to create invitation record: " . $e->getMessage(), [
            'plan_id' => $planId,
            'agent_id' => $agentId,
            'method' => $invitationMethod,
        ]);

        return [
            'success' => false,
            'message' => 'Failed to create invitation record: ' . $e->getMessage()
        ];
    }
}

    /**
     * ✅ FIXED: Send multi-channel invitation with SINGLE token creation
     */
    public function sendMultiChannelInvitation($plan, $invitationData, $agentId, $instructions = null)
    {
        $results = [];
        $method = $invitationData['invitation_method'];
        
        // Determine which channels to use
        $channels = [];
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
                $channels = ['sms', 'whatsapp', 'email'];
                break;
            default:
                $channels = ['sms'];
        }

        // ✅ FIX: Create ONE master token for all channels
        $masterToken = Str::random(64);
        $invitationRecord = null;

        foreach ($channels as $channel) {
            try {
                // ✅ FIX: Use the SAME token for all channels
                $result = $this->sendChannelSpecificInvitation(
                    $plan, 
                    $invitationData, 
                    $agentId, 
                    $channel, 
                    $instructions,
                    $invitationRecord, // Pass existing record
                    $masterToken,      // Use SAME token
                    $channel === 'email' // Only create record for first channel
                );
                $results[] = $result;
                
                // Store invitation record after first creation
                if ($result['success'] && !$invitationRecord && isset($result['invitation_id'])) {
                    $invitationRecord = AgentInvitation::find($result['invitation_id']);
                }
            } catch (\Exception $e) {
                Log::error("{$channel} invitation exception for agent {$agentId}: " . $e->getMessage());
                $results[] = [
                    'success' => false,
                    'method' => $method,
                    'channel' => $channel,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * ✅ FIXED: Send channel-specific invitation with SINGLE token
     */
    private function sendChannelSpecificInvitation($plan, $invitationData, $agentId, $channel, $instructions = null, $invitationRecord = null, $masterToken = null, $createRecord = true)
    {
        $agent = User::find($agentId);
        if (!$agent) {
            return [
                'success' => false,
                'method' => $invitationData['invitation_method'],
                'channel' => $channel,
                'message' => 'Agent not found'
            ];
        }

        // ✅ FIX: Create invitation record only once with master token
        if ($createRecord && (!$invitationRecord || !$masterToken)) {
            $masterToken = $masterToken ?? Str::random(64);
            $invitationResult = $this->createInvitationRecord($plan->id, $agentId, $channel, $masterToken);
            
            if (!$invitationResult['success']) {
                return [
                    'success' => false,
                    'method' => $invitationData['invitation_method'],
                    'channel' => $channel,
                    'message' => $invitationResult['message']
                ];
            }
            $invitationRecord = $invitationResult['invitation'];
        }

        switch ($channel) {
            case 'sms':
                return $this->sendSMSInvitation($plan, $invitationData, $agentId, $instructions, $invitationRecord, $masterToken);
                
            case 'whatsapp':
                return $this->sendWhatsAppInvitation($plan, $invitationData, $agentId, $instructions, $invitationRecord, $masterToken);
                
            case 'email':
                return $this->sendEmailInvitation($plan, $invitationData, $agentId, $instructions, $invitationRecord, $masterToken);
                
            default:
                return [
                    'success' => false,
                    'method' => $invitationData['invitation_method'],
                    'channel' => $channel,
                    'message' => 'Unknown channel'
                ];
        }
    }

    /**
     * ✅ FIXED: Send Email invitation with SINGLE token creation
     */
    private function sendEmailInvitation($plan, $invitationData, $agentId, $instructions = null, $invitationRecord = null, $token = null)
    {
        try {
            $agent = User::find($agentId);
            
            // ✅ FIX: Create ONE token and use it consistently
            if (!$invitationRecord || !$token) {
                // Generate token ONCE
                $token = Str::random(64);
                
                $invitationResult = $this->createInvitationRecord($plan->id, $agentId, 'email', $token);
                if (!$invitationResult['success']) {
                    return [
                        'success' => false,
                        'method' => $invitationData['invitation_method'],
                        'channel' => 'email',
                        'message' => $invitationResult['message']
                    ];
                }
                $invitationRecord = $invitationResult['invitation'];
                $token = $invitationResult['token']; // Use the SAME token
                
                Log::info("Email invitation - Token generated and stored", [
                    'invitation_id' => $invitationRecord->id,
                    'token' => $token,
                    'agent_id' => $agentId,
                    'plan_id' => $plan->id
                ]);
            }
            
            $invitationLink = route('agent.invitations.accept', ['token' => $token]);
            
            // ✅ VERIFY: Log token verification
            Log::info("Email invitation - Token verification", [
                'invitation_id' => $invitationRecord->id,
                'token_in_db' => $invitationRecord->token,
                'token_in_email' => $token,
                'tokens_match' => $invitationRecord->token === $token,
                'invitation_link' => $invitationLink
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
                'customMessage' => $instructions,
                'invitationLink' => $invitationLink,
                'expiryDate' => now()->addDays($this->invitationExpiryDays)->format('F j, Y'),
                'daysUntilExpiry' => $this->invitationExpiryDays,
                'invitationId' => $invitationRecord->id,
                'token' => $token // Include token in email data for debugging
            ];

            // Use queued email for better performance
            if (class_exists('App\Jobs\SendAgentInvitationEmail')) {
                \App\Jobs\SendAgentInvitationEmail::dispatch(
                    $plan->id,
                    $agentId,
                    $invitationData['invitation_method'],
                    $emailData,
                    $instructions,
                    $token // ✅ Pass the same token to job
                )->delay(now()->addSeconds(2));
                
                Log::info("Email invitation queued with consistent token", [
                    'invitation_id' => $invitationRecord->id,
                    'token' => $token,
                    'agent_email' => $agent->email
                ]);
                
                return [
                    'success' => true,
                    'method' => $invitationData['invitation_method'],
                    'channel' => 'email',
                    'message' => 'Email queued for delivery',
                    'invitation_id' => $invitationRecord->id,
                    'token' => $token // ✅ Return the same token
                ];
            } else {
                // Fallback to immediate email sending
                $emailResult = $this->sendImmediateEmailInvitation($agent, $emailData, $token);
                return [
                    'success' => $emailResult['success'],
                    'method' => $invitationData['invitation_method'],
                    'channel' => 'email',
                    'message' => $emailResult['message'],
                    'invitation_id' => $invitationRecord->id,
                    'token' => $token // ✅ Return the same token
                ];
            }

        } catch (\Exception $e) {
            Log::error("Email invitation failed for agent {$agentId}: " . $e->getMessage(), [
                'token_used' => $token ?? 'none',
                'invitation_id' => $invitationRecord->id ?? 'none'
            ]);
            return [
                'success' => false,
                'method' => $invitationData['invitation_method'],
                'channel' => 'email',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ FIXED: Send immediate email invitation with token verification
     */
    private function sendImmediateEmailInvitation(User $agent, $emailData, $expectedToken)
    {
        try {
            // ✅ VERIFY: Check token consistency before sending
            $invitationLinkToken = basename(parse_url($emailData['invitationLink'], PHP_URL_PATH));
            
            Log::info("Immediate email - Token verification before sending", [
                'expected_token' => $expectedToken,
                'link_token' => $invitationLinkToken,
                'tokens_match' => $expectedToken === $invitationLinkToken,
                'agent_email' => $agent->email
            ]);

            $this->emailService->sendAgentInvitation($agent->email, $emailData);

            Log::info("Immediate email invitation sent with verified token", [
                'agent_id' => $agent->id,
                'agent_email' => $agent->email,
                'invitation_link' => $emailData['invitationLink'],
                'token_used' => $expectedToken,
                'expiry_date' => $emailData['expiryDate']
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully with consistent token'
            ];

        } catch (\Exception $e) {
            Log::error("Immediate email invitation failed: " . $e->getMessage(), [
                'agent_id' => $agent->id,
                'agent_email' => $agent->email,
                'token_attempted' => $expectedToken
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ FIXED: Send SMS invitation with token creation
     */
    private function sendSMSInvitation($plan, $invitationData, $agentId, $instructions = null, $invitationRecord = null, $token = null)
    {
        try {
            $agent = User::find($agentId);
            
            // ✅ FIX: Ensure we have an invitation record for SMS too (for tracking)
            if (!$invitationRecord || !$token) {
                $token = $token ?? Str::random(64);
                $invitationResult = $this->createInvitationRecord($plan->id, $agentId, 'sms', $token);
                if (!$invitationResult['success']) {
                    return [
                        'success' => false,
                        'method' => $invitationData['invitation_method'],
                        'channel' => 'sms',
                        'message' => $invitationResult['message']
                    ];
                }
                $invitationRecord = $invitationResult['invitation'];
                $token = $invitationResult['token'];
            }
            
            $invitationLink = route('agent.invitations.accept', ['token' => $token]);
            
            $message = "Hello {$agent->name}!\n\n";
            $message .= "You have been assigned to a registration plan.\n";
            $message .= "Zone: {$plan->zone}\n";
            if ($plan->section) {
                $message .= "Section: {$plan->section}\n";
            }
            $message .= "Houses: {$plan->estimated_houses}\n";
            
            if ($plan->registration_start_date && $plan->registration_end_date) {
                $message .= "Period: {$plan->registration_start_date->format('M d')} - {$plan->registration_end_date->format('M d, Y')}\n";
            }
            
            if ($instructions) {
                $message .= "Instructions: {$instructions}\n";
            }
            
            $message .= "\nAccept invitation: {$invitationLink}\n";
            $message .= "Expires: " . now()->addDays($this->invitationExpiryDays)->format('M d, Y');

            $smsResult = $this->smsService->sendWithDefaultProvider(
                $agent->phone,
                $message,
                [
                    'is_test' => false,
                    'plan_id' => $plan->id,
                    'agent_id' => $agentId,
                    'channel' => 'sms',
                    'invitation_id' => $invitationRecord->id,
                    'token' => $token // Include token for tracking
                ]
            );

            return [
                'success' => $smsResult['success'],
                'method' => $invitationData['invitation_method'],
                'channel' => 'sms',
                'message' => $smsResult['message'] ?? ($smsResult['success'] ? 'SMS sent successfully' : 'SMS failed'),
                'invitation_id' => $invitationRecord->id,
                'token' => $token // Return token for consistency
            ];

        } catch (\Exception $e) {
            Log::error("SMS invitation failed for agent {$agentId}: " . $e->getMessage());
            return [
                'success' => false,
                'method' => $invitationData['invitation_method'],
                'channel' => 'sms',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ FIXED: Send WhatsApp invitation with token creation
     */
    private function sendWhatsAppInvitation($plan, $invitationData, $agentId, $instructions = null, $invitationRecord = null, $token = null)
    {
        try {
            $agent = User::find($agentId);
            
            // ✅ FIX: Ensure we have an invitation record for WhatsApp too
            if (!$invitationRecord || !$token) {
                $token = $token ?? Str::random(64);
                $invitationResult = $this->createInvitationRecord($plan->id, $agentId, 'whatsapp', $token);
                if (!$invitationResult['success']) {
                    return [
                        'success' => false,
                        'method' => $invitationData['invitation_method'],
                        'channel' => 'whatsapp',
                        'message' => $invitationResult['message']
                    ];
                }
                $invitationRecord = $invitationResult['invitation'];
                $token = $invitationResult['token'];
            }
            
            $invitationLink = route('agent.invitations.accept', ['token' => $token]);
            
            $message = "👋 *Hello {$agent->name}!*\n\n";
            $message .= "📋 *Registration Plan Assignment*\n\n";
            $message .= "📍 *Zone:* {$plan->zone}\n";
            if ($plan->section) {
                $message .= "🏘️ *Section:* {$plan->section}\n";
            }
            $message .= "🏠 *Estimated Houses:* {$plan->estimated_houses}\n";
            
            if ($plan->registration_start_date && $plan->registration_end_date) {
                $message .= "📅 *Period:* {$plan->registration_start_date->format('M d')} - {$plan->registration_end_date->format('M d, Y')}\n";
            }
            
            if ($instructions) {
                $message .= "\n📝 *Instructions:*\n{$instructions}\n";
            }
            
            $message .= "\n✅ *Accept Invitation:*\n{$invitationLink}\n";
            $message .= "⏰ *Expires:* " . now()->addDays($this->invitationExpiryDays)->format('M d, Y');

            $whatsappResult = $this->whatsappService->sendMessage(
                $agent->phone,
                $message,
                [
                    'plan_id' => $plan->id,
                    'agent_id' => $agentId,
                    'type' => 'invitation',
                    'invitation_id' => $invitationRecord->id,
                    'token' => $token // Include token for tracking
                ]
            );

            return [
                'success' => $whatsappResult['success'],
                'method' => $invitationData['invitation_method'],
                'channel' => 'whatsapp',
                'message' => $whatsappResult['message'] ?? ($whatsappResult['success'] ? 'WhatsApp message sent successfully' : 'WhatsApp failed'),
                'invitation_id' => $invitationRecord->id,
                'token' => $token // Return token for consistency
            ];

        } catch (\Exception $e) {
            Log::error("WhatsApp invitation failed for agent {$agentId}: " . $e->getMessage());
            return [
                'success' => false,
                'method' => $invitationData['invitation_method'],
                'channel' => 'whatsapp',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: API endpoint to send invitation via email with token creation
     */
    public function sendEmailInvitationApi(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:registration_plans,id',
            'agent_id' => 'required|exists:users,id',
            'instructions' => 'nullable|string',
            'custom_message' => 'nullable|string'
        ]);

        try {
            $plan = RegistrationPlan::find($validated['plan_id']);
            $agent = User::find($validated['agent_id']);

            // Check permissions
            if (!auth()->user()->isAdmin() && auth()->id() !== $plan->created_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.'
                ], 403);
            }

            $invitationData = [
                'invitation_method' => 'email',
                'agent_phone' => $agent->phone,
                'agent_name' => $agent->name,
                'agent_email' => $agent->email,
            ];

            $result = $this->sendEmailInvitation(
                $plan, 
                $invitationData, 
                $agent->id, 
                $validated['instructions'] ?? null
            );

            if ($result['success']) {
                Log::info("Email invitation sent via API", [
                    'plan_id' => $plan->id,
                    'agent_id' => $agent->id,
                    'invitation_id' => $result['invitation_id'] ?? 'unknown',
                    'token_used' => $result['token'] ?? 'unknown',
                    'sent_by' => auth()->id()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email invitation sent successfully',
                    'invitation_id' => $result['invitation_id'] ?? null,
                    'token' => $result['token'] ?? null,
                    'expiry_days' => $this->invitationExpiryDays
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send email invitation: ' . $result['message']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Failed to send email invitation via API: " . $e->getMessage(), [
                'plan_id' => $validated['plan_id'],
                'agent_id' => $validated['agent_id'],
                'sent_by' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Check if email invitation record exists
     */
    public function checkEmailInvitation($planId, $agentId)
    {
        try {
            $invitation = AgentInvitation::where('plan_id', $planId)
                ->where('agent_id', $agentId)
                ->where('invitation_method', 'email')
                ->first();

            if ($invitation) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'invitation' => [
                        'id' => $invitation->id,
                        'status' => $invitation->status,
                        'token' => $invitation->token,
                        'expires_at' => $invitation->expires_at?->toISOString(),
                        'created_at' => $invitation->created_at?->toISOString(),
                        'is_active' => $invitation->isActive(),
                        'is_expired' => $invitation->isExpired(),
                        'invitation_url' => $invitation->getInvitationUrl()
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'exists' => false,
                'message' => 'No email invitation found for this agent and plan'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to check email invitation: " . $e->getMessage(), [
                'plan_id' => $planId,
                'agent_id' => $agentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check email invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ UPDATED: Send multi-channel acceptance confirmation
     */
    private function sendMultiChannelAcceptanceConfirmation(User $agent, RegistrationPlan $plan, $preferredChannel = 'sms')
    {
        try {
            $message = "Hello {$agent->name}! Welcome to Property Registration System. " .
                      "You have been assigned to Zone: {$plan->zone}" .
                      ($plan->section ? ", Section: {$plan->section}" : "") .
                      ". Start your field work now. Download the field agent app from [link]";

            $result = $this->multiChannelService->sendMessage(
                $agent,
                $message,
                'welcome_message',
                [
                    'plan_id' => $plan->id,
                    'channel' => $preferredChannel,
                    'invitation_type' => 'acceptance_confirmation'
                ]
            );

            if ($result['success']) {
                Log::info("Welcome message sent via {$result['channel']}", [
                    'agent_id' => $agent->id,
                    'plan_id' => $plan->id,
                    'channel' => $result['channel'],
                    'provider' => $result['provider'] ?? 'unknown'
                ]);
            } else {
                Log::warning("Failed to send welcome message via {$preferredChannel}", [
                    'agent_id' => $agent->id,
                    'plan_id' => $plan->id,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::warning("Failed to send welcome message: " . $e->getMessage(), [
                'agent_id' => $agent->id,
                'plan_id' => $plan->id,
                'channel' => $preferredChannel
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ✅ NEW: Verify token consistency for debugging
     */
    public function verifyTokenConsistency($invitationId)
    {
        try {
            $invitation = AgentInvitation::findOrFail($invitationId);
            
            // Get the actual invitation URL
            $actualUrl = route('agent.invitations.accept', ['token' => $invitation->token]);
            
            // Check if any emails were sent with different tokens
            $emailLogs = DB::table('email_logs')
                ->where('related_id', $invitationId)
                ->where('type', 'agent_invitation')
                ->get();

            $tokenIssues = [];
            foreach ($emailLogs as $log) {
                if (strpos($log->content, 'token=') !== false) {
                    preg_match('/token=([a-zA-Z0-9]+)/', $log->content, $matches);
                    if (isset($matches[1]) && $matches[1] !== $invitation->token) {
                        $tokenIssues[] = [
                            'email_sent_at' => $log->created_at,
                            'email_token' => $matches[1],
                            'db_token' => $invitation->token,
                            'match' => false
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'invitation_id' => $invitationId,
                'current_token' => $invitation->token,
                'invitation_url' => $actualUrl,
                'token_issues_found' => count($tokenIssues),
                'token_issues' => $tokenIssues,
                'recommendation' => count($tokenIssues) > 0 ? 
                    'Found token mismatches. Use token repair tool.' : 
                    'All tokens are consistent.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Repair token inconsistencies (Admin only)
     */
    public function repairTokenInconsistencies($invitationId)
    {
        // Check admin permissions
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $invitation = AgentInvitation::findOrFail($invitationId);
            $originalToken = $invitation->token;

            // Get email logs to find what token was actually sent
            $emailLogs = DB::table('email_logs')
                ->where('related_id', $invitationId)
                ->where('type', 'agent_invitation')
                ->orderBy('created_at', 'desc')
                ->first();

            $emailToken = null;
            if ($emailLogs && strpos($emailLogs->content, 'token=') !== false) {
                preg_match('/token=([a-zA-Z0-9]+)/', $emailLogs->content, $matches);
                $emailToken = $matches[1] ?? null;
            }

            // If email token is different, update database to match
            if ($emailToken && $emailToken !== $originalToken) {
                $invitation->update(['token' => $emailToken]);
                
                Log::info("Token inconsistency repaired", [
                    'invitation_id' => $invitationId,
                    'original_token' => $originalToken,
                    'email_token' => $emailToken,
                    'new_token' => $emailToken,
                    'repaired_by' => auth()->id(),
                    'repaired_at' => now()->toISOString()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Token inconsistency repaired successfully',
                    'details' => [
                        'original_token' => $originalToken,
                        'email_token_found' => $emailToken,
                        'database_updated' => true,
                        'new_invitation_url' => route('agent.invitations.accept', ['token' => $emailToken])
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No token inconsistencies found',
                'details' => [
                    'current_token' => $originalToken,
                    'email_token_found' => $emailToken,
                    'tokens_match' => $emailToken === $originalToken
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Token repair failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Token repair failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show expired invitation page
     */
   /**
 * Show expired invitation page with system branding
 */
public function expired()
{
    $invitation = session('invitation');
    $systemSettings = SystemSetting::getSettings();
    $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');
    $supportEmail = $systemSettings->system_email ?? 'support@example.com';
    
    Log::info("Expired invitation page accessed", [
        'has_invitation' => !is_null($invitation),
        'invitation_id' => $invitation?->id,
        'expiry_days' => $this->invitationExpiryDays,
        'auto_expiry_enabled' => $this->invitationAutoExpiry,
        'system_name' => $systemName
    ]);

    return view('agent.invitations.expired', [
        'invitation' => $invitation,
        'expiry_days' => $this->invitationExpiryDays,
        'auto_expiry_enabled' => $this->invitationAutoExpiry,
        'systemSettings' => $systemSettings,
        'systemName' => $systemName,
        'supportEmail' => $supportEmail
    ]);
}

/**
 * Show invalid invitation page with system branding
 */
public function invalid()
{
    $invitation = session('invitation');
    $systemSettings = SystemSetting::getSettings();
    $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');
    $supportEmail = $systemSettings->system_email ?? 'support@example.com';
    
    Log::info("Invalid invitation page accessed", [
        'has_invitation' => !is_null($invitation),
        'invitation_id' => $invitation?->id,
        'status' => $invitation?->status,
        'expiry_days' => $this->invitationExpiryDays,
        'system_name' => $systemName
    ]);

    return view('agent.invitations.invalid', [
        'invitation' => $invitation,
        'expiry_days' => $this->invitationExpiryDays,
        'systemSettings' => $systemSettings,
        'systemName' => $systemName,
        'supportEmail' => $supportEmail
    ]);
}

/**
 * Show success page after acceptance with system branding
 */
public function success()
{
    $systemSettings = SystemSetting::getSettings();
    $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');
    $supportEmail = $systemSettings->system_email ?? 'support@example.com';
    
    Log::info("Invitation success page accessed", [
        'expiry_days' => $this->invitationExpiryDays,
        'auto_expiry_enabled' => $this->invitationAutoExpiry,
        'system_name' => $systemName
    ]);

    return view('agent.invitations.success', [
        'expiry_days' => $this->invitationExpiryDays,
        'auto_expiry_enabled' => $this->invitationAutoExpiry,
        'systemSettings' => $systemSettings,
        'systemName' => $systemName,
        'supportEmail' => $supportEmail
    ]);
}

/**
 * View invitation details with system branding
 */
public function view($token)
{
    $invitation = AgentInvitation::with(['plan', 'agent'])
        ->where('token', $token)
        ->firstOrFail();
    
    $systemSettings = SystemSetting::getSettings();
    $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');

    return view('agent.invitations.view', compact('invitation', 'systemSettings', 'systemName'));
}

    /**
     * ✅ UPDATED: Resend invitation to agent with multi-channel support
     */
    public function resendInvitation(Request $request, $invitationId)
    {
        $validated = $request->validate([
            'preferred_channel' => 'sometimes|in:sms,whatsapp,email,both'
        ]);

        $invitation = AgentInvitation::with(['plan', 'agent'])->findOrFail($invitationId);

        // Check permissions (admin or plan creator)
        if (!auth()->user()->isAdmin() && auth()->id() !== $invitation->plan->created_by) {
            abort(403, 'Unauthorized action.');
        }

        if ($invitation->isAccepted()) {
            return back()->with('warning', 'This invitation has already been accepted.');
        }

        // Use the service to resend invitation with preferred channel
        try {
            $preferredChannel = $validated['preferred_channel'] ?? $invitation->invitation_method;
            
            $result = $this->invitationService->resendInvitation($invitation->id, $preferredChannel);
            
            if ($result['success']) {
                $methodText = is_array($result['methods']) ? 
                    implode(', ', $result['methods']) : 
                    ($result['method'] ?? 'SMS');
                    
                return back()->with('success', "Invitation resent successfully via {$methodText}");
            } else {
                return back()->with('error', 'Failed to resend invitation: ' . ($result['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error("Failed to resend invitation: " . $e->getMessage(), [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
                'expiry_days' => $this->invitationExpiryDays,
                'resend_extends_expiry' => $this->invitationResendExtendsExpiry
            ]);
            return back()->with('error', 'Failed to resend invitation: ' . $e->getMessage());
        }
    }

    /**
     * Revoke an invitation
     */
    public function revokeInvitation(Request $request, $invitationId)
    {
        $invitation = AgentInvitation::with(['plan', 'agent'])->findOrFail($invitationId);

        // Check permissions
        if (!auth()->user()->isAdmin() && auth()->id() !== $invitation->plan->created_by) {
            abort(403, 'Unauthorized action.');
        }

        if ($invitation->isAccepted()) {
            return back()->with('warning', 'Cannot revoke an accepted invitation.');
        }

        DB::beginTransaction();

        try {
            // Use model method for consistency
            $invitation->markAsRevoked(auth()->id());

            // If this was the only active invitation, set plan back to draft
            $plan = $invitation->plan;
            $activeInvitations = $plan->invitations()->active()->count();

            if ($activeInvitations === 0 && $plan->status === 'assigned') {
                $plan->update([
                    'status' => 'draft',
                    'assigned_agent_id' => null
                ]);
            }

            DB::commit();

            Log::info("Agent invitation revoked", [
                'invitation_id' => $invitation->id,
                'plan_id' => $plan->id,
                'revoked_by' => auth()->id(),
                'revoked_at' => now()->toISOString(),
                'expiry_days' => $this->invitationExpiryDays,
                'auto_expiry_enabled' => $this->invitationAutoExpiry,
                'original_invitation_method' => $invitation->invitation_method
            ]);

            return back()->with('success', 'Invitation revoked successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Failed to revoke invitation: " . $e->getMessage(), [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
                'expiry_days' => $this->invitationExpiryDays
            ]);
            return back()->with('error', 'Failed to revoke invitation.');
        }
    }

    /**
     * ✅ UPDATED: Send multi-channel verification code
     */
    public function sendVerificationCode(Request $request, $token)
    {
        $validated = $request->validate([
            'preferred_channel' => 'required|in:sms,whatsapp,email'
        ]);

        $invitation = AgentInvitation::with(['agent'])->where('token', $token)->firstOrFail();

        // Use safe status check
        $safeStatus = $invitation->getSafeStatus();
        if (!in_array($safeStatus, [AgentInvitation::STATUS_SENT, 'should_be_expired'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invitation status'
            ], 400);
        }

        $agent = $invitation->agent;
        $preferredChannel = $validated['preferred_channel'];

        // Validate channel availability
        if ($preferredChannel === 'email' && !$agent->email) {
            return response()->json([
                'success' => false,
                'message' => 'No email address available for email verification'
            ], 400);
        }

        if (in_array($preferredChannel, ['sms', 'whatsapp']) && !$agent->phone) {
            return response()->json([
                'success' => false,
                'message' => 'No phone number available for SMS/WhatsApp verification'
            ], 400);
        }

        try {
            // Generate verification code (6 digits)
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store verification code
            $agent->update([
                'phone_verification_code' => $verificationCode,
                'phone_verification_sent_at' => now(),
                'preferred_verification_channel' => $preferredChannel
            ]);

            // Send verification code via multi-channel service
            $message = "Your verification code is: {$verificationCode}. Valid for 10 minutes.";
            
            $result = $this->multiChannelService->sendMessage(
                $agent,
                $message,
                'verification_code',
                [
                    'invitation_id' => $invitation->id,
                    'channel' => $preferredChannel,
                    'verification_code' => $verificationCode
                ]
            );

            if ($result['success']) {
                Log::info("Verification code sent via {$result['channel']}", [
                    'invitation_id' => $invitation->id,
                    'agent_id' => $agent->id,
                    'channel' => $result['channel'],
                    'provider' => $result['provider'] ?? 'unknown',
                    'expiry_days' => $this->invitationExpiryDays
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Verification code sent via {$result['channel']}",
                    'channel' => $result['channel'],
                    'provider' => $result['provider'] ?? 'unknown'
                ]);
            } else {
                Log::error("Failed to send verification code via {$preferredChannel}", [
                    'invitation_id' => $invitation->id,
                    'agent_id' => $agent->id,
                    'error' => $result['message'] ?? 'Unknown error',
                    'expiry_days' => $this->invitationExpiryDays
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Failed to send verification code: " . $e->getMessage(), [
                'invitation_id' => $invitation->id,
                'agent_id' => $agent->id,
                'channel' => $preferredChannel,
                'error' => $e->getMessage(),
                'expiry_days' => $this->invitationExpiryDays
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.'
            ], 500);
        }
    }

    /**
     * Show invitation statistics for a plan
     */
    public function invitationStatistics($planId)
    {
        $plan = RegistrationPlan::with(['invitations.agent'])->findOrFail($planId);

        // Check permissions
        if (!auth()->user()->isAdmin() && auth()->id() !== $plan->created_by) {
            abort(403, 'Unauthorized action.');
        }

        $statistics = [
            'total' => $plan->invitations->count(),
            'sent' => $plan->invitations->where('status', AgentInvitation::STATUS_SENT)->count(),
            'accepted' => $plan->invitations->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
            'expired' => $plan->invitations->where('status', AgentInvitation::STATUS_EXPIRED)->count(),
            'revoked' => $plan->invitations->where('status', AgentInvitation::STATUS_REVOKED)->count(),
            'failed' => $plan->invitations->where('status', AgentInvitation::STATUS_FAILED)->count(),
            'active' => $plan->invitations()->active()->count(),
            'expiring_soon' => $plan->invitations()->expiringSoon($this->invitationWarningDays)->count(),
            'expiry_days' => $this->invitationExpiryDays,
            'warning_days' => $this->invitationWarningDays,
            'auto_expiry_enabled' => $this->invitationAutoExpiry,
            'by_channel' => $plan->invitations->groupBy('invitation_method')->map->count()
        ];

        $recentInvitations = $plan->invitations()
            ->with('agent')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('admin.registration-plans.invitation-statistics', compact(
            'plan', 'statistics', 'recentInvitations'
        ));
    }

    /**
     * ✅ UPDATED: Check for expired invitations with channel analytics
     */
    public function checkExpiredInvitations()
    {
        // Check if auto-expiry is enabled
        if (!$this->invitationAutoExpiry) {
            return response()->json([
                'success' => true,
                'message' => 'Auto-expiry is disabled, skipping expiration check',
                'auto_expiry_enabled' => false,
                'expiry_days' => $this->invitationExpiryDays
            ]);
        }

        $expiredCount = AgentInvitation::where('status', AgentInvitation::STATUS_SENT)
            ->where('expires_at', '<', now())
            ->count();

        $expiredInvitations = AgentInvitation::where('status', AgentInvitation::STATUS_SENT)
            ->where('expires_at', '<', now())
            ->get();

        $processedCount = 0;
        $channelBreakdown = [];

        foreach ($expiredInvitations as $invitation) {
            if ($invitation->markAsExpired()) {
                $processedCount++;
                $channel = $invitation->invitation_method;
                $channelBreakdown[$channel] = ($channelBreakdown[$channel] ?? 0) + 1;
                
                Log::info("Invitation auto-expired", [
                    'invitation_id' => $invitation->id,
                    'plan_id' => $invitation->plan_id,
                    'agent_id' => $invitation->agent_id,
                    'expired_at' => now()->toISOString(),
                    'expiry_days' => $this->invitationExpiryDays,
                    'auto_expiry_enabled' => $this->invitationAutoExpiry,
                    'invitation_method' => $invitation->invitation_method
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Processed {$processedCount} expired invitations",
            'total_expired' => $expiredCount,
            'processed' => $processedCount,
            'channel_breakdown' => $channelBreakdown,
            'expiry_days' => $this->invitationExpiryDays,
            'auto_expiry_enabled' => $this->invitationAutoExpiry
        ]);
    }

    /**
     * ✅ UPDATED: Send multi-channel expiration warnings
     */
    public function sendExpirationWarnings()
    {
        $expiringSoon = AgentInvitation::expiringSoon($this->invitationWarningDays)->get();
        $sentCount = 0;
        $channelResults = [];

        foreach ($expiringSoon as $invitation) {
            $result = $this->sendMultiChannelExpirationWarning($invitation);
            if ($result['success']) {
                $sentCount++;
                $channel = $result['channel'];
                $channelResults[$channel] = ($channelResults[$channel] ?? 0) + 1;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sent {$sentCount} expiration warnings",
            'total_expiring' => $expiringSoon->count(),
            'warnings_sent' => $sentCount,
            'channel_results' => $channelResults,
            'expiry_days' => $this->invitationExpiryDays,
            'warning_days' => $this->invitationWarningDays
        ]);
    }

    /**
     * ✅ NEW: Send multi-channel expiration warning
     */
    private function sendMultiChannelExpirationWarning(AgentInvitation $invitation)
    {
        try {
            $daysLeft = $invitation->getDaysUntilExpiry();
            $agent = $invitation->agent;
            $plan = $invitation->plan;
            $preferredChannel = $invitation->invitation_method;

            $message = "Reminder: Your invitation for {$plan->zone} expires in {$daysLeft} day(s). " .
                      "Accept it now: " . $invitation->getInvitationUrl();

            $result = $this->multiChannelService->sendMessage(
                $agent,
                $message,
                'expiration_warning',
                [
                    'invitation_id' => $invitation->id,
                    'days_until_expiry' => $daysLeft,
                    'channel' => $preferredChannel,
                    'total_expiry_days' => $this->invitationExpiryDays,
                    'warning_days' => $this->invitationWarningDays
                ]
            );

            if ($result['success']) {
                Log::info("Expiration warning sent via {$result['channel']}", [
                    'invitation_id' => $invitation->id,
                    'agent_id' => $agent->id,
                    'days_until_expiry' => $daysLeft,
                    'channel' => $result['channel'],
                    'provider' => $result['provider'] ?? 'unknown',
                    'expiry_days' => $this->invitationExpiryDays,
                    'warning_days' => $this->invitationWarningDays
                ]);
            }

            return array_merge($result, ['channel' => $result['channel'] ?? $preferredChannel]);

        } catch (\Exception $e) {
            Log::warning("Failed to send expiration warning: " . $e->getMessage(), [
                'invitation_id' => $invitation->id,
                'agent_id' => $invitation->agent_id,
                'channel' => $preferredChannel,
                'expiry_days' => $this->invitationExpiryDays,
                'warning_days' => $this->invitationWarningDays
            ]);
            return ['success' => false, 'message' => $e->getMessage(), 'channel' => $preferredChannel];
        }
    }

    /**
     * ✅ UPDATED: Preview invitation message with multi-channel support
     */
    public function previewInvitation(Request $request, $planId)
    {
        $plan = RegistrationPlan::with('assignedAgent')->findOrFail($planId);

        // Check permissions
        if (!auth()->user()->isAdmin() && auth()->id() !== $plan->created_by) {
            abort(403, 'Unauthorized action.');
        }

        $invitationMethod = $request->get('method', 'sms');
        $customMessage = $request->get('message');

        $preview = $this->invitationService->generateInvitationPreview(
            $plan,
            $invitationMethod,
            $customMessage
        );

        return response()->json([
            'success' => true,
            'preview' => $preview,
            'method' => $invitationMethod,
            'channel_specific' => $this->getChannelSpecificPreview($invitationMethod, $preview),
            'expiry_days' => $this->invitationExpiryDays,
            'warning_days' => $this->invitationWarningDays
        ]);
    }

    /**
     * ✅ NEW: Get channel-specific preview formatting
     */
    private function getChannelSpecificPreview($channel, $basePreview)
    {
        switch ($channel) {
            case 'whatsapp':
                return [
                    'format' => 'WhatsApp Business Template',
                    'max_length' => 4096,
                    'supports_buttons' => true,
                    'supports_media' => true
                ];
            case 'email':
                return [
                    'format' => 'HTML Email Template',
                    'max_length' => 'Unlimited',
                    'supports_attachments' => true,
                    'template_variables' => ['agent_name', 'plan_zone', 'invitation_url', 'expiry_date']
                ];
            case 'sms':
                return [
                    'format' => 'Plain Text SMS',
                    'max_length' => 160,
                    'supports_unicode' => true,
                    'character_count' => strlen($basePreview)
                ];
            case 'both':
                return [
                    'sms' => $this->getChannelSpecificPreview('sms', $basePreview),
                    'email' => $this->getChannelSpecificPreview('email', $basePreview)
                ];
            default:
                return ['format' => 'Unknown channel'];
        }
    }

    /**
     * ✅ UPDATED: Get invitation status with channel information
     */
    public function getInvitationStatus($invitationId)
    {
        $invitation = AgentInvitation::with(['agent', 'plan'])->findOrFail($invitationId);

        // Check permissions
        if (!auth()->user()->isAdmin() && auth()->id() !== $invitation->plan->created_by) {
            abort(403, 'Unauthorized action.');
        }

        return response()->json([
            'success' => true,
            'invitation' => [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'status_text' => $invitation->getStatusText(),
                'expires_at' => $invitation->expires_at?->toISOString(),
                'accepted_at' => $invitation->accepted_at?->toISOString(),
                'viewed_at' => $invitation->viewed_at?->toISOString(),
                'is_expired' => $invitation->isExpired(),
                'is_accepted' => $invitation->isAccepted(),
                'is_active' => $invitation->isActive(),
                'days_until_expiry' => $invitation->getDaysUntilExpiry(),
                'resend_count' => $invitation->getResendCount(),
                'invitation_method' => $invitation->invitation_method,
                'agent' => [
                    'name' => $invitation->agent->name,
                    'phone' => $invitation->agent->phone,
                    'email' => $invitation->agent->email,
                    'status' => $invitation->agent->status,
                    'preferred_communication_channel' => $invitation->agent->preferred_communication_channel
                ],
                'invitation_url' => $invitation->getInvitationUrl(),
                'expiry_days' => $this->invitationExpiryDays,
                'warning_days' => $this->invitationWarningDays
            ]
        ]);
    }

    /**
     * ✅ UPDATED: Bulk resend invitations with channel selection
     */
    public function bulkResendInvitations(Request $request)
    {
        $request->validate([
            'invitation_ids' => 'required|array',
            'invitation_ids.*' => 'exists:agent_invitations,id',
            'preferred_channel' => 'sometimes|in:sms,whatsapp,email,both'
        ]);

        $preferredChannel = $request->get('preferred_channel');
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => [],
            'channel_breakdown' => []
        ];

        foreach ($request->invitation_ids as $invitationId) {
            $invitation = AgentInvitation::find($invitationId);

            // Check permissions for each invitation
            if (!auth()->user()->isAdmin() && auth()->id() !== $invitation->plan->created_by) {
                $results['failed']++;
                $results['details'][] = [
                    'id' => $invitationId,
                    'success' => false,
                    'message' => 'Unauthorized'
                ];
                continue;
            }

            if ($invitation->isAccepted()) {
                $results['failed']++;
                $results['details'][] = [
                    'id' => $invitationId,
                    'success' => false,
                    'message' => 'Invitation already accepted'
                ];
                continue;
            }

            try {
                $channel = $preferredChannel ?? $invitation->invitation_method;
                $result = $this->invitationService->resendInvitation($invitation->id, $channel);

                if ($result['success']) {
                    $results['success']++;
                    $methods = is_array($result['methods']) ? $result['methods'] : [$result['method'] ?? 'SMS'];
                    
                    foreach ($methods as $method) {
                        $results['channel_breakdown'][$method] = ($results['channel_breakdown'][$method] ?? 0) + 1;
                    }
                    
                    $results['details'][] = [
                        'id' => $invitationId,
                        'success' => true,
                        'message' => 'Resent via ' . implode(', ', $methods),
                        'channels' => $methods,
                        'expiry_days' => $this->invitationExpiryDays
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $invitationId,
                        'success' => false,
                        'message' => $result['message'] ?? 'Unknown error',
                        'expiry_days' => $this->invitationExpiryDays
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'id' => $invitationId,
                    'success' => false,
                    'message' => $e->getMessage(),
                    'expiry_days' => $this->invitationExpiryDays
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'message' => "Resent {$results['success']} invitations successfully, {$results['failed']} failed",
            'channel_breakdown' => $results['channel_breakdown'],
            'expiry_days' => $this->invitationExpiryDays,
            'resend_extends_expiry' => $this->invitationResendExtendsExpiry
        ]);
    }

    /**
 * Direct expired invitation access with system branding
 */
public function showExpiredInvitation($token)
{
    $invitation = AgentInvitation::with(['plan', 'agent'])
        ->where('token', $token)
        ->firstOrFail();

    // Use safe status check
    $safeStatus = $invitation->getSafeStatus();
    
    // Only allow access if invitation is actually expired
    if (!in_array($safeStatus, [AgentInvitation::STATUS_EXPIRED, 'should_be_expired'])) {
        return redirect()->route('agent.invitations.accept', $token);
    }

    $systemSettings = SystemSetting::getSettings();
    $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');
    $supportEmail = $systemSettings->system_email ?? 'support@example.com';

    Log::info("Direct access to expired invitation", [
        'invitation_id' => $invitation->id,
        'token' => $token,
        'safe_status' => $safeStatus,
        'expiry_days' => $this->invitationExpiryDays,
        'auto_expiry_enabled' => $this->invitationAutoExpiry,
        'invitation_method' => $invitation->invitation_method,
        'system_name' => $systemName
    ]);

    return view('agent.invitations.expired', [
        'invitation' => $invitation,
        'expiry_days' => $this->invitationExpiryDays,
        'auto_expiry_enabled' => $this->invitationAutoExpiry,
        'systemSettings' => $systemSettings,
        'systemName' => $systemName,
        'supportEmail' => $supportEmail
    ]);
}

    /**
     * Get invitation expiry configuration
     */
    public function getExpiryConfiguration()
    {
        return response()->json([
            'success' => true,
            'expiry_configuration' => [
                'expiry_days' => $this->invitationExpiryDays,
                'warning_days' => $this->invitationWarningDays,
                'auto_expiry_enabled' => $this->invitationAutoExpiry,
                'resend_extends_expiry' => $this->invitationResendExtendsExpiry,
                'timezone' => config('app.timezone', 'UTC'),
                'supported_channels' => ['sms', 'whatsapp', 'email', 'both']
            ]
        ]);
    }

    /**
     * Update invitation expiry configuration (admin only)
     */
    public function updateExpiryConfiguration(Request $request)
    {
        // Check admin permissions
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'expiry_days' => 'required|integer|min:1|max:30',
            'warning_days' => 'required|integer|min:1|max:7',
            'auto_expiry_enabled' => 'required|boolean',
            'resend_extends_expiry' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid configuration data',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->invitationService->updateExpiryConfiguration($request->all());

            if ($result['success']) {
                // Update local configuration for current request
                $this->invitationExpiryDays = $request->expiry_days;
                $this->invitationWarningDays = $request->warning_days;
                $this->invitationAutoExpiry = $request->auto_expiry_enabled;
                $this->invitationResendExtendsExpiry = $request->resend_extends_expiry;

                Log::info("Invitation expiry configuration updated by admin", [
                    'admin_id' => auth()->id(),
                    'new_configuration' => $result['configuration']
                ]);

                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            Log::error("Failed to update expiry configuration: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fix expiration issues for all invitations (admin only)
     */
    public function fixExpirationIssues()
    {
        // Check admin permissions
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $result = $this->invitationService->fixExpirationIssues();

            Log::info("Expiration issues fix attempted", [
                'admin_id' => auth()->id(),
                'result' => $result
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("Failed to fix expiration issues: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fix expiration issues: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expiration analysis with channel analytics (admin only)
     */
    public function getExpirationAnalysis($planId = null)
    {
        // Check admin permissions
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $analysis = $this->invitationService->getExpirationAnalysis($planId);

            // Add channel-specific analytics
            $analysis['channel_performance'] = $this->getChannelPerformanceAnalytics($planId);

            return response()->json([
                'success' => true,
                'analysis' => $analysis
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get expiration analysis: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get expiration analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Get channel performance analytics
     */
    private function getChannelPerformanceAnalytics($planId = null)
    {
        $query = AgentInvitation::query();
        
        if ($planId) {
            $query->where('plan_id', $planId);
        }

        $invitations = $query->get();
        
        $channelStats = [];
        $channels = ['sms', 'whatsapp', 'email', 'both'];

        foreach ($channels as $channel) {
            $channelInvitations = $invitations->where('invitation_method', $channel);
            $total = $channelInvitations->count();
            
            if ($total > 0) {
                $channelStats[$channel] = [
                    'total' => $total,
                    'accepted' => $channelInvitations->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
                    'expired' => $channelInvitations->where('status', AgentInvitation::STATUS_EXPIRED)->count(),
                    'completion_rate' => round(($channelInvitations->where('status', AgentInvitation::STATUS_ACCEPTED)->count() / $total) * 100, 2),
                    'average_acceptance_time_hours' => $this->calculateAverageAcceptanceTime($channelInvitations)
                ];
            }
        }

        return $channelStats;
    }

    /**
     * ✅ NEW: Calculate average acceptance time for a channel
     */
    private function calculateAverageAcceptanceTime($invitations)
    {
        $acceptedInvitations = $invitations->where('status', AgentInvitation::STATUS_ACCEPTED)
            ->whereNotNull('accepted_at')
            ->whereNotNull('created_at');

        if ($acceptedInvitations->isEmpty()) {
            return null;
        }

        $totalHours = 0;
        foreach ($acceptedInvitations as $invitation) {
            $totalHours += $invitation->created_at->diffInHours($invitation->accepted_at);
        }

        return round($totalHours / $acceptedInvitations->count(), 2);
    }

    /**
     * Debug method to check invitation status
     */
    public function debugInvitation($token)
    {
        $invitation = AgentInvitation::with(['plan', 'agent'])
            ->where('token', $token)
            ->firstOrFail();

        return response()->json([
            'debug_info' => $invitation->debugTimezoneIssues(),
            'safe_status' => $invitation->getSafeStatus(),
            'is_expired' => $invitation->isExpired(),
            'check_if_expired' => $invitation->checkIfExpired(),
            'is_active' => $invitation->isActive(),
            'analytics_data' => $invitation->getAnalyticsData(),
            'expiration_health' => $invitation->getExpirationHealth(),
            'channel_info' => [
                'invitation_method' => $invitation->invitation_method,
                'agent_communication_preference' => $invitation->agent->preferred_communication_channel ?? 'not_set',
                'agent_has_phone' => !empty($invitation->agent->phone),
                'agent_has_email' => !empty($invitation->agent->email)
            ],
            'config' => [
                'expiry_days' => $this->invitationExpiryDays,
                'warning_days' => $this->invitationWarningDays,
                'auto_expiry_enabled' => $this->invitationAutoExpiry
            ]
        ]);
    }

    /**
     * Emergency fix for all corrupted invitations (admin only)
     */
    public function emergencyFixCorruptedInvitations()
    {
        // Check admin permissions
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $corruptedInvitations = AgentInvitation::where('status', 'sent')
                ->get()
                ->filter(function ($invitation) {
                    $rawExpiresAt = $invitation->getRawOriginal('expires_at');
                    $rawCreatedAt = $invitation->getRawOriginal('created_at');
                    
                    if (!$rawExpiresAt || !$rawCreatedAt) {
                        return false;
                    }
                    
                    $createdAt = Carbon::parse($rawCreatedAt);
                    $expiresAt = Carbon::parse($rawExpiresAt);
                    $hoursDifference = $createdAt->diffInHours($expiresAt);
                    
                    return $hoursDifference < 24;
                });

            $fixedCount = 0;
            $channelBreakdown = [];
            
            foreach ($corruptedInvitations as $invitation) {
                if ($invitation->fixExpirationDate()) {
                    $fixedCount++;
                    $channel = $invitation->invitation_method;
                    $channelBreakdown[$channel] = ($channelBreakdown[$channel] ?? 0) + 1;
                    
                    Log::info('Emergency fixed corrupted invitation', [
                        'invitation_id' => $invitation->id,
                        'old_expires_at' => $invitation->getRawOriginal('expires_at'),
                        'new_expires_at' => $invitation->expires_at?->toISOString(),
                        'channel' => $channel
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Emergency fix completed. Fixed {$fixedCount} corrupted invitations.",
                'fixed_count' => $fixedCount,
                'total_corrupted' => $corruptedInvitations->count(),
                'channel_breakdown' => $channelBreakdown,
                'details' => [
                    'criteria' => 'Invitations with expiration less than 24 hours from creation',
                    'expiry_days_applied' => $this->invitationExpiryDays
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to run emergency fix: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to run emergency fix: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ UPDATED: Get invitation health report with channel analytics
     */
    public function getInvitationHealthReport($planId = null)
    {
        // Check admin permissions
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $query = AgentInvitation::with(['plan', 'agent']);
            
            if ($planId) {
                $query->where('plan_id', $planId);
            }

            $invitations = $query->get();
            
            $healthReport = [
                'total_invitations' => $invitations->count(),
                'by_status' => $invitations->groupBy('status')->map->count(),
                'by_channel' => $invitations->groupBy('invitation_method')->map->count(),
                'corrupted_count' => $invitations->filter(function ($invitation) {
                    $rawExpiresAt = $invitation->getRawOriginal('expires_at');
                    $rawCreatedAt = $invitation->getRawOriginal('created_at');
                    
                    if (!$rawExpiresAt || !$rawCreatedAt) return false;
                    
                    $createdAt = Carbon::parse($rawCreatedAt);
                    $expiresAt = Carbon::parse($rawExpiresAt);
                    return $createdAt->diffInHours($expiresAt) < 24;
                })->count(),
                'expiration_health' => $invitations->groupBy(function ($invitation) {
                    return $invitation->getExpirationHealth();
                })->map->count(),
                'channel_performance' => $this->getChannelPerformanceAnalytics($planId),
                'config' => [
                    'expiry_days' => $this->invitationExpiryDays,
                    'warning_days' => $this->invitationWarningDays,
                    'auto_expiry_enabled' => $this->invitationAutoExpiry
                ]
            ];

            return response()->json([
                'success' => true,
                'health_report' => $healthReport
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get invitation health report: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get health report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Test multi-channel messaging (admin only)
     */
    public function testMultiChannelMessaging(Request $request)
    {
        // Check admin permissions
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'channels' => 'required|array',
            'channels.*' => 'in:sms,whatsapp,email',
            'message' => 'required|string|max:500'
        ]);

        try {
            $agent = User::find($validated['agent_id']);
            $results = [];

            foreach ($validated['channels'] as $channel) {
                $result = $this->multiChannelService->sendMessage(
                    $agent,
                    $validated['message'],
                    'test_message',
                    ['channel' => $channel, 'test' => true]
                );

                $results[$channel] = $result;
            }

            Log::info("Multi-channel test completed", [
                'admin_id' => auth()->id(),
                'agent_id' => $agent->id,
                'results' => $results
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multi-channel test completed',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error("Multi-channel test failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}