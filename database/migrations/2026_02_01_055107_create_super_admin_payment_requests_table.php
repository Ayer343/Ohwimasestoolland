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
        Schema::create('super_admin_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_setting_id')->constrained('developer_settings')->onDelete('cascade');
            $table->foreignId('super_admin_id')->constrained('users')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->enum('category', ['hosting', 'maintenance', 'upgrade', 'emergency', 'other'])->default('other');
            $table->text('description');
            $table->date('due_date');
            $table->enum('payment_method', ['bank_transfer', 'mobile_money', 'cash'])->default('bank_transfer');
            $table->enum('status', ['pending', 'completed', 'partial', 'overdue', 'cancelled'])->default('pending');
            $table->decimal('received_amount', 10, 2)->nullable();
            $table->date('received_date')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->integer('reminder_count')->default(0);
            $table->boolean('overdue_notification_sent')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('invoice_number');
            $table->index('status');
            $table->index('due_date');
            $table->index('category');
            $table->index(['developer_setting_id', 'status']);
            $table->index(['super_admin_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_payment_requests');
    }
};