<?php

namespace App\Services;

use App\Models\PropertyOwnershipTransfer;
use App\Models\PropertyOwnershipTransferArchive;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AutomatedArchiveService
{
    /**
     * Archive transfers for a specific year
     */
    public function archiveYear(int $year, array $options = []): array
    {
        $defaults = [
            'statuses' => ['completed', 'rejected', 'cancelled'],
            'include_reversals' => true,
            'soft_delete_after_archive' => true,
            'batch_size' => 100,
            'force' => false
        ];
        
        $options = array_merge($defaults, $options);
        
        $result = [
            'success' => true,
            'archived_count' => 0,
            'regular_count' => 0,
            'reversal_count' => 0,
            'failed_count' => 0,
            'failed_ids' => [],
            'message' => ''
        ];
        
        // Build query
        $query = PropertyOwnershipTransfer::query()
            ->whereYear('created_at', '<=', $year)
            ->whereIn('status', $options['statuses'])
            ->whereNull('deleted_at');
        
        if (!$options['include_reversals']) {
            $query->where(function($q) {
                $q->whereNull('metadata->is_reversal')
                  ->orWhere('metadata->is_reversal', false);
            });
        }
        
        $totalCount = $query->count();
        
        if ($totalCount === 0) {
            $result['message'] = "No transfers found to archive for year {$year}";
            return $result;
        }
        
        DB::beginTransaction();
        
        try {
            $query->chunk($options['batch_size'], function($transfers) use ($year, $options, &$result) {
                foreach ($transfers as $transfer) {
                    try {
                        // Check if already archived
                        $existingArchive = PropertyOwnershipTransferArchive::where('original_transfer_id', $transfer->id)
                            ->orWhere('document_reference', $transfer->document_reference)
                            ->first();
                        
                        if ($existingArchive && !$options['force']) {
                            continue;
                        }
                        
                        // Create archive record
                        $archiveData = $this->prepareArchiveData($transfer, $year);
                        
                        if ($existingArchive && $options['force']) {
                            $existingArchive->update($archiveData);
                            $archive = $existingArchive;
                        } else {
                            $archive = PropertyOwnershipTransferArchive::create($archiveData);
                        }
                        
                        // Soft delete the original if requested
                        if ($options['soft_delete_after_archive']) {
                            $transfer->delete();
                        }
                        
                        // Update counts
                        $result['archived_count']++;
                        if ($transfer->isReversalRecord()) {
                            $result['reversal_count']++;
                        } else {
                            $result['regular_count']++;
                        }
                        
                        // Log the activity
                        \App\Models\ActivityLog::create([
                            'user_id' => null,
                            'action' => 'auto_yearly_archive',
                            'description' => "Auto-archived transfer #{$transfer->id} for year {$year}",
                            'ip_address' => 'system',
                            'user_agent' => 'Automated Archive Service',
                            'metadata' => [
                                'transfer_id' => $transfer->id,
                                'archive_id' => $archive->id,
                                'archive_year' => $year,
                                'is_reversal' => $transfer->isReversalRecord()
                            ]
                        ]);
                        
                    } catch (\Exception $e) {
                        $result['failed_count']++;
                        $result['failed_ids'][] = $transfer->id;
                        Log::error("Failed to archive transfer #{$transfer->id}: " . $e->getMessage());
                    }
                }
            });
            
            DB::commit();
            
            $result['message'] = sprintf(
                "Successfully archived %d records (%d regular, %d reversal) for year %d. Failed: %d",
                $result['archived_count'],
                $result['regular_count'],
                $result['reversal_count'],
                $year,
                $result['failed_count']
            );
            
        } catch (\Exception $e) {
            DB::rollBack();
            $result['success'] = false;
            $result['message'] = "Archive failed: " . $e->getMessage();
            Log::error("Yearly archive failed: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Prepare archive data from transfer
     */
    private function prepareArchiveData(PropertyOwnershipTransfer $transfer, int $year): array
    {
        return [
            'original_transfer_id' => $transfer->id,
            'property_id' => $transfer->property_id,
            'current_landlord_id' => $transfer->current_landlord_id,
            'new_landlord_id' => $transfer->new_landlord_id,
            'requested_by_id' => $transfer->requested_by_id,
            'admin_approved_by_id' => $transfer->admin_approved_by_id,
            'completed_by_id' => $transfer->completed_by_id,
            'rejected_by_id' => $transfer->rejected_by_id,
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
            'rejected_at' => $transfer->rejected_at,
            'cancelled_at' => $transfer->cancelled_at,
            'completed_at' => $transfer->completed_at,
            'metadata' => array_merge($transfer->metadata ?? [], [
                'auto_archived' => true,
                'yearly_archive_year' => $year,
                'archived_at' => now()->toISOString()
            ]),
            'archive_year' => $year,
            'archived_at' => now(),
            'archived_by' => null,
            'archive_reason' => "yearly_auto_archive_{$year}",
            'reversal_status' => $transfer->reversal_status,
            'is_reversed' => $transfer->is_reversed,
            'reversal_transfer_id' => $transfer->reversal_transfer_id,
            'original_transfer_id' => $transfer->original_transfer_id,
        ];
    }
    
    /**
     * Archive all years older than specified
     */
    public function archiveYearsOlderThan(int $yearsToKeep = 1): array
    {
        $cutoffYear = now()->subYears($yearsToKeep)->year;
        $years = range(now()->year - 10, $cutoffYear);
        
        $results = [];
        
        foreach ($years as $year) {
            if ($year <= $cutoffYear) {
                $results[$year] = $this->archiveYear($year);
            }
        }
        
        return $results;
    }
}