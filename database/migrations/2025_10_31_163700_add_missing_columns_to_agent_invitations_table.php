<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_invitations', function (Blueprint $table) {
            // ✅ Add missing columns for multi-channel support
            if (!Schema::hasColumn('agent_invitations', 'sent_via')) {
                $table->string('sent_via')->nullable()->after('provider')->comment('Channel used for sending: sms, whatsapp, email');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'channel_data')) {
                $table->json('channel_data')->nullable()->after('sent_via')->comment('Additional data for specific channels');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'email_sent_successfully')) {
                $table->boolean('email_sent_successfully')->nullable()->after('channel_data')->comment('Track email delivery status');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable()->after('email_sent_successfully');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'email_address')) {
                $table->string('email_address')->nullable()->after('email_sent_at')->comment('Email address used for invitation');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'email_message')) {
                $table->text('email_message')->nullable()->after('email_address')->comment('Email content sent');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'email_error')) {
                $table->text('email_error')->nullable()->after('email_message')->comment('Email delivery error if any');
            }
            
            // ✅ Add index for better performance
            $table->index(['status', 'expires_at']);
            $table->index(['agent_id', 'plan_id']);
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_invitations', function (Blueprint $table) {
            // Remove columns in reverse order
            $table->dropColumn([
                'email_error',
                'email_message',
                'email_address',
                'email_sent_at',
                'email_sent_successfully',
                'channel_data',
                'sent_via'
            ]);
            
            // Drop indexes
            $table->dropIndex(['status', 'expires_at']);
            $table->dropIndex(['agent_id', 'plan_id']);
            $table->dropIndex(['token']);
        });
    }
};