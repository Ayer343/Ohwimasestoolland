<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('digital_address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('city', 100)->nullable()->after('longitude');
            $table->index(['latitude', 'longitude'], 'properties_coordinates_index');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_coordinates_index');
            $table->dropColumn(['latitude', 'longitude', 'city']);
        });
    }
};
