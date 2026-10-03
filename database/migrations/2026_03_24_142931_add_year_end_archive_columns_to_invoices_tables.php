<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddYearEndArchiveColumnsToInvoicesTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add columns to invoices table (landlord invoices)
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'year_end_archived_at')) {
                $table->timestamp('year_end_archived_at')->nullable();
            }
            
            if (!Schema::hasColumn('invoices', 'year_end_archive_year')) {
                $table->year('year_end_archive_year')->nullable();
            }
            
            if (!Schema::hasColumn('invoices', 'original_year')) {
                $table->year('original_year')->nullable();
            }
        });
        
        // Add columns to tenant_invoices table
        Schema::table('tenant_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_invoices', 'year_end_archived_at')) {
                $table->timestamp('year_end_archived_at')->nullable();
            }
            
            if (!Schema::hasColumn('tenant_invoices', 'year_end_archive_year')) {
                $table->year('year_end_archive_year')->nullable();
            }
            
            if (!Schema::hasColumn('tenant_invoices', 'original_year')) {
                $table->year('original_year')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'year_end_archived_at',
                'year_end_archive_year',
                'original_year'
            ]);
        });
        
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'year_end_archived_at',
                'year_end_archive_year',
                'original_year'
            ]);
        });
    }
}