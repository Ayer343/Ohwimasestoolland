<?php
// database/migrations/xxxx_xx_xx_add_missing_columns_to_rotation_groups.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToRotationGroups extends Migration
{
    public function up()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            // Add code column (required)
            if (!Schema::hasColumn('rotation_groups', 'code')) {
                $table->string('code')->nullable()->after('name');
                $table->unique('code', 'unq_rotation_groups_code');
            }

            // Add foreign key columns
            if (!Schema::hasColumn('rotation_groups', 'security_post_id')) {
                $table->unsignedBigInteger('security_post_id')->nullable()->after('type');
                $table->foreign('security_post_id', 'fk_rot_group_post')
                      ->references('id')->on('security_posts')
                      ->onDelete('set null');
            }

            if (!Schema::hasColumn('rotation_groups', 'security_shift_id')) {
                $table->unsignedBigInteger('security_shift_id')->nullable()->after('security_post_id');
                $table->foreign('security_shift_id', 'fk_rot_group_shift')
                      ->references('id')->on('security_shifts')
                      ->onDelete('set null');
            }

            // Add configuration columns
            if (!Schema::hasColumn('rotation_groups', 'rotation_config')) {
                $table->json('rotation_config')->nullable()->after('configuration');
            }

            if (!Schema::hasColumn('rotation_groups', 'preference_weights')) {
                $table->json('preference_weights')->nullable()->after('rotation_config');
            }

            // Add member management columns
            if (!Schema::hasColumn('rotation_groups', 'max_members')) {
                $table->integer('max_members')->default(10)->after('preference_weights');
            }

            if (!Schema::hasColumn('rotation_groups', 'min_members')) {
                $table->integer('min_members')->default(2)->after('max_members');
            }

            if (!Schema::hasColumn('rotation_groups', 'current_members')) {
                $table->integer('current_members')->default(0)->after('min_members');
            }

            // Add rotation scheduling columns
            if (!Schema::hasColumn('rotation_groups', 'auto_rotate')) {
                $table->boolean('auto_rotate')->default(false)->after('current_members');
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

            // Add settings column
            if (!Schema::hasColumn('rotation_groups', 'settings')) {
                $table->json('settings')->nullable()->after('last_rotated_at');
            }

            // Add audit columns
            if (!Schema::hasColumn('rotation_groups', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $table->foreign('updated_by', 'fk_rot_group_updated_by')
                      ->references('id')->on('users')
                      ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->dropForeign(['security_post_id']);
            $table->dropForeign(['security_shift_id']);
            $table->dropForeign(['updated_by']);
            
            $table->dropColumn([
                'code',
                'security_post_id',
                'security_shift_id',
                'rotation_config',
                'preference_weights',
                'max_members',
                'min_members',
                'current_members',
                'auto_rotate',
                'auto_rotate_schedule',
                'auto_rotate_time',
                'last_rotated_at',
                'settings',
                'updated_by'
            ]);
        });
    }
}