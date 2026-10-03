<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('emergency_modes', function (Blueprint $table) {
            $table->id();
            
            // Emergency mode details
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->text('reason')->nullable(); // Why emergency mode was activated
            $table->string('reference_id')->unique(); // EM_YYYYMMDD_HHMMSS format
            
            // Status and activation
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('inactive'); // inactive, activating, active, deactivating, inactive
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            
            // Affected components
            $table->json('affected_modules')->nullable(); // Which modules are affected
            $table->json('restricted_features')->nullable(); // Features to disable
            $table->json('allowed_operations')->nullable(); // Operations allowed during emergency
            
            // Activation controls
            $table->timestamp('activated_at')->nullable();
            $table->integer('activated_by')->nullable(); // User who activated
            $table->timestamp('scheduled_start')->nullable(); // For scheduled emergencies
            $table->timestamp('scheduled_end')->nullable(); // Auto-deactivation time
            
            // Deactivation
            $table->timestamp('deactivated_at')->nullable();
            $table->integer('deactivated_by')->nullable(); // User who deactivated
            $table->text('deactivation_reason')->nullable();
            
            // Duration tracking
            $table->integer('duration_minutes')->nullable(); // Total active duration
            $table->timestamp('last_extended_at')->nullable();
            $table->integer('extensions_count')->default(0);
            
            // Impact tracking
            $table->integer('affected_users_count')->default(0);
            $table->json('impact_metrics')->nullable(); // Store impact data
            
            // Notification settings
            $table->boolean('notify_users')->default(true);
            $table->json('notification_channels')->nullable(); // email, sms, whatsapp, in-app
            $table->timestamp('last_notified_at')->nullable();
            
            // Recovery settings
            $table->boolean('auto_recovery')->default(false);
            $table->json('recovery_checks')->nullable(); // Checks to perform before deactivation
            $table->timestamp('recovery_started_at')->nullable();
            $table->timestamp('recovery_completed_at')->nullable();
            
            // Settings and overrides
            $table->json('settings')->nullable();
            $table->json('environment_overrides')->nullable(); // Temporary env overrides
            $table->json('feature_flags')->nullable(); // Feature flags during emergency
            
            // Audit trail
            $table->json('activation_log')->nullable(); // Log of activation steps
            $table->json('deactivation_log')->nullable(); // Log of deactivation steps
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['is_active', 'status']);
            $table->index('severity_level');
            $table->index('activated_at');
            $table->index('reference_id');
            $table->index('created_at');
        });
        
        // Create emergency mode logs table
        Schema::create('emergency_mode_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_mode_id')->constrained()->onDelete('cascade');
            $table->string('action'); // activated, deactivated, extended, modified, notified
            $table->text('details')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('performed_by')->nullable(); // User ID
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['emergency_mode_id', 'action']);
            $table->index('created_at');
        });
        
        // Create emergency mode affected users table (for detailed tracking)
        Schema::create('emergency_affected_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_mode_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('user_type');
            $table->json('affected_features')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->json('notification_response')->nullable();
            $table->timestamps();
            
            $table->unique(['emergency_mode_id', 'user_id']);
            $table->index(['emergency_mode_id', 'user_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('emergency_affected_users');
        Schema::dropIfExists('emergency_mode_logs');
        Schema::dropIfExists('emergency_modes');
    }
};