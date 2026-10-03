<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            
            // Identification
            $table->string('reference_id')->unique();
            
            // User Information
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Email Details
            $table->string('to_email');
            $table->string('from_email');
            $table->string('subject');
            $table->text('message')->nullable();
            $table->string('template')->nullable();
            
            // Type and Status
            $table->enum('type', [
                'transactional',
                'marketing',
                'notification',
                'system',
                'bulk'
            ])->default('transactional');
            
            $table->enum('status', [
                'pending',
                'sent',
                'delivered',
                'opened',
                'clicked',
                'failed',
                'bounced',
                'complained',
                'unsubscribed'
            ])->default('pending');
            
            // Timestamps for tracking
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Failure Information
            $table->text('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            
            // Priority
            $table->enum('priority', [
                'high',
                'normal',
                'low'
            ])->default('normal');
            
            // Attachments and Metadata
            $table->json('attachments')->nullable();
            $table->json('headers')->nullable();
            $table->json('metadata')->nullable();
            
            // Campaign and Batch
            $table->string('campaign_id')->nullable();
            $table->string('batch_id')->nullable();
            
            // Provider Information
            $table->enum('provider', [
                'smtp',
                'mailgun',
                'sendgrid',
                'ses',
                'postmark',
                'system'
            ])->default('smtp');
            
            $table->string('provider_message_id')->nullable();
            $table->decimal('cost', 10, 4)->nullable();
            
            // Tracking
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['to_email', 'status']);
            $table->index('status');
            $table->index('type');
            $table->index('provider');
            $table->index('sent_at');
            $table->index('created_at');
            $table->index('campaign_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};