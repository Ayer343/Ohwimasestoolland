<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registration_plans', function (Blueprint $table) {
            // Prevent two plans with the same (zone, section, pattern) from existing.
            // NULL section is treated as '' by MySQL; on SQLite/Postgres, use a
            // generated column if you need strict NULL handling.
            $table->unique(
                ['zone', 'section', 'naming_pattern'],
                'registration_plans_zone_section_pattern_unique'
            );

            // Optional: prevent exact duplicates within a zone regardless of
            // pattern (starting_point is what actually conflicts operationally).
            $table->unique(
                ['zone', 'section', 'starting_point'],
                'registration_plans_zone_section_start_unique'
            );

            // Idempotency token — see store() below.
            $table->string('submission_token', 64)->nullable()->unique();
        });

        Schema::table('plan_agent_assignments', function (Blueprint $table) {
            $table->unique(
                ['plan_id', 'agent_id'],
                'plan_agent_assignments_plan_agent_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('plan_agent_assignments', function (Blueprint $table) {
            $table->dropUnique('plan_agent_assignments_plan_agent_unique');
        });
        Schema::table('registration_plans', function (Blueprint $table) {
            $table->dropUnique('registration_plans_zone_section_start_unique');
            $table->dropUnique('registration_plans_zone_section_pattern_unique');
            $table->dropUnique(['submission_token']);
            $table->dropColumn('submission_token');
        });
    }
};