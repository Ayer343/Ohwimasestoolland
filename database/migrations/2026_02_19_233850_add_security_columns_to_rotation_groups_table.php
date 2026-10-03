<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecurityColumnsToRotationGroupsTable extends Migration
{
    public function up()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            // Check if columns exist before adding
            if (!Schema::hasColumn('rotation_groups', 'security_post_id')) {
                $table->foreignId('security_post_id')->nullable()->constrained('security_posts')->after('description');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'security_shift_id')) {
                $table->foreignId('security_shift_id')->nullable()->constrained('security_shifts')->after('security_post_id');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'group_type')) {
                $table->string('group_type')->default('day')->after('security_shift_id');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'rotation_config')) {
                $table->json('rotation_config')->nullable()->after('group_type');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'max_members')) {
                $table->integer('max_members')->nullable()->after('rotation_config');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'min_members')) {
                $table->integer('min_members')->nullable()->after('max_members');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'current_members')) {
                $table->integer('current_members')->default(0)->after('min_members');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'preference_weights')) {
                $table->json('preference_weights')->nullable()->after('current_members');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'auto_rotate')) {
                $table->boolean('auto_rotate')->default(false)->after('preference_weights');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'auto_rotate_schedule')) {
                $table->string('auto_rotate_schedule')->nullable()->after('auto_rotate');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'auto_rotate_time')) {
                $table->time('auto_rotate_time')->nullable()->after('auto_rotate_schedule');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'last_rotated_at')) {
                $table->timestamp('last_rotated_at')->nullable()->after('auto_rotate_time');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_rotated_at');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'settings')) {
                $table->json('settings')->nullable()->after('is_active');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->after('settings');
            }
            
            if (!Schema::hasColumn('rotation_groups', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->after('created_by');
            }
        });
    }

    public function down()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['security_post_id']);
            $table->dropForeign(['security_shift_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            
            // Then drop columns
            $table->dropColumn([
                'security_post_id',
                'security_shift_id',
                'group_type',
                'rotation_config',
                'max_members',
                'min_members',
                'current_members',
                'preference_weights',
                'auto_rotate',
                'auto_rotate_schedule',
                'auto_rotate_time',
                'last_rotated_at',
                'is_active',
                'settings',
                'created_by',
                'updated_by'
            ]);
        });
    }
}