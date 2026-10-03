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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            
            // ==================== RELATIONSHIPS ====================
            $table->foreignId('user_email_account_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->comment('References the email account this email belongs to');
            
            $table->unsignedBigInteger('user_id')
                  ->nullable()
                  ->comment('The user who owns this email (denormalized for faster queries)');
            
            // ==================== EMAIL HEADERS ====================
            $table->string('message_id')
                  ->unique()
                  ->comment('Unique message ID from the email server');
            
            $table->string('subject')
                  ->nullable()
                  ->comment('Email subject line');
            
            $table->longText('body')
                  ->nullable()
                  ->comment('Plain text body of the email');
            
            $table->longText('html_body')
                  ->nullable()
                  ->comment('HTML formatted body of the email');
            
            $table->text('body_preview')
                  ->nullable()
                  ->comment('Preview/snippet of the email body (first 200 characters)');
            
            // ==================== SENDER INFORMATION ====================
            $table->string('from_email');
            $table->string('from_name')->nullable();
            
            // ==================== RECIPIENT INFORMATION ====================
            $table->string('to_email');
            $table->string('to_name')->nullable();
            
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            
            // ==================== REPLY INFORMATION ====================
            $table->string('reply_to_email')->nullable();
            $table->string('reply_to_name')->nullable();
            
            // ==================== EMAIL METADATA ====================
            $table->enum('direction', ['sent', 'received'])
                  ->default('received')
                  ->comment('Direction of the email: sent or received');
            
            $table->boolean('is_read')->default(false);
            $table->boolean('is_replied')->default(false);
            $table->boolean('is_forwarded')->default(false);
            $table->boolean('is_important')->default(false);
            $table->boolean('is_spam')->default(false);
            $table->boolean('is_trashed')->default(false);
            $table->boolean('is_draft')->default(false);
            
            // ==================== FOLDER & THREADING ====================
            $table->string('folder')
                  ->default('INBOX')
                  ->comment('Email folder (INBOX, SENT, DRAFTS, etc.)');
            
            $table->string('thread_id')
                  ->nullable()
                  ->comment('Thread ID for grouping related emails');
            
            $table->unsignedBigInteger('parent_id')
                  ->nullable()
                  ->comment('Parent email ID for replies/forwards');
            
            // ==================== REFERENCES ====================
            $table->text('in_reply_to')->nullable();
            $table->text('references')->nullable();
            
            // ==================== TIMESTAMPS ====================
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            
            // ==================== PRIORITY & FLAGS ====================
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->json('flags')->nullable()->comment('Additional email flags');
            
            // ==================== METADATA ====================
            $table->json('metadata')->nullable();
            $table->json('headers')->nullable();
            $table->json('attachments_info')->nullable()->comment('Summary of attachments');
            $table->integer('attachment_count')->default(0);
            
            // ==================== SIZE ====================
            $table->bigInteger('size_bytes')->nullable()->comment('Email size in bytes');
            
            // ==================== STANDARD TIMESTAMPS ====================
            $table->timestamps();
            $table->softDeletes();
            
            // ==================== INDEXES ====================
            // Foreign key indexes
            $table->index('user_email_account_id', 'idx_emails_account_id');
            $table->index('user_id', 'idx_emails_user_id');
            
            // Query indexes
            $table->index('message_id', 'idx_emails_message_id');
            $table->index('folder', 'idx_emails_folder');
            $table->index('thread_id', 'idx_emails_thread_id');
            $table->index('parent_id', 'idx_emails_parent_id');
            
            // Status indexes
            $table->index('direction', 'idx_emails_direction');
            $table->index('is_read', 'idx_emails_is_read');
            $table->index('is_spam', 'idx_emails_is_spam');
            $table->index('is_trashed', 'idx_emails_is_trashed');
            $table->index('is_draft', 'idx_emails_is_draft');
            
            // Timestamp indexes
            $table->index('sent_at', 'idx_emails_sent_at');
            $table->index('received_at', 'idx_emails_received_at');
            $table->index('read_at', 'idx_emails_read_at');
            $table->index('created_at', 'idx_emails_created_at');
            
            // Priority index
            $table->index('priority', 'idx_emails_priority');
            
            // Composite indexes for common queries
            $table->index(['user_email_account_id', 'folder'], 'idx_emails_account_folder');
            $table->index(['user_email_account_id', 'is_read'], 'idx_emails_account_unread');
            $table->index(['user_email_account_id', 'direction'], 'idx_emails_account_direction');
            $table->index(['user_email_account_id', 'sent_at'], 'idx_emails_account_sent_at');
            $table->index(['user_email_account_id', 'created_at'], 'idx_emails_account_created');
            $table->index(['thread_id', 'user_email_account_id'], 'idx_emails_thread_account');
            $table->index(['folder', 'is_read', 'user_email_account_id'], 'idx_emails_folder_unread_account');
            
            // Search indexes
            $table->index('from_email', 'idx_emails_from_email');
            $table->index('to_email', 'idx_emails_to_email');
            $table->index('subject', 'idx_emails_subject');
            
            // Deleted at index
            $table->index('deleted_at', 'idx_emails_deleted_at');
        });

        // ==================== TABLE COMMENTS ====================
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE emails COMMENT = 'Stores all emails sent and received through linked email accounts'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};