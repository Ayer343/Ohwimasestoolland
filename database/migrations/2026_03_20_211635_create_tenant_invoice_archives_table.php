<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantInvoiceArchivesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tenant_invoice_archives', function (Blueprint $table) {
            $table->id();
            
            // === Original Invoice Data ===
            $table->unsignedBigInteger('original_invoice_id')->comment('Original invoice ID from tenant_invoices table');
            $table->string('invoice_number', 50)->comment('Invoice number');
            $table->unsignedBigInteger('tenant_id')->comment('Tenant ID');
            $table->string('tenant_name', 255)->comment('Tenant name at time of deletion');
            $table->unsignedBigInteger('property_unit_id')->nullable()->comment('Property unit ID');
            $table->string('period', 7)->comment('Invoice period (Y-m)');
            $table->date('due_date')->comment('Due date');
            $table->decimal('community_dues', 10, 2)->comment('Community dues amount');
            $table->decimal('additional_charges', 10, 2)->default(0)->comment('Additional charges');
            $table->decimal('total_amount', 10, 2)->comment('Total amount');
            $table->decimal('paid_amount', 10, 2)->default(0)->comment('Amount paid');
            $table->decimal('balance', 10, 2)->default(0)->comment('Remaining balance');
            $table->string('status', 20)->comment('Invoice status (pending/paid/overdue/cancelled)');
            $table->string('payment_method', 50)->nullable()->comment('Payment method used');
            $table->string('payment_reference', 100)->nullable()->comment('Payment reference');
            $table->date('payment_date')->nullable()->comment('Date of payment');
            $table->decimal('penalty_amount', 10, 2)->default(0)->comment('Penalty amount applied');
            $table->timestamp('penalty_applied_at')->nullable()->comment('When penalty was applied');
            $table->integer('grace_period_days')->default(7)->comment('Grace period days at time of deletion');
            $table->string('calculation_method', 50)->nullable()->comment('Calculation method used');
            $table->json('calculation_details')->nullable()->comment('Calculation breakdown');
            $table->text('description')->nullable()->comment('Invoice description');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            
            // === Deletion Audit Trail ===
            $table->dateTime('original_created_at')->comment('Original creation timestamp');
            $table->unsignedBigInteger('original_created_by')->nullable()->comment('Who created the original invoice');
            $table->dateTime('deleted_at')->comment('When the invoice was deleted');
            $table->unsignedBigInteger('deleted_by')->comment('Who deleted the invoice');
            $table->string('deleted_by_name', 255)->comment('Name of person who deleted');
            $table->text('deletion_reason')->nullable()->comment('Reason for deletion');
            $table->string('deletion_ip', 45)->nullable()->comment('IP address at deletion time');
            $table->text('deletion_user_agent')->nullable()->comment('User agent at deletion time');
            
            // === Laravel Timestamps ===
            $table->timestamps(); // created_at, updated_at
            
            // === Indexes for Performance ===
            $table->index('original_invoice_id');
            $table->index('invoice_number');
            $table->index('tenant_id');
            $table->index('period');
            $table->index('status');
            $table->index('deleted_at');
            $table->index(['tenant_id', 'period']);
            $table->index(['deleted_at', 'original_invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('tenant_invoice_archives');
    }
}