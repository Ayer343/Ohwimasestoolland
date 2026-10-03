<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('device_tokens', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('device_tokens', 'session_id')) {
                $table->string('session_id')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('device_tokens', 'platform')) {
                $table->string('platform')->nullable()->after('session_id');
            }
            if (!Schema::hasColumn('device_tokens', 'token')) {
                $table->string('token')->nullable()->after('platform');
            }
            if (!Schema::hasColumn('device_tokens', 'metadata')) {
                $table->json('metadata')->nullable()->after('token');
            }
            if (!Schema::hasColumn('device_tokens', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('device_tokens', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('last_used_at');
            }
            if (!Schema::hasColumn('device_tokens', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // Add indexes using raw SQL to avoid Doctrine issues
        $this->addIndexesIfNotExist();
    }

    /**
     * Add indexes using raw SQL
     */
    protected function addIndexesIfNotExist(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $table = 'device_tokens';

        if ($driver === 'mysql') {
            // Check if indexes exist using information_schema
            $indexes = DB::select("SHOW INDEX FROM `{$table}`");
            $existingIndexes = array_column($indexes, 'Key_name');

            if (!in_array('idx_device_tokens_session', $existingIndexes)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `idx_device_tokens_session` (`session_id`)");
            }
            if (!in_array('idx_device_tokens_platform', $existingIndexes)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `idx_device_tokens_platform` (`platform`)");
            }
            if (!in_array('idx_device_tokens_user_agent', $existingIndexes)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `idx_device_tokens_user_agent` (`user_agent`(191))");
            }
            if (!in_array('idx_device_tokens_last_used', $existingIndexes)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `idx_device_tokens_last_used` (`last_used_at`)");
            }
            if (!in_array('idx_device_tokens_created', $existingIndexes)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `idx_device_tokens_created` (`created_at`)");
            }
        } elseif ($driver === 'pgsql') {
            // PostgreSQL - check if indexes exist
            $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = '{$table}'");
            $existingIndexes = array_column($indexes, 'indexname');

            if (!in_array('idx_device_tokens_session', $existingIndexes)) {
                DB::statement("CREATE INDEX idx_device_tokens_session ON {$table} (session_id)");
            }
            if (!in_array('idx_device_tokens_platform', $existingIndexes)) {
                DB::statement("CREATE INDEX idx_device_tokens_platform ON {$table} (platform)");
            }
            if (!in_array('idx_device_tokens_user_agent', $existingIndexes)) {
                DB::statement("CREATE INDEX idx_device_tokens_user_agent ON {$table} (user_agent)");
            }
            if (!in_array('idx_device_tokens_last_used', $existingIndexes)) {
                DB::statement("CREATE INDEX idx_device_tokens_last_used ON {$table} (last_used_at)");
            }
            if (!in_array('idx_device_tokens_created', $existingIndexes)) {
                DB::statement("CREATE INDEX idx_device_tokens_created ON {$table} (created_at)");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'user_agent',
                'session_id',
                'platform',
                'token',
                'metadata',
                'last_used_at',
                'created_at',
                'updated_at'
            ]);
        });

        // Drop indexes using raw SQL
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $table = 'device_tokens';

        if ($driver === 'mysql') {
            DB::statement("DROP INDEX idx_device_tokens_session ON {$table}");
            DB::statement("DROP INDEX idx_device_tokens_platform ON {$table}");
            DB::statement("DROP INDEX idx_device_tokens_user_agent ON {$table}");
            DB::statement("DROP INDEX idx_device_tokens_last_used ON {$table}");
            DB::statement("DROP INDEX idx_device_tokens_created ON {$table}");
        } elseif ($driver === 'pgsql') {
            DB::statement("DROP INDEX idx_device_tokens_session");
            DB::statement("DROP INDEX idx_device_tokens_platform");
            DB::statement("DROP INDEX idx_device_tokens_user_agent");
            DB::statement("DROP INDEX idx_device_tokens_last_used");
            DB::statement("DROP INDEX idx_device_tokens_created");
        }
    }
};