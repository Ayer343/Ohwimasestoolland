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
        Schema::table('device_tokens', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('device_tokens', 'session_id')) {
                $table->string('session_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('device_tokens', 'platform')) {
                $table->string('platform')->nullable()->after('device_name');
            }
            if (!Schema::hasColumn('device_tokens', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('platform');
            }
            if (!Schema::hasColumn('device_tokens', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('last_used_at');
            }
            
            // Add indexes
            $table->index('session_id', 'idx_device_tokens_session');
            $table->index('platform', 'idx_device_tokens_platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'platform', 'last_used_at', 'updated_at']);
            $table->dropIndex('idx_device_tokens_session');
            $table->dropIndex('idx_device_tokens_platform');
        });
    }
};