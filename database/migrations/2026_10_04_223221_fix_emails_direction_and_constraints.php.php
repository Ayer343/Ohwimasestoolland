<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────
        // 1. Align `direction` enum with the app: incoming / outgoing
        // ──────────────────────────────────────────────────────────
        DB::statement("
            UPDATE `emails` SET `direction` = 'incoming'
            WHERE `direction` = 'received'
        ");
        DB::statement("
            UPDATE `emails` SET `direction` = 'outgoing'
            WHERE `direction` = 'sent'
        ");
        DB::statement("
            ALTER TABLE `emails`
            MODIFY `direction` ENUM('incoming','outgoing')
                NOT NULL DEFAULT 'incoming'
                COMMENT 'Direction: incoming or outgoing'
        ");

        // ──────────────────────────────────────────────────────────
        // 2. Add `delivered` to the status enum (used for inbound mail)
        // ──────────────────────────────────────────────────────────
        DB::statement("
            ALTER TABLE `emails`
            MODIFY `status` ENUM('draft','sent','failed','queued','delivered')
                NOT NULL DEFAULT 'draft'
        ");

        // ──────────────────────────────────────────────────────────
        // 3. Drop over-strict global unique on message_id
        //    The (account_id, message_id) composite is what we want.
        // ──────────────────────────────────────────────────────────
        try {
            DB::statement("ALTER TABLE `emails` DROP INDEX `emails_message_id_unique`");
        } catch (\Throwable $e) {
            // Already gone — fine
        }

        // ──────────────────────────────────────────────────────────
        // 4. Keep cc/bcc JSON columns but the code must write JSON.
        //    (Handled in EmailService — see companion changes.)
        // ──────────────────────────────────────────────────────────
        // No DDL needed here.
    }

    public function down(): void
    {
        DB::statement("
            UPDATE `emails` SET `direction` = 'received'
            WHERE `direction` = 'incoming'
        ");
        DB::statement("
            UPDATE `emails` SET `direction` = 'outgoing'
            WHERE `direction` = 'outgoing'
        ");
        DB::statement("
            ALTER TABLE `emails`
            MODIFY `direction` ENUM('sent','received')
                NOT NULL DEFAULT 'received'
        ");
        DB::statement("
            ALTER TABLE `emails`
            MODIFY `status` ENUM('draft','sent','failed','queued')
                NOT NULL DEFAULT 'draft'
        ");

        try {
            DB::statement("
                ALTER TABLE `emails`
                ADD UNIQUE KEY `emails_message_id_unique` (`message_id`)
            ");
        } catch (\Throwable $e) {
            // ignore
        }
    }
};