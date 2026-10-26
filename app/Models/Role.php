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
        'priority'    => 'integer',
        'is_default'  => 'boolean',
        'is_system'   => 'boolean',
        'permissions' => 'array',
        'metadata'    => 'array',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    /**
     * The attributes that should be appended to arrays.
     *
     * ✅ FIX: `display_name` is cheap and safe to append. `user_count`
     *         fires a DB query per role (unless cached), so appending it
     *         unconditionally is an N+1. We keep it appended for BC with
     *         existing frontend code (it IS cached), but callers that
     *         don't need it should use `->makeHidden('user_count')` or
     *         `->withoutAppends('user_count')` on hot paths.
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
        'priority'   => 0,
        'is_default' => false,
        'is_system'  => false,
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
                        'metadata',
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
                    ->where(function ($q) {
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
     *
     * ✅ FIX: Null-safe. When the model was fetched with a partial select
     *         (e.g. `get(['slug', 'priority'])`), `$this->name` is null.
     *         Previously this called `str_replace(..., null)` which emits
     *         a deprecation in PHP 8.5+ and returned "" as display_name.
     *
     *         Resolution order:
     *           1. Explicit `display_name` column value (if present)
     *           2. Prettified `name` column
     *           3. Prettified `slug` column
     *           4. Empty string
     *
     *         We read from `$this->attributes` (not `$this->name`) so that
     *         accessors don't recurse or accidentally trigger `__get`.
     */
    public function getDisplayNameAttribute(): string
    {
        // 1. Explicit display_name column wins if it's a real column value
        $explicit = $this->attributes['display_name'] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        // 2. Prettify name, then slug — both guarded against null
        $source = $this->attributes['name']
            ?? $this->attributes['slug']
            ?? '';

        if (!is_string($source) || $source === '') {
            return '';
        }

        return ucwords(str_replace(['-', '_'], ' ', $source));
    }

    /**
     * Get user count attribute
     *
     * ✅ FIX: Return 0 if the model has no primary key (unsaved) or no id
     *         loaded (partial select). Cache key also becomes sensible.
     */
    public function getUserCountAttribute(): int
    {
        if (empty($this->id)) {
            return 0;
        }

        return (int) Cache::remember("role.{$this->id}.user_count", 3600, function () {
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
     * Resolve one or more role identifiers (IDs or slugs) into an array
     * of numeric role IDs suitable for `$user->roles()->sync()`.
     *
     * ✅ NEW: Used by UserManagementController::store() to accept BOTH
     *         legacy numeric IDs and the newer slug-based submission
     *         from the auto-role hidden input in the create blade.
     *
     * @param  array<int|string>  $identifiers
     * @return array<int>
     */
    public static function resolveToIds(array $identifiers): array
    {
        $numericIds = [];
        $slugs      = [];

        foreach ($identifiers as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $numericIds[] = (int) $value;
            } else {
                $slugs[] = (string) $value;
            }
        }

        if (!empty($slugs)) {
            $slugIds = static::whereIn('slug', $slugs)->pluck('id')->all();
            $numericIds = array_merge($numericIds, array_map('intval', $slugIds));
        }

        return array_values(array_unique(array_filter($numericIds)));
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

        if (in_array($permission, $permissions)) {
            return true;
        }

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
            $permissions[]      = $permission;
            $this->permissions  = $permissions;
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
        if (!empty($this->id)) {
            Cache::forget("role.{$this->id}.user_count");
        }
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            // ✅ FIX: Null-safe slug generation. `Str::slug(null)` can
            //         produce an empty string; fall back to a random
            //         suffix so the DB unique constraint doesn't blow up.
            if (empty($role->slug)) {
                $base = Str::slug((string) $role->name, '-');
                $role->slug = $base !== '' ? $base : 'role-' . Str::random(6);
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