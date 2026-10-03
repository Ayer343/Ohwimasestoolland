<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoleUser extends Model
{
    use SoftDeletes;
    
    protected $table = 'role_user';
    
    protected $fillable = [
        'role_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'updated_at',
        'expires_at',
        'is_active',
        'status',
        'metadata',
        'permissions_override',
        'notes',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'permissions_override' => 'array',
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_active' => 'boolean',
    ];
    
    // ==================== RELATIONSHIPS ====================
    
    /**
     * The role being assigned
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    
    /**
     * The user receiving the role
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Admin who assigned this role
     */
    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
    
    // ==================== SCOPES ====================
    
    /**
     * Scope for active assignments only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
    
    /**
     * Scope for expired assignments
     */
    public function scopeExpired($query)
    {
        return $query->where(function($q) {
            $q->where('expires_at', '<=', now())
              ->orWhere('status', 'expired');
        });
    }
    
    /**
     * Scope by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Check if assignment is currently active
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active 
            && $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at > now());
    }
    
    /**
     * Check if assignment is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at <= now();
    }
    
    /**
     * Get days remaining until expiration
     */
    public function daysRemaining(): ?int
    {
        if (!$this->expires_at) return null;
        if ($this->isExpired()) return 0;
        
        return now()->diffInDays($this->expires_at);
    }
    
    /**
     * Activate the role assignment
     */
    public function activate(): bool
    {
        return $this->update([
            'is_active' => true,
            'status' => 'active',
            'updated_at' => now(),
        ]);
    }
    
    /**
     * Suspend the role assignment
     */
    public function suspend(?string $reason = null): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata['suspension'] = [
            'suspended_at' => now()->toISOString(),
            'suspended_by' => auth()->id(),
            'reason' => $reason,
        ];
        
        return $this->update([
            'is_active' => false,
            'status' => 'suspended',
            'metadata' => $metadata,
            'updated_at' => now(),
        ]);
    }
    
    /**
     * Revoke the role assignment permanently
     */
    public function revoke(?string $reason = null): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata['revocation'] = [
            'revoked_at' => now()->toISOString(),
            'revoked_by' => auth()->id(),
            'reason' => $reason,
        ];
        
        return $this->update([
            'is_active' => false,
            'status' => 'revoked',
            'metadata' => $metadata,
            'updated_at' => now(),
        ]);
    }
    
    /**
     * Get effective permissions (role permissions + overrides)
     */
    public function getEffectivePermissions(): array
    {
        $rolePermissions = $this->role->permissions ?? [];
        $overrides = $this->permissions_override ?? [];
        
        // Merge with overrides (overrides take precedence)
        return array_merge($rolePermissions, $overrides);
    }
}