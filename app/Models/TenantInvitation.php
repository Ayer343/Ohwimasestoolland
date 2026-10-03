<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TenantInvitation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Invitation status constants.
     */
    const STATUS_PENDING   = 'pending';
    const STATUS_SENT      = 'sent';
    const STATUS_FAILED    = 'failed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Channel constants.
     */
    const CHANNEL_SMS      = 'sms';
    const CHANNEL_EMAIL    = 'email';
    const CHANNEL_WHATSAPP = 'whatsapp';

    /**
     * ✅ ADDED: Invitation type constants.
     *
     * Distinguishes what kind of invitation this is so different flows
     * can coexist in the same table without confusion.
     */
    const TYPE_LEASE_REVIEW   = 'lease_review';    // lease sent for tenant review + signature
    const TYPE_REGISTRATION   = 'registration';    // invite a tenant to register on the platform
    const TYPE_ONBOARDING     = 'onboarding';      // welcome / post-registration
    const TYPE_PROPERTY_INVITE = 'property_invite'; // property-scoped invitation
    const TYPE_GENERAL        = 'general';         // fallback / misc

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // ---------- Core relationships ----------
        'user_id',
        'tenant_id',             // legacy — kept for BC with older callers
        'property_id',
        'unit_id',               // ✅ ADDED: used by lease invitations
        'lease_id',              // ✅ ADDED: used by lease invitations
        'invited_by',

        // ---------- Invitation configuration ----------
        'invitation_type',       // ✅ ADDED: 'lease_review' | 'registration' | ...
        'channels',
        'sent_channels',
        'custom_message',

        // ---------- Token + lifecycle ----------
        'token',
        'status',
        'sent_at',
        'expires_at',
        'completed_at',
        'failure_reason',

        // ---------- Cancellation tracking ----------
        'cancelled_at',          // ✅ ADDED: was silently dropped before
        'cancelled_by',          // ✅ ADDED: was silently dropped before

        // ---------- Result ----------
        'registered_user_id',

        // ---------- Meta ----------
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'channels'      => 'array',
        'sent_channels' => 'array',
        'metadata'      => 'array',

        'sent_at'       => 'datetime',
        'expires_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'cancelled_at'  => 'datetime',  // ✅ ADDED
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'is_expired',
        'is_active',
        'days_until_expiry',
        'invitation_url',
        'formatted_channels',
        'formatted_sent_channels',  // ✅ ADDED: matches the accessor already defined
        'user_name',
        'property_name',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // On creating: set defaults for token, expiry, inviter, invitation_type
        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(60);
            }

            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7);
            }

            if (empty($invitation->invited_by) && auth()->check()) {
                $invitation->invited_by = auth()->id();
            }

            // ✅ ADDED: sensible default so legacy callers don't leave it null
            if (empty($invitation->invitation_type)) {
                $invitation->invitation_type = self::TYPE_GENERAL;
            }

            // ❌ REMOVED: dead legacy block that referenced `Tenant::find()`
            // (there is no Tenant model — tenants are User rows with type=tenant)
        });

        // On created: log for audit trail
        static::created(function ($invitation) {
            Log::info('Tenant invitation created', [
                'invitation_id'   => $invitation->id,
                'invitation_type' => $invitation->invitation_type,
                'user_id'         => $invitation->user_id,
                'property_id'     => $invitation->property_id,
                'unit_id'         => $invitation->unit_id,
                'lease_id'        => $invitation->lease_id,
                'invited_by'      => $invitation->invited_by,
                'channels'        => $invitation->channels,
                // Never log the raw token — it's a bearer credential
            ]);
        });

        // On retrieved: auto-flip to expired if the deadline has passed
        static::retrieved(function ($invitation) {
            if ($invitation->isExpired() && $invitation->status !== self::STATUS_EXPIRED) {
                $invitation->markAsExpired();
            }
        });
    }

    // ========== RELATIONSHIPS ==========

    /**
     * The user this invitation is addressed to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Legacy alias — some code may still call ->tenant().
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The property this invitation relates to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * ✅ ADDED: The property unit this invitation is for (lease invitations).
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    /**
     * ✅ ADDED: The rental agreement this invitation relates to.
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class, 'lease_id');
    }

    /**
     * The user who sent this invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * The user who eventually registered using this invitation.
     */
    public function registeredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }

    /**
     * ✅ ADDED: The user who cancelled this invitation.
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_SENT)
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere(function ($q2) {
                  $q2->where('status', self::STATUS_SENT)
                     ->where('expires_at', '<=', now());
              });
        });
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('expires_at', '>', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * ✅ ADDED: Filter by unit.
     */
    public function scopeForUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    /**
     * ✅ ADDED: Filter by lease.
     */
    public function scopeForLease($query, $leaseId)
    {
        return $query->where('lease_id', $leaseId);
    }

    /**
     * ✅ ADDED: Filter by invitation type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('invitation_type', $type);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->whereJsonContains('channels', $channel);
    }

    public function scopeForTenantUsers($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('type', User::TYPE_TENANT);
        });
    }

    // ========== STATE CHECKS ==========

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_SENT && !$this->isExpired();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return now()->diffInDays($this->expires_at, false);
    }

    // ========== URLS ==========

    public function getInvitationUrl(): string
    {
        return route('tenant.invitation.accept', ['token' => $this->token]);
    }

    public function getInvitationUrlAttribute(): string
    {
        return $this->getInvitationUrl();
    }

    // ========== FORMATTING ACCESSORS ==========

    public function getFormattedChannelsAttribute(): string
    {
        if (empty($this->channels)) {
            return 'None';
        }

        return collect($this->channels)
            ->map(fn ($channel) => match ($channel) {
                'sms'      => 'SMS',
                'email'    => 'Email',
                'whatsapp' => 'WhatsApp',
                default    => ucfirst($channel),
            })
            ->implode(', ');
    }

    public function getFormattedSentChannelsAttribute(): string
    {
        if (empty($this->sent_channels)) {
            return 'None';
        }

        return collect($this->sent_channels)
            ->map(fn ($channel) => match ($channel) {
                'sms'      => 'SMS',
                'email'    => 'Email',
                'whatsapp' => 'WhatsApp',
                default    => ucfirst($channel),
            })
            ->implode(', ');
    }

    public function getUserNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function getPropertyNameAttribute(): ?string
    {
        return $this->property?->property_name;
    }

    // ========== STATE TRANSITIONS ==========

    /**
     * Mark invitation as successfully sent.
     */
    public function markAsSent(array $sentChannels = []): bool
    {
        return $this->update([
            'status'         => self::STATUS_SENT,
            'sent_channels'  => $sentChannels,
            'sent_at'        => now(),
            'failure_reason' => null,
        ]);
    }

    /**
     * Mark invitation as failed.
     */
    public function markAsFailed(?string $reason = null): bool
    {
        return $this->update([
            'status'         => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'sent_at'        => now(),
        ]);
    }

    /**
     * Mark invitation as completed (e.g. tenant accepted / signed).
     */
    public function markAsCompleted(?User $registeredUser = null): bool
    {
        return $this->update([
            'status'             => self::STATUS_COMPLETED,
            'completed_at'       => now(),
            'registered_user_id' => $registeredUser?->id ?? $this->user_id,
        ]);
    }

    /**
     * Mark invitation as expired.
     */
    public function markAsExpired(): bool
    {
        return $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Mark invitation as cancelled.
     * ✅ FIXED: cancelled_at and cancelled_by are now in $fillable,
     *           so this actually persists them.
     */
    public function markAsCancelled(): bool
    {
        return $this->update([
            'status'       => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id() ?? $this->invited_by,
        ]);
    }

    /**
     * Resend invitation with a fresh token and extended expiry.
     * ✅ FIXED: rotates the token for security.
     */
    public function resend(array $channels = []): bool
    {
        $updateData = [
            'status'         => self::STATUS_PENDING,
            'sent_channels'  => null,
            'sent_at'        => null,
            'expires_at'     => now()->addDays(7),
            'failure_reason' => null,
            'token'          => Str::random(60),  // ✅ rotate
        ];

        if (!empty($channels)) {
            $updateData['channels'] = $channels;
        }

        $success = $this->update($updateData);

        if ($success) {
            Log::info('Tenant invitation resent', [
                'invitation_id'  => $this->id,
                'user_id'        => $this->user_id,
                'new_channels'   => $channels,
                'new_expires_at' => $this->expires_at,
            ]);
        }

        return $success;
    }

    public function canBeResent(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
            self::STATUS_PENDING,
        ]) || $this->isExpired();
    }

    // ========== STATISTICS ==========

    public static function getStats(): array
    {
        return [
            'total'                 => self::count(),
            'active'                => self::active()->count(),
            'pending'               => self::pending()->count(),
            'sent'                  => self::where('status', self::STATUS_SENT)->count(),
            'completed'             => self::completed()->count(),
            'failed'                => self::failed()->count(),
            'expired'               => self::expired()->count(),
            'cancelled'             => self::cancelled()->count(),
            'by_channel_sms'        => self::byChannel('sms')->count(),
            'by_channel_email'      => self::byChannel('email')->count(),
            'by_channel_whatsapp'   => self::byChannel('whatsapp')->count(),
        ];
    }

    public static function getUserStats($userId): array
    {
        return [
            'total'     => self::forUser($userId)->count(),
            'active'    => self::forUser($userId)->active()->count(),
            'completed' => self::forUser($userId)->completed()->count(),
            'pending'   => self::forUser($userId)->pending()->count(),
            'expired'   => self::forUser($userId)->expired()->count(),
        ];
    }

    public static function getPropertyStats($propertyId): array
    {
        return [
            'total'        => self::forProperty($propertyId)->count(),
            'active'       => self::forProperty($propertyId)->active()->count(),
            'completed'    => self::forProperty($propertyId)->completed()->count(),
            'pending'      => self::forProperty($propertyId)->pending()->count(),
            'expired'      => self::forProperty($propertyId)->expired()->count(),
            'unique_users' => self::forProperty($propertyId)->distinct('user_id')->count('user_id'),
        ];
    }

    /**
     * ✅ ADDED: Statistics filtered by invitation type.
     */
    public static function getStatsByType(?string $type = null): array
    {
        $query = $type ? self::ofType($type) : self::query();

        return [
            'type'      => $type ?? 'all',
            'total'     => (clone $query)->count(),
            'active'    => (clone $query)->active()->count(),
            'pending'   => (clone $query)->pending()->count(),
            'sent'      => (clone $query)->where('status', self::STATUS_SENT)->count(),
            'completed' => (clone $query)->completed()->count(),
            'failed'    => (clone $query)->failed()->count(),
            'expired'   => (clone $query)->expired()->count(),
            'cancelled' => (clone $query)->cancelled()->count(),
        ];
    }

    // ========== TOKEN VALIDATION ==========

    /**
     * Validate a token and return the matching invitation (or null).
     */
    public static function validateToken(string $token): ?self
    {
        $invitation = self::where('token', $token)->first();

        if (!$invitation) {
            Log::warning('Invalid tenant invitation token', ['token_prefix' => substr($token, 0, 8) . '...']);
            return null;
        }

        if ($invitation->isExpired()) {
            $invitation->markAsExpired();
            Log::info('Tenant invitation expired on validation', [
                'invitation_id' => $invitation->id,
            ]);
            return null;
        }

        if ($invitation->status === self::STATUS_COMPLETED) {
            Log::info('Tenant invitation already completed', ['invitation_id' => $invitation->id]);
            return null;
        }

        if ($invitation->status === self::STATUS_CANCELLED) {
            Log::info('Tenant invitation cancelled', ['invitation_id' => $invitation->id]);
            return null;
        }

        if ($invitation->status === self::STATUS_FAILED) {
            Log::info('Tenant invitation failed', ['invitation_id' => $invitation->id]);
            return null;
        }

        if (!$invitation->user || !$invitation->user->isTenant()) {
            Log::warning('Tenant invitation user not found or not a tenant', [
                'invitation_id' => $invitation->id,
                'user_id'       => $invitation->user_id,
                'user_type'     => $invitation->user?->type ?? 'none',
            ]);
            return null;
        }

        return $invitation;
    }

    // ========== FINDERS ==========

    public static function findByUserAndProperty($userId, $propertyId, $status = null): ?self
    {
        $query = self::where('user_id', $userId)
                    ->where('property_id', $propertyId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->latest()->first();
    }

    /**
     * ✅ ADDED: Find existing invitation for a specific lease.
     */
    public static function findByLease($leaseId, $status = null): ?self
    {
        $query = self::where('lease_id', $leaseId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->latest()->first();
    }

    /**
     * ✅ ADDED: Find active invitation for a specific lease.
     */
    public static function findActiveForLease($leaseId): ?self
    {
        return self::where('lease_id', $leaseId)
            ->where('status', self::STATUS_SENT)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    // ========== FACTORY HELPERS ==========

    /**
     * Create a generic invitation for a tenant user + property.
     */
    public static function createForUser(User $user, Property $property, array $channels = ['email'], ?User $invitedBy = null): self
    {
        if (!$user->isTenant()) {
            throw new \InvalidArgumentException('User must be a tenant to receive an invitation.');
        }

        // If an active invitation already exists for this user+property, throw
        $existingInvitation = self::findByUserAndProperty($user->id, $property->id, self::STATUS_SENT);
        if ($existingInvitation && $existingInvitation->isActive()) {
            throw new \Exception('An active invitation already exists for this user and property.');
        }

        return self::create([
            'user_id'         => $user->id,
            'property_id'     => $property->id,
            'invited_by'      => $invitedBy?->id ?? auth()->id(),
            'invitation_type' => self::TYPE_REGISTRATION,
            'channels'        => $channels,
            'status'          => self::STATUS_PENDING,
        ]);
    }

    /**
     * ✅ ADDED: Create a lease-review invitation scoped to a specific lease.
     * This is what `PropertyUnitLeaseController::sendLeaseToTenant()` should call.
     */
    public static function createForLease(
        RentalAgreement $lease,
        PropertyUnit $unit,
        array $channels = ['email'],
        ?User $invitedBy = null,
        ?array $metadata = null,
        int $expiresInDays = 14
    ): self {
        $tenant = $lease->tenant;

        if (!$tenant || !$tenant->isTenant()) {
            throw new \InvalidArgumentException('Lease does not have a valid tenant to invite.');
        }

        return self::create([
            'user_id'         => $tenant->id,
            'property_id'     => $unit->property_id,
            'unit_id'         => $unit->id,
            'lease_id'        => $lease->id,
            'invited_by'      => $invitedBy?->id ?? auth()->id(),
            'invitation_type' => self::TYPE_LEASE_REVIEW,
            'channels'        => $channels,
            'status'          => self::STATUS_PENDING,
            'expires_at'      => now()->addDays($expiresInDays),
            'metadata'        => $metadata,
        ]);
    }

    // ========== HISTORY ==========

    public static function getUserHistory($userId, $limit = 10)
    {
        return self::forUser($userId)
            ->with(['property', 'inviter'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getPropertyHistory($propertyId, $limit = 10)
    {
        return self::forProperty($propertyId)
            ->with(['user', 'inviter'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * ✅ ADDED: History for a specific lease.
     */
    public static function getLeaseHistory($leaseId, $limit = 10)
    {
        return self::forLease($leaseId)
            ->with(['user', 'inviter'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}