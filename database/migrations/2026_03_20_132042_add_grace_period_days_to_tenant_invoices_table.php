<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGracePeriodDaysToTenantInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->integer('grace_period_days')->default(7)->after('penalty_amount');
        });
    }

    public function down()
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->dropColumn('grace_period_days');
        });
    }
}