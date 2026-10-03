<?php

// database/migrations/2026_04_18_114601_create_property_ownership_transfer_archives_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyOwnershipTransferArchivesTable extends Migration
{
    public function up()
    {
        // Check if table already exists
        if (Schema::hasTable('property_ownership_transfer_archives')) {
            return;
        }
        
        Schema::create('property_ownership_transfer_archives', function (Blueprint $table) {
            $table->id();
            
            // Original transfer ID reference
            $table->unsignedBigInteger('original_transfer_id')->nullable();
            
            // All original columns from property_ownership_transfers
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('current_landlord_id');
            $table->unsignedBigInteger('new_landlord_id');
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->unsignedBigInteger('admin_approved_by_id')->nullable();
            $table->unsignedBigInteger('completed_by_id')->nullable();
            $table->unsignedBigInteger('rejected_by_id')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            
            $table->string('status', 50);
            $table->date('transfer_date');
            $table->decimal('sale_amount', 15, 2)->nullable();
            $table->string('document_type', 50);
            $table->string('document_reference', 100);
            $table->string('document_url')->nullable();
            $table->string('certificate_url')->nullable();
            
            $table->string('new_owner_name', 255);
            $table->string('new_owner_phone', 20)->nullable();
            $table->string('new_owner_email', 255)->nullable();
            $table->text('new_owner_address')->nullable();
            
            $table->text('reason_for_transfer')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('can_resubmit_after')->nullable();
            
            // Archive specific columns - Use datetime to avoid strict mode issues
            $table->datetime('archived_at');
            $table->string('archive_year', 4);
            $table->string('archive_month', 2)->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->string('archive_reason')->default('yearly_cleanup');
            
            // Metadata JSON columns
            $table->json('metadata')->nullable();
            $table->json('original_metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index('archive_year');
            $table->index('archive_month');
            $table->index('archived_at');
            $table->index('status');
            $table->index('property_id');
            $table->index('current_landlord_id');
            $table->index('new_landlord_id');
            $table->index('transfer_date');
            $table->index(['archive_year', 'status']);
            $table->index('original_transfer_id');
        });
        
        // Add index for faster archiving queries on main table
        // Use a try-catch to handle if indexes already exist
        if (Schema::hasTable('property_ownership_transfers')) {
            try {
                Schema::table('property_ownership_transfers', function (Blueprint $table) {
                    $table->index(['status', 'completed_at', 'rejected_at', 'cancelled_at'], 'idx_transfers_status_dates');
                    $table->index('created_at', 'idx_transfers_created_at');
                });
            } catch (\Exception $e) {
                // Indexes might already exist, continue silently
            }
        }
    }

    public function down()
    {
        // Drop indexes on main table if they exist
        if (Schema::hasTable('property_ownership_transfers')) {
            try {
                Schema::table('property_ownership_transfers', function (Blueprint $table) {
                    $table->dropIndex('idx_transfers_status_dates');
                    $table->dropIndex('idx_transfers_created_at');
                });
            } catch (\Exception $e) {
                // Indexes might not exist, continue silently
            }
        }
        
        Schema::dropIfExists('property_ownership_transfer_archives');
    }
}