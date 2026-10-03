<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminBillingRecordsTable extends Migration
{
    public function up()
    {
        Schema::create('admin_billing_records', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('developer_setting_id')
                  ->constrained('developer_settings')
                  ->onDelete('cascade');
            $table->foreignId('super_admin_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            // Invoice details
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->text('description');
            $table->date('due_date');
            
            // Status
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('category')->default('other');
            
            // Payment details
            $table->decimal('amount_received', 10, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->text('payment_notes')->nullable();
            
            // Payment proof/attachments
            $table->string('receipt_path')->nullable();
            $table->string('invoice_path')->nullable();
            $table->json('attachments')->nullable();
            
            // Notification tracking (from the job)
            $table->json('metadata')->nullable();
            $table->timestamp('last_notification_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->integer('notification_count')->default(0);
            
            // Dates
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('invoice_number');
            $table->index('status');
            $table->index('payment_status');
            $table->index('due_date');
            $table->index(['super_admin_id', 'status']);
            $table->index(['developer_setting_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_billing_records');
    }
}