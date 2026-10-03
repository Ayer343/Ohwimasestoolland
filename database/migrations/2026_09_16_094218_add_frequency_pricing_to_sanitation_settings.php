<?php
// database/migrations/2026_09_16_000001_add_frequency_pricing_to_sanitation_settings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a JSON column that stores a per-frequency pricing matrix.
     *
     * Shape:
     * {
     *   "daily":    { "per_visit": 30, "per_month": 650, "emergency": 90 },
     *   "weekly":   { "per_visit": 50, "per_month": 200, "emergency": 90 },
     *   "biweekly": { "per_visit": 55, "per_month": 110, "emergency": 90 },
     *   "monthly":  { "per_visit": 60, "per_month":  65, "emergency": 90 }
     * }
     *
     * The landlord-side "Request Sanitation Service" modal reads this
     * matrix. When a key is missing, the controller falls back to
     * `default_collection_fee` / `emergency_collection_fee`, and
     * finally to a hard-coded fallback matrix.
     */
    public function up(): void
    {
        Schema::table('sanitation_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sanitation_settings', 'frequency_pricing')) {
                $table->json('frequency_pricing')
                    ->nullable()
                    ->after('default_collection_days');
            }
        });

        // Column comments — MySQL / MariaDB
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sanitation_settings
                MODIFY frequency_pricing JSON NULL
                COMMENT 'Per-frequency pricing matrix: {\"weekly\": {\"per_visit\": 50, \"per_month\": 200, \"emergency\": 90}, ...}'");
        }

        // Column comments — PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN sanitation_settings.frequency_pricing IS
                'Per-frequency pricing matrix: {\"weekly\": {\"per_visit\": 50, \"per_month\": 200, \"emergency\": 90}, ...}'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanitation_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sanitation_settings', 'frequency_pricing')) {
                $table->dropColumn('frequency_pricing');
            }
        });
    }
};