<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToRotationGroupsTable extends Migration
{
    public function up()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
        });

        // Populate the status column based on is_active
        DB::table('rotation_groups')->update([
            'status' => DB::raw('CASE WHEN is_active = 1 THEN "active" ELSE "inactive" END')
        ]);
    }

    public function down()
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}