<?php
// database/migrations/2026_02_14_094409_add_missing_fields_to_security_schedules_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddMissingFieldsToSecuritySchedulesTable extends Migration
{
    public function up()
    {
        Schema::table('security_schedules', function (Blueprint $table) {
            // Check and add missing rotation fields using Schema::hasColumn
            if (!Schema::hasColumn('security_schedules', 'rotation_group_id')) {
                $table->unsignedBigInteger('rotation_group_id')->nullable()->after('assigned_by');
            }
            
            if (!Schema::hasColumn('security_schedules', 'rotated_from_user_id')) {
                $table->unsignedBigInteger('rotated_from_user_id')->nullable()->after('rotation_group_id');
            }
            
            if (!Schema::hasColumn('security_schedules', 'rotation_cycle_start_date')) {
                $table->date('rotation_cycle_start_date')->nullable()->after('rotation_sequence_number');
            }
            
            if (!Schema::hasColumn('security_schedules', 'rotation_cycle_end_date')) {
                $table->date('rotation_cycle_end_date')->nullable()->after('rotation_cycle_start_date');
            }
            
            if (!Schema::hasColumn('security_schedules', 'rotation_swap_count')) {
                $table->integer('rotation_swap_count')->default(0)->after('rotation_cycle_end_date');
            }
            
            if (!Schema::hasColumn('security_schedules', 'rotation_history')) {
                $table->longText('rotation_history')->nullable()->after('rotation_swap_count');
            }
            
            if (!Schema::hasColumn('security_schedules', 'attendance_score')) {
                $table->decimal('attendance_score', 5, 2)->nullable()->after('checkin_notes');
            }
            
            if (!Schema::hasColumn('security_schedules', 'punctuality_score')) {
                $table->decimal('punctuality_score', 5, 2)->nullable()->after('attendance_score');
            }
            
            if (!Schema::hasColumn('security_schedules', 'audit_log')) {
                $table->longText('audit_log')->nullable()->after('punctuality_score');
            }
            
            if (!Schema::hasColumn('security_schedules', 'metadata')) {
                $table->longText('metadata')->nullable()->after('audit_log');
            }
        });
        
        // Add foreign keys using raw SQL to check if they exist
        $this->addForeignKeysIfNotExist();
    }
    
    private function addForeignKeysIfNotExist()
    {
        // Get all foreign keys from the table
        $foreignKeys = $this->getForeignKeys('security_schedules');
        
        // Add rotation_group_id foreign key if it doesn't exist
        if (!in_array('security_schedules_rotation_group_id_foreign', $foreignKeys) && 
            Schema::hasTable('rotation_groups')) {
            
            Schema::table('security_schedules', function (Blueprint $table) {
                $table->foreign('rotation_group_id')
                      ->references('id')
                      ->on('rotation_groups')
                      ->onDelete('set null');
            });
        }
        
        // Add rotated_from_user_id foreign key if it doesn't exist
        if (!in_array('security_schedules_rotated_from_user_id_foreign', $foreignKeys)) {
            Schema::table('security_schedules', function (Blueprint $table) {
                $table->foreign('rotated_from_user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }
    }
    
    private function getForeignKeys($tableName)
    {
        try {
            // For MySQL
            $databaseName = env('DB_DATABASE');
            $results = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$databaseName, $tableName]);
            
            return array_map(function($row) {
                return $row->CONSTRAINT_NAME;
            }, $results);
        } catch (\Exception $e) {
            // If the query fails, return empty array and log the error
            \Log::warning('Could not fetch foreign keys: ' . $e->getMessage());
            return [];
        }
    }

    public function down()
    {
        // Drop foreign keys first
        try {
            Schema::table('security_schedules', function (Blueprint $table) {
                $table->dropForeign(['rotation_group_id']);
                $table->dropForeign(['rotated_from_user_id']);
            });
        } catch (\Exception $e) {
            // Foreign keys might not exist, ignore
        }
        
        // Then drop columns
        Schema::table('security_schedules', function (Blueprint $table) {
            $columns = [
                'rotation_group_id',
                'rotated_from_user_id',
                'rotation_cycle_start_date',
                'rotation_cycle_end_date',
                'rotation_swap_count',
                'rotation_history',
                'attendance_score',
                'punctuality_score',
                'audit_log',
                'metadata',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('security_schedules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}