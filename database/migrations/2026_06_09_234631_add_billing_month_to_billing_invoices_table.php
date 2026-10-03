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
        Schema::table('billing_invoices', function (Blueprint $table) {
            // Add billing_month column after due_date
            $table->string('billing_month')->nullable()->after('due_date');
            $table->index('billing_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex(['billing_month']);
            $table->dropColumn('billing_month');
        });
    }
};