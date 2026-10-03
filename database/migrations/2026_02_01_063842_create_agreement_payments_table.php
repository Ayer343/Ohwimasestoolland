<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_billing_record_id')->constrained()->onDelete('cascade');
            $table->decimal('amount_paid', 15, 2);
            $table->string('currency', 3)->default('GHS');
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->text('payment_notes')->nullable();
            $table->string('status')->default('pending_confirmation'); // pending_confirmation, confirmed, cancelled
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('confirmation_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['status', 'payment_date']);
            $table->index('transaction_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_payments');
    }
};