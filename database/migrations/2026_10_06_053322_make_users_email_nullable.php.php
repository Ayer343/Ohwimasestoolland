<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes the `users.email` column nullable so that users can be created
     * with phone as the sole identifier. Phone is now the required field.
     *
     * Notes:
     *   - The existing UNIQUE index on `email` is preserved. MySQL,
     *     PostgreSQL, and SQLite all allow multiple NULLs in a UNIQUE
     *     column, so phone-only users will not collide.
     *   - No data is modified by this migration.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Log::warning('make_users_email_nullable: users table does not exist — skipping.');
            return;
        }

        if (!Schema::hasColumn('users', 'email')) {
            Log::warning('make_users_email_nullable: users.email column does not exist — skipping.');
            return;
        }

        // Guard: if the column is already nullable, don't touch it.
        // This makes the migration safe to re-run in environments where
        // the change was applied manually.
        if ($this->columnIsNullable('users', 'email')) {
            Log::info('make_users_email_nullable: users.email is already nullable — skipping.');
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // `change()` requires doctrine/dbal on older Laravel versions.
            // Laravel 11+ supports it natively without doctrine/dbal.
            $table->string('email')->nullable()->change();
        });

        Log::info('make_users_email_nullable: users.email is now nullable.');
    }

    /**
     * Reverse the migrations.
     *
     * ⚠️ WARNING: Rolling this migration back will FAIL if any user
     * currently has a NULL email. Before reverting, ensure all rows have
     * a non-null email value (e.g. backfill placeholder values).
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'email')) {
            return;
        }

        // Safety: refuse to revert if NULL emails exist, since the NOT NULL
        // constraint would be violated and MySQL would silently coerce to ''.
        $nullCount = DB::table('users')->whereNull('email')->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot revert make_users_email_nullable: {$nullCount} user(s) have a NULL email. " .
                "Backfill or delete these records before rolling back."
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        Log::info('make_users_email_nullable: reverted — users.email is NOT NULL again.');
    }

    /**
     * Detect whether a column is currently nullable, across MySQL,
     * PostgreSQL, and SQLite. Falls back to a best-effort check.
     */
    private function columnIsNullable(string $table, string $column): bool
    {
        try {
            $connection = DB::connection();
            $driver     = $connection->getDriverName();

            switch ($driver) {
                case 'mysql':
                case 'mariadb':
                    $result = $connection->selectOne(
                        "SELECT IS_NULLABLE
                           FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = ?
                            AND COLUMN_NAME = ?",
                        [$table, $column]
                    );
                    return $result && strtoupper($result->IS_NULLABLE) === 'YES';

                case 'pgsql':
                    $result = $connection->selectOne(
                        "SELECT is_nullable
                           FROM information_schema.columns
                          WHERE table_schema = 'public'
                            AND table_name = ?
                            AND column_name = ?",
                        [$table, $column]
                    );
                    return $result && strtolower($result->is_nullable) === 'yes';

                case 'sqlite':
                    $columns = $connection->select("PRAGMA table_info({$table})");
                    foreach ($columns as $col) {
                        if (($col->name ?? null) === $column) {
                            // In SQLite, notnull = 0 means nullable.
                            return (int) ($col->notnull ?? 1) === 0;
                        }
                    }
                    return false;

                default:
                    // Unknown driver — assume we need to run the change.
                    return false;
            }
        } catch (\Throwable $e) {
            Log::warning('make_users_email_nullable: nullability check failed, proceeding.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
};