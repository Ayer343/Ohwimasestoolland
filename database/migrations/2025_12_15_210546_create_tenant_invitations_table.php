<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            
            // Main relationships - USING USER MODEL
            $table->unsignedBigInteger('user_id'); // Changed from tenant_id to user_id
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('unit_id')->nullable(); // REMOVED: after('property_id')
            $table->unsignedBigInteger('invited_by');
            
            // For backwards compatibility - keep tenant_id but make it optional
            $table->unsignedBigInteger('tenant_id')->nullable(); // Made nullable
            
            // Invitation details
            $table->json('channels'); // ['sms', 'email', 'whatsapp']
            $table->text('custom_message')->nullable(); // Added for custom messages
            $table->string('token')->unique();
            $table->enum('status', ['pending', 'sent', 'failed', 'completed', 'expired', 'cancelled'])->default('pending');
            $table->json('sent_channels')->nullable(); // Channels that were successfully sent
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Fixed: should be nullable, will be set in model
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); // Added for cancellation timestamp
            $table->unsignedBigInteger('cancelled_by')->nullable(); // Added for who cancelled
            
            // User registration tracking
            $table->unsignedBigInteger('registered_user_id')->nullable(); // For backwards compatibility
            
            // Failure tracking
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable(); // Additional data like message content, response details
            
            // Soft deletes and timestamps
            $table->softDeletes();
            $table->timestamps();

            // Foreign key constraints - UPDATED FOR USER MODEL
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade'); // Changed to users
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('property_units')->onDelete('cascade'); // ADDED: Correct foreign key
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('registered_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null'); // Added
            
            // Keep tenant foreign key for backwards compatibility
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade')->nullable();

            // Indexes for performance
            $table->index('user_id'); // Changed from tenant_id
            $table->index('property_id');
            $table->index('unit_id'); // ADDED: Index for unit_id
            $table->index('invited_by');
            $table->index('tenant_id'); // Keep for backwards compatibility
            $table->index('token');
            $table->index('status');
            $table->index('expires_at');
            $table->index('created_at');
            $table->index(['user_id', 'property_id', 'status']); // Composite index for common queries
            $table->index('cancelled_at');
            $table->index('sent_at');
            
            // Unique constraint to prevent duplicate active invitations
            $table->unique(['user_id', 'property_id', 'token']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_invitations');
    }
};