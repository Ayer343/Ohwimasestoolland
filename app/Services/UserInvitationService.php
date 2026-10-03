<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserInvitation;
use App\Models\SystemSetting;
use App\Models\UserInvitationLog;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsTemplateService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Exception;

class UserInvitationService
{
    protected $multiChannelInvitationService;
    protected $smsService;
    protected $emailService;
    protected $whatsappService;
    protected $smsTemplateService;

    // Configurable properties with enhanced token management
    protected $invitationExpiryDays;
    protected $invitationWarningDays;
    protected $invitationAutoExpiry;
    protected $invitationResendExtendsExpiry;

    public function __construct(
        MultiChannelInvitationService $multiChannelInvitationService,
        SmsService $smsService,
        EmailService $emailService,
        WhatsAppService $whatsappService,
        SmsTemplateService $smsTemplateService
    ) {
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->smsService = $smsService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;
        $this->smsTemplateService = $smsTemplateService;

        // Load configuration with type casting
        $this->invitationExpiryDays = (int) config('app.invitation_expiry_days', 7);
        $this->invitationWarningDays = (int) config('app.invitation_warning_days', 2);
        $this->invitationAutoExpiry = config('app.invitation_auto_expiry', true);
        $this->invitationResendExtendsExpiry = config('app.invitation_resend_extends_expiry', true);
    }

    /**
     * ✅ MAIN METHOD: Send invitation to user (called by controller)
     *
     * ✅ FIX: Now returns `failed_channels` alongside `channels_successful`
     * so callers (UserManagementController, Sanitation\PersonnelController)
     * can render accurate success/partial-failure feedback.
     */
    public function sendInvitation(User $user, array $data = []): array
    {
        // Get current authenticated user
        $currentUser = auth()->user();

        if (!$currentUser) {
            return [
                'success'    => false,
                'message'    => 'Authentication required to send invitations',
                'error_code' => 'UNAUTHENTICATED',
            ];
        }

        // ✅ Extract channels - handle multiple formats
        $channels = $this->resolveRequestedChannels($data);

        // Log the resolved channels
        Log::info('Sending invitation with channels', [
            'user_id'           => $user->id,
            'raw_input'         => $data['invitation_channels'] ?? $data['channels'] ?? null,
            'resolved_channels' => $channels,
            'user_has_phone'    => !empty($user->phone),
            'user_has_email'    => !empty($user->email),
        ]);

        // Prepare invitation data
        $invitationData = [
            'user_id'         => $user->id,
            'channels'        => $channels, // MUST be a simple indexed array: ['sms', 'email']
            'invitation_type' => $data['invitation_type'] ?? 'welcome',
            'custom_message'  => $data['custom_message'] ?? null,
            'expires_in_days' => isset($data['expires_in_days'])
                ? (int) $data['expires_in_days']
                : $this->invitationExpiryDays,
        ];

        // Call the main invitation creation method
        $result = $this->createAndSendInvitation($invitationData, $currentUser);

        // ✅ Split delivery results into successful/failed channel lists
        $deliveryResults = $result['delivery_results'] ?? [];
        $channelStats    = $this->extractChannelStatsFromDeliveryResults($deliveryResults);

        return [
            'success'             => $result['success'] ?? false,
            'message'             => $result['message'] ?? ($result['success'] ? 'Invitation sent successfully' : 'Failed to send invitation'),
            'invitation_url'      => $result['invitation_url'] ?? null,
            'channels_successful' => $channelStats['successful'],
            'failed_channels'     => $channelStats['failed'],   // ✅ NEW
            'delivery_results'    => $deliveryResults,
            'master_token'        => $result['master_token'] ?? null,
            'invitation_id'       => $result['invitation']->id ?? null,
            'error_code'          => $result['error_code'] ?? null,
        ];
    }

    /**
     * ✅ SAFE MERGE HELPER: Safely merge metadata arrays
     */
    private function safeMergeMetadata($existingMetadata, array $newData): array
    {
        if (is_string($existingMetadata)) {
            $decoded = json_decode($existingMetadata, true);
            $existingMetadata = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($existingMetadata)) {
            $existingMetadata = [];
        }

        return array_merge($existingMetadata, $newData);
    }

    /**
     * ✅ SAFE ARRAY HELPER: Ensure value is an array
     */
    private function ensureArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    /**
     * ✅ NEW HELPER: Normalise requested channels from any input format.
     *
     * Accepts:
     *   - ['invitation_channels' => ['email']]
     *   - ['channels' => ['email']]
     *   - ['invitation_channels' => 'email,sms']
     *   - ['channels' => '["email","sms"]']
     *
     * Falls back to ['email'] when nothing usable is provided.
     * Does NOT do channel availability checks — that happens later.
     */
    private function resolveRequestedChannels(array $data): array
    {
        $channels = $data['invitation_channels']
            ?? $data['channels']
            ?? ['email'];

        if (is_string($channels)) {
            if (strpos($channels, '[') === 0) {
                $decoded  = json_decode($channels, true);
                $channels = is_array($decoded) ? $decoded : ['email'];
            } else {
                $channels = array_map('trim', explode(',', $channels));
            }
        }

        if (!is_array($channels)) {
            $channels = ['email'];
        }

        return array_values(array_unique(array_filter($channels)));
    }

    /**
     * ✅ NEW HELPER: Split a delivery results array into successful/failed channel lists.
     *
     * @param  array  $deliveryResults  Array of ['channel' => 'email', 'success' => bool, ...]
     * @return array{successful: array, failed: array}
     */
    private function extractChannelStatsFromDeliveryResults(array $deliveryResults): array
    {
        $successful = [];
        $failed     = [];

        foreach ($deliveryResults as $delivery) {
            if (!is_array($delivery)) {
                continue;
            }

            $channel = $delivery['channel'] ?? 'unknown';

            if (!empty($delivery['success'])) {
                $successful[] = $channel;
            } else {
                $failed[] = $channel;
            }
        }

        return [
            'successful' => array_values(array_unique($successful)),
            'failed'     => array_values(array_unique($failed)),
        ];
    }

    /**
     * ✅ ENHANCED: Create and send user invitation with MASTER TOKEN consistency
     */
    public function createAndSendInvitation(array $data, $currentUser): array
    {
        return DB::transaction(function () use ($data, $currentUser) {
            try {
                $user = User::findOrFail($data['user_id']);

                // Validate permissions
                if (!$this->canSendInvitationForUser($user, $currentUser)) {
                    return [
                        'success'    => false,
                        'message'    => 'You do not have permission to send invitations for this user.',
                        'error_code' => 'PERMISSION_DENIED',
                    ];
                }

                // Check if user can receive invitation
                $eligibilityCheck = $this->checkUserInvitationEligibility($user);
                if (!$eligibilityCheck['eligible']) {
                    return [
                        'success'    => false,
                        'message'    => $eligibilityCheck['message'],
                        'error_code' => $eligibilityCheck['error_code'] ?? 'USER_NOT_ELIGIBLE',
                    ];
                }

                // ✅ FIX: Properly extract and normalize channels
                $requestedChannels = $data['channels'] ?? $data['invitation_channels'] ?? ['email'];

                if (is_string($requestedChannels)) {
                    if (strpos($requestedChannels, '[') === 0) {
                        $decoded = json_decode($requestedChannels, true);
                        $requestedChannels = is_array($decoded) ? $decoded : ['email'];
                    } else {
                        $requestedChannels = array_map('trim', explode(',', $requestedChannels));
                    }
                }

                if (!is_array($requestedChannels)) {
                    $requestedChannels = ['email'];
                }

                $requestedChannels = array_filter($requestedChannels);

                // Get available channels for this user
                $availableChannels = $this->getAvailableChannels($user);

                // Intersect requested with available
                $channels = array_intersect($requestedChannels, $availableChannels);

                // Convert to simple indexed array (remove any string keys)
                $channels = array_values($channels);

                // Log the channel resolution
                Log::info('Channel resolution for invitation', [
                    'user_id'              => $user->id,
                    'raw_requested'        => $data['channels'] ?? $data['invitation_channels'] ?? null,
                    'normalized_requested' => $requestedChannels,
                    'available_channels'   => $availableChannels,
                    'final_channels'       => $channels,
                    'user_has_phone'       => !empty($user->phone),
                    'user_has_email'       => !empty($user->email),
                ]);

                if (empty($channels)) {
                    Log::warning('No available channels for user after resolution', [
                        'user_id'            => $user->id,
                        'requested_channels' => $requestedChannels,
                        'available_channels' => $availableChannels,
                        'user_has_phone'     => !empty($user->phone),
                        'user_has_email'     => !empty($user->email),
                    ]);

                    return [
                        'success'            => false,
                        'message'            => 'No available communication channels for this user.',
                        'available_channels' => $availableChannels,
                        'requested_channels' => $requestedChannels,
                        'error_code'         => 'NO_AVAILABLE_CHANNELS',
                    ];
                }

                // Create invitation with MASTER TOKEN
                $invitationResult = $this->createInvitationWithMasterToken($user, $currentUser, $data, $channels);
                if (!$invitationResult['success']) {
                    return $invitationResult;
                }

                $invitation  = $invitationResult['invitation'];
                $masterToken = $invitationResult['master_token'];

                // Verify token consistency before sending
                $tokenVerification = $this->verifyTokenConsistency($invitation->id, $masterToken);
                if (!$tokenVerification['consistent']) {
                    Log::error('CRITICAL: Token inconsistency detected before sending user invitation', [
                        'invitation_id'  => $invitation->id,
                        'expected_token' => $masterToken,
                        'actual_token'   => $tokenVerification['actual_token'],
                        'user_id'        => $user->id,
                    ]);

                    return [
                        'success'              => false,
                        'message'              => 'Token inconsistency detected. Please try again.',
                        'error_code'           => 'TOKEN_INCONSISTENCY',
                        'verification_details' => $tokenVerification,
                    ];
                }

                // Send invitation via channels with verified token
                $sendResults = $this->sendInvitationViaChannelsWithToken($invitation, $channels, $masterToken);

                // Update invitation with delivery results
                $this->updateInvitationWithDeliveryResults($invitation, $sendResults);

                Log::info('User invitation created and sent successfully', [
                    'invitation_id'    => $invitation->id,
                    'user_id'          => $user->id,
                    'sent_by'          => $currentUser->id,
                    'channels'         => $channels,
                    'master_token'     => $masterToken,
                    'delivery_results' => $sendResults,
                ]);

                // Log the invitation creation
                UserInvitationLog::logInvitationCreated(
                    $invitation->id,
                    $user->id,
                    $currentUser->id,
                    $channels,
                    $masterToken
                );

                return [
                    'success'            => true,
                    'invitation'         => $invitation,
                    'master_token'       => $masterToken,
                    'invitation_url'     => $invitationResult['invitation_url'],
                    'delivery_results'   => $sendResults,
                    'available_channels' => $availableChannels,
                    'token_verified'     => $tokenVerification['consistent'],
                ];

            } catch (Exception $e) {
                Log::error('Failed to create and send user invitation: ' . $e->getMessage(), [
                    'user_id' => $data['user_id'] ?? 'unknown',
                    'sent_by' => $currentUser->id,
                    'data'    => $data,
                ]);

                return [
                    'success'    => false,
                    'message'    => 'Failed to create invitation: ' . $e->getMessage(),
                    'error_code' => 'INVITATION_CREATION_EXCEPTION',
                ];
            }
        });
    }

    /**
     * ✅ CRITICAL FIX: Create invitation with SINGLE master token for all channels
     */
    public function createInvitationWithMasterToken(User $user, $invitedBy, array $data, array $channels): array
    {
        try {
            // Generate ONE master token for all channels
            $masterToken = Str::random(64);

            // ✅ Ensure expires_in_days is integer
            $expiresInDays = isset($data['expires_in_days'])
                ? (int) $data['expires_in_days']
                : $this->invitationExpiryDays;
            $expiresInDays = max(1, min(90, $expiresInDays));
            $expiresAt     = now()->addDays($expiresInDays);

            $invitationData = [
                'user_id'         => $user->id,
                'invited_by'      => $invitedBy->id,
                'token'           => $masterToken,
                'channels'        => $channels,
                'invitation_type' => $data['invitation_type'] ?? 'welcome',
                'custom_message'  => $data['custom_message'] ?? null,
                'expires_at'      => $expiresAt,
                'sent_at'         => now(),
                'status'          => 'sent',
                'metadata'        => [
                    'sent_by'                  => $invitedBy->id,
                    'sent_by_name'             => $invitedBy->name,
                    'channels_used'            => $channels,
                    'custom_message_provided'  => !empty($data['custom_message']),
                    'expires_in_days'          => $expiresInDays,
                    'invitation_expiry_days'   => $this->invitationExpiryDays,
                    'auto_expiry_enabled'      => $this->invitationAutoExpiry,
                    'created_via'              => 'service',
                    'master_token'             => $masterToken,
                    'channels_sent'            => [],
                    'token_created_at'         => now()->toISOString(),
                    'token_hash'               => hash('sha256', $masterToken),
                ],
            ];

            $invitation = UserInvitation::create($invitationData);

            // Verify token was stored correctly
            $invitation->refresh();

            if ($invitation->token !== $masterToken) {
                Log::error('CRITICAL: Token mismatch during user invitation creation', [
                    'expected_token' => $masterToken,
                    'stored_token'   => $invitation->token,
                    'invitation_id'  => $invitation->id,
                    'action'         => 'FORCING_TOKEN_CORRECTION',
                ]);

                $invitation->update(['token' => $masterToken]);
                $invitation->refresh();
            }

            // Final verification
            $finalVerification = $this->verifyTokenConsistency($invitation->id, $masterToken);

            Log::info('User invitation created with master token', [
                'invitation_id'      => $invitation->id,
                'user_id'            => $user->id,
                'master_token'       => $masterToken,
                'stored_token'       => $invitation->token,
                'tokens_match'       => $invitation->token === $masterToken,
                'final_verification' => $finalVerification['consistent'],
                'expires_in_days'    => $expiresInDays,
                'expires_at'         => $expiresAt->toISOString(),
            ]);

            return [
                'success'        => true,
                'invitation'     => $invitation,
                'master_token'   => $masterToken,
                'invitation_url' => route('user.invitations.accept', ['token' => $masterToken]),
                'token_verified' => $finalVerification['consistent'],
            ];

        } catch (Exception $e) {
            Log::error('Error creating user invitation with master token: ' . $e->getMessage());

            return [
                'success'    => false,
                'message'    => 'Failed to create invitation: ' . $e->getMessage(),
                'error_code' => 'TOKEN_CREATION_FAILED',
            ];
        }
    }

    /**
     * ✅ ENHANCED: Send invitation via channels with verified token consistency
     */
    public function sendInvitationViaChannelsWithToken(UserInvitation $invitation, array $channels, string $masterToken): array
    {
        $results             = [];
        $user                = $invitation->user;
        $successfulChannels  = [];

        foreach ($channels as $channel) {
            try {
                // Token verification before each channel send
                $currentToken = $invitation->fresh()->token;
                if ($currentToken !== $masterToken) {
                    Log::error('CRITICAL: Token changed before channel send', [
                        'channel'        => $channel,
                        'expected_token' => $masterToken,
                        'current_token'  => $currentToken,
                        'invitation_id'  => $invitation->id,
                        'action'         => 'ATTEMPTING_REPAIR',
                    ]);

                    $repairResult = $this->repairTokenInconsistency($invitation->id, $masterToken);

                    if (!$repairResult['success']) {
                        $results[] = [
                            'channel'    => $channel,
                            'success'    => false,
                            'message'    => "Token inconsistency detected. Repair failed.",
                            'error_code' => 'TOKEN_CHANGED_DURING_SEND',
                            'sent_at'    => now()->toISOString(),
                        ];
                        continue;
                    }

                    $invitation->refresh();
                }

                $channelResult = $this->sendToSpecificChannelWithToken($invitation, $channel, $masterToken);

                $results[] = $channelResult;

                if ($channelResult['success']) {
                    $successfulChannels[] = $channel;
                    $this->updateChannelTracking($invitation->id, $channel, $masterToken);
                }

            } catch (Exception $e) {
                Log::error("Failed to send user invitation via {$channel}: " . $e->getMessage(), [
                    'invitation_id' => $invitation->id,
                    'channel'       => $channel,
                ]);

                $results[] = [
                    'channel'    => $channel,
                    'success'    => false,
                    'message'    => $e->getMessage(),
                    'sent_at'    => now()->toISOString(),
                    'error_code' => 'CHANNEL_SEND_EXCEPTION',
                ];
            }
        }

        // Final token consistency check
        $finalTokenCheck = $this->verifyTokenConsistency($invitation->id, $masterToken);
        if (!$finalTokenCheck['consistent']) {
            Log::error('CRITICAL: Token changed during multi-channel user invitation send', [
                'invitation_id'      => $invitation->id,
                'original_token'     => $masterToken,
                'final_token'        => $finalTokenCheck['actual_token'],
                'successful_channels'=> $successfulChannels,
            ]);
        }

        return $results;
    }

    /**
     * ✅ Send to specific channel with verified token
     */
    private function sendToSpecificChannelWithToken(UserInvitation $invitation, string $channel, string $masterToken): array
    {
        $user          = $invitation->user;
        $invitationUrl = route('user.invitations.accept', ['token' => $masterToken]);

        switch ($channel) {
            case 'email':
                return $this->sendEmailInvitationWithToken($user, $invitation, $masterToken, $invitationUrl);

            case 'sms':
                return $this->sendSmsInvitationWithToken($user, $invitation, $masterToken, $invitationUrl);

            case 'whatsapp':
                return $this->sendWhatsAppInvitationWithToken($user, $invitation, $masterToken, $invitationUrl);

            default:
                return [
                    'channel'    => $channel,
                    'success'    => false,
                    'message'    => "Unsupported channel: {$channel}",
                    'error_code' => 'UNSUPPORTED_CHANNEL',
                    'sent_at'    => now()->toISOString(),
                ];
        }
    }

    /**
     * ✅ Send email invitation with token verification
     */
    private function sendEmailInvitationWithToken(User $user, UserInvitation $invitation, string $masterToken, string $invitationUrl): array
    {
        try {
            if (empty($user->email)) {
                return [
                    'channel'    => 'email',
                    'success'    => false,
                    'message'    => 'User has no email address',
                    'error_code' => 'NO_EMAIL',
                    'sent_at'    => now()->toISOString(),
                ];
            }

            $preSendToken = $invitation->fresh()->token;
            if ($preSendToken !== $masterToken) {
                Log::error('CRITICAL: Token mismatch before email generation', [
                    'invitation_id'  => $invitation->id,
                    'expected_token' => $masterToken,
                    'actual_token'   => $preSendToken,
                    'user_email'     => $user->email,
                ]);

                return [
                    'channel'    => 'email',
                    'success'    => false,
                    'message'    => 'Token inconsistency detected before email generation',
                    'error_code' => 'TOKEN_MISMATCH_PRE_EMAIL',
                    'sent_at'    => now()->toISOString(),
                ];
            }

            // ✅ Ensure invitation URL is correctly formatted with token
            $invitationUrl = route('user.invitations.accept', ['token' => $masterToken]);

            Log::debug('Generated invitation URL for email', [
                'user_id'        => $user->id,
                'invitation_id'  => $invitation->id,
                'invitation_url' => $invitationUrl,
                'token'          => $masterToken,
            ]);

            $emailData = [
                'userName'         => $user->name,
                'userType'         => $user->type_name,
                'customMessage'    => $invitation->custom_message,
                'invitationLink'   => $invitationUrl,
                'expiryDate'       => $invitation->expires_at->format('F j, Y'),
                'daysUntilExpiry'  => $this->invitationExpiryDays,
                'invitationType'   => $invitation->invitation_type,
                'invitationId'     => $invitation->id,
                'invitation'       => $invitation,
                'user'             => $user,
                'invitation_url'   => $invitationUrl,
                'token'            => $masterToken,
            ];

            Log::info('Preparing email invitation with data', [
                'invitation_id'        => $invitation->id,
                'email_data_keys'      => array_keys($emailData),
                'has_invitation_url'   => isset($emailData['invitation_url']),
                'invitation_url_value' => $emailData['invitation_url'] ?? 'NOT_SET',
            ]);

            $emailResult = $this->emailService->sendUserInvitation($user->email, $emailData);

            if ($emailResult['success']) {
                $postSendToken        = $invitation->fresh()->token;
                $tokenStillConsistent = $postSendToken === $masterToken;

                UserInvitationLog::logEmailSent(
                    $invitation->id,
                    $user->email,
                    $emailResult['message_id'] ?? 'unknown',
                    $masterToken,
                    $tokenStillConsistent
                );

                return [
                    'channel'  => 'email',
                    'success'  => true,
                    'message'  => 'Email sent with verified token',
                    'provider' => 'email',
                    'sent_at'  => now()->toISOString(),
                ];
            }

            return [
                'channel'    => 'email',
                'success'    => false,
                'message'    => $emailResult['message'] ?? 'Email send failed',
                'error_code' => 'EMAIL_SEND_FAILED',
                'sent_at'    => now()->toISOString(),
            ];

        } catch (Exception $e) {
            Log::error('Email send with verified token failed: ' . $e->getMessage());

            return [
                'channel'    => 'email',
                'success'    => false,
                'message'    => 'Email exception: ' . $e->getMessage(),
                'error_code' => 'EMAIL_EXCEPTION',
                'sent_at'    => now()->toISOString(),
            ];
        }
    }

    /**
     * ✅ Send SMS invitation with token verification - ENHANCED
     */
    private function sendSmsInvitationWithToken(User $user, UserInvitation $invitation, string $masterToken, string $invitationUrl): array
    {
        try {
            if (empty($user->phone)) {
                Log::warning('SMS not sent: User has no phone number', [
                    'user_id'       => $user->id,
                    'invitation_id' => $invitation->id,
                ]);

                return [
                    'channel'    => 'sms',
                    'success'    => false,
                    'message'    => 'User has no phone number',
                    'error_code' => 'NO_PHONE',
                    'sent_at'    => now()->toISOString(),
                ];
            }

            $phone   = $this->formatPhoneNumber($user->phone);
            $message = $this->generateSmsInvitationMessage($user, $invitation, $invitationUrl);

            Log::info('Attempting to send SMS invitation', [
                'user_id'        => $user->id,
                'phone'          => $phone,
                'message_length' => strlen($message),
                'invitation_id'  => $invitation->id,
            ]);

            $result = $this->smsService->sendWithDefaultProvider(
                $phone,
                $message,
                [
                    'is_test'         => false,
                    'user_id'         => $user->id,
                    'invitation_id'   => $invitation->id,
                    'master_token'    => $masterToken,
                    'channel'         => 'sms',
                    'invitation_type' => $invitation->invitation_type,
                ]
            );

            if ($result['success']) {
                Log::info('SMS invitation sent successfully', [
                    'user_id'       => $user->id,
                    'phone'         => $phone,
                    'invitation_id' => $invitation->id,
                    'provider'      => $result['provider'] ?? 'unknown',
                    'message_id'    => $result['message_id'] ?? 'unknown',
                ]);

                UserInvitationLog::logSmsSent(
                    $invitation->id,
                    $phone,
                    $result['message_id'] ?? 'unknown',
                    $masterToken
                );
            } else {
                Log::error('SMS invitation failed', [
                    'user_id'       => $user->id,
                    'phone'         => $phone,
                    'invitation_id' => $invitation->id,
                    'error'         => $result['message'] ?? 'Unknown error',
                ]);
            }

            return [
                'channel'        => 'sms',
                'success'        => $result['success'],
                'message'        => $result['message'] ?? ($result['success'] ? 'SMS sent' : 'SMS failed'),
                'provider'       => $result['provider'] ?? 'unknown',
                'sent_at'        => now()->toISOString(),
                'token_verified' => true,
            ];

        } catch (Exception $e) {
            Log::error('SMS send with verified token failed: ' . $e->getMessage(), [
                'user_id'       => $user->id,
                'invitation_id' => $invitation->id,
                'trace'         => $e->getTraceAsString(),
            ]);

            return [
                'channel'    => 'sms',
                'success'    => false,
                'message'    => 'SMS exception: ' . $e->getMessage(),
                'error_code' => 'SMS_EXCEPTION',
                'sent_at'    => now()->toISOString(),
            ];
        }
    }

    /**
     * ✅ Format phone number for SMS sending
     */
    private function formatPhoneNumber($phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($cleaned) === 10 && substr($cleaned, 0, 1) === '0') {
            return '233' . substr($cleaned, 1);
        }

        if (strlen($cleaned) === 12 && substr($cleaned, 0, 3) === '233') {
            return $cleaned;
        }

        if (substr($phone, 0, 1) === '+') {
            return substr($phone, 1);
        }

        return $cleaned;
    }

    /**
     * ✅ Send WhatsApp invitation with token verification
     */
    private function sendWhatsAppInvitationWithToken(User $user, UserInvitation $invitation, string $masterToken, string $invitationUrl): array
    {
        try {
            if (empty($user->phone)) {
                return [
                    'channel'    => 'whatsapp',
                    'success'    => false,
                    'message'    => 'User has no phone number',
                    'error_code' => 'NO_PHONE',
                    'sent_at'    => now()->toISOString(),
                ];
            }

            $phone   = $this->formatPhoneNumber($user->phone);
            $message = $this->generateWhatsAppInvitationMessage($user, $invitation, $invitationUrl);

            $result = $this->whatsappService->sendMessage(
                $phone,
                $message,
                [
                    'user_id'         => $user->id,
                    'invitation_id'   => $invitation->id,
                    'master_token'    => $masterToken,
                    'channel'         => 'whatsapp',
                    'invitation_type' => $invitation->invitation_type,
                ]
            );

            if ($result['success']) {
                UserInvitationLog::logWhatsAppSent(
                    $invitation->id,
                    $phone,
                    $result['message_id'] ?? 'unknown',
                    $masterToken
                );
            }

            return [
                'channel'        => 'whatsapp',
                'success'        => $result['success'],
                'message'        => $result['message'] ?? ($result['success'] ? 'WhatsApp sent' : 'WhatsApp failed'),
                'provider'       => $result['provider'] ?? 'unknown',
                'sent_at'        => now()->toISOString(),
                'token_verified' => true,
            ];

        } catch (Exception $e) {
            Log::error('WhatsApp send with verified token failed: ' . $e->getMessage());

            return [
                'channel'    => 'whatsapp',
                'success'    => false,
                'message'    => 'WhatsApp exception: ' . $e->getMessage(),
                'error_code' => 'WHATSAPP_EXCEPTION',
                'sent_at'    => now()->toISOString(),
            ];
        }
    }

    /**
     * ✅ Generate SMS invitation message
     */
    private function generateSmsInvitationMessage(User $user, UserInvitation $invitation, string $invitationUrl): string
    {
        try {
            $appName    = config('app.name', 'Our System');
            $expiryDate = $invitation->expires_at->format('M j, Y');
            $shortUrl   = $this->shortenUrl($invitationUrl);

            $message = "{$appName} Invite: Hello {$user->name}! Join as {$user->type_name}. Click: {$shortUrl} Expires: {$expiryDate}";

            if (strlen($message) > 160) {
                $message = "{$appName}: Join as {$user->type_name} - {$shortUrl}";
            }

            return $message;

        } catch (Exception $e) {
            Log::error('Error generating SMS message: ' . $e->getMessage());
            return "Join invitation: {$invitationUrl}";
        }
    }

    /**
     * ✅ Shorten URL for SMS
     */
    private function shortenUrl(string $url): string
    {
        if (strlen($url) <= 60) {
            return $url;
        }

        if (preg_match('/token=([a-zA-Z0-9]+)/', $url, $matches)) {
            $token = substr($matches[1], 0, 10);
            return url('/invite/' . $token);
        }

        return $url;
    }

    /**
     * ✅ Generate WhatsApp invitation message
     */
    private function generateWhatsAppInvitationMessage(User $user, UserInvitation $invitation, string $invitationUrl): string
    {
        try {
            $appName    = config('app.name', 'Our System');
            $expiryDate = $invitation->expires_at->format('M j, Y');

            $message = "👋 *Hello {$user->name}!*\n\n" .
                      "You've been invited to join *{$appName}* as *{$user->type_name}*.\n\n" .
                      "✅ *Accept Invitation:*\n" .
                      "{$invitationUrl}\n\n" .
                      "⏰ *Expires:* {$expiryDate}";

            if (!empty($invitation->custom_message)) {
                $message .= "\n\n📝 *Note:* {$invitation->custom_message}";
            }

            $message .= "\n\nThank you!\nThe {$appName} Team";

            return $message;

        } catch (Exception $e) {
            Log::error('Error generating WhatsApp message: ' . $e->getMessage());
            return "Hello {$user->name}! Join invitation: {$invitationUrl}";
        }
    }

    /**
     * ✅ Process invitation acceptance with token verification
     */
    public function processInvitationAcceptance(string $token, array $acceptanceData): array
    {
        return DB::transaction(function () use ($token, $acceptanceData) {
            try {
                $invitation = UserInvitation::with(['user', 'invitedBy'])
                    ->where('token', $token)
                    ->firstOrFail();

                $tokenVerification = $this->verifyTokenConsistency($invitation->id, $token);
                if (!$tokenVerification['consistent']) {
                    Log::error('CRITICAL: Token inconsistency during invitation acceptance', [
                        'invitation_id'  => $invitation->id,
                        'provided_token' => $token,
                        'stored_token'   => $tokenVerification['actual_token'],
                    ]);

                    return [
                        'success'    => false,
                        'message'    => 'Invalid invitation token. Please request a new invitation.',
                        'error_code' => 'TOKEN_INCONSISTENCY',
                    ];
                }

                $validationResult = $this->validateInvitationForAcceptance($invitation);
                if (!$validationResult['valid']) {
                    return [
                        'success'    => false,
                        'message'    => $validationResult['message'],
                        'error_code' => $validationResult['error_code'] ?? 'INVITATION_INVALID',
                    ];
                }

                $user = $invitation->user;

                if (isset($acceptanceData['verification_code'])) {
                    $verificationResult = $this->verifyMultiChannelCode(
                        $user,
                        $acceptanceData['verification_code'],
                        $acceptanceData['verification_channel']
                    );

                    if (!$verificationResult['success']) {
                        return [
                            'success'    => false,
                            'message'    => $verificationResult['message'],
                            'error_code' => 'VERIFICATION_FAILED',
                        ];
                    }
                }

                $this->activateUserAccount($user, $acceptanceData);
                $this->markInvitationAsAccepted($invitation, $acceptanceData);

                Log::info("User invitation accepted successfully", [
                    'invitation_id' => $invitation->id,
                    'user_id'       => $user->id,
                    'user_type'     => $user->type,
                    'accepted_via'  => $acceptanceData['verification_channel'] ?? 'web_form',
                ]);

                UserInvitationLog::logInvitationAccepted(
                    $invitation->id,
                    $user->id,
                    $acceptanceData['verification_channel'] ?? 'web_form',
                    $token
                );

                $dashboardRoute = $this->getDashboardRouteName($user);

                return [
                    'success'        => true,
                    'user'           => $user,
                    'invitation'     => $invitation,
                    'redirect_route' => $dashboardRoute,
                    'token_verified' => true,
                ];

            } catch (Exception $e) {
                Log::error("Failed to process invitation acceptance: " . $e->getMessage(), [
                    'token'           => $token,
                    'acceptance_data' => $acceptanceData,
                ]);

                return [
                    'success'    => false,
                    'message'    => 'Failed to process invitation: ' . $e->getMessage(),
                    'error_code' => 'ACCEPTANCE_PROCESSING_EXCEPTION',
                ];
            }
        });
    }

    /**
     * ✅ Get dashboard route name
     */
    private function getDashboardRouteName(User $user): string
    {
        try {
            $routeMap = [
                User::TYPE_SUPER_ADMIN        => 'admin.dashboard',
                User::TYPE_ADMIN              => 'admin.dashboard',
                User::TYPE_LANDLORD           => 'landlord.dashboard',
                User::TYPE_TENANT             => 'tenant.dashboard',
                User::TYPE_FIELD_AGENT        => 'field-agent.dashboard',
                User::TYPE_DEVELOPER          => 'developer.dashboard',
                User::TYPE_SECURITY_PERSONNEL => 'security.dashboard',
            ];

            return $routeMap[$user->type] ?? 'dashboard';

        } catch (Exception $e) {
            Log::warning('Error determining dashboard route: ' . $e->getMessage());
            return 'dashboard';
        }
    }

    /**
     * ✅ Validate invitation for acceptance
     */
    public function validateInvitationForAcceptance(UserInvitation $invitation): array
    {
        $rawExpiresAt = $invitation->getRawOriginal('expires_at');
        $rawStatus    = $invitation->getRawOriginal('status');
        $rawCreatedAt = $invitation->getRawOriginal('created_at');

        $isExpired = $rawExpiresAt && Carbon::parse($rawExpiresAt)->isPast();

        $isCorrupted      = false;
        $corruptionDetails = [];

        if ($rawCreatedAt && $rawExpiresAt) {
            $createdAt = Carbon::parse($rawCreatedAt);
            $expiresAt = Carbon::parse($rawExpiresAt);
            $hoursDifference = $createdAt->diffInHours($expiresAt);

            if ($hoursDifference < 24) {
                $isCorrupted         = true;
                $corruptionDetails[] = "Invalid expiration date (too short: {$hoursDifference}h)";
            }
        }

        if (!$rawExpiresAt) {
            $isCorrupted         = true;
            $corruptionDetails[] = "Missing expiration date";
        }

        if ($isCorrupted && in_array($rawStatus, ['sent', 'pending'])) {
            if ($invitation->fixExpirationDate()) {
                $invitation->refresh();
                $isExpired = false;
                Log::info('Auto-repaired corrupted user invitation', [
                    'invitation_id'      => $invitation->id,
                    'corruption_details' => $corruptionDetails,
                ]);
            }
        }

        $safeStatus = $invitation->getSafeStatus();

        if ($isExpired || $safeStatus === 'expired' || $safeStatus === 'should_be_expired') {
            if ($this->invitationAutoExpiry && $safeStatus === 'should_be_expired' && !$isCorrupted) {
                $invitation->markAsExpired();
            }
            return [
                'valid'      => false,
                'message'    => 'This invitation has expired.',
                'error_code' => 'INVITATION_EXPIRED',
            ];
        }

        if ($invitation->isAccepted()) {
            return [
                'valid'      => false,
                'message'    => 'This invitation has already been accepted.',
                'error_code' => 'ALREADY_ACCEPTED',
            ];
        }

        if ($invitation->isCancelled() || $invitation->isRevoked()) {
            return [
                'valid'      => false,
                'message'    => 'This invitation has been cancelled.',
                'error_code' => 'INVITATION_CANCELLED',
            ];
        }

        if (!$invitation->isActive()) {
            return [
                'valid'      => false,
                'message'    => 'This invitation is no longer valid.',
                'error_code' => 'INVITATION_INACTIVE',
            ];
        }

        return ['valid' => true];
    }

    /**
     * ✅ Check user invitation eligibility
     */
    public function checkUserInvitationEligibility(User $user): array
    {
        $validStatus = in_array($user->status, [User::STATUS_PENDING, User::STATUS_ACTIVE]);
        $hasContact  = !empty($user->email) || !empty($user->phone);

        $noActiveInvitations = $user->invitations()
            ->where('status', 'sent')
            ->where('expires_at', '>', now())
            ->doesntExist();

        $issues = [];

        if (!$validStatus) {
            $issues[] = "User status '{$user->status}' cannot receive invitations";
        }

        if (!$hasContact) {
            $issues[] = "User has no email or phone number";
        }

        if (!$noActiveInvitations) {
            $issues[] = "User already has active invitations";
        }

        $eligible = $validStatus && $hasContact && $noActiveInvitations;

        return [
            'eligible'   => $eligible,
            'message'    => $eligible ? 'User can receive invitations' : implode(', ', $issues),
            'issues'     => $issues,
            'error_code' => $eligible ? null : 'USER_NOT_ELIGIBLE',
            'details'    => [
                'valid_status'          => $validStatus,
                'has_contact'           => $hasContact,
                'no_active_invitations' => $noActiveInvitations,
                'user_status'           => $user->status,
                'has_email'             => !empty($user->email),
                'has_phone'             => !empty($user->phone),
            ],
        ];
    }

    /**
     * ✅ Token consistency verification
     */
    public function verifyTokenConsistency($invitationId, $expectedToken = null): array
    {
        try {
            $invitation = UserInvitation::find($invitationId);

            if (!$invitation) {
                return [
                    'consistent'     => false,
                    'message'        => 'Invitation not found',
                    'actual_token'   => null,
                    'expected_token' => $expectedToken,
                ];
            }

            $actualToken = $invitation->token;

            if ($expectedToken === null) {
                return [
                    'consistent'     => true,
                    'message'        => 'Token verification (no expected token provided)',
                    'actual_token'   => $actualToken,
                    'expected_token' => null,
                ];
            }

            $isConsistent = $actualToken === $expectedToken;

            if (!$isConsistent) {
                Log::error('User invitation token inconsistency detected', [
                    'invitation_id'  => $invitationId,
                    'expected_token' => $expectedToken,
                    'actual_token'   => $actualToken,
                ]);
            }

            return [
                'consistent'     => $isConsistent,
                'message'        => $isConsistent ? 'Tokens are consistent' : 'TOKEN MISMATCH DETECTED',
                'actual_token'   => $actualToken,
                'expected_token' => $expectedToken,
            ];

        } catch (Exception $e) {
            Log::error('User invitation token consistency check failed: ' . $e->getMessage());

            return [
                'consistent'     => false,
                'message'        => 'Verification failed: ' . $e->getMessage(),
                'actual_token'   => null,
                'expected_token' => $expectedToken,
            ];
        }
    }

    /**
     * ✅ Repair token inconsistency
     */
    public function repairTokenInconsistency($invitationId, $correctToken): array
    {
        try {
            $invitation = UserInvitation::findOrFail($invitationId);

            $originalToken = $invitation->token;

            if ($originalToken === $correctToken) {
                return [
                    'success'        => true,
                    'message'        => 'Tokens already match, no repair needed',
                    'original_token' => $originalToken,
                    'correct_token'  => $correctToken,
                ];
            }

            $invitation->update(['token' => $correctToken]);
            $invitation->refresh();

            $verification = $this->verifyTokenConsistency($invitationId, $correctToken);

            Log::info('User invitation token inconsistency repaired', [
                'invitation_id'     => $invitationId,
                'original_token'    => $originalToken,
                'correct_token'     => $correctToken,
                'repair_successful' => $verification['consistent'],
            ]);

            UserInvitationLog::logTokenRepaired(
                $invitationId,
                $originalToken,
                $correctToken,
                $verification['consistent']
            );

            return [
                'success'        => true,
                'message'        => 'Token inconsistency repaired successfully',
                'original_token' => $originalToken,
                'new_token'      => $correctToken,
                'verification'   => $verification,
            ];

        } catch (Exception $e) {
            Log::error('User invitation token repair failed: ' . $e->getMessage());

            return [
                'success'        => false,
                'message'        => 'Token repair failed: ' . $e->getMessage(),
                'original_token' => null,
                'correct_token'  => $correctToken,
            ];
        }
    }

    /**
     * ✅ Update channel tracking in invitation metadata
     */
    private function updateChannelTracking($invitationId, $channel, $tokenUsed): void
    {
        try {
            $invitation = UserInvitation::find($invitationId);
            if (!$invitation) return;

            $metadata     = $this->ensureArray($invitation->metadata);
            $channelsSent = $metadata['channels_sent'] ?? [];

            $channelsSent[$channel] = [
                'sent_at'    => now()->toISOString(),
                'token_used' => $tokenUsed,
                'verified'   => $tokenUsed === $invitation->token,
            ];

            $metadata['channels_sent']       = $channelsSent;
            $metadata['last_channel_update'] = now()->toISOString();

            $invitation->update(['metadata' => $metadata]);

        } catch (Exception $e) {
            Log::warning('Failed to update user invitation channel tracking: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Resend invitation
     *
     * NOTE: This delegates to `sendInvitation()`, which uses `auth()->user()`
     * as the current actor. The `$currentUser` parameter is kept for signature
     * compatibility with older callers but is not used directly.
     */
    public function resendInvitation(User $user, $currentUser, array $data = []): array
    {
        return $this->sendInvitation($user, $data);
    }

    /**
     * ✅ Update invitation with delivery results
     *
     * ✅ Wrapped in a defensive try/catch so a metadata hiccup can't
     * roll back the outer transaction that created the invitation.
     */
    public function updateInvitationWithDeliveryResults(UserInvitation $invitation, array $deliveryResults): void
    {
        try {
            $successfulChannels = array_filter($deliveryResults, function ($result) {
                return !empty($result['success']);
            });

            $currentMetadata = $this->ensureArray($invitation->metadata);

            $newMetadata = array_merge($currentMetadata, [
                'delivery_results'    => $deliveryResults,
                'successful_channels' => array_values(array_column($successfulChannels, 'channel')),
                'last_delivery_update'=> now()->toISOString(),
            ]);

            $updateData = ['metadata' => $newMetadata];

            if (empty($successfulChannels)) {
                $updateData['status']         = 'failed';
                $updateData['failure_reason'] = 'All communication channels failed';
            }

            $invitation->update($updateData);

        } catch (Exception $e) {
            Log::error('Failed to update invitation with delivery results: ' . $e->getMessage(), [
                'invitation_id'    => $invitation->id ?? null,
                'delivery_results' => $deliveryResults,
            ]);
        }
    }

    /**
     * ✅ Activate user account
     */
    public function activateUserAccount(User $user, array $acceptanceData): void
    {
        $existingMetadata = $this->ensureArray($user->metadata);

        $newMetadata = array_merge($existingMetadata, [
            'invitation_accepted_via'        => 'web_form',
            'invitation_accepted_ip'         => $acceptanceData['ip'] ?? request()->ip(),
            'invitation_accepted_user_agent' => $acceptanceData['user_agent'] ?? request()->userAgent(),
            'password_set_at'                => now()->toISOString(),
            'last_password_change'           => now()->toISOString(),
            'accepted_invitation_id'         => $user->invitations()->latest()->first()?->id,
        ]);

        $userData = [
            'password'                        => Hash::make($acceptanceData['password']),
            'status'                          => User::STATUS_ACTIVE,
            'email_verified_at'               => now(),
            'last_login_at'                   => now(),
            'last_login_ip'                   => $acceptanceData['ip'] ?? request()->ip(),
            'user_agent'                      => $acceptanceData['user_agent'] ?? request()->userAgent(),
            'preferred_communication_channel' => $acceptanceData['verification_channel'] ?? 'email',
            'metadata'                        => $newMetadata,
        ];

        if (in_array($acceptanceData['verification_channel'] ?? '', ['sms', 'whatsapp'])) {
            $userData['phone_verified_at'] = now();
        }

        $user->update($userData);
    }

    /**
     * ✅ Mark invitation as accepted
     */
    public function markInvitationAsAccepted(UserInvitation $invitation, array $acceptanceData): void
    {
        $existingMetadata = $this->ensureArray($invitation->metadata);

        $newMetadata = array_merge($existingMetadata, [
            'accepted_ip'                   => $acceptanceData['ip'] ?? request()->ip(),
            'accepted_user_agent'           => $acceptanceData['user_agent'] ?? request()->userAgent(),
            'password_set'                  => true,
            'terms_accepted'                => $acceptanceData['agree_terms'] ?? false,
            'privacy_accepted'              => $acceptanceData['agree_privacy'] ?? false,
            'verification_channel_used'     => $acceptanceData['verification_channel'] ?? null,
            'accepted_via_service'          => true,
            'token_verified_at_acceptance'  => now()->toISOString(),
        ]);

        $invitation->update([
            'accepted_at' => now(),
            'status'      => 'accepted',
            'metadata'    => $newMetadata,
        ]);

        $invitation->trackView();
    }

    /**
     * ✅ Verify multi-channel verification code
     */
    public function verifyMultiChannelCode(User $user, string $code, string $channel): array
    {
        try {
            if ($channel === 'sms' || $channel === 'whatsapp') {
                if ($user->verifyPhone($code)) {
                    return [
                        'success' => true,
                        'message' => 'Phone verification successful',
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Invalid verification code',
                ];
            }

            return [
                'success' => true,
                'message' => 'Verification successful',
            ];

        } catch (Exception $e) {
            Log::error('Error verifying multi-channel code: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Cancel invitation
     */
    public function cancelInvitation($invitationId, $cancelledBy): array
    {
        try {
            $invitation = UserInvitation::findOrFail($invitationId);

            $existingMetadata = $this->ensureArray($invitation->metadata);
            $newMetadata = array_merge($existingMetadata, [
                'cancelled_by_name' => $cancelledBy->name,
                'cancelled_at_iso'  => now()->toISOString(),
            ]);

            $invitation->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy->id,
                'metadata'     => $newMetadata,
            ]);

            Log::info('User invitation cancelled', [
                'invitation_id' => $invitationId,
                'cancelled_by'  => $cancelledBy->id,
                'user_id'       => $invitation->user_id,
            ]);

            return [
                'success' => true,
                'message' => 'Invitation cancelled successfully',
            ];

        } catch (Exception $e) {
            Log::error('Error cancelling user invitation: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to cancel invitation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Get statistics for user invitations
     */
    public function getStatistics(): array
    {
        $total     = UserInvitation::count();
        $sent      = UserInvitation::where('status', 'sent')->count();
        $accepted  = UserInvitation::where('status', 'accepted')->count();
        $expired   = UserInvitation::where('status', 'expired')->count();
        $failed    = UserInvitation::where('status', 'failed')->count();
        $cancelled = UserInvitation::where('status', 'cancelled')->count();

        $today     = now()->startOfDay();
        $thisWeek  = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        $sentToday     = UserInvitation::where('status', 'sent')->where('sent_at', '>=', $today)->count();
        $acceptedToday = UserInvitation::where('status', 'accepted')->where('accepted_at', '>=', $today)->count();

        $sentThisWeek     = UserInvitation::where('status', 'sent')->where('sent_at', '>=', $thisWeek)->count();
        $acceptedThisWeek = UserInvitation::where('status', 'accepted')->where('accepted_at', '>=', $thisWeek)->count();

        $sentThisMonth     = UserInvitation::where('status', 'sent')->where('sent_at', '>=', $thisMonth)->count();
        $acceptedThisMonth = UserInvitation::where('status', 'accepted')->where('accepted_at', '>=', $thisMonth)->count();

        return [
            'total'           => $total,
            'sent'            => $sent,
            'accepted'        => $accepted,
            'expired'         => $expired,
            'failed'          => $failed,
            'cancelled'       => $cancelled,
            'pending'         => $sent - $accepted - $expired - $failed - $cancelled,
            'acceptance_rate' => $sent > 0 ? round(($accepted / $sent) * 100, 2) : 0,
            'today'           => [
                'sent'            => $sentToday,
                'accepted'        => $acceptedToday,
                'acceptance_rate' => $sentToday > 0 ? round(($acceptedToday / $sentToday) * 100, 2) : 0,
            ],
            'this_week'       => [
                'sent'            => $sentThisWeek,
                'accepted'        => $acceptedThisWeek,
                'acceptance_rate' => $sentThisWeek > 0 ? round(($acceptedThisWeek / $sentThisWeek) * 100, 2) : 0,
            ],
            'this_month'      => [
                'sent'            => $sentThisMonth,
                'accepted'        => $acceptedThisMonth,
                'acceptance_rate' => $sentThisMonth > 0 ? round(($acceptedThisMonth / $sentThisMonth) * 100, 2) : 0,
            ],
            'channels'        => $this->getChannelStatistics(),
            'by_type'         => $this->getInvitationTypeStatistics(),
        ];
    }

    /**
     * ✅ Get channel-specific statistics
     */
    private function getChannelStatistics(): array
    {
        $channels = ['email', 'sms', 'whatsapp'];
        $stats    = [];

        foreach ($channels as $channel) {
            $total    = UserInvitation::whereJsonContains('channels', $channel)->count();
            $sent     = UserInvitation::where('status', 'sent')->whereJsonContains('channels', $channel)->count();
            $accepted = UserInvitation::where('status', 'accepted')->whereJsonContains('channels', $channel)->count();

            $stats[$channel] = [
                'total'           => $total,
                'sent'            => $sent,
                'accepted'        => $accepted,
                'acceptance_rate' => $sent > 0 ? round(($accepted / $sent) * 100, 2) : 0,
            ];
        }

        return $stats;
    }

    /**
     * ✅ Get invitation type statistics
     */
    private function getInvitationTypeStatistics(): array
    {
        return UserInvitation::select('invitation_type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent')
            ->selectRaw('SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted')
            ->groupBy('invitation_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->invitation_type => [
                        'total'           => $item->total,
                        'sent'            => $item->sent,
                        'accepted'        => $item->accepted,
                        'acceptance_rate' => $item->sent > 0 ? round(($item->accepted / $item->sent) * 100, 2) : 0,
                    ],
                ];
            })
            ->toArray();
    }

    /**
     * ✅ Enhanced: Get available channels for user
     */
    public function getAvailableChannels(User $user): array
    {
        $channels = [];

        // Check email
        if (!empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $emailConfig = $this->checkEmailConfiguration();
            if ($emailConfig['configured']) {
                $channels[] = 'email';
                Log::debug('Email channel available for user', ['user_id' => $user->id]);
            } else {
                Log::debug('Email channel not configured', ['user_id' => $user->id]);
            }
        } else {
            Log::debug('Email not available for user', [
                'user_id'    => $user->id,
                'has_email'  => !empty($user->email),
            ]);
        }

        // Check SMS
        if (!empty($user->phone)) {
            $smsConfig = $this->checkSmsConfiguration();
            if ($smsConfig['configured']) {
                $channels[] = 'sms';
                Log::debug('SMS channel available for user', [
                    'user_id' => $user->id,
                    'phone'   => $user->phone,
                ]);
            } else {
                Log::debug('SMS channel not configured', ['user_id' => $user->id]);
            }
        } else {
            Log::debug('Phone not available for user', ['user_id' => $user->id]);
        }

        // Check WhatsApp
        if (!empty($user->phone)) {
            $whatsappConfig = $this->checkWhatsAppConfiguration();
            if ($whatsappConfig['configured']) {
                $channels[] = 'whatsapp';
                Log::debug('WhatsApp channel available for user', ['user_id' => $user->id]);
            }
        }

        Log::info('Available channels for user', [
            'user_id'        => $user->id,
            'channels'       => $channels,
            'user_has_phone' => !empty($user->phone),
            'user_has_email' => !empty($user->email),
        ]);

        return $channels;
    }

    /**
     * ✅ Check email configuration
     */
    public function checkEmailConfiguration(): array
    {
        try {
            $mailConfig   = config('mail');
            $defaultMailer = $mailConfig['default'] ?? null;
            $mailerConfig  = $mailConfig['mailers'][$defaultMailer] ?? [];

            $canSendEmails = !empty($defaultMailer)
                && !empty($mailerConfig['host'])
                && !empty($mailerConfig['port']);

            return [
                'configured'      => $canSendEmails,
                'mailer'          => $defaultMailer,
                'host'            => $mailerConfig['host'] ?? 'not set',
                'port'            => $mailerConfig['port'] ?? 'not set',
                'encryption'      => $mailerConfig['encryption'] ?? 'not set',
                'can_send_emails' => $canSendEmails,
            ];

        } catch (Exception $e) {
            Log::error('Error checking email configuration: ' . $e->getMessage());

            return [
                'configured' => false,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Check SMS configuration
     */
    public function checkSmsConfiguration(): array
    {
        try {
            $defaultProvider = config('sms.default', 'unknown');
            $providers       = array_keys(config('sms.providers', []));

            $isConfigured = !empty($defaultProvider)
                && !empty($providers)
                && !empty(config('sms.providers.' . $defaultProvider));

            return [
                'configured'       => $isConfigured,
                'default_provider' => $defaultProvider,
                'providers'        => $providers,
                'can_send_sms'     => $isConfigured,
            ];
        } catch (Exception $e) {
            Log::error('Error checking SMS configuration: ' . $e->getMessage());

            return [
                'configured'       => false,
                'default_provider' => 'unknown',
                'providers'        => [],
                'can_send_sms'     => false,
            ];
        }
    }

    /**
     * ✅ Check WhatsApp configuration
     */
    public function checkWhatsAppConfiguration(): array
    {
        try {
            $defaultProvider = config('whatsapp.default');
            $providers       = config('whatsapp.providers', []);

            return [
                'configured'         => !empty($defaultProvider) && !empty($providers),
                'default_provider'   => $defaultProvider ?? 'unknown',
                'providers'          => array_keys($providers),
                'can_send_whatsapp'  => !empty($defaultProvider),
            ];
        } catch (Exception $e) {
            Log::error('Error checking WhatsApp configuration: ' . $e->getMessage());

            return [
                'configured'        => false,
                'default_provider'  => 'unknown',
                'providers'         => [],
                'can_send_whatsapp' => false,
            ];
        }
    }

    /**
     * ✅ Get expiry configuration
     */
    public function getExpiryConfiguration(): array
    {
        return [
            'expiry_days'           => $this->invitationExpiryDays,
            'warning_days'          => $this->invitationWarningDays,
            'auto_expiry_enabled'   => $this->invitationAutoExpiry,
            'resend_extends_expiry' => $this->invitationResendExtendsExpiry,
            'timezone'              => config('app.timezone', 'UTC'),
            'supported_channels'    => ['sms', 'whatsapp', 'email'],
            'description'           => "User invitations expire after {$this->invitationExpiryDays} days",
        ];
    }

    /**
     * ✅ Check if current user can send invitation for target user
     *
     * ✅ FIX: Added sanitation supervisor support so personnel invitations
     * sent from Sanitation\PersonnelController work for non-admin creators.
     */
    private function canSendInvitationForUser(User $targetUser, $currentUser): bool
    {
        // Super Admin can send to anyone
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        // Admin can send to non-SuperAdmin and non-Admin users
        if ($currentUser->isAdmin()) {
            return !$targetUser->isSuperAdmin() && !$targetUser->isAdmin();
        }

        // ✅ FIX: Sanitation supervisors can invite sanitation personnel
        if (
            $currentUser->type === User::TYPE_SANITATION_PERSONNEL
            && $currentUser->sanitationPersonnel
            && $currentUser->sanitationPersonnel->isSupervisor()
        ) {
            return $targetUser->type === User::TYPE_SANITATION_PERSONNEL;
        }

        // ✅ FIX: Area Supervisor support (supervisor_level >= 2)
        if (
            $currentUser->type === User::TYPE_SECURITY_PERSONNEL
            && ($currentUser->supervisor_level ?? 0) >= 2
        ) {
            return $targetUser->type === User::TYPE_SECURITY_PERSONNEL;
        }

        // Field Agent can send to other Field Agents
        if ($currentUser->isFieldAgent()) {
            return $targetUser->isFieldAgent();
        }

        // Area Supervisor / Post Commander roles
        if ($currentUser->hasRole('area_supervisor') || $currentUser->hasRole('post_commander')) {
            return $targetUser->type === User::TYPE_SECURITY_PERSONNEL;
        }

        return false;
    }

    /**
     * ✅ Get user's invitation history
     */
    public function getUserInvitationHistory(User $user): array
    {
        $invitations = $user->invitations()
            ->with('invitedBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return $invitations->map(function ($invitation) {
            return [
                'id'              => $invitation->id,
                'invitation_type' => $invitation->invitation_type,
                'channels'        => $invitation->channels,
                'status'          => $invitation->status,
                'sent_at'         => $invitation->sent_at?->toISOString(),
                'expires_at'      => $invitation->expires_at?->toISOString(),
                'accepted_at'     => $invitation->accepted_at?->toISOString(),
                'invited_by'      => $invitation->invitedBy ? [
                    'id'   => $invitation->invitedBy->id,
                    'name' => $invitation->invitedBy->name,
                    'type' => $invitation->invitedBy->type_name,
                ] : null,
                'custom_message'  => $invitation->custom_message,
                'metadata'        => $invitation->metadata,
            ];
        })->toArray();
    }

    /**
     * ✅ Clean up old expired invitations
     */
    public function cleanupOldInvitations($daysToKeep = 30): int
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);

            $expiredCount = UserInvitation::whereIn('status', ['expired', 'failed', 'cancelled'])
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            Log::info('Cleaned up old user invitations', [
                'deleted_count' => $expiredCount,
                'days_to_keep'  => $daysToKeep,
                'cutoff_date'   => $cutoffDate->toISOString(),
            ]);

            return $expiredCount;

        } catch (Exception $e) {
            Log::error('Error cleaning up old user invitations: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Get invitation by token
     */
    public function getInvitationByToken(string $token): ?UserInvitation
    {
        return UserInvitation::with(['user', 'invitedBy'])
            ->where('token', $token)
            ->first();
    }

    /**
     * ✅ Validate invitation token
     */
    public function validateInvitationToken(string $token): array
    {
        $invitation = $this->getInvitationByToken($token);

        if (!$invitation) {
            return [
                'valid'      => false,
                'message'    => 'Invalid invitation token',
                'error_code' => 'INVALID_TOKEN',
            ];
        }

        return $this->validateInvitationForAcceptance($invitation);
    }

    /**
     * ✅ Get invitation details for display
     */
    public function getInvitationDetails(string $token): array
    {
        $invitation = $this->getInvitationByToken($token);

        if (!$invitation) {
            return [
                'success' => false,
                'message' => 'Invitation not found',
            ];
        }

        $validation = $this->validateInvitationForAcceptance($invitation);

        if (!$validation['valid']) {
            return [
                'success'    => false,
                'message'    => $validation['message'],
                'error_code' => $validation['error_code'] ?? 'INVITATION_INVALID',
            ];
        }

        $user = $invitation->user;

        return [
            'success'    => true,
            'invitation' => [
                'id'                => $invitation->id,
                'type'              => $invitation->invitation_type,
                'custom_message'    => $invitation->custom_message,
                'expires_at'        => $invitation->expires_at->toISOString(),
                'days_until_expiry' => now()->diffInDays($invitation->expires_at, false),
                'channels'          => $invitation->channels,
            ],
            'user'       => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'phone'  => $user->phone,
                'type'   => $user->type_name,
                'status' => $user->status,
            ],
            'invited_by' => $invitation->invitedBy ? [
                'name' => $invitation->invitedBy->name,
                'type' => $invitation->invitedBy->type_name,
            ] : null,
        ];
    }

    /**
     * ✅ Track invitation view
     */
    public function trackInvitationView(string $token): void
    {
        try {
            $invitation = UserInvitation::where('token', $token)->first();

            if ($invitation) {
                $invitation->trackView();
            }
        } catch (Exception $e) {
            Log::error('Error tracking invitation view: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Get invitation analytics
     */
    public function getInvitationAnalytics(): array
    {
        $totalViews       = UserInvitation::sum('view_count');
        $totalUniqueViews = UserInvitation::sum('unique_view_count');

        $invitationsWithViews       = UserInvitation::where('view_count', '>', 0)->count();
        $invitationsWithUniqueViews = UserInvitation::where('unique_view_count', '>', 0)->count();

        $averageViews       = $invitationsWithViews > 0 ? round($totalViews / $invitationsWithViews, 2) : 0;
        $averageUniqueViews = $invitationsWithUniqueViews > 0 ? round($totalUniqueViews / $invitationsWithUniqueViews, 2) : 0;

        return [
            'total_views'                          => $totalViews,
            'total_unique_views'                   => $totalUniqueViews,
            'invitations_with_views'               => $invitationsWithViews,
            'invitations_with_unique_views'        => $invitationsWithUniqueViews,
            'average_views_per_invitation'         => $averageViews,
            'average_unique_views_per_invitation'  => $averageUniqueViews,
            'view_to_acceptance_ratio'             => $totalViews > 0
                ? round(UserInvitation::where('status', 'accepted')->count() / $totalViews * 100, 2)
                : 0,
        ];
    }
}