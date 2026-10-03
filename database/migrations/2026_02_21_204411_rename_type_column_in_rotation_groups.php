<?php
// database/migrations/xxxx_xx_xx_rename_type_column_in_rotation_groups.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameTypeColumnInRotationGroups extends Migration
{
    public function up()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            if (Schema::hasColumn('rotation_groups', 'type') && 
                !Schema::hasColumn('rotation_groups', 'group_type')) {
                $table->renameColumn('type', 'group_type');
            }
        });
    }

    public function down()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            if (Schema::hasColumn('rotation_groups', 'group_type') && 
                !Schema::hasColumn('rotation_groups', 'type')) {
                $table->renameColumn('group_type', 'type');
            }
        });
    }
}