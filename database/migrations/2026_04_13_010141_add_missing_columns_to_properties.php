<?php
// database/migrations/2026_04_13_000001_add_missing_columns_to_properties.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            // Add verification_status column if it doesn't exist
            if (!Schema::hasColumn('properties', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('status');
            }
            
            // Add registered_by column if it doesn't exist
            if (!Schema::hasColumn('properties', 'registered_by')) {
                $table->foreignId('registered_by')->nullable()->after('landlord_id')->constrained('users')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'verification_status')) {
                $table->dropColumn('verification_status');
            }
            if (Schema::hasColumn('properties', 'registered_by')) {
                $table->dropForeign(['registered_by']);
                $table->dropColumn('registered_by');
            }
        });
    }
};