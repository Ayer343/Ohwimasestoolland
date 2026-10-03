<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyIssueDateInTenantInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            // Option A: Make it nullable
            $table->date('issue_date')->nullable()->change();
            
            // OR Option B: Add a default value
            // $table->date('issue_date')->default(DB::raw('CURRENT_DATE'))->change();
        });
    }

    public function down()
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->date('issue_date')->nullable(false)->change();
        });
    }
}