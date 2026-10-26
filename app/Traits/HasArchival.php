<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * User account archival (soft archival — not Laravel soft-deletes).
 *
 * Behaviour identical to the in-model implementation it replaces.
 */
trait HasArchival
{
    /* ============================================================
     | STATE CHECKS
     * ============================================================ */

    public function isArchived(): bool
    {
        return $this->status === static::STATUS_ARCHIVED || !is_null($this->archived_at);
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->isArchived();
    }

    public function getCanBeRestoredAttribute(): bool
    {
        return $this->isArchived();
    }

    public function canBeArchived(): bool
    {
        if ($this->isAdmin() || $this->isSuperAdmin()) {
            return false;
        }

        if (method_exists($this, 'properties') && $this->properties()->exists()) {
            return false;
        }

        if ($this->hasPendingFinancialObligations()) {
            return false;
        }

        if ($this->isArchived()) {
            return false;
        }

        return true;
    }

    public function hasPendingFinancialObligations(): bool
    {
        return $this->invoices()->where('status', '!=', 'paid')->exists()
            || $this->payments()->where('status', 'pending')->exists()
            || $this->tenantInvoices()->where('status', '!=', 'paid')->exists();
    }

    /* ============================================================
     | ACCESSORS
     * ============================================================ */

    public function getArchivalInfoAttribute(): ?array
    {
        if (!$this->isArchived()) {
            return null;
        }

        $data = $this->metadata['archived'] ?? [];

        return [
            'is_archived'           => true,
            'archived_at'           => $this->archived_at?->toISOString(),
            'archived_reason'       => $data['archived_reason'] ?? 'unknown',
            'archived_by'           => $data['archived_by'] ?? null,
            'original_email'        => $data['original_data']['email'] ?? null,
            'original_type'         => $data['original_user_type'] ?? null,
            'deletion_scheduled_at' => $this->deletion_scheduled_at?->toISOString(),
            'days_since_archival'   => $this->archived_at ? $this->archived_at->diffInDays(now()) : null,
        ];
    }

    public function getOriginalEmailForDisplay(): string
    {
        if (!$this->isArchived()) {
            return $this->email;
        }

        $metadata = $this->metadata;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        }

        return $metadata['archival_info']['original_email']
            ?? $metadata['archived']['original_data']['email']
            ?? $metadata['archived']['original_email']
            ?? $this->email;
    }

    /* ============================================================
     | MUTATIONS
     * ============================================================ */

    public function archive(string $reason = 'no_properties', ?int $archivedBy = null): bool
    {
        if (!$this->canBeArchived()) {
            Log::warning('Archive failed: User cannot be archived', [
                'user_id'   => $this->id,
                'user_name' => $this->name,
            ]);
            return false;
        }

        try {
            return DB::transaction(function () use ($reason, $archivedBy) {
                $originalData = [
                    'email'   => $this->email,
                    'phone'   => $this->phone,
                    'address' => $this->location,
                    'status'  => $this->status,
                    'type'    => $this->type,
                ];

                $metadata = $this->metadata ?? [];
                $metadata['archived'] = [
                    'archived_at'             => now()->toISOString(),
                    'archived_by'             => $archivedBy ?? auth()->id(),
                    'archived_by_name'        => $archivedBy
                        ? (User::find($archivedBy)?->name ?? 'System')
                        : (auth()->user()?->name ?? 'System'),
                    'archived_reason'         => $reason,
                    'original_data'           => $originalData,
                    'original_user_type'      => $this->getOriginal('type'),
                    'properties_count'        => method_exists($this, 'properties') ? $this->properties()->count() : 0,
                    'last_property_ownership' => $this->last_property_ownership,
                ];

                $updated = $this->update([
                    'status'          => static::STATUS_ARCHIVED,
                    'type'            => static::TYPE_FORMER_LANDLORD,
                    'archived_at'     => now(),
                    'email'           => 'archived_' . $this->id . '_' . time() . '@deleted.local',
                    'phone'           => null,
                    'location'        => null,
                    'digital_address' => null,
                    'can_login'       => false,
                    'api_access'      => false,
                    'metadata'        => $metadata,
                ]);

                if (!$updated) {
                    throw new \RuntimeException('Failed to update user record');
                }

                if (method_exists($this, 'tokens')) {
                    $this->tokens()->delete();
                }

                DB::table('sessions')->where('user_id', $this->id)->delete();

                Log::info('User account archived successfully', [
                    'user_id'     => $this->id,
                    'user_name'   => $this->name,
                    'reason'      => $reason,
                    'archived_by' => $archivedBy ?? auth()->id(),
                ]);

                return true;
            });

        } catch (\Throwable $e) {
            Log::error('Failed to archive user account', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    public function restoreArchived(): bool
    {
        if (!$this->isArchived()) {
            return false;
        }

        try {
            return DB::transaction(function () {
                $archived     = $this->metadata['archived'] ?? [];
                $originalData = $archived['original_data'] ?? [];

                $metadata = $this->metadata ?? [];
                $metadata['restored'] = [
                    'restored_at'           => now()->toISOString(),
                    'restored_by'           => auth()->id(),
                    'restored_by_name'      => auth()->user()?->name ?? 'System',
                    'previous_archive_data' => $archived,
                ];

                unset($metadata['archived']);

                $updated = $this->update([
                    'status'      => $originalData['status'] ?? static::STATUS_INACTIVE,
                    'type'        => $archived['original_user_type']
                        ?? $originalData['type']
                        ?? static::TYPE_LANDLORD,
                    'archived_at' => null,
                    'email'       => $originalData['email'] ?? $this->email,
                    'phone'       => $originalData['phone'] ?? null,
                    'location'    => $originalData['address'] ?? null,
                    'can_login'   => true,
                    'api_access'  => true,
                    'metadata'    => $metadata,
                ]);

                if (!$updated) {
                    throw new \RuntimeException('Failed to restore user');
                }

                Log::info('User account restored from archive', [
                    'user_id'     => $this->id,
                    'user_name'   => $this->name,
                    'restored_by' => auth()->id(),
                ]);

                return true;
            });

        } catch (\Throwable $e) {
            Log::error('Failed to restore archived user', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function scheduleArchival(int $daysUntilArchival = 30): bool
    {
        if ($this->isArchived() || !$this->canBeArchived()) {
            return false;
        }

        $scheduledDate = now()->addDays($daysUntilArchival);

        $this->update([
            'deletion_scheduled_at' => $scheduledDate,
            'metadata'              => array_merge($this->metadata ?? [], [
                'archival_scheduled' => [
                    'scheduled_at'        => now()->toISOString(),
                    'scheduled_for'       => $scheduledDate->toISOString(),
                    'days_until_archival' => $daysUntilArchival,
                    'notifications_sent'  => false,
                ],
            ]),
        ]);

        Log::info('User scheduled for archival', [
            'user_id'        => $this->id,
            'user_name'      => $this->name,
            'scheduled_date' => $scheduledDate->toISOString(),
        ]);

        return true;
    }

    /* ============================================================
     | STATE HELPERS (kept for BC — some callers use these)
     * ============================================================ */

    public function activate(): bool
    {
        return !$this->isArchived() && $this->update(['status' => static::STATUS_ACTIVE]);
    }

    public function suspend(): bool
    {
        return !$this->isArchived() && $this->update(['status' => static::STATUS_SUSPENDED]);
    }

    public function deactivate(): bool
    {
        return !$this->isArchived() && $this->update(['status' => static::STATUS_INACTIVE]);
    }
}