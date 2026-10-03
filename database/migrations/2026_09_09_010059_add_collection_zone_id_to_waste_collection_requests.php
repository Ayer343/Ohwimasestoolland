<?php
// database/migrations/2026_09_09_000002_add_collection_zone_id_to_waste_collection_requests.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('waste_collection_requests', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('waste_collection_requests', 'collection_zone_id')) {
                $table->unsignedBigInteger('collection_zone_id')->nullable()->after('property_id');
                $table->foreign('collection_zone_id')
                    ->references('id')
                    ->on('collection_zones')
                    ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('waste_collection_requests', function (Blueprint $table) {
            if (Schema::hasColumn('waste_collection_requests', 'collection_zone_id')) {
                $table->dropForeign(['collection_zone_id']);
                $table->dropColumn('collection_zone_id');
            }
        });
    }
};