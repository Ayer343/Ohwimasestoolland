<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddMetadataAndWorkflowToOwnershipTransfers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            // Add metadata column if it doesn't exist
            if (!Schema::hasColumn('property_ownership_transfers', 'metadata')) {
                $table->json('metadata')->nullable();
                echo "Added metadata column\n";
            }
            
            // Add approval_workflow_step column if it doesn't exist
            if (!Schema::hasColumn('property_ownership_transfers', 'approval_workflow_step')) {
                $table->string('approval_workflow_step')->default('approval');
                echo "Added approval_workflow_step column\n";
            }
            
            // Add digital_signature_verified column if it doesn't exist
            if (!Schema::hasColumn('property_ownership_transfers', 'digital_signature_verified')) {
                $table->boolean('digital_signature_verified')->default(false);
                echo "Added digital_signature_verified column\n";
            }
            
            // Add bulk_group_id column if it doesn't exist
            if (!Schema::hasColumn('property_ownership_transfers', 'bulk_group_id')) {
                $table->string('bulk_group_id')->nullable();
                echo "Added bulk_group_id column\n";
            }
            
            // Add can_resubmit_after column - check if rejected_at exists first
            if (!Schema::hasColumn('property_ownership_transfers', 'can_resubmit_after')) {
                // Check if rejected_at column exists before trying to add after it
                if (Schema::hasColumn('property_ownership_transfers', 'rejected_at')) {
                    $table->timestamp('can_resubmit_after')->nullable()->after('rejected_at');
                } else {
                    $table->timestamp('can_resubmit_after')->nullable();
                }
                echo "Added can_resubmit_after column\n";
            }
        });
        
        // Add indexes for better performance
        $this->addIndexesIfNotExist();
    }
    
    /**
     * Add indexes if they don't exist
     */
    private function addIndexesIfNotExist(): void
    {
        try {
            $prefix = DB::getTablePrefix();
            $tableName = 'property_ownership_transfers';
            
            // Check and add metadata index
            $indexExists = $this->indexExists($tableName, 'property_ownership_transfers_metadata_index');
            if (!$indexExists && Schema::hasColumn($tableName, 'metadata')) {
                DB::statement("ALTER TABLE `{$prefix}{$tableName}` ADD INDEX `property_ownership_transfers_metadata_index` (`metadata`(255))");
                echo "Added metadata index\n";
            }
            
            // Check and add approval_workflow_step index
            $indexExists = $this->indexExists($tableName, 'property_ownership_transfers_approval_workflow_step_index');
            if (!$indexExists && Schema::hasColumn($tableName, 'approval_workflow_step')) {
                DB::statement("ALTER TABLE `{$prefix}{$tableName}` ADD INDEX `property_ownership_transfers_approval_workflow_step_index` (`approval_workflow_step`)");
                echo "Added approval_workflow_step index\n";
            }
            
            // Check and add bulk_group_id index
            $indexExists = $this->indexExists($tableName, 'property_ownership_transfers_bulk_group_id_index');
            if (!$indexExists && Schema::hasColumn($tableName, 'bulk_group_id')) {
                DB::statement("ALTER TABLE `{$prefix}{$tableName}` ADD INDEX `property_ownership_transfers_bulk_group_id_index` (`bulk_group_id`)");
                echo "Added bulk_group_id index\n";
            }
            
        } catch (\Exception $e) {
            echo "Warning: Could not add indexes: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists($tableName, $indexName): bool
    {
        try {
            $prefix = DB::getTablePrefix();
            $result = DB::select("SHOW INDEX FROM `{$prefix}{$tableName}` WHERE Key_name = ?", [$indexName]);
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            $columns = ['metadata', 'approval_workflow_step', 'can_resubmit_after', 'digital_signature_verified', 'bulk_group_id'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('property_ownership_transfers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}