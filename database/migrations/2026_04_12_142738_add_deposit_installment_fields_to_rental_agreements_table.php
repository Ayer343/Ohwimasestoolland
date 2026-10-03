<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get existing columns
        $existingColumns = Schema::getColumnListing('rental_agreements');
        
        Schema::table('rental_agreements', function (Blueprint $table) use ($existingColumns) {
            // Only add columns that don't exist
            if (!in_array('deposit_payment_method', $existingColumns)) {
                $table->string('deposit_payment_method', 20)->nullable()->after('security_deposit');
            }
            
            if (!in_array('deposit_installment_months', $existingColumns)) {
                $table->tinyInteger('deposit_installment_months')->unsigned()->nullable()->after('deposit_payment_method');
            }
            
            if (!in_array('deposit_monthly_installment', $existingColumns)) {
                $table->decimal('deposit_monthly_installment', 10, 2)->nullable()->after('deposit_installment_months');
            }
            
            if (!in_array('deposit_collected_so_far', $existingColumns)) {
                $table->decimal('deposit_collected_so_far', 10, 2)->default(0)->after('deposit_monthly_installment');
            }
            
            if (!in_array('deposit_fully_paid_at', $existingColumns)) {
                $table->timestamp('deposit_fully_paid_at')->nullable()->after('deposit_collected_so_far');
            }
        });
        
        // Make columns nullable if they exist and are required
        if (in_array('late_fee_percentage', $existingColumns)) {
            DB::statement('ALTER TABLE `rental_agreements` MODIFY `late_fee_percentage` DECIMAL(5,2) DEFAULT 0');
        }
        
        if (in_array('grace_period_days', $existingColumns)) {
            DB::statement('ALTER TABLE `rental_agreements` MODIFY `grace_period_days` INT DEFAULT 0');
        }
    }

    public function down(): void
    {
        // This is a safe down method that won't error if columns don't exist
        Schema::table('rental_agreements', function (Blueprint $table) {
            $columns = ['deposit_payment_method', 'deposit_installment_months', 'deposit_monthly_installment', 'deposit_collected_so_far', 'deposit_fully_paid_at'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('rental_agreements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};