<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            
            // Maintenance details
            $table->string('title');
            $table->string('reference_id')->unique(); // MT_YYYYMMDD_HHMMSS format
            $table->text('description')->nullable();
            $table->text('technical_details')->nullable(); // Technical notes for developers
            $table->text('user_impact_description')->nullable(); // User-friendly impact description
            
            // Status and timing
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled', 'delayed'])->default('draft');
            $table->enum('impact_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('maintenance_type', ['planned', 'emergency', 'hotfix', 'upgrade', 'security'])->default('planned');
            
            // Scheduling
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->integer('estimated_duration_minutes')->nullable(); // Estimated duration in minutes
            
            // Actual duration tracking
            $table->integer('actual_duration_minutes')->nullable();
            $table->boolean('completed_within_estimate')->default(false);
            $table->timestamp('completion_verified_at')->nullable();
            
            // Affected components
            $table->json('affected_modules')->nullable(); // Which modules are affected
            $table->json('affected_services')->nullable(); // Which services are affected
            $table->json('affected_features')->nullable(); // Specific features affected
            $table->json('allowed_operations')->nullable(); // Operations allowed during maintenance
            
            // Impact and notification
            $table->json('affected_user_types')->nullable(); // User types affected
            $table->integer('estimated_affected_users')->default(0);
            $table->integer('actual_affected_users')->default(0);
            $table->boolean('notify_users')->default(true);
            $table->json('notification_channels')->nullable(); // email, sms, whatsapp, in-app
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            
            // Emergency maintenance details
            $table->boolean('is_emergency')->default(false);
            $table->text('emergency_reason')->nullable();
            $table->foreignId('related_emergency_id')->nullable()->constrained('emergency_modes')->onDelete('set null');
            
            // Rollback and recovery
            $table->boolean('has_rollback_plan')->default(false);
            $table->text('rollback_procedure')->nullable();
            $table->json('rollback_conditions')->nullable();
            $table->timestamp('rollback_initiated_at')->nullable();
            $table->timestamp('rollback_completed_at')->nullable();
            
            // Verification and testing
            $table->json('pre_maintenance_checks')->nullable();
            $table->json('post_maintenance_checks')->nullable();
            $table->boolean('all_checks_passed')->default(false);
            $table->timestamp('checks_completed_at')->nullable();
            
            // Documentation and updates
            $table->json('change_log')->nullable(); // What changes are being made
            $table->json('dependencies')->nullable(); // Dependencies on other systems
            $table->json('team_members')->nullable(); // Team members involved
            $table->json('communication_log')->nullable(); // Communication history
            
            // Performance metrics
            $table->decimal('downtime_minutes', 8, 2)->nullable();
            $table->json('performance_impact')->nullable();
            $table->json('user_feedback')->nullable();
            
            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Additional settings
            $table->json('settings')->nullable();
            $table->json('environment_changes')->nullable(); // Environment changes during maintenance
            $table->json('feature_flags')->nullable(); // Feature flags to enable/disable
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('status');
            $table->index('maintenance_type');
            $table->index('impact_level');
            $table->index('is_emergency');
            $table->index('scheduled_start');
            $table->index('scheduled_end');
            $table->index('reference_id');
            $table->index(['scheduled_start', 'scheduled_end']);
            $table->index(['created_at', 'status']);
        });
        
        // Create maintenance logs table
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->onDelete('cascade');
            $table->string('action'); // created, scheduled, started, completed, cancelled, updated, notified
            $table->text('details')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('performed_by')->nullable(); // User ID
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['maintenance_id', 'action']);
            $table->index('created_at');
        });
        
        // Create maintenance affected users table
        Schema::create('maintenance_affected_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('user_type');
            $table->json('affected_modules')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->json('notification_response')->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->json('feedback')->nullable();
            $table->timestamps();
            
            $table->unique(['maintenance_id', 'user_id']);
            $table->index(['maintenance_id', 'user_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('maintenance_affected_users');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('maintenances');
    }
};