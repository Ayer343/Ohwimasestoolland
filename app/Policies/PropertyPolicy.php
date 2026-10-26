<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\PropertyFamilyLink;
use App\Models\User;

class PropertyPolicy
{
    /* ================================================================
       INTERNAL HELPERS
       ================================================================ */

    /**
     * Is the user the owner of the property?
     */
    protected function isOwner(User $user, Property $property): bool
    {
        return (int) $property->landlord_id === (int) $user->id;
    }

    /**
     * Is the user an admin, super-admin, or developer?
     *
     * These roles bypass all permission checks — they're platform
     * operators, not delegated actors.
     */
    protected function isElevated(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isDeveloper();
    }

    /**
     * Does the user hold an APPROVED family link to this property?
     *
     * This is the authoritative check used across the units module,
     * the family-links module, and anything else that needs to know
     * "can this user act on this property as a linked member?"
     */
    protected function isLinkedFamilyMember(User $user, Property $property): bool
    {
        // Short-circuit for properties the user could never be linked to.
        if (!$property->exists) {
            return false;
        }

        return PropertyFamilyLink::query()
            ->where('property_id', $property->id)
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Does the user's approved family link to this property grant
     * the given permission?
     *
     * Centralises the "load the link, cast permissions, in_array"
     * boilerplate that every delegated-action ability needs.
     *
     * Returns false if:
     *   - there is no approved link, or
     *   - the link's permissions array is missing/empty, or
     *   - the requested permission isn't in the array.
     */
    protected function permissionGranted(User $user, Property $property, string $permission): bool
    {
        if (!$property->exists) {
            return false;
        }

        $link = PropertyFamilyLink::query()
            ->where('property_id', $property->id)
            ->where('linked_user_id', $user->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->first();

        if (!$link) {
            return false;
        }

        $permissions = is_array($link->permissions) ? $link->permissions : [];

        return in_array($permission, $permissions, true);
    }

    /* ================================================================
       VIEWING
       ================================================================ */

    /**
     * Can the user view this property's basic details?
     *
     * Rules:
     *   • Super-admins, admins, developers: always.
     *   • The owner: yes.
     *   • An approved family member (any permission set): yes.
     *
     * Broad on purpose — the property-detail page is the entry point
     * for the units / tenants / financials pages, and linked members
     * need to reach it. Fine-grained actions are gated separately.
     */
    public function view(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->isLinkedFamilyMember($user, $property);
    }

    /* ================================================================
       ACCESS (generic "can act on this property" ability)
       ================================================================ */

    /**
     * Generic "can this user act on this property?" ability.
     *
     * Kept as a separate ability so callers can express intent even
     * though it currently delegates to `view()`. If a future change
     * needs to distinguish "view" from "interact", `access` becomes
     * the intermediate gate.
     */
    public function access(User $user, Property $property): bool
    {
        return $this->view($user, $property);
    }

    /* ================================================================
       MANAGING UNITS
       ================================================================ */

    /**
     * Can the user create / edit / archive / delete units on this
     * property?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `manage_units`.
     * Elevated: yes.
     */
    public function manageUnits(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'manage_units');
    }

    /**
     * Alias for `manageUnits` — kept so blade and controller code
     * reads naturally in either phrasing.
     */
    public function createUnit(User $user, Property $property): bool
    {
        return $this->manageUnits($user, $property);
    }

    /* ================================================================
       MANAGING TENANTS
       ================================================================ */

    /**
     * Can the user assign / manage tenants on this property?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `manage_tenants`.
     * Elevated: yes.
     *
     * This is the ability the unit-tenant controller asks for when
     * gating tenant assignment, vacate, and pending-queue actions.
     * Tenant assignment is a distinct concern from unit CRUD — a
     * family member could have `manage_units` without `manage_tenants`
     * (create units but not lease them out), and vice versa.
     */
    public function manageTenants(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'manage_tenants');
    }

    /* ================================================================
       EDITING PROPERTY DETAILS
       ================================================================ */

    /**
     * Can the user edit the property's own details (construction,
     * coordinates, metadata)?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `edit`.
     * Elevated: yes.
     *
     * Note: this is *not* the gate for unit editing — that's
     * `manageUnits`. The `edit` permission covers the property record
     * itself, not the units it contains.
     */
    public function update(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'edit');
    }

    /* ================================================================
       DELETING
       ================================================================ */

    /**
     * Can the user permanently delete this property?
     *
     * Only admins, super-admins, and the owner. Family members can
     * never delete — that's a destructive action that must remain
     * with the legal owner. Developers are intentionally excluded
     * (they don't own properties and shouldn't be able to destroy
     * a landlord's record even by accident).
     */
    public function delete(User $user, Property $property): bool
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        return $this->isOwner($user, $property);
    }

    /* ================================================================
       FINANCIALS
       ================================================================ */

    /**
     * Can the user see financial data (invoices, payments, reports)?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `view_financials`.
     * Elevated: yes.
     */
    public function viewFinancials(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'view_financials');
    }

    /* ================================================================
       LEASES
       ================================================================ */

    /**
     * Can the user view leases for this property?
     *
     * Owner: yes.
     * Family member: any approved link is enough — lease records are
     *   a fact about the property, not a secret. Actual lease
     *   actions are gated separately by `manageLeases`.
     * Elevated: yes.
     *
     * ⚠️ Behaviour note: this returns TRUE for a family member with
     *    zero permissions, as long as their link is approved. If you
     *    want lease visibility to require a specific permission,
     *    change this to `permissionGranted($user, $property, 'view')`
     *    or add a `view_leases` permission to the matrix.
     */
    public function viewLeases(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->isLinkedFamilyMember($user, $property);
    }

    /**
     * Can the user create / renew / terminate / sign leases?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `manage_leases`.
     * Elevated: yes.
     *
     * Termination is included here because the permission matrix
     * describes `manage_leases` as covering "create, renew, and
     * terminate leases; sign on behalf of the landlord." If you
     * later decide termination should be owner-only, split this
     * into a separate `terminateLeases` ability.
     */
    public function manageLeases(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'manage_leases');
    }

    /* ================================================================
       INVOICES
       ================================================================ */

    /**
     * Can the user manage invoices for this property?
     *
     * Covers generation, updating, and voiding of invoices. Used
     * by the lease controller for deposit tracking and refund flows.
     *
     * Owner: yes.
     * Family member: only if their approved link grants `manage_invoices`.
     * Elevated: yes.
     */
    public function manageInvoices(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'manage_invoices');
    }

    /* ================================================================
       PAYMENTS
       ================================================================ */

    /**
     * Can the user submit or confirm payments for this property?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `make_payments`.
     * Elevated: yes.
     *
     * Not currently called anywhere in the codebase — included for
     * completeness so the permission matrix has a matching ability
     * when the payment module is wired up.
     */
    public function makePayments(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'make_payments');
    }

    /* ================================================================
       MAINTENANCE
       ================================================================ */

    /**
     * Can the user create / update / close maintenance requests for
     * this property?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `manage_maintenance`.
     * Elevated: yes.
     */
    public function manageMaintenance(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'manage_maintenance');
    }

    /* ================================================================
       WASTE COLLECTION
       ================================================================ */

    /**
     * Can the user approve / reject waste collection requests for
     * this property?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `manage_waste`.
     * Elevated: yes.
     */
    public function manageWaste(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'manage_waste');
    }

    /* ================================================================
       CONSTRUCTION CONTRACTS
       ================================================================ */

    /**
     * Can the user view / create / update construction contracts for
     * this property?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `manage_contracts`.
     * Elevated: yes.
     */
    public function manageContracts(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'manage_contracts');
    }

    /* ================================================================
       PHOTOS
       ================================================================ */

    /**
     * Can the user upload / delete / reorder property photos?
     *
     * Owner: yes.
     * Family member: only if their approved link grants `upload_photos`.
     * Elevated: yes.
     */
    public function uploadPhotos(User $user, Property $property): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        if ($this->isOwner($user, $property)) {
            return true;
        }

        return $this->permissionGranted($user, $property, 'upload_photos');
    }
}