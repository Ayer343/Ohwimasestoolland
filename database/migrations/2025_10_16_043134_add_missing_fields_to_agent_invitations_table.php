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
            // Add missing columns that are causing errors
            if (!Schema::hasColumn('agent_invitations', 'metadata')) {
                $table->json('metadata')->nullable()->after('user_agent');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'invitation_method')) {
                $table->enum('invitation_method', ['sms', 'whatsapp', 'email', 'both'])->default('sms')->after('token');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('accepted_at');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'revoked_by')) {
                $table->foreignId('revoked_by')->nullable()->constrained('users')->onDelete('set null')->after('revoked_at');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'accepted_ip')) {
                $table->ipAddress('accepted_ip')->nullable()->after('last_sent_at');
            }
            
            if (!Schema::hasColumn('agent_invitations', 'accepted_user_agent')) {
                $table->text('accepted_user_agent')->nullable()->after('accepted_ip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_invitations', function (Blueprint $table) {
            $table->dropColumn([
                'metadata',
                'invitation_method', 
                'revoked_at',
                'revoked_by',
                'accepted_ip',
                'accepted_user_agent'
            ]);
        });
    }
};