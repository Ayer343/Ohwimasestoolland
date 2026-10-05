<?php

namespace App\Http\Traits;

use App\Models\Property;
use App\Models\User;
use App\Models\PlanAgentAssignment;
use App\Models\PropertyFamilyLink;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

trait PropertyAuthorizationTrait
{
    /* ================================================================
       PROPERTY FAMILY LINK — PERMISSION HELPERS
       ================================================================ */

    /**
     * Return the approved family-link row for the current user on this
     * property, or null if none exists.
     *
     * Cached per request via a static map so that controllers calling
     * canViewProperty() → canUpdateProperty() → canDeleteProperty() in
     * sequence don't re-query the DB for the same pair.
     */
    protected function getFamilyLinkForProperty(Property $property): ?PropertyFamilyLink
    {
        static $cache = [];

        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $key = $property->id . ':' . $user->id;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $link = PropertyFamilyLink::query()
            ->where('property_id', $property->id)
            ->where('linked_user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        return $cache[$key] = $link;
    }

    /**
     * Check whether the current user has a specific permission on
     * the given property via an approved family link.
     *
     * Supported permissions (config/property_family_links.php):
     *   view | edit | receive_notifications | manage_tenants
     *   manage_units | upload_photos
     */
    protected function familyLinkAllows(Property $property, string $permission): bool
    {
        $link = $this->getFamilyLinkForProperty($property);

        if (!$link) {
            return false;
        }

        $permissions = $link->permissions ?? [];

        // Support both JSON arrays and comma-separated strings
        if (is_string($permissions)) {
            $permissions = array_filter(array_map('trim', explode(',', $permissions)));
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * Convenience: is the current user a linked family member on this
     * property (regardless of specific permission)?
     */
    protected function isLinkedFamilyMember(Property $property): bool
    {
        return $this->getFamilyLinkForProperty($property) !== null;
    }

    /* ================================================================
       CREATION
       ================================================================ */

    /**
     * Check if user can create property.
     *
     * Unchanged from original: only admins, super admins, and field
     * agents can register new properties. Linked family members are
     * never allowed to create properties — they inherit access to an
     * existing one.
     */
    private function canCreateProperty($request): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $user->isFieldAgent();
    }

    /* ================================================================
       VIEW
       ================================================================ */

    /**
     * Check if user can view property.
     *
     * Resolution order:
     *   1. Super admins and admins    → always
     *   2. Landlord owner             → always
     *   3. Linked family member       → requires 'view' permission
     *   4. Gate policy                → if defined
     *   5. Field agent                → assigned / registered
     */
    private function canViewProperty($property): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // 1. Administrators
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        // 2. Landlord owner
        if ($user->isLandlord() && $property->landlord_id == $user->id) {
            return true;
        }

        // 3. Linked family member
        if ($this->familyLinkAllows($property, 'view')) {
            return true;
        }

        // 4. Gate policy
        if (Gate::allows('view', $property)) {
            return true;
        }

        // 5. Field agents
        if ($user->isFieldAgent()) {
            return $property->registered_by == $user->id
                || $property->registrationPlan?->planAssignments()
                    ->where('agent_id', $user->id)
                    ->where('is_active', true)
                    ->exists();
        }

        return false;
    }

    /* ================================================================
       UPDATE
       ================================================================ */

    /**
     * Check if user can update property.
     *
     * Resolution order:
     *   1. Super admins and admins    → always
     *   2. Landlord owner             → always
     *   3. Linked family member       → requires 'edit' permission
     *   4. Gate policy                → if defined
     *   5. Field agent                → only if registered_by == user
     */
    private function canUpdateProperty($property): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        Log::debug('canUpdateProperty check', [
            'user_id'              => $user->id,
            'user_type'            => $user->type,
            'user_roles'           => $user->roles->pluck('name')->toArray(),
            'is_landlord'          => $user->isLandlord(),
            'property_id'          => $property->id,
            'property_landlord_id' => $property->landlord_id,
            'is_owner'             => $property->landlord_id == $user->id,
        ]);

        // 1. Administrators
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        // 2. Landlord owner
        if ($user->isLandlord() && $property->landlord_id == $user->id) {
            return true;
        }

        // 3. Linked family member with 'edit'
        if ($this->familyLinkAllows($property, 'edit')) {
            Log::debug('canUpdateProperty: granted via family link (edit)', [
                'user_id'     => $user->id,
                'property_id' => $property->id,
            ]);
            return true;
        }

        // 4. Gate policy
        if (Gate::allows('update', $property)) {
            return true;
        }

        // 5. Field agents
        if ($user->isFieldAgent()) {
            return $property->registered_by == $user->id;
        }

        Log::warning('canUpdateProperty: Access denied', [
            'user_id'     => $user->id,
            'user_type'   => $user->type,
            'property_id' => $property->id,
        ]);

        return false;
    }

    /* ================================================================
       DELETE
       ================================================================ */

    /**
     * Check if user can delete property.
     *
     * Family links NEVER grant delete. Only admins and the field agent
     * who registered the property (subject to policy) may delete.
     */
    private function canDeleteProperty($property): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // 1. Administrators
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        // 2. Landlords cannot delete
        if ($user->isLandlord()) {
            Log::info('canDeleteProperty: Landlord denied - only admins can delete');
            return false;
        }

        // 3. Linked family members cannot delete
        if ($this->isLinkedFamilyMember($property)) {
            Log::info('canDeleteProperty: Linked family member denied');
            return false;
        }

        // 4. Field agents — only properties they registered
        if ($user->isFieldAgent()) {
            return $property->registered_by == $user->id;
        }

        return false;
    }

    /* ================================================================
       TARGETED PERMISSION CHECKS (for fine-grained UI gating)
       ================================================================ */

    /**
     * Can the current user upload photos to this property?
     *
     * Owners + admins + field agents who registered it — plus linked
     * family members who were explicitly granted `upload_photos`.
     */
    private function canUploadPhotos(Property $property): bool
    {
        if ($this->canUpdateProperty($property)) {
            return true;
        }

        return $this->familyLinkAllows($property, 'upload_photos');
    }

    /**
     * Can the current user manage tenants on this property?
     */
    private function canManageTenants(Property $property): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if ($property->landlord_id == $user->id) {
            return true;
        }

        return $this->familyLinkAllows($property, 'manage_tenants');
    }

    /**
     * Can the current user manage units on this property?
     */
    private function canManageUnits(Property $property): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if ($property->landlord_id == $user->id) {
            return true;
        }

        return $this->familyLinkAllows($property, 'manage_units');
    }

    /**
     * Should the current user receive notifications about this property?
     *
     * Landlord owner + admins + linked family members with the
     * `receive_notifications` permission.
     */
    private function canReceivePropertyNotifications(Property $property): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if ($property->landlord_id == $user->id) {
            return true;
        }

        return $this->familyLinkAllows($property, 'receive_notifications');
    }

    /* ================================================================
       OWNERSHIP HELPERS
       ================================================================ */

    /**
     * Check if landlord owns a property.
     */
    private function isLandlordOwner($property): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $isOwner = $user->isLandlord() && $property->landlord_id == $user->id;

        Log::debug('isLandlordOwner check', [
            'user_id'              => $user->id,
            'is_landlord'          => $user->isLandlord(),
            'property_landlord_id' => $property->landlord_id,
            'is_owner'             => $isOwner,
        ]);

        return $isOwner;
    }

    /**
     * Check if agent is assigned to plan.
     */
    private function isAgentAssignedToPlan($agentId, $planId): bool
    {
        return PlanAgentAssignment::where('agent_id', $agentId)
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->exists();
    }

    /* ================================================================
       QUERY SCOPES
       ================================================================ */

    /**
     * Apply field agent scope to query.
     */
    private function applyFieldAgentScope($query): void
    {
        $user = auth()->user();
        if (!$user || !$user->isFieldAgent()) {
            return;
        }

        $query->where(function ($q) use ($user) {
            $q->where('registered_by', $user->id)
              ->orWhereHas('registrationPlan', function ($planQuery) use ($user) {
                  $planQuery->whereHas('planAssignments', function ($assignmentQuery) use ($user) {
                      $assignmentQuery->where('agent_id', $user->id)
                                      ->where('is_active', true);
                  });
              });
        });
    }

    /**
     * Apply landlord scope to query.
     *
     * Now includes:
     *   - Properties the landlord owns
     *   - Properties they are linked to via an approved family link
     *     (with at least 'view' permission)
     *
     * This means the "My Properties" dashboard can naturally surface
     * properties a landlord has been linked to via their family.
     */
    private function applyLandlordScope($query): void
    {
        $user = auth()->user();
        if (!$user || !$user->isLandlord()) {
            return;
        }

        $query->where(function ($q) use ($user) {
            // Own properties
            $q->where('landlord_id', $user->id)

              // OR properties linked to this user via family link
              ->orWhereHas('familyLinks', function ($linkQuery) use ($user) {
                  $linkQuery->where('linked_user_id', $user->id)
                            ->where('status', 'approved');
              });
        });
    }

    /**
     * ✅ NEW: Apply linked family scope to query.
     *
     * Useful for dashboards that want to show *only* the properties a
     * user has been linked to (not ones they own).
     */
    private function applyFamilyLinkScope($query): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        $query->whereHas('familyLinks', function ($linkQuery) use ($user) {
            $linkQuery->where('linked_user_id', $user->id)
                      ->where('status', 'approved');
        });
    }

    /* ================================================================
       REGISTRATION PLANS
       ================================================================ */

    /**
     * Get registration plans based on user type.
     */
    protected function getRegistrationPlansForUser()
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        // Admins & super admins — all active plans
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return \App\Models\RegistrationPlan::whereNull('deleted_at')
                ->whereIn('status', ['assigned', 'in_progress', 'draft'])
                ->withCount('properties')
                ->orderBy('zone')
                ->orderBy('section')
                ->get();
        }

        // Field agents — only assigned plans
        if ($user->isFieldAgent()) {
            $planIds = PlanAgentAssignment::where('agent_id', $user->id)
                ->where('is_active', true)
                ->pluck('plan_id')
                ->toArray();

            Log::info('getRegistrationPlansForUser - Field Agent', [
                'user_id'  => $user->id,
                'plan_ids' => $planIds,
            ]);

            if (empty($planIds)) {
                return collect();
            }

            $plans = \App\Models\RegistrationPlan::whereIn('id', $planIds)
                ->whereNull('deleted_at')
                ->whereIn('status', ['assigned', 'in_progress', 'draft'])
                ->withCount('properties')
                ->orderBy('zone')
                ->orderBy('section')
                ->get();

            Log::info('getRegistrationPlansForUser - Plans found', [
                'count' => $plans->count(),
                'plans' => $plans->map(fn ($p) => [
                    'id'     => $p->id,
                    'zone'   => $p->zone,
                    'status' => $p->status,
                ])->toArray(),
            ]);

            return $plans;
        }

        // Everyone else — empty
        return collect();
    }

    /* ================================================================
       AGENT ASSIGNMENT PROGRESS
       ================================================================ */

    /**
     * Update agent assignment progress.
     */
    protected function updateAgentAssignmentProgress($agentId, $planId): void
    {
        try {
            $assignment = PlanAgentAssignment::where('agent_id', $agentId)
                ->where('plan_id', $planId)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return;
            }

            $propertiesRegistered = \App\Models\Property::where('registration_plan_id', $planId)
                ->where('registered_by', $agentId)
                ->count();

            $assignment->update([
                'properties_registered' => $propertiesRegistered,
                'last_activity_at'      => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating agent assignment progress: ' . $e->getMessage());
        }
    }
}