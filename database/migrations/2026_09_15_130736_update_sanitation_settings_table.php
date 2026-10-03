<?php
// database/migrations/2026_09_15_130736_update_sanitation_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanitation_settings', function (Blueprint $table) {

            // ✅ Add operational_status only if it doesn't already exist
            if (!Schema::hasColumn('sanitation_settings', 'operational_status')) {
                $table->string('operational_status', 20)->nullable()->after('is_active');
            }

            // ✅ Add missing indexes — idempotent via Schema::hasIndex
            //    (Laravel 10.x+; no Doctrine required)
            if (!Schema::hasIndex('sanitation_settings', ['default_report_timezone'])) {
                $table->index('default_report_timezone');
            }
            if (!Schema::hasIndex('sanitation_settings', ['created_by'])) {
                $table->index('created_by');
            }
            if (!Schema::hasIndex('sanitation_settings', ['updated_by'])) {
                $table->index('updated_by');
            }
            if (!Schema::hasIndex('sanitation_settings', ['is_active', 'created_at'])) {
                $table->index(['is_active', 'created_at']);
            }
        });

        // ✅ Postgres-only CHECK constraints
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sanitation_settings
                ADD CONSTRAINT chk_sanitation_late_fee
                CHECK (late_fee_percentage IS NULL OR (late_fee_percentage >= 0 AND late_fee_percentage <= 100))");

            DB::statement("ALTER TABLE sanitation_settings
                ADD CONSTRAINT chk_sanitation_workers_per_vehicle
                CHECK (default_worker_count_per_vehicle BETWEEN 1 AND 10)");
        }
    }

    public function down(): void
    {
        Schema::table('sanitation_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sanitation_settings', 'operational_status')) {
                $table->dropColumn('operational_status');
            }

            // Drop indexes if they exist
            if (Schema::hasIndex('sanitation_settings', ['default_report_timezone'])) {
                $table->dropIndex(['default_report_timezone']);
            }
            if (Schema::hasIndex('sanitation_settings', ['created_by'])) {
                $table->dropIndex(['created_by']);
            }
            if (Schema::hasIndex('sanitation_settings', ['updated_by'])) {
                $table->dropIndex(['updated_by']);
            }
            if (Schema::hasIndex('sanitation_settings', ['is_active', 'created_at'])) {
                $table->dropIndex(['is_active', 'created_at']);
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sanitation_settings DROP CONSTRAINT IF EXISTS chk_sanitation_late_fee");
            DB::statement("ALTER TABLE sanitation_settings DROP CONSTRAINT IF EXISTS chk_sanitation_workers_per_vehicle");
        }
    }
};