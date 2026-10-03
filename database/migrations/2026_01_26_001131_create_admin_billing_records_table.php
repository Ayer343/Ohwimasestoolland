<?php
// database/migrations/2026_09_01_000002_create_admin_billing_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_billing_records', function (Blueprint $table) {
            // -----------------------------------------------------------------
            // Primary key — BIGINT UNSIGNED
            // -----------------------------------------------------------------
            $table->id();

            // -----------------------------------------------------------------
            // Relationships
            // foreignId()->constrained() already creates an index on each FK,
            // so no extra $table->index() is needed for these columns.
            // -----------------------------------------------------------------
            $table->foreignId('developer_setting_id')
                ->constrained('developer_settings')
                ->cascadeOnDelete();

            $table->foreignId('super_admin_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // -----------------------------------------------------------------
            // Invoice details
            // unique() already creates an index for invoice_number —
            // no separate $table->index('invoice_number') needed.
            // -----------------------------------------------------------------
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->text('description');
            $table->date('due_date');

            // -----------------------------------------------------------------
            // Status
            // -----------------------------------------------------------------
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('category')->default('other');

            // -----------------------------------------------------------------
            // Payment details
            // -----------------------------------------------------------------
            $table->decimal('amount_received', 10, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->text('payment_notes')->nullable();

            // -----------------------------------------------------------------
            // Payment proof / attachments
            // -----------------------------------------------------------------
            $table->string('receipt_path')->nullable();
            $table->string('invoice_path')->nullable();
            $table->json('attachments')->nullable();

            // -----------------------------------------------------------------
            // Notification tracking
            // -----------------------------------------------------------------
            $table->json('metadata')->nullable();
            $table->timestamp('last_notification_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->integer('notification_count')->default(0);

            // -----------------------------------------------------------------
            // Dates
            // -----------------------------------------------------------------
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // -----------------------------------------------------------------
            // Indexes — only for columns NOT already indexed.
            //
            // Skipped (already indexed):
            //   • invoice_number       → from ->unique()
            //   • super_admin_id       → from foreignId()->constrained()
            //   • developer_setting_id → from foreignId()->constrained()
            //
            // Explicit names: prevent 64-char overflow AND prevent the
            // "Duplicate key name ..._super_admin_id_status_index" 1061 error.
            // -----------------------------------------------------------------
            $table->index('status', 'abr_status_index');
            $table->index('payment_status', 'abr_payment_status_index');
            $table->index('due_date', 'abr_due_date_index');
            $table->index('category', 'abr_category_index');
            $table->index(['super_admin_id', 'status'], 'abr_admin_status_index');
            $table->index(['developer_setting_id', 'created_at'], 'abr_dev_created_index');
            $table->index('deleted_at', 'abr_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_billing_records');
    }
};