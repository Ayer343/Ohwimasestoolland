<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure the two surviving rows have unique (user_id, token) pairs.
        // (They do — we verified — but this guards against future drift.)
        $dupes = DB::table('device_tokens')
            ->select('user_id', 'token', DB::raw('COUNT(*) as c'))
            ->groupBy('user_id', 'token')
            ->having('c', '>', 1)
            ->get();

        if ($dupes->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot add UNIQUE index — duplicate (user_id, token) rows exist. Clean them first.'
            );
        }

        Schema::table('device_tokens', function (Blueprint $table) {
            // Prevent duplicate device rows for the same user+token
            $table->unique(
                ['user_id', 'token'],
                'device_tokens_user_token_unique'
            );
        });

        // Backfill any NULL created_at values with updated_at (or now)
        DB::statement("
            UPDATE device_tokens
            SET created_at = COALESCE(updated_at, CURRENT_TIMESTAMP)
            WHERE created_at IS NULL
        ");

        // Make created_at default to CURRENT_TIMESTAMP
        DB::statement("
            ALTER TABLE device_tokens
            MODIFY created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique('device_tokens_user_token_unique');
        });

        DB::statement('
            ALTER TABLE device_tokens
            MODIFY created_at TIMESTAMP NULL DEFAULT NULL
        ');
    }
};