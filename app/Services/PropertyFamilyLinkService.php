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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PropertyFamilyLinkService
{
    /**
     * Channels the invitation pipeline understands.
     * Must match ReviewFamilyLinkRequest::SUPPORTED_CHANNELS.
     */
    public const SUPPORTED_INVITATION_CHANNELS = ['email', 'sms', 'whatsapp'];

    public function __construct(
        protected UserInvitationService $invitationService
    ) {}

    /* ============================================================
       STAGE 1 — LANDLORD PROPOSES (OR RESUBMITS)
       ============================================================ */

    /**
     * Landlord proposes a new family member.
     *
     * The link starts in `pending_landlord_confirmation` UNLESS the
     * relationship is in the auto-approve list, in which case it
     * jumps straight to `pending_admin_review`.
     *
     * An invitation is NEVER sent at this stage.
     *
     * @param  PropertyFamilyLink|null  $resubmit  Optional. When
     *         provided, this row is updated in place instead of
     *         creating a new one. Used by the bulk-proposal flow and
     *         by the single-property resubmit path.
     */
    public function propose(
        Property $property,
        array $data,
        User $landlord,
        ?PropertyFamilyLink $resubmit = null
    ): PropertyFamilyLink {
        $permissions = $data['permissions']
            ?? config('property_family_links.default_permissions', ['view']);

        $autoConfirmLandlord = in_array(
            $data['relationship'],
            config('property_family_links.auto_confirm_landlord_relationships', []),
            true
        );

        // If the caller passed `resubmit` explicitly, use it — otherwise
        // fall back to reading `resubmit_from` from the payload (legacy
        // single-property flow).
        $existing = $resubmit;

        if (!$existing && !empty($data['resubmit_from'])) {
            $existing = PropertyFamilyLink::query()
                ->where('id', $data['resubmit_from'])
                ->where('landlord_id', $landlord->id)
                ->where('status', PropertyFamilyLink::STATUS_REJECTED)
                ->first();
        }

        return DB::transaction(function () use (
            $property, $data, $landlord, $permissions, $autoConfirmLandlord, $existing
        ) {
            $payload = [
                'property_id'               => $property->id,
                'landlord_id'               => $landlord->id,
                'proposed_name'             => $data['proposed_name'],
                'proposed_phone'            => $data['proposed_phone'] ?? null,
                'proposed_email'            => $data['proposed_email'] ?? null,
                'relationship'              => $data['relationship'],
                'relationship_other'        => $data['relationship_other'] ?? null,
                'permissions'               => $permissions,
                'landlord_notes'            => $data['landlord_notes'] ?? null,

                'status'                    => $autoConfirmLandlord
                    ? PropertyFamilyLink::STATUS_PENDING_ADMIN
                    : PropertyFamilyLink::STATUS_PENDING_LANDLORD,

                'landlord_confirmed_at'     => $autoConfirmLandlord ? now() : null,
                'landlord_confirmed_by'     => $autoConfirmLandlord ? $landlord->id : null,

                'reviewed_by'               => null,
                'reviewed_at'               => null,

                'previous_rejection_reason' => $existing?->admin_notes,
                'admin_notes'               => null,
            ];

            if ($existing) {
                $existing->update($payload);
                $link = $existing->fresh();
            } else {
                $link = PropertyFamilyLink::create($payload);
            }

            event(new PropertyFamilyLinkProposed($link, $landlord));

            if ($autoConfirmLandlord) {
                event(new PropertyFamilyLinkLandlordConfirmed($link, $landlord));
            } else {
                event(new PropertyFamilyLinkAwaitingLandlordConfirmation($link, $landlord));
            }

            Log::info($existing ? 'Family link resubmitted (row updated)' : 'Family link proposed', [
                'link_id'        => $link->id,
                'property_id'    => $property->id,
                'landlord_id'    => $landlord->id,
                'status'         => $link->status,
                'relationship'   => $link->relationship,
                'auto_confirm'   => $autoConfirmLandlord,
                'was_resubmit'   => (bool) $existing,
            ]);

            return $link->fresh(['property', 'landlord']);
        });
    }

    /* ============================================================
       STAGE 1b — EXISTING MEMBER, ADD MORE PROPERTIES
       ============================================================ */

    /**
     * Attach an already-linked family member to an additional property.
     *
     * This bypasses the proposal + invitation flow entirely because the
     * user is already in the system. The row is created in `approved`
     * state with both landlord-confirmation and admin-review timestamps
     * set, so the rest of the workflow treats it like any other approved
     * link (permissions editable, revocable, etc.).
     *
     * The caller is responsible for verifying that the landlord already
     * has at least one approved link to this user — a check that lives
     * in the controller to keep this method focused.
     */
    public function linkExistingUser(
        Property $property,
        User $user,
        array $attributes,
        User $landlord
    ): PropertyFamilyLink {
        return DB::transaction(function () use ($property, $user, $attributes, $landlord) {
            $link = PropertyFamilyLink::create([
                'property_id'           => $property->id,
                'landlord_id'           => $landlord->id,
                'linked_user_id'        => $user->id,

                // Snapshot the user's current details so the row is
                // self-contained even if the user later changes them.
                'proposed_name'         => $user->name,
                'proposed_email'        => $user->email,
                'proposed_phone'        => $user->phone ?? null,

                'relationship'          => $attributes['relationship'] ?? null,
                'relationship_other'    => $attributes['relationship_other'] ?? null,

                'permissions'           => is_array($attributes['permissions'] ?? null)
                    ? $attributes['permissions']
                    : config('property_family_links.default_permissions', ['view']),

                'landlord_notes'        => $attributes['landlord_notes'] ?? null,

                'status'                => PropertyFamilyLink::STATUS_APPROVED,

                // The landlord is the actor here (they're extending their
                // own prior approval to a new property). No admin gate.
                'landlord_confirmed_at' => now(),
                'landlord_confirmed_by' => $landlord->id,

                'linked_at'             => now(),
                'reviewed_at'           => now(),
                'reviewed_by'           => null,
                'admin_notes'           => null,
            ]);

            // Fire the approved event so listeners (welcome email,
            // in-app notification, audit trail) run exactly as they
            // would for a normal admin approval.
            event(new PropertyFamilyLinkApproved($link, $landlord));

            Log::info('Family link created for existing user (no invitation)', [
                'link_id'        => $link->id,
                'property_id'    => $property->id,
                'landlord_id'    => $landlord->id,
                'linked_user_id' => $user->id,
                'permissions'    => $link->permissions,
            ]);

            return $link->fresh(['property', 'landlord', 'linkedUser']);
        });
    }

    /* ============================================================
       STAGE 2 — LANDLORD CONFIRMS
       ============================================================ */

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

        $channels = $this->normalizeChannels($data['channels'] ?? null);

        return DB::transaction(function () use ($link, $admin, $data, $channels) {
            $link->update([
                'status'         => PropertyFamilyLink::STATUS_APPROVED,
                'reviewed_by'    => $admin->id,
                'reviewed_at'    => now(),
                'linked_at'      => now(),
                'admin_notes'    => $data['admin_notes'] ?? null,
                'permissions'    => $data['permissions']
                                    ?? $link->permissions
                                    ?? config('property_family_links.default_permissions', ['view']),
                'proposed_name'  => $data['override_name']  ?? $link->proposed_name,
                'proposed_phone' => $data['override_phone'] ?? $link->proposed_phone,
                'proposed_email' => $data['override_email'] ?? $link->proposed_email,
            ]);

            $link->refresh();

            $this->materializeLinkedUser($link, $channels);

            event(new PropertyFamilyLinkApproved($link, $admin));

            Log::info('Family link approved', [
                'link_id'     => $link->id,
                'reviewed_by' => $admin->id,
                'linked_user' => $link->linked_user_id,
                'channels'    => $channels,
            ]);

            return $link->fresh(['property', 'landlord', 'linkedUser']);
        });
    }

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

    // Capture the linked user before we mutate the row.
    $linkedUser = $link->linked_user_id
        ? User::find($link->linked_user_id)
        : null;

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

    // ✅ NEW: If this was the user's last approved link, and they
    //         don't own any property, archive them under the same
    //         retention policy used by landlord / tenant archival.
    if ($linkedUser) {
        // Refresh relation caches so the archival check sees the
        // post-revoke state.
        $linkedUser->unsetRelation('approvedFamilyLinks');

        $this->orphanArchivalService->considerArchival($linkedUser);
    }

    return $link->fresh();
}

    /* ============================================================
       USER MATERIALIZATION + INVITATION
       ============================================================ */

    protected function materializeLinkedUser(PropertyFamilyLink $link, array $channels = []): void
    {
        if ($link->linked_user_id) {
            return;
        }

        $existingUser = $this->findExistingUser($link);

        if ($existingUser) {
            $this->elevateExistingUserToLandlord($existingUser);

            $link->update(['linked_user_id' => $existingUser->id]);

            Log::info('Family link attached to existing user (classification normalized)', [
                'link_id'        => $link->id,
                'user_id'        => $existingUser->id,
                'final_type'     => $existingUser->fresh()->type,
                'final_roles'    => $existingUser->fresh()->roles->pluck('slug')->toArray(),
                'channels_asked' => $channels,
            ]);

            return;
        }

        $user = $this->createLinkedUser($link);

        $this->grantLinkedFamilyRole($user);
        $this->sendFamilyInvitation($user, $link, $channels);

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

    protected function elevateExistingUserToLandlord(User $user): void
    {
        $user->refresh();

        $rawType = (int) $user->getRawOriginal('type');

        $isPrivileged = in_array($rawType, [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADMIN,
        ], true);

        $updates = [];

        if (!$isPrivileged && $rawType !== User::TYPE_LANDLORD) {
            $updates['type'] = User::TYPE_LANDLORD;

            $username = (string) ($user->username ?? '');
            if ($username !== '' && str_starts_with($username, 'agent_')) {
                $updates['username'] = null;
            }
        }

        if (!empty($updates)) {
            $user->forceFill($updates)->saveQuietly();

            Log::info('Existing user elevated to landlord for family link', [
                'user_id'          => $user->id,
                'previous_type'    => $rawType,
                'new_type'         => $updates['type'] ?? $rawType,
                'username_cleared' => array_key_exists('username', $updates),
            ]);
        }

        $this->grantLinkedFamilyRole($user);
    }

    protected function createLinkedUser(PropertyFamilyLink $link): User
    {
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

        if (Schema::hasColumn('users', 'must_change_password')) {
            $user->forceFill(['must_change_password' => true])->saveQuietly();
        }

        Log::info('Linked family user created (pending invitation)', [
            'user_id' => $user->id,
            'link_id' => $link->id,
        ]);

        return $user;
    }

    protected function sendFamilyInvitation(
        User $user,
        PropertyFamilyLink $link,
        array $channels = []
    ): void {
        try {
            $requested = $channels ?: $this->defaultChannelsFor($user);

            $deliverable = array_values(array_filter(
                $requested,
                fn ($c) => $this->channelIsDeliverable($c, $user)
            ));

            if (empty($deliverable)) {
                Log::warning('Cannot send family-link invitation — no deliverable channels', [
                    'user_id'    => $user->id,
                    'link_id'    => $link->id,
                    'requested'  => $requested,
                    'has_email'  => !empty($user->email),
                    'has_phone'  => !empty($user->phone),
                ]);
                return;
            }

            $invitationChannels = array_values(array_intersect($deliverable, ['email', 'sms']));

            $invitationResult = null;

            if (!empty($invitationChannels)) {
                $invitationResult = $this->invitationService->sendInvitation($user, [
                    'invitation_type'     => 'welcome',
                    'invitation_channels' => $invitationChannels,
                    'custom_message'      => $this->buildInvitationMessage($link),
                    'expires_in_days'     => (int) config('app.invitation_expiry_days', 7),
                ]);

                if (!($invitationResult['success'] ?? false)) {
                    Log::warning('Family-link invitation (email/SMS) delivery failed', [
                        'user_id'  => $user->id,
                        'link_id'  => $link->id,
                        'channels' => $invitationChannels,
                        'message'  => $invitationResult['message'] ?? 'Unknown error',
                    ]);
                } else {
                    Log::info('Family-link invitation (email/SMS) sent', [
                        'user_id'  => $user->id,
                        'link_id'  => $link->id,
                        'channels' => $invitationChannels,
                        'url'      => $invitationResult['invitation_url'] ?? null,
                    ]);
                }
            }

            if (in_array('whatsapp', $deliverable, true)) {
                $this->sendWhatsAppInvitation($user, $link, $invitationResult);
            }

        } catch (\Throwable $e) {
            Log::error('Exception while sending family-link invitation', [
                'user_id' => $user->id,
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    protected function sendWhatsAppInvitation(
        User $user,
        PropertyFamilyLink $link,
        ?array $invitationResult
    ): void {
        if (empty($user->phone)) {
            Log::info('Skipping WhatsApp invitation — recipient has no phone', [
                'user_id' => $user->id,
            ]);
            return;
        }

        try {
            $whatsApp = app(\App\Services\WhatsAppService::class);

            if (!$whatsApp->isConfigured()) {
                Log::warning('Skipping WhatsApp invitation — WhatsAppService not configured', [
                    'user_id' => $user->id,
                    'link_id' => $link->id,
                ]);
                return;
            }

            $message = $this->buildInvitationMessage($link);

            $url = $invitationResult['invitation_url'] ?? null;
            if (!empty($url)) {
                $message .= "\n\n" . $url;
            }

            $result = $whatsApp->sendMessage($user->phone, $message, [
                'message_type' => 'text',
            ]);

            if (($result['success'] ?? false) === true) {
                Log::info('WhatsApp invitation sent', [
                    'user_id'    => $user->id,
                    'link_id'    => $link->id,
                    'message_id' => $result['message_id'] ?? null,
                ]);
            } else {
                Log::warning('WhatsApp invitation delivery failed', [
                    'user_id' => $user->id,
                    'link_id' => $link->id,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Exception while sending WhatsApp invitation', [
                'user_id' => $user->id,
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    protected function buildInvitationMessage(PropertyFamilyLink $link): string
    {
        $landlordName = $link->landlord->name ?? 'Your landlord';
        $propertyName = $link->property->property_name ?? 'a property';

        return sprintf(
            '%s has linked you as a %s to the property "%s". Set your password to access it.',
            $landlordName,
            $link->relationship_label,
            $propertyName
        );
    }

    /**
     * Normalize the requested channel list.
     *
     * @return array<int,string>
     */
    protected function normalizeChannels($channels): array
    {
        if (!is_array($channels) || empty($channels)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                fn ($c) => is_string($c) ? strtolower(trim($c)) : null,
                $channels
            ),
            fn ($c) => $c !== null && in_array($c, self::SUPPORTED_INVITATION_CHANNELS, true)
        )));
    }

    /**
     * @return array<int,string>
     */
    protected function defaultChannelsFor(User $user): array
    {
        $channels = [];

        if (!empty($user->email)) $channels[] = 'email';
        if (!empty($user->phone)) $channels[] = 'sms';

        return $channels;
    }

    protected function channelIsDeliverable(string $channel, User $user): bool
    {
        return match ($channel) {
            'email'    => !empty($user->email),
            'sms'      => !empty($user->phone),
            'whatsapp' => !empty($user->phone),
            default    => false,
        };
    }

    protected function grantLinkedFamilyRole(User $user): void
    {
        if (!class_exists(\Spatie\Permission\Models\Role::class)) {
            return;
        }

        if (!method_exists($user, 'assignRole') || !method_exists($user, 'hasRole')) {
            return;
        }

        try {
            $landlordRole = \Spatie\Permission\Models\Role::where('slug', 'landlord')->first();

            if ($landlordRole && !$user->hasRole('landlord')) {
                $user->assignRole($landlordRole);

                Log::info('Assigned landlord role to linked family member', [
                    'user_id' => $user->id,
                ]);
            }

            $linkedFamilyRole = \Spatie\Permission\Models\Role::where('slug', 'linked_family')->first();

            if ($linkedFamilyRole && !$user->hasRole('linked_family')) {
                $user->assignRole('linked_family');

                Log::info('Assigned linked_family role to linked family member', [
                    'user_id' => $user->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to assign roles to linked family member', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}