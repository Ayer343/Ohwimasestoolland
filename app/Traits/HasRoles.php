<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\RoleUser;
use Illuminate\Support\Facades\Log;

/**
 * Role assignment, checking, syncing, and permission caching.
 *
 * Behaviour identical to the in-model implementation it replaces.
 * Assumes the host model uses User::TYPE_* constants and TYPE_SLUG_MAP.
 */
trait HasRoles
{
    /* ============================================================
     | RELATIONSHIPS
     * ============================================================ */

    public function roleAssignments()
    {
        return $this->hasMany(RoleUser::class);
    }

    public function activeRoleAssignments()
    {
        return $this->hasMany(RoleUser::class)
            ->whereNull('role_user.deleted_at')
            ->where('role_user.is_active', true)
            ->where(function ($q) {
                $q->whereNull('role_user.expires_at')
                  ->orWhere('role_user.expires_at', '>', now());
            });
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->using(RoleUser::class)
            ->withPivot([
                'id', 'assigned_by', 'assigned_at', 'expires_at',
                'is_active', 'metadata', 'permissions_override',
            ])
            ->wherePivotNull('role_user.deleted_at')
            ->wherePivot('is_active', true)
            ->where(function ($q) {
                $q->whereNull('role_user.expires_at')
                  ->orWhere('role_user.expires_at', '>', now());
            });
    }

    public function allRolesWithTrashed()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->using(RoleUser::class)
            ->withPivot(['status', 'expires_at', 'is_active', 'deleted_at'])
            ->withTrashed();
    }

    /* ============================================================
     | CHECKS
     * ============================================================ */

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    public function hasAllRoles(array $roleSlugs): bool
    {
        $userRoles = $this->roles()->pluck('slug')->all();

        return empty(array_diff($roleSlugs, $userRoles));
    }

    /* ============================================================
     | ASSIGN / REMOVE / SYNC
     * ============================================================ */

    public function assignRole($role, array $options = []): RoleUser
    {
        $roleModel = is_numeric($role)
            ? Role::findOrFail($role)
            : Role::where('slug', $role)->firstOrFail();

        $existing = $this->roleAssignments()
            ->where('role_id', $roleModel->id)
            ->whereNull('deleted_at')
            ->first();

        if ($existing && method_exists($existing, 'isCurrentlyActive') && $existing->isCurrentlyActive()) {
            Log::info('User already has active role', [
                'user_id' => $this->id,
                'role'    => $roleModel->name,
            ]);
            return $existing;
        }

        if ($existing) {
            $existing->update([
                'is_active'  => true,
                'status'     => 'active',
                'expires_at' => $options['expires_at'] ?? null,
            ]);
            $assignment = $existing;
        } else {
            $assignment = $this->roleAssignments()->create([
                'role_id'              => $roleModel->id,
                'assigned_by'          => $options['assigned_by'] ?? auth()->id(),
                'assigned_at'          => $options['assigned_at'] ?? now(),
                'expires_at'           => $options['expires_at'] ?? null,
                'is_active'            => true,
                'status'               => 'active',
                'metadata'             => $options['metadata'] ?? [],
                'permissions_override' => $options['permissions_override'] ?? [],
                'notes'                => $options['notes'] ?? null,
            ]);
        }

        $this->syncLegacyTypeFromRoles();
        $this->clearPermissionCache();

        return $assignment;
    }

    public function removeRole($role): bool
    {
        $roleModel = is_numeric($role)
            ? Role::find($role)
            : Role::where('slug', $role)->first();

        if (!$roleModel) {
            return false;
        }

        // Guard: cannot remove landlord role while user still owns properties
        if ($roleModel->slug === 'landlord' && method_exists($this, 'isPropertyOwner') && $this->isPropertyOwner()) {
            Log::warning('Cannot remove landlord role — user still owns properties', [
                'user_id'        => $this->id,
                'property_count' => $this->ownedProperties()->count(),
            ]);

            throw new \RuntimeException('Cannot remove landlord role. User still owns properties. Please transfer ownership first.');
        }

        $assignment = $this->roleAssignments()->where('role_id', $roleModel->id)->first();

        if (!$assignment) {
            return false;
        }

        $result = $assignment->revoke('Removed by user/admin');

        $this->syncLegacyTypeFromRoles();
        $this->clearPermissionCache();

        return (bool) $result;
    }

    public function syncRoles(array $roleSlugs): array
    {
        $current = $this->getRoleSlugsAttribute();
        $results = ['added' => [], 'removed' => [], 'kept' => []];

        foreach (array_diff($current, $roleSlugs) as $slug) {
            try {
                $this->removeRole($slug);
                $results['removed'][] = $slug;
            } catch (\Throwable $e) {
                Log::warning('Could not remove role during sync', [
                    'user_id' => $this->id,
                    'role'    => $slug,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        foreach (array_diff($roleSlugs, $current) as $slug) {
            $this->assignRole($slug);
            $results['added'][] = $slug;
        }

        $results['kept'] = array_values(array_intersect($current, $roleSlugs));

        $this->clearPermissionCache();

        return $results;
    }

    public function ensureLandlordRole(?int $assignedBy = null, string $reason = 'Property ownership'): bool
    {
        if ($this->hasRole('landlord')) {
            Log::info('User already has landlord role', [
                'user_id'        => $this->id,
                'user_name'      => $this->name,
                'existing_roles' => $this->getRoleSlugsAttribute(),
            ]);
            return true;
        }

        try {
            $this->assignRole('landlord', [
                'assigned_by' => $assignedBy ?? auth()->id(),
                'assigned_at' => now(),
                'notes'       => 'Landlord role automatically assigned due to property ownership',
                'metadata'    => [
                    'assigned_via'      => 'ensure_landlord_role_method',
                    'user_type'         => $this->type,
                    'user_roles_before' => $this->getRoleSlugsAttribute(),
                ],
            ]);

            Log::info('Landlord role assigned to multi-role user', [
                'user_id'     => $this->id,
                'user_name'   => $this->name,
                'user_type'   => $this->type,
                'assigned_by' => $assignedBy ?? auth()->id(),
                'new_roles'   => $this->getRoleSlugsAttribute(),
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('Failed to assign landlord role', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /* ============================================================
     | LEGACY TYPE <-> ROLE SYNC
     * ============================================================ */

    public function syncRolesFromLegacyType(): void
    {
        $slug = static::TYPE_SLUG_MAP[$this->type] ?? null;

        if (!$slug) {
            return;
        }

        $role = Role::where('slug', $slug)->first();

        if ($role && !$this->hasRole($slug)) {
            $this->roles()->attach($role->id, [
                'assigned_by'       => auth()->id() ?? 1,
                'assigned_at'       => now(),
                'is_active'         => true,
                'status'            => 'active',
                'assignment_reason' => 'Auto-synced from legacy user type',
            ]);

            Log::info("Auto-assigned role '{$slug}' from legacy type", [
                'user_id'     => $this->id,
                'user_name'   => $this->name,
                'legacy_type' => $this->type,
            ]);
        }
    }

    public function syncLegacyTypeFromRoles(): void
    {
        $primaryRole = $this->getPrimaryRoleAttribute();

        if (!$primaryRole) {
            return;
        }

        $newType = array_search($primaryRole->slug, static::TYPE_SLUG_MAP, true);

        if ($newType !== false && $this->type != $newType) {
            $old = $this->type;
            $this->type = $newType;
            $this->saveQuietly();

            Log::info('Updated legacy type from roles', [
                'user_id'      => $this->id,
                'old_type'     => $old,
                'new_type'     => $newType,
                'primary_role' => $primaryRole->slug,
            ]);
        }
    }

    public function ensureRoleConsistency(): void
    {
        $this->syncRolesFromLegacyType();
        $this->syncLegacyTypeFromRoles();
    }

    /* ============================================================
     | PERMISSIONS
     * ============================================================ */

    public function hasPermission(string $permission): bool
    {
        $cacheKey = "user_permission_{$this->id}_{$permission}";
        $indexKey = "user_permissions_index_{$this->id}";

        return cache()->remember($cacheKey, 3600, function () use ($permission, $indexKey, $cacheKey) {
            $index = cache()->get($indexKey, []);

            if (!in_array($cacheKey, $index, true)) {
                $index[] = $cacheKey;
                cache()->put($indexKey, $index, 3600);
            }

            foreach ($this->roles as $role) {
                if (in_array($permission, $role->permissions ?? [], true)) {
                    return true;
                }

                $assignment = $this->roleAssignments()->where('role_id', $role->id)->first();

                if ($assignment && in_array($permission, $assignment->permissions_override ?? [], true)) {
                    return true;
                }
            }

            return false;
        });
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function clearPermissionCache(): void
    {
        $indexKey = "user_permissions_index_{$this->id}";
        $index    = cache()->get($indexKey, []);

        foreach ($index as $cacheKey) {
            cache()->forget($cacheKey);
        }

        cache()->forget($indexKey);
    }

    /* ============================================================
     | ACCESSORS
     * ============================================================ */

    public function getPrimaryRoleAttribute(): ?Role
    {
        return $this->roles()->orderByDesc('priority')->first();
    }

    public function getPrimaryRoleNameAttribute(): string
    {
        return $this->getPrimaryRoleAttribute()?->display_name ?? $this->getTypeName();
    }

    public function getRoleSlugsAttribute(): array
    {
        return $this->roles()->pluck('slug')->all();
    }

    public function getRoleNamesAttribute(): string
    {
        return $this->roles()->pluck('name')->implode(', ');
    }

    public function getAllRolesAttribute(): array
    {
        return $this->roles->map(fn ($role) => [
            'id'          => $role->id,
            'name'        => $role->name,
            'slug'        => $role->slug,
            'priority'    => $role->priority,
            'assigned_at' => $role->pivot->assigned_at,
            'expires_at'  => $role->pivot->expires_at,
        ])->all();
    }

    /* ============================================================
     | SCOPES
     * ============================================================ */

    public function scopeWithRole($query, string $roleSlug)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('slug', $roleSlug));
    }

    public function scopeWithAnyRole($query, array $roleSlugs)
    {
        return $query->whereHas('roles', fn ($q) => $q->whereIn('slug', $roleSlugs));
    }

    public function scopeLandlords($query)
    {
        return $query->where(function ($q) {
            $q->where('type', static::TYPE_LANDLORD)
              ->orWhereHas('roles', fn ($r) => $r->where('slug', 'landlord'));
        });
    }

    public function scopeContractors($query)
    {
        return $query->where(function ($q) {
            $q->where('type', static::TYPE_CONTRACTOR)
              ->orWhereHas('roles', fn ($r) => $r->where('slug', 'contractor'));
        });
    }

    public function scopeSupervisorCapable($query)
    {
        return $query->where(function ($q) {
            $q->where('type', static::TYPE_SECURITY_PERSONNEL)
              ->orWhereHas('roles', fn ($r) => $r->whereIn('slug', ['security-personnel', 'supervisor', 'admin']));
        })
        ->where('status', static::STATUS_ACTIVE)
        ->where('can_be_supervisor', true)
        ->where('supervisor_level', '>', static::SUPERVISOR_LEVEL_NONE);
    }

    public function scopeEligibleSupervisors($query)
    {
        return $query->supervisorCapable();
    }

    public function scopeActiveSupervisors($query)
    {
        return $query->whereHas('activeSupervisorAssignments');
    }

    public function scopeNonSupervisors($query)
    {
        return $query->whereDoesntHave('activeSupervisorAssignments');
    }
}