<?php
// database/migrations/2026_08_18_add_last_sent_at_to_user_email_accounts.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_email_accounts', function (Blueprint $table) {
            // Add missing columns
            if (!Schema::hasColumn('user_email_accounts', 'last_sent_at')) {
                $table->timestamp('last_sent_at')->nullable()->after('last_sync_at');
            }
            
            if (!Schema::hasColumn('user_email_accounts', 'emails_sent_today')) {
                $table->integer('emails_sent_today')->default(0)->after('last_sent_at');
            }
            
            if (!Schema::hasColumn('user_email_accounts', 'emails_received_today')) {
                $table->integer('emails_received_today')->default(0)->after('emails_sent_today');
            }
            
            if (!Schema::hasColumn('user_email_accounts', 'daily_send_limit')) {
                $table->integer('daily_send_limit')->default(500)->after('emails_received_today');
            }
            
            if (!Schema::hasColumn('user_email_accounts', 'daily_receive_limit')) {
                $table->integer('daily_receive_limit')->default(1000)->after('daily_send_limit');
            }
        });
    }

    public function down()
    {
        Schema::table('user_email_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'last_sent_at',
                'emails_sent_today',
                'emails_received_today',
                'daily_send_limit',
                'daily_receive_limit'
            ]);
        });
    }
};