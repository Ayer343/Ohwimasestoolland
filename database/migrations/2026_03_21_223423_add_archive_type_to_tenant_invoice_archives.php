<?php
// database/migrations/2026_03_21_223423_add_archive_type_to_tenant_invoice_archives.php

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
        Schema::table('tenant_invoice_archives', function (Blueprint $table) {
            // First, add the column that will be used as reference
            if (!Schema::hasColumn('tenant_invoice_archives', 'archive_approved_by_tenant')) {
                // Add it after deletion_user_agent (assuming that exists)
                if (Schema::hasColumn('tenant_invoice_archives', 'deletion_user_agent')) {
                    $table->boolean('archive_approved_by_tenant')->default(false)->after('deletion_user_agent');
                } else {
                    $table->boolean('archive_approved_by_tenant')->default(false);
                }
            }
            
            // Now add archive_type after the column we just created
            if (!Schema::hasColumn('tenant_invoice_archives', 'archive_type')) {
                if (Schema::hasColumn('tenant_invoice_archives', 'archive_approved_by_tenant')) {
                    $table->string('archive_type')->nullable()->after('archive_approved_by_tenant');
                } else {
                    $table->string('archive_type')->nullable();
                }
            }
            
            // Add other missing columns
            if (!Schema::hasColumn('tenant_invoice_archives', 'month_name')) {
                if (Schema::hasColumn('tenant_invoice_archives', 'period')) {
                    $table->string('month_name')->nullable()->after('period');
                } else {
                    $table->string('month_name')->nullable();
                }
            }
            
            if (!Schema::hasColumn('tenant_invoice_archives', 'grace_period_days')) {
                if (Schema::hasColumn('tenant_invoice_archives', 'penalty_applied_at')) {
                    $table->integer('grace_period_days')->default(7)->after('penalty_applied_at');
                } else {
                    $table->integer('grace_period_days')->default(7);
                }
            }
            
            if (!Schema::hasColumn('tenant_invoice_archives', 'calculation_method')) {
                if (Schema::hasColumn('tenant_invoice_archives', 'grace_period_days')) {
                    $table->string('calculation_method')->nullable()->after('grace_period_days');
                } else {
                    $table->string('calculation_method')->nullable();
                }
            }
            
            if (!Schema::hasColumn('tenant_invoice_archives', 'calculation_details')) {
                if (Schema::hasColumn('tenant_invoice_archives', 'calculation_method')) {
                    $table->json('calculation_details')->nullable()->after('calculation_method');
                } else {
                    $table->json('calculation_details')->nullable();
                }
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
                'archive_type',
                'archive_approved_by_tenant',
                'calculation_method',
                'calculation_details',
                'grace_period_days',
                'month_name'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenant_invoice_archives', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};