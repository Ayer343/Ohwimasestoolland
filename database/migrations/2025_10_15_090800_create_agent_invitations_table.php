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
        Schema::create('agent_invitations', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys - ✅ VERIFIED: Correct for pivot table system
            $table->foreignId('plan_id')->constrained('registration_plans')->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            
            // ✅ ADDED: Link to plan_agent_assignments for better tracking
            $table->foreignId('assignment_id')->nullable()->constrained('plan_agent_assignments')->onDelete('cascade');
            
            // Invitation details
            $table->string('token', 64)->unique();
            
            // ✅ FIXED: Remove sent_via duplication and use invitation_method consistently
            $table->enum('invitation_method', ['sms', 'whatsapp', 'email', 'both'])->default('sms');
            
            $table->timestamp('expires_at');

            // ✅ FIXED: Align statuses with AgentInvitation model constants
            $table->enum('status', ['pending', 'sent', 'accepted', 'expired', 'revoked', 'failed'])->default('pending');
            
            // Message and provider info
            $table->text('message')->nullable();
            $table->string('provider', 50)->nullable(); // SMS provider used (arkesel, twilio, etc.)
            $table->integer('resend_count')->default(0);
            $table->integer('delivery_attempts')->default(0);
            
            // Tracking fields
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            
            // Revocation tracking
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('revocation_reason')->nullable();
            
            // Failure tracking
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            
            $table->timestamp('last_sent_at')->nullable();
            
            // IP and user agent fields
            $table->ipAddress('sent_from_ip')->nullable();
            $table->ipAddress('accepted_from_ip')->nullable();
            $table->text('accepted_user_agent')->nullable();
            
            // Security and validation fields
            $table->string('security_code', 10)->nullable();
            $table->integer('verification_attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            
            // Response tracking
            $table->integer('response_time_minutes')->nullable();
            
            // Enhanced metadata for tracking
            $table->json('metadata')->nullable();
            $table->json('delivery_receipt')->nullable();

            // ✅ ADDED: Multi-channel specific fields for better analytics
            $table->enum('last_verification_channel', ['sms', 'whatsapp', 'email'])->nullable()->comment('Last channel used for verification');
            $table->integer('verification_send_count')->default(0)->comment('Number of verification codes sent');
            $table->timestamp('last_verification_sent_at')->nullable()->comment('Last time verification code was sent');

            // ✅ ADDED: Email-specific tracking fields
            $table->string('email_subject')->nullable()->comment('Custom email subject for email invitations');
            $table->boolean('email_sent_successfully')->nullable()->comment('Whether email was successfully sent');
            $table->timestamp('email_sent_at')->nullable()->comment('When email was actually sent (for queued emails)');
            $table->text('email_error')->nullable()->comment('Email sending error if any');
            
            // ✅ ADDED: Token security enhancements
            $table->timestamp('token_used_at')->nullable()->comment('When token was used to accept invitation');
            $table->ipAddress('token_used_from_ip')->nullable()->comment('IP address where token was used');
            $table->boolean('is_token_compromised')->default(false)->comment('Flag if token security is compromised');
            
            // ✅ ADDED: Bulk invitation tracking
            $table->string('batch_id', 64)->nullable()->comment('Batch ID for bulk invitations');
            $table->integer('batch_sequence')->nullable()->comment('Sequence in batch for ordering');
            
            // Soft deletes and timestamps
            $table->softDeletes();
            $table->timestamps();

            // ✅ OPTIMIZED: Strategic indexes for better performance with multiple agents
            $table->index(['plan_id', 'agent_id', 'status'], 'idx_invitations_plan_agent_status');
            $table->index(['token'], 'idx_invitations_token');
            $table->index(['status', 'expires_at'], 'idx_invitations_status_expires');
            $table->index(['invitation_method', 'created_at'], 'idx_invitations_method_created');
            $table->index(['agent_id', 'status', 'created_at'], 'idx_invitations_agent_status_created');
            $table->index(['sent_at', 'status'], 'idx_invitations_sent_status');
            $table->index(['accepted_at'], 'idx_invitations_accepted');
            $table->index(['expires_at', 'status'], 'idx_invitations_expires_status');
            $table->index(['security_code'], 'idx_invitations_security_code');
            $table->index(['resend_count', 'last_sent_at'], 'idx_invitations_resend_activity');
            
            // ✅ ADDED: Composite indexes for multiple agent queries
            $table->index(['plan_id', 'status', 'expires_at'], 'idx_invitations_plan_status_expires');
            $table->index(['agent_id', 'plan_id', 'status'], 'idx_invitations_agent_plan_status');
            $table->index(['created_at', 'status'], 'idx_invitations_created_status');
            $table->index(['failed_at', 'status'], 'idx_invitations_failed_status');
            
            // ✅ ADDED: Index for assignment relationship
            $table->index(['assignment_id', 'status'], 'idx_invitations_assignment_status');
            
            // ✅ ADDED: Index for active invitations query (used in service)
            $table->index(['status', 'expires_at', 'agent_id'], 'idx_invitations_active_agent');
            
            // ✅ ADDED: Index for multi-channel analytics
            $table->index(['invitation_method', 'status', 'created_at'], 'idx_invitations_method_status_created');
            
            // ✅ ADDED: Index for corruption detection queries
            $table->index(['status', 'expires_at', 'created_at'], 'idx_invitations_status_expires_created');
            
            // ✅ ADDED: Indexes for multi-channel verification tracking
            $table->index(['last_verification_channel', 'created_at'], 'idx_invitations_verification_channel');
            $table->index(['verification_send_count', 'last_verification_sent_at'], 'idx_invitations_verification_activity');
            $table->index(['invitation_method', 'last_verification_channel'], 'idx_invitations_method_verification');
            
            // ✅ ADDED: New indexes for email and token tracking
            $table->index(['email_sent_successfully', 'created_at'], 'idx_invitations_email_success');
            $table->index(['batch_id', 'batch_sequence'], 'idx_invitations_batch');
            $table->index(['token_used_at', 'status'], 'idx_invitations_token_used');
            $table->index(['is_token_compromised'], 'idx_invitations_token_compromised');
            
            // ✅ ADDED: Index for performance on common queries
            $table->index(['status', 'invitation_method', 'created_at'], 'idx_invitations_status_method_created');
            $table->index(['agent_id', 'invitation_method', 'status'], 'idx_invitations_agent_method_status');
        });
        
        // ✅ FIXED: Added missing updated_at column to agent_invitation_logs table
        Schema::create('agent_invitation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained('agent_invitations')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('agent_id')->nullable()->constrained('users')->onDelete('set null'); // ✅ ADDED: Direct agent reference
            $table->foreignId('plan_id')->nullable()->constrained('registration_plans')->onDelete('set null'); // ✅ ADDED: Direct plan reference
            
            // ✅ FIXED: Removed duplicate 'viewed' value from enum
            $table->enum('action', [
                'created', 
                'sent', 
                'resent', 
                'delivered', 
                'viewed', 
                'accepted', 
                'expired', 
                'revoked', 
                'failed',
                'verified',
                'security_code_sent',
                'bulk_sent',
                'extended', // ✅ ADDED: For expiration extension
                'fixed',    // ✅ ADDED: For corruption fixes
                'verification_sent', // ✅ ADDED: For multi-channel verification tracking
                'verification_failed', // ✅ ADDED: For verification failures
                'channel_switched', // ✅ ADDED: For channel switching
                'email_sent', // ✅ ADDED: For email-specific tracking
                'email_failed', // ✅ ADDED: For email failure tracking
                'token_used', // ✅ ADDED: For token usage tracking
                'token_regenerated' // ✅ ADDED: For token security events
            ]); 
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            
            // ✅ FIXED: Added both timestamp columns to match Laravel conventions
            $table->timestamps(); // This creates both created_at and updated_at
            
            // ✅ ADDED: Multi-channel specific fields for logs
            $table->enum('channel_used', ['sms', 'whatsapp', 'email'])->nullable()->comment('Channel used for this action');
            $table->string('provider_used', 50)->nullable()->comment('Provider used for this action');
            
            // ✅ ADDED: Email-specific log fields
            $table->string('email_subject')->nullable()->comment('Email subject for email actions');
            $table->string('email_recipient')->nullable()->comment('Email recipient address');
            $table->boolean('email_success')->nullable()->comment('Whether email action was successful');
            
            // ✅ ADDED: Token security log fields
            $table->string('token_action', 50)->nullable()->comment('Token-related action');
            $table->ipAddress('token_ip_address')->nullable()->comment('IP address for token actions');
            
            // ✅ ENHANCED: Indexes for multiple agent analytics
            $table->index(['invitation_id', 'created_at'], 'idx_invitation_logs_invitation_created');
            $table->index(['action', 'created_at'], 'idx_invitation_logs_action_created');
            $table->index(['user_id', 'created_at'], 'idx_invitation_logs_user_created');
            $table->index(['agent_id', 'created_at'], 'idx_invitation_logs_agent_created');
            $table->index(['plan_id', 'created_at'], 'idx_invitation_logs_plan_created');
            $table->index(['action', 'agent_id', 'created_at'], 'idx_invitation_logs_action_agent_created');
            
            // ✅ ADDED: Indexes for multi-channel analytics
            $table->index(['action', 'plan_id', 'created_at'], 'idx_invitation_logs_action_plan_created');
            $table->index(['created_at', 'action'], 'idx_invitation_logs_created_action');
            $table->index(['channel_used', 'created_at'], 'idx_invitation_logs_channel_created');
            $table->index(['action', 'channel_used'], 'idx_invitation_logs_action_channel');
            $table->index(['provider_used', 'created_at'], 'idx_invitation_logs_provider_created');
            
            // ✅ ADDED: Index for updated_at queries
            $table->index(['updated_at'], 'idx_invitation_logs_updated');
            $table->index(['created_at', 'updated_at'], 'idx_invitation_logs_created_updated');
            
            // ✅ ADDED: New indexes for email and token tracking
            $table->index(['email_success', 'created_at'], 'idx_invitation_logs_email_success');
            $table->index(['token_action', 'created_at'], 'idx_invitation_logs_token_action');
            $table->index(['action', 'channel_used', 'created_at'], 'idx_invitation_logs_action_channel_created');
        });

        // Add database-specific comments and constraints
        $this->addDatabaseSpecificFeatures();
    }

    /**
     * Add database-specific features
     */
    private function addDatabaseSpecificFeatures(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $this->addMySQLFeatures();
        } elseif ($driver === 'pgsql') {
            $this->addPostgreSQLFeatures();
        }
    }

    /**
     * Add MySQL/MariaDB specific features
     */
    private function addMySQLFeatures(): void
    {
        // Column comments for agent_invitations table
        DB::statement("ALTER TABLE agent_invitations MODIFY token VARCHAR(64) NOT NULL COMMENT 'Unique secure token for invitation URL'");
        DB::statement("ALTER TABLE agent_invitations MODIFY invitation_method ENUM('sms', 'whatsapp', 'email', 'both') DEFAULT 'sms' COMMENT 'Method used to send the invitation'");
        DB::statement("ALTER TABLE agent_invitations MODIFY expires_at TIMESTAMP NOT NULL COMMENT 'Timestamp when invitation expires'");
        DB::statement("ALTER TABLE agent_invitations MODIFY status ENUM('pending', 'sent', 'accepted', 'expired', 'revoked', 'failed') DEFAULT 'pending' COMMENT 'Current status of the invitation'");
        DB::statement("ALTER TABLE agent_invitations MODIFY provider VARCHAR(50) NULL COMMENT 'SMS/email provider used for delivery'");
        DB::statement("ALTER TABLE agent_invitations MODIFY resend_count INT DEFAULT 0 COMMENT 'Number of times invitation has been resent'");
        DB::statement("ALTER TABLE agent_invitations MODIFY delivery_attempts INT DEFAULT 0 COMMENT 'Number of delivery attempts made'");
        DB::statement("ALTER TABLE agent_invitations MODIFY security_code VARCHAR(10) NULL COMMENT 'Short security code for additional verification'");
        DB::statement("ALTER TABLE agent_invitations MODIFY verification_attempts INT DEFAULT 0 COMMENT 'Number of security code verification attempts'");
        DB::statement("ALTER TABLE agent_invitations MODIFY response_time_minutes INT NULL COMMENT 'Time taken from sent to accepted in minutes'");
        
        // New fields comments
        DB::statement("ALTER TABLE agent_invitations MODIFY revocation_reason TEXT NULL COMMENT 'Reason for revoking the invitation'");
        DB::statement("ALTER TABLE agent_invitations MODIFY failure_reason TEXT NULL COMMENT 'Reason for invitation failure'");
        DB::statement("ALTER TABLE agent_invitations MODIFY delivery_receipt JSON NULL COMMENT 'Provider delivery confirmation data'");
        DB::statement("ALTER TABLE agent_invitations MODIFY metadata JSON NULL COMMENT 'Additional invitation metadata and tracking data'");
        DB::statement("ALTER TABLE agent_invitations MODIFY assignment_id BIGINT UNSIGNED NULL COMMENT 'Reference to plan_agent_assignments table for better tracking'");
        
        // ✅ ADDED: Comments for multi-channel fields
        DB::statement("ALTER TABLE agent_invitations MODIFY last_verification_channel ENUM('sms', 'whatsapp', 'email') NULL COMMENT 'Last channel used for verification'");
        DB::statement("ALTER TABLE agent_invitations MODIFY verification_send_count INT DEFAULT 0 COMMENT 'Number of verification codes sent'");
        DB::statement("ALTER TABLE agent_invitations MODIFY last_verification_sent_at TIMESTAMP NULL COMMENT 'Last time verification code was sent'");
        
        // ✅ ADDED: Comments for new email and token fields
        DB::statement("ALTER TABLE agent_invitations MODIFY email_subject VARCHAR(255) NULL COMMENT 'Custom email subject for email invitations'");
        DB::statement("ALTER TABLE agent_invitations MODIFY email_sent_successfully BOOLEAN NULL COMMENT 'Whether email was successfully sent'");
        DB::statement("ALTER TABLE agent_invitations MODIFY email_sent_at TIMESTAMP NULL COMMENT 'When email was actually sent (for queued emails)'");
        DB::statement("ALTER TABLE agent_invitations MODIFY email_error TEXT NULL COMMENT 'Email sending error if any'");
        DB::statement("ALTER TABLE agent_invitations MODIFY token_used_at TIMESTAMP NULL COMMENT 'When token was used to accept invitation'");
        DB::statement("ALTER TABLE agent_invitations MODIFY token_used_from_ip VARCHAR(45) NULL COMMENT 'IP address where token was used'");
        DB::statement("ALTER TABLE agent_invitations MODIFY is_token_compromised BOOLEAN DEFAULT FALSE COMMENT 'Flag if token security is compromised'");
        DB::statement("ALTER TABLE agent_invitations MODIFY batch_id VARCHAR(64) NULL COMMENT 'Batch ID for bulk invitations'");
        DB::statement("ALTER TABLE agent_invitations MODIFY batch_sequence INT NULL COMMENT 'Sequence in batch for ordering'");
        
        // Table comment
        DB::statement("ALTER TABLE agent_invitations COMMENT = 'Agent invitations for registration plans with multi-channel delivery, security features, and comprehensive tracking. Supports multiple agents per plan with enhanced multi-channel verification, email tracking, and token security.'");

        // Column comments for agent_invitation_logs table
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY action ENUM('created', 'sent', 'resent', 'delivered', 'viewed', 'accepted', 'expired', 'revoked', 'failed', 'verified', 'security_code_sent', 'bulk_sent', 'extended', 'fixed', 'verification_sent', 'verification_failed', 'channel_switched', 'email_sent', 'email_failed', 'token_used', 'token_regenerated') NOT NULL COMMENT 'Action performed on the invitation'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY details TEXT NULL COMMENT 'Detailed information about the action'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY metadata JSON NULL COMMENT 'Additional log metadata'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY agent_id BIGINT UNSIGNED NULL COMMENT 'Direct agent reference for analytics'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY plan_id BIGINT UNSIGNED NULL COMMENT 'Direct plan reference for analytics'");
        
        // ✅ ADDED: Comments for log multi-channel fields
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY channel_used ENUM('sms', 'whatsapp', 'email') NULL COMMENT 'Channel used for this action'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY provider_used VARCHAR(50) NULL COMMENT 'Provider used for this action'");
        
        // ✅ ADDED: Comments for new log fields
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY email_subject VARCHAR(255) NULL COMMENT 'Email subject for email actions'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY email_recipient VARCHAR(255) NULL COMMENT 'Email recipient address'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY email_success BOOLEAN NULL COMMENT 'Whether email action was successful'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY token_action VARCHAR(50) NULL COMMENT 'Token-related action'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY token_ip_address VARCHAR(45) NULL COMMENT 'IP address for token actions'");
        
        // ✅ ADDED: Comments for timestamp columns
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY created_at TIMESTAMP NULL COMMENT 'When the log entry was created'");
        DB::statement("ALTER TABLE agent_invitation_logs MODIFY updated_at TIMESTAMP NULL COMMENT 'When the log entry was last updated'");
        
        // Table comment for logs
        DB::statement("ALTER TABLE agent_invitation_logs COMMENT = 'Audit log for agent invitation activities and status changes. Supports multiple agent tracking and multi-channel analytics with detailed channel, email, and token security tracking.'");

        // Add check constraints for data integrity
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_resend_count_non_negative CHECK (resend_count >= 0)');
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_delivery_attempts_non_negative CHECK (delivery_attempts >= 0)');
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_verification_attempts_non_negative CHECK (verification_attempts >= 0)');
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_response_time_positive CHECK (response_time_minutes IS NULL OR response_time_minutes > 0)');
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_verification_send_count_non_negative CHECK (verification_send_count >= 0)');
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_batch_sequence_positive CHECK (batch_sequence IS NULL OR batch_sequence > 0)');
        
        // ✅ ADDED: Constraint for assignment relationship
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_assignment_consistency CHECK (
            (assignment_id IS NULL) OR 
            (assignment_id IS NOT NULL AND plan_id IS NOT NULL AND agent_id IS NOT NULL)
        )');
        
        // ✅ ADDED: Constraint for email tracking consistency
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_email_tracking_consistency CHECK (
            (email_sent_at IS NULL AND email_sent_successfully IS NULL) OR 
            (email_sent_at IS NOT NULL AND email_sent_successfully IS NOT NULL)
        )');
    }

    /**
     * Add PostgreSQL specific features
     */
    private function addPostgreSQLFeatures(): void
    {
        DB::statement("COMMENT ON TABLE agent_invitations IS 'Agent invitations for registration plans with multi-channel delivery, security features, and comprehensive tracking. Supports multiple agents per plan with enhanced multi-channel verification, email tracking, and token security.'");
        DB::statement("COMMENT ON COLUMN agent_invitations.token IS 'Unique secure token for invitation URL'");
        DB::statement("COMMENT ON COLUMN agent_invitations.invitation_method IS 'Method used to send the invitation'");
        DB::statement("COMMENT ON COLUMN agent_invitations.expires_at IS 'Timestamp when invitation expires'");
        DB::statement("COMMENT ON COLUMN agent_invitations.status IS 'Current status of the invitation'");
        DB::statement("COMMENT ON COLUMN agent_invitations.provider IS 'SMS/email provider used for delivery'");
        DB::statement("COMMENT ON COLUMN agent_invitations.resend_count IS 'Number of times invitation has been resent'");
        DB::statement("COMMENT ON COLUMN agent_invitations.delivery_attempts IS 'Number of delivery attempts made'");
        DB::statement("COMMENT ON COLUMN agent_invitations.security_code IS 'Short security code for additional verification'");
        DB::statement("COMMENT ON COLUMN agent_invitations.verification_attempts IS 'Number of security code verification attempts'");
        DB::statement("COMMENT ON COLUMN agent_invitations.response_time_minutes IS 'Time taken from sent to accepted in minutes'");
        
        // New fields comments
        DB::statement("COMMENT ON COLUMN agent_invitations.revocation_reason IS 'Reason for revoking the invitation'");
        DB::statement("COMMENT ON COLUMN agent_invitations.failure_reason IS 'Reason for invitation failure'");
        DB::statement("COMMENT ON COLUMN agent_invitations.delivery_receipt IS 'Provider delivery confirmation data'");
        DB::statement("COMMENT ON COLUMN agent_invitations.metadata IS 'Additional invitation metadata and tracking data'");
        DB::statement("COMMENT ON COLUMN agent_invitations.assignment_id IS 'Reference to plan_agent_assignments table for better tracking'");

        // ✅ ADDED: PostgreSQL comments for multi-channel fields
        DB::statement("COMMENT ON COLUMN agent_invitations.last_verification_channel IS 'Last channel used for verification'");
        DB::statement("COMMENT ON COLUMN agent_invitations.verification_send_count IS 'Number of verification codes sent'");
        DB::statement("COMMENT ON COLUMN agent_invitations.last_verification_sent_at IS 'Last time verification code was sent'");

        // ✅ ADDED: PostgreSQL comments for new email and token fields
        DB::statement("COMMENT ON COLUMN agent_invitations.email_subject IS 'Custom email subject for email invitations'");
        DB::statement("COMMENT ON COLUMN agent_invitations.email_sent_successfully IS 'Whether email was successfully sent'");
        DB::statement("COMMENT ON COLUMN agent_invitations.email_sent_at IS 'When email was actually sent (for queued emails)'");
        DB::statement("COMMENT ON COLUMN agent_invitations.email_error IS 'Email sending error if any'");
        DB::statement("COMMENT ON COLUMN agent_invitations.token_used_at IS 'When token was used to accept invitation'");
        DB::statement("COMMENT ON COLUMN agent_invitations.token_used_from_ip IS 'IP address where token was used'");
        DB::statement("COMMENT ON COLUMN agent_invitations.is_token_compromised IS 'Flag if token security is compromised'");
        DB::statement("COMMENT ON COLUMN agent_invitations.batch_id IS 'Batch ID for bulk invitations'");
        DB::statement("COMMENT ON COLUMN agent_invitations.batch_sequence IS 'Sequence in batch for ordering'");

        // Comments for agent_invitation_logs table
        DB::statement("COMMENT ON TABLE agent_invitation_logs IS 'Audit log for agent invitation activities and status changes. Supports multiple agent tracking and multi-channel analytics with detailed channel, email, and token security tracking.'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.action IS 'Action performed on the invitation'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.details IS 'Detailed information about the action'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.metadata IS 'Additional log metadata'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.agent_id IS 'Direct agent reference for analytics'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.plan_id IS 'Direct plan reference for analytics'");

        // ✅ ADDED: PostgreSQL comments for log multi-channel fields
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.channel_used IS 'Channel used for this action'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.provider_used IS 'Provider used for this action'");

        // ✅ ADDED: PostgreSQL comments for new log fields
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.email_subject IS 'Email subject for email actions'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.email_recipient IS 'Email recipient address'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.email_success IS 'Whether email action was successful'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.token_action IS 'Token-related action'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.token_ip_address IS 'IP address for token actions'");

        // ✅ ADDED: PostgreSQL comments for timestamp columns
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.created_at IS 'When the log entry was created'");
        DB::statement("COMMENT ON COLUMN agent_invitation_logs.updated_at IS 'When the log entry was last updated'");

        // ✅ FIXED: Use proper PostgreSQL syntax for partial indexes
        DB::statement("CREATE INDEX idx_invitations_active ON agent_invitations (plan_id, agent_id) WHERE status IN ('pending', 'sent') AND expires_at > NOW()");
        DB::statement("CREATE INDEX idx_invitations_recent ON agent_invitations (created_at) WHERE created_at >= (NOW() - INTERVAL '30 days')");
        DB::statement("CREATE INDEX idx_invitations_multiple_agents ON agent_invitations (plan_id, status) WHERE status IN ('sent', 'accepted')");
        
        // ✅ ADDED: Partial index for active assignments
        DB::statement("CREATE INDEX idx_invitations_active_assignments ON agent_invitations (assignment_id, status) WHERE status IN ('sent', 'accepted') AND assignment_id IS NOT NULL");
        
        // ✅ ADDED: Partial index for corruption detection
        DB::statement("CREATE INDEX idx_invitations_corrupted ON agent_invitations (status, created_at, expires_at) WHERE status IN ('sent', 'pending') AND expires_at - created_at < INTERVAL '1 day'");
        
        // ✅ ADDED: Partial index for multi-channel analytics
        DB::statement("CREATE INDEX idx_invitations_channel_performance ON agent_invitations (invitation_method, status, created_at) WHERE status IN ('sent', 'accepted', 'expired')");

        // ✅ ADDED: Partial indexes for multi-channel verification
        DB::statement("CREATE INDEX idx_invitations_verification_active ON agent_invitations (last_verification_channel, status) WHERE status IN ('sent', 'pending') AND verification_send_count > 0");
        DB::statement("CREATE INDEX idx_invitations_recent_verification ON agent_invitations (last_verification_sent_at) WHERE last_verification_sent_at >= (NOW() - INTERVAL '1 hour')");

        // ✅ ADDED: Partial indexes for email tracking
        DB::statement("CREATE INDEX idx_invitations_email_success ON agent_invitations (email_sent_successfully, created_at) WHERE invitation_method = 'email'");
        DB::statement("CREATE INDEX idx_invitations_email_recent ON agent_invitations (email_sent_at) WHERE email_sent_at >= (NOW() - INTERVAL '7 days')");
        
        // ✅ ADDED: Partial indexes for token security
        DB::statement("CREATE INDEX idx_invitations_token_used ON agent_invitations (token_used_at, status) WHERE token_used_at IS NOT NULL");
        DB::statement("CREATE INDEX idx_invitations_token_compromised ON agent_invitations (is_token_compromised) WHERE is_token_compromised = true");
        
        // ✅ ADDED: Partial index for batch operations
        DB::statement("CREATE INDEX idx_invitations_batch_active ON agent_invitations (batch_id, batch_sequence) WHERE batch_id IS NOT NULL AND status IN ('sent', 'pending')");

        // ✅ PostgreSQL allows functions in CHECK constraints, so we can add it here
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_expiration_consistency CHECK (
            (status IN (\'pending\', \'sent\') AND expires_at > NOW()) OR 
            (status IN (\'accepted\', \'expired\', \'revoked\', \'failed\'))
        )');
        
        // ✅ ADDED: PostgreSQL constraints for new fields
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_batch_sequence_positive CHECK (batch_sequence IS NULL OR batch_sequence > 0)');
        DB::statement('ALTER TABLE agent_invitations ADD CONSTRAINT chk_email_tracking_consistency CHECK (
            (email_sent_at IS NULL AND email_sent_successfully IS NULL) OR 
            (email_sent_at IS NOT NULL AND email_sent_successfully IS NOT NULL)
        )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial indexes first (PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_invitations_active');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_recent');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_multiple_agents');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_active_assignments');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_corrupted');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_channel_performance');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_verification_active');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_recent_verification');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_email_success');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_email_recent');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_token_used');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_token_compromised');
            DB::statement('DROP INDEX IF EXISTS idx_invitations_batch_active');
        }

        Schema::dropIfExists('agent_invitation_logs');
        Schema::dropIfExists('agent_invitations');
    }
};