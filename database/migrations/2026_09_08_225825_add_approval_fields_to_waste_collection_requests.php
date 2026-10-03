<?php
// database/migrations/2026_09_08_225825_add_approval_fields_to_waste_collection_requests.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // ✅ Check if columns already exist before adding them
        Schema::table('waste_collection_requests', function (Blueprint $table) {
            // Check if approval_status column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_status')) {
                $table->enum('approval_status', [
                    'pending',
                    'approved',
                    'rejected',
                    'expired',
                    'auto_approved'
                ])->default('pending')->after('status');
            }
            
            // Check if approval_requested_at column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_requested_at')) {
                $table->timestamp('approval_requested_at')->nullable()->after('approval_status');
            }
            
            // Check if approval_responded_at column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_responded_at')) {
                $table->timestamp('approval_responded_at')->nullable()->after('approval_requested_at');
            }
            
            // Check if approved_by column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approval_responded_at');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            }
            
            // Check if approval_notes column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_notes')) {
                $table->text('approval_notes')->nullable()->after('approved_by');
            }
            
            // Check if rejection_reason column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_notes');
            }
            
            // Check if approval_expires_at column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_expires_at')) {
                $table->timestamp('approval_expires_at')->nullable()->after('rejection_reason');
            }
            
            // Check if approval_notification_sent column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_notification_sent')) {
                $table->boolean('approval_notification_sent')->default(false)->after('approval_expires_at');
            }
            
            // Check if approval_reminder_sent_at column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_reminder_sent_at')) {
                $table->timestamp('approval_reminder_sent_at')->nullable()->after('approval_notification_sent');
            }
            
            // Check if approval_reminder_count column exists before adding
            if (!Schema::hasColumn('waste_collection_requests', 'approval_reminder_count')) {
                $table->integer('approval_reminder_count')->default(0)->after('approval_reminder_sent_at');
            }
        });
        
        // ✅ Add indexes only if they don't exist
        try {
            // Check if indexes exist before creating
            $indexes = DB::select('SHOW INDEX FROM waste_collection_requests WHERE Key_name = ?', ['wcr_approval_status_expires_idx']);
            if (empty($indexes)) {
                DB::statement('ALTER TABLE `waste_collection_requests` ADD INDEX `wcr_approval_status_expires_idx` (`approval_status`, `approval_expires_at`)');
            }
            
            $indexes2 = DB::select('SHOW INDEX FROM waste_collection_requests WHERE Key_name = ?', ['wcr_property_approval_idx']);
            if (empty($indexes2)) {
                DB::statement('ALTER TABLE `waste_collection_requests` ADD INDEX `wcr_property_approval_idx` (`property_id`, `approval_status`)');
            }
        } catch (\Exception $e) {
            // Silently handle index creation errors
            // Some indexes might already exist with different names
            \Log::warning('Could not create indexes: ' . $e->getMessage());
        }
    }

    public function down()
    {
        Schema::table('waste_collection_requests', function (Blueprint $table) {
            // Drop indexes if they exist
            try {
                DB::statement('ALTER TABLE `waste_collection_requests` DROP INDEX `wcr_approval_status_expires_idx`');
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            try {
                DB::statement('ALTER TABLE `waste_collection_requests` DROP INDEX `wcr_property_approval_idx`');
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            // Drop foreign key if exists
            try {
                $table->dropForeign(['approved_by']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            
            // Drop columns if they exist
            $columnsToDrop = [
                'approval_status',
                'approval_requested_at',
                'approval_responded_at',
                'approved_by',
                'approval_notes',
                'rejection_reason',
                'approval_expires_at',
                'approval_notification_sent',
                'approval_reminder_sent_at',
                'approval_reminder_count'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('waste_collection_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};