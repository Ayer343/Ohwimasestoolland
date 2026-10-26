<?php

namespace App\Traits;

use App\Models\UserInvitation;
use Illuminate\Support\Str;

/**
 * User invitation lifecycle: creation, acceptance, completion, status.
 *
 * Behaviour identical to the in-model implementation it replaces.
 */
trait HasInvitations
{
    /* ============================================================
     | RELATIONSHIPS
     * ============================================================ */

    public function invitations()
    {
        return $this->hasMany(UserInvitation::class, 'user_id');
    }

    public function latestInvitation()
    {
        return $this->hasOne(UserInvitation::class)->latest();
    }

    public function pendingInvitation()
    {
        return $this->hasOne(UserInvitation::class)
            ->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
            ->where('expires_at', '>', now())
            ->latest();
    }

    /* ============================================================
     | ACCESSORS
     * ============================================================ */

    public function getHasValidInvitationAttribute(): bool
    {
        return $this->pendingInvitation()->exists();
    }

    public function getIsInvitationPendingAttribute(): bool
    {
        return $this->status === static::STATUS_PENDING && $this->has_valid_invitation;
    }

    public function getCanAcceptInvitationAttribute(): bool
    {
        return $this->getIsInvitationPendingAttribute();
    }

    public function getHasAcceptedInvitationAttribute(): bool
    {
        return !is_null($this->invitation_accepted_at);
    }

    public function getInvitationStatusAttribute(): string
    {
        if (!is_null($this->invitation_accepted_at)) {
            return 'accepted';
        }

        if ($this->status === static::STATUS_PENDING && $this->has_valid_invitation) {
            return 'pending';
        }

        return 'not_applicable';
    }

    public function getCanSetPasswordAttribute(): bool
    {
        return $this->status === static::STATUS_PENDING && $this->has_valid_invitation;
    }

    public function getLatestInvitationStatusAttribute(): array
    {
        $invitation = $this->latestInvitation;

        if (!$invitation) {
            return ['status' => 'none', 'message' => 'No invitations sent'];
        }

        return [
            'status'          => $invitation->status,
            'message'         => $this->getInvitationStatusMessage($invitation->status),
            'invitation_type' => $invitation->invitation_type,
            'sent_at'         => $invitation->sent_at?->toISOString(),
            'expires_at'      => $invitation->expires_at?->toISOString(),
            'accepted_at'     => $invitation->accepted_at?->toISOString(),
            'channels'        => $invitation->channels,
        ];
    }

    public function getInvitationUrl(): ?string
    {
        return $this->pendingInvitation?->getInvitationUrl();
    }

    public function getInvitationUrlAttribute(): ?string
    {
        return $this->getInvitationUrl();
    }

    /* ============================================================
     | CREATION
     * ============================================================ */

    public function canReceiveInvitation(): bool
    {
        return $this->status === static::STATUS_PENDING
            && !$this->has_valid_invitation
            && (!empty($this->phone) || !empty($this->email));
    }

    public function createInvitation(array $data = []): ?UserInvitation
    {
        if (!$this->canReceiveInvitation()) {
            return null;
        }

        return UserInvitation::create(array_merge([
            'user_id'         => $this->id,
            'invited_by'      => auth()->id(),
            'token'           => Str::random(UserInvitation::TOKEN_LENGTH),
            'channels'        => [UserInvitation::CHANNEL_EMAIL],
            'invitation_type' => UserInvitation::TYPE_WELCOME,
            'expires_at'      => now()->addDays(UserInvitation::DEFAULT_EXPIRY_DAYS),
            'sent_at'         => now(),
            'status'          => UserInvitation::STATUS_SENT,
            'metadata'        => [
                'created_via' => 'system',
                'user_status' => $this->status,
                'user_type'   => $this->type_name,
            ],
        ], $data));
    }

    /* ============================================================
     | COMPLETION
     * ============================================================ */

    public function markInvitationAccepted(): bool
    {
        if (!$this->isFieldAgent()) {
            return false;
        }

        return $this->update([
            'invitation_accepted_at' => now(),
            'status'                 => static::STATUS_ACTIVE,
            'email_verified_at'      => $this->email_verified_at ?? now(),
        ]);
    }

    public function completeInvitation($password): bool
    {
        if (!$this->has_valid_invitation) {
            return false;
        }

        \DB::transaction(function () use ($password) {
            $this->update([
                'password'               => \Hash::make($password),
                'invitation_accepted_at' => now(),
                'status'                 => static::STATUS_ACTIVE,
                'email_verified_at'      => $this->email_verified_at ?? now(),
            ]);

            $this->pendingInvitation?->update([
                'accepted_at' => now(),
                'status'      => UserInvitation::STATUS_ACCEPTED,
            ]);
        });

        return true;
    }

    /* ============================================================
     | STATS
     * ============================================================ */

    public function getInvitationStats(): array
    {
        return [
            'total_invitations'      => $this->invitations()->count(),
            'successful_invitations' => $this->invitations()->where('status', UserInvitation::STATUS_ACCEPTED)->count(),
            'pending_invitations'    => $this->invitations()->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])->count(),
            'expired_invitations'    => $this->invitations()->where('status', UserInvitation::STATUS_EXPIRED)->count(),
            'failed_invitations'     => $this->invitations()->where('status', UserInvitation::STATUS_FAILED)->count(),
            'latest_invitation'      => $this->latest_invitation_status,
        ];
    }

    /* ============================================================
     | PRIVATE
     * ============================================================ */

    private function getInvitationStatusMessage($status): string
    {
        return [
            UserInvitation::STATUS_PENDING   => 'Invitation is pending',
            UserInvitation::STATUS_SENT      => 'Invitation has been sent',
            UserInvitation::STATUS_ACCEPTED  => 'Invitation was accepted',
            UserInvitation::STATUS_EXPIRED   => 'Invitation has expired',
            UserInvitation::STATUS_FAILED    => 'Invitation failed to send',
            UserInvitation::STATUS_CANCELLED => 'Invitation was cancelled',
        ][$status] ?? 'Unknown invitation status';
    }

    /* ============================================================
     | SCOPES
     * ============================================================ */

    public function scopeWithValidInvitations($query)
    {
        return $query->whereHas('invitations', function ($q) {
            $q->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
              ->where('expires_at', '>', now());
        })->where('status', static::STATUS_PENDING);
    }
}