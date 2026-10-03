<?php

namespace App\Http\Resources;

use App\Http\Resources\MissingValue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // ══════════════════════════════════════════════════════════════
        // Guard — null OR MissingValue → drop the entry entirely.
        //
        //   • MissingValue  → relation not eager-loaded (`whenLoaded`)
        //   • null          → relation eager-loaded but the FK is orphaned
        //                     (e.g. landlord_id points to a deleted user)
        //
        // Returning [] tells Laravel to omit the key from the parent
        // response. The Flutter client treats missing keys as null.
        // ══════════════════════════════════════════════════════════════
        if ($this->resource === null || $this->resource instanceof MissingValue) {
            return [];
        }

        // Resolve the flags once — used both in the response and to
        // gate `properties_count`.
        $isLandlord    = $this->resolveLandlordFlag();
        $isSuperAdmin  = $this->resolveSuperAdminFlag();

        return [
            // ── Core fields ──
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,

            // ── Roles ──
            'roles'        => $this->whenLoaded('roles', fn () => $this->formatRoles()),
            'primary_role' => $this->whenLoaded('primaryRole', fn () => $this->formatPrimaryRole()),

            // ── Flags — computed from type + loaded roles, NOT from
            //             raw attributes (which don't exist on the model)
            'is_landlord'       => $isLandlord,
            'is_super_admin'    => $isSuperAdmin,
            'is_phone_verified' => (bool) ($this->is_phone_verified ?? false),

            // ── Counts ──
            // Only included when `withCount('properties')` was applied AND
            // the user is a landlord. The count lives in the controller —
            // never fire an inline query here.
            'properties_count' => $this->when(
                $isLandlord && isset($this->properties_count),
                fn () => (int) $this->properties_count,
            ),

            // ── Timestamps ──
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // Flag resolvers
    // ═════════════════════════════════════════════════════════════════

    /**
     * Determine if the user is a landlord.
     *
     * Fast path 1: `type` column matches `User::TYPE_LANDLORD`.
     * Fast path 2: `roles` relation is loaded and contains `landlord`.
     * Fallback:    ask the model (`isLandlord()`), which may query.
     */
    private function resolveLandlordFlag(): bool
    {
        // 1. Type column — cheapest check.
        $type = $this->type ?? null;
        if ($type !== null) {
            // Handle both int and string representations.
            if ((int) $type === User::TYPE_LANDLORD) {
                return true;
            }
        }

        // 2. Already-loaded roles relation — no query.
        if ($this->relationLoaded('roles')) {
            $roles = $this->roles;
            if ($roles !== null) {
                foreach ($roles as $role) {
                    if (($role->slug ?? null) === 'landlord') {
                        return true;
                    }
                }
            }
            return false;
        }

        // 3. Fallback to the model method (may query).
        try {
            return (bool) $this->resource->isLandlord();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Determine if the user is a super admin.
     *
     * Fast path 1: `roles` relation is loaded and contains `super-admin`
     *              or `super_admin`.
     * Fallback:    ask the model (`isSuperAdmin()`), which may query.
     */
    private function resolveSuperAdminFlag(): bool
    {
        // 1. Already-loaded roles relation.
        if ($this->relationLoaded('roles')) {
            $roles = $this->roles;
            if ($roles !== null) {
                foreach ($roles as $role) {
                    $slug = $role->slug ?? null;
                    if ($slug === 'super-admin' || $slug === 'super_admin') {
                        return true;
                    }
                }
            }
            return false;
        }

        // 2. Fallback to the model method.
        try {
            return (bool) $this->resource->isSuperAdmin();
        } catch (Throwable) {
            return false;
        }
    }

    // ═════════════════════════════════════════════════════════════════
    // Roles formatting
    // ═════════════════════════════════════════════════════════════════

    /**
     * Format the user's roles. Returns an empty array when the relation
     * is null (never loaded, or genuinely empty).
     */
    private function formatRoles(): array
    {
        $roles = $this->roles;
        if ($roles === null) {
            return [];
        }

        return collect($roles)
            ->map(fn ($role) => [
                'id'           => $role->id,
                'name'         => $role->name,
                'display_name' => $role->display_name ?? $role->name,
                'slug'         => $role->slug ?? null,
                'metadata'     => $this->decodePivotMetadata($role),
                'assigned_at'  => $role->pivot->created_at ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Format the user's primary role (highest-priority assigned role).
     * Returns null when the relation is null.
     */
    private function formatPrimaryRole(): ?array
    {
        $role = $this->primaryRole;
        if ($role === null) {
            return null;
        }

        $pivot = $role->pivot ?? null;

        return [
            'id'           => $role->id,
            'name'         => $role->name,
            'display_name' => $role->display_name ?? $role->name,
            'slug'         => $role->slug ?? null,
            'description'  => $role->description ?? null,
            'priority'     => $role->priority ?? null,
            'is_default'   => (bool) ($role->is_default ?? false),
            'is_system'    => (bool) ($role->is_system ?? false),
            'permissions'  => $role->permissions ?? [],
            'metadata'     => $role->metadata ?? null,
            'pivot'        => $pivot ? [
                'user_id'              => $pivot->user_id ?? null,
                'role_id'              => $pivot->role_id ?? null,
                'assigned_by'          => $pivot->assigned_by ?? null,
                'assigned_at'          => $pivot->created_at ?? null,
                'expires_at'           => $pivot->expires_at ?? null,
                'is_active'            => (bool) ($pivot->is_active ?? true),
                'permissions_override' => $pivot->permissions_override ?? null,
            ] : null,
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // Pivot helpers
    // ═════════════════════════════════════════════════════════════════

    /**
     * Safely decode the pivot metadata for a role. Handles:
     *   • missing pivot
     *   • missing/nulled `metadata` attribute
     *   • JSON string (well-formed or not)
     *   • already-decoded array
     *
     * Never throws.
     */
    private function decodePivotMetadata(object $role): ?array
    {
        if (!isset($role->pivot)) {
            return null;
        }

        $meta = $role->pivot->metadata ?? null;

        if (is_array($meta)) {
            return $meta;
        }

        if (!is_string($meta) || $meta === '') {
            return null;
        }

        try {
            $decoded = json_decode($meta, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }
}