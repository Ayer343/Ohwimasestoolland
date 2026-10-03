<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoleUser extends Pivot
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'role_id',
        'assigned_by',
        'revoked_by',
        'assigned_via',
        'assigned_at',
        'revoked_at',
        'expires_at',
        'is_active',
        'is_primary',
        'context_type',
        'context_id',
        'metadata',
        'assignment_reason',
        'revocation_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns this assignment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the role that was assigned
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user who assigned this role
     */
    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user who revoked this role
     */
    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Check if this assignment is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if this assignment is active
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired() && !$this->trashed();
    }

    /**
     * Revoke this assignment
     */
    public function revoke(?string $reason = null): bool
    {
        $this->is_active = false;
        $this->revoked_at = now();
        $this->revoked_by = auth()->id();
        $this->revocation_reason = $reason;
        
        return $this->save();
    }

    /**
     * Extend expiration
     */
    public function extendExpiration($days): bool
    {
        if ($this->expires_at) {
            $this->expires_at = $this->expires_at->addDays($days);
            return $this->save();
        }
        
        return false;
    }
}