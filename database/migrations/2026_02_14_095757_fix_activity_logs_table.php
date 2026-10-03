<?php
// database/migrations/2026_02_14_100000_fix_activity_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixActivityLogsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                // Add type column if it doesn't exist
                if (!Schema::hasColumn('activity_logs', 'type')) {
                    $table->string('type')->default('schedule')->after('action');
                }
                
                // Add other potentially missing columns
                if (!Schema::hasColumn('activity_logs', 'model_type')) {
                    $table->string('model_type')->nullable()->after('type');
                }
                
                if (!Schema::hasColumn('activity_logs', 'model_id')) {
                    $table->unsignedBigInteger('model_id')->nullable()->after('model_type');
                }
                
                if (!Schema::hasColumn('activity_logs', 'action')) {
                    $table->string('action')->nullable()->after('model_id');
                }
                
                if (!Schema::hasColumn('activity_logs', 'metadata')) {
                    $table->json('metadata')->nullable()->after('action');
                }
                
                if (!Schema::hasColumn('activity_logs', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('id');
                }
                
                if (!Schema::hasColumn('activity_logs', 'ip_address')) {
                    $table->string('ip_address')->nullable()->after('metadata');
                }
                
                if (!Schema::hasColumn('activity_logs', 'user_agent')) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                }
            });
        } else {
            // Create the table if it doesn't exist
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type')->default('schedule');
                $table->string('model_type')->nullable();
                $table->unsignedBigInteger('model_id')->nullable();
                $table->string('action');
                $table->json('metadata')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
                
                $table->index(['model_type', 'model_id']);
                $table->index(['user_id', 'created_at']);
                $table->index('type');
                $table->index('action');
            });
        }
    }

    public function down()
    {
        // Don't drop the table in down to prevent data loss
        // Just remove the columns we added if needed
        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $columns = ['type', 'model_type', 'model_id', 'ip_address', 'user_agent'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('activity_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}