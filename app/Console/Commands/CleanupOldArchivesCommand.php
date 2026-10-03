<?php

namespace App\Console\Commands;

use App\Models\PropertyOwnershipTransferArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOldArchivesCommand extends Command
{
    protected $signature = 'transfers:cleanup-old-archives
                            {--years=7 : Years to keep (older than this will be deleted)}
                            {--dry-run : Simulate the cleanup without actually deleting}';
    
    protected $description = 'Permanently delete archive records older than specified years';
    
    public function handle()
    {
        $yearsToKeep = $this->option('years');
        $dryRun = $this->option('dry-run');
        $cutoffYear = now()->subYears($yearsToKeep)->year;
        
        $this->info("Deleting archives older than {$cutoffYear} (keeping last {$yearsToKeep} years)");
        
        $archives = PropertyOwnershipTransferArchive::where('archive_year', '<', $cutoffYear)->get();
        
        if ($archives->isEmpty()) {
            $this->info("No archives found older than {$cutoffYear}.");
            return 0;
        }
        
        $regularCount = $archives->filter(function($a) { 
            return !$a->isReversalRecord(); 
        })->count();
        
        $reversalCount = $archives->filter(function($a) { 
            return $a->isReversalRecord(); 
        })->count();
        
        $this->info("Found {$archives->count()} archives ({$regularCount} regular, {$reversalCount} reversal) older than {$cutoffYear}");
        
        if ($dryRun) {
            $this->warn("DRY RUN: No archives will be deleted.");
            return 0;
        }
        
        DB::beginTransaction();
        
        try {
            $deletedCount = 0;
            
            foreach ($archives as $archive) {
                // Delete associated files
                if ($archive->document_url && Storage::disk('public')->exists($archive->document_url)) {
                    Storage::disk('public')->delete($archive->document_url);
                }
                if ($archive->certificate_url && Storage::disk('public')->exists($archive->certificate_url)) {
                    Storage::disk('public')->delete($archive->certificate_url);
                }
                
                $archive->delete();
                $deletedCount++;
            }
            
            DB::commit();
            
            $this->info("Successfully deleted {$deletedCount} old archives.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Cleanup failed: " . $e->getMessage());
            Log::error("Old archive cleanup failed: " . $e->getMessage());
            return 0;
        }
        
        return $deletedCount;
    }
}