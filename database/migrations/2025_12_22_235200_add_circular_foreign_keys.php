<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix 1: Add current_lease_id foreign key to property_units
        Schema::table('property_units', function (Blueprint $table) {
            $table->foreign('current_lease_id')
                  ->references('id')
                  ->on('rental_agreements')
                  ->onDelete('set null');
        });
        
        // Fix 2: Add unit_id foreign key to rental_agreements
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->foreign('unit_id')
                  ->references('id')
                  ->on('property_units')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Drop in reverse order
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
        });
        
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropForeign(['current_lease_id']);
        });
    }
};