<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registration_tenants', function (Blueprint $table) {
            // Add property_id column if it doesn't exist
            if (!Schema::hasColumn('registration_tenants', 'property_id')) {
                $table->foreignId('property_id')
                      ->nullable()
                      ->constrained()
                      ->onDelete('set null')
                      ->after('registration_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_tenants', function (Blueprint $table) {
            if (Schema::hasColumn('registration_tenants', 'property_id')) {
                $table->dropForeign(['property_id']);
                $table->dropColumn('property_id');
            }
        });
    }
};