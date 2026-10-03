<?php
// app/Console/Commands/ArchiveRegistrations.php

namespace App\Console\Commands;

use App\Models\LandlordConstructionRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ArchiveRegistrations extends Command
{
    protected $signature = 'registrations:archive {--year= : Year to archive (default: previous year)} {--dry-run : Run without making changes}';
    protected $description = 'Archive registration records at the end of each year';

    public function handle()
    {
        $year = $this->option('year') ?? Carbon::now()->subYear()->year;
        $dryRun = $this->option('dry-run');
        
        $startDate = Carbon::create($year, 1, 1, 0, 0, 0);
        $endDate = Carbon::create($year, 12, 31, 23, 59, 59);
        
        $this->info("Archiving registrations for year: {$year}");
        $this->info("Date range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
        }
        
        // Get registrations to archive
        $registrations = LandlordConstructionRegistration::whereBetween('created_at', [$startDate, $endDate])
            ->where('is_archived', false)
            ->get();
        
        $count = $registrations->count();
        
        if ($count === 0) {
            $this->info("No registrations found to archive for year {$year}");
            return 0;
        }
        
        $this->info("Found {$count} registrations to archive");
        
        if (!$dryRun) {
            $bar = $this->output->createProgressBar($count);
            
            DB::beginTransaction();
            
            try {
                foreach ($registrations as $registration) {
                    $registration->update([
                        'is_archived' => true,
                        'archived_at' => now(),
                        'archive_year' => $year,
                        'archive_reason' => 'Yearly archival',
                    ]);
                    
                    $bar->advance();
                }
                
                DB::commit();
                $bar->finish();
                $this->newLine();
                
                $this->info("Successfully archived {$count} registrations for year {$year}");
                
                // Log the archival
                Log::info("Yearly registration archival completed", [
                    'year' => $year,
                    'count' => $count,
                    'executed_at' => now()->toISOString()
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to archive registrations: " . $e->getMessage());
                Log::error("Failed to archive registrations", [
                    'year' => $year,
                    'error' => $e->getMessage()
                ]);
                return 1;
            }
        }
        
        return 0;
    }
}