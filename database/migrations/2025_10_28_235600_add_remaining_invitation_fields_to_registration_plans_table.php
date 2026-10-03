<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRemainingInvitationFieldsToRegistrationPlansTable extends Migration
{
    public function up()
    {
        Schema::table('registration_plans', function (Blueprint $table) {
            // Add only the missing columns
            if (!Schema::hasColumn('registration_plans', 'email_attempts')) {
                $table->integer('email_attempts')->default(0)->after('whatsapp_attempts');
            }
            
            if (!Schema::hasColumn('registration_plans', 'invitation_channels')) {
                $table->json('invitation_channels')->nullable()->after('invitation_provider');
            }
            
            if (!Schema::hasColumn('registration_plans', 'preferred_channel')) {
                $table->string('preferred_channel')->default('sms')->after('email_attempts');
            }
            
            if (!Schema::hasColumn('registration_plans', 'last_whatsapp_attempt_at')) {
                $table->timestamp('last_whatsapp_attempt_at')->nullable()->after('last_sms_attempt_at');
            }
            
            if (!Schema::hasColumn('registration_plans', 'last_email_attempt_at')) {
                $table->timestamp('last_email_attempt_at')->nullable()->after('last_whatsapp_attempt_at');
            }
            
            if (!Schema::hasColumn('registration_plans', 'invitation_expires_at')) {
                $table->timestamp('invitation_expires_at')->nullable()->after('preferred_channel');
            }
        });
    }

    public function down()
    {
        Schema::table('registration_plans', function (Blueprint $table) {
            $table->dropColumn([
                'email_attempts',
                'invitation_channels',
                'preferred_channel',
                'last_whatsapp_attempt_at',
                'last_email_attempt_at',
                'invitation_expires_at'
            ]);
        });
    }
}