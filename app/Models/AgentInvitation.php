<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AgentInvitation extends Model
{
    use HasFactory, SoftDeletes;

    // ✅ Status constants to match database schema
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';
    const STATUS_FAILED = 'failed';

    // ✅ Invitation method constants for multi-channel support
    const METHOD_SMS = 'sms';
    const METHOD_WHATSAPP = 'whatsapp';
    const METHOD_EMAIL = 'email';
    const METHOD_ALL_CHANNELS = 'all_channels';

    // ✅ Provider constants
    const PROVIDER_SYSTEM = 'system';
    const PROVIDER_TWILIO = 'twilio';
    const PROVIDER_VONAGE = 'vonage';
    const PROVIDER_WHATSAPP_BUSINESS = 'whatsapp_business';
    const PROVIDER_SENDGRID = 'sendgrid';
    const PROVIDER_MAILGUN = 'mailgun';

    protected $fillable = [
        'plan_id',
        'agent_id',
        'assignment_id',
        'token',
        'invitation_method',
        'expires_at',
        'status',
        'message',
        'provider',
        'resend_count',
        'delivery_attempts',
        'sent_at',
        'viewed_at',
        'accepted_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'failed_at',
        'failure_reason',
        'last_sent_at',
        'sent_from_ip',
        'accepted_from_ip',
        'accepted_user_agent',
        'security_code',
        'verification_attempts',
        'verified_at',
        'response_time_minutes',
        'metadata',
        'delivery_receipt',
        'sent_via',
        'channel_data',
        'email_sent_successfully',
        'email_sent_at',
        'email_address',
        'email_message',
        'email_error',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'metadata' => 'array',
        'delivery_receipt' => 'array',
        'channel_data' => 'array',
        'email_sent_successfully' => 'boolean',
    ];

    protected $appends = [
        'is_active',
        'days_until_expiry',
        'expiration_health',
        'can_resend',
        'invitation_url',
        'status_label',
        'invitation_method_display',
        'formatted_expiration_date',
        'delivery_status',
    ];

    /**
     * ✅ UPDATED: Boot method with enhanced token and expiration management
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Set default status if not provided
            if (empty($model->status)) {
                $model->status = self::STATUS_PENDING;
            }

            // Generate token if not provided
            if (empty($model->token)) {
                $model->token = Str::random(64);
            }

            // Set expiration date if not provided
            if (empty($model->expires_at)) {
                $expiryDays = (int) config('app.invitation_expiry_days', 7);
                $model->expires_at = now()->setTimezone('UTC')->addDays($expiryDays);
            }

            // Set sent_at if status is sent
            if ($model->status === self::STATUS_SENT && empty($model->sent_at)) {
                $model->sent_at = now()->setTimezone('UTC');
            }

            // Set last_sent_at if sending
            if ($model->status === self::STATUS_SENT) {
                $model->last_sent_at = now()->setTimezone('UTC');
            }

            // Set default invitation method
            if (empty($model->invitation_method)) {
                $model->invitation_method = self::METHOD_SMS;
            }

            // Set default provider
            if (empty($model->provider)) {
                $model->provider = self::PROVIDER_SYSTEM;
            }

            // ✅ NEW: Initialize resend_count if not set
            if (empty($model->resend_count)) {
                $model->resend_count = 0;
            }
        });

        static::updating(function ($model) {
            // Auto-update response_time_minutes when accepted
            if ($model->isDirty('status') && $model->status === self::STATUS_ACCEPTED) {
                if ($model->sent_at && empty($model->accepted_at)) {
                    $model->accepted_at = now()->setTimezone('UTC');
                    $model->response_time_minutes = $model->sent_at->diffInMinutes($model->accepted_at);
                }
            }

            // Update sent_at when status changes to sent
            if ($model->isDirty('status') && $model->status === self::STATUS_SENT) {
                $model->sent_at = $model->sent_at ?? now()->setTimezone('UTC');
                $model->last_sent_at = now()->setTimezone('UTC');
            }

            // ✅ FIXED: Auto-expire invitations if needed (only for sent/pending)
            if ($model->shouldBeExpired() && 
                in_array($model->status, [self::STATUS_SENT, self::STATUS_PENDING]) && 
                $model->status !== self::STATUS_EXPIRED) {
                $model->status = self::STATUS_EXPIRED;
            }
        });

        static::created(function ($model) {
            $model->logAction('created', 'Invitation created');
        });
    }

    /**
     * ✅ UPDATED: Safe creation method required by controller
     */
    public static function createSafe(array $attributes = [])
    {
        // Filter out non-fillable attributes to prevent mass assignment errors
        $fillableAttributes = array_intersect_key($attributes, array_flip((new static)->getFillable()));
        
        return static::create($fillableAttributes);
    }

    /**
     * ✅ UPDATED: Relationships
     */
    public function plan()
    {
        return $this->belongsTo(RegistrationPlan::class, 'plan_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function assignment()
    {
        return $this->belongsTo(PlanAgentAssignment::class, 'assignment_id');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function logs()
    {
        return $this->hasMany(AgentInvitationLog::class, 'invitation_id');
    }

    /**
     * ✅ UPDATED: Status check methods with enhanced logic
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isExpired(): bool
    {
        // If already marked as expired, return true
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        // Only check expiration for sent or pending invitations
        if (!$this->isSent() && !$this->isPending()) {
            return false;
        }

        // Check if expiration date exists and is in the past
        if ($this->expires_at) {
            return $this->expires_at->isPast();
        }

        return false;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    /**
     * ✅ CRITICAL: Method required by controller for safe status checking
     */
    public function getSafeStatus(): string
    {
        // If status is already expired, return it
        if ($this->status === self::STATUS_EXPIRED) {
            return self::STATUS_EXPIRED;
        }

        // Check if invitation should be expired but status hasn't been updated
        if ($this->shouldBeExpired()) {
            return 'should_be_expired';
        }

        // Return current status for all other cases
        return $this->status;
    }

    /**
     * ✅ CRITICAL: Method required by controller
     */
    public function shouldBeExpired(): bool
    {
        // Only check for sent or pending invitations
        if (!$this->isSent() && !$this->isPending()) {
            return false;
        }

        // Check if expiration date exists and is in the past
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * ✅ CRITICAL: Method required by controller
     */
    public function isActive(): bool
    {
        return ($this->isSent() || $this->isPending()) && 
               $this->expires_at && 
               $this->expires_at->isFuture();
    }

    /**
     * ✅ CRITICAL: Method required by controller
     */
    public function isExpiringSoon(int $warningDays = 2): bool
    {
        if (!$this->isActive() || !$this->expires_at) {
            return false;
        }

        $daysUntilExpiry = $this->getDaysUntilExpiry();
        return $daysUntilExpiry !== null && $daysUntilExpiry <= $warningDays;
    }

    /**
     * ✅ UPDATED: Accessors for enhanced functionality
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->getStatusText();
    }

    public function getStatusText(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_REVOKED => 'Revoked',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown'
        };
    }

    public function getInvitationMethodDisplayAttribute(): string
    {
        return $this->getInvitationMethodDisplay();
    }

    public function getInvitationMethodDisplay(): string
    {
        return match($this->invitation_method) {
            self::METHOD_SMS => 'SMS',
            self::METHOD_WHATSAPP => 'WhatsApp',
            self::METHOD_EMAIL => 'Email',
            self::METHOD_ALL_CHANNELS => 'Multiple Channels',
            default => ucfirst($this->invitation_method ?? 'Unknown')
        };
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->getDaysUntilExpiry();
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        
        $nowUtc = now()->setTimezone('UTC');
        $days = $nowUtc->diffInDays($this->expires_at, false);
        return $days >= 0 ? $days : 0;
    }

    public function getExpirationHealthAttribute(): string
    {
        return $this->getExpirationHealth();
    }

    public function getExpirationHealth(): string
    {
        if (!$this->expires_at) {
            return 'missing_expiration';
        }

        if ($this->isCorrupted()) {
            return 'corrupted';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        $daysUntilExpiry = $this->getDaysUntilExpiry();

        if ($daysUntilExpiry === null) {
            return 'unknown';
        }

        if ($daysUntilExpiry <= 0) {
            return 'expired';
        }

        if ($daysUntilExpiry <= 1) {
            return 'critical';
        }

        if ($daysUntilExpiry <= 3) {
            return 'warning';
        }

        return 'healthy';
    }

    public function getCanResendAttribute(): bool
    {
        return $this->canBeResent();
    }

    public function canBeResent(): bool
    {
        return ($this->isExpired() || $this->isFailed()) && 
               (!$this->resend_count || $this->resend_count < 5);
    }

    public function getInvitationUrlAttribute(): string
    {
        return $this->getInvitationUrl();
    }

    public function getInvitationUrl(): string
    {
        return route('agent.invitations.accept', $this->token);
    }

    public function getFormattedExpirationDateAttribute(): string
    {
        return $this->getFormattedExpirationDate();
    }

    public function getFormattedExpirationDate(): string
    {
        if (!$this->expires_at) {
            return 'Not set';
        }

        return $this->expires_at->format('M j, Y g:i A');
    }

    public function getDeliveryStatusAttribute(): string
    {
        return $this->getDeliveryStatus();
    }

    public function getDeliveryStatus(): string
    {
        if ($this->isAccepted()) {
            return 'Delivered & Accepted';
        }

        if ($this->isSent() && $this->viewed_at) {
            return 'Delivered & Viewed';
        }

        if ($this->isSent()) {
            return 'Delivered';
        }

        if ($this->isFailed()) {
            return 'Delivery Failed';
        }

        return 'Pending Delivery';
    }

    public function getResendCount(): int
    {
        return $this->resend_count ?? 0;
    }

    public function checkIfExpired(): bool
    {
        return $this->shouldBeExpired();
    }

    /**
     * ✅ CRITICAL: Token Management Methods required by controller
     */
    public function generateNewToken(): string
    {
        $newToken = Str::random(64);
        
        $this->update([
            'token' => $newToken,
            'last_sent_at' => now()->setTimezone('UTC'),
        ]);

        $this->logAction('token_regenerated', 'New security token generated');

        return $newToken;
    }

    public function validateTokenConsistency(string $expectedToken): array
    {
        $isConsistent = $this->token === $expectedToken;
        
        $validation = [
            'is_consistent' => $isConsistent,
            'database_token' => $this->token,
            'expected_token' => $expectedToken,
            'tokens_match' => $isConsistent,
            'invitation_url' => $this->getInvitationUrl(),
            'recommendation' => $isConsistent ? 'No action needed' : 'Token repair required'
        ];

        if (!$isConsistent) {
            $this->logAction('token_mismatch', 
                "Token mismatch detected. DB: {$this->token}, Expected: {$expectedToken}"
            );
        }

        return $validation;
    }

    public function repairTokenInconsistency(string $correctToken): bool
    {
        if ($this->token === $correctToken) {
            return true; // Already consistent
        }

        $success = $this->update([
            'token' => $correctToken,
            'metadata' => array_merge($this->metadata ?? [], [
                'token_repair' => [
                    'repaired_at_utc' => now()->setTimezone('UTC')->toISOString(),
                    'previous_token' => $this->token,
                    'new_token' => $correctToken,
                    'reason' => 'Token inconsistency repair'
                ]
            ])
        ]);

        if ($success) {
            $this->logAction('token_repaired', 'Token inconsistency repaired');
        }

        return $success;
    }

    /**
     * ✅ NEW: Channel-specific methods
     */
    public function isEmailInvitation(): bool
    {
        return in_array($this->invitation_method, [self::METHOD_EMAIL, self::METHOD_ALL_CHANNELS]);
    }

    public function isSmsInvitation(): bool
    {
        return in_array($this->invitation_method, [self::METHOD_SMS, self::METHOD_ALL_CHANNELS]);
    }

    public function isWhatsAppInvitation(): bool
    {
        return in_array($this->invitation_method, [self::METHOD_WHATSAPP, self::METHOD_ALL_CHANNELS]);
    }

    public function getUsedChannels(): array
    {
        if ($this->invitation_method === self::METHOD_ALL_CHANNELS) {
            return [self::METHOD_SMS, self::METHOD_WHATSAPP, self::METHOD_EMAIL];
        }

        return [$this->invitation_method];
    }

    public static function getInvitationMethods(): array
    {
        return [
            self::METHOD_SMS => 'SMS',
            self::METHOD_WHATSAPP => 'WhatsApp',
            self::METHOD_EMAIL => 'Email',
            self::METHOD_ALL_CHANNELS => 'All Channels',
        ];
    }

    public static function getProviders(): array
    {
        return [
            self::PROVIDER_SYSTEM => 'System',
            self::PROVIDER_TWILIO => 'Twilio',
            self::PROVIDER_VONAGE => 'Vonage',
            self::PROVIDER_WHATSAPP_BUSINESS => 'WhatsApp Business',
            self::PROVIDER_SENDGRID => 'SendGrid',
            self::PROVIDER_MAILGUN => 'Mailgun',
        ];
    }

    /**
     * ✅ CRITICAL: Debug and analytics methods required by controller
     */
    public function debugTimezoneIssues(): array
    {
        $rawExpiresAt = $this->getRawOriginal('expires_at');
        $rawCreatedAt = $this->getRawOriginal('created_at');
        $rawSentAt = $this->getRawOriginal('sent_at');

        return [
            'invitation_id' => $this->id,
            'status' => $this->status,
            'database_values' => [
                'expires_at_raw' => $rawExpiresAt,
                'created_at_raw' => $rawCreatedAt,
                'sent_at_raw' => $rawSentAt,
            ],
            'carbon_values' => [
                'expires_at_carbon' => $this->expires_at?->toISOString(),
                'created_at_carbon' => $this->created_at?->toISOString(),
                'sent_at_carbon' => $this->sent_at?->toISOString(),
            ],
            'timezone_info' => [
                'app_timezone' => config('app.timezone'),
                'database_timezone' => 'UTC',
                'current_time_utc' => now()->setTimezone('UTC')->toISOString(),
                'current_time_app' => now()->setTimezone(config('app.timezone'))->toISOString(),
            ],
            'expiration_analysis' => [
                'is_expired' => $this->isExpired(),
                'should_be_expired' => $this->shouldBeExpired(),
                'days_until_expiry' => $this->getDaysUntilExpiry(),
                'hours_until_expiry' => $this->expires_at ? now()->diffInHours($this->expires_at, false) : null,
                'is_active' => $this->isActive(),
            ],
            'corruption_detection' => [
                'is_corrupted' => $this->isCorrupted(),
                'hours_creation_to_expiry' => $this->getHoursFromCreationToExpiry(),
                'recommended_action' => $this->getExpirationFixRecommendation(),
            ]
        ];
    }

    public function getAnalyticsData(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->getStatusText(),
            'method' => $this->invitation_method ?? 'sms',
            'expires_at_utc' => $this->expires_at?->toISOString(),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            'expiration_health' => $this->getExpirationHealth(),
            'is_active' => $this->isActive(),
            'resend_count' => $this->getResendCount(),
            'delivery_attempts' => $this->delivery_attempts ?? 0,
            'response_time_minutes' => $this->response_time_minutes,
            'created_at_utc' => $this->created_at?->setTimezone('UTC')->toISOString(),
            'sent_at_utc' => $this->sent_at?->setTimezone('UTC')->toISOString(),
            'accepted_at_utc' => $this->accepted_at?->setTimezone('UTC')->toISOString(),
            'assignment_id' => $this->assignment_id,
            'agent_id' => $this->agent_id,
            'plan_id' => $this->plan_id,
            'is_corrupted' => $this->isCorrupted(),
            'safe_status' => $this->getSafeStatus(),
            'token_consistency' => $this->validateTokenConsistency($this->token),
        ];
    }

    public function isCorrupted(): bool
    {
        if (!$this->created_at || !$this->expires_at) {
            return false;
        }

        $hoursDifference = $this->created_at->diffInHours($this->expires_at);
        return $hoursDifference < 24; // Less than 1 day difference indicates corruption
    }

    public function getHoursFromCreationToExpiry(): ?int
    {
        if (!$this->created_at || !$this->expires_at) {
            return null;
        }

        return $this->created_at->diffInHours($this->expires_at);
    }

    public function getExpirationFixRecommendation(): string
    {
        if (!$this->expires_at) {
            return 'set_expiration';
        }

        if ($this->isCorrupted()) {
            return 'extend_expiration';
        }

        if ($this->shouldBeExpired() && $this->status !== self::STATUS_EXPIRED) {
            return 'mark_expired';
        }

        return 'no_action_needed';
    }

    public function validateExpiration(): array
    {
        $validation = [
            'is_valid' => true,
            'issues' => [],
            'details' => [],
            'requires_fix' => false
        ];

        if (!$this->expires_at) {
            $validation['is_valid'] = false;
            $validation['issues'][] = 'No expiration date set';
            $validation['requires_fix'] = true;
            return $validation;
        }

        $nowUtc = now()->setTimezone('UTC');
        $validation['details'] = [
            'expires_at_utc' => $this->expires_at->toISOString(),
            'current_time_utc' => $nowUtc->toISOString(),
            'app_timezone' => config('app.timezone'),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            'hours_until_expiry' => $nowUtc->diffInHours($this->expires_at, false),
            'is_future' => $this->expires_at->isFuture(),
            'is_past' => $this->expires_at->isPast(),
            'raw_database_value' => $this->getRawOriginal('expires_at')
        ];

        $issues = [];

        if ($this->expires_at->isPast() && ($this->isSent() || $this->isPending())) {
            $issues[] = 'Invitation is expired but status not updated';
            $validation['requires_fix'] = true;
        }

        if ($this->created_at && $this->expires_at) {
            $hoursSinceCreation = $this->created_at->diffInHours($this->expires_at);
            if ($hoursSinceCreation < 24) {
                $issues[] = "Expiration too short: {$hoursSinceCreation} hours from creation";
                $validation['requires_fix'] = true;
            }
        }

        // Check if expiration is set but status doesn't match
        if ($this->expires_at->isFuture() && $this->status === self::STATUS_EXPIRED) {
            $issues[] = 'Expiration date is in future but status is expired';
            $validation['requires_fix'] = true;
        }

        if (!empty($issues)) {
            $validation['issues'] = $issues;
            $validation['is_valid'] = false;
        }

        return $validation;
    }

    /**
     * ✅ CRITICAL: Action methods required by controller
     */
    public function markAsSent(string $method, ?string $provider = null, ?string $ipAddress = null): bool
    {
        $updateData = [
            'status' => self::STATUS_SENT,
            'invitation_method' => $method,
            'sent_at' => now()->setTimezone('UTC'),
            'last_sent_at' => now()->setTimezone('UTC'),
        ];

        if ($provider) {
            $updateData['provider'] = $provider;
        }

        if ($ipAddress && $this->hasColumn('sent_from_ip')) {
            $updateData['sent_from_ip'] = $ipAddress;
        }

        if ($this->hasColumn('delivery_attempts')) {
            $updateData['delivery_attempts'] = ($this->delivery_attempts ?? 0) + 1;
        }

        if ($this->hasColumn('metadata')) {
            $updateData['metadata'] = array_merge($this->metadata ?? [], [
                'sent_details' => [
                    'method' => $method,
                    'provider' => $provider,
                    'sent_at_utc' => now()->setTimezone('UTC')->toISOString(),
                    'ip_address' => $ipAddress,
                ]
            ]);
        }

        $success = $this->update($updateData);

        if ($success) {
            $this->logAction('sent', "Invitation sent via {$method}", null, $ipAddress);
        }

        return $success;
    }

    public function markAsAccepted(?string $ipAddress = null, ?string $userAgent = null): bool
    {
        if ($this->isExpired()) {
            $this->logAction('failed_acceptance', 'Attempt to accept expired invitation', null, $ipAddress);
            return false;
        }

        $updateData = [
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now()->setTimezone('UTC'),
        ];

        if ($ipAddress && $this->hasColumn('accepted_from_ip')) {
            $updateData['accepted_from_ip'] = $ipAddress;
        }

        if ($userAgent && $this->hasColumn('accepted_user_agent')) {
            $updateData['accepted_user_agent'] = $userAgent;
        }

        // Calculate response time
        if ($this->sent_at) {
            $updateData['response_time_minutes'] = $this->sent_at->diffInMinutes(now()->setTimezone('UTC'));
        }

        if ($this->hasColumn('metadata')) {
            $updateData['metadata'] = array_merge($this->metadata ?? [], [
                'accepted_details' => [
                    'accepted_at_utc' => now()->setTimezone('UTC')->toISOString(),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'response_time_minutes' => $updateData['response_time_minutes'] ?? null,
                ]
            ]);
        }

        $success = $this->update($updateData);

        if ($success) {
            $this->logAction('accepted', 'Invitation accepted', null, $ipAddress, $userAgent);
            
            // Update agent assignment last activity
            if ($this->assignment) {
                $this->assignment->update(['last_activity_at' => now()]);
            }
        }

        return $success;
    }

    public function markAsExpired(): bool
    {
        // ✅ FIXED: Only mark as expired if it's actually expired and currently sent/pending
        if (($this->isSent() || $this->isPending()) && $this->shouldBeExpired()) {
            $updateData = [
                'status' => self::STATUS_EXPIRED,
            ];

            if ($this->hasColumn('metadata')) {
                $updateData['metadata'] = array_merge($this->metadata ?? [], [
                    'auto_expired_at_utc' => now()->setTimezone('UTC')->toISOString(),
                    'original_expires_at_utc' => $this->expires_at?->toISOString(),
                ]);
            }

            $success = $this->update($updateData);

            if ($success) {
                $this->logAction('expired', 'Invitation expired automatically');
            }

            return $success;
        }

        return false;
    }

    public function markAsRevoked(int $revokedBy, ?string $reason = null): bool
    {
        if (!$this->isAccepted()) {
            $updateData = [
                'status' => self::STATUS_REVOKED,
                'revoked_at' => now()->setTimezone('UTC'),
                'revoked_by' => $revokedBy,
            ];

            if ($reason && $this->hasColumn('revocation_reason')) {
                $updateData['revocation_reason'] = $reason;
            }

            if ($this->hasColumn('metadata')) {
                $updateData['metadata'] = array_merge($this->metadata ?? [], [
                    'revoked_details' => [
                        'revoked_by_user_id' => $revokedBy,
                        'revoked_at_utc' => now()->setTimezone('UTC')->toISOString(),
                        'reason' => $reason,
                    ]
                ]);
            }

            $success = $this->update($updateData);

            if ($success) {
                $this->logAction('revoked', "Invitation revoked: {$reason}", $revokedBy);
            }

            return $success;
        }

        return false;
    }

    public function markAsFailed(?string $errorMessage = null): bool
    {
        $updateData = [
            'status' => self::STATUS_FAILED,
            'failed_at' => now()->setTimezone('UTC'),
        ];

        if ($errorMessage && $this->hasColumn('failure_reason')) {
            $updateData['failure_reason'] = $errorMessage;
        }

        if ($this->hasColumn('metadata')) {
            $updateData['metadata'] = array_merge($this->metadata ?? [], [
                'failed_details' => [
                    'failed_at_utc' => now()->setTimezone('UTC')->toISOString(),
                    'error_message' => $errorMessage,
                ]
            ]);
        }

        $success = $this->update($updateData);

        if ($success) {
            $this->logAction('failed', "Invitation failed: {$errorMessage}");
        }

        return $success;
    }

    public function resend(string $method, ?string $provider = null): bool
    {
        if (!$this->canBeResent()) {
            return false;
        }

        // Reset for resend
        $expiryDays = (int) config('app.invitation_expiry_days', 7);
        $newExpiresAt = now()->setTimezone('UTC')->addDays($expiryDays);
        
        $updateData = [
            'status' => self::STATUS_SENT,
            'token' => Str::random(64), // Generate new token for security
            'expires_at' => $newExpiresAt,
            'invitation_method' => $method,
            'last_sent_at' => now()->setTimezone('UTC'),
            'resend_count' => ($this->resend_count ?? 0) + 1,
            'delivery_attempts' => ($this->delivery_attempts ?? 0) + 1,
        ];

        if ($provider) {
            $updateData['provider'] = $provider;
        }

        if ($this->hasColumn('metadata')) {
            $updateData['metadata'] = array_merge($this->metadata ?? [], [
                'resend_details' => [
                    'resent_at_utc' => now()->setTimezone('UTC')->toISOString(),
                    'total_resends' => $updateData['resend_count'],
                    'new_expires_at_utc' => $newExpiresAt->toISOString(),
                    'method' => $method,
                    'provider' => $provider,
                ]
            ]);
        }

        $success = $this->update($updateData);

        if ($success) {
            $this->logAction('resent', "Invitation resent via {$method}");
        }

        return $success;
    }

    public function trackView(?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $updateData = [];

        if (!$this->viewed_at && $this->hasColumn('viewed_at')) {
            $updateData['viewed_at'] = now()->setTimezone('UTC');
        }

        if ($this->hasColumn('metadata')) {
            $metadataUpdates = [];

            if (!$this->viewed_at) {
                $metadataUpdates['first_viewed_at_utc'] = now()->setTimezone('UTC')->toISOString();
            }

            $viewHistory = $this->metadata['view_history'] ?? [];
            $viewHistory[] = [
                'viewed_at_utc' => now()->setTimezone('UTC')->toISOString(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'days_until_expiry' => $this->getDaysUntilExpiry(),
            ];
            
            $metadataUpdates['view_history'] = $viewHistory;
            $metadataUpdates['last_viewed_at_utc'] = now()->setTimezone('UTC')->toISOString();
            $metadataUpdates['total_views'] = count($viewHistory);

            $updateData['metadata'] = array_merge($this->metadata ?? [], $metadataUpdates);
        }

        $success = !empty($updateData) ? $this->update($updateData) : true;

        if ($success) {
            $this->logAction('viewed', 'Invitation viewed', null, $ipAddress, $userAgent);
        }

        return $success;
    }

    /**
     * ✅ CRITICAL: Method required by controller for expiration fixing
     */
    public function fixExpirationDate(): bool
    {
        // Fix corrupted expiration dates (less than 24 hours from creation)
        if ($this->isCorrupted() || 
            ($this->isSent() && $this->expires_at && $this->expires_at->isPast())) {
            
            $expiryDays = (int) config('app.invitation_expiry_days', 7);
            $newExpiration = now()->setTimezone('UTC')->addDays($expiryDays);
            
            $success = $this->update([
                'expires_at' => $newExpiration,
                'metadata' => array_merge($this->metadata ?? [], [
                    'fix_details' => [
                        'fixed_at_utc' => now()->setTimezone('UTC')->toISOString(),
                        'previous_expires_at_utc' => $this->expires_at?->toISOString(),
                        'reason' => $this->isCorrupted() ? 'corrupted' : 'expired'
                    ]
                ])
            ]);

            if ($success) {
                $this->logAction('fixed', 'Expiration date fixed');
            }

            return $success;
        }

        return false;
    }

    /**
     * ✅ NEW: Security code methods
     */
    public function generateSecurityCode(): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->update([
            'security_code' => $code,
            'verification_attempts' => 0,
            'verified_at' => null,
        ]);

        $this->logAction('security_code_sent', 'Security code generated');

        return $code;
    }

    public function verifySecurityCode(string $code): bool
    {
        if (!$this->security_code || $this->verified_at) {
            return false;
        }

        $isValid = $this->security_code === $code;
        
        $this->increment('verification_attempts');

        if ($isValid) {
            $this->update([
                'verified_at' => now()->setTimezone('UTC'),
            ]);
            $this->logAction('verified', 'Security code verified successfully');
        } else {
            $this->logAction('verification_failed', 'Security code verification failed');
        }

        return $isValid;
    }

    /**
     * ✅ UPDATED: Logging method with better error handling
     */
    public function logAction(string $action, ?string $details = null, ?int $userId = null, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        if (!$this->hasTable('agent_invitation_logs')) {
            return;
        }

        try {
            AgentInvitationLog::create([
                'invitation_id' => $this->id,
                'user_id' => $userId,
                'agent_id' => $this->agent_id,
                'plan_id' => $this->plan_id,
                'action' => $action,
                'details' => $details,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => now()->setTimezone('UTC'),
            ]);
        } catch (\Exception $e) {
            // Silently fail if logging table doesn't exist or there's an error
            // Consider logging this to a file if needed
        }
    }

    /**
     * ✅ UPDATED: Scope methods for multiple agent support
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                    ->where('expires_at', '>', now()->setTimezone('UTC'));
    }

    public function scopeExpired($query)
    {
        return $query->where(function($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere(function($q2) {
                  $q2->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                     ->where('expires_at', '<=', now()->setTimezone('UTC'));
              });
        });
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeRevoked($query)
    {
        return $query->where('status', self::STATUS_REVOKED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpiringSoon($query, int $days = 2)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                    ->whereBetween('expires_at', [
                        now()->setTimezone('UTC'), 
                        now()->setTimezone('UTC')->addDays($days)
                    ]);
    }

    public function scopeForPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForAssignment($query, $assignmentId)
    {
        return $query->where('assignment_id', $assignmentId);
    }

    public function scopeSentVia($query, string $method)
    {
        return $query->where('invitation_method', $method);
    }

    public function scopeWithExpirationIssues($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '<=', now()->setTimezone('UTC'));
                    });
    }

    public function scopeNeedsAttention($query)
    {
        return $query->where(function($q) {
            $q->where('status', self::STATUS_FAILED)
              ->orWhere('status', self::STATUS_EXPIRED)
              ->orWhere(function($q2) {
                  $q2->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                     ->where('expires_at', '<=', now()->setTimezone('UTC')->addHours(24));
              });
        });
    }

    /**
     * ✅ NEW: Email tracking methods
     */
    public function markEmailAsSent(string $emailAddress, ?string $message = null): bool
    {
        return $this->update([
            'email_sent_successfully' => true,
            'email_sent_at' => now()->setTimezone('UTC'),
            'email_address' => $emailAddress,
            'email_message' => $message,
            'sent_via' => self::METHOD_EMAIL,
        ]);
    }

    public function markEmailAsFailed(string $emailAddress, string $error): bool
    {
        return $this->update([
            'email_sent_successfully' => false,
            'email_error' => $error,
            'email_address' => $emailAddress,
            'status' => self::STATUS_FAILED,
            'failed_at' => now()->setTimezone('UTC'),
        ]);
    }

    /**
     * ✅ Database helper methods
     */
    private function hasColumn(string $column): bool
    {
        static $columnCache = [];

        if (!isset($columnCache[$column])) {
            try {
                $columnCache[$column] = Schema::hasColumn($this->getTable(), $column);
            } catch (\Exception $e) {
                $columnCache[$column] = false;
            }
        }

        return $columnCache[$column];
    }

    private function hasTable(string $table): bool
    {
        static $tableCache = [];

        if (!isset($tableCache[$table])) {
            try {
                $tableCache[$table] = Schema::hasTable($table);
            } catch (\Exception $e) {
                $tableCache[$table] = false;
            }
        }

        return $tableCache[$table];
    }
}