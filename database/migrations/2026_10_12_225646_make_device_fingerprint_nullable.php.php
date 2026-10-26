<?php

// database/migrations/2026_10_12_230000_make_device_fingerprint_nullable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            // The new trusted-device scheme uses `device_token_hash` only.
            // `device_fingerprint` is left over from the earlier scheme and
            // is no longer written. Make it nullable so inserts succeed.
            $table->string('device_fingerprint', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->string('device_fingerprint', 255)->nullable(false)->change();
        });
    }
};