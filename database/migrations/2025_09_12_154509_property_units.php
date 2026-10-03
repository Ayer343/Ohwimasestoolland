<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_units', function (Blueprint $table) {
            $table->id();
            
            // Regular foreign keys (these tables should exist already)
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('tenant_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('tenant_requested_by')->nullable()->constrained('users')->onDelete('set null');
            
            // ⚠️ IMPORTANT: NO foreign key to rental_agreements here!
            // Just create the column as a regular unsigned integer
            $table->unsignedBigInteger('current_lease_id')->nullable();
            
            // Rest of your columns...
            $table->string('unit_number', 50);
            $table->string('unit_name', 255)->nullable();
            $table->string('unit_type', 50);
            $table->text('description')->nullable();
            $table->decimal('floor_area', 8, 2)->nullable();
            $table->integer('bedrooms')->nullable()->default(0);
            $table->integer('bathrooms')->nullable()->default(0);
            $table->integer('living_rooms')->nullable()->default(0);
            $table->integer('kitchens')->nullable()->default(0);
            $table->json('amenities')->nullable();
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('security_deposit', 10, 2)->nullable();
            $table->decimal('current_rent_amount', 10, 2)->nullable();
            $table->boolean('is_furnished')->default(false);
            
            // ========== TENANT STATUS FIELDS (place BEFORE new columns that reference them) ==========
            $table->enum('tenant_status', ['pending_approval', 'approved', 'rejected', 'vacated', 'terminated'])->nullable()->default('pending_approval');
            $table->text('tenant_approval_notes')->nullable();
            $table->json('tenant_documents')->nullable();
            
            // ========== NEW/MODIFIED COLUMNS ==========
            
            // Tenant type (new or existing) - now properly placed after tenant_status
            $table->enum('tenant_type', ['new', 'existing'])->nullable();
            
            // Tenant details for new tenants
            $table->json('tenant_details')->nullable();
            
            // Proposed rent during assignment
            $table->decimal('proposed_rent', 10, 2)->nullable();
            
            // Resubmission fields
            $table->boolean('tenant_resubmission_allowed')->default(false);
            $table->text('tenant_resubmission_notes')->nullable();
            
            // Invitation channels
            $table->json('invitation_channels')->nullable();
            
            // Property condition and vacate details
            $table->enum('property_condition', ['excellent', 'good', 'fair', 'poor'])->nullable();
            $table->boolean('cleaning_required')->default(false);
            $table->text('damages_noted')->nullable();
            $table->text('tenant_vacate_reason')->nullable();
            
            // Termination details
            $table->json('termination_details')->nullable();
            
            // ========== EXISTING COLUMNS (keeping but modifying) ==========
            
            $table->date('tenant_move_in_date')->nullable();
            $table->date('tenant_move_out_date')->nullable();
            $table->timestamp('tenant_requested_at')->nullable();
            $table->timestamp('tenant_approved_at')->nullable();
            $table->date('lease_start_date')->nullable();
            $table->date('lease_end_date')->nullable();
            
            // Modified status to include 'reserved'
            $table->enum('status', ['available', 'reserved', 'occupied', 'under_maintenance', 'unavailable'])->default('available');
            
            $table->boolean('is_available')->default(true);
            $table->date('available_from')->nullable();
            $table->timestamp('last_maintenance_date')->nullable();
            
            // ========== ADDED COLUMNS FOR MAINTENANCE HISTORY ==========
            $table->json('maintenance_history')->nullable();
            
            // ========== ADDED COLUMNS FOR ARCHIVE SYSTEM ==========
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->onDelete('set null');
            
            // ========== ADDED COLUMNS FOR DEPOSIT REFUND ==========
            $table->decimal('security_deposit_refunded', 10, 2)->nullable();
            $table->text('security_deposit_deduction_reason')->nullable();
            
            // ========== SOFT DELETES AND TIMESTAMPS ==========
            $table->softDeletes();
            $table->timestamps();

            // ========== INDEXES ==========
            $table->index(['property_id', 'status']);
            $table->index(['property_id', 'is_available']);
            $table->index(['property_id', 'unit_number']);
            $table->index('unit_type');
            $table->index('status');
            $table->index('tenant_status');
            $table->index('tenant_type');
            $table->index('is_available');
            $table->index('monthly_rent');
            $table->index('available_from');
            $table->index(['created_at']);
            $table->index(['updated_at']);
            $table->index(['deleted_at']);
            $table->index('tenant_id');
            $table->index('tenant_requested_by');
            $table->index('approved_by');
            $table->index('tenant_move_in_date');
            $table->index('tenant_move_out_date');
            $table->index('current_lease_id');
            $table->index('lease_start_date');
            $table->index('lease_end_date');
            $table->index(['unit_type', 'status']);
            $table->index(['monthly_rent', 'status']);
            $table->index(['bedrooms', 'bathrooms', 'status']);
            $table->index(['is_available', 'available_from']);
            $table->index(['tenant_status', 'property_id']);
            $table->index(['status', 'tenant_status']);
            $table->index(['property_id', 'tenant_status', 'status']);
            $table->index('proposed_rent');
            $table->index('is_archived');
            $table->index('archived_at');
            $table->index('archived_by');
            $table->index(['is_archived', 'status']);
            $table->index(['tenant_status', 'tenant_type']);
            $table->index(['property_condition', 'status']);
            $table->index(['last_maintenance_date', 'status']);
            
            // Unique constraint
            $table->unique(['property_id', 'unit_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_units');
    }
};