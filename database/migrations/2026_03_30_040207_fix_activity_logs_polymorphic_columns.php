<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixActivityLogsPolymorphicColumns extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Check if columns exist before adding
            if (!Schema::hasColumn('activity_logs', 'loggable_id')) {
                $table->nullableMorphs('loggable');
            }
            
            // Add missing type column if it doesn't exist
            if (!Schema::hasColumn('activity_logs', 'type')) {
                $table->string('type')->default('general')->after('action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'loggable_id')) {
                $table->dropMorphs('loggable');
            }
            
            if (Schema::hasColumn('activity_logs', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
}