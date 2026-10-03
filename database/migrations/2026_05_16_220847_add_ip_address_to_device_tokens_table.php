<?php
// database/migrations/2026_05_16_000000_add_ip_address_to_device_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            // Add ip_address column if it doesn't exist
            if (!Schema::hasColumn('device_tokens', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('device_browser');
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('device_tokens', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }
};