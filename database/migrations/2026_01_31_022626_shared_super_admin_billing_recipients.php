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
        Schema::create('shared_billing_payments', function (Blueprint $table) {
            $table->id();
            
            // FIX 1: Use unsignedBigInteger with separate foreign() for better control
            $table->unsignedBigInteger('shared_billing_id')
                ->comment('Reference to the shared billing');
            
            // FIX 2: Temporarily make nullable if table doesn't exist yet
            $table->unsignedBigInteger('super_admin_id')
                ->comment('Super Admin who made the payment');
            
            $table->unsignedBigInteger('developer_id')
                ->nullable()
                ->comment('Developer who received the payment');
            
            // Payment details
            $table->decimal('amount', 12, 2)->comment('Amount paid');
            $table->string('currency', 3)->default('GHS')->comment('Currency of payment');
            $table->enum('payment_method', ['bank_transfer', 'mobile_money', 'cash', 'other'])
                ->comment('Method used for payment');
            
            $table->string('transaction_reference', 100)->nullable()->comment('Bank/MOMO reference number');
            $table->string('receipt_number', 100)->nullable()->comment('Receipt number if any');
            
            // Payment information
            $table->dateTime('payment_date')->comment('Date payment was made');
            $table->dateTime('received_date')->comment('Date payment was received/recorded');
            
            // Payment source details
            $table->string('bank_name', 100)->nullable()->comment('For bank transfers');
            $table->string('account_number', 50)->nullable()->comment('For bank transfers');
            $table->string('account_name', 200)->nullable()->comment('For bank transfers');
            
            $table->string('mobile_money_provider', 50)->nullable()->comment('MTN, Vodafone, etc.');
            $table->string('mobile_number', 20)->nullable()->comment('For mobile money');
            $table->string('mobile_money_name', 200)->nullable()->comment('Name on mobile money');
            
            $table->string('cash_location', 200)->nullable()->comment('Where cash was paid');
            $table->string('cash_contact_person', 200)->nullable()->comment('Who received cash');
            
            // Verification
            $table->boolean('verified')->default(false)->comment('Whether payment was verified');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable()->comment('When payment was verified');
            $table->text('verification_notes')->nullable()->comment('Notes about verification');
            
            // Status
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'disputed'])
                ->default('pending')
                ->comment('Payment status');
            
            // Attachments
            $table->string('receipt_path', 500)->nullable()->comment('Path to receipt scan/photo');
            $table->string('proof_of_payment_path', 500)->nullable()->comment('Path to proof of payment');
            
            // Notes
            $table->text('notes')->nullable()->comment('Additional notes about payment');
            $table->text('admin_notes')->nullable()->comment('Notes from admin/developer');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes (add these BEFORE foreign keys)
            $table->index(['shared_billing_id', 'status']);
            $table->index(['super_admin_id', 'payment_date']);
            $table->index(['developer_id', 'received_date']);
            $table->index('transaction_reference');
            $table->index('receipt_number');
            $table->index('payment_method');
            $table->index('verified');
        });
        
        // FIX 3: Add foreign keys AFTER table creation for better control
        Schema::table('shared_billing_payments', function (Blueprint $table) {
            // Check if referenced table exists first
            if (Schema::hasTable('shared_super_admin_billings')) {
                $table->foreign('shared_billing_id')
                    ->references('id')
                    ->on('shared_super_admin_billings')
                    ->onDelete('cascade');
            }
            
            if (Schema::hasTable('users')) {
                $table->foreign('super_admin_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
                
                $table->foreign('developer_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
                
                $table->foreign('verified_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('shared_billing_payments', function (Blueprint $table) {
            $table->dropForeign(['shared_billing_id']);
            $table->dropForeign(['super_admin_id']);
            $table->dropForeign(['developer_id']);
            $table->dropForeign(['verified_by']);
        });
        
        Schema::dropIfExists('shared_billing_payments');
    }
};