<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registration_plans', function (Blueprint $table) {
            $table->id();
            
            // The admin creating the plan - nullable for set null
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            // The geographical area definition for registration
            $table->string('zone', 100); // e.g., "Zone A", "North Sector"
            $table->string('section', 100)->nullable(); // e.g., "Section 1", "Premium Block"
            
            // Naming convention and sequencing - ENHANCED
            $table->string('naming_pattern', 255); // e.g., "{letter}{number}", "House {letter}", "Unit {number}"
            $table->string('starting_point', 50); // e.g., "A", "1", "A1", "B12"
            $table->string('next_available_name', 50)->nullable(); // Next name in sequence (e.g., "B", "2", "B2")
            $table->enum('sequence_type', ['sequential', 'even_only', 'odd_only'])->default('sequential');
            
            // Agent Assignment Type - NEW
            $table->enum('agent_assignment_type', ['single', 'multiple'])->default('single');
            
            // Global Sequence Implementation - UPDATED
            $table->boolean('is_global_sequence')->default(false); // Whether this plan continues a global sequence
            $table->foreignId('continues_from_plan_id')->nullable()->constrained('registration_plans')->onDelete('set null'); // Plan this continues from
            
            // ✅ ADDED: Missing sequence continuation fields (REMOVED after() calls)
            $table->integer('sequence_continuation_count')->default(0);
            $table->integer('total_sequence_properties')->default(0);
            $table->string('last_used_pattern', 255)->nullable();
            
            // Estimated targets and progress tracking - UPDATED
            $table->integer('estimated_houses')->default(1);
            $table->integer('houses_registered')->default(0);
            $table->integer('properties_count')->default(0); // ADDED: Total properties count for statistics
            
            // Plan status and tracking - UPDATED
            $table->enum('status', ['draft', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('draft');
            
            // ✅ ADDED: Complete invitation tracking fields (REMOVED after() calls)
            $table->enum('invitation_status', ['pending', 'sent', 'failed', 'not_required'])->default('not_required');
            $table->timestamp('invitation_sent_at')->nullable();
            $table->string('invitation_provider', 50)->nullable(); // Which SMS provider was used
            $table->integer('sms_attempts')->default(0); // Number of SMS sending attempts
            $table->integer('whatsapp_attempts')->default(0); // ✅ ADDED: WhatsApp attempts
            $table->integer('email_attempts')->default(0); // ✅ ADDED: Email attempts
            $table->timestamp('last_sms_attempt_at')->nullable(); // Last SMS attempt timestamp
            $table->json('invitation_channels')->nullable(); // ✅ ADDED: Invitation channels
            $table->string('preferred_channel', 50)->nullable(); // ✅ ADDED: Preferred channel
            
            // Planning and execution dates - UPDATED
            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();
            $table->timestamp('started_at')->nullable(); // When plan actually started
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); // When plan was cancelled
            
            // Notes and instructions - UPDATED
            $table->text('instructions')->nullable();
            $table->text('boundaries_description')->nullable();
            $table->text('completion_notes')->nullable(); // Notes upon completion/cancellation
            
            // ✅ NEW: Additional fields for better analytics and management
            $table->string('plan_code')->unique()->nullable(); // Human-readable identifier (RP-ZONE-A-001)
            $table->integer('priority')->default(1); // Plan priority (1-10)
            $table->json('metadata')->nullable(); // Flexible field for additional data
            
            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->onDelete('set null'); // Who deleted the plan

            // ✅ OPTIMIZED: Strategic indexes for better performance
            $table->index(['zone', 'section', 'status'], 'idx_plans_zone_sect_status');
            $table->index(['status', 'registration_end_date'], 'idx_plans_status_end_date');
            $table->index(['created_by', 'created_at'], 'idx_plans_creator_created');
            $table->index(['is_global_sequence', 'naming_pattern', 'status'], 'idx_plans_global_pattern_status');
            $table->index(['agent_assignment_type', 'status', 'created_at'], 'idx_plans_assignment_status_created');
            $table->index(['invitation_status', 'sms_attempts'], 'idx_plans_invite_attempts');
            $table->index(['properties_count', 'houses_registered'], 'idx_plans_prop_house_stats');
            $table->index(['plan_code'], 'idx_plans_code');
            $table->index(['priority', 'status'], 'idx_plans_priority_status');
            
            // ✅ ADDED: Indexes for common query patterns
            $table->index(['deleted_at', 'status'], 'idx_plans_deleted_status');
            $table->index(['registration_start_date', 'registration_end_date'], 'idx_plans_date_range');
            $table->index(['next_available_name', 'naming_pattern'], 'idx_plans_next_name_pattern');
            
            // ✅ ADDED: Indexes for the new fields
            $table->index(['sequence_continuation_count', 'is_global_sequence'], 'idx_plans_sequence_continuation');
            $table->index(['total_sequence_properties'], 'idx_plans_total_sequence_props');
            $table->index(['last_used_pattern'], 'idx_plans_last_used_pattern');
            $table->index(['preferred_channel', 'invitation_status'], 'idx_plans_channel_invite_status');
            
            // ✅ ADDED: Foreign key indexes
            $table->index('continues_from_plan_id', 'idx_plans_continues_from');
        });

        // Create the plan_agent_assignments table for multiple agent support
        Schema::create('plan_agent_assignments', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('plan_id')->constrained('registration_plans')->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            
            // Assignment tracking
            $table->timestamp('assigned_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->timestamp('removed_at')->nullable();
            $table->text('removal_reason')->nullable();
            
            // Performance metrics
            $table->integer('properties_registered')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            
            // ✅ NEW: Enhanced agent management fields
            $table->enum('assignment_type', ['primary', 'secondary', 'backup'])->default('primary');
            $table->decimal('performance_score', 5, 2)->nullable(); // Calculated performance metric
            $table->timestamp('last_invitation_sent_at')->nullable();
            $table->integer('invitation_acceptance_time')->nullable(); // in minutes
            
            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // ✅ UPDATED: Unique constraint to prevent duplicate active assignments
            $table->unique(['plan_id', 'agent_id', 'is_active'], 'unique_plan_agent_active');
            
            // ✅ OPTIMIZED: Strategic indexes for performance
            $table->index(['plan_id', 'is_active', 'deleted_at'], 'idx_assignments_plan_active_deleted');
            $table->index(['agent_id', 'is_active', 'last_activity_at'], 'idx_assignments_agent_active_activity');
            $table->index(['properties_registered', 'assigned_at'], 'idx_assignments_performance_timeline');
            $table->index(['assignment_type', 'is_active'], 'idx_assignments_type_active');
            $table->index(['performance_score', 'last_activity_at'], 'idx_assignments_perf_score_activity');
            
            // ✅ ADDED: Composite indexes for common queries
            $table->index(['plan_id', 'agent_id', 'assigned_by'], 'idx_assignments_plan_agent_assigner');
            $table->index(['is_active', 'assignment_type', 'properties_registered'], 'idx_assignments_active_type_perf');
            $table->index(['assigned_at', 'removed_at'], 'idx_assignments_duration');
            $table->index(['last_invitation_sent_at', 'invitation_acceptance_time'], 'idx_assignments_invitation_stats');
        });

        // Add database-specific constraints and comments
        $this->addDatabaseSpecificFeatures();
    }

    /**
     * Add database-specific features like comments and constraints
     */
    private function addDatabaseSpecificFeatures(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $this->addMySQLFeatures();
        } elseif ($driver === 'pgsql') {
            $this->addPostgreSQLFeatures();
        }
        
        // SQLite doesn't support these features well, so we skip
    }

    /**
     * Add MySQL/MariaDB specific features
     */
    private function addMySQLFeatures(): void
    {
        // Column comments for registration_plans table
        DB::statement("ALTER TABLE registration_plans MODIFY naming_pattern VARCHAR(255) NOT NULL COMMENT 'Property naming pattern with placeholders like {letter}, {number}, or custom patterns'");
        DB::statement("ALTER TABLE registration_plans MODIFY starting_point VARCHAR(50) NOT NULL COMMENT 'Initial starting point for the naming sequence (e.g., A, 1, A1, B5)'");
        DB::statement("ALTER TABLE registration_plans MODIFY next_available_name VARCHAR(50) NULL COMMENT 'Next available property name in the sequence for auto-generation'");
        DB::statement("ALTER TABLE registration_plans MODIFY sequence_type ENUM('sequential', 'even_only', 'odd_only') DEFAULT 'sequential' COMMENT 'Type of numerical sequence: sequential (1,2,3), even_only (2,4,6), or odd_only (1,3,5)'");
        DB::statement("ALTER TABLE registration_plans MODIFY agent_assignment_type ENUM('single', 'multiple') DEFAULT 'single' COMMENT 'Type of agent assignment: single agent or multiple agents per plan'");
        DB::statement("ALTER TABLE registration_plans MODIFY is_global_sequence BOOLEAN DEFAULT FALSE COMMENT 'Whether this plan continues a global naming sequence across zones/sections'");
        DB::statement("ALTER TABLE registration_plans MODIFY continues_from_plan_id BIGINT UNSIGNED NULL COMMENT 'Reference to the previous plan in the global sequence chain'");
        
        // ✅ ADDED: Comments for new sequence continuation fields
        DB::statement("ALTER TABLE registration_plans MODIFY sequence_continuation_count INT DEFAULT 0 COMMENT 'Count of how many times this sequence has been continued from previous plans'");
        DB::statement("ALTER TABLE registration_plans MODIFY total_sequence_properties INT DEFAULT 0 COMMENT 'Total properties across all continuations of this sequence'");
        DB::statement("ALTER TABLE registration_plans MODIFY last_used_pattern VARCHAR(255) NULL COMMENT 'Last used naming pattern for sequence continuation tracking'");
        
        DB::statement("ALTER TABLE registration_plans MODIFY estimated_houses INT DEFAULT 1 COMMENT 'Estimated number of houses to be registered in this plan'");
        DB::statement("ALTER TABLE registration_plans MODIFY houses_registered INT DEFAULT 0 COMMENT 'Actual count of registered houses (calculated from properties relationship)'");
        DB::statement("ALTER TABLE registration_plans MODIFY properties_count INT DEFAULT 0 COMMENT 'Total count of properties associated with this plan for statistics and reporting'");
        DB::statement("ALTER TABLE registration_plans MODIFY status ENUM('draft', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft' COMMENT 'Current status of the registration plan'");
        
        // Complete invitation field comments
        DB::statement("ALTER TABLE registration_plans MODIFY invitation_status ENUM('pending', 'sent', 'failed', 'not_required') DEFAULT 'not_required' COMMENT 'Status of invitation to field agent: pending, sent, failed, or not required'");
        DB::statement("ALTER TABLE registration_plans MODIFY invitation_sent_at TIMESTAMP NULL COMMENT 'Timestamp when invitation was successfully sent to field agent'");
        DB::statement("ALTER TABLE registration_plans MODIFY invitation_provider VARCHAR(50) NULL COMMENT 'Provider used for sending the invitation (arkesel, twilio, africastalking, etc.)'");
        DB::statement("ALTER TABLE registration_plans MODIFY sms_attempts INT DEFAULT 0 COMMENT 'Number of SMS sending attempts made for this invitation'");
        DB::statement("ALTER TABLE registration_plans MODIFY whatsapp_attempts INT DEFAULT 0 COMMENT 'Number of WhatsApp sending attempts made for this invitation'");
        DB::statement("ALTER TABLE registration_plans MODIFY email_attempts INT DEFAULT 0 COMMENT 'Number of email sending attempts made for this invitation'");
        DB::statement("ALTER TABLE registration_plans MODIFY last_sms_attempt_at TIMESTAMP NULL COMMENT 'Timestamp of the last SMS sending attempt'");
        DB::statement("ALTER TABLE registration_plans MODIFY invitation_channels JSON NULL COMMENT 'Communication channels used for invitations (sms, whatsapp, email)'");
        DB::statement("ALTER TABLE registration_plans MODIFY preferred_channel VARCHAR(50) NULL COMMENT 'Preferred communication channel for this plan (sms, whatsapp, email)'");
        
        // New fields comments
        DB::statement("ALTER TABLE registration_plans MODIFY plan_code VARCHAR(255) NULL COMMENT 'Human-readable unique identifier for the registration plan'");
        DB::statement("ALTER TABLE registration_plans MODIFY priority INT DEFAULT 1 COMMENT 'Priority level of the plan (1-10), higher number indicates higher priority'");
        DB::statement("ALTER TABLE registration_plans MODIFY metadata JSON NULL COMMENT 'Flexible JSON field for storing additional plan metadata and configuration'");
        
        DB::statement("ALTER TABLE registration_plans MODIFY registration_start_date DATE NULL COMMENT 'Scheduled start date for registration activities'");
        DB::statement("ALTER TABLE registration_plans MODIFY registration_end_date DATE NULL COMMENT 'Scheduled end date for registration activities'");
        DB::statement("ALTER TABLE registration_plans MODIFY started_at TIMESTAMP NULL COMMENT 'Timestamp when the plan execution actually began (when status changed to in_progress)'");
        DB::statement("ALTER TABLE registration_plans MODIFY completed_at TIMESTAMP NULL COMMENT 'Timestamp when the plan was marked as completed'");
        DB::statement("ALTER TABLE registration_plans MODIFY cancelled_at TIMESTAMP NULL COMMENT 'Timestamp when the plan was cancelled'");
        DB::statement("ALTER TABLE registration_plans MODIFY instructions TEXT NULL COMMENT 'Specific instructions for the assigned field agent'");
        DB::statement("ALTER TABLE registration_plans MODIFY boundaries_description TEXT NULL COMMENT 'Description of geographical boundaries for the registration area'");
        DB::statement("ALTER TABLE registration_plans MODIFY completion_notes TEXT NULL COMMENT 'Notes recorded when plan was completed or cancelled'");
        DB::statement("ALTER TABLE registration_plans MODIFY deleted_by BIGINT UNSIGNED NULL COMMENT 'User who soft-deleted the plan'");
        
        // Table comment for registration_plans
        DB::statement("ALTER TABLE registration_plans COMMENT = 'Registration plans for organizing property registration by geographical zones and sections with configurable naming sequences, multiple agent assignments, invitation tracking across multiple channels, and properties statistics'");

        // Column comments for plan_agent_assignments table
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY plan_id BIGINT UNSIGNED NOT NULL COMMENT 'Reference to the registration plan'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY agent_id BIGINT UNSIGNED NOT NULL COMMENT 'Reference to the assigned field agent user'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY assigned_by BIGINT UNSIGNED NOT NULL COMMENT 'User who assigned the agent to the plan'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY assigned_at TIMESTAMP NOT NULL COMMENT 'Timestamp when agent was assigned to the plan'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether this assignment is currently active'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY removed_at TIMESTAMP NULL COMMENT 'Timestamp when agent was removed from the plan'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY removal_reason TEXT NULL COMMENT 'Reason for removing agent from the plan'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY properties_registered INT DEFAULT 0 COMMENT 'Number of properties registered by this agent for this plan'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY last_activity_at TIMESTAMP NULL COMMENT 'Timestamp of last registration activity by this agent'");
        
        // New fields comments for plan_agent_assignments
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY assignment_type ENUM('primary', 'secondary', 'backup') DEFAULT 'primary' COMMENT 'Type of assignment: primary (main agent), secondary (support), or backup (reserve)'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY performance_score DECIMAL(5,2) NULL COMMENT 'Calculated performance score based on productivity and efficiency'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY last_invitation_sent_at TIMESTAMP NULL COMMENT 'Timestamp when last invitation was sent to this agent'");
        DB::statement("ALTER TABLE plan_agent_assignments MODIFY invitation_acceptance_time INT NULL COMMENT 'Time taken by agent to accept invitation (in minutes)'");
        
        // Table comment for plan_agent_assignments
        DB::statement("ALTER TABLE plan_agent_assignments COMMENT = 'Many-to-many relationship between registration plans and field agents with assignment tracking, performance metrics, and invitation management'");

        // Add check constraints for data integrity
        try {
            DB::statement('ALTER TABLE registration_plans ADD CONSTRAINT chk_estimated_houses_positive CHECK (estimated_houses > 0)');
            DB::statement('ALTER TABLE registration_plans ADD CONSTRAINT chk_houses_registered_non_negative CHECK (houses_registered >= 0)');
            DB::statement('ALTER TABLE registration_plans ADD CONSTRAINT chk_properties_count_non_negative CHECK (properties_count >= 0)');
            DB::statement('ALTER TABLE registration_plans ADD CONSTRAINT chk_priority_range CHECK (priority BETWEEN 1 AND 10)');
            DB::statement('ALTER TABLE registration_plans ADD CONSTRAINT chk_sequence_continuation_non_negative CHECK (sequence_continuation_count >= 0)');
            DB::statement('ALTER TABLE registration_plans ADD CONSTRAINT chk_total_sequence_props_non_negative CHECK (total_sequence_properties >= 0)');
            
            DB::statement('ALTER TABLE plan_agent_assignments ADD CONSTRAINT chk_properties_registered_non_negative CHECK (properties_registered >= 0)');
            DB::statement('ALTER TABLE plan_agent_assignments ADD CONSTRAINT chk_performance_score_range CHECK (performance_score IS NULL OR performance_score BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE plan_agent_assignments ADD CONSTRAINT chk_invitation_time_positive CHECK (invitation_acceptance_time IS NULL OR invitation_acceptance_time > 0)');
        } catch (\Exception $e) {
            // Constraints might fail on some MySQL versions, so we catch and continue
            \Log::warning('Database constraints could not be added: ' . $e->getMessage());
        }
    }

    /**
     * Add PostgreSQL specific features
     */
    private function addPostgreSQLFeatures(): void
    {
        DB::statement("COMMENT ON TABLE registration_plans IS 'Registration plans for organizing property registration by geographical zones and sections with configurable naming sequences, multiple agent assignments, invitation tracking across multiple channels, and properties statistics'");
        DB::statement("COMMENT ON COLUMN registration_plans.naming_pattern IS 'Property naming pattern with placeholders like {letter}, {number}, or custom patterns'");
        DB::statement("COMMENT ON COLUMN registration_plans.starting_point IS 'Initial starting point for the naming sequence (e.g., A, 1, A1, B5)'");
        DB::statement("COMMENT ON COLUMN registration_plans.next_available_name IS 'Next available property name in the sequence for auto-generation'");
        DB::statement("COMMENT ON COLUMN registration_plans.sequence_type IS 'Type of numerical sequence: sequential (1,2,3), even_only (2,4,6), or odd_only (1,3,5)'");
        DB::statement("COMMENT ON COLUMN registration_plans.agent_assignment_type IS 'Type of agent assignment: single agent or multiple agents per plan'");
        DB::statement("COMMENT ON COLUMN registration_plans.is_global_sequence IS 'Whether this plan continues a global naming sequence across zones/sections'");
        DB::statement("COMMENT ON COLUMN registration_plans.continues_from_plan_id IS 'Reference to the previous plan in the global sequence chain'");
        
        // ✅ ADDED: Comments for new sequence continuation fields
        DB::statement("COMMENT ON COLUMN registration_plans.sequence_continuation_count IS 'Count of how many times this sequence has been continued from previous plans'");
        DB::statement("COMMENT ON COLUMN registration_plans.total_sequence_properties IS 'Total properties across all continuations of this sequence'");
        DB::statement("COMMENT ON COLUMN registration_plans.last_used_pattern IS 'Last used naming pattern for sequence continuation tracking'");
        
        DB::statement("COMMENT ON COLUMN registration_plans.estimated_houses IS 'Estimated number of houses to be registered in this plan'");
        DB::statement("COMMENT ON COLUMN registration_plans.houses_registered IS 'Actual count of registered houses (calculated from properties relationship)'");
        DB::statement("COMMENT ON COLUMN registration_plans.properties_count IS 'Total count of properties associated with this plan for statistics and reporting'");
        DB::statement("COMMENT ON COLUMN registration_plans.status IS 'Current status of the registration plan'");
        
        // Complete invitation field comments for PostgreSQL
        DB::statement("COMMENT ON COLUMN registration_plans.invitation_status IS 'Status of invitation to field agent: pending, sent, failed, or not required'");
        DB::statement("COMMENT ON COLUMN registration_plans.invitation_sent_at IS 'Timestamp when invitation was successfully sent to field agent'");
        DB::statement("COMMENT ON COLUMN registration_plans.invitation_provider IS 'Provider used for sending the invitation (arkesel, twilio, africastalking, etc.)'");
        DB::statement("COMMENT ON COLUMN registration_plans.sms_attempts IS 'Number of SMS sending attempts made for this invitation'");
        DB::statement("COMMENT ON COLUMN registration_plans.whatsapp_attempts IS 'Number of WhatsApp sending attempts made for this invitation'");
        DB::statement("COMMENT ON COLUMN registration_plans.email_attempts IS 'Number of email sending attempts made for this invitation'");
        DB::statement("COMMENT ON COLUMN registration_plans.last_sms_attempt_at IS 'Timestamp of the last SMS sending attempt'");
        DB::statement("COMMENT ON COLUMN registration_plans.invitation_channels IS 'Communication channels used for invitations (sms, whatsapp, email)'");
        DB::statement("COMMENT ON COLUMN registration_plans.preferred_channel IS 'Preferred communication channel for this plan (sms, whatsapp, email)'");
        
        // New fields comments
        DB::statement("COMMENT ON COLUMN registration_plans.plan_code IS 'Human-readable unique identifier for the registration plan'");
        DB::statement("COMMENT ON COLUMN registration_plans.priority IS 'Priority level of the plan (1-10), higher number indicates higher priority'");
        DB::statement("COMMENT ON COLUMN registration_plans.metadata IS 'Flexible JSON field for storing additional plan metadata and configuration'");
        
        DB::statement("COMMENT ON COLUMN registration_plans.registration_start_date IS 'Scheduled start date for registration activities'");
        DB::statement("COMMENT ON COLUMN registration_plans.registration_end_date IS 'Scheduled end date for registration activities'");
        DB::statement("COMMENT ON COLUMN registration_plans.started_at IS 'Timestamp when the plan execution actually began (when status changed to in_progress)'");
        DB::statement("COMMENT ON COLUMN registration_plans.completed_at IS 'Timestamp when the plan was marked as completed'");
        DB::statement("COMMENT ON COLUMN registration_plans.cancelled_at IS 'Timestamp when the plan was cancelled'");
        DB::statement("COMMENT ON COLUMN registration_plans.instructions IS 'Specific instructions for the assigned field agent'");
        DB::statement("COMMENT ON COLUMN registration_plans.boundaries_description IS 'Description of geographical boundaries for the registration area'");
        DB::statement("COMMENT ON COLUMN registration_plans.completion_notes IS 'Notes recorded when plan was completed or cancelled'");
        DB::statement("COMMENT ON COLUMN registration_plans.deleted_by IS 'User who soft-deleted the plan'");

        // Comments for plan_agent_assignments table
        DB::statement("COMMENT ON TABLE plan_agent_assignments IS 'Many-to-many relationship between registration plans and field agents with assignment tracking, performance metrics, and invitation management'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.plan_id IS 'Reference to the registration plan'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.agent_id IS 'Reference to the assigned field agent user'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.assigned_by IS 'User who assigned the agent to the plan'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.assigned_at IS 'Timestamp when agent was assigned to the plan'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.is_active IS 'Whether this assignment is currently active'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.removed_at IS 'Timestamp when agent was removed from the plan'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.removal_reason IS 'Reason for removing agent from the plan'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.properties_registered IS 'Number of properties registered by this agent for this plan'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.last_activity_at IS 'Timestamp of last registration activity by this agent'");
        
        // New fields comments for plan_agent_assignments
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.assignment_type IS 'Type of assignment: primary (main agent), secondary (support), or backup (reserve)'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.performance_score IS 'Calculated performance score based on productivity and efficiency'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.last_invitation_sent_at IS 'Timestamp when last invitation was sent to this agent'");
        DB::statement("COMMENT ON COLUMN plan_agent_assignments.invitation_acceptance_time IS 'Time taken by agent to accept invitation (in minutes)'");

        // Create partial indexes for better performance
        DB::statement('CREATE INDEX idx_assignments_active_only ON plan_agent_assignments (plan_id, agent_id) WHERE is_active = true');
        DB::statement('CREATE INDEX idx_plans_active_only ON registration_plans (zone, section, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_plans_sequence_cont ON registration_plans (sequence_continuation_count, is_global_sequence) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial indexes first (PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_assignments_active_only');
            DB::statement('DROP INDEX IF EXISTS idx_plans_active_only');
            DB::statement('DROP INDEX IF EXISTS idx_plans_sequence_cont');
        }

        // Drop tables in correct order to handle foreign keys
        Schema::dropIfExists('plan_agent_assignments');
        Schema::dropIfExists('registration_plans');
    }
};