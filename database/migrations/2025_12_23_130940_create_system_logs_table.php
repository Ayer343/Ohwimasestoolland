<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            
            // Log identification
            $table->string('reference_id')->unique(); // LOG_YYYYMMDD_HHMMSS format
            $table->string('log_group')->nullable(); // Group logs by feature/module
            $table->string('log_subgroup')->nullable(); // Subgroup for finer categorization
            
            // Log severity and type
            $table->enum('level', [
                'emergency', 'alert', 'critical', 'error', 'warning', 
                'notice', 'info', 'debug', 'trace'
            ])->default('info');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('channel')->nullable(); // Log channel (laravel, custom, api, etc.)
            $table->string('source')->nullable(); // Source system/module
            $table->string('component')->nullable(); // Component within source
            
            // Log message and context
            $table->text('message');
            $table->string('summary')->nullable(); // Short summary of the log
            $table->json('context')->nullable(); // Structured context data
            $table->json('extra')->nullable(); // Extra metadata
            $table->json('tags')->nullable(); // Tags for categorization
            
            // Error location and trace
            $table->string('file')->nullable(); // File where log occurred
            $table->integer('line')->nullable(); // Line number
            $table->text('trace')->nullable(); // Stack trace (truncated)
            $table->json('trace_data')->nullable(); // Full trace data
            $table->string('exception_class')->nullable(); // Exception class name
            
            // Request information
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->json('request_data')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('response_code')->nullable();
            $table->decimal('response_time_ms', 10, 2)->nullable();
            
            // User and session information
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_type')->nullable(); // User type for filtering
            $table->string('session_id')->nullable();
            $table->json('user_context')->nullable(); // User-specific context
            
            // Impact and resolution tracking
            $table->json('affected_users')->nullable(); // User IDs affected
            $table->integer('affected_users_count')->default(0);
            $table->json('affected_modules')->nullable(); // Modules affected
            $table->json('affected_features')->nullable(); // Features affected
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->json('resolution_metadata')->nullable();
            
            // Related entities
            $table->foreignId('related_maintenance_id')->nullable()->constrained('maintenances')->onDelete('set null');
            $table->foreignId('related_emergency_id')->nullable()->constrained('emergency_modes')->onDelete('set null');
            $table->foreignId('related_entity_id')->nullable(); // Generic related entity
            $table->string('related_entity_type')->nullable(); // Entity type
            
            // Performance and analytics
            $table->integer('occurrence_count')->default(1); // For duplicate errors
            $table->timestamp('first_occurrence_at')->nullable();
            $table->timestamp('last_occurrence_at')->nullable();
            $table->decimal('frequency_per_hour', 10, 2)->nullable(); // Occurrences per hour
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_pattern')->nullable();
            
            // Alert and notification tracking
            $table->boolean('alert_sent')->default(false);
            $table->timestamp('alert_sent_at')->nullable();
            $table->json('alert_recipients')->nullable();
            $table->json('notification_channels')->nullable();
            $table->boolean('requires_human_intervention')->default(false);
            $table->enum('intervention_status', ['pending', 'in_progress', 'completed', 'escalated'])->nullable();
            
            // SLA and priority tracking
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('sla_hours')->nullable(); // SLA in hours
            $table->timestamp('sla_deadline')->nullable();
            $table->boolean('sla_breached')->default(false);
            
            // Analytics and metrics
            $table->json('analytics_data')->nullable(); // Performance analytics
            $table->json('metrics')->nullable(); // Custom metrics
            $table->decimal('performance_impact_score', 5, 2)->nullable();
            $table->decimal('business_impact_score', 5, 2)->nullable();
            
            // Archiving and retention
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->integer('retention_days')->nullable(); // Retention policy
            $table->timestamp('expires_at')->nullable(); // Auto-delete date
            
            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Comprehensive indexes for performance - with SHORTENED names
            $table->index('level', 'idx_level');
            $table->index('severity', 'idx_severity');
            $table->index('priority', 'idx_priority');
            $table->index('source', 'idx_source');
            $table->index('component', 'idx_component');
            $table->index('log_group', 'idx_log_group');
            $table->index('log_subgroup', 'idx_log_subgroup');
            $table->index('user_id', 'idx_user_id');
            $table->index('user_type', 'idx_user_type');
            $table->index('resolved', 'idx_resolved');
            $table->index('archived', 'idx_archived');
            $table->index('requires_human_intervention', 'idx_req_human_int');
            $table->index('alert_sent', 'idx_alert_sent');
            $table->index('sla_breached', 'idx_sla_breached');
            $table->index('is_recurring', 'idx_recurring');
            $table->index('created_at', 'idx_created_at');
            $table->index('updated_at', 'idx_updated_at');
            $table->index('first_occurrence_at', 'idx_first_occurrence');
            $table->index('last_occurrence_at', 'idx_last_occurrence');
            $table->index('resolved_at', 'idx_resolved_at');
            $table->index('archived_at', 'idx_archived_at');
            $table->index('expires_at', 'idx_expires_at');
            $table->index('sla_deadline', 'idx_sla_deadline');
            $table->index('assigned_to', 'idx_assigned_to');
            $table->index('related_maintenance_id', 'idx_rel_maintenance');
            $table->index('related_emergency_id', 'idx_rel_emergency');
            $table->index(['related_entity_id', 'related_entity_type'], 'idx_rel_entity');
            
            // Composite indexes with custom short names
            $table->index(['level', 'created_at'], 'idx_level_created');
            $table->index(['level', 'resolved'], 'idx_level_resolved');
            $table->index(['severity', 'priority'], 'idx_sev_priority');
            $table->index(['source', 'component'], 'idx_source_comp');
            $table->index(['log_group', 'log_subgroup'], 'idx_log_group_sub');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
            $table->index(['resolved', 'created_at'], 'idx_resolved_created');
            $table->index(['archived', 'created_at'], 'idx_archived_created');
            $table->index(['requires_human_intervention', 'intervention_status'], 'idx_human_int_status');
            $table->index(['alert_sent', 'alert_sent_at'], 'idx_alert_sent_at');
            $table->index(['sla_breached', 'sla_deadline'], 'idx_sla_breach_deadline');
            $table->index(['is_recurring', 'last_occurrence_at'], 'idx_recurring_last');
            $table->index('reference_id', 'idx_reference_id');
            
            // Full-text index for searching - FIXED VERSION
            // Remove JSON columns from FULLTEXT indexes - they can't be indexed with FULLTEXT
            $table->fullText(['message', 'summary', 'file'], 'ft_log_content');
            // Note: Cannot create FULLTEXT index on JSON columns or with TEXT columns mixed with other types
            // Removed the problematic fulltext index
        });
        
        // Create system log comments table
        Schema::create('system_log_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_log_id')->constrained()->onDelete('cascade');
            $table->text('comment');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('created_by_name')->nullable();
            $table->string('created_by_type')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('system_log_id', 'idx_comments_log_id');
            $table->index('created_by', 'idx_comments_created_by');
            $table->index('created_at', 'idx_comments_created_at');
            
            // Add fulltext index for comments search
            $table->fullText('comment', 'ft_comments_content');
        });
        
        // Create system log attachments table
        Schema::create('system_log_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_log_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('path');
            $table->integer('size')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index('system_log_id', 'idx_attachments_log_id');
            $table->index('uploaded_by', 'idx_attachments_uploaded_by');
        });
        
        // Create system log analytics table
        Schema::create('system_log_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('analytics_date')->unique();
            $table->integer('total_logs')->default(0);
            $table->integer('error_logs')->default(0);
            $table->integer('critical_logs')->default(0);
            $table->integer('warning_logs')->default(0);
            $table->integer('info_logs')->default(0);
            $table->integer('debug_logs')->default(0);
            $table->integer('resolved_logs')->default(0);
            $table->integer('unresolved_logs')->default(0);
            $table->integer('recurring_logs')->default(0);
            $table->integer('sla_breached_logs')->default(0);
            $table->integer('alerts_sent')->default(0);
            $table->decimal('avg_response_time_ms', 10, 2)->nullable();
            $table->decimal('avg_resolution_time_hours', 10, 2)->nullable();
            $table->json('source_distribution')->nullable();
            $table->json('component_distribution')->nullable();
            $table->json('user_type_distribution')->nullable();
            $table->json('severity_distribution')->nullable();
            $table->json('priority_distribution')->nullable();
            $table->timestamps();
            
            $table->index('analytics_date', 'idx_analytics_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_log_analytics');
        Schema::dropIfExists('system_log_attachments');
        Schema::dropIfExists('system_log_comments');
        Schema::dropIfExists('system_logs');
    }
};