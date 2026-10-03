<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // ✅ RELATIONSHIPS
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            
            // ✅ PAYMENT DETAILS - UPDATED TO MATCH CODE
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('GHS'); // ✅ ADDED
            $table->string('payment_provider'); // ✅ ADDED: mtn_momo, telecel_cash, etc.
            $table->string('payment_method')->nullable(); // ✅ KEPT FOR BACKWARD COMPATIBILITY
            
            // ✅ TRANSACTION IDS
            $table->string('transaction_id')->unique();
            $table->string('transaction_reference')->nullable(); // ✅ RENAMED/ADDED
            
            // ✅ STATUS - UPDATED TO MATCH CODE
            $table->enum('status', [
                'pending', 
                'processing', // ✅ ADDED
                'completed', 
                'failed', 
                'refunded', 
                'cancelled',
                'partially_refunded'
            ])->default('pending');
            
            // ✅ TIMING
            $table->dateTime('payment_date')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            
            // ✅ CONTACT INFORMATION
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable(); // ✅ ADDED for Paystack
            
            // ✅ NETWORK/PROVIDER INFO
            $table->string('network')->nullable(); // ✅ CHANGED from enum to string
            
            // ✅ METADATA - ✅ ADDED
            $table->json('metadata')->nullable();
            
            // ✅ DESCRIPTION
            $table->text('description')->nullable();
            
            // ✅ GATEWAY RESPONSE
            $table->text('gateway_response')->nullable();
            
            // ✅ FEES
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->storedAs('amount - fee_amount');
            
            // ✅ PAYMENT PURPOSE - KEPT FOR BACKWARD COMPATIBILITY
            $table->enum('payment_type', [
                'rent', 
                'maintenance_fee', 
                'service_charge', 
                'security_deposit', 
                'utility_bill', 
                'penalty_fee', 
                'other'
            ])->default('service_charge')->nullable();
            
            // ✅ AUDIT TRAIL - ✅ ADDED
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            // ✅ VERIFICATION (Optional - kept from original)
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            
            // ✅ SOFT DELETES & TIMESTAMPS
            $table->softDeletes();
            $table->timestamps();
            
            // ✅ INDEXES FOR PERFORMANCE
            $table->index(['landlord_id', 'status']);
            $table->index(['property_id', 'payment_date']);
            $table->index('transaction_id');
            $table->index('transaction_reference');
            $table->index('payment_provider');
            $table->index('status');
            $table->index('payment_date');
            $table->index('created_at');
            $table->index(['status', 'payment_date']);
            $table->index(['landlord_id', 'status', 'payment_date']);
        });

        // ✅ PAYMENT ATTACHMENTS (Optional - kept from original)
        Schema::create('payment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            
            $table->index(['payment_id', 'file_type']);
        });

        // ✅ PAYMENT REVERSALS (Optional - kept from original)
        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->decimal('reversal_amount', 12, 2);
            $table->enum('reversal_type', ['full_refund', 'partial_refund', 'chargeback', 'adjustment']);
            $table->text('reason');
            $table->foreignId('initiated_by')->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->text('gateway_response')->nullable();
            $table->string('reversal_reference')->nullable();
            $table->timestamps();
            
            $table->index(['payment_id', 'status']);
            $table->index('reversal_reference');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_reversals');
        Schema::dropIfExists('payment_attachments');
        Schema::dropIfExists('payments');
    }
};