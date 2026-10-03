<?php

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
        Schema::table('tenant_invoices', function (Blueprint $table) {
            // Check if column exists before dropping
            if (Schema::hasColumn('tenant_invoices', 'amount')) {
                $table->dropColumn('amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            // Add back the column in case of rollback
            $table->decimal('amount', 10, 2)->nullable();
        });
    }
};