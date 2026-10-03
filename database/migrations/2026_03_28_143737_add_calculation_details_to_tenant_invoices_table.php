<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCalculationDetailsToTenantInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            // Add the missing column
            $table->json('calculation_details')->nullable()->after('metadata');
            
            // Also check for other potentially missing columns from the error
            if (!Schema::hasColumn('tenant_invoices', 'grace_period_days')) {
                $table->integer('grace_period_days')->default(7)->after('penalty_amount');
            }
            
            if (!Schema::hasColumn('tenant_invoices', 'archive_status')) {
                $table->string('archive_status')->default('pending')->after('grace_period_days');
            }
            
            if (!Schema::hasColumn('tenant_invoices', 'original_year')) {
                $table->year('original_year')->nullable()->after('archive_status');
            }
        });
    }

    public function down()
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->dropColumn('calculation_details');
            $table->dropColumn('grace_period_days');
            $table->dropColumn('archive_status');
            $table->dropColumn('original_year');
        });
    }
}