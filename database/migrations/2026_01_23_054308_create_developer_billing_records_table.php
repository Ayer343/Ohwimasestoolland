<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeveloperBillingRecordsTable extends Migration
{
    public function up()
    {
        Schema::create('developer_billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_setting_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Billing Details
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->string('billing_period'); // e.g., "2024-01"
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
            $table->json('billing_items')->nullable(); // Breakdown of charges
            
            // Tax Information
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            
            // Admin Approval
            $table->boolean('admin_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
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
            
            // Indexes
            $table->index('invoice_number');
            $table->index('payment_status');
            $table->index(['billing_period', 'payment_status']);
            $table->index('developer_setting_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('developer_billing_records');
    }
}