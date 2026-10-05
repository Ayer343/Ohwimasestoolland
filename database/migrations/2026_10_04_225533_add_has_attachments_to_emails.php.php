<?php

// database/migrations/2026_10_04_231000_add_has_attachments_to_emails.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('emails', 'has_attachments')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->boolean('has_attachments')
                      ->default(false)
                      ->after('attachment_count');
            });

            // Backfill from existing rows
            DB::statement("
                UPDATE `emails`
                SET `has_attachments` = CASE
                    WHEN `attachment_count` > 0 THEN 1
                    ELSE 0
                END
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('emails', 'has_attachments')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->dropColumn('has_attachments');
            });
        }
    }
};