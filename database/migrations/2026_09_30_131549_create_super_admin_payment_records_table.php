<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `super_admin_payment_records` table is the shared payment ledger
     * for the primary-SA billing model. Each row represents one super
     * admin's contribution toward one billing month.
     *
     * Referenced by:
     *   - DeveloperBillingController (5 sites)
     *   - SuperAdminBillingController (4 sites)
     *   - DeveloperBillingService (1 site)
     *
     * These references have been in place since the billing redesign but
     * the table was never created. This migration closes that gap.
     */
    public function up(): void
    {
        if (Schema::hasTable('super_admin_payment_records')) {
            return;
        }

        Schema::create('super_admin_payment_records', function (Blueprint $table) {
            $table->id();

            // Who is being billed and by whom
            $table->foreignId('developer_setting_id')
                  ->constrained('developer_settings')
                  ->cascadeOnDelete();

            $table->foreignId('super_admin_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Which agreement this payment counts toward.
            // Nullable so a payment can exist even if the agreement is later
            // removed — historical records shouldn't disappear.
            $table->foreignId('agreement_id')
                  ->nullable()
                  ->constrained('admin_billing_records')
                  ->nullOnDelete();

            // Billing period, format YYYY-MM
            $table->string('billing_month', 7);

            // The money
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('currency', 3)->default('GHS');

            // How it was paid
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_reference', 191)->nullable();

            // Status lifecycle:
            //   pending  → record created, payment not yet received
            //   confirmed → developer/SA confirmed the payment landed
            //   failed   → payment bounced or was rejected
            $table->string('status', 20)->default('pending');

            // Dates
            $table->date('payment_date')->nullable();
            $table->timestamp('last_payment_date')->nullable();

            // Audit
            $table->foreignId('confirmed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            // Free-form metadata (provider response, reconciliation notes, etc.)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // One super admin can have at most ONE record per developer per month.
            // The service uses updateOrCreate([...]) keyed on these three columns,
            // so this unique index enforces the invariant at the DB level.
            $table->unique(
                ['developer_setting_id', 'super_admin_id', 'billing_month'],
                'sapr_dev_sa_month_unique'
            );

            // Query patterns used by the service and controllers
            $table->index(['developer_setting_id', 'billing_month'], 'sapr_dev_month_idx');
            $table->index('billing_month');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_payment_records');
    }
};