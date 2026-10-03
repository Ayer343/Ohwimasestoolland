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
        Schema::table('system_settings', function (Blueprint $table) {
            // Default SMS sender ID (11 chars max, same rule as
            // SmsProviderController and the provider-level env keys).
            // Nullable because it's an admin convenience fallback —
            // the developer's per-provider value always takes priority.
            $table->string('sms_sender_id', 11)
                ->nullable()
                ->after('system_favicon')
                ->comment('Admin-editable default SMS sender ID. Overridden by per-provider sender IDs.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('sms_sender_id');
        });
    }
};