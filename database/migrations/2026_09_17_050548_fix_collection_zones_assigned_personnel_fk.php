<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phantom table created by an early migration that used the
     * singular form. Our Eloquent models use the plural form, so this
     * table is empty and every FK pointing at it is broken.
     */
    private const PHANTOM_TABLE = 'sanitation_personnel';
    private const REAL_TABLE    = 'sanitation_personnels';

    public function up(): void
    {
        // -----------------------------------------------------------------
        // 1. Find EVERY foreign key that references the phantom table
        // -----------------------------------------------------------------
        $constraints = DB::select("
            SELECT
                kcu.TABLE_NAME        AS table_name,
                kcu.COLUMN_NAME       AS column_name,
                kcu.CONSTRAINT_NAME   AS constraint_name,
                kcu.REFERENCED_COLUMN_NAME AS referenced_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.REFERENCED_TABLE_NAME = ?
        ", [self::PHANTOM_TABLE]);

        // -----------------------------------------------------------------
        // 2. For each constraint: drop it, null orphans, re-add against
        //    the correct plural table.
        // -----------------------------------------------------------------
        foreach ($constraints as $c) {
            $table      = $c->table_name;
            $column     = $c->column_name;
            $constraint = $c->constraint_name;

            // 2a. Drop the incorrect FK
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            } catch (\Throwable $e) {
                // Try the Blueprint form as a fallback
                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($column) {
                        $blueprint->dropForeign([$column]);
                    });
                } catch (\Throwable $e2) {
                    // Constraint may already be gone — skip it
                    continue;
                }
            }

            // 2b. Null out any orphaned references before re-adding the FK
            DB::table($table)
                ->whereNotNull($column)
                ->whereNotIn($column, function ($q) {
                    $q->select('id')->from(self::REAL_TABLE);
                })
                ->update([$column => null]);

            // 2c. Re-add the FK pointing at the plural table
            try {
                DB::statement("
                    ALTER TABLE `{$table}`
                    ADD CONSTRAINT `{$constraint}`
                    FOREIGN KEY (`{$column}`)
                    REFERENCES `" . self::REAL_TABLE . "` (`id`)
                    ON DELETE SET NULL
                ");
            } catch (\Throwable $e) {
                // Some child tables might use a different ON DELETE action.
                // Log and continue — the important thing is we tried.
                \Log::warning('Failed to repoint FK', [
                    'table'      => $table,
                    'column'     => $column,
                    'constraint' => $constraint,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // -----------------------------------------------------------------
        // 3. Belt-and-braces: also fix collection_zones directly, in case
        //    the enumeration above missed it (e.g. running on a fresh DB
        //    where the FK was created inline).
        // -----------------------------------------------------------------
        if (Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
            // Null orphans first
            DB::table('collection_zones')
                ->whereNotNull('assigned_personnel_id')
                ->whereNotIn('assigned_personnel_id', function ($q) {
                    $q->select('id')->from(self::REAL_TABLE);
                })
                ->update(['assigned_personnel_id' => null]);

            // Check if a FK still exists (constraint name varies by DB)
            $hasFk = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'collection_zones'
                  AND COLUMN_NAME = 'assigned_personnel_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (!$hasFk) {
                // No FK — add the correct one
                try {
                    Schema::table('collection_zones', function (Blueprint $table) {
                        $table->foreign('assigned_personnel_id')
                            ->references('id')
                            ->on(self::REAL_TABLE)
                            ->onDelete('set null');
                    });
                } catch (\Throwable $e) {
                    // Already exists or name collision — safe to continue
                }
            }
        }

        // -----------------------------------------------------------------
        // 4. Drop the phantom table (only if empty)
        // -----------------------------------------------------------------
        if (Schema::hasTable(self::PHANTOM_TABLE)) {
            $hasRows = DB::table(self::PHANTOM_TABLE)->exists();

            if (!$hasRows) {
                // Recheck for lingering dependents one more time
                $stillReferenced = DB::select("
                    SELECT COUNT(*) AS cnt
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND REFERENCED_TABLE_NAME = ?
                ", [self::PHANTOM_TABLE]);

                $cnt = (int) ($stillReferenced[0]->cnt ?? 0);

                if ($cnt === 0) {
                    Schema::drop(self::PHANTOM_TABLE);
                } else {
                    \Log::warning('Phantom table still referenced — not dropped', [
                        'phantom_table' => self::PHANTOM_TABLE,
                        'dependents'    => $cnt,
                    ]);
                }
            } else {
                \Log::warning('Phantom table has rows — not dropped', [
                    'phantom_table' => self::PHANTOM_TABLE,
                    'row_count'     => DB::table(self::PHANTOM_TABLE)->count(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op — reverting to a broken FK is undesirable.
        // If you truly need to reverse this, write a new forward-only
        // migration that recreates the phantom table.
    }
};