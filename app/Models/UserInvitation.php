<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserInvitation extends Model
{
    use HasFactory;

    // ✅ ENHANCED: Invitation status constants with controller alignment
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVOKED = 'revoked';

    // ✅ ENHANCED: Invitation type constants - MATCHING CONTROLLER
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_REGISTRATION = 'registration';
    public const TYPE_ACCOUNT_SETUP = 'account_setup';
    public const TYPE_PASSWORD_SETUP = 'password_setup';

    // ✅ ENHANCED: Channel constants - MATCHING CONTROLLER
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    // ✅ ENHANCED: Token and expiry constants with config integration
    public const TOKEN_LENGTH = 60;
    public const DEFAULT_EXPIRY_DAYS = 7;

    protected $fillable = [
        'user_id',
        'invited_by',
        'token',
        'channels',
        'invitation_type',
        'custom_message',
        'expires_at',
        'sent_at',
        'accepted_at',
        'viewed_at',
        'status',
        'failure_reason',
        'metadata'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'viewed_at' => 'datetime',
        'channels' => 'array',
        'metadata' => 'array'
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'invitation_type' => self::TYPE_WELCOME,
        'channels' => '["email"]'
    ];

    protected $appends = [
        'is_valid',
        'is_expired',
        'is_accepted',
        'is_active',
        'is_revoked',
        'days_until_expiry',
        'expiry_status',
        'delivery_status',
        'invitation_url',
        'safe_status',
        'expiration_health',
        'can_be_resent',
        'is_expiring_soon'
    ];

    /**
     * ✅ ENHANCED: Boot the model with comprehensive event handling
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            // Generate unique token if not provided
            if (empty($invitation->token)) {
                $invitation->token = Str::random(self::TOKEN_LENGTH);
            }

            // ✅ FIXED: Set default expiry if not provided - Ensure integer type
            if (empty($invitation->expires_at)) {
                $expiryDays = (int) config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS);
                $invitation->expires_at = now()->addDays($expiryDays);
            }

            // Set default metadata if not provided
            if (empty($invitation->metadata)) {
                $invitation->metadata = [
                    'created_at' => now()->toISOString(),
                    'token_length' => self::TOKEN_LENGTH,
                    'expiry_days' => config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS),
                    'auto_expiry_enabled' => config('app.invitation_auto_expiry', true),
                    'resend_extends_expiry' => config('app.invitation_resend_extends_expiry', true),
                ];
            }

            // Ensure channels is properly formatted
            if (is_string($invitation->channels)) {
                $invitation->channels = json_decode($invitation->channels, true) ?? ['email'];
            }
        });

        static::updating(function ($invitation) {
            // ✅ ENHANCED: Auto-update status to expired if expiry date has passed
            if ($invitation->isDirty('expires_at') || 
                ($invitation->expires_at && $invitation->expires_at->isPast())) {
                if (in_array($invitation->status, [self::STATUS_PENDING, self::STATUS_SENT]) && 
                    $invitation->expires_at->isPast() &&
                    config('app.invitation_auto_expiry', true)) {
                    
                    $invitation->status = self::STATUS_EXPIRED;
                    
                    Log::info('User invitation auto-expired', [
                        'invitation_id' => $invitation->id,
                        'user_id' => $invitation->user_id,
                        'expired_at' => now()->toDateTimeString(),
                        'auto_expiry_enabled' => true
                    ]);
                }
            }

            // ✅ ENHANCED: Track status changes in metadata
            if ($invitation->isDirty('status')) {
                $invitation->metadata = array_merge($invitation->metadata ?? [], [
                    'status_history' => array_merge(
                        $invitation->metadata['status_history'] ?? [],
                        [
                            [
                                'from' => $invitation->getOriginal('status'),
                                'to' => $invitation->status,
                                'at' => now()->toISOString(),
                                'by' => auth()->id() ?? 'system',
                                'ip' => request()->ip() ?? null
                            ]
                        ]
                    )
                ]);
            }

            // ✅ ENHANCED: Track acceptance details - ALIGNED WITH CONTROLLER
            if ($invitation->isDirty('accepted_at') && $invitation->accepted_at) {
                $invitation->metadata = array_merge($invitation->metadata ?? [], [
                    'accepted_details' => [
                        'accepted_at' => $invitation->accepted_at->toISOString(),
                        'accepted_via' => 'web_form',
                        'accepted_ip' => request()->ip() ?? null,
                        'accepted_user_agent' => request()->userAgent() ?? null,
                        'password_set' => true,
                        'terms_accepted' => true,
                        'privacy_accepted' => true,
                        'verification_channel_used' => null // Will be set by controller
                    ]
                ]);
            }

            // ✅ NEW: Track view events
            if ($invitation->isDirty('viewed_at') && $invitation->viewed_at) {
                $invitation->metadata = array_merge($invitation->metadata ?? [], [
                    'view_history' => array_merge(
                        $invitation->metadata['view_history'] ?? [],
                        [
                            [
                                'viewed_at' => $invitation->viewed_at->toISOString(),
                                'ip' => request()->ip() ?? null,
                                'user_agent' => request()->userAgent() ?? null
                            ]
                        ]
                    )
                ]);
            }
        });

        // ✅ ENHANCED: Log invitation creation with comprehensive details
        static::created(function ($invitation) {
            Log::info('User invitation created', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'invited_by' => $invitation->invited_by,
                'invitation_type' => $invitation->invitation_type,
                'channels' => $invitation->channels,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at,
                'expiry_days' => config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS),
                'auto_expiry_enabled' => config('app.invitation_auto_expiry', true)
            ]);
        });

        // ✅ NEW: Log invitation updates for audit trail
        static::updated(function ($invitation) {
            Log::info('User invitation updated', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'status' => $invitation->status,
                'changes' => $invitation->getChanges(),
                'updated_by' => auth()->id() ?? 'system'
            ]);
        });
    }

    /**
     * Relationship with User model (the invited user)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with User model (the inviter)
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

   /**
 * Get the invitation URL with token
 */
public function getInvitationUrl(): string
{
    // Make sure you have a route named 'user.invitations.accept'
    return route('user.invitations.accept', ['token' => $this->token]);
}

    /**
     * ✅ FIXED: Get invitation URL as attribute (for JSON responses)
     */
    public function getInvitationUrlAttribute(): string
    {
        return $this->getInvitationUrl();
    }

    /**
     * ✅ NEW: Get safe status check (for controller alignment)
     */
    public function getSafeStatus(): string
    {
        $rawExpiresAt = $this->getRawOriginal('expires_at');
        $rawStatus = $this->getRawOriginal('status');
        
        // Check if invitation should be expired but status hasn't been updated
        if (in_array($rawStatus, [self::STATUS_PENDING, self::STATUS_SENT]) && 
            $rawExpiresAt && Carbon::parse($rawExpiresAt)->isPast()) {
            return 'should_be_expired';
        }
        
        return $rawStatus;
    }

    /**
     * ✅ NEW: Get safe status attribute
     */
    public function getSafeStatusAttribute(): string
    {
        return $this->getSafeStatus();
    }

    /**
     * ✅ NEW: Check if invitation is active (controller alignment)
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SENT]) && 
               $this->expires_at && $this->expires_at->isFuture();
    }

    /**
     * ✅ NEW: Get is_active attribute
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    /**
     * ✅ NEW: Check if invitation is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * ✅ NEW: Check if invitation is revoked
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    /**
     * ✅ NEW: Get is_revoked attribute
     */
    public function getIsRevokedAttribute(): bool
    {
        return $this->isRevoked();
    }

    /**
     * ✅ NEW: Check if invitation is accepted (method)
     */
    public function isAccepted(): bool
    {
        return !is_null($this->accepted_at) && $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * ✅ NEW: Get is_accepted attribute
     */
    public function getIsAcceptedAttribute(): bool
    {
        return $this->isAccepted();
    }

    /**
     * ✅ NEW: Check if invitation is expired (method)
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || 
               (in_array($this->status, [self::STATUS_PENDING, self::STATUS_SENT]) && 
                $this->expires_at && $this->expires_at->isPast());
    }

    /**
     * ✅ ENHANCED: Get is_expired attribute
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    /**
     * ✅ ENHANCED: Check if invitation is valid (can be accepted) - ALIGNED WITH CONTROLLER
     */
    public function getIsValidAttribute(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    /**
     * ✅ ENHANCED: Get days until expiry
     */
    public function getDaysUntilExpiry(): int
    {
        if (!$this->expires_at) {
            return 0;
        }
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * ✅ NEW: Get days until expiry attribute
     */
    public function getDaysUntilExpiryAttribute(): int
    {
        return $this->getDaysUntilExpiry();
    }

    /**
     * ✅ NEW: Check if invitation is expiring soon
     */
    public function isExpiringSoon(?int $warningDays = null): bool
    {
        $warningDays = $warningDays ?? config('app.invitation_warning_days', 2);
        return $this->is_active && $this->days_until_expiry <= $warningDays;
    }

    /**
     * ✅ NEW: Get is_expiring_soon attribute
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->isExpiringSoon();
    }

    /**
     * ✅ ENHANCED: Get expiry status
     */
    public function getExpiryStatusAttribute(): string
    {
        if ($this->is_accepted) {
            return 'accepted';
        }

        if ($this->is_expired) {
            return 'expired';
        }

        if ($this->isExpiringSoon(1)) {
            return 'expiring_soon';
        }

        if ($this->isExpiringSoon(3)) {
            return 'expiring';
        }

        return 'valid';
    }

    /**
     * ✅ NEW: Get expiration health status
     */
    public function getExpirationHealthAttribute(): string
    {
        $rawExpiresAt = $this->getRawOriginal('expires_at');
        $rawCreatedAt = $this->getRawOriginal('created_at');
        
        if (!$rawExpiresAt || !$rawCreatedAt) {
            return 'unknown';
        }
        
        $createdAt = Carbon::parse($rawCreatedAt);
        $expiresAt = Carbon::parse($rawExpiresAt);
        $hoursDifference = $createdAt->diffInHours($expiresAt);
        
        if ($hoursDifference < 24) {
            return 'corrupted';
        }
        
        if ($hoursDifference < 48) {
            return 'short_duration';
        }
        
        $expectedDays = config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS);
        $actualDays = $createdAt->diffInDays($expiresAt);
        
        if ($actualDays < $expectedDays) {
            return 'shorter_than_expected';
        }
        
        if ($actualDays > $expectedDays + 2) {
            return 'longer_than_expected';
        }
        
        return 'healthy';
    }

    /**
     * ✅ ENHANCED: Get delivery status - ALIGNED WITH CONTROLLER
     */
    public function getDeliveryStatusAttribute(): array
    {
        $status = [
            'overall' => $this->status,
            'safe_status' => $this->safe_status,
            'sent' => !is_null($this->sent_at),
            'accepted' => $this->is_accepted,
            'expired' => $this->is_expired,
            'failed' => $this->status === self::STATUS_FAILED,
            'cancelled' => $this->status === self::STATUS_CANCELLED,
            'revoked' => $this->is_revoked,
            'can_resend' => $this->canBeResent(),
            'is_active' => $this->is_active,
            'expiration_health' => $this->expiration_health,
            'viewed' => !is_null($this->viewed_at),
            'view_count' => count($this->metadata['view_history'] ?? [])
        ];

        // Add channel-specific status from metadata if available
        if (isset($this->metadata['delivery_results'])) {
            $status['channel_results'] = $this->metadata['delivery_results'];
        }

        if ($this->channels && is_array($this->channels)) {
            foreach ($this->channels as $channel) {
                $status['channels'][$channel] = [
                    'sent' => !is_null($this->sent_at),
                    'status' => $this->getChannelStatus($channel)
                ];
            }
        }

        return $status;
    }

    /**
     * ✅ ENHANCED: Check if invitation is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->is_active;
    }

    /**
     * ✅ ENHANCED: Check if invitation can be resent - ALIGNED WITH CONTROLLER
     */
    public function canBeResent(): bool
    {
        $resendExtendsExpiry = config('app.invitation_resend_extends_expiry', true);
        
        // Can resend if expired, failed, cancelled, revoked, or expiring soon
        $canResend = in_array($this->status, [
            self::STATUS_EXPIRED, 
            self::STATUS_FAILED, 
            self::STATUS_CANCELLED,
            self::STATUS_REVOKED
        ]) || ($this->is_active && $this->days_until_expiry <= 1);

        // If resend extends expiry, allow resending active invitations
        if ($resendExtendsExpiry && $this->is_active) {
            $canResend = true;
        }

        return $canResend;
    }

    /**
     * ✅ NEW: Get can_be_resent attribute
     */
    public function getCanBeResentAttribute(): bool
    {
        return $this->canBeResent();
    }

    /**
     * ✅ NEW: Track invitation view
     */
    public function trackView(): bool
    {
        return $this->update([
            'viewed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'last_viewed_at' => now()->toISOString(),
                'last_viewed_ip' => request()->ip(),
                'view_count' => ($this->metadata['view_count'] ?? 0) + 1
            ])
        ]);
    }

    /**
     * ✅ FIXED: Fix expiration date for corrupted invitations - TYPE ERROR RESOLVED
     */
    public function fixExpirationDate(): bool
    {
        $rawExpiresAt = $this->getRawOriginal('expires_at');
        $rawCreatedAt = $this->getRawOriginal('created_at');
        
        if (!$rawExpiresAt || !$rawCreatedAt) {
            return false;
        }
        
        $createdAt = Carbon::parse($rawCreatedAt);
        $expiresAt = Carbon::parse($rawExpiresAt);
        $hoursDifference = $createdAt->diffInHours($expiresAt);
        
        // If expiration is less than 24 hours from creation, fix it
        if ($hoursDifference < 24) {
            // ✅ FIX: Cast to integer to prevent type error
            $expiryDays = (int) config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS);
            $newExpiresAt = $createdAt->copy()->addDays($expiryDays);
            
            $success = $this->update([
                'expires_at' => $newExpiresAt,
                'metadata' => array_merge($this->metadata ?? [], [
                    'expiration_fix_applied' => [
                        'original_expires_at' => $rawExpiresAt,
                        'new_expires_at' => $newExpiresAt->toISOString(),
                        'hours_difference_before' => $hoursDifference,
                        'fixed_at' => now()->toISOString(),
                        'fixed_by' => 'system_auto_repair',
                        'expiry_days_used' => $expiryDays
                    ]
                ])
            ]);
            
            if ($success) {
                Log::warning('User invitation expiration date fixed', [
                    'invitation_id' => $this->id,
                    'user_id' => $this->user_id,
                    'original_expires_at' => $rawExpiresAt,
                    'new_expires_at' => $newExpiresAt->toISOString(),
                    'hours_difference' => $hoursDifference,
                    'expiry_days_used' => $expiryDays
                ]);
            }
            
            return $success;
        }
        
        return false;
    }

    /**
     * ✅ ENHANCED: Mark invitation as sent - ALIGNED WITH CONTROLLER
     */
    public function markAsSent(?array $channels = null, ?array $deliveryResults = null): bool
    {
        $updateData = [
            'sent_at' => now(),
            'status' => self::STATUS_SENT,
            'failure_reason' => null,
        ];

        if ($channels) {
            $updateData['channels'] = $channels;
        }

        if ($deliveryResults) {
            $this->metadata = array_merge($this->metadata ?? [], [
                'delivery_results' => $deliveryResults,
                'successful_channels' => array_column(
                    array_filter($deliveryResults, function($result) {
                        return $result['success'] ?? false;
                    }), 
                    'channel'
                ),
                'sent_details' => [
                    'sent_via' => $channels ?? $this->channels,
                    'sent_at' => now()->toISOString(),
                    'sent_by' => auth()->id() ?? 'system'
                ]
            ]);
            $updateData['metadata'] = $this->metadata;
        }

        $success = $this->update($updateData);

        if ($success) {
            Log::info('User invitation marked as sent', [
                'invitation_id' => $this->id,
                'user_id' => $this->user_id,
                'channels' => $this->channels,
                'sent_at' => $this->sent_at,
                'delivery_results' => $deliveryResults
            ]);
        }

        return $success;
    }

    /**
     * ✅ ENHANCED: Mark invitation as accepted - ALIGNED WITH CONTROLLER
     */
    public function markAsAccepted(?array $acceptanceData = null): bool
    {
        $updateData = [
            'accepted_at' => now(),
            'status' => self::STATUS_ACCEPTED,
        ];

        if ($acceptanceData) {
            $this->metadata = array_merge($this->metadata ?? [], [
                'accepted_details' => array_merge(
                    $this->metadata['accepted_details'] ?? [],
                    $acceptanceData
                )
            ]);
            $updateData['metadata'] = $this->metadata;
        }

        $success = $this->update($updateData);

        if ($success) {
            Log::info('User invitation marked as accepted', [
                'invitation_id' => $this->id,
                'user_id' => $this->user_id,
                'accepted_at' => $this->accepted_at,
                'acceptance_data' => $acceptanceData
            ]);
        }

        return $success;
    }

    /**
     * ✅ NEW: Mark invitation as expired
     */
    public function markAsExpired(): bool
    {
        $success = $this->update([
            'status' => self::STATUS_EXPIRED,
            'metadata' => array_merge($this->metadata ?? [], [
                'expired_details' => [
                    'expired_at' => now()->toISOString(),
                    'expired_by' => 'system_auto_expiry',
                    'original_expires_at' => $this->expires_at?->toISOString()
                ]
            ])
        ]);

        if ($success) {
            Log::info('User invitation marked as expired', [
                'invitation_id' => $this->id,
                'user_id' => $this->user_id,
                'expired_at' => now()->toISOString(),
                'auto_expiry_enabled' => config('app.invitation_auto_expiry', true)
            ]);
        }

        return $success;
    }

    /**
     * ✅ ENHANCED: Mark invitation as failed
     */
    public function markAsFailed(?string $reason = null, ?array $failureDetails = null): bool
    {
        $updateData = [
            'status' => self::STATUS_FAILED,
        ];

        if ($reason) {
            $updateData['failure_reason'] = $reason;
        }

        if ($failureDetails) {
            $this->metadata = array_merge($this->metadata ?? [], [
                'failure_details' => $failureDetails
            ]);
            $updateData['metadata'] = $this->metadata;
        }

        $success = $this->update($updateData);

        if ($success) {
            Log::warning('User invitation marked as failed', [
                'invitation_id' => $this->id,
                'user_id' => $this->user_id,
                'reason' => $reason,
                'failure_details' => $failureDetails
            ]);
        }

        return $success;
    }

    /**
     * ✅ ENHANCED: Mark invitation as cancelled - ALIGNED WITH CONTROLLER
     */
    public function markAsCancelled(?string $reason = null, ?int $cancelledBy = null): bool
    {
        $updateData = [
            'status' => self::STATUS_CANCELLED,
        ];

        if ($reason) {
            $updateData['failure_reason'] = $reason;
        }

        $this->metadata = array_merge($this->metadata ?? [], [
            'cancellation_details' => [
                'cancelled_by' => $cancelledBy ?? auth()->id(),
                'cancelled_at' => now()->toISOString(),
                'cancellation_reason' => $reason,
                'cancelled_ip' => request()->ip() ?? null
            ]
        ]);
        $updateData['metadata'] = $this->metadata;

        $success = $this->update($updateData);

        if ($success) {
            Log::info('User invitation marked as cancelled', [
                'invitation_id' => $this->id,
                'user_id' => $this->user_id,
                'reason' => $reason,
                'cancelled_by' => $cancelledBy ?? auth()->id()
            ]);
        }

        return $success;
    }

    /**
     * ✅ NEW: Mark invitation as revoked
     */
    public function markAsRevoked(?int $revokedBy = null): bool
    {
        $success = $this->update([
            'status' => self::STATUS_REVOKED,
            'metadata' => array_merge($this->metadata ?? [], [
                'revocation_details' => [
                    'revoked_by' => $revokedBy ?? auth()->id(),
                    'revoked_at' => now()->toISOString(),
                    'revoked_ip' => request()->ip() ?? null
                ]
            ])
        ]);

        if ($success) {
            Log::info('User invitation marked as revoked', [
                'invitation_id' => $this->id,
                'user_id' => $this->user_id,
                'revoked_by' => $revokedBy ?? auth()->id()
            ]);
        }

        return $success;
    }

    /**
     * ✅ FIXED: Resend invitation with new token - ALIGNED WITH CONTROLLER
     */
    public function resend(?array $channels = null, ?string $invitationType = null): bool
    {
        $newToken = Str::random(self::TOKEN_LENGTH);
        $extendsExpiry = config('app.invitation_resend_extends_expiry', true);

        $updateData = [
            'token' => $newToken,
            'sent_at' => null, // Reset sent_at to be set when actually sent
            'status' => self::STATUS_PENDING,
            'failure_reason' => null,
            'accepted_at' => null,
            'viewed_at' => null,
        ];

        // ✅ FIXED: Extend expiry if configured - Ensure integer type
        if ($extendsExpiry) {
            $expiryDays = (int) config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS);
            $updateData['expires_at'] = now()->addDays($expiryDays);
        }

        if ($channels) {
            $updateData['channels'] = $channels;
        }

        if ($invitationType) {
            $updateData['invitation_type'] = $invitationType;
        }

        // Update metadata with resend information
        $this->metadata = array_merge($this->metadata ?? [], [
            'resend_history' => array_merge(
                $this->metadata['resend_history'] ?? [],
                [
                    [
                        'resent_at' => now()->toISOString(),
                        'resent_by' => auth()->id() ?? 'system',
                        'previous_token' => $this->token,
                        'new_token' => $newToken,
                        'previous_status' => $this->status,
                        'channels' => $channels ?? $this->channels,
                        'extends_expiry' => $extendsExpiry,
                        'new_expires_at' => $updateData['expires_at'] ?? $this->expires_at?->toISOString()
                    ]
                ]
            )
        ]);

        $updateData['metadata'] = $this->metadata;

        $success = $this->update($updateData);

        if ($success) {
            Log::info('User invitation prepared for resending', [
                'invitation_id' => $this->id,
                'user_id' => $this->user_id,
                'new_token' => $newToken,
                'channels' => $channels ?? $this->channels,
                'resent_by' => auth()->id() ?? 'system',
                'extends_expiry' => $extendsExpiry
            ]);
        }

        return $success;
    }

    /**
     * ✅ NEW: Get channel-specific status
     */
    private function getChannelStatus(string $channel): string
    {
        if ($this->status === self::STATUS_FAILED) {
            return 'failed';
        }

        if ($this->is_accepted) {
            return 'accepted';
        }

        if ($this->is_expired) {
            return 'expired';
        }

        if ($this->sent_at) {
            return 'sent';
        }

        return 'pending';
    }

    /**
     * ✅ NEW: Get invitation statistics for user
     */
    public function getInvitationStats(): array
    {
        return [
            'total_invitations' => self::where('user_id', $this->user_id)->count(),
            'successful_invitations' => self::where('user_id', $this->user_id)
                ->where('status', self::STATUS_ACCEPTED)
                ->count(),
            'pending_invitations' => self::where('user_id', $this->user_id)
                ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                ->where('expires_at', '>', now())
                ->count(),
            'expired_invitations' => self::where('user_id', $this->user_id)
                ->where('status', self::STATUS_EXPIRED)
                ->count(),
            'failed_invitations' => self::where('user_id', $this->user_id)
                ->where('status', self::STATUS_FAILED)
                ->count(),
            'cancelled_invitations' => self::where('user_id', $this->user_id)
                ->where('status', self::STATUS_CANCELLED)
                ->count(),
            'revoked_invitations' => self::where('user_id', $this->user_id)
                ->where('status', self::STATUS_REVOKED)
                ->count(),
            'acceptance_rate' => $this->getAcceptanceRate(),
        ];
    }

    /**
     * ✅ NEW: Calculate acceptance rate
     */
    private function getAcceptanceRate(): float
    {
        $total = self::where('user_id', $this->user_id)->count();
        $accepted = self::where('user_id', $this->user_id)
            ->where('status', self::STATUS_ACCEPTED)
            ->count();

        return $total > 0 ? round(($accepted / $total) * 100, 2) : 0;
    }

    /**
     * ✅ ENHANCED: Get invitation summary for API responses - ALIGNED WITH CONTROLLER
     */
    public function getInvitationSummary(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'invitation_type' => $this->invitation_type,
            'channels' => $this->channels,
            'custom_message' => $this->custom_message,
            'status' => $this->status,
            'safe_status' => $this->safe_status,
            'is_valid' => $this->is_valid,
            'is_expired' => $this->is_expired,
            'is_accepted' => $this->is_accepted,
            'is_active' => $this->is_active,
            'is_revoked' => $this->is_revoked,
            'is_expiring_soon' => $this->is_expiring_soon,
            'can_be_resent' => $this->can_be_resent,
            'expires_at' => $this->expires_at?->toISOString(),
            'expires_in_human' => $this->expires_at?->diffForHumans(),
            'days_until_expiry' => $this->days_until_expiry,
            'expiry_status' => $this->expiry_status,
            'expiration_health' => $this->expiration_health,
            'sent_at' => $this->sent_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'viewed_at' => $this->viewed_at?->toISOString(),
            'invitation_url' => $this->invitation_url,
            'delivery_status' => $this->delivery_status,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'type' => $this->user->type,
                'status' => $this->user->status
            ] : null,
            'invited_by' => $this->invitedBy ? [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->name
            ] : null,
            'config' => [
                'expiry_days' => config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS),
                'warning_days' => config('app.invitation_warning_days', 2),
                'auto_expiry_enabled' => config('app.invitation_auto_expiry', true),
                'resend_extends_expiry' => config('app.invitation_resend_extends_expiry', true)
            ]
        ];
    }

    /**
     * ✅ NEW: Get analytics data for debugging
     */
    public function getAnalyticsData(): array
    {
        return [
            'invitation_id' => $this->id,
            'user_id' => $this->user_id,
            'raw_status' => $this->getRawOriginal('status'),
            'raw_expires_at' => $this->getRawOriginal('expires_at'),
            'raw_created_at' => $this->getRawOriginal('created_at'),
            'safe_status' => $this->safe_status,
            'is_active' => $this->is_active,
            'is_expired' => $this->is_expired,
            'days_until_expiry' => $this->days_until_expiry,
            'expiration_health' => $this->expiration_health,
            'view_count' => $this->metadata['view_count'] ?? 0,
            'resend_count' => count($this->metadata['resend_history'] ?? []),
            'status_history_count' => count($this->metadata['status_history'] ?? [])
        ];
    }

    /**
     * ✅ NEW: Debug timezone issues
     */
    public function debugTimezoneIssues(): array
    {
        return [
            'database_expires_at' => $this->getRawOriginal('expires_at'),
            'parsed_expires_at' => $this->expires_at?->toISOString(),
            'database_created_at' => $this->getRawOriginal('created_at'),
            'parsed_created_at' => $this->created_at?->toISOString(),
            'current_time' => now()->toISOString(),
            'timezone' => config('app.timezone'),
            'is_past_raw' => $this->getRawOriginal('expires_at') && 
                            Carbon::parse($this->getRawOriginal('expires_at'))->isPast(),
            'is_past_parsed' => $this->expires_at?->isPast(),
            'hours_difference' => $this->created_at && $this->expires_at ? 
                                 $this->created_at->diffInHours($this->expires_at) : null
        ];
    }

    /**
     * ✅ ENHANCED: Scope for valid invitations - ALIGNED WITH CONTROLLER
     */
    public function scopeValid($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                    ->where('expires_at', '>', now());
    }

    /**
     * ✅ ENHANCED: Scope for active invitations
     */
    public function scopeActive($query)
    {
        return $query->valid();
    }

    /**
     * ✅ ENHANCED: Scope for expired invitations
     */
    public function scopeExpired($query)
    {
        return $query->where(function($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere(function($q2) {
                  $q2->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                     ->where('expires_at', '<=', now());
              });
        });
    }

    /**
     * ✅ ENHANCED: Scope for accepted invitations
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED)
                    ->whereNotNull('accepted_at');
    }

    /**
     * ✅ NEW: Scope for revoked invitations
     */
    public function scopeRevoked($query)
    {
        return $query->where('status', self::STATUS_REVOKED);
    }

    /**
     * ✅ ENHANCED: Scope for invitations expiring soon
     */
    public function scopeExpiringSoon($query, ?int $days = null)
    {
        $days = $days ?? config('app.invitation_warning_days', 2);
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                    ->where('expires_at', '<=', now()->addDays($days))
                    ->where('expires_at', '>', now());
    }

    /**
     * ✅ NEW: Scope for corrupted invitations
     */
    public function scopeCorrupted($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                    ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, expires_at) < 24');
    }

    /**
     * ✅ ENHANCED: Get all invitation types - ALIGNED WITH CONTROLLER
     */
    public static function getInvitationTypes(): array
    {
        return [
            self::TYPE_WELCOME => 'Welcome Invitation',
            self::TYPE_REGISTRATION => 'Registration Invitation',
            self::TYPE_ACCOUNT_SETUP => 'Account Setup',
            self::TYPE_PASSWORD_SETUP => 'Password Setup',
        ];
    }

    /**
     * ✅ ENHANCED: Get all invitation statuses
     */
    public static function getInvitationStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REVOKED => 'Revoked',
        ];
    }

    /**
     * ✅ ENHANCED: Get all delivery channels - ALIGNED WITH CONTROLLER
     */
    public static function getDeliveryChannels(): array
    {
        return [
            self::CHANNEL_EMAIL => 'Email',
            self::CHANNEL_SMS => 'SMS',
            self::CHANNEL_WHATSAPP => 'WhatsApp',
        ];
    }

    /**
     * ✅ ENHANCED: Clean up expired invitations
     */
    public static function cleanupExpired(): int
    {
        if (!config('app.invitation_auto_expiry', true)) {
            Log::info('Auto-expiry disabled, skipping cleanup');
            return 0;
        }

        $expiredCount = self::whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
            ->where('expires_at', '<=', now())
            ->update([
                'status' => self::STATUS_EXPIRED,
                'metadata' => DB::raw("JSON_MERGE_PATCH(COALESCE(metadata, '{}'), JSON_OBJECT('auto_expired_at', NOW()))")
            ]);

        Log::info('Expired user invitations cleaned up', [
            'expired_count' => $expiredCount,
            'cleaned_at' => now()->toDateTimeString(),
            'auto_expiry_enabled' => true
        ]);

        return $expiredCount;
    }

    /**
     * ✅ ENHANCED: Get global invitation statistics - ALIGNED WITH CONTROLLER
     */
    public static function getGlobalStats(array $filters = []): array
    {
        $query = self::query();

        // Apply filters similar to controller
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['invitation_type'])) {
            $query->where('invitation_type', $filters['invitation_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $total = $query->count();
        $accepted = (clone $query)->where('status', self::STATUS_ACCEPTED)->count();
        $pending = (clone $query)->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])->count();
        $expired = (clone $query)->where('status', self::STATUS_EXPIRED)->count();
        $failed = (clone $query)->where('status', self::STATUS_FAILED)->count();
        $cancelled = (clone $query)->where('status', self::STATUS_CANCELLED)->count();
        $revoked = (clone $query)->where('status', self::STATUS_REVOKED)->count();

        $acceptanceRate = $total > 0 ? round(($accepted / $total) * 100, 2) : 0;

        return [
            'total_invitations' => $total,
            'accepted_invitations' => $accepted,
            'pending_invitations' => $pending,
            'expired_invitations' => $expired,
            'failed_invitations' => $failed,
            'cancelled_invitations' => $cancelled,
            'revoked_invitations' => $revoked,
            'acceptance_rate' => $acceptanceRate,
            'status_distribution' => [
                'accepted' => $accepted,
                'sent' => (clone $query)->where('status', self::STATUS_SENT)->count(),
                'pending' => (clone $query)->where('status', self::STATUS_PENDING)->count(),
                'expired' => $expired,
                'cancelled' => $cancelled,
                'failed' => $failed,
                'revoked' => $revoked,
            ],
            'config' => [
                'expiry_days' => config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS),
                'warning_days' => config('app.invitation_warning_days', 2),
                'auto_expiry_enabled' => config('app.invitation_auto_expiry', true),
                'resend_extends_expiry' => config('app.invitation_resend_extends_expiry', true)
            ]
        ];
    }

    /**
     * ✅ NEW: Fix all corrupted invitations
     */
    public static function fixCorruptedInvitations(): array
    {
        $corrupted = self::corrupted()->get();
        $fixedCount = 0;
        $failedCount = 0;

        foreach ($corrupted as $invitation) {
            if ($invitation->fixExpirationDate()) {
                $fixedCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'total_corrupted' => $corrupted->count(),
            'fixed' => $fixedCount,
            'failed' => $failedCount,
            'fixed_percentage' => $corrupted->count() > 0 ? 
                round(($fixedCount / $corrupted->count()) * 100, 2) : 0
        ];
    }

    /**
     * ✅ FIXED: Safe creation method for controller - TYPE ERROR RESOLVED
     */
    public static function createSafe(array $attributes): self
    {
        return DB::transaction(function () use ($attributes) {
            // Ensure token is generated
            if (empty($attributes['token'])) {
                $attributes['token'] = Str::random(self::TOKEN_LENGTH);
            }

            // ✅ FIXED: Ensure expiry is set - Ensure integer type
            if (empty($attributes['expires_at'])) {
                $expiryDays = (int) config('app.invitation_expiry_days', self::DEFAULT_EXPIRY_DAYS);
                $attributes['expires_at'] = now()->addDays($expiryDays);
            }

            $invitation = self::create($attributes);

            Log::info('User invitation created safely', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at,
                'safe_creation' => true
            ]);

            return $invitation;
        });
    }

    /**
     * ✅ NEW: Check if user can receive invitation - ALIGNED WITH CONTROLLER
     */
    public function canReceiveInvitation(): bool
    {
        $user = $this->user;
        
        if (!$user) return false;

        // User must be pending or active (for password resets)
        $validStatus = in_array($user->status, [User::STATUS_PENDING, User::STATUS_ACTIVE]);
        
        // Must have contact information
        $hasContact = !empty($user->email) || !empty($user->phone);
        
        return $validStatus && $hasContact && $this->is_active;
    }
}