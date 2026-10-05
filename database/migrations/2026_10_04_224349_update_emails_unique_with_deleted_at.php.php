<?php

// database/migrations/xxxx_update_emails_unique_with_deleted_at.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old composite unique
        DB::statement("ALTER TABLE `emails` DROP INDEX `emails_account_message_unique`");

        // Re-add with deleted_at — MySQL treats NULL as distinct,
        // so a soft-deleted row (deleted_at = '2026-...') no longer
        // collides with a live row (deleted_at = NULL).
        DB::statement("
            ALTER TABLE `emails`
            ADD UNIQUE KEY `emails_account_message_unique`
                (`user_email_account_id`, `message_id`, `deleted_at`)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `emails` DROP INDEX `emails_account_message_unique`");
        DB::statement("
            ALTER TABLE `emails`
            ADD UNIQUE KEY `emails_account_message_unique`
                (`user_email_account_id`, `message_id`)
        ");
    }
};