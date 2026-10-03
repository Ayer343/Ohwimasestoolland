<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Auth;  

class Role extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'priority',
        'display_name',
        'is_default',
        'is_system',
        'permissions',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => 'integer',
        'is_default' => 'boolean',
        'is_system' => 'boolean',
        'permissions' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be appended to arrays.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'user_count',
        'display_name',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'priority' => 0,
        'is_default' => false,
        'is_system' => false,
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Users belonging to this role
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user')
                    ->withPivot([
                        'id', 
                        'assigned_by', 
                        'assigned_at',
                        'expires_at',
                        'is_active',
                        'is_primary',
                        'metadata'
                    ])
                    ->withTimestamps()
                    ->wherePivot('deleted_at', null);
    }

    /**
     * Active users with this role
     */
    public function activeUsers()
    {
        return $this->belongsToMany(User::class, 'role_user')
                    ->wherePivot('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('role_user.expires_at')
                          ->orWhere('role_user.expires_at', '>', now());
                    })
                    ->wherePivot('deleted_at', null);
    }

    /**
     * User who created this role
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Role assignments (for pivot table access)
     */
    public function assignments()
    {
        return $this->hasMany(RoleUser::class, 'role_id');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get display name (capitalized name)
     */
    public function getDisplayNameAttribute(): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $this->name));
    }

    /**
     * Get user count attribute
     */
    public function getUserCountAttribute(): int
    {
        return Cache::remember("role.{$this->id}.user_count", 3600, function() {
            return $this->activeUsers()->count();
        });
    }

    // ==================== SCOPES ====================

    /**
     * Scope default roles
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope system roles
     */
    public function scopeSystemRoles($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope non-system roles
     */
    public function scopeNonSystemRoles($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope roles ordered by priority
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('name', 'asc');
    }

    // ==================== METHODS ====================

    /**
     * Find role by slug
     */
    public static function findBySlug($slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Find role by slug or fail
     */
    public static function findBySlugOrFail($slug): self
    {
        return static::where('slug', $slug)->firstOrFail();
    }

    /**
     * Get all available roles for assignment
     */
    public static function getAvailableForAssignment(): \Illuminate\Database\Eloquent\Collection
    {
        return static::ordered()->get();
    }

    /**
     * Get role options for dropdown
     */
    public static function getOptionsForDropdown($includeSystem = false): array
    {
        $query = static::ordered();
        
        if (!$includeSystem) {
            $query->nonSystemRoles();
        }
        
        return $query->get()->pluck('display_name', 'slug')->toArray();
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission($permission): bool
    {
        $permissions = $this->permissions ?? [];
        
        // Check direct permissions
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // Check wildcard
        if (in_array('*', $permissions)) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if role has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if role has all given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Grant permission to role
     */
    public function grantPermission($permission): bool
    {
        $permissions = $this->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            return $this->save();
        }
        
        return false;
    }

    /**
     * Revoke permission from role
     */
    public function revokePermission($permission): bool
    {
        $permissions = $this->permissions ?? [];
        
        if (($key = array_search($permission, $permissions)) !== false) {
            unset($permissions[$key]);
            $this->permissions = array_values($permissions);
            return $this->save();
        }
        
        return false;
    }

    /**
     * Clear role cache
     */
    public function clearCache(): void
    {
        Cache::forget("role.{$this->id}.user_count");
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name, '-');
            }
            
            if (auth()->check()) {
                $role->created_by = auth()->id();
            }
        });

        static::updating(function ($role) {
            if (auth()->check()) {
                $role->updated_by = auth()->id();
            }
            
            // Don't allow system roles to be modified by non-super admins
            if ($role->isDirty() && $role->is_system) {
                if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
                    throw new \Exception('System roles cannot be modified by non-super admins.');
                }
            }
        });

        static::deleting(function ($role) {
            // Prevent deletion of system roles
            if ($role->is_system) {
                if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
                    throw new \Exception('System roles cannot be deleted.');
                }
            }
            
            $role->clearCache();
        });

        static::saved(function ($role) {
            $role->clearCache();
        });
    }
}