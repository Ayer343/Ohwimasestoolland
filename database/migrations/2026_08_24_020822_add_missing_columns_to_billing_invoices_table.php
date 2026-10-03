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
            // 1. Add agreement_id for relationship
            if (!Schema::hasColumn('billing_invoices', 'agreement_id')) {
                $table->unsignedBigInteger('agreement_id')->nullable()->after('admin_billing_record_id');
                $table->foreign('agreement_id')->references('id')->on('admin_billing_records')->onDelete('set null');
            }

            // 2. Add amount tracking columns
            if (!Schema::hasColumn('billing_invoices', 'original_amount')) {
                $table->decimal('original_amount', 15, 2)->nullable()->after('amount');
            }

            if (!Schema::hasColumn('billing_invoices', 'amount_paid_already')) {
                $table->decimal('amount_paid_already', 15, 2)->default(0)->after('original_amount');
            }

            if (!Schema::hasColumn('billing_invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('amount_paid_already');
            }

            // 3. Add payment tracking columns
            if (!Schema::hasColumn('billing_invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('paid_amount');
            }

            if (!Schema::hasColumn('billing_invoices', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('paid_at');
            }

            if (!Schema::hasColumn('billing_invoices', 'sent_to')) {
                $table->string('sent_to')->nullable()->after('sent_at');
            }

            // 4. Add payment method and reference columns
            if (!Schema::hasColumn('billing_invoices', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('sent_to');
            }

            if (!Schema::hasColumn('billing_invoices', 'transaction_reference')) {
                $table->string('transaction_reference')->nullable()->after('payment_method');
            }

            if (!Schema::hasColumn('billing_invoices', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('transaction_reference');
            }

            if (!Schema::hasColumn('billing_invoices', 'payment_data')) {
                $table->json('payment_data')->nullable()->after('payment_reference');
            }

            // 5. Add auto-generated flag
            if (!Schema::hasColumn('billing_invoices', 'is_auto_generated')) {
                $table->boolean('is_auto_generated')->default(false)->after('is_custom');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
                $table->dropForeign(['agreement_id']);
            }

            // Drop columns
            $columns = [
                'agreement_id',
                'original_amount',
                'amount_paid_already',
                'paid_amount',
                'paid_at',
                'sent_at',
                'sent_to',
                'payment_method',
                'transaction_reference',
                'payment_reference',
                'payment_data',
                'is_auto_generated'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('billing_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};