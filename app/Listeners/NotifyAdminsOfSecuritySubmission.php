<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Models\User;
use App\Notifications\SecurityPersonnelSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyAdminsOfSecuritySubmission implements ShouldQueue
{
    public function handle(UserCreated $event): void
    {
        $head = $this->resolveActor($event);
        $user = $event->user ?? null;

        if (! $user instanceof User) {
            Log::debug('NotifyAdminsOfSecuritySubmission: no user on event', [
                'event_class' => get_class($event),
                'properties'  => array_keys(get_object_vars($event)),
            ]);
            return;
        }

        if ((int) $user->type !== User::TYPE_SECURITY_PERSONNEL) {
            Log::debug('NotifyAdminsOfSecuritySubmission: user is not security personnel', [
                'user_id'   => $user->id,
                'user_type' => $user->type,
            ]);
            return;
        }

        if (! $head instanceof User) {
            Log::warning('NotifyAdminsOfSecuritySubmission: could not resolve submitting head', [
                'user_id'     => $user->id,
                'event_class' => get_class($event),
                'properties'  => array_keys(get_object_vars($event)),
            ]);
            return;
        }

        $freshUser = $user->fresh();

        if (! $freshUser) {
            Log::warning('NotifyAdminsOfSecuritySubmission: user was deleted before listener ran', [
                'user_id' => $user->id,
            ]);
            return;
        }

        // ----------------------------------------------------------
        // 🔧 FIX: read the metadata column RAW from the DB, not via
        // the model's array cast. The cast can silently return null
        // for double-encoded JSON, causing awaiting_approval to look
        // empty even when the value is present.
        //
        // Log the raw value so we can see exactly what's stored.
        // ----------------------------------------------------------
        $rawMetadata = DB::table('users')->where('id', $freshUser->id)->value('metadata');

        $metadata = $this->decodeMetadata($rawMetadata);

        // Fall back to the cast if raw read returned nothing useful.
        if (empty($metadata)) {
            $metadata = $this->decodeMetadata($freshUser->metadata);
        }

        Log::info('NotifyAdminsOfSecuritySubmission: inspecting submission', [
            'user_id'            => $freshUser->id,
            'raw_metadata'       => is_string($rawMetadata)
                ? substr($rawMetadata, 0, 300)
                : $rawMetadata,
            'decoded_metadata'   => $metadata,
            'awaiting_approval'  => $metadata['awaiting_approval'] ?? null,
            'created_by_head_id' => $metadata['created_by_head_id'] ?? null,
            'queued_invitation'  => isset($metadata['queued_invitation']),
        ]);

        // ----------------------------------------------------------
        // Only notify while it's still awaiting approval. The check
        // accepts true, 'true', 1, and '1' — anything truthy.
        // ----------------------------------------------------------
        $awaiting = $metadata['awaiting_approval'] ?? false;

        if (! $awaiting && $awaiting !== 'true' && $awaiting !== 1 && $awaiting !== '1') {
            Log::warning('NotifyAdminsOfSecuritySubmission: skipping — awaiting_approval is falsy', [
                'user_id'           => $freshUser->id,
                'awaiting_value'    => $awaiting,
                'awaiting_type'     => gettype($awaiting),
                'decoded_metadata'  => $metadata,
            ]);
            return;
        }

        // ----------------------------------------------------------
        // Confirm the head matches what's in metadata (defense in depth).
        // ----------------------------------------------------------
        $headFromMetadata = isset($metadata['created_by_head_id'])
            ? (int) $metadata['created_by_head_id']
            : null;

        if ($headFromMetadata !== null && $headFromMetadata !== (int) $head->id) {
            Log::warning('NotifyAdminsOfSecuritySubmission: head mismatch', [
                'user_id'              => $freshUser->id,
                'event_head_id'        => $head->id,
                'metadata_head_id'     => $headFromMetadata,
            ]);
            return;
        }

        // ----------------------------------------------------------
        // Find the admins to notify.
        // ----------------------------------------------------------
        $admins = User::query()
            ->whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', $head->id)
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('NotifyAdminsOfSecuritySubmission: no active admins to notify', [
                'user_id'      => $freshUser->id,
                'submitted_by' => $head->id,
            ]);
            return;
        }

        $queuedInvitation = $metadata['queued_invitation'] ?? null;

        $sent   = 0;
        $failed = 0;

        foreach ($admins as $admin) {
            try {
                $admin->notify(new SecurityPersonnelSubmittedNotification(
                    submittedUser: $freshUser,
                    head: $head,
                    queuedInvitation: is_array($queuedInvitation) ? $queuedInvitation : null,
                ));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('NotifyAdminsOfSecuritySubmission: notify() failed for one admin', [
                    'admin_id' => $admin->id,
                    'user_id'  => $freshUser->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        Log::info('NotifyAdminsOfSecuritySubmission: dispatched', [
            'user_id'      => $freshUser->id,
            'submitted_by' => $head->id,
            'admins_found' => $admins->count(),
            'sent'         => $sent,
            'failed'       => $failed,
            'notif_class'  => SecurityPersonnelSubmittedNotification::class,
        ]);
    }

    /**
     * Decode metadata defensively — handles array, single-encoded JSON
     * string, double-encoded JSON string, and null/empty.
     */
    private function decodeMetadata($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        // Handle double-encoded: the first decode returns a JSON string
        // instead of an array (happens when the column was written with
        // an already-json_encoded string).
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true) ?? [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve the submitting user from the event, tolerating whatever
     * property name UserCreated chose for its second argument.
     */
    private function resolveActor(UserCreated $event): ?User
    {
        foreach (['createdBy', 'creator', 'actor', 'byUser', 'authUser', 'submittedBy'] as $prop) {
            if (isset($event->{$prop}) && $event->{$prop} instanceof User) {
                return $event->{$prop};
            }
        }

        return null;
    }
}