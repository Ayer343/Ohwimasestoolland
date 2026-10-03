<?php
// database/migrations/2026_09_09_000000_add_is_active_to_collection_zones.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('collection_zones', function (Blueprint $table) {
            // Check if is_active doesn't exist
            if (!Schema::hasColumn('collection_zones', 'is_active')) {
                // Add is_active column
                $table->boolean('is_active')->default(true)->after('description');
            }
        });

        // ✅ FIXED: Update the data AFTER the column is added
        if (Schema::hasColumn('collection_zones', 'status') && Schema::hasColumn('collection_zones', 'is_active')) {
            DB::statement("UPDATE collection_zones SET is_active = CASE WHEN status = 'active' THEN 1 ELSE 0 END");
        }

        // ✅ Now drop the status column after data is migrated
        Schema::table('collection_zones', function (Blueprint $table) {
            if (Schema::hasColumn('collection_zones', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    public function down()
    {
        // ✅ First add status column back
        Schema::table('collection_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('collection_zones', 'status')) {
                $table->string('status')->default('active')->after('description');
            }
        });

        // ✅ Copy data from is_active to status
        if (Schema::hasColumn('collection_zones', 'is_active') && Schema::hasColumn('collection_zones', 'status')) {
            DB::statement("UPDATE collection_zones SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END");
        }

        // ✅ Drop is_active
        Schema::table('collection_zones', function (Blueprint $table) {
            if (Schema::hasColumn('collection_zones', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};