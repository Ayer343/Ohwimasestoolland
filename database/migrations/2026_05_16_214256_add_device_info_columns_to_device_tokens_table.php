<?php
// database/migrations/2026_05_16_000000_add_device_info_columns_to_device_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('device_tokens', 'device_type')) {
                $table->string('device_type')->nullable()->after('device_name');
            }
            if (!Schema::hasColumn('device_tokens', 'device_platform')) {
                $table->string('device_platform')->nullable()->after('device_type');
            }
            if (!Schema::hasColumn('device_tokens', 'device_browser')) {
                $table->string('device_browser')->nullable()->after('device_platform');
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['device_type', 'device_platform', 'device_browser']);
        });
    }
};