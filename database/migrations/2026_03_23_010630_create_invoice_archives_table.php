<?php
// database/migrations/xxxx_xx_xx_create_invoice_archives_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceArchivesTable extends Migration
{
    public function up()
    {
        Schema::create('invoice_archives', function (Blueprint $table) {
            $table->id();
            
            // Original invoice reference
            $table->unsignedBigInteger('original_invoice_id')->nullable();
            $table->string('invoice_number')->nullable();
            
            // Property and landlord info
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('property_name')->nullable();
            $table->unsignedBigInteger('landlord_id')->nullable();
            $table->string('landlord_name')->nullable();
            
            // Invoice details
            $table->string('period', 7)->nullable();
            $table->string('month_name')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status')->nullable();
            
            // Payment info
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->date('payment_date')->nullable();
            
            // Bulk payment info
            $table->boolean('is_bulk_payment')->default(false);
            $table->unsignedBigInteger('bulk_payment_id')->nullable();
            $table->json('covers_periods')->nullable();
            $table->string('bulk_coverage_start', 7)->nullable();
            $table->string('bulk_coverage_end', 7)->nullable();
            
            // Content
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Original timestamps
            $table->timestamp('original_created_at')->nullable();
            $table->unsignedBigInteger('original_created_by')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->unsignedBigInteger('original_updated_by')->nullable();
            
            // Deletion info
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('deleted_by_name')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->string('deletion_ip')->nullable();
            $table->text('deletion_user_agent')->nullable();
            $table->string('archive_type')->default('manual'); // manual, year_end, post_payment
            
            // System settings at deletion
            $table->integer('grace_period_days')->nullable();
            $table->decimal('late_payment_percentage', 5, 2)->nullable();
            $table->decimal('fixed_penalty_amount', 12, 2)->nullable();
            
            // Indexes
            $table->index('original_invoice_id');
            $table->index('property_id');
            $table->index('landlord_id');
            $table->index('period');
            $table->index('status');
            $table->index('deleted_at');
            $table->index('archive_type');
            $table->index('is_bulk_payment');
            
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('invoice_archives');
    }
}