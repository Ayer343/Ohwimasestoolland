<?php

namespace App\Console\Commands;

use App\Models\PropertyOwnershipTransfer;
use App\Models\PropertyOwnershipTransferArchive;
use App\Models\User;
use App\Services\TransferArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class YearlyTransferArchiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfers:yearly-archive
                            {--year= : The year to archive (defaults to previous year)}
                            {--statuses=completed,rejected,cancelled : Comma-separated statuses to archive}
                            {--include-reversals : Include reversal records in archiving}
                            {--dry-run : Simulate the archiving without actually moving data}
                            {--force : Force archive even if already archived}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive old transfers (regular and reversal records) at the end of each year';

    /**
     * The transfer archive service instance.
     *
     * @var \App\Services\TransferArchiveService
     */
    protected $archiveService;

    /**
     * Create a new command instance.
     */
    public function __construct(TransferArchiveService $archiveService)
    {
        parent::__construct();
        $this->archiveService = $archiveService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->option('year') ?? now()->subYear()->year;
        $statuses = explode(',', $this->option('statuses'));
        $includeReversals = $this->option('include-reversals');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Starting yearly archive process for year: {$year}");
        $this->info("Statuses to archive: " . implode(', ', $statuses));
        $this->info("Include reversals: " . ($includeReversals ? 'Yes' : 'No'));
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE: No actual changes will be made.");
        }

        // Build query for regular transfers
        $query = PropertyOwnershipTransfer::query()
            ->whereYear('created_at', '<=', $year)
            ->whereIn('status', $statuses)
            ->where(function($q) {
                $q->whereNull('deleted_at'); // Not already soft-deleted
            });

        // Exclude reversal records if not explicitly included
        if (!$includeReversals) {
            $query->where(function($q) {
                $q->whereNull('metadata->is_reversal')
                  ->orWhere('metadata->is_reversal', false);
            });
        }

        $transfersToArchive = $query->get();
        
        $regularTransfersCount = $transfersToArchive->filter(function($t) {
            return !$t->isReversalRecord();
        })->count();
        
        $reversalRecordsCount = $transfersToArchive->filter(function($t) {
            return $t->isReversalRecord();
        })->count();

        $this->info("Found {$regularTransfersCount} regular transfers and {$reversalRecordsCount} reversal records to archive.");

        if ($transfersToArchive->isEmpty()) {
            $this->info("No transfers found to archive for year {$year}.");
            return 0;
        }

        if ($dryRun) {
            $this->table(
                ['ID', 'Document Reference', 'Property', 'Status', 'Created At', 'Is Reversal'],
                $transfersToArchive->map(function($transfer) {
                    return [
                        $transfer->id,
                        $transfer->document_reference,
                        $transfer->property->property_name ?? 'N/A',
                        $transfer->status_label,
                        $transfer->created_at->format('Y-m-d'),
                        $transfer->isReversalRecord() ? 'Yes' : 'No'
                    ];
                })->toArray()
            );
            $this->info("DRY RUN completed. No actual changes were made.");
            return 0;
        }

        DB::beginTransaction();

        try {
            $archivedCount = 0;
            $failedCount = 0;
            $failedIds = [];

            foreach ($transfersToArchive as $transfer) {
                try {
                    // Check if already archived
                    $existingArchive = PropertyOwnershipTransferArchive::where('original_transfer_id', $transfer->id)
                        ->orWhere('document_reference', $transfer->document_reference)
                        ->first();

                    if ($existingArchive && !$force) {
                        $this->warn("Transfer #{$transfer->id} already archived. Skipping (use --force to override).");
                        continue;
                    }

                    // Create archive record
                    $archiveData = $this->prepareArchiveData($transfer, $year);
                    
                    if ($existingArchive && $force) {
                        $existingArchive->update($archiveData);
                        $archive = $existingArchive;
                    } else {
                        $archive = PropertyOwnershipTransferArchive::create($archiveData);
                    }

                    // Soft delete the original transfer
                    $transfer->delete();

                    // Log the archiving
                    \App\Models\ActivityLog::create([
                        'user_id' => null,
                        'action' => 'auto_yearly_archive',
                        'description' => "Auto-archived transfer #{$transfer->id} ({$transfer->document_reference}) for year {$year}",
                        'ip_address' => 'system',
                        'user_agent' => 'Yearly Archive Job',
                        'metadata' => [
                            'transfer_id' => $transfer->id,
                            'archive_id' => $archive->id,
                            'archive_year' => $year,
                            'is_reversal' => $transfer->isReversalRecord(),
                            'original_status' => $transfer->status
                        ]
                    ]);

                    $archivedCount++;
                    $this->info("Archived transfer #{$transfer->id} - {$transfer->document_reference}");

                } catch (\Exception $e) {
                    $failedCount++;
                    $failedIds[] = $transfer->id;
                    $this->error("Failed to archive transfer #{$transfer->id}: " . $e->getMessage());
                    Log::error("Yearly archive failed for transfer #{$transfer->id}: " . $e->getMessage());
                }
            }

            DB::commit();

            $this->info("Yearly archive completed for year {$year}!");
            $this->info("Successfully archived: {$archivedCount} records");
            $this->info("Failed: {$failedCount} records");
            
            if (!empty($failedIds)) {
                $this->warn("Failed IDs: " . implode(', ', $failedIds));
            }

            return $archivedCount;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Yearly archive failed: " . $e->getMessage());
            Log::error("Yearly archive failed: " . $e->getMessage());
            return 0;
        }
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
                'archived_from_yearly_job' => true,
                'original_deleted_at' => now()->toISOString(),
                'yearly_archive_year' => $year
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
}