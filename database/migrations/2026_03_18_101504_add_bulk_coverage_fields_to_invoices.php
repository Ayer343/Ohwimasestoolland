<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Safely add columns only if they don't exist
            if (!Schema::hasColumn('invoices', 'covers_periods')) {
                $table->json('covers_periods')->nullable()->after('metadata')
                      ->comment('JSON array of periods (Y-m) covered by this bulk payment');
            }

            if (!Schema::hasColumn('invoices', 'bulk_coverage_start')) {
                $table->date('bulk_coverage_start')->nullable()->after('covers_periods')
                      ->comment('Start date of bulk coverage period (first day of month)');
            }

            if (!Schema::hasColumn('invoices', 'bulk_coverage_end')) {
                $table->date('bulk_coverage_end')->nullable()->after('bulk_coverage_start')
                      ->comment('End date of bulk coverage period (last day of month)');
            }
        });

        // Add indexes safely
        Schema::table('invoices', function (Blueprint $table) {
            try {
                $table->index('bulk_coverage_start');
            } catch (\Exception $e) {
                // Index might already exist
                echo "Index on bulk_coverage_start may already exist\n";
            }

            try {
                $table->index('bulk_coverage_end');
            } catch (\Exception $e) {
                // Index might already exist
                echo "Index on bulk_coverage_end may already exist\n";
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Safely drop columns only if they exist
            if (Schema::hasColumn('invoices', 'covers_periods')) {
                $table->dropColumn('covers_periods');
            }

            if (Schema::hasColumn('invoices', 'bulk_coverage_start')) {
                $table->dropColumn('bulk_coverage_start');
            }

            if (Schema::hasColumn('invoices', 'bulk_coverage_end')) {
                $table->dropColumn('bulk_coverage_end');
            }
        });
    }
};