<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_billing_records', function (Blueprint $table) {
            $table->id();

            // Relations — foreignId()->constrained() already creates an index,
            // so we do NOT add another $table->index() for these columns.
            $table->foreignId('developer_setting_id')
                ->constrained('developer_settings')
                ->cascadeOnDelete();

            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Billing Details
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->string('billing_period'); // e.g. "2024-01"
            $table->date('invoice_date');
            $table->date('due_date');
            $table->date('paid_date')->nullable();

            // Payment Details
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->text('payment_details')->nullable();
            $table->string('transaction_id')->nullable();

            // Billing Items
            $table->json('billing_items')->nullable(); // breakdown of charges

            // Tax Information
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);

            // Admin Approval
            $table->boolean('admin_approved')->default(false);
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Developer Confirmation
            $table->boolean('developer_confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();

            // Notifications
            $table->boolean('invoice_sent')->default(false);
            $table->timestamp('invoice_sent_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();

            // Metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // -----------------------------------------------------------------
            // Indexes (only for columns that do NOT already have one)
            // -----------------------------------------------------------------
            // NOTE: invoice_number already has a UNIQUE index from ->unique(),
            //       so no extra ->index() is needed.
            // NOTE: developer_setting_id, admin_id, approved_by already have
            //       indexes from foreignId()->constrained().
            //
            // Add only the composite / standalone indexes that are truly extra:

            $table->index('payment_status', 'dbr_payment_status_index');
            $table->index(['billing_period', 'payment_status'], 'dbr_period_status_index');
            $table->index('due_date', 'dbr_due_date_index');
            $table->index(['developer_setting_id', 'payment_status'], 'dbr_dev_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_billing_records');
    }
};