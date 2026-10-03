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
        Schema::table('property_units', function (Blueprint $table) {
            // Check if columns exist before adding them
            if (!Schema::hasColumn('property_units', 'security_deposit_refunded')) {
                $table->decimal('security_deposit_refunded', 10, 2)->nullable()->after('security_deposit');
            }
            
            if (!Schema::hasColumn('property_units', 'security_deposit_deduction')) {
                $table->decimal('security_deposit_deduction', 10, 2)->nullable()->after('security_deposit_refunded');
            }
            
            if (!Schema::hasColumn('property_units', 'security_deposit_deduction_reason')) {
                $table->text('security_deposit_deduction_reason')->nullable()->after('security_deposit_deduction');
            }
            
            if (!Schema::hasColumn('property_units', 'security_deposit_refunded_at')) {
                $table->timestamp('security_deposit_refunded_at')->nullable()->after('security_deposit_deduction_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn([
                'security_deposit_refunded',
                'security_deposit_deduction',
                'security_deposit_deduction_reason',
                'security_deposit_refunded_at'
            ]);
        });
    }
};