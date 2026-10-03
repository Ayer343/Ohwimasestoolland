<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait UserArchivalTrait
{
    /**
     * Check if user is archived
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED || !is_null($this->archived_at);
    }

    /**
     * Get is_archived attribute
     */
    public function getIsArchivedAttribute(): bool
    {
        return $this->isArchived();
    }

    /**
     * Check if user can be archived
     * 
     * @return bool
     */
    public function canBeArchived(): bool
    {
        // Don't archive admin users
        if ($this->isAdmin() || $this->isSuperAdmin()) {
            return false;
        }
        
        // Must have no properties
        if ($this->properties()->exists()) {
            return false;
        }
        
        // Must have no pending financial obligations
        if ($this->hasPendingFinancialObligations()) {
            return false;
        }
        
        // Must not already be archived
        if ($this->isArchived()) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if user has pending financial obligations
     */
    public function hasPendingFinancialObligations(): bool
    {
        // Check for unpaid invoices (landlord invoices)
        if ($this->invoices()->where('status', '!=', 'paid')->exists()) {
            return true;
        }
        
        // Check for pending payments
        if ($this->payments()->where('status', 'pending')->exists()) {
            return true;
        }
        
        // Check for unpaid tenant invoices (if user is a tenant)
        if ($this->tenantInvoices()->where('status', '!=', 'paid')->exists()) {
            return true;
        }
        
        return false;
    }

    /**
     * Archive the user account
     * 
     * @param string $reason
     * @param int|null $archivedBy
     * @return bool
     */
    public function archive(string $reason = 'no_properties', ?int $archivedBy = null): bool
    {
        // Check if user can be archived
        if (!$this->canBeArchived()) {
            \Log::warning('Archive failed: User cannot be archived', [
                'user_id' => $this->id,
                'user_name' => $this->name
            ]);
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            // Store original data before archiving
            $originalData = [
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->location,
                'status' => $this->status,
                'type' => $this->type
            ];
            
            // Prepare metadata - create new array instead of modifying existing
            $metadata = $this->metadata ?? [];
            $metadata['archived'] = [
                'archived_at' => now()->toISOString(),
                'archived_by' => $archivedBy ?? auth()->id(),
                'archived_by_name' => $archivedBy ? (self::find($archivedBy)?->name ?? 'System') : (auth()->user()?->name ?? 'System'),
                'archived_reason' => $reason,
                'original_data' => $originalData,
                'original_user_type' => $this->getOriginal('type'),
                'properties_count' => $this->properties()->count(),
                'last_property_ownership' => $this->last_property_ownership
            ];
            
            // Update user record
            $updated = $this->update([
                'status' => self::STATUS_ARCHIVED,
                'type' => self::TYPE_FORMER_LANDLORD,
                'archived_at' => now(),
                'email' => 'archived_' . $this->id . '_' . time() . '@deleted.local',
                'phone' => null,
                'location' => null,
                'digital_address' => null,
                'can_login' => false,
                'api_access' => false,
                'metadata' => $metadata
            ]);
            
            if (!$updated) {
                throw new \Exception('Failed to update user record');
            }
            
            // Revoke all API tokens
            $this->tokens()->delete();
            
            // Clear all user sessions
            DB::table('sessions')->where('user_id', $this->id)->delete();
            
            DB::commit();
            
            \Log::info('User account archived successfully', [
                'user_id' => $this->id,
                'user_name' => $this->name,
                'reason' => $reason,
                'archived_by' => $archivedBy ?? auth()->id()
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Failed to archive user account', [
                'user_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Restore an archived account
     * 
     * @return bool
     */
    public function restoreArchived(): bool
    {
        if (!$this->isArchived()) {
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            // Get original data from metadata
            $archivedData = $this->metadata['archived'] ?? [];
            $originalData = $archivedData['original_data'] ?? [];
            
            // Prepare metadata update - DON'T modify directly, create a new array
            $metadata = $this->metadata ?? [];
            
            // Add restoration info
            $metadata['restored'] = [
                'restored_at' => now()->toISOString(),
                'restored_by' => auth()->id(),
                'restored_by_name' => auth()->user()?->name ?? 'System',
                'previous_archive_data' => $archivedData
            ];
            
            // Remove the archived flag from metadata
            if (isset($metadata['archived'])) {
                unset($metadata['archived']);
            }
            
            // Update the user - set metadata as a new array, not modified in place
            $updated = $this->update([
                'status' => $originalData['status'] ?? self::STATUS_INACTIVE,
                'type' => $originalData['original_user_type'] ?? self::TYPE_LANDLORD,
                'archived_at' => null,
                'email' => $originalData['email'] ?? $this->email,
                'phone' => $originalData['phone'] ?? null,
                'location' => $originalData['address'] ?? null,
                'can_login' => true,
                'api_access' => true,
                'metadata' => $metadata
            ]);
            
            if (!$updated) {
                throw new \Exception('Failed to restore user');
            }
            
            DB::commit();
            
            \Log::info('User account restored from archive', [
                'user_id' => $this->id,
                'user_name' => $this->name,
                'restored_by' => auth()->id()
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Failed to restore archived user', [
                'user_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check if user can be restored
     */
    public function getCanBeRestoredAttribute(): bool
    {
        return $this->isArchived();
    }

    /**
     * Schedule account for archival (not deletion)
     * 
     * @param int $daysUntilArchival
     * @return bool
     */
    public function scheduleArchival(int $daysUntilArchival = 30): bool
    {
        // Don't schedule if already archived
        if ($this->isArchived()) {
            return false;
        }
        
        // Don't schedule if not eligible
        if (!$this->canBeArchived()) {
            return false;
        }
        
        $scheduledDate = now()->addDays($daysUntilArchival);
        
        $this->update([
            'deletion_scheduled_at' => $scheduledDate,
            'metadata' => array_merge($this->metadata ?? [], [
                'archival_scheduled' => [
                    'scheduled_at' => now()->toISOString(),
                    'scheduled_for' => $scheduledDate->toISOString(),
                    'days_until_archival' => $daysUntilArchival,
                    'notifications_sent' => false
                ]
            ])
        ]);
        
        \Log::info('User scheduled for archival', [
            'user_id' => $this->id,
            'user_name' => $this->name,
            'scheduled_date' => $scheduledDate->toISOString()
        ]);
        
        return true;
    }

    /**
     * Get archival information
     */
    public function getArchivalInfoAttribute(): ?array
    {
        if (!$this->isArchived()) {
            return null;
        }
        
        $archivedData = $this->metadata['archived'] ?? [];
        
        return [
            'is_archived' => true,
            'archived_at' => $this->archived_at?->toISOString(),
            'archived_reason' => $archivedData['archived_reason'] ?? 'unknown',
            'archived_by' => $archivedData['archived_by'] ?? null,
            'original_email' => $archivedData['original_data']['email'] ?? null,
            'original_type' => $archivedData['original_user_type'] ?? null,
            'deletion_scheduled_at' => $this->deletion_scheduled_at?->toISOString(),
            'days_since_archival' => $this->archived_at ? $this->archived_at->diffInDays(now()) : null,
        ];
    }

    /**
     * Scope to find user by original email from archived metadata
     */
    public function scopeWhereOriginalEmail($query, $email)
    {
        return $query->where('status', 'archived')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.archived.original_data.email")) = ?', [$email]);
    }

    /**
     * Scope to find user by original phone from archived metadata
     */
    public function scopeWhereOriginalPhone($query, $phone)
    {
        return $query->where('status', 'archived')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.archived.original_data.phone")) = ?', [$phone]);
    }
}