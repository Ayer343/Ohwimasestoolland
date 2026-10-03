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
        Schema::create('user_email_accounts', function (Blueprint $table) {
            $table->id();
            
            // ==================== RELATIONSHIP ====================
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->comment('References the user who owns this email account');
            
            // ==================== EMAIL ACCOUNT DETAILS ====================
            $table->string('email')
                  ->unique()
                  ->comment('The email address used for sending/receiving emails');
            
            $table->string('display_name')
                  ->nullable()
                  ->comment('Display name shown when sending emails (e.g., "John Doe - Property Manager")');
            
            $table->string('reply_to_email')
                  ->nullable()
                  ->comment('Alternative reply-to email address');
            
            $table->string('reply_to_name')
                  ->nullable()
                  ->comment('Alternative reply-to display name');
            
            // ==================== IMAP SETTINGS (Receiving Emails) ====================
            $table->string('imap_host')
                  ->nullable()
                  ->comment('IMAP server hostname (e.g., imap.gmail.com)');
            
            $table->integer('imap_port')
                  ->default(993)
                  ->comment('IMAP server port (993 for SSL, 143 for TLS)');
            
            $table->enum('imap_encryption', ['ssl', 'tls', 'none'])
                  ->default('ssl')
                  ->comment('IMAP encryption type');
            
            $table->boolean('imap_validate_cert')
                  ->default(true)
                  ->comment('Whether to validate SSL/TLS certificates');
            
            $table->integer('imap_timeout')
                  ->default(30)
                  ->comment('IMAP connection timeout in seconds');
            
            $table->string('imap_folder')
                  ->default('INBOX')
                  ->comment('Default IMAP folder to sync');
            
            // ==================== SMTP SETTINGS (Sending Emails) ====================
            $table->string('smtp_host')
                  ->nullable()
                  ->comment('SMTP server hostname (e.g., smtp.gmail.com)');
            
            $table->integer('smtp_port')
                  ->default(587)
                  ->comment('SMTP server port (587 for TLS, 465 for SSL, 25 for none)');
            
            $table->enum('smtp_encryption', ['ssl', 'tls', 'none'])
                  ->default('tls')
                  ->comment('SMTP encryption type');
            
            $table->boolean('smtp_validate_cert')
                  ->default(true)
                  ->comment('Whether to validate SSL/TLS certificates');
            
            $table->integer('smtp_timeout')
                  ->default(30)
                  ->comment('SMTP connection timeout in seconds');
            
            // ==================== AUTHENTICATION ====================
            $table->text('encrypted_password')
                  ->comment('Encrypted email account password');
            
            $table->string('auth_method')
                  ->default('password')
                  ->comment('Authentication method: password, oauth2, etc.');
            
            $table->json('oauth_tokens')
                  ->nullable()
                  ->comment('OAuth tokens for providers like Gmail, Outlook');
            
            // ==================== PROVIDER-SPECIFIC SETTINGS ====================
            $table->enum('provider', [
                'gmail', 
                'outlook', 
                'yahoo', 
                'custom'
            ])->default('custom')
              ->comment('Email provider for pre-configured settings');
            
            // Gmail-specific
            $table->string('gmail_user_id')
                  ->nullable()
                  ->comment('Gmail user ID for API access');
            
            $table->string('gmail_refresh_token')
                  ->nullable()
                  ->comment('Gmail refresh token for long-term access');
            
            // Outlook-specific
            $table->string('outlook_tenant_id')
                  ->nullable()
                  ->comment('Outlook tenant ID for Microsoft Graph API');
            
            // ==================== SYNC SETTINGS ====================
            $table->enum('sync_frequency', [
                'realtime', 
                'every_minute', 
                'every_five_minutes', 
                'every_fifteen_minutes', 
                'every_thirty_minutes', 
                'hourly', 
                'manual'
            ])->default('every_five_minutes')
              ->comment('How often to sync emails');
            
            $table->integer('max_emails_per_sync')
                  ->default(100)
                  ->comment('Maximum emails to fetch per sync cycle');
            
            $table->integer('sync_days_back')
                  ->default(30)
                  ->comment('Number of days to sync emails from');
            
            $table->timestamp('last_sync_at')
                  ->nullable()
                  ->comment('Last successful sync timestamp');
            
            $table->timestamp('next_sync_at')
                  ->nullable()
                  ->comment('Next scheduled sync timestamp');
            
            // ==================== FOLDER MAPPINGS ====================
            $table->json('folder_mappings')
                  ->nullable()
                  ->comment('Custom folder mappings (e.g., {"INBOX": "Inbox", "SENT": "Sent"})');
            
            $table->json('sync_folders')
                  ->nullable()
                  ->comment('Which folders to sync (default: ["INBOX", "SENT", "DRAFTS"])');
            
            $table->json('exclude_folders')
                  ->nullable()
                  ->comment('Folders to exclude from sync (e.g., ["SPAM", "TRASH"])');
            
            // ==================== STATUS & VERIFICATION ====================
            $table->enum('status', [
                'pending', 
                'verified', 
                'failed', 
                'suspended', 
                'expired'
            ])->default('pending')
              ->comment('Account verification status');
            
            $table->timestamp('verified_at')
                  ->nullable()
                  ->comment('When the account was successfully verified');
            
            $table->text('verification_error')
                  ->nullable()
                  ->comment('Error message if verification failed');
            
            $table->integer('verification_attempts')
                  ->default(0)
                  ->comment('Number of verification attempts made');
            
            $table->timestamp('last_verification_attempt_at')
                  ->nullable()
                  ->comment('Last verification attempt timestamp');
            
            // ==================== USAGE LIMITS ====================
            $table->integer('daily_send_limit')
                  ->default(500)
                  ->comment('Maximum emails that can be sent per day');
            
            $table->integer('daily_receive_limit')
                  ->default(1000)
                  ->comment('Maximum emails that can be received per day');
            
            $table->integer('emails_sent_today')
                  ->default(0)
                  ->comment('Counter for emails sent today');
            
            $table->integer('emails_received_today')
                  ->default(0)
                  ->comment('Counter for emails received today');
            
            $table->timestamp('daily_limit_reset_at')
                  ->nullable()
                  ->comment('When the daily counters should reset');
            
            // ==================== SECURITY ====================
            $table->boolean('is_primary')
                  ->default(false)
                  ->comment('Whether this is the user\'s primary email account');
            
            $table->boolean('enable_auto_reply')
                  ->default(false)
                  ->comment('Whether auto-reply is enabled');
            
            $table->text('auto_reply_message')
                  ->nullable()
                  ->comment('Auto-reply message template');
            
            $table->json('auto_reply_conditions')
                  ->nullable()
                  ->comment('Conditions for auto-reply (e.g., specific senders, subjects)');
            
            $table->boolean('enable_signature')
                  ->default(false)
                  ->comment('Whether to add a signature to outgoing emails');
            
            $table->text('signature')
                  ->nullable()
                  ->comment('Email signature HTML content');
            
            // ==================== NOTIFICATIONS ====================
            $table->boolean('notify_on_new_email')
                  ->default(true)
                  ->comment('Send notification when new email arrives');
            
            $table->boolean('notify_on_send_failure')
                  ->default(true)
                  ->comment('Send notification when email fails to send');
            
            $table->json('notification_preferences')
                  ->nullable()
                  ->comment('Advanced notification preferences');
            
            // ==================== METADATA ====================
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional metadata for the email account');
            
            $table->json('settings')
                  ->nullable()
                  ->comment('Additional settings as JSON');
            
            // ==================== CONNECTION STATUS ====================
            $table->boolean('is_connected')
                  ->default(false)
                  ->comment('Whether the account is currently connected');
            
            $table->timestamp('last_connected_at')
                  ->nullable()
                  ->comment('Last successful connection timestamp');
            
            $table->text('last_connection_error')
                  ->nullable()
                  ->comment('Last connection error message');
            
            // ==================== AUDIT TRAIL ====================
            $table->timestamp('password_changed_at')
                  ->nullable()
                  ->comment('When the password was last changed');
            
            $table->timestamp('settings_changed_at')
                  ->nullable()
                  ->comment('When the settings were last changed');
            
            // ==================== STANDARD TIMESTAMPS ====================
            $table->timestamps();
            $table->softDeletes();
            
            // ==================== INDEXES ====================
            // Basic indexes
            $table->index('user_id', 'idx_email_accounts_user');
            $table->index('email', 'idx_email_accounts_email');
            $table->index('status', 'idx_email_accounts_status');
            $table->index('provider', 'idx_email_accounts_provider');
            $table->index('is_primary', 'idx_email_accounts_primary');
            $table->index('last_sync_at', 'idx_email_accounts_last_sync');
            $table->index('next_sync_at', 'idx_email_accounts_next_sync');
            
            // Composite indexes
            $table->index(['user_id', 'status', 'is_primary'], 'idx_email_accounts_user_status_primary');
            $table->index(['status', 'next_sync_at'], 'idx_email_accounts_status_next_sync');
            $table->index(['user_id', 'is_primary'], 'idx_email_accounts_user_primary');
            $table->index(['provider', 'status'], 'idx_email_accounts_provider_status');
            $table->index(['user_id', 'last_sync_at'], 'idx_email_accounts_user_last_sync');
            $table->index(['user_id', 'provider'], 'idx_email_accounts_user_provider');
            $table->index(['email', 'status'], 'idx_email_accounts_email_status');
            $table->index(['user_id', 'deleted_at'], 'idx_email_accounts_user_deleted');
            
            // ==================== UNIQUE CONSTRAINTS ====================
            $table->unique(['user_id', 'email'], 'unique_user_email');
            $table->unique(['user_id', 'is_primary'], 'unique_user_primary_email');
        });

        // ==================== TABLE COMMENTS ====================
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_email_accounts COMMENT = 'Stores external email accounts linked by users for sending/receiving emails within the application'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_email_accounts');
    }
};