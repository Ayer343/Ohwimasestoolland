<?php
// database/migrations/2026_03_19_144522_create_tenant_invoices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenant_invoices', function (Blueprint $table) {
            $table->id();
            
            // Tenant relationship
            $table->foreignId('tenant_id')
                ->constrained('users')
                ->onDelete('cascade');
            
            // Property relationship (temporary, will be updated later)
            $table->foreignId('property_id')
                ->constrained('properties')
                ->onDelete('cascade');
            
            // Invoice details
            $table->string('invoice_number')->unique();
            $table->string('period', 7); // YYYY-MM format
            $table->date('issue_date');
            $table->date('due_date');
            
            // Financial fields
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            
            // Status
            $table->enum('status', ['pending', 'paid', 'overdue', 'partial', 'cancelled'])
                ->default('pending');
            
            // Payment details
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->date('payment_date')->nullable();
            
            // Penalty tracking
            $table->integer('penalty_days')->default(0);
            $table->text('penalty_reason')->nullable();
            
            // Description
            $table->text('notes')->nullable();
            
            // Reminder tracking
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->integer('reminder_count')->default(0);
            
            // Bulk payment fields
            $table->boolean('is_bulk_payment')->default(false);
            $table->json('covers_periods')->nullable();
            $table->string('bulk_payment_reference')->nullable();
            $table->foreignId('bulk_parent_id')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('tenant_id');
            $table->index('property_id');
            $table->index('invoice_number');
            $table->index('period');
            $table->index('status');
            $table->index('due_date');
            $table->index(['status', 'due_date']);
            $table->index(['period', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_invoices');
    }
};