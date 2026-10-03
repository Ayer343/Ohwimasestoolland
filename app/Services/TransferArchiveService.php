<?php

namespace App\Services;

use App\Models\PropertyOwnershipTransfer;
use App\Models\PropertyOwnershipTransferArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TransferArchiveService
{
    /**
     * Archive transfers from a specific year
     * 
     * @param int $year Year to archive (e.g., 2025)
     * @param array $options Archiving options
     * @return array Result with counts and details
     */
    public function archiveYear(int $year, array $options = []): array
    {
        $defaults = [
            'permanently_delete_after_archive' => true, // Permanently delete instead of soft delete
            'delete_files' => true, // Delete associated files when archiving
            'include_soft_deleted' => true, // Include soft-deleted records (trashed)
            'archive_completed' => true,
            'archive_rejected' => true,
            'archive_cancelled' => true,
            'archive_pending' => false,
            'archive_approved' => false,
            'chunk_size' => 100,
            'archived_by' => null,
            'archive_reason' => 'yearly_cleanup',
        ];
        
        $options = array_merge($defaults, $options);
        
        // Build status filter
        $statuses = [];
        if ($options['archive_completed']) $statuses[] = 'completed';
        if ($options['archive_rejected']) $statuses[] = 'rejected';
        if ($options['archive_cancelled']) $statuses[] = 'cancelled';
        if ($options['archive_pending']) $statuses[] = 'pending';
        if ($options['archive_approved']) $statuses[] = 'approved';
        
        if (empty($statuses)) {
            return [
                'success' => false,
                'message' => 'No statuses selected for archiving',
                'archived_count' => 0,
                'skipped_count' => 0,
            ];
        }
        
        // Query transfers in the specified year - INCLUDING soft-deleted records
        $query = PropertyOwnershipTransfer::withTrashed() // This includes soft-deleted (trashed) records
            ->whereIn('status', $statuses)
            ->where(function($q) use ($year) {
                $q->whereYear('completed_at', $year)
                  ->orWhereYear('rejected_at', $year)
                  ->orWhereYear('cancelled_at', $year)
                  ->orWhereYear('created_at', $year);
            });
        
        // If we don't want to include soft-deleted, uncomment this line
        // if (!$options['include_soft_deleted']) {
        //     $query->whereNull('deleted_at');
        // }
        
        $totalToArchive = $query->count();
        
        if ($totalToArchive === 0) {
            return [
                'success' => true,
                'message' => "No transfers found to archive for year {$year}",
                'archived_count' => 0,
                'skipped_count' => 0,
            ];
        }
        
        $archivedCount = 0;
        $failedCount = 0;
        $failedIds = [];
        
        DB::beginTransaction();
        
        try {
            $query->chunk($options['chunk_size'], function($transfers) use (&$archivedCount, &$failedCount, &$failedIds, $year, $options) {
                foreach ($transfers as $transfer) {
                    try {
                        // Skip if already archived (check by original_transfer_id)
                        $existingArchive = PropertyOwnershipTransferArchive::where('original_transfer_id', $transfer->id)->first();
                        if ($existingArchive) {
                            Log::info("Transfer #{$transfer->id} already archived, skipping", [
                                'existing_archive_id' => $existingArchive->id
                            ]);
                            continue;
                        }
                        
                        // Create archive record
                        $archiveData = $this->prepareArchiveData($transfer, $year, $options);
                        $archive = PropertyOwnershipTransferArchive::create($archiveData);
                        
                        // PERMANENTLY DELETE the original (not soft delete)
                        if ($options['permanently_delete_after_archive']) {
                            // Delete associated files first if configured
                            if ($options['delete_files']) {
                                if ($transfer->document_url && Storage::disk('public')->exists($transfer->document_url)) {
                                    Storage::disk('public')->delete($transfer->document_url);
                                    Log::info("Deleted document file for transfer #{$transfer->id}", [
                                        'file' => $transfer->document_url
                                    ]);
                                }
                                if ($transfer->certificate_url && Storage::disk('public')->exists($transfer->certificate_url)) {
                                    Storage::disk('public')->delete($transfer->certificate_url);
                                    Log::info("Deleted certificate file for transfer #{$transfer->id}", [
                                        'file' => $transfer->certificate_url
                                    ]);
                                }
                            }
                            
                            // Permanently delete the transfer (force delete - removes from both active and trash)
                            $transfer->forceDelete();
                        }
                        
                        $archivedCount++;
                        
                        // Log the archiving action
                        \App\Models\ActivityLog::create([
                            'user_id' => $options['archived_by'] ?? auth()->id(),
                            'action' => 'transfer_archived',
                            'description' => "Archived ownership transfer #{$transfer->id} for year {$year} (permanently removed from main table)",
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'metadata' => [
                                'transfer_id' => $transfer->id,
                                'archive_id' => $archive->id,
                                'year' => $year,
                                'status' => $transfer->status,
                                'was_trashed' => $transfer->trashed(),
                                'files_deleted' => $options['delete_files'],
                            ]
                        ]);
                        
                    } catch (\Exception $e) {
                        $failedCount++;
                        $failedIds[] = $transfer->id;
                        Log::error("Failed to archive transfer {$transfer->id}", [
                            'error' => $e->getMessage(),
                            'year' => $year,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            });
            
            // Clear caches
            $this->clearArchiveCaches();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => "Successfully archived {$archivedCount} transfers for year {$year}",
                'archived_count' => $archivedCount,
                'failed_count' => $failedCount,
                'failed_ids' => $failedIds,
                'total_processed' => $totalToArchive,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Archive process failed for year {$year}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => "Archive failed: " . $e->getMessage(),
                'archived_count' => $archivedCount,
                'failed_count' => $failedCount,
            ];
        }
    }
    
    /**
     * Prepare data for archiving
     */
    private function prepareArchiveData($transfer, int $year, array $options): array
    {
        // Parse metadata if it's a string
        $metadata = $transfer->metadata;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        
        return [
            'original_transfer_id' => $transfer->id,
            'property_id' => $transfer->property_id,
            'current_landlord_id' => $transfer->current_landlord_id,
            'new_landlord_id' => $transfer->new_landlord_id,
            'requested_by_id' => $transfer->requested_by_id,
            'admin_approved_by_id' => $transfer->admin_approved_by_id,
            'completed_by_id' => $transfer->completed_by_id,
            'rejected_by_id' => $transfer->rejected_by_id,
            'deleted_by' => $transfer->deleted_by,
            'status' => $transfer->status,
            'transfer_date' => $transfer->transfer_date,
            'sale_amount' => $transfer->sale_amount,
            'document_type' => $transfer->document_type,
            'document_reference' => $transfer->document_reference,
            'document_url' => $transfer->document_url,
            'certificate_url' => $transfer->certificate_url,
            'new_owner_name' => $transfer->new_owner_name,
            'new_owner_phone' => $transfer->new_owner_phone,
            'new_owner_email' => $transfer->new_owner_email,
            'new_owner_address' => $transfer->new_owner_address,
            'reason_for_transfer' => $transfer->reason_for_transfer,
            'notes' => $transfer->notes,
            'admin_notes' => $transfer->admin_notes,
            'rejection_reason' => $transfer->rejection_reason,
            'approved_at' => $transfer->approved_at,
            'completed_at' => $transfer->completed_at,
            'rejected_at' => $transfer->rejected_at,
            'cancelled_at' => $transfer->cancelled_at,
            'can_resubmit_after' => $transfer->can_resubmit_after,
            'archive_year' => (string) $year,
            'archive_month' => date('m'),
            'archived_at' => now(),
            'archived_by' => $options['archived_by'] ?? auth()->id(),
            'archive_reason' => $options['archive_reason'],
            'metadata' => $metadata,
            'original_metadata' => json_encode([
                'created_at' => $transfer->created_at->toISOString(),
                'updated_at' => $transfer->updated_at->toISOString(),
                'deleted_at' => $transfer->deleted_at ? $transfer->deleted_at->toISOString() : null,
                'original_status' => $transfer->status,
                'was_trashed' => $transfer->trashed(),
                'property_name' => $transfer->property ? $transfer->property->property_name : null,
                'property_registration' => $transfer->property ? $transfer->property->registration_pattern : null,
            ]),
        ];
    }
    
    /**
     * Restore archived transfers back to main table
     */
    public function restoreFromArchive(array $archiveIds, array $options = []): array
    {
        $defaults = [
            'restore_files' => true,
            'restored_by' => null,
            'delete_after_restore' => false,
        ];
        
        $options = array_merge($defaults, $options);
        
        $archives = PropertyOwnershipTransferArchive::whereIn('id', $archiveIds)->get();
        
        $restoredCount = 0;
        $failedCount = 0;
        $failedIds = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($archives as $archive) {
                try {
                    // Check if a transfer with this reference already exists
                    $existingTransfer = PropertyOwnershipTransfer::withTrashed()
                        ->where('document_reference', $archive->document_reference)
                        ->first();
                    
                    if ($existingTransfer && !$existingTransfer->trashed()) {
                        // Transfer already exists and is active
                        Log::info("Transfer with reference {$archive->document_reference} already exists, skipping restore", [
                            'existing_transfer_id' => $existingTransfer->id
                        ]);
                        $restoredCount++;
                        continue;
                    }
                    
                    // Create new transfer from archive data
                    $transferData = $this->prepareRestoreData($archive);
                    $newTransfer = PropertyOwnershipTransfer::create($transferData);
                    
                    // Update archive with new transfer ID
                    $archive->update(['original_transfer_id' => $newTransfer->id]);
                    
                    // Optionally delete archive record
                    if ($options['delete_after_restore']) {
                        $archive->delete();
                    }
                    
                    $restoredCount++;
                    
                    // Log the restoration action
                    \App\Models\ActivityLog::create([
                        'user_id' => $options['restored_by'] ?? auth()->id(),
                        'action' => 'transfer_restored_from_archive',
                        'description' => "Restored archived transfer #{$archive->id} back to active transfers as #{$newTransfer->id}",
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'metadata' => [
                            'archive_id' => $archive->id,
                            'new_transfer_id' => $newTransfer->id,
                            'archive_year' => $archive->archive_year,
                            'document_reference' => $archive->document_reference,
                        ]
                    ]);
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $failedIds[] = $archive->id;
                    Log::error("Failed to restore archive {$archive->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            DB::commit();
            $this->clearArchiveCaches();
            
            return [
                'success' => true,
                'restored_count' => $restoredCount,
                'failed_count' => $failedCount,
                'failed_ids' => $failedIds,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Restore process failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'restored_count' => $restoredCount,
                'failed_count' => $failedCount,
            ];
        }
    }
    
    /**
     * Prepare data for restoring from archive
     */
    private function prepareRestoreData($archive): array
    {
        // Parse metadata if it's a string
        $metadata = $archive->metadata;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        
        // Parse original metadata
        $originalMetadata = $archive->original_metadata;
        if (is_string($originalMetadata)) {
            $originalMetadata = json_decode($originalMetadata, true);
        }
        
        return [
            'property_id' => $archive->property_id,
            'current_landlord_id' => $archive->current_landlord_id,
            'new_landlord_id' => $archive->new_landlord_id,
            'requested_by_id' => $archive->requested_by_id,
            'admin_approved_by_id' => $archive->admin_approved_by_id,
            'completed_by_id' => $archive->completed_by_id,
            'rejected_by_id' => $archive->rejected_by_id,
            'status' => $archive->status,
            'transfer_date' => $archive->transfer_date,
            'sale_amount' => $archive->sale_amount,
            'document_type' => $archive->document_type,
            'document_reference' => $archive->document_reference,
            'document_url' => $archive->document_url,
            'certificate_url' => $archive->certificate_url,
            'new_owner_name' => $archive->new_owner_name,
            'new_owner_phone' => $archive->new_owner_phone,
            'new_owner_email' => $archive->new_owner_email,
            'new_owner_address' => $archive->new_owner_address,
            'reason_for_transfer' => $archive->reason_for_transfer,
            'notes' => $archive->notes,
            'admin_notes' => $archive->admin_notes,
            'rejection_reason' => $archive->rejection_reason,
            'approved_at' => $archive->approved_at,
            'completed_at' => $archive->completed_at,
            'rejected_at' => $archive->rejected_at,
            'cancelled_at' => $archive->cancelled_at,
            'can_resubmit_after' => $archive->can_resubmit_after,
            'metadata' => $metadata,
            'created_at' => $originalMetadata['created_at'] ?? now(),
            'updated_at' => now(),
        ];
    }
    
    /**
     * Delete old archives (permanent deletion)
     */
    public function deleteOldArchives(int $olderThanYears = 7): array
    {
        $cutoffYear = now()->subYears($olderThanYears)->format('Y');
        
        $archives = PropertyOwnershipTransferArchive::where('archive_year', '<', $cutoffYear)->get();
        
        $deletedCount = 0;
        $failedCount = 0;
        
        foreach ($archives as $archive) {
            try {
                // Delete associated files
                if ($archive->document_url && Storage::disk('public')->exists($archive->document_url)) {
                    Storage::disk('public')->delete($archive->document_url);
                }
                if ($archive->certificate_url && Storage::disk('public')->exists($archive->certificate_url)) {
                    Storage::disk('public')->delete($archive->certificate_url);
                }
                
                $archive->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to delete archive {$archive->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info("Old archives cleanup completed", [
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'cutoff_year' => $cutoffYear,
        ]);
        
        return [
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'cutoff_year' => $cutoffYear,
        ];
    }
    
    /**
     * Archive a single transfer by ID
     * 
     * @param int $transferId
     * @param array $options
     * @return array
     */
    public function archiveSingle(int $transferId, array $options = []): array
    {
        $defaults = [
            'permanently_delete_after_archive' => true,
            'delete_files' => true,
            'archived_by' => null,
            'archive_reason' => 'manual_archiving',
        ];
        
        $options = array_merge($defaults, $options);
        
        // Find the transfer (including soft-deleted)
        $transfer = PropertyOwnershipTransfer::withTrashed()->find($transferId);
        
        if (!$transfer) {
            return [
                'success' => false,
                'message' => "Transfer #{$transferId} not found",
            ];
        }
        
        // Check if already archived
        $existingArchive = PropertyOwnershipTransferArchive::where('original_transfer_id', $transferId)->first();
        if ($existingArchive) {
            return [
                'success' => false,
                'message' => "Transfer #{$transferId} is already archived (Archive ID: {$existingArchive->id})",
            ];
        }
        
        DB::beginTransaction();
        
        try {
            // Create archive record
            $year = $transfer->completed_at ? $transfer->completed_at->year : date('Y');
            $archiveData = $this->prepareArchiveData($transfer, $year, $options);
            $archive = PropertyOwnershipTransferArchive::create($archiveData);
            
            // Permanently delete the original
            if ($options['permanently_delete_after_archive']) {
                if ($options['delete_files']) {
                    if ($transfer->document_url && Storage::disk('public')->exists($transfer->document_url)) {
                        Storage::disk('public')->delete($transfer->document_url);
                    }
                    if ($transfer->certificate_url && Storage::disk('public')->exists($transfer->certificate_url)) {
                        Storage::disk('public')->delete($transfer->certificate_url);
                    }
                }
                $transfer->forceDelete();
            }
            
            DB::commit();
            $this->clearArchiveCaches();
            
            return [
                'success' => true,
                'message' => "Successfully archived transfer #{$transferId}",
                'archive_id' => $archive->id,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to archive single transfer {$transferId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => "Archive failed: " . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get archive statistics
     */
    public function getArchiveStatistics(): array
    {
        return [
            'total_archived' => PropertyOwnershipTransferArchive::count(),
            'by_year' => PropertyOwnershipTransferArchive::selectRaw('archive_year, COUNT(*) as count')
                ->groupBy('archive_year')
                ->orderBy('archive_year', 'desc')
                ->get()
                ->map(function($item) {
                    return [
                        'archive_year' => $item->archive_year,
                        'count' => $item->count,
                    ];
                })
                ->toArray(),
            'by_status' => PropertyOwnershipTransferArchive::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->map(function($item) {
                    return [
                        'status' => $item->status,
                        'status_label' => ucfirst($item->status),
                        'count' => $item->count,
                    ];
                })
                ->toArray(),
            'total_value' => PropertyOwnershipTransferArchive::where('status', 'completed')->sum('sale_amount') ?? 0,
            'oldest_archive' => PropertyOwnershipTransferArchive::min('archive_year'),
            'newest_archive' => PropertyOwnershipTransferArchive::max('archive_year'),
            'storage_usage_mb' => $this->calculateStorageUsage(),
        ];
    }
    
    /**
     * Calculate storage usage of archive files
     */
    private function calculateStorageUsage(): float
    {
        $archives = PropertyOwnershipTransferArchive::all();
        $totalBytes = 0;
        
        foreach ($archives as $archive) {
            if ($archive->document_url && Storage::disk('public')->exists($archive->document_url)) {
                try {
                    $totalBytes += Storage::disk('public')->size($archive->document_url);
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
            if ($archive->certificate_url && Storage::disk('public')->exists($archive->certificate_url)) {
                try {
                    $totalBytes += Storage::disk('public')->size($archive->certificate_url);
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
        }
        
        return round($totalBytes / (1024 * 1024), 2);
    }
    
    /**
     * Clear archive-related caches
     */
    private function clearArchiveCaches(): void
    {
        Cache::forget('archive_statistics');
        Cache::forget('archive_years');
        Cache::forget('archive_export_data');
        Cache::forget('trashed_transfers_stats');
    }
}