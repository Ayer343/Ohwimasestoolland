<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    use SoftDeletes;
    
    protected $table = 'roles';
    
    protected $fillable = [
        'name',           // 'Landlord'
        'slug',           // 'landlord'
        'description',    // 'Can own and manage properties'
        'priority',       // 1-100 (higher = more important)
        'is_default',     // Whether new users get this role
        'is_system',      // Whether role is system-protected
        'permissions',    // JSON array of permissions
        'metadata',       // Additional role metadata
    ];
    
    protected $casts = [
        'permissions' => 'array',
        'metadata' => 'array',
        'priority' => 'integer',
        'is_default' => 'boolean',
        'is_system' => 'boolean',
        'deleted_at' => 'datetime',
    ];
    
    // ==================== RELATIONSHIPS ====================
    
    /**
     * Users who have this role
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot([
                'id', 'assigned_by', 'assigned_at', 'updated_at',
                'expires_at', 'is_active', 'status', 'metadata',
                'permissions_override', 'notes', 'deleted_at'
            ])
            ->withTimestamps()
            ->wherePivot('deleted_at', null);
    }
    
    /**
     * Active users with this role (not expired, not suspended)
     */
    public function activeUsers()
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('expires_at', 'status')
            ->wherePivot('is_active', true)
            ->wherePivot('status', 'active')
            ->where(function($q) {
                $q->whereNull('role_user.expires_at')
                  ->orWhere('role_user.expires_at', '>', now());
            });
    }
    
    /**
     * Role assignments (pivot records)
     */
    public function assignments()
    {
        return $this->hasMany(RoleUser::class, 'role_id');
    }
    
    // ==================== SCOPES ====================
    
    /**
     * Scope for active roles only
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
    
    /**
     * Scope for default roles
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
    
    /**
     * Scope for system roles
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
    
    /**
     * Scope ordered by priority
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('name');
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }
    
    /**
     * Grant permission to role
     */
    public function grantPermission(string $permission): self
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
            $this->clearPermissionsCache();
        }
        return $this;
    }
    
    /**
     * Revoke permission from role
     */
    public function revokePermission(string $permission): self
    {
        $permissions = array_filter($this->permissions ?? [], function($p) use ($permission) {
            return $p !== $permission;
        });
        $this->update(['permissions' => array_values($permissions)]);
        $this->clearPermissionsCache();
        return $this;
    }
    
    /**
     * Clear permissions cache for all users with this role
     */
    protected function clearPermissionsCache(): void
    {
        $cacheKey = "role_permissions_{$this->id}";
        Cache::forget($cacheKey);
        
        // Clear individual user caches
        foreach ($this->users as $user) {
            Cache::forget("user_permissions_{$user->id}");
        }
    }
}