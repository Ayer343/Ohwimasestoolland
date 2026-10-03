<?php
// database/migrations/2026_04_13_000004_fix_end_date_column_in_registration_plans.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('registration_plans', function (Blueprint $table) {
            // Add registration_end_date if it doesn't exist
            if (!Schema::hasColumn('registration_plans', 'registration_end_date')) {
                $table->date('registration_end_date')->nullable()->after('registration_start_date');
            }
            
            // Add end_date as an alias (or rename if needed)
            if (!Schema::hasColumn('registration_plans', 'end_date')) {
                $table->date('end_date')->nullable()->after('registration_end_date');
            }
        });
        
        // Sync end_date with registration_end_date for existing records
        DB::statement('UPDATE registration_plans SET end_date = registration_end_date WHERE registration_end_date IS NOT NULL');
    }

    public function down()
    {
        Schema::table('registration_plans', function (Blueprint $table) {
            if (Schema::hasColumn('registration_plans', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });
    }
};