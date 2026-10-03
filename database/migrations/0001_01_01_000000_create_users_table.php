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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // ==================== BASIC USER INFORMATION ====================
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            
            // ==================== PERSONAL DETAILS ====================
            $table->string('digital_address')->nullable();
            $table->string('region')->nullable();
            $table->string('location')->nullable();
            $table->string('photo')->nullable()->comment('Profile photo filename');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('username')->nullable()->unique();
            
            // ==================== ADDED FROM PROFILE CONTROLLER ====================
            $table->string('alt_phone')->nullable()->comment('Alternative phone number');
            $table->string('national_id', 50)->nullable()->comment('National ID/Passport number');
            $table->string('emergency_contact')->nullable()->comment('Emergency contact information');
            $table->timestamp('password_changed_at')->nullable()->comment('Last password change timestamp');
            
            // ==================== SECURITY PERSONNEL FIELDS ====================
            $table->string('security_id')->nullable()->comment('Security personnel ID');
            $table->string('security_location')->nullable()->comment('Assigned security location');
            $table->string('shift')->nullable()->comment('Work shift schedule');
            
            // ==================== FIELD AGENT SPECIFIC FIELDS ====================
            $table->json('specializations')->nullable()->comment('Field agent specializations/skills');
            $table->json('preferences')->nullable()->comment('Work preferences');
            $table->text('equipment')->nullable()->comment('Equipment details');
            $table->text('agent_notes')->nullable()->comment('Agent notes/comments');
            
            // ==================== DEVELOPER FIELDS ====================
            $table->string('api_key')->nullable()->unique()->comment('API key for developers');
            $table->timestamp('api_key_generated_at')->nullable()->comment('API key generation timestamp');
            $table->enum('api_access_level', ['read', 'write', 'admin'])->nullable()->default('read');
            $table->enum('default_environment', ['local', 'staging', 'production'])->nullable()->default('local');
            $table->boolean('debug_mode')->default(false)->comment('Debug mode for developers');
            $table->boolean('system_alerts')->default(true)->comment('System alerts for developers');
            $table->boolean('api_change_notifications')->default(true)->comment('API change notifications');
            $table->boolean('security_alerts')->default(true)->comment('Security alerts for developers');

            // ==================== TENANT-SPECIFIC FIELDS ====================
            $table->enum('employment_status', ['employed', 'self_employed', 'student', 'unemployed', 'retired'])->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->string('company_name')->nullable();
            $table->string('job_title')->nullable();
            $table->json('emergency_contact_json')->nullable()->comment('JSON emergency contact details');
            $table->text('tenant_background')->nullable();
            $table->text('previous_landlord_reference')->nullable();
            $table->text('landlord_notes')->nullable();
            
            // ==================== DOCUMENT FIELDS ====================
            $table->json('tenant_documents')->nullable();
            $table->json('id_documents')->nullable();
            $table->json('employment_documents')->nullable();
            $table->json('bank_documents')->nullable();
            $table->json('reference_documents')->nullable();
            $table->json('other_documents')->nullable();

            // ==================== ROLE AND STATUS ====================
            $table->tinyInteger('type')->default(3); // TYPE_TENANT as default
            $table->enum('status', [
                'pending', 
                'active', 
                'suspended', 
                'inactive', 
                'verification_required'
            ])->default('active');

            // ==================== INVITATION SYSTEM FIELDS ====================
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->timestamp('last_invitation_sent_at')->nullable();
            $table->enum('invitation_method', ['sms', 'whatsapp', 'email', 'sms_whatsapp', 'sms_email', 'whatsapp_email', 'all_channels'])->nullable();
            $table->string('temp_password')->nullable();
            
            // ==================== VERIFICATION FIELDS ====================
            $table->string('verification_code')->nullable();
            $table->timestamp('verification_code_sent_at')->nullable();
            $table->string('phone_verification_code')->nullable();
            $table->timestamp('phone_verification_sent_at')->nullable();
            
            // ==================== COMMUNICATION PREFERENCES ====================
            $table->enum('preferred_verification_channel', ['sms', 'whatsapp', 'email'])->nullable();
            $table->enum('last_verification_channel', ['sms', 'whatsapp', 'email'])->nullable();
            $table->enum('preferred_communication_channel', ['sms', 'whatsapp', 'email'])->default('sms');
            
            // ==================== ACTIVITY TRACKING ====================
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            
            // ==================== METADATA AND AUDIT ====================
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            // ==================== REGISTRATION TRACKING ====================
            $table->timestamp('first_login_at')->nullable();
            $table->string('registration_ip', 45)->nullable();
            $table->text('registration_user_agent')->nullable();
            $table->string('registration_source')->nullable()->comment('e.g., invitation, manual, import');
            $table->boolean('password_change_required')->default(false);

            // ==================== STANDARD LARAVEL FIELDS ====================
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // ==================== INDEXES ====================
            // Basic indexes
            $table->index(['type', 'status'], 'idx_users_type_status');
            $table->index(['status', 'deleted_at'], 'idx_users_status_deleted');
            $table->index(['type', 'deleted_at'], 'idx_users_type_deleted');
            $table->index('status', 'idx_users_status');
            $table->index('type', 'idx_users_type');
            $table->index('last_activity_at', 'idx_users_last_activity');
            $table->index('last_login_at', 'idx_users_last_login');
            $table->index('created_by', 'idx_users_creator');
            $table->index('phone_verified_at', 'idx_users_phone_verified');
            $table->index('invitation_accepted_at', 'idx_users_invitation_accepted');
            $table->index(['deleted_at', 'status'], 'idx_users_deleted_status');
            $table->index('email_verified_at', 'idx_users_email_verified');
            
            // ✅ ADDED: Indexes for new fields from ProfileController
            $table->index('alt_phone', 'idx_users_alt_phone');
            $table->index('national_id', 'idx_users_national_id');
            $table->index('security_id', 'idx_users_security_id');
            $table->index('security_location', 'idx_users_security_location');
            $table->index('shift', 'idx_users_shift');
            $table->index('api_key', 'idx_users_api_key');
            $table->index('api_access_level', 'idx_users_api_access_level');
            $table->index('password_changed_at', 'idx_users_password_changed_at');
            
            // Phone and username indexes
            $table->index('phone', 'idx_users_phone');
            $table->index('username', 'idx_users_username');
            $table->index('email', 'idx_users_email');
            
            // Communication preferences indexes
            $table->index('preferred_verification_channel', 'idx_users_preferred_verification');
            $table->index('last_verification_channel', 'idx_users_last_verification');
            $table->index('preferred_communication_channel', 'idx_users_preferred_communication');
            
            // Performance indexes
            $table->index(['created_at', 'type'], 'idx_users_created_type');
            $table->index(['updated_at', 'type'], 'idx_users_updated_type');
            $table->index(['deleted_at', 'type'], 'idx_users_deleted_type');
            
            // Tenant-specific indexes
            $table->index('employment_status', 'idx_users_employment_status');
            $table->index('monthly_income', 'idx_users_monthly_income');
            $table->index('company_name', 'idx_users_company_name');
            $table->index('job_title', 'idx_users_job_title');
            $table->index(['type', 'employment_status'], 'idx_users_type_employment');
            $table->index(['type', 'monthly_income'], 'idx_users_type_income');
            $table->index(['type', 'status', 'employment_status'], 'idx_users_type_status_employment');
            
            // Additional tracking indexes
            $table->index('first_login_at', 'idx_users_first_login');
            $table->index('registration_ip', 'idx_users_registration_ip');
            $table->index('registration_source', 'idx_users_registration_source');
            $table->index('password_change_required', 'idx_users_password_change');
            
            // ✅ ADDED: Composite indexes for better query performance
            $table->index(['type', 'status', 'last_activity_at'], 'idx_users_type_status_activity');
            $table->index(['status', 'created_at'], 'idx_users_status_created');
            $table->index(['type', 'created_at'], 'idx_users_type_created');
        });

        // Create password reset tokens table
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->index(['email', 'token'], 'idx_password_reset_email_token');
        });

        // Create sessions table
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index('idx_sessions_user');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index('idx_sessions_last_activity');
            $table->index(['user_id', 'last_activity'], 'idx_sessions_user_activity');
        });

        // Create password history table for enhanced security
        Schema::create('password_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('password_hash');
            $table->timestamp('changed_at')->useCurrent();
            $table->string('changed_by_ip', 45)->nullable();
            $table->string('changed_by_user_agent')->nullable();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'changed_at'], 'idx_password_history_user_changed');
            $table->index('changed_at', 'idx_password_history_changed_at');
        });

        // Create user activity log table
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('activity_type'); // login, profile_update, password_change, invitation_sent, etc.
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'performed_at'], 'idx_activity_log_user_performed');
            $table->index('activity_type', 'idx_activity_log_type');
            $table->index('performed_at', 'idx_activity_log_performed');
        });

        // Create user verification attempts table
        Schema::create('user_verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('verification_type'); // phone, email, invitation, etc.
            $table->string('code')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'verification_type'], 'idx_verification_user_type');
            $table->index(['user_id', 'successful'], 'idx_verification_user_success');
            $table->index('attempted_at', 'idx_verification_attempted');
        });

        // Create user login history table for detailed tracking
        Schema::create('user_login_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('device_type')->nullable();
            $table->boolean('successful')->default(true);
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('login_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'login_at'], 'idx_login_history_user_login');
            $table->index('login_at', 'idx_login_history_login_at');
            $table->index(['user_id', 'successful'], 'idx_login_history_user_success');
            $table->index('ip_address', 'idx_login_history_ip');
        });

        // Create user profile updates table for audit trail
        Schema::create('user_profile_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('field_name'); // name, email, phone, photo, etc.
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'updated_at'], 'idx_profile_updates_user_updated');
            $table->index('field_name', 'idx_profile_updates_field');
            $table->index('updated_at', 'idx_profile_updates_updated');
        });

        // ✅ ADDED: Create user notification settings table
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('new_assignment_notifications')->default(true);
            $table->boolean('assignment_update_notifications')->default(true);
            $table->boolean('deadline_notifications')->default(true);
            $table->boolean('team_message_notifications')->default(true);
            $table->boolean('announcement_notifications')->default(true);
            $table->boolean('training_notifications')->default(true);
            $table->boolean('performance_review_notifications')->default(true);
            $table->boolean('feedback_notifications')->default(true);
            $table->boolean('recognition_notifications')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(true);
            $table->boolean('in_app_notifications')->default(true);
            $table->boolean('suspicious_activity_alerts')->default(true);
            $table->boolean('password_change_alerts')->default(true);
            $table->boolean('new_device_alerts')->default(true);
            $table->boolean('data_sharing')->default(false);
            $table->boolean('marketing_emails')->default(false);
            $table->boolean('location_tracking')->default(false);
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('login_alerts')->default(true);
            $table->json('custom_rules')->nullable();
            $table->timestamp('settings_updated_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id', 'idx_notification_settings_user_unique');
            $table->index('two_factor_enabled', 'idx_notification_two_factor');
            $table->index('settings_updated_at', 'idx_notification_settings_updated');
        });

        // ✅ ADDED: Create user security settings table
        Schema::create('user_security_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('two_factor_enabled')->default(false);
            $table->enum('two_factor_method', ['sms', 'email', 'authenticator'])->nullable();
            $table->boolean('login_alerts')->default(true);
            $table->boolean('data_sharing')->default(false);
            $table->boolean('marketing_emails')->default(false);
            $table->boolean('suspicious_activity_alerts')->default(true);
            $table->boolean('password_change_alerts')->default(true);
            $table->boolean('new_device_alerts')->default(true);
            $table->integer('session_timeout_minutes')->default(120);
            $table->integer('max_login_attempts')->default(5);
            $table->boolean('force_password_change')->default(false);
            $table->timestamp('last_security_update')->useCurrent();
            $table->json('security_metadata')->nullable();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id', 'idx_security_settings_user_unique');
            $table->index('two_factor_enabled', 'idx_security_two_factor');
            $table->index('last_security_update', 'idx_security_last_update');
        });

        // ✅ ADDED: Create user API usage table for developers
        Schema::create('user_api_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('api_key')->nullable();
            $table->string('endpoint');
            $table->string('method'); // GET, POST, PUT, DELETE
            $table->integer('response_code');
            $table->integer('response_time_ms');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->json('request_metadata')->nullable();
            $table->timestamp('request_time')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'request_time'], 'idx_api_usage_user_time');
            $table->index('api_key', 'idx_api_usage_key');
            $table->index('endpoint', 'idx_api_usage_endpoint');
            $table->index('method', 'idx_api_usage_method');
            $table->index('response_code', 'idx_api_usage_response_code');
            $table->index('request_time', 'idx_api_usage_request_time');
        });

        // ✅ ADDED: Create user ID documents table
        Schema::create('user_id_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('document_type', ['ghana_card', 'passport', 'driver_license', 'voter_id', 'other']);
            $table->string('document_number');
            $table->string('document_file_path');
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'expired'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('document_metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'document_type'], 'idx_id_documents_user_type');
            $table->index('document_number', 'idx_id_documents_number');
            $table->index('verification_status', 'idx_id_documents_status');
            $table->index('verified_at', 'idx_id_documents_verified');
            $table->index('expires_at', 'idx_id_documents_expires');
            $table->unique(['user_id', 'document_type'], 'idx_id_documents_user_type_unique');
        });

        // ✅ ADDED: Create user sessions table for multi-device management
        Schema::create('user_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('session_id')->unique();
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('ip_address', 45);
            $table->string('location')->nullable();
            $table->timestamp('login_time')->useCurrent();
            $table->timestamp('last_activity')->useCurrent();
            $table->boolean('is_current')->default(false);
            $table->json('session_metadata')->nullable();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'last_activity'], 'idx_active_sessions_user_activity');
            $table->index('session_id', 'idx_active_sessions_id');
            $table->index('device_type', 'idx_active_sessions_device');
            $table->index('is_current', 'idx_active_sessions_current');
            $table->index('login_time', 'idx_active_sessions_login');
        });

        // ✅ ADDED: Create user profile completion table
        Schema::create('user_profile_completion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('completion_percentage')->default(0);
            $table->json('completed_fields')->nullable();
            $table->json('pending_fields')->nullable();
            $table->json('field_weights')->nullable();
            $table->timestamp('last_calculated_at')->useCurrent();
            $table->json('completion_metadata')->nullable();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id', 'idx_profile_completion_user_unique');
            $table->index('completion_percentage', 'idx_profile_completion_percentage');
            $table->index('last_calculated_at', 'idx_profile_completion_calculated');
        });

        // ✅ ADDED: Create user statistics table
        Schema::create('user_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            
            // General statistics
            $table->integer('total_logins')->default(0);
            $table->integer('failed_logins')->default(0);
            $table->integer('password_changes')->default(0);
            $table->integer('profile_updates')->default(0);
            
            // Type-specific statistics
            $table->integer('total_properties')->default(0)->comment('For landlords');
            $table->integer('vacant_units')->default(0)->comment('For landlords');
            $table->integer('total_units')->default(0)->comment('For landlords');
            $table->integer('active_assignments')->default(0)->comment('For field agents');
            $table->decimal('completion_rate', 5, 2)->default(0)->comment('For field agents');
            $table->integer('total_incidents')->default(0)->comment('For security personnel');
            $table->integer('total_patrols')->default(0)->comment('For security personnel');
            $table->integer('api_requests')->default(0)->comment('For developers');
            $table->integer('api_requests_today')->default(0)->comment('For developers');
            
            // Timestamps
            $table->timestamp('first_login_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_profile_update')->nullable();
            $table->timestamp('last_password_change')->nullable();
            $table->timestamp('statistics_updated_at')->useCurrent();
            $table->json('statistics_metadata')->nullable();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id', 'idx_user_statistics_user_unique');
            $table->index('total_logins', 'idx_statistics_logins');
            $table->index('total_properties', 'idx_statistics_properties');
            $table->index('active_assignments', 'idx_statistics_assignments');
            $table->index('api_requests', 'idx_statistics_api_requests');
            $table->index('statistics_updated_at', 'idx_statistics_updated');
        });

        // ✅ ADDED: Create user invitations table (separate from history)
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->enum('purpose', ['tenant_registration', 'property_assignment', 'lease_review', 'welcome'])->default('tenant_registration');
            $table->json('channels')->nullable();
            $table->enum('status', ['pending', 'sent', 'accepted', 'expired', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('token');
            $table->index('purpose');
            $table->index('expires_at');
            $table->index(['status', 'expires_at']);
        });

        // ✅ FIXED: Add table comments AFTER all tables are created
        $this->addTableComments();
    }

    /**
     * Add table and column comments for documentation
     */
    private function addTableComments(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            // Users table comments
            DB::statement("ALTER TABLE users COMMENT = 'Users table storing all system users including super admins, admins, landlords, tenants, field agents, developers, and security personnel with comprehensive profile fields from ProfileController'");
            
            // Users column comments for new fields
            DB::statement("ALTER TABLE users MODIFY type TINYINT DEFAULT 3 COMMENT 'User type: 0=Super Admin, 1=Admin, 2=Landlord, 3=Tenant, 4=Field Agent, 5=Developer, 6=Security Personnel'");
            DB::statement("ALTER TABLE users MODIFY alt_phone VARCHAR(255) NULL COMMENT 'Alternative phone number for emergency/backup contact'");
            DB::statement("ALTER TABLE users MODIFY national_id VARCHAR(50) NULL COMMENT 'National ID/GH Card number'");
            DB::statement("ALTER TABLE users MODIFY emergency_contact TEXT NULL COMMENT 'Emergency contact information'");
            DB::statement("ALTER TABLE users MODIFY password_changed_at TIMESTAMP NULL COMMENT 'Timestamp of last password change for security monitoring'");
            DB::statement("ALTER TABLE users MODIFY security_id VARCHAR(255) NULL COMMENT 'Security personnel identification number'");
            DB::statement("ALTER TABLE users MODIFY security_location VARCHAR(255) NULL COMMENT 'Assigned location for security personnel'");
            DB::statement("ALTER TABLE users MODIFY shift VARCHAR(255) NULL COMMENT 'Work shift schedule for security personnel'");
            DB::statement("ALTER TABLE users MODIFY specializations JSON NULL COMMENT 'Field agent specializations and skills'");
            DB::statement("ALTER TABLE users MODIFY preferences JSON NULL COMMENT 'Work preferences for field agents'");
            DB::statement("ALTER TABLE users MODIFY equipment TEXT NULL COMMENT 'Equipment details for field agents'");
            DB::statement("ALTER TABLE users MODIFY agent_notes TEXT NULL COMMENT 'Notes and comments about field agent'");
            DB::statement("ALTER TABLE users MODIFY api_key VARCHAR(255) NULL UNIQUE COMMENT 'API key for developer access'");
            DB::statement("ALTER TABLE users MODIFY api_key_generated_at TIMESTAMP NULL COMMENT 'Timestamp when API key was generated'");
            DB::statement("ALTER TABLE users MODIFY api_access_level ENUM('read', 'write', 'admin') NULL DEFAULT 'read' COMMENT 'API access level for developers'");
            DB::statement("ALTER TABLE users MODIFY default_environment ENUM('local', 'staging', 'production') NULL DEFAULT 'local' COMMENT 'Default environment for developers'");
            DB::statement("ALTER TABLE users MODIFY debug_mode BOOLEAN DEFAULT FALSE COMMENT 'Debug mode setting for developers'");
            DB::statement("ALTER TABLE users MODIFY system_alerts BOOLEAN DEFAULT TRUE COMMENT 'System alerts setting for developers'");
            DB::statement("ALTER TABLE users MODIFY api_change_notifications BOOLEAN DEFAULT TRUE COMMENT 'API change notifications for developers'");
            DB::statement("ALTER TABLE users MODIFY security_alerts BOOLEAN DEFAULT TRUE COMMENT 'Security alerts for developers'");
            
            // Existing tenant fields comments
            DB::statement("ALTER TABLE users MODIFY employment_status ENUM('employed', 'self_employed', 'student', 'unemployed', 'retired') NULL COMMENT 'Employment status'");
            DB::statement("ALTER TABLE users MODIFY monthly_income DECIMAL(12,2) NULL COMMENT 'Monthly income'");
            DB::statement("ALTER TABLE users MODIFY company_name VARCHAR(255) NULL COMMENT 'Company name'");
            DB::statement("ALTER TABLE users MODIFY job_title VARCHAR(255) NULL COMMENT 'Job title'");
            
            // Document fields
            DB::statement("ALTER TABLE users MODIFY tenant_documents JSON NULL COMMENT 'Tenant documents'");
            DB::statement("ALTER TABLE users MODIFY id_documents JSON NULL COMMENT 'ID documents'");
            DB::statement("ALTER TABLE users MODIFY employment_documents JSON NULL COMMENT 'Employment documents'");
            DB::statement("ALTER TABLE users MODIFY bank_documents JSON NULL COMMENT 'Bank documents'");
            DB::statement("ALTER TABLE users MODIFY reference_documents JSON NULL COMMENT 'Reference documents'");
            DB::statement("ALTER TABLE users MODIFY other_documents JSON NULL COMMENT 'Other documents'");

            // Status field
            DB::statement("ALTER TABLE users MODIFY status ENUM('pending', 'active', 'suspended', 'inactive', 'verification_required') DEFAULT 'active' COMMENT 'User account status'");

            // Supporting tables comments
            DB::statement("ALTER TABLE password_history COMMENT = 'Stores password history for users to prevent password reuse'");
            DB::statement("ALTER TABLE user_activity_logs COMMENT = 'Logs user activities for audit trail'");
            DB::statement("ALTER TABLE user_verification_attempts COMMENT = 'Tracks verification code attempts'");
            DB::statement("ALTER TABLE user_login_history COMMENT = 'Detailed login history for security monitoring'");
            DB::statement("ALTER TABLE user_profile_updates COMMENT = 'Tracks profile changes for audit trail'");
            
            // ✅ ADDED: New tables comments
            DB::statement("ALTER TABLE user_notification_settings COMMENT = 'User notification preferences and settings'");
            DB::statement("ALTER TABLE user_security_settings COMMENT = 'User security preferences and settings'");
            DB::statement("ALTER TABLE user_api_usage COMMENT = 'API usage tracking for developers'");
            DB::statement("ALTER TABLE user_id_documents COMMENT = 'User ID document verification tracking'");
            DB::statement("ALTER TABLE user_active_sessions COMMENT = 'Active user sessions for multi-device management'");
            DB::statement("ALTER TABLE user_profile_completion COMMENT = 'User profile completion tracking and calculation'");
            DB::statement("ALTER TABLE user_statistics COMMENT = 'User statistics and activity metrics'");
            DB::statement("ALTER TABLE user_invitations COMMENT = 'User invitations management'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to avoid foreign key constraints
        Schema::dropIfExists('user_statistics');
        Schema::dropIfExists('user_profile_completion');
        Schema::dropIfExists('user_active_sessions');
        Schema::dropIfExists('user_id_documents');
        Schema::dropIfExists('user_api_usage');
        Schema::dropIfExists('user_security_settings');
        Schema::dropIfExists('user_notification_settings');
        Schema::dropIfExists('user_invitations');
        Schema::dropIfExists('user_profile_updates');
        Schema::dropIfExists('user_login_history');
        Schema::dropIfExists('user_verification_attempts');
        Schema::dropIfExists('user_activity_logs');
        Schema::dropIfExists('password_history');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};