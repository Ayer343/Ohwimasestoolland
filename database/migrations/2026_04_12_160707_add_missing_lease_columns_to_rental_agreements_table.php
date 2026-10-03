<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            // Add missing columns that controller expects
            
            // Lease type (fixed or month_to_month)
            if (!Schema::hasColumn('rental_agreements', 'lease_type')) {
                $table->enum('lease_type', ['fixed', 'month_to_month'])->default('fixed')->after('landlord_id');
            }
            
            // Utility deposit
            if (!Schema::hasColumn('rental_agreements', 'utility_deposit')) {
                $table->decimal('utility_deposit', 10, 2)->default(0)->after('security_deposit');
            }
            
            // Late fee percentage (you have 'late_fee' which might be different)
            if (!Schema::hasColumn('rental_agreements', 'late_fee_percentage')) {
                $table->decimal('late_fee_percentage', 5, 2)->default(0)->after('late_fee');
            }
            
            // Late fee fixed amount
            if (!Schema::hasColumn('rental_agreements', 'late_fee_fixed')) {
                $table->decimal('late_fee_fixed', 10, 2)->default(0)->after('late_fee_percentage');
            }
            
            // Notice period days
            if (!Schema::hasColumn('rental_agreements', 'notice_period_days')) {
                $table->integer('notice_period_days')->default(30)->after('payment_due_day');
            }
            
            // Terms (standard lease terms)
            if (!Schema::hasColumn('rental_agreements', 'terms')) {
                $table->longText('terms')->nullable()->after('special_terms');
            }
            
            // Renewal terms
            if (!Schema::hasColumn('rental_agreements', 'renewal_terms')) {
                $table->text('renewal_terms')->nullable()->after('terms');
            }
            
            // Created by type (landlord/admin/etc)
            if (!Schema::hasColumn('rental_agreements', 'created_by_type')) {
                $table->string('created_by_type')->nullable()->after('created_by');
            }
            
            // Previous lease ID for renewals
            if (!Schema::hasColumn('rental_agreements', 'previous_lease_id')) {
                $table->unsignedBigInteger('previous_lease_id')->nullable()->after('notes');
                $table->foreign('previous_lease_id')->references('id')->on('rental_agreements')->onDelete('set null');
            }
            
            // Additional signature fields that might be missing
            if (!Schema::hasColumn('rental_agreements', 'landlord_signed_by')) {
                $table->string('landlord_signed_by')->nullable()->after('landlord_signed_at');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'tenant_signed_by')) {
                $table->string('tenant_signed_by')->nullable()->after('tenant_signed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            if (Schema::hasColumn('rental_agreements', 'previous_lease_id')) {
                $table->dropForeign(['previous_lease_id']);
            }
            
            $columns = [
                'lease_type',
                'utility_deposit',
                'late_fee_percentage',
                'late_fee_fixed',
                'notice_period_days',
                'terms',
                'renewal_terms',
                'created_by_type',
                'previous_lease_id',
                'landlord_signed_by',
                'tenant_signed_by'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('rental_agreements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};