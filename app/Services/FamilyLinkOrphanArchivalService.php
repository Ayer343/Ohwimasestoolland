<?php
// app/Services/FamilyLinkOrphanArchivalService.php

namespace App\Services;

use App\Models\PropertyFamilyLink;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Archives family members who have lost their last property link.
 *
 * A user is considered "orphaned" when ALL of the following hold:
 *   • They were linked via at least one family link in the past
 *     (they have the `linked_family` role or `linked_user_id` on
 *     some prior link).
 *   • They have ZERO approved family links remaining.
 *   • They own ZERO properties.
 *   • They are NOT an admin / super-admin / developer.
 *
 * Archival obeys the same retention window as the landlord and tenant
 * archival flows (`SystemSetting::getSettings()->account_archival`).
 *
 * Every failure here is logged but NOT rethrown — an orphan-archival
 * failure must never roll back the caller's revoke transaction.
 */
class FamilyLinkOrphanArchivalService
{
    /**
     * Consider a user for archival after their link set changed.
     *
     * Safe to call unconditionally: it no-ops for admins, for users
     * who still have access, and for users who were never linked.
     */
    public function considerArchival(User $user): bool
    {
        try {
            if (!$this->isEligibleForOrphanArchival($user)) {
                return false;
            }

            return $this->archive($user);
        } catch (\Throwable $e) {
            Log::error('Family-link orphan archival failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * The archival eligibility gate — kept public so it can be
     * asserted from tests and reused from admin UI ("eligible for
     * archival" badges).
     */
    public function isEligibleForOrphanArchival(User $user): bool
    {
        // Never archive admins or developers. The orphan-archival
        // service exists only for family-linked end users.
        if ($user->isSuperAdmin() || $user->isAdmin() || $user->isDeveloper()) {
            return false;
        }

        // Already archived — nothing to do.
        if ($user->trashed() || $user->status === User::STATUS_ARCHIVED) {
            return false;
        }

        // Must have been linked at least once. Without this gate we'd
        // archive every ordinary tenant / agent who happens to have
        // zero links — which is not our job here.
        if (!$this->hasEverBeenLinked($user)) {
            return false;
        }

        // Still has approved access — not orphaned.
        if ($this->hasRemainingAccess($user)) {
            return false;
        }

        return true;
    }

    /**
     * Have they ever been linked? Either via the `linked_family`
     * role OR via a `linked_user_id` on any row of the family-links
     * table (including revoked / rejected / cancelled ones).
     */
    protected function hasEverBeenLinked(User $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('linked_family')) {
            return true;
        }

        return PropertyFamilyLink::query()
            ->where('linked_user_id', $user->id)
            ->exists();
    }

    /**
     * Do they still have any approved family link OR any owned
     * property?
     */
    protected function hasRemainingAccess(User $user): bool
    {
        // Owned properties (shouldn't happen, but be defensive).
        if ($user->ownedProperties()->exists()) {
            return true;
        }

        // Approved family links still active.
        return $user->approvedFamilyLinks()->exists();
    }

    /**
     * Perform the archival. Writes the same metadata shape as
     * UserManagementController::archiveUser() so restore works
     * consistently across all three archival paths.
     */
    protected function archive(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $settings = SystemSetting::getSettings();

            $permanentDeletionDays = (int) (
                $settings->account_archival['permanent_deletion_days'] ?? 365
            );

            $originalEmail    = $user->email;
            $originalUsername = $user->username;
            $originalPhone    = $user->phone;

            // ---------------------------------------------------------
            // Preserve existing metadata and stamp archival fields.
            // ---------------------------------------------------------
            $metadata = $user->metadata ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $archivalReason = 'Family member lost all property links — auto-archived by system';

            $archivalInfo = [
                'archived_at'                   => now()->toISOString(),
                'archived_reason'               => $archivalReason,
                'archived_by'                   => null,   // system-triggered
                'archived_by_name'              => 'System',
                'original_email'                => $originalEmail,
                'original_phone'                => $originalPhone,
                'original_username'             => $originalUsername,
                'archival_type'                 => 'auto_orphaned_family_member',
                'user_type_at_archival'         => $user->type,
                'days_until_permanent_deletion' => $permanentDeletionDays,
            ];

            $metadata['archival_info'] = $archivalInfo;
            $metadata['archived'] = [
                'archived_at'       => now()->toISOString(),
                'archived_by'       => null,
                'archived_by_name'  => 'System',
                'reason'            => $archivalReason,
                'original_email'    => $originalEmail,
                'original_username' => $originalUsername,
                'original_phone'    => $originalPhone,
                'user_type'         => $user->type,
            ];

            $deletionScheduledAt = now()->addDays($permanentDeletionDays);

            $metadata['deletion_info'] = [
                'scheduled_at' => $deletionScheduledAt->toISOString(),
                'reason'       => 'Auto-deletion after orphan-archival period',
                'notified'     => false,
            ];

            // ---------------------------------------------------------
            // Tombstone identifiers so originals are free to be
            // re-registered.
            // ---------------------------------------------------------
            $archivedEmail    = $this->archivedEmail($originalEmail, $user->id);
            $archivedUsername = $this->archivedUsername($originalUsername, $user->id);

            $user->forceFill([
                'status'                => User::STATUS_ARCHIVED,
                'metadata'              => $metadata,
                'deletion_scheduled_at' => $deletionScheduledAt,
                'email'                 => $archivedEmail,
                'username'              => $archivedUsername,
            ])->saveQuietly();

            // Soft-delete sets `deleted_at`.
            $user->delete();

            Log::info('Family member orphaned — archived automatically', [
                'user_id'               => $user->id,
                'user_name'             => $user->name,
                'original_email'        => $originalEmail,
                'archived_email'        => $archivedEmail,
                'deletion_scheduled_at' => $deletionScheduledAt->toISOString(),
            ]);

            return true;
        });
    }

    protected function archivedEmail(?string $originalEmail, int $userId): ?string
    {
        if (empty($originalEmail)) {
            return null;
        }

        if (str_contains($originalEmail, '.archived.')) {
            return $originalEmail;
        }

        [$local, $domain] = array_pad(explode('@', $originalEmail, 2), 2, 'example.com');

        return $local . '.archived.' . $userId . '@' . $domain;
    }

    protected function archivedUsername(?string $originalUsername, int $userId): ?string
    {
        if (empty($originalUsername)) {
            return null;
        }

        if (str_contains($originalUsername, '.archived.')) {
            return $originalUsername;
        }

        return $originalUsername . '.archived.' . $userId;
    }
}