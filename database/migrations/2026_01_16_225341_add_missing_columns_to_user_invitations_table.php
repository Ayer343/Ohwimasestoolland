<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            // Add failure_reason column if it doesn't exist
            if (!Schema::hasColumn('user_invitations', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('status');
            }
            
            // Add sent_at column if it doesn't exist
            if (!Schema::hasColumn('user_invitations', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('expires_at');
            }
            
            // Add accepted_at column if it doesn't exist
            if (!Schema::hasColumn('user_invitations', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('sent_at');
            }
            
            // Add viewed_at column if it doesn't exist
            if (!Schema::hasColumn('user_invitations', 'viewed_at')) {
                $table->timestamp('viewed_at')->nullable()->after('accepted_at');
            }
        });
    }

    public function down()
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropColumn(['failure_reason', 'sent_at', 'accepted_at', 'viewed_at']);
        });
    }
};