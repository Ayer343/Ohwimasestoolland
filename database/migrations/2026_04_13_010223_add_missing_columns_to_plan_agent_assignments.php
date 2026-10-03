<?php
// database/migrations/2026_04_13_000002_add_missing_columns_to_plan_agent_assignments.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('plan_agent_assignments', function (Blueprint $table) {
            // Add assigned_properties_count column if it doesn't exist
            if (!Schema::hasColumn('plan_agent_assignments', 'assigned_properties_count')) {
                $table->integer('assigned_properties_count')->default(0)->after('agent_id');
            }
        });
    }

    public function down()
    {
        Schema::table('plan_agent_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('plan_agent_assignments', 'assigned_properties_count')) {
                $table->dropColumn('assigned_properties_count');
            }
        });
    }
};