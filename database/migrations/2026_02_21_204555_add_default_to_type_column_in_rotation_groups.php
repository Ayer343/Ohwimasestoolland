<?php
// database/migrations/xxxx_xx_xx_add_default_to_type_column_in_rotation_groups.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultToTypeColumnInRotationGroups extends Migration
{
    public function up()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->string('type')->default('day')->change();
        });
    }

    public function down()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->string('type')->default(null)->change();
        });
    }
}