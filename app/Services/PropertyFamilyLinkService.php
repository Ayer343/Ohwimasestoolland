<?php
// app/Services/PropertyFamilyLinkService.php

namespace App\Services;

use App\Events\PropertyFamilyLinkProposed;
use App\Events\PropertyFamilyLinkAwaitingLandlordConfirmation;
use App\Events\PropertyFamilyLinkLandlordConfirmed;
use App\Events\PropertyFamilyLinkApproved;
use App\Events\PropertyFamilyLinkRejected;
use App\Events\PropertyFamilyLinkRevoked;
use App\Models\Property;
use App\Models\PropertyFamilyLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PropertyFamilyLinkService
{
    public function __construct(
        protected UserInvitationService $invitationService
    ) {}

    /* ============================================================
       STAGE 1 — LANDLORD PROPOSES
       ============================================================ */

    /**
     * Landlord proposes a new family member.
     *
     * The link starts in `pending_landlord_confirmation` UNLESS the
     * relationship is in the auto-approve list, in which case it
     * jumps straight to `pending_admin_review`.
     *
     * An invitation is NEVER sent at this stage.
     */
    public function propose(Property $property, array $data, User $landlord): PropertyFamilyLink
    {
        $permissions = $data['permissions']
            ?? config('property_family_links.default_permissions', ['view']);

        $autoConfirmLandlord = in_array(
            $data['relationship'],
            config('property_family_links.auto_confirm_landlord_relationships', []),
            true
        );

        return DB::transaction(function () use ($property, $data, $landlord, $permissions, $autoConfirmLandlord) {
            $link = PropertyFamilyLink::create([
                'property_id'        => $property->id,
                'landlord_id'        => $landlord->id,
                'proposed_name'      => $data['proposed_name'],
                'proposed_phone'     => $data['proposed_phone'] ?? null,
                'proposed_email'     => $data['proposed_email'] ?? null,
                'relationship'       => $data['relationship'],
                'relationship_other' => $data['relationship_other'] ?? null,
                'permissions'        => $permissions,
                'status'             => $autoConfirmLandlord
                    ? PropertyFamilyLink::STATUS_PENDING_ADMIN
                    : PropertyFamilyLink::STATUS_PENDING_LANDLORD,
                'landlord_notes'     => $data['landlord_notes'] ?? null,
                'landlord_confirmed_at' => $autoConfirmLandlord ? now() : null,
                'landlord_confirmed_by' => $autoConfirmLandlord ? $landlord->id : null,
            ]);

            event(new PropertyFamilyLinkProposed($link, $landlord));

            if ($autoConfirmLandlord) {
                // Auto-confirmed by config — notify admins immediately.
                event(new PropertyFamilyLinkLandlordConfirmed($link, $landlord));
            } else {
                // Ask the landlord to confirm the proposal.
                event(new PropertyFamilyLinkAwaitingLandlordConfirmation($link, $landlord));
            }

            Log::info('Family link proposed', [
                'link_id'      => $link->id,
                'property_id'  => $property->id,
                'landlord_id'  => $landlord->id,
                'status'       => $link->status,
                'relationship' => $link->relationship,
                'auto_confirm' => $autoConfirmLandlord,
            ]);

            return $link->fresh(['property', 'landlord']);
        });
    }

    /* ============================================================
       STAGE 2 — LANDLORD CONFIRMS
       ============================================================ */

    /**
     * Landlord explicitly confirms the proposal.
     * Transitions: pending_landlord_confirmation → pending_admin_review.
     * Fires the event that notifies admins.
     */
    public function confirmByLandlord(PropertyFamilyLink $link, User $landlord): PropertyFamilyLink
    {
        if (!$link->isAwaitingLandlordConfirmation()) {
            throw new \RuntimeException(
                'Only proposals awaiting landlord confirmation can be confirmed.'
            );
        }

        if ((int) $link->landlord_id !== (int) $landlord->id) {
            throw new \RuntimeException('You are not the landlord of this proposal.');
        }

        return DB::transaction(function () use ($link, $landlord) {
            $link->update([
                'status'                => PropertyFamilyLink::STATUS_PENDING_ADMIN,
                'landlord_confirmed_at' => now(),
                'landlord_confirmed_by' => $landlord->id,
            ]);

            event(new PropertyFamilyLinkLandlordConfirmed($link, $landlord));

            Log::info('Family link confirmed by landlord', [
                'link_id'     => $link->id,
                'landlord_id' => $landlord->id,
            ]);

            return $link->fresh();
        });
    }

    /* ============================================================
       STAGE 3 — ADMIN APPROVES / REJECTS
       ============================================================ */

    /**
     * Admin approves the proposal. Only allowed after the landlord
     * has confirmed it (or auto-confirm config kicked in).
     *
     * On approval:
     *   1. Create or find the linked user
     *   2. Send the invitation via UserInvitationService
     *   3. Update the link to `approved`
     */
    public function approve(
        PropertyFamilyLink $link,
        User $admin,
        array $data
    ): PropertyFamilyLink {
        if (!$link->isAwaitingAdminReview()) {
            throw new \RuntimeException(
                'Only proposals awaiting admin review can be approved.'
            );
        }

        return DB::transaction(function () use ($link, $admin, $data) {
            $link->update([
                'status'          => PropertyFamilyLink::STATUS_APPROVED,
                'reviewed_by'     => $admin->id,
                'reviewed_at'     => now(),
                'linked_at'       => now(),
                'admin_notes'     => $data['admin_notes'] ?? null,
                'permissions'     => $data['permissions']
                                     ?? $link->permissions
                                     ?? config('property_family_links.default_permissions', ['view']),
                'proposed_name'   => $data['override_name']  ?? $link->proposed_name,
                'proposed_phone'  => $data['override_phone'] ?? $link->proposed_phone,
                'proposed_email'  => $data['override_email'] ?? $link->proposed_email,
            ]);

            $this->materializeLinkedUser($link);

            event(new PropertyFamilyLinkApproved($link, $admin));

            Log::info('Family link approved', [
                'link_id'     => $link->id,
                'reviewed_by' => $admin->id,
                'linked_user' => $link->linked_user_id,
            ]);

            return $link->fresh(['property', 'landlord', 'linkedUser']);
        });
    }

    /**
     * Admin rejects the proposal. No invitation is sent.
     */
    public function reject(
        PropertyFamilyLink $link,
        User $admin,
        string $reason
    ): PropertyFamilyLink {
        if (!$link->isAwaitingAdminReview()) {
            throw new \RuntimeException(
                'Only proposals awaiting admin review can be rejected.'
            );
        }

        $link->update([
            'status'      => PropertyFamilyLink::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $reason,
        ]);

        event(new PropertyFamilyLinkRejected($link, $admin, $reason));

        Log::info('Family link rejected', [
            'link_id' => $link->id,
            'reason'  => $reason,
        ]);

        return $link->fresh();
    }

    /* ============================================================
       REVOKE
       ============================================================ */

    public function revoke(
        PropertyFamilyLink $link,
        User $actor,
        ?string $reason = null
    ): PropertyFamilyLink {
        if (!$link->isApproved()) {
            throw new \RuntimeException('Only approved links can be revoked.');
        }

        $link->update([
            'status'      => PropertyFamilyLink::STATUS_REVOKED,
            'revoked_at'  => now(),
            'revoked_by'  => $actor->id,
            'admin_notes' => $reason ?? $link->admin_notes,
        ]);

        event(new PropertyFamilyLinkRevoked($link, $actor, $reason));

        Log::info('Family link revoked', [
            'link_id'    => $link->id,
            'revoked_by' => $actor->id,
            'reason'     => $reason,
        ]);

        return $link->fresh();
    }

    /* ============================================================
       USER MATERIALIZATION + INVITATION
       ============================================================ */

    /**
     * Ensure the linked user exists (or is reused) and the invitation
     * is sent. Called ONLY from approve().
     */
    protected function materializeLinkedUser(PropertyFamilyLink $link): void
    {
        if ($link->linked_user_id) {
            return; // already linked
        }

        $existingUser = $this->findExistingUser($link);

        if ($existingUser) {
            // Existing user — no invitation needed. They already have a password.
            $this->grantLinkedFamilyRole($existingUser);

            $link->update(['linked_user_id' => $existingUser->id]);

            Log::info('Family link attached to existing user', [
                'link_id' => $link->id,
                'user_id' => $existingUser->id,
            ]);

            return;
        }

        // New user — create as PENDING and send the invitation.
        $user = $this->createLinkedUser($link);

        $this->grantLinkedFamilyRole($user);
        $this->sendFamilyInvitation($user, $link);

        $link->update(['linked_user_id' => $user->id]);
    }

    protected function findExistingUser(PropertyFamilyLink $link): ?User
    {
        return User::query()
            ->when($link->proposed_phone, fn ($q) =>
                $q->orWhere('phone', $link->proposed_phone)
            )
            ->when($link->proposed_email, fn ($q) =>
                $q->orWhere('email', $link->proposed_email)
            )
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Create the linked user as PENDING so the invitation flow can
     * complete the account setup.
     */
    protected function createLinkedUser(PropertyFamilyLink $link): User
    {
        // Placeholder password — the user sets their own via the invite link.
        // Status stays PENDING so UserInvitationController::processAcceptance()
        // flips it to ACTIVE once the user accepts.
        $user = User::create([
            'name'              => $link->proposed_name,
            'phone'             => $link->proposed_phone,
            'email'             => $link->proposed_email,
            'type'              => User::TYPE_LANDLORD,
            'status'            => User::STATUS_PENDING,
            'password'          => Hash::make(Str::random(32)),
            'created_by'        => $link->reviewed_by ?? $link->landlord_id,
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);

        $user->forceFill(['must_change_password' => true])->saveQuietly();

        Log::info('Linked family user created (pending invitation)', [
            'user_id' => $user->id,
            'link_id' => $link->id,
        ]);

        return $user;
    }

    /**
     * Send the invitation for the newly created user.
     */
    protected function sendFamilyInvitation(User $user, PropertyFamilyLink $link): void
    {
        try {
            $channels = [];
            if (!empty($user->email)) $channels[] = 'email';
            if (!empty($user->phone)) $channels[] = 'sms';

            if (empty($channels)) {
                Log::warning('Cannot send family-link invitation — no contact channels', [
                    'user_id' => $user->id,
                    'link_id' => $link->id,
                ]);
                return;
            }

            $landlordName = $link->landlord->name ?? 'Your landlord';
            $propertyName = $link->property->property_name ?? 'a property';

            $result = $this->invitationService->sendInvitation($user, [
                'invitation_type'     => 'welcome',
                'invitation_channels' => $channels,
                'custom_message'      => sprintf(
                    '%s has linked you as a %s to the property "%s". Set your password to access it.',
                    $landlordName,
                    $link->relationship_label,
                    $propertyName
                ),
                'expires_in_days' => (int) config('app.invitation_expiry_days', 7),
            ]);

            if (!($result['success'] ?? false)) {
                Log::warning('Family-link invitation delivery failed', [
                    'user_id'  => $user->id,
                    'link_id'  => $link->id,
                    'message'  => $result['message'] ?? 'Unknown error',
                ]);
                return;
            }

            Log::info('Family-link invitation sent', [
                'user_id'  => $user->id,
                'link_id'  => $link->id,
                'channels' => $channels,
                'url'      => $result['invitation_url'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('Exception while sending family-link invitation', [
                'user_id' => $user->id,
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);
            // Do NOT rethrow — the link is approved and the user exists.
            // Admin can resend from the invitation dashboard.
        }
    }

    /**
     * Grant the "linked_family" role if it exists.
     */
    protected function grantLinkedFamilyRole(User $user): void
    {
        try {
            if (!method_exists($user, 'assignRole')) return;

            if (\Spatie\Permission\Models\Role::where('slug', 'linked_family')->exists()
                && !$user->hasRole('linked_family')) {
                $user->assignRole('linked_family');
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to assign linked_family role', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}