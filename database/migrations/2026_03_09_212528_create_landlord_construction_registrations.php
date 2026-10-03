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
        Schema::create('landlord_construction_registrations', function (Blueprint $table) {
            $table->id();
            
            // ========== DUPLICATE PREVENTION FIELDS ==========
            $table->string('submission_hash', 64)->nullable()->unique();
            $table->timestamp('duplicate_check_at')->nullable();
            
            // Registration Type & Purpose
            $table->enum('registration_type', ['construction', 'property_capture'])->default('construction');
            $table->enum('purpose', ['construction', 'permanent_registration', 'both'])->default('construction');
            
            // Landlord Information
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('primary_phone');
            $table->json('additional_phones')->nullable();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Land/Plot Information (Common Fields)
            $table->string('property_name');
            $table->string('plot_number');
            $table->string('street_name');
            $table->string('digital_address')->nullable();
            $table->text('land_description')->nullable();
            $table->string('land_ownership_document')->nullable();
            
            // Zone and Section (Admin Only - Filled upon approval)
            $table->string('zone')->nullable();
            $table->string('section')->nullable();
            
            // Construction-Specific Fields
            $table->string('property_type')->nullable(); // residential, apartment, commercial, mixed, other
            $table->string('custom_property_type')->nullable();
            $table->string('property_status')->nullable(); // under_construction, active, vacant, inactive
            $table->integer('estimated_bedrooms')->nullable();
            $table->boolean('has_plans')->default(false);
            $table->date('estimated_completion')->nullable();
            $table->json('construction_documents')->nullable();
            
            // Property Capture-Specific Fields
            $table->string('existing_property_type')->nullable(); // residential, apartment, commercial, mixed, other
            $table->string('existing_custom_property_type')->nullable();
            $table->string('existing_property_status')->nullable(); // active, inactive, vacant, under_maintenance
            $table->integer('existing_bedrooms')->nullable();
            $table->integer('existing_bathrooms')->nullable();
            $table->integer('year_built')->nullable();
            $table->json('property_photos')->nullable();
            $table->json('property_documents')->nullable();
            
            // Tenant Information
            $table->boolean('has_tenants')->default(false);
            $table->integer('tenant_count')->nullable();
            $table->json('tenant_data')->nullable();
            
            // Status & Tracking
            $table->string('status')->default('pending'); // pending, in_review, approved, rejected, needs_info, cancelled
            $table->string('access_token')->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('info_requested')->nullable();
            
            // Assignment Tracking
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            
            // Archive Fields
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->integer('archive_year')->nullable();
            $table->string('archive_reason')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Payment Tracking (for future use)
            $table->string('payment_status')->nullable()->default('pending'); // pending, paid, failed
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Audit Fields
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // ========== INDEXES FOR PERFORMANCE ==========
            // Duplicate Prevention Indexes
            $table->index('submission_hash');
            $table->index('duplicate_check_at');
            
            // Status & Type Indexes
            $table->index('status');
            $table->index('registration_type');
            $table->index('purpose');
            $table->index('zone');
            $table->index('section');
            $table->index('property_type');
            $table->index('existing_property_type');
            $table->index('has_tenants');
            $table->index('assigned_to');
            $table->index('landlord_id');
            $table->index('created_at');
            $table->index('submitted_at');
            $table->index('is_archived');
            $table->index('archive_year');
            
            // Composite Indexes
            $table->index(['status', 'assigned_to']);
            $table->index(['zone', 'section']);
            $table->index(['primary_phone', 'plot_number', 'property_name'], 'duplicate_check_idx');
            $table->index(['status', 'created_at'], 'status_created_idx');
            $table->index(['registration_type', 'status'], 'type_status_idx');
            
            // Unique Constraints
            $table->unique(['primary_phone', 'plot_number', 'property_name', 'registration_type'], 'unique_registration_per_landlord_plot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_construction_registrations');
    }
};