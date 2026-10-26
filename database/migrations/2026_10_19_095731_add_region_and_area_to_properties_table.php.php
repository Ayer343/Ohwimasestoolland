// database/migrations/xxxx_xx_xx_add_region_and_area_to_properties_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Broad geographic location — sits ABOVE the community-level
            // zone/section/block address, so a property can be described as
            // "Greater Accra Region → Ga East → Madina → Community 5 → Block C".
            $table->string('region', 100)->nullable()->after('zone');
            $table->string('area', 150)->nullable()->after('region');
            // $table->string('district', 150)->nullable()->after('region'); // optional

            // Index for the region/area filters in PropertyController::applyFilters
            $table->index(['region', 'area']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['region', 'area']);
            // $table->dropColumn('district');
            $table->dropColumn(['region', 'area']);
        });
    }
};