<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddArchivalFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Check if status column exists before adding (skip if exists)
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('type');
            }
            
            // Check if archived_at column exists
            if (!Schema::hasColumn('users', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('status');
            }
            
            // Check if last_property_ownership column exists
            if (!Schema::hasColumn('users', 'last_property_ownership')) {
                $table->timestamp('last_property_ownership')->nullable()->after('archived_at');
            }
            
            // Check if deletion_scheduled_at column exists
            if (!Schema::hasColumn('users', 'deletion_scheduled_at')) {
                $table->timestamp('deletion_scheduled_at')->nullable()->after('last_property_ownership');
            }
            
            // Check if can_login column exists
            if (!Schema::hasColumn('users', 'can_login')) {
                $table->boolean('can_login')->default(true)->after('deletion_scheduled_at');
            }
            
            // Check if api_access column exists
            if (!Schema::hasColumn('users', 'api_access')) {
                $table->boolean('api_access')->default(true)->after('can_login');
            }
            
            // Check if metadata column exists (JSON type)
            if (!Schema::hasColumn('users', 'metadata')) {
                $table->json('metadata')->nullable()->after('api_access');
            }
        });

        // Add indexes separately (only if they don't exist)
        $this->addIndexesIfNotExists();
    }

    /**
     * Add indexes only if they don't already exist
     */
    private function addIndexesIfNotExists(): void
    {
        $table = 'users';
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        $existingIndexes = array_column($indexes, 'Key_name');
        
        // Add status_archived_at index
        if (!in_array('users_status_archived_at_index', $existingIndexes)) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['status', 'archived_at'], 'users_status_archived_at_index');
            });
        }
        
        // Add last_property_ownership index
        if (!in_array('users_last_property_ownership_index', $existingIndexes)) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('last_property_ownership', 'users_last_property_ownership_index');
            });
        }
        
        // Add deletion_scheduled_at index
        if (!in_array('users_deletion_scheduled_at_index', $existingIndexes)) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('deletion_scheduled_at', 'users_deletion_scheduled_at_index');
            });
        }
        
        // Add composite index for archival queries
        if (!in_array('users_archival_composite_index', $existingIndexes)) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['type', 'status', 'deletion_scheduled_at'], 'users_archival_composite_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndexIfExists('users_status_archived_at_index');
            $table->dropIndexIfExists('users_last_property_ownership_index');
            $table->dropIndexIfExists('users_deletion_scheduled_at_index');
            $table->dropIndexIfExists('users_archival_composite_index');
            
            // Drop columns (but don't drop status as it might be used elsewhere)
            $table->dropColumnIfExists('archived_at');
            $table->dropColumnIfExists('last_property_ownership');
            $table->dropColumnIfExists('deletion_scheduled_at');
            $table->dropColumnIfExists('can_login');
            $table->dropColumnIfExists('api_access');
            $table->dropColumnIfExists('metadata');
            
            // Note: 'status' column is NOT dropped as it existed before this migration
        });
    }
}