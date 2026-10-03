<?php
// database/migrations/2026_10_03_000004_fix_sanitation_workers_supervisor_fk.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'sanitation_workers'
              AND COLUMN_NAME = 'supervisor_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `sanitation_workers` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        DB::statement("
            DELETE w FROM sanitation_workers w
            LEFT JOIN sanitation_personnels p ON w.supervisor_id = p.id
            WHERE w.supervisor_id IS NOT NULL AND p.id IS NULL
        ");

        DB::statement("
            ALTER TABLE `sanitation_workers`
            MODIFY `supervisor_id` BIGINT UNSIGNED NULL
        ");

        DB::statement("
            ALTER TABLE `sanitation_workers`
            ADD CONSTRAINT `sw_supervisor_fk`
            FOREIGN KEY (`supervisor_id`)
            REFERENCES `sanitation_personnels` (`id`)
            ON DELETE SET NULL
            ON UPDATE CASCADE
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `sanitation_workers` DROP FOREIGN KEY `sw_supervisor_fk`");
        DB::statement("ALTER TABLE `sanitation_workers` MODIFY `supervisor_id` BIGINT UNSIGNED NULL");
    }
};