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
        Schema::table('construction_workers', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('construction_workers', 'payment_frequency')) {
                $table->enum('payment_frequency', ['daily', 'weekly', 'biweekly', 'monthly'])
                    ->default('daily')
                    ->after('contract_rate')
                    ->comment('Payment frequency: daily, weekly, biweekly, monthly');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_workers', function (Blueprint $table) {
            if (Schema::hasColumn('construction_workers', 'payment_frequency')) {
                $table->dropColumn('payment_frequency');
            }
        });
    }
};