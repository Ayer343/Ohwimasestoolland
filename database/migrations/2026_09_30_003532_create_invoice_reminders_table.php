<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoice reminders.
     *
     * One row per (invoice, days_before_due) pair. The scheduler dispatches
     * a SendPaymentReminderJob for each row where scheduled_for <= today
     * and status = 'pending'.
     *
     * Design notes:
     *  - `channels` is a comma-separated list ('email', 'sms', 'email,sms')
     *    so a single row can fan out to multiple delivery services.
     *  - `delivery_results` is a JSON blob per channel:
     *      {"email":{"success":true,"id":"..."},"sms":{"success":false,"error":"..."}}
     *    Useful for auditing why a reminder was marked failed.
     *  - A unique constraint on (invoice_id, days_before_due) makes
     *    scheduling idempotent — re-running the invoice generator cannot
     *    double-book the same reminder.
     *  - `status` lifecycle: pending → sent | failed | skipped
     */
    public function up(): void
    {
        if (Schema::hasTable('invoice_reminders')) {
            return;
        }

        Schema::create('invoice_reminders', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('invoice_id')->index();
            $table->unsignedBigInteger('developer_setting_id')->index();
            $table->unsignedBigInteger('super_admin_id')->index();

            // Schedule
            $table->unsignedSmallInteger('days_before_due'); // 7, 3, 1, etc.
            $table->date('scheduled_for')->index();          // due_date − days_before_due

            // Delivery
            $table->string('channels', 100)->default('email'); // "email" | "sms" | "email,sms"

            // Status
            $table->string('status', 20)->default('pending')->index(); // pending|sent|failed|skipped
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Audit — per-channel result snapshot
            $table->json('delivery_results')->nullable();

            $table->timestamps();

            // Idempotency: one reminder per (invoice, days_before_due).
            $table->unique(
                ['invoice_id', 'days_before_due'],
                'invoice_reminders_invoice_days_unique'
            );

            // Convenience index for the scheduler query.
            $table->index(
                ['status', 'scheduled_for'],
                'invoice_reminders_status_scheduled_idx'
            );

            // Optional foreign key constraints — uncomment if the referenced
            // tables use unsigned bigint primary keys and you want strict
            // referential integrity. Using ON DELETE CASCADE keeps reminder
            // rows from becoming orphans when an invoice is deleted.
            //
            // $table->foreign('invoice_id')
            //       ->references('id')->on('billing_invoices')
            //       ->onDelete('cascade');
            //
            // $table->foreign('developer_setting_id')
            //       ->references('id')->on('developer_settings')
            //       ->onDelete('cascade');
            //
            // $table->foreign('super_admin_id')
            //       ->references('id')->on('users')
            //       ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');
    }
};