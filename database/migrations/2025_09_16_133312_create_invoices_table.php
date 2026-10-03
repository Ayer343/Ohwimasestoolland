<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            
            // ✅ FOREIGN KEY: Property relationship
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            
            // ✅ AMOUNT FIELDS
            $table->decimal('amount', 12, 2);
            $table->decimal('penalty_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->storedAs('amount + penalty_amount');
            
            // ✅ PERIOD & DATES
            $table->string('period'); // e.g., '2024-01' OR 'bulk-2024-01-15'
            $table->date('due_date');
            $table->dateTime('penalty_applied_date')->nullable();
            
            // ✅ STATUS - UPDATED: Added 'processing' status
            $table->enum('status', [
                'pending', 
                'processing', // ✅ ADDED FOR PAYMENT FLOW
                'paid', 
                'overdue', 
                'cancelled', 
                'partial'
            ])->default('pending');
            
            // ✅ PAYMENT INFORMATION - UPDATED
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->dateTime('payment_date')->nullable();
            
            // ✅ BULK PAYMENT TRACKING
            $table->string('bulk_payment_reference')->nullable();
            $table->foreignId('bulk_payment_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->boolean('is_bulk_payment')->default(false);
            $table->integer('bulk_months')->nullable();
            
            // ✅ DESCRIPTION & NOTES
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // ✅ METADATA FOR FLEXIBILITY
            $table->json('metadata')->nullable();
            
            // ✅ AUDIT TRAIL
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            // ✅ SOFT DELETES & TIMESTAMPS
            $table->softDeletes();
            $table->timestamps();
            
            // ✅ INDEXES FOR PERFORMANCE
            $table->index('property_id');
            $table->index('period');
            $table->index('status');
            $table->index('due_date');
            $table->index('payment_id');
            $table->index('bulk_payment_reference');
            $table->index('bulk_payment_id');
            $table->index('is_bulk_payment');
            $table->index(['property_id', 'period']);
            
            // ✅ UNIQUE CONSTRAINT
            $table->unique(['property_id', 'period', 'is_bulk_payment'], 'unique_property_period_bulk');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};