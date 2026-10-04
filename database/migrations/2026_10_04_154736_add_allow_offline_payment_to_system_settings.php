<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Controls whether admins can mark invoices as paid manually
            // (cash at the office, cheque, bank deposit recorded in person).
            // When false, landlords must pay through the online gateway.
            $table->boolean('allow_offline_payment')
                ->default(true)
                ->after('enable_bulk_payments');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('allow_offline_payment');
        });
    }
};