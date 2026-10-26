<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ---- 1. Add device_token_hash if missing ----
        if (!Schema::hasColumn('trusted_devices', 'device_token_hash')) {
            Schema::table('trusted_devices', function (Blueprint $table) {
                $table->string('device_token_hash', 64)
                    ->nullable()
                    ->after('user_id');
            });
        }

        // ---- 2. Ensure user_agent / ip_address are nullable ----
        //     (column change — only touch them if they need it)
        //     If your table already has them as nullable this is a no-op.
        //     Skipped here to avoid churn — your schema already shows them.

        // ---- 3. Add the composite index if missing ----
        $indexExists = collect(DB::select("SHOW INDEX FROM trusted_devices"))
            ->contains(fn ($row) => ($row->Key_name ?? null) === 'trusted_devices_user_hash_idx');

        if (!$indexExists) {
            Schema::table('trusted_devices', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'device_token_hash'],
                    'trusted_devices_user_hash_idx'
                );
            });
        }

        // NOTE: no backfill from a legacy `device_token` column —
        // that column never existed on this schema. Any pre-existing
        // trust rows are keyed by `device_fingerprint` and will be
        // handled by the app-level migration below if desired.
    }

    public function down(): void
    {
        $indexExists = collect(DB::select("SHOW INDEX FROM trusted_devices"))
            ->contains(fn ($row) => ($row->Key_name ?? null) === 'trusted_devices_user_hash_idx');

        if ($indexExists) {
            Schema::table('trusted_devices', function (Blueprint $table) {
                $table->dropIndex('trusted_devices_user_hash_idx');
            });
        }

        if (Schema::hasColumn('trusted_devices', 'device_token_hash')) {
            Schema::table('trusted_devices', function (Blueprint $table) {
                $table->dropColumn('device_token_hash');
            });
        }
    }
};