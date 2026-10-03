<?php
// File: 2025_09_12_154502_create_rental_agreements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_agreements', function (Blueprint $table) {
            $table->id();
            
            // ⚠️ Create unit_id as unsigned integer first
            $table->unsignedBigInteger('unit_id');
            
            // Other foreign keys
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            
            // Rest of your columns...
            $table->string('agreement_number')->unique()->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('monthly_rent', 12, 2);
            $table->decimal('security_deposit', 12, 2)->default(0);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->integer('grace_period_days')->default(5);
            $table->integer('payment_due_day')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_months');
            $table->boolean('is_renewable')->default(false);
            $table->integer('renewal_notice_days')->nullable();
            $table->decimal('renewal_rent_increase_percent', 5, 2)->nullable();
            $table->enum('status', ['draft', 'pending', 'active', 'expired', 'terminated', 'cancelled', 'completed'])->default('draft');
            $table->boolean('is_auto_renew')->default(false);
            $table->boolean('has_early_termination')->default(false);
            $table->decimal('early_termination_fee', 12, 2)->nullable();
            $table->json('utilities_included')->nullable();
            $table->json('tenant_responsibilities')->nullable();
            $table->json('landlord_responsibilities')->nullable();
            $table->json('special_terms')->nullable();
            $table->json('house_rules')->nullable();
            $table->json('property_condition')->nullable();
            $table->string('agreement_file_path')->nullable();
            $table->string('signed_file_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->json('signatures')->nullable();
            $table->json('witnesses')->nullable();
            $table->json('guarantors')->nullable();
            $table->json('emergency_contacts')->nullable();
            $table->decimal('total_rent_paid', 12, 2)->default(0);
            $table->decimal('total_deposit_held', 12, 2)->default(0);
            $table->date('last_rent_paid_date')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->foreignId('terminated_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('agreement_number');
            $table->index('tenant_id');
            $table->index('landlord_id');
            $table->index('property_id');
            $table->index('unit_id'); // Still index it
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['status', 'end_date']);
            $table->index('created_by');
        });
        
        // Create related tables
        Schema::create('rental_agreement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_agreement_id')->constrained()->onDelete('cascade');
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('change_description');
            $table->foreignId('changed_by')->constrained('users');
            $table->string('change_type');
            $table->timestamps();
            
            $table->index('rental_agreement_id');
            $table->index('changed_by');
        });
        
        Schema::create('rental_agreement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_agreement_id')->constrained()->onDelete('cascade');
            $table->string('document_type');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index('rental_agreement_id');
            $table->index('document_type');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_agreement_documents');
        Schema::dropIfExists('rental_agreement_revisions');
        Schema::dropIfExists('rental_agreements');
    }
};