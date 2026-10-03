<?php
// database/migrations/2026_03_21_add_all_archive_columns_to_tenant_invoice_archives.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAllArchiveColumnsToTenantInvoiceArchives extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_invoice_archives', function (Blueprint $table) {
            // Check and add columns one by one to avoid errors
            
            // Add archive_approved_by_tenant column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'archive_approved_by_tenant')) {
                $table->boolean('archive_approved_by_tenant')->default(false)->after('deletion_user_agent');
            }
            
            // Add archive_type column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'archive_type')) {
                $table->string('archive_type')->nullable()->after('archive_approved_by_tenant');
            }
            
            // Add calculation_method column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'calculation_method')) {
                $table->string('calculation_method')->nullable()->after('grace_period_days');
            }
            
            // Add calculation_details column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'calculation_details')) {
                $table->json('calculation_details')->nullable()->after('calculation_method');
            }
            
            // Add grace_period_days column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'grace_period_days')) {
                $table->integer('grace_period_days')->default(7)->after('penalty_applied_at');
            }
            
            // Add month_name column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'month_name')) {
                $table->string('month_name')->nullable()->after('period');
            }
            
            // Add original_created_by column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'original_created_by')) {
                $table->unsignedBigInteger('original_created_by')->nullable()->after('original_created_at');
            }
            
            // Add deletion_ip column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'deletion_ip')) {
                $table->string('deletion_ip')->nullable()->after('deletion_reason');
            }
            
            // Add deletion_user_agent column if it doesn't exist
            if (!Schema::hasColumn('tenant_invoice_archives', 'deletion_user_agent')) {
                $table->string('deletion_user_agent')->nullable()->after('deletion_ip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_invoice_archives', function (Blueprint $table) {
            $columns = [
                'archive_approved_by_tenant',
                'archive_type',
                'calculation_method',
                'calculation_details',
                'grace_period_days',
                'month_name',
                'original_created_by',
                'deletion_ip',
                'deletion_user_agent'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenant_invoice_archives', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}