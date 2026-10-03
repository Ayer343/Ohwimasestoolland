<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ==================== CONSTRUCTION CONTRACTS TABLE ====================
        Schema::create('construction_contracts', function (Blueprint $table) {
            $table->id();
            
            // Contract Reference
            $table->string('contract_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Landlord & Property
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            
            // Contractor Details
            $table->enum('contractor_type', ['company', 'individual'])->default('company');
            $table->string('contractor_name');
            $table->string('contractor_phone');
            $table->string('contractor_email')->nullable();
            $table->string('contractor_address')->nullable();
            
            // Company Specific Fields
            $table->string('company_registration_number')->nullable();
            $table->string('company_tin')->nullable();
            
            // Contract Details
            $table->decimal('contract_amount', 15, 2)->nullable();
            $table->date('contract_start_date');
            $table->date('contract_end_date')->nullable();
            $table->date('estimated_completion_date');
            
            // Contract Documents
            $table->json('contract_documents')->nullable();
            
            // Work Details
            $table->json('work_scope')->nullable(); // JSON array of work items
            $table->json('materials_required')->nullable();
            
            // Status Tracking
            $table->enum('status', [
                'draft', 'pending_approval', 'approved', 
                'in_progress', 'completed', 'cancelled', 
                'on_hold', 'under_review'
            ])->default('draft');
            
            // Admin Tracking
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Notifications
            $table->boolean('admin_notified')->default(false);
            $table->timestamp('admin_notified_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('contract_number');
            $table->index('contractor_name');
            $table->index('status');
            $table->index(['landlord_id', 'property_id']);
            $table->index(['contractor_type', 'contractor_name']);
            $table->index('contract_start_date');
            $table->index('estimated_completion_date');
            $table->index(['status', 'approved_at']);
        });

        // ==================== CONSTRUCTION MILESTONES TABLE ====================
        Schema::create('construction_milestones', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('contract_id')->constrained('construction_contracts')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('milestone_amount', 15, 2)->nullable();
            $table->date('target_date');
            $table->date('completed_date')->nullable();
            
            $table->enum('status', ['pending', 'in_progress', 'completed', 'delayed'])->default('pending');
            
            $table->json('documents')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['contract_id', 'status']);
            $table->index('target_date');
        });

        // ==================== CONSTRUCTION ACTIVITY LOGS TABLE ====================
        Schema::create('construction_activity_logs', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('contract_id')->constrained('construction_contracts')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('action');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestamps();
            
            $table->index(['contract_id', 'action']);
            $table->index('created_at');
        });

        // ==================== CONSTRUCTION NOTIFICATIONS TABLE ====================
        Schema::create('construction_notifications', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('contract_id')->constrained('construction_contracts')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('type'); // admin_notification, landlord_notification, contractor_notification
            $table->string('title');
            $table->text('message');
            $table->json('channels')->nullable(); // ['email', 'sms', 'in_app']
            
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['contract_id', 'type']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_notifications');
        Schema::dropIfExists('construction_activity_logs');
        Schema::dropIfExists('construction_milestones');
        // REMOVED: Schema::dropIfExists('worker_skills');
        // REMOVED: Schema::dropIfExists('construction_workers');
        Schema::dropIfExists('construction_contracts');
    }
};