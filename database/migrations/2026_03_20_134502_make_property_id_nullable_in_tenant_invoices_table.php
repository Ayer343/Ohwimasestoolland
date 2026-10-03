<?php
// database/migrations/2026_03_20_134502_make_property_id_nullable_in_tenant_invoices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Check if table exists and column exists before modifying
        if (Schema::hasTable('tenant_invoices') && Schema::hasColumn('tenant_invoices', 'property_id')) {
            Schema::table('tenant_invoices', function (Blueprint $table) {
                $table->foreignId('property_id')
                    ->nullable()
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable('tenant_invoices') && Schema::hasColumn('tenant_invoices', 'property_id')) {
            Schema::table('tenant_invoices', function (Blueprint $table) {
                $table->foreignId('property_id')
                    ->nullable(false)
                    ->change();
            });
        }
    }
};