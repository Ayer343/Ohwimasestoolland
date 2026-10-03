<?php
// database/migrations/2026_10_03_000003_fix_sanitation_schedules_personnel_fk.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // 1. Drop any existing FK on personnel_id (by whatever name)
        // -----------------------------------------------------------------
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'sanitation_schedules'
              AND COLUMN_NAME = 'personnel_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `sanitation_schedules` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        // -----------------------------------------------------------------
        // 2. Remove orphaned rows so the FK can actually be created
        // -----------------------------------------------------------------
        DB::statement("
            DELETE s FROM sanitation_schedules s
            LEFT JOIN sanitation_personnels p ON s.personnel_id = p.id
            WHERE s.personnel_id IS NOT NULL AND p.id IS NULL
        ");

        // -----------------------------------------------------------------
        // 3. Align the column type with sanitation_personnels.id
        //    (must be BIGINT UNSIGNED NULL)
        // -----------------------------------------------------------------
        DB::statement("
            ALTER TABLE `sanitation_schedules`
            MODIFY `personnel_id` BIGINT UNSIGNED NULL
        ");

        // -----------------------------------------------------------------
        // 4. Recreate the FK (uses a short explicit name)
        // -----------------------------------------------------------------
        DB::statement("
            ALTER TABLE `sanitation_schedules`
            ADD CONSTRAINT `ss_personnel_fk`
            FOREIGN KEY (`personnel_id`)
            REFERENCES `sanitation_personnels` (`id`)
            ON DELETE SET NULL
            ON UPDATE CASCADE
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `sanitation_schedules` DROP FOREIGN KEY `ss_personnel_fk`");
        DB::statement("ALTER TABLE `sanitation_schedules` MODIFY `personnel_id` BIGINT UNSIGNED NULL");
    }
};