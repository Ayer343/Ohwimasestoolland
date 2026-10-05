<?php
// app/Models/PropertyFamilyLink.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PropertyFamilyLink extends Model
{
    use SoftDeletes;

    /* ============================================================
       STATUS CONSTANTS
       ============================================================ */

    public const STATUS_PENDING_LANDLORD = 'pending_landlord_confirmation';
    public const STATUS_PENDING_ADMIN    = 'pending_admin_review';
    public const STATUS_PENDING          = 'pending'; // legacy — kept for BC
    public const STATUS_APPROVED         = 'approved';
    public const STATUS_REJECTED         = 'rejected';
    public const STATUS_REVOKED          = 'revoked';
    public const STATUS_CANCELLED        = 'cancelled';

    protected $fillable = [
        'property_id',
        'landlord_id',
        'linked_user_id',
        'proposed_name',
        'proposed_phone',
        'proposed_email',
        'relationship',
        'relationship_other',
        'permissions',
        'status',
        'landlord_notes',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'landlord_confirmed_at',
        'landlord_confirmed_by',
        'linked_at',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'permissions'          => 'array',
        'reviewed_at'          => 'datetime',
        'landlord_confirmed_at'=> 'datetime',
        'linked_at'            => 'datetime',
        'revoked_at'           => 'datetime',
    ];

    /* ============================================================
       RELATIONSHIPS
       ============================================================ */

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function landlordConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_confirmed_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /* ============================================================
       SCOPES
       ============================================================ */

    public function scopePendingLandlord(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING_LANDLORD);
    }

    public function scopePendingAdmin(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING_ADMIN);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_PENDING_LANDLORD,
            self::STATUS_PENDING_ADMIN,
            self::STATUS_PENDING, // legacy
        ]);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function scopeForProperty(Builder $q, int $propertyId): Builder
    {
        return $q->where('property_id', $propertyId);
    }

    /* ============================================================
       STATE HELPERS
       ============================================================ */

    public function isAwaitingLandlordConfirmation(): bool
    {
        return $this->status === self::STATUS_PENDING_LANDLORD;
    }

    public function isAwaitingAdminReview(): bool
    {
        return $this->status === self::STATUS_PENDING_ADMIN;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_LANDLORD,
            self::STATUS_PENDING_ADMIN,
            self::STATUS_PENDING,
        ], true);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    /* ============================================================
       ACCESSORS
       ============================================================ */

    public function getRelationshipLabelAttribute(): string
    {
        if ($this->relationship === 'other' && $this->relationship_other) {
            return $this->relationship_other;
        }
        return ucfirst($this->relationship);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->linkedUser?->name ?? $this->proposed_name;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_LANDLORD => 'Awaiting Landlord Confirmation',
            self::STATUS_PENDING_ADMIN    => 'Awaiting Admin Review',
            self::STATUS_PENDING          => 'Pending',
            self::STATUS_APPROVED         => 'Approved',
            self::STATUS_REJECTED         => 'Rejected',
            self::STATUS_REVOKED          => 'Revoked',
            self::STATUS_CANCELLED        => 'Cancelled',
            default                       => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}