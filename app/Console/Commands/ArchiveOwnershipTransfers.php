<?php

namespace App\Console\Commands;

use App\Services\TransferArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveOwnershipTransfers extends Command
{
    protected $signature = 'transfers:archive 
                            {--year= : Year to archive (default: previous year)}
                            {--dry-run : Simulate archiving without making changes}
                            {--force : Archive even if already archived}';
    
    protected $description = 'Archive old ownership transfers to archive table';
    
    protected $archiveService;
    
    public function __construct(TransferArchiveService $archiveService)
    {
        parent::__construct();
        $this->archiveService = $archiveService;
    }
    
    public function handle()
    {
        $year = $this->option('year') ?? now()->subYear()->year;
        $isDryRun = $this->option('dry-run');
        
        $this->info("Starting archive process for year: {$year}");
        
        if ($isDryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
        }
        
        // Check if already archived
        if (!$this->option('force')) {
            $stats = $this->archiveService->getArchiveStatistics();
            $existingYears = collect($stats['by_year'])->pluck('archive_year')->toArray();
            
            if (in_array((string)$year, $existingYears)) {
                $this->error("Year {$year} has already been archived. Use --force to override.");
                return 1;
            }
        }
        
        if ($isDryRun) {
            // Just show what would be archived
            $this->info("DRY RUN: Would archive transfers from {$year}");
            $this->info("Use --force to actually perform the archive.");
            return 0;
        }
        
        // Perform the archive
        $result = $this->archiveService->archiveYear($year, [
            'soft_delete_after_archive' => true,
            'archive_completed' => true,
            'archive_rejected' => true,
            'archive_cancelled' => true,
            'archive_pending' => false,
            'archive_approved' => false,
            'archived_by' => 1, // System user
            'archive_reason' => 'scheduled_yearly_cleanup',
        ]);
        
        if ($result['success']) {
            $this->info($result['message']);
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Archived', $result['archived_count']],
                    ['Failed', $result['failed_count']],
                    ['Total Processed', $result['total_processed'] ?? $result['archived_count']],
                ]
            );
            
            if (!empty($result['failed_ids'])) {
                $this->warn("Failed IDs: " . implode(', ', array_slice($result['failed_ids'], 0, 10)));
            }
            
            return 0;
        } else {
            $this->error($result['message']);
            return 1;
        }
    }
}