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
        Schema::table('landlord_phones', function (Blueprint $table) {
            // Only add the is_active column if it doesn't exist
            if (!Schema::hasColumn('landlord_phones', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('phone_number');
            }
            
            // DO NOT add indexes or other columns that might already exist
            // Only add what's absolutely necessary
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landlord_phones', function (Blueprint $table) {
            // Only drop the is_active column if it exists
            if (Schema::hasColumn('landlord_phones', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};