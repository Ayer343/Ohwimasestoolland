<?php
// database/migrations/2026_09_09_000001_add_collection_zone_id_to_properties.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('properties', 'collection_zone_id')) {
                $table->unsignedBigInteger('collection_zone_id')->nullable()->after('zone');
                $table->foreign('collection_zone_id')
                    ->references('id')
                    ->on('collection_zones')
                    ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'collection_zone_id')) {
                $table->dropForeign(['collection_zone_id']);
                $table->dropColumn('collection_zone_id');
            }
        });
    }
};