<?php
// database/migrations/2026_06_15_145311_add_roles_index_to_notifications.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddRolesIndexToNotifications extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Check if notifications table exists
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql') {
            $this->handleMySQL();
        } elseif ($driver === 'pgsql') {
            $this->handlePostgreSQL();
        } else {
            $this->handleFallback();
        }
    }
    
    /**
     * Handle MySQL (both MySQL and MariaDB)
     */
    protected function handleMySQL()
    {
        $version = DB::select('SELECT VERSION() as version')[0]->version;
        $isMariaDB = strpos(strtolower($version), 'mariadb') !== false;
        
        // Check if roles_json column already exists
        $columnExists = Schema::hasColumn('notifications', 'roles_json');
        
        if (!$columnExists) {
            try {
                // Add generated column ONLY if it doesn't exist
                if ($isMariaDB) {
                    // MariaDB syntax
                    DB::statement("
                        ALTER TABLE notifications 
                        ADD COLUMN roles_json JSON AS (JSON_EXTRACT(data, '$.roles')) VIRTUAL
                    ");
                } else {
                    // MySQL syntax
                    DB::statement("
                        ALTER TABLE notifications 
                        ADD COLUMN roles_json JSON GENERATED ALWAYS AS (JSON_EXTRACT(data, '$.roles')) STORED
                    ");
                }
            } catch (\Exception $e) {
                // If virtual column fails, try creating a regular column
                if (!Schema::hasColumn('notifications', 'roles_json')) {
                    Schema::table('notifications', function (Blueprint $table) {
                        $table->json('roles_json')->nullable();
                    });
                    
                    // For regular column, we can update it
                    DB::statement("
                        UPDATE notifications 
                        SET roles_json = JSON_EXTRACT(data, '$.roles')
                        WHERE roles_json IS NULL
                    ");
                }
            }
        }
        
        // Now add index - check if it exists first
        $indexExists = $this->indexExists('notifications', 'idx_roles_json');
        
        if (!$indexExists) {
            try {
                // For MariaDB, we might need a different approach
                if ($isMariaDB) {
                    // Try to create index on JSON path directly (MariaDB 10.2+)
                    try {
                        DB::statement("
                            ALTER TABLE notifications 
                            ADD INDEX idx_roles_json ((data->>'$.roles'))
                        ");
                    } catch (\Exception $e) {
                        // If that fails, create a regular index on the column
                        Schema::table('notifications', function (Blueprint $table) {
                            if (Schema::hasColumn('notifications', 'roles_json')) {
                                $table->index('roles_json', 'idx_roles_json');
                            }
                        });
                    }
                } else {
                    // For MySQL, index the generated column
                    Schema::table('notifications', function (Blueprint $table) {
                        if (Schema::hasColumn('notifications', 'roles_json')) {
                            $table->index('roles_json', 'idx_roles_json');
                        }
                    });
                }
            } catch (\Exception $e) {
                // If index creation fails, try alternative approach
                try {
                    DB::statement("
                        ALTER TABLE notifications 
                        ADD INDEX idx_notification_roles ((data->>'$.roles'))
                    ");
                } catch (\Exception $e2) {
                    // Last resort: create a regular column and index it
                    if (!Schema::hasColumn('notifications', 'roles_json')) {
                        Schema::table('notifications', function (Blueprint $table) {
                            $table->json('roles_json')->nullable();
                            $table->index('roles_json', 'idx_roles_json');
                        });
                        
                        // Update existing records
                        DB::statement("
                            UPDATE notifications 
                            SET roles_json = JSON_EXTRACT(data, '$.roles')
                            WHERE roles_json IS NULL
                        ");
                    }
                }
            }
        }
        
        // DO NOT UPDATE generated columns - they are auto-computed!
        // The generated column will automatically have the correct value
    }
    
    /**
     * Handle PostgreSQL
     */
    protected function handlePostgreSQL()
    {
        // Check if index exists
        $indexExists = DB::select("
            SELECT COUNT(*) as count 
            FROM pg_indexes 
            WHERE tablename = 'notifications' 
            AND indexname = 'idx_notification_roles'
        ")[0]->count > 0;
        
        if (!$indexExists) {
            DB::statement("
                CREATE INDEX idx_notification_roles 
                ON notifications USING GIN ((data->'roles'))
            ");
        }
    }
    
    /**
     * Fallback for other databases (SQLite, etc.)
     */
    protected function handleFallback()
    {
        // Add a regular column that can be indexed
        if (!Schema::hasColumn('notifications', 'roles_json')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->json('roles_json')->nullable();
                $table->index('roles_json', 'idx_roles_json');
            });
            
            // Update existing records
            DB::statement("
                UPDATE notifications 
                SET roles_json = JSON_EXTRACT(data, '$.roles')
                WHERE roles_json IS NULL
            ");
        }
    }
    
    /**
     * Check if an index exists on a table
     */
    protected function indexExists($table, $indexName)
    {
        try {
            $result = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND INDEX_NAME = ?
            ", [$table, $indexName]);
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        try {
            // Drop index first
            DB::statement('DROP INDEX idx_roles_json ON notifications');
        } catch (\Exception $e) {
            // Index might not exist
        }
        
        try {
            DB::statement('DROP INDEX idx_notification_roles ON notifications');
        } catch (\Exception $e) {
            // Index might not exist
        }
        
        // Drop the column if it exists
        if (Schema::hasColumn('notifications', 'roles_json')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('roles_json');
            });
        }
        
        // Drop trigger if it exists
        try {
            DB::statement('DROP TRIGGER IF EXISTS update_notification_roles');
        } catch (\Exception $e) {
            // Trigger might not exist
        }
    }
}