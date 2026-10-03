<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Allow guest messages (no user_id)
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Identify guest sessions
            $table->string('guest_token', 64)->nullable()->index()->after('user_id');
            $table->string('guest_ip', 45)->nullable()->after('guest_token');
            $table->string('guest_user_agent', 500)->nullable()->after('guest_ip');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['guest_token', 'guest_ip', 'guest_user_agent']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};