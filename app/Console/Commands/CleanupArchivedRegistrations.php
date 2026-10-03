<?php

namespace App\Console\Commands;

use App\Models\ArchivedConstructionRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupArchivedRegistrations extends Command
{
    protected $signature = 'registrations:cleanup-archive 
                            {--years=7 : Delete archives older than X years}
                            {--dry-run : Preview what will be deleted}
                            {--year= : Delete archives from specific year}';

    protected $description = 'Permanently delete old archived registrations';

    public function handle()
    {
        $yearsOld = $this->option('years');
        $dryRun = $this->option('dry-run');
        $specificYear = $this->option('year');

        if ($specificYear) {
            $query = ArchivedConstructionRegistration::where('archive_year', $specificYear);
            $this->info("Looking for archives from year: {$specificYear}");
        } else {
            $cutoffYear = now()->subYears($yearsOld)->year;
            $query = ArchivedConstructionRegistration::where('archive_year', '<', $cutoffYear);
            $this->info("Looking for archives older than {$yearsOld} years (before {$cutoffYear})");
        }

        $archives = $query->get();

        if ($archives->isEmpty()) {
            $this->info("No archives found matching criteria");
            return 0;
        }

        $this->info("Found {$archives->count()} archived records to delete");

        if ($dryRun) {
            $this->table(
                ['ID', 'Original ID', 'Name', 'Property', 'Archive Year', 'Archived At'],
                $archives->map(fn($a) => [
                    $a->id,
                    $a->original_id,
                    $a->name,
                    $a->property_name ?? 'N/A',
                    $a->archive_year,
                    $a->archived_at->format('Y-m-d')
                ])
            );
            $this->info("Dry run completed. No data was deleted.");
            return 0;
        }

        DB::beginTransaction();

        try {
            $deleted = 0;
            foreach ($archives as $archive) {
                $archive->delete();
                $deleted++;
            }

            DB::commit();

            $this->info("Successfully deleted {$deleted} archived records permanently");
            
            Log::info("Archive cleanup completed", [
                'deleted' => $deleted,
                'years_old' => $yearsOld,
                'specific_year' => $specificYear
            ]);

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Cleanup failed: " . $e->getMessage());
            Log::error("Archive cleanup failed", ['error' => $e->getMessage()]);
            return 1;
        }
    }
}