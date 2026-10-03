<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LandlordInvitation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'landlord_id',
        'token',
        'invited_by',
        'channels',
        'status',
        'sent_at',
        'accepted_at',
        'expires_at',
        'invitation_type',
        'custom_message',
        'attempts',
        'last_attempt_at',
        'failure_reason',
        'metadata', // ✅ Stores additional invitation metadata
        
        // ✅ NEW: Added tracking columns
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
        'ip_address',
        'user_agent',
        
        // ✅ NEW: Added communication tracking
        'sms_message_id',
        'sms_delivery_status',
        'email_message_id'
    ];

    protected $casts = [
        'channels' => 'array',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'attempts' => 'integer',
        'metadata' => 'array', // ✅ Cast metadata to array
        
        // ✅ NEW: Added casts for new columns
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer'
    ];

    protected $appends = [
        'expires_in_human',
        'time_until_expiration',
        'days_until_expiration',
        'status_color',
        'status_text',
        'invitation_type_text',
        'acceptance_time_in_hours',
        'response_time_human',
        'channels_list',
        'is_active',
        'is_expired',
        'is_accepted',
        'is_cancelled',
        'can_be_resent',
        
        // ✅ NEW: Added appends for new functionality
        'expiry_warning',
        'sms_delivery_status_text',
        'delivery_status_color',
        'has_been_viewed',
        'view_count_label',
        'engagement_score'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Invitation type constants
    const TYPE_REGISTRATION = 'registration';
    const TYPE_PROPERTY_ADDED = 'property_added';
    const TYPE_WELCOME = 'welcome';
    
    // ✅ NEW: SMS delivery status constants
    const SMS_STATUS_PENDING = 'pending';
    const SMS_STATUS_DELIVERED = 'delivered';
    const SMS_STATUS_FAILED = 'failed';
    const SMS_STATUS_UNDELIVERED = 'undelivered';

    /**
     * Generate a secure invitation token
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Relationship with Property
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Relationship with Landlord
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Relationship with Inviter
     */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Scope for active invitations
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_SENT)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for expired invitations
     */
    public function scopeExpired($query)
    {
        return $query->where(function($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere(function($q2) {
                  $q2->where('status', self::STATUS_SENT)
                     ->where('expires_at', '<=', now());
              });
        });
    }

    /**
     * Scope for sent invitations
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for failed invitations
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for cancelled invitations
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope for accepted invitations
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope for invitations by property
     */
    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Scope for invitations by landlord
     */
    public function scopeForLandlord($query, $landlordId)
    {
        return $query->where('landlord_id', $landlordId);
    }

    /**
     * Scope for invitations that can be resent
     */
    public function scopeCanResend($query)
    {
        return $query->where(function($q) {
            $q->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_FAILED,
                self::STATUS_EXPIRED
            ])->orWhere(function($q2) {
                $q2->where('status', self::STATUS_SENT)
                   ->where('expires_at', '<=', now());
            });
        });
    }

    /**
     * Scope for invitations expiring soon
     */
    public function scopeExpiringSoon($query, $hours = 24)
    {
        return $query->where('status', self::STATUS_SENT)
                    ->where('expires_at', '>', now())
                    ->where('expires_at', '<=', now()->addHours($hours));
    }

    /**
     * Scope for recent invitations
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for invitations by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('invitation_type', $type);
    }
    
    /**
     * ✅ NEW: Scope for invitations that have been viewed
     */
    public function scopeViewed($query)
    {
        return $query->where('view_count', '>', 0);
    }
    
    /**
     * ✅ NEW: Scope for invitations with SMS tracking
     */
    public function scopeWithSmsTracking($query)
    {
        return $query->whereNotNull('sms_message_id');
    }
    
    /**
     * ✅ NEW: Scope for invitations by delivery status
     */
    public function scopeBySmsStatus($query, $status)
    {
        return $query->where('sms_delivery_status', $status);
    }

    /**
     * Check if invitation is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_SENT && 
               $this->expires_at->isFuture();
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || 
               ($this->status === self::STATUS_SENT && $this->expires_at->isPast());
    }

    /**
     * Check if invitation can be resent
     */
    public function canBeResent(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED
        ]) || ($this->status === self::STATUS_SENT && $this->expires_at->isPast());
    }

    /**
     * Check if invitation is pending sending
     */
    public function isPendingSending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if invitation was accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if invitation is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if invitation is revocable
     */
    public function isRevocable(): bool
    {
        return !in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if invitation is revocable
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 0;
        }

        return $this->expires_at->diffInDays(now());
    }

    /**
     * Check if invitation is valid (for blade template)
     */
    public function isValid(): bool
    {
        return $this->isActive() && !$this->isAccepted() && !$this->isCancelled();
    }
    
    /**
     * ✅ NEW: Check if invitation has been viewed
     */
    public function hasBeenViewed(): bool
    {
        return $this->view_count > 0;
    }
    
    /**
     * ✅ NEW: Check if SMS was delivered
     */
    public function isSmsDelivered(): bool
    {
        return $this->sms_delivery_status === self::SMS_STATUS_DELIVERED;
    }
    
    /**
     * ✅ NEW: Check if email has tracking ID
     */
    public function hasEmailTracking(): bool
    {
        return !empty($this->email_message_id);
    }

    /**
     * Mark invitation as sent
     */
    public function markAsSent(array $channels = []): bool
    {
        return $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'channels' => !empty($channels) ? $channels : $this->channels,
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
            'failure_reason' => null
        ]);
    }

    /**
     * Mark invitation as accepted
     */
    public function markAsAccepted(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now()
        ]);
    }

    /**
     * Mark invitation as expired
     */
    public function markAsExpired(): bool
    {
        return $this->update([
            'status' => self::STATUS_EXPIRED
        ]);
    }

    /**
     * Mark invitation as failed
     */
    public function markAsFailed(?string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now()
        ]);
    }

    /**
     * Mark invitation as cancelled
     */
    public function markAsCancelled(): bool
    {
        return $this->update([
            'status' => self::STATUS_CANCELLED
        ]);
    }
    
    /**
     * ✅ NEW: Track invitation view
     */
    public function trackView(string $ipAddress, string $userAgent): bool
    {
        $updateData = [
            'view_count' => $this->view_count + 1,
            'last_viewed_at' => now(),
            'user_agent' => $userAgent
        ];
        
        if (!$this->first_viewed_at) {
            $updateData['first_viewed_at'] = now();
            $updateData['ip_address'] = $ipAddress;
        }
        
        return $this->update($updateData);
    }
    
    /**
     * ✅ NEW: Update SMS delivery status
     */
    public function updateSmsDeliveryStatus(string $status, ?string $messageId = null): bool
    {
        $updateData = [
            'sms_delivery_status' => $status
        ];
        
        if ($messageId) {
            $updateData['sms_message_id'] = $messageId;
        }
        
        return $this->update($updateData);
    }
    
    /**
     * ✅ NEW: Update email message ID
     */
    public function updateEmailMessageId(string $messageId): bool
    {
        return $this->update([
            'email_message_id' => $messageId
        ]);
    }

    /**
     * Revoke invitation (alias for markAsCancelled)
     */
    public function revoke(): bool
    {
        return $this->markAsCancelled();
    }

    /**
     * Resend invitation with token regeneration option
     */
    public function resend(array $channels = [], bool $regenerateToken = false): bool
    {
        $updateData = [
            'status' => self::STATUS_PENDING,
            'channels' => !empty($channels) ? $channels : $this->channels,
            'failure_reason' => null,
            'sent_at' => null,
            'metadata' => array_merge($this->metadata ?? [], [
                'last_resend_at' => now()->toISOString(),
                'resend_count' => ($this->metadata['resend_count'] ?? 0) + 1,
                'previous_status' => $this->status
            ])
        ];

        if ($regenerateToken) {
            $updateData['token'] = self::generateToken();
            $updateData['metadata']['token_regenerated'] = true;
            $updateData['metadata']['previous_token'] = $this->token;
        }

        return $this->update($updateData);
    }

    /**
     * Regenerate token for this invitation
     */
    public function regenerateToken(): bool
    {
        return $this->update([
            'token' => self::generateToken(),
            'status' => self::STATUS_PENDING,
            'sent_at' => null,
            'attempts' => 0,
            'failure_reason' => null,
            'metadata' => array_merge($this->metadata ?? [], [
                'token_regenerated_at' => now()->toISOString(),
                'previous_token' => $this->token
            ])
        ]);
    }

    /**
     * Get invitation URL
     */
    public function getInvitationUrl(): string
    {
        if (empty($this->token)) {
            $this->regenerateToken();
            $this->refresh();
        }
        
        return route('landlord.invitations.accept', ['token' => $this->token]);
    }

    /**
     * Get expiration date in human readable format
     */
    public function getExpiresInHumanAttribute(): string
    {
        if (!$this->expires_at) {
            return 'No expiry date';
        }
        return $this->expires_at->diffForHumans();
    }

    /**
     * Get time until expiration
     */
    public function getTimeUntilExpirationAttribute(): string
    {
        if (!$this->expires_at) {
            return 'No expiry date';
        }
        
        if ($this->expires_at->isPast()) {
            return 'Expired';
        }
        
        return $this->expires_at->diffForHumans();
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }
        
        return max(0, $this->expires_at->diffInDays(now()));
    }

    /**
     * Get invitation status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_SENT => 'info',
            self::STATUS_ACCEPTED => 'success',
            self::STATUS_EXPIRED => 'secondary',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'dark',
            default => 'secondary'
        };
    }

    /**
     * Get status text for display
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Get invitation type text for display
     */
    public function getInvitationTypeTextAttribute(): string
    {
        return match($this->invitation_type) {
            self::TYPE_REGISTRATION => 'Registration',
            self::TYPE_PROPERTY_ADDED => 'Property Added',
            self::TYPE_WELCOME => 'Welcome',
            default => 'Unknown'
        };
    }

    /**
     * Check if invitation was accepted within a certain timeframe
     */
    public function wasAcceptedWithinHours(int $hours): bool
    {
        if (!$this->accepted_at) {
            return false;
        }
        
        return $this->accepted_at->diffInHours(now()) <= $hours;
    }

    /**
     * Get acceptance time in hours
     */
    public function getAcceptanceTimeInHoursAttribute(): ?float
    {
        if (!$this->sent_at || !$this->accepted_at) {
            return null;
        }
        
        return $this->sent_at->diffInHours($this->accepted_at);
    }

    /**
     * Get response time in human readable format
     */
    public function getResponseTimeHumanAttribute(): ?string
    {
        if (!$this->sent_at || !$this->accepted_at) {
            return null;
        }
        
        return $this->sent_at->diffForHumans($this->accepted_at, true);
    }

    /**
     * Check if invitation has available channels
     */
    public function hasAvailableChannels(): bool
    {
        return !empty($this->channels) && is_array($this->channels);
    }

    /**
     * Get channels as comma-separated string
     */
    public function getChannelsListAttribute(): string
    {
        if (empty($this->channels) || !is_array($this->channels)) {
            return 'None';
        }
        
        return implode(', ', array_map('strtoupper', $this->channels));
    }

    /**
     * Get is_active attribute (for blade templates)
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    /**
     * Get is_expired attribute (for blade templates)
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    /**
     * Get is_accepted attribute (for blade templates)
     */
    public function getIsAcceptedAttribute(): bool
    {
        return $this->isAccepted();
    }

    /**
     * Get is_cancelled attribute (for blade templates)
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->isCancelled();
    }

    /**
     * Get can_be_resent attribute (for blade templates)
     */
    public function getCanBeResentAttribute(): bool
    {
        return $this->canBeResent();
    }

    /**
     * Get invitation expiry warning status
     */
    public function getExpiryWarningAttribute(): ?string
    {
        if (!$this->expires_at || $this->expires_at->isPast()) {
            return null;
        }

        $daysRemaining = $this->expires_at->diffInDays(now());
        
        if ($daysRemaining <= 1) {
            return 'danger';
        } elseif ($daysRemaining <= 3) {
            return 'warning';
        } elseif ($daysRemaining <= 7) {
            return 'info';
        }
        
        return null;
    }
    
    /**
     * ✅ NEW: Get SMS delivery status text
     */
    public function getSmsDeliveryStatusTextAttribute(): string
    {
        return match($this->sms_delivery_status) {
            self::SMS_STATUS_DELIVERED => 'Delivered',
            self::SMS_STATUS_FAILED => 'Failed',
            self::SMS_STATUS_UNDELIVERED => 'Undelivered',
            self::SMS_STATUS_PENDING => 'Pending',
            default => 'Not Sent'
        };
    }
    
    /**
     * ✅ NEW: Get delivery status color
     */
    public function getDeliveryStatusColorAttribute(): string
    {
        return match($this->sms_delivery_status) {
            self::SMS_STATUS_DELIVERED => 'success',
            self::SMS_STATUS_FAILED => 'danger',
            self::SMS_STATUS_UNDELIVERED => 'warning',
            self::SMS_STATUS_PENDING => 'info',
            default => 'secondary'
        };
    }
    
    /**
     * ✅ NEW: Get has_been_viewed attribute
     */
    public function getHasBeenViewedAttribute(): bool
    {
        return $this->hasBeenViewed();
    }
    
    /**
     * ✅ NEW: Get view count label
     */
    public function getViewCountLabelAttribute(): string
    {
        return match(true) {
            $this->view_count === 0 => 'Not Viewed',
            $this->view_count === 1 => 'Viewed Once',
            $this->view_count <= 3 => 'Viewed Few Times',
            $this->view_count <= 10 => 'Viewed Multiple Times',
            default => 'Viewed Many Times'
        };
    }
    
    /**
     * ✅ NEW: Get engagement score (0-100)
     */
    public function getEngagementScoreAttribute(): int
    {
        $score = 0;
        
        // Points for viewing
        if ($this->view_count > 0) $score += 20;
        if ($this->view_count > 1) $score += 10;
        
        // Points for quick response (within 24 hours)
        if ($this->first_viewed_at && $this->sent_at) {
            $hoursToView = $this->first_viewed_at->diffInHours($this->sent_at);
            if ($hoursToView <= 24) $score += 30;
            if ($hoursToView <= 1) $score += 20;
        }
        
        // Points for multiple views
        if ($this->view_count >= 5) $score += 20;
        
        // Points for acceptance
        if ($this->is_accepted) $score = 100;
        
        return min(100, $score);
    }

    /**
     * Get metadata value with default
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Update metadata value
     */
    public function updateMetadata(array $data): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata = array_merge($metadata, $data);
        
        return $this->update(['metadata' => $metadata]);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            // Generate token automatically if not provided
            if (empty($invitation->token)) {
                $invitation->token = self::generateToken();
            }
            
            // Set default expiry if not provided
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(config('app.invitation_expiry_days', 7));
            }
            
            // Set default status if not provided
            if (empty($invitation->status)) {
                $invitation->status = self::STATUS_PENDING;
            }
            
            // Set default invitation type if not provided
            if (empty($invitation->invitation_type)) {
                $invitation->invitation_type = self::TYPE_REGISTRATION;
            }

            // Set default attempts if not provided
            if (empty($invitation->attempts)) {
                $invitation->attempts = 0;
            }

            // Set invited_by if not provided and user is authenticated
            if (empty($invitation->invited_by) && auth()->check()) {
                $invitation->invited_by = auth()->id();
            }

            // Initialize metadata if not provided
            if (empty($invitation->metadata)) {
                $invitation->metadata = [
                    'created_via' => 'system',
                    'initial_created_at' => now()->toISOString(),
                    'resend_count' => 0
                ];
            }
            
            // ✅ NEW: Initialize new tracking fields
            if (empty($invitation->view_count)) {
                $invitation->view_count = 0;
            }
        });

        // Auto-expire invitations when they pass expiry date
        static::saving(function ($invitation) {
            if ($invitation->isDirty('expires_at') || $invitation->isDirty('status')) {
                if ($invitation->status === self::STATUS_SENT && $invitation->expires_at->isPast()) {
                    $invitation->status = self::STATUS_EXPIRED;
                }
            }
        });

        // Log important changes
        static::updated(function ($invitation) {
            if ($invitation->isDirty('status')) {
                \Log::info("Landlord invitation status changed", [
                    'invitation_id' => $invitation->id,
                    'old_status' => $invitation->getOriginal('status'),
                    'new_status' => $invitation->status,
                    'property_id' => $invitation->property_id,
                    'landlord_id' => $invitation->landlord_id,
                    'token' => substr($invitation->token, 0, 10) . '...'
                ]);
            }
        });

        // Clean up old expired invitations
        static::deleting(function ($invitation) {
            if ($invitation->isForceDeleting()) {
                \Log::info("Landlord invitation permanently deleted", [
                    'invitation_id' => $invitation->id,
                    'property_id' => $invitation->property_id,
                    'landlord_id' => $invitation->landlord_id
                ]);
            }
        });
    }

    /**
     * Find invitation by token
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)->first();
    }

    /**
     * Get valid invitation by token with expiry check
     */
    public static function findValidByToken(string $token): ?self
    {
        $invitation = self::findByToken($token);
        
        if (!$invitation) {
            return null;
        }

        // Auto-mark as expired if past expiry date
        if ($invitation->status === self::STATUS_SENT && $invitation->expires_at->isPast()) {
            $invitation->markAsExpired();
            return null;
        }
        
        if (!$invitation->isActive()) {
            return null;
        }
        
        return $invitation;
    }
    
    /**
     * ✅ NEW: Find and track view by token
     */
    public static function findAndTrackView(string $token, string $ipAddress, string $userAgent): ?self
    {
        $invitation = self::findByToken($token);
        
        if ($invitation && $invitation->isActive()) {
            $invitation->trackView($ipAddress, $userAgent);
            return $invitation;
        }
        
        return null;
    }

    /**
     * Find invitations by landlord
     */
    public static function findByLandlord(int $landlordId)
    {
        return self::where('landlord_id', $landlordId)->get();
    }

    /**
     * Find active invitations by landlord
     */
    public static function findActiveByLandlord(int $landlordId)
    {
        return self::where('landlord_id', $landlordId)
            ->active()
            ->get();
    }

    /**
     * Find latest invitation for property and landlord
     */
    public static function findLatestForPropertyAndLandlord(int $propertyId, int $landlordId): ?self
    {
        return self::where('property_id', $propertyId)
            ->where('landlord_id', $landlordId)
            ->latest()
            ->first();
    }

    /**
     * Check if active invitation exists for property and landlord
     */
    public static function hasActiveInvitation(int $propertyId, int $landlordId): bool
    {
        return self::where('property_id', $propertyId)
            ->where('landlord_id', $landlordId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Create invitation with proper defaults
     */
    public static function createInvitation(array $data): self
    {
        $defaults = [
            'expires_at' => now()->addDays(config('app.invitation_expiry_days', 7)),
            'status' => self::STATUS_PENDING,
            'invitation_type' => self::TYPE_REGISTRATION,
            'attempts' => 0,
            'metadata' => [
                'created_via' => 'system',
                'initial_created_at' => now()->toISOString(),
                'resend_count' => 0
            ],
            'view_count' => 0
        ];

        $data = array_merge($defaults, $data);

        return self::create($data);
    }

    /**
     * Get invitation statistics for dashboard
     */
    public static function getDashboardStats(): array
    {
        $total = self::count();
        $pending = self::pending()->count();
        $sent = self::sent()->count();
        $accepted = self::accepted()->count();
        $expired = self::expired()->count();
        $failed = self::failed()->count();
        $cancelled = self::cancelled()->count();
        $expiringSoon = self::expiringSoon()->count();
        
        // ✅ NEW: Added engagement metrics
        $viewed = self::viewed()->count();
        $smsDelivered = self::bySmsStatus(self::SMS_STATUS_DELIVERED)->count();
        $avgViews = $total > 0 ? round(self::sum('view_count') / $total, 2) : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'sent' => $sent,
            'accepted' => $accepted,
            'expired' => $expired,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'expiring_soon' => $expiringSoon,
            'viewed' => $viewed,
            'sms_delivered' => $smsDelivered,
            'avg_views' => $avgViews,
            'acceptance_rate' => $sent > 0 ? round(($accepted / $sent) * 100, 2) : 0,
            'success_rate' => $total > 0 ? round((($accepted + $sent) / $total) * 100, 2) : 0,
            'response_rate' => $sent > 0 ? round((($accepted + $expired) / $sent) * 100, 2) : 0,
            'view_rate' => $sent > 0 ? round(($viewed / $sent) * 100, 2) : 0,
        ];
    }

    /**
     * Get statistics by invitation type
     */
    public static function getStatsByType(): array
    {
        return self::groupBy('invitation_type')
            ->select('invitation_type', \DB::raw('count(*) as count'))
            ->get()
            ->pluck('count', 'invitation_type')
            ->toArray();
    }

    /**
     * Get acceptance rate by channel
     */
    public static function getAcceptanceRateByChannel(): array
    {
        $results = [];
        
        // Get all unique channels used
        $allChannels = self::whereNotNull('channels')->pluck('channels')->flatten()->unique();
        
        foreach ($allChannels as $channel) {
            $total = self::whereJsonContains('channels', $channel)->count();
            $accepted = self::whereJsonContains('channels', $channel)
                ->where('status', self::STATUS_ACCEPTED)
                ->count();
                
            $results[$channel] = [
                'total' => $total,
                'accepted' => $accepted,
                'rate' => $total > 0 ? round(($accepted / $total) * 100, 2) : 0
            ];
        }
        
        return $results;
    }

    /**
     * Clean up expired invitations
     */
    public static function cleanupExpired(): array
    {
        $expired = self::expired()->where('status', '!=', self::STATUS_EXPIRED)->get();
        
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0
        ];
        
        foreach ($expired as $invitation) {
            try {
                $results['processed']++;
                if ($invitation->markAsExpired()) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
            }
        }
        
        return $results;
    }

    /**
     * Get invitations that need to be sent (pending status)
     */
    public static function getPendingSend()
    {
        return self::pending()->get();
    }

    /**
     * Get invitations expiring soon (within 24 hours)
     */
    public static function getExpiringSoon()
    {
        return self::active()
            ->where('expires_at', '<=', now()->addHours(24))
            ->get();
    }

    /**
     * Get invitations that failed and can be retried
     */
    public static function getFailedRetryable()
    {
        return self::failed()
            ->where('attempts', '<', 3) // Max 3 attempts
            ->where('last_attempt_at', '<=', now()->subHours(1)) // Wait at least 1 hour between attempts
            ->get();
    }

    /**
     * Get invitations that need follow-up (sent but not responded)
     */
    public static function getNeedsFollowUp($days = 3)
    {
        return self::sent()
            ->where('sent_at', '<=', now()->subDays($days))
            ->where('accepted_at', null)
            ->get();
    }

    /**
     * Get successful invitations for a property
     */
    public static function getSuccessfulForProperty($propertyId)
    {
        return self::where('property_id', $propertyId)
            ->where('status', self::STATUS_ACCEPTED)
            ->get();
    }
    
    /**
     * ✅ NEW: Get invitations with low engagement
     */
    public static function getLowEngagement($days = 2)
    {
        return self::sent()
            ->where('sent_at', '<=', now()->subDays($days))
            ->where('view_count', 0)
            ->where('accepted_at', null)
            ->get();
    }

    /**
     * Convert to array for API responses
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'landlord_id' => $this->landlord_id,
            'token' => $this->token,
            'invitation_url' => $this->getInvitationUrl(),
            'status' => $this->status,
            'status_text' => $this->status_text,
            'status_color' => $this->status_color,
            'invitation_type' => $this->invitation_type,
            'invitation_type_text' => $this->invitation_type_text,
            'expires_at' => $this->expires_at?->toISOString(),
            'expires_in_human' => $this->expires_in_human,
            'days_until_expiration' => $this->days_until_expiration,
            'sent_at' => $this->sent_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'response_time_human' => $this->response_time_human,
            'acceptance_time_hours' => $this->acceptance_time_in_hours,
            'attempts' => $this->attempts,
            'channels' => $this->channels,
            'channels_list' => $this->channels_list,
            'custom_message' => $this->custom_message,
            'failure_reason' => $this->failure_reason,
            'last_attempt_at' => $this->last_attempt_at?->toISOString(),
            'invited_by' => $this->invited_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // ✅ NEW: Added new fields
            'metadata' => $this->metadata,
            'first_viewed_at' => $this->first_viewed_at?->toISOString(),
            'last_viewed_at' => $this->last_viewed_at?->toISOString(),
            'view_count' => $this->view_count,
            'view_count_label' => $this->view_count_label,
            'has_been_viewed' => $this->has_been_viewed,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'sms_message_id' => $this->sms_message_id,
            'sms_delivery_status' => $this->sms_delivery_status,
            'sms_delivery_status_text' => $this->sms_delivery_status_text,
            'delivery_status_color' => $this->delivery_status_color,
            'email_message_id' => $this->email_message_id,
            'engagement_score' => $this->engagement_score,
            'expiry_warning' => $this->expiry_warning,
            
            // Boolean attributes
            'is_active' => $this->is_active,
            'is_expired' => $this->is_expired,
            'is_accepted' => $this->is_accepted,
            'is_cancelled' => $this->is_cancelled,
            'can_be_resent' => $this->can_be_resent,
            
            // Relationships (loaded when needed)
            'property' => $this->relationLoaded('property') ? [
                'id' => $this->property->id,
                'property_name' => $this->property->property_name,
                'registration_pattern' => $this->property->registration_pattern,
                'street_name' => $this->property->street_name,
                'zone' => $this->property->zone,
                'house_number' => $this->property->house_number,
                'block_number' => $this->property->block_number
            ] : null,
            
            'landlord' => $this->relationLoaded('landlord') ? [
                'id' => $this->landlord->id,
                'name' => $this->landlord->name,
                'email' => $this->landlord->email,
                'phone' => $this->landlord->phone
            ] : null,
            
            'inviter' => $this->relationLoaded('inviter') ? [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
                'email' => $this->inviter->email
            ] : null
        ];
    }

    /**
     * Convert to simple array for dropdowns/lists
     */
    public function toSimpleArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'invitation_type' => $this->invitation_type,
            'expires_at' => $this->expires_at?->toISOString(),
            'property_name' => $this->property->property_name ?? 'Unknown Property',
            'landlord_name' => $this->landlord->name ?? 'Unknown Landlord',
            'is_active' => $this->is_active,
            'can_be_resent' => $this->can_be_resent,
            'view_count' => $this->view_count,
            'has_been_viewed' => $this->has_been_viewed
        ];
    }

    /**
     * Convert to array for invitation acceptance page
     */
    public function toAcceptanceArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_accepted' => $this->isAccepted(),
            'is_cancelled' => $this->isCancelled(),
            'custom_message' => $this->custom_message,
            
            // ✅ NEW: Added tracking info
            'first_viewed_at' => $this->first_viewed_at?->toISOString(),
            'view_count' => $this->view_count,
            'has_been_viewed' => $this->has_been_viewed,
            
            'property' => [
                'id' => $this->property->id,
                'property_name' => $this->property->property_name,
                'registration_pattern' => $this->property->registration_pattern,
                'street_name' => $this->property->street_name,
                'zone' => $this->property->zone,
                'house_number' => $this->property->house_number,
                'block_number' => $this->property->block_number
            ],
            
            'landlord' => [
                'id' => $this->landlord->id,
                'name' => $this->landlord->name,
                'email' => $this->landlord->email,
                'phone' => $this->landlord->phone
            ]
        ];
    }
}