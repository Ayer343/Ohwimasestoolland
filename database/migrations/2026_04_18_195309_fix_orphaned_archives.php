// database/migrations/2026_04_18_000001_fix_orphaned_archives.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PropertyOwnershipTransfer;
use App\Models\PropertyOwnershipTransferArchive;

class FixOrphanedArchives extends Migration
{
    public function up()
    {
        // Find all archives where the original transfer still exists and is not soft-deleted
        $archives = PropertyOwnershipTransferArchive::all();
        
        $fixed = 0;
        foreach ($archives as $archive) {
            $transfer = PropertyOwnershipTransfer::find($archive->original_transfer_id);
            
            if ($transfer && !$transfer->trashed()) {
                // Update metadata
                $metadata = $transfer->metadata ?? [];
                if (is_string($metadata)) {
                    $metadata = json_decode($metadata, true) ?? [];
                }
                
                $metadata['archived'] = [
                    'archived_at' => $archive->archived_at->toISOString(),
                    'archived_by' => $archive->archived_by,
                    'archive_id' => $archive->id,
                    'archive_year' => $archive->archive_year,
                    'archive_reason' => $archive->archive_reason,
                    'fixed_by_migration' => true
                ];
                
                $transfer->metadata = json_encode($metadata);
                $transfer->saveQuietly();
                
                // Soft delete the transfer
                $transfer->delete();
                $fixed++;
            }
        }
        
        echo "Fixed {$fixed} orphaned archives (transfers that were archived but not soft-deleted)\n";
    }

    public function down()
    {
        // This migration cannot be reversed easily
    }
}