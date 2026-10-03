<?php

namespace App\Traits;

use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait UserInvitationTrait
{
    /**
     * Relationship with UserInvitation model
     */
    public function invitations()
    {
        return $this->hasMany(UserInvitation::class);
    }

    /**
     * Get latest invitation
     */
    public function latestInvitation()
    {
        return $this->hasOne(UserInvitation::class)->latest();
    }
    
    /**
     * Get pending invitation
     */
    public function pendingInvitation()
    {
        return $this->hasOne(UserInvitation::class)
                    ->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
                    ->where('expires_at', '>', now())
                    ->latest();
    }

    /**
     * Check if user has valid invitation
     */
    public function getHasValidInvitationAttribute(): bool
    {
        return $this->pendingInvitation()->exists();
    }

    /**
     * Get latest invitation status
     */
    public function getLatestInvitationStatusAttribute(): array
    {
        $invitation = $this->latestInvitation;
        
        if (!$invitation) {
            return ['status' => 'none', 'message' => 'No invitations sent'];
        }

        return [
            'status' => $invitation->status,
            'message' => $this->getInvitationStatusMessage($invitation->status),
            'invitation_type' => $invitation->invitation_type,
            'sent_at' => $invitation->sent_at?->toISOString(),
            'expires_at' => $invitation->expires_at?->toISOString(),
            'accepted_at' => $invitation->accepted_at?->toISOString(),
            'channels' => $invitation->channels,
        ];
    }

    /**
     * Get invitation status message
     */
    private function getInvitationStatusMessage($status): string
    {
        $messages = [
            UserInvitation::STATUS_PENDING => 'Invitation is pending',
            UserInvitation::STATUS_SENT => 'Invitation has been sent',
            UserInvitation::STATUS_ACCEPTED => 'Invitation was accepted',
            UserInvitation::STATUS_EXPIRED => 'Invitation has expired',
            UserInvitation::STATUS_FAILED => 'Invitation failed to send',
            UserInvitation::STATUS_CANCELLED => 'Invitation was cancelled',
        ];

        return $messages[$status] ?? 'Unknown invitation status';
    }

    /**
     * Check if user can set password (has valid invitation)
     */
    public function getCanSetPasswordAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               $this->has_valid_invitation;
    }

    /**
     * Get invitation URL for backward compatibility
     */
    public function getInvitationUrl(): ?string
    {
        $invitation = $this->pendingInvitation;
        return $invitation ? $invitation->getInvitationUrl() : null;
    }

    /**
     * Get invitation URL attribute
     */
    public function getInvitationUrlAttribute(): ?string
    {
        return $this->getInvitationUrl();
    }

    /**
     * Check if user needs password setup
     */
    public function needsPasswordSetup(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               $this->has_valid_invitation;
    }

    /**
     * Complete invitation process by setting password
     */
    public function completeInvitation($password): bool
    {
        if (!$this->has_valid_invitation) {
            return false;
        }

        DB::transaction(function () use ($password) {
            $this->update([
                'password' => Hash::make($password),
                'invitation_accepted_at' => now(),
                'status' => self::STATUS_ACTIVE,
                'email_verified_at' => $this->email_verified_at ?? now(),
            ]);

            $this->pendingInvitation->update([
                'accepted_at' => now(),
                'status' => UserInvitation::STATUS_ACCEPTED
            ]);
        });

        return true;
    }

    /**
     * Check if user can receive invitations
     */
    public function canReceiveInvitation(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               !$this->has_valid_invitation &&
               (!empty($this->phone) || !empty($this->email));
    }

    /**
     * Create new invitation for user
     */
    public function createInvitation(array $data = []): ?UserInvitation
    {
        if (!$this->canReceiveInvitation()) {
            return null;
        }

        return UserInvitation::create(array_merge([
            'user_id' => $this->id,
            'invited_by' => auth()->id(),
            'token' => Str::random(UserInvitation::TOKEN_LENGTH),
            'channels' => [UserInvitation::CHANNEL_EMAIL],
            'invitation_type' => UserInvitation::TYPE_WELCOME,
            'expires_at' => now()->addDays(UserInvitation::DEFAULT_EXPIRY_DAYS),
            'sent_at' => now(),
            'status' => UserInvitation::STATUS_SENT,
            'metadata' => [
                'created_via' => 'system',
                'user_status' => $this->status,
                'user_type' => $this->type_name,
            ]
        ], $data));
    }

    /**
     * Get invitation statistics for user
     */
    public function getInvitationStats(): array
    {
        return [
            'total_invitations' => $this->invitations()->count(),
            'successful_invitations' => $this->invitations()->where('status', UserInvitation::STATUS_ACCEPTED)->count(),
            'pending_invitations' => $this->invitations()->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])->count(),
            'expired_invitations' => $this->invitations()->where('status', UserInvitation::STATUS_EXPIRED)->count(),
            'failed_invitations' => $this->invitations()->where('status', UserInvitation::STATUS_FAILED)->count(),
            'latest_invitation' => $this->latest_invitation_status,
        ];
    }

    /**
     * Check if invitation is pending
     */
    public function getIsInvitationPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->has_valid_invitation;
    }

    /**
     * Check if user can accept invitation
     */
    public function getCanAcceptInvitationAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->has_valid_invitation;
    }

    /**
     * Check if user has accepted invitation
     */
    public function getHasAcceptedInvitationAttribute(): bool
    {
        return !is_null($this->invitation_accepted_at);
    }

    /**
     * Get invitation status
     */
    public function getInvitationStatusAttribute(): string
    {
        if (!is_null($this->invitation_accepted_at)) {
            return 'accepted';
        }

        if ($this->status === self::STATUS_PENDING && $this->has_valid_invitation) {
            return 'pending';
        }

        return 'not_applicable';
    }

    /**
     * Debug invitation information for troubleshooting
     */
    public function getInvitationDebugInfo(): array
    {
        return [
            'user_status' => $this->status,
            'has_valid_invitation' => $this->has_valid_invitation,
            'can_receive_invitation' => $this->canReceiveInvitation(),
            'invitation_accepted_at' => $this->invitation_accepted_at,
            'total_invitations' => $this->invitations()->count(),
            'latest_invitation_status' => $this->latest_invitation_status,
            'pending_invitation' => $this->pendingInvitation ? [
                'id' => $this->pendingInvitation->id,
                'token' => $this->pendingInvitation->token,
                'expires_at' => $this->pendingInvitation->expires_at,
                'status' => $this->pendingInvitation->status,
            ] : null,
        ];
    }
}