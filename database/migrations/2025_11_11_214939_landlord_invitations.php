<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('landlord_invitations', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            
            // Invitation details
            $table->string('token', 64)->unique();
            $table->json('channels')->nullable();
            $table->enum('status', [
                'pending',
                'sent', 
                'accepted',
                'expired',
                'failed',
                'cancelled'
            ])->default('pending');
            
            $table->enum('invitation_type', [
                'registration',
                'property_added', 
                'welcome'
            ])->default('registration');
            
            $table->text('custom_message')->nullable();
            
            // ✅ ADDED: Metadata column to store additional invitation data
            $table->json('metadata')->nullable()->comment('Stores additional invitation metadata like sms_precheck, requested_channels, etc.');
            
            // Timestamps
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            
            // Tracking
            $table->unsignedInteger('attempts')->default(0);
            $table->text('failure_reason')->nullable();
            
            // ✅ ADDED: Track response/click data
            $table->dateTime('first_viewed_at')->nullable()->comment('When landlord first viewed the invitation');
            $table->dateTime('last_viewed_at')->nullable()->comment('When landlord last viewed the invitation');
            $table->unsignedInteger('view_count')->default(0)->comment('How many times the invitation was viewed');
            $table->string('ip_address', 45)->nullable()->comment('IP address from which invitation was accepted');
            $table->string('user_agent')->nullable()->comment('Browser/device info');
            
            // Soft deletes
            $table->softDeletes();
            $table->timestamps();

            // Indexes - Optimized for common queries
            $table->index(['token']);
            $table->index(['status', 'expires_at']);
            $table->index(['landlord_id', 'status']);
            $table->index(['property_id', 'landlord_id']);
            $table->index(['invited_by', 'created_at']);
            $table->index(['expires_at', 'status']);
            $table->index(['sent_at', 'status']);
            $table->index(['accepted_at']);
            $table->index(['created_at']);
            
            // ✅ ADDED: New indexes for better performance
            $table->index(['token', 'status'], 'token_status_index');
            $table->index(['landlord_id', 'property_id', 'status'], 'landlord_property_status_index');
            $table->index(['expires_at', 'status', 'invitation_type'], 'expiry_status_type_index');
            $table->index(['created_at', 'status'], 'created_status_index');
            
            // ✅ ADDED: Index for soft delete queries
            $table->index(['deleted_at', 'status']);
            
            // ✅ ADDED: Composite index for dashboard queries
            $table->index(['invited_by', 'status', 'created_at'], 'inviter_dashboard_index');
            
            // ✅ ADDED: Index for analytics
            $table->index(['invitation_type', 'status', 'accepted_at'], 'analytics_type_status_index');
            
            // ✅ ADDED: Ensure we have an index for the exact error scenario
            $table->index(['property_id', 'landlord_id', 'status', 'expires_at'], 'active_invitation_check');
        });
        
        // ✅ ADDED: Optional - Insert comment for table documentation
        DB::statement("ALTER TABLE landlord_invitations COMMENT = 'Stores landlord invitation records for property registration and onboarding'");
    }

    public function down()
    {
        Schema::dropIfExists('landlord_invitations');
    }
};