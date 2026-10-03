<?php
// database/migrations/2026_09_09_000000_add_assigned_personnel_id_to_collection_zones.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('collection_zones', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
                $table->unsignedBigInteger('assigned_personnel_id')->nullable()->after('description');
                $table->foreign('assigned_personnel_id')
                    ->references('id')
                    ->on('sanitation_personnel')
                    ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('collection_zones', function (Blueprint $table) {
            if (Schema::hasColumn('collection_zones', 'assigned_personnel_id')) {
                $table->dropForeign(['assigned_personnel_id']);
                $table->dropColumn('assigned_personnel_id');
            }
        });
    }
};