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
        Schema::create('tenant_payments', function (Blueprint $table) {
            $table->id();
            
            // Transaction identifiers
            $table->string('transaction_id', 100)->unique();
            $table->string('transaction_reference', 100)->nullable();
            
            // Foreign keys
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('property_unit_id')->nullable();
            
            // Payment details
            $table->string('payment_provider', 50); // mtn_momo, telecel_cash, airteltigo_cash, paystack
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('GHS');
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, cancelled, refunded
            
            // Customer contact details
            $table->string('phone_number', 20)->nullable();
            $table->string('email', 100)->nullable();
            
            // Payment completion details
            $table->dateTime('payment_date')->nullable();
            $table->string('payment_method', 50)->nullable(); // mobile_money, card, bank_transfer
            
            // Description and notes
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Metadata for provider-specific data
            $table->json('metadata')->nullable();
            
            // Audit trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('tenant_id');
            $table->index('invoice_id');
            $table->index('property_unit_id');
            $table->index('status');
            $table->index('payment_provider');
            $table->index('payment_date');
            $table->index('created_at');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            
            // Foreign key constraints
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('tenant_invoices')
                  ->onDelete('set null');
                  
            $table->foreign('property_unit_id')
                  ->references('id')
                  ->on('property_units')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
    }
};